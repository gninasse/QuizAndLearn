<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Tests\TestCase;

class KpiDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Group $groupA;

    private Group $groupB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->admin = $this->makeUser('admin');
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');

        $this->groupA = Group::create(['name' => 'A', 'is_active' => true]);
        $this->groupB = Group::create(['name' => 'B', 'is_active' => true]);
        $l1 = Learner::create(['user_id' => $this->makeUser('awa')->id, 'matricule' => 'MAT-AWA']);
        $l2 = Learner::create(['user_id' => $this->makeUser('bob')->id, 'matricule' => 'MAT-BOB']);
        $this->groupA->learners()->attach($l1->id);
        $this->groupB->learners()->attach($l2->id);
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }

    private function makeTrainer(string $handle, array $groups = []): User
    {
        $user = $this->makeUser($handle);
        Role::findOrCreate('trainer');
        $user->assignRole('trainer');
        $trainer = Trainer::create(['user_id' => $user->id]);
        foreach ($groups as $g) {
            $g->trainers()->attach($trainer->id);
        }

        return $user->fresh();
    }

    public function test_admin_gets_kpis_json(): void
    {
        $res = $this->actingAs($this->admin)->getJson(route('cores.dashboard.kpis'));

        $res->assertOk()->assertJsonStructure([
            'active_7d', 'active_30d', 'activation_rate', 'retention_30d', 'review_regularity',
            'quiz_avg_score', 'exam_avg_note', 'completion', 'failed_questions',
            'inactive', 'incidents' => ['screenshots', 'exam_captures', 'exam_navigation', 'exams_cancelled'],
            'showcase', 'weekly_active',
        ]);
        $this->assertCount(12, $res->json('weekly_active'));
    }

    public function test_trainer_foreign_group_is_403(): void
    {
        $trainer = $this->makeTrainer('prof', [$this->groupA]);

        $this->actingAs($trainer)
            ->getJson(route('cores.dashboard.kpis', ['group' => $this->groupB->id]))
            ->assertForbidden();
    }

    public function test_invalid_period_is_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('cores.dashboard.kpis', ['period' => '5']))
            ->assertStatus(422);
    }

    public function test_invalid_custom_periods_are_422(): void
    {
        $bad = [
            ['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01'],
            ['period' => 'custom', 'from' => '2025-01-01', 'to' => '2026-10-01'],
            ['period' => 'custom', 'from' => '10/01/2026', 'to' => '2026-10-05'],
            ['period' => 'custom'],
        ];
        foreach ($bad as $q) {
            $this->actingAs($this->admin)
                ->getJson(route('cores.dashboard.kpis', $q))
                ->assertStatus(422);
        }
    }

    public function test_learner_cannot_access(): void
    {
        $user = $this->makeUser('eleve');
        Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-E']);

        $this->actingAs($user)->get(route('cores.dashboard'))->assertRedirect('/');
        $this->actingAs($user)->getJson(route('cores.dashboard.kpis'))->assertForbidden();
    }

    public function test_csv_export_has_bom_semicolons_and_scope(): void
    {
        $trainer = $this->makeTrainer('prof', [$this->groupA]);

        $res = $this->actingAs($trainer)->get(route('cores.dashboard.export', 'inactive'));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('kpi-inactive-'.now()->format('Y-m-d').'.csv', $res->headers->get('Content-Disposition'));

        $body = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Apprenant;Matricule;"Dernière activité"', $body);
        $this->assertStringContainsString('MAT-AWA', $body);
        $this->assertStringNotContainsString('MAT-BOB', $body);
    }

    public function test_csv_export_neutralises_formulas(): void
    {
        $payloads = ['=1+1', '+SUM(A1)', '-2+3', '@cmd', "\tTAB", "\rCR"];
        foreach ($payloads as $i => $p) {
            Learner::create(['user_id' => $this->makeUser("evil{$i}")->id, 'matricule' => $p]);
        }

        $body = $this->actingAs($this->admin)->get(route('cores.dashboard.export', 'inactive'))->streamedContent();

        foreach ($payloads as $p) {
            $this->assertStringContainsString("'".$p, $body);
        }
        $this->assertStringContainsString(';MAT-AWA;', $body); // valeur saine inchangée
    }

    public function test_admin_csv_contains_all_learners(): void
    {
        $body = $this->actingAs($this->admin)->get(route('cores.dashboard.export', 'inactive'))->streamedContent();
        $this->assertStringContainsString('MAT-AWA', $body);
        $this->assertStringContainsString('MAT-BOB', $body);
    }

    public function test_other_csv_headers(): void
    {
        $c = $this->actingAs($this->admin)->get(route('cores.dashboard.export', 'completion'))->streamedContent();
        $this->assertStringContainsString('Type;Contenu;Assignés;Terminé;"Taux (%)"', $c);
        $f = $this->actingAs($this->admin)->get(route('cores.dashboard.export', 'failed_questions'))->streamedContent();
        $this->assertStringContainsString("Question;Quiz;Réponses;\"Taux d'échec (%)\"", $f);
    }

    public function test_unknown_export_table_404(): void
    {
        $this->actingAs($this->admin)->get('/cores/dashboard/kpis/export/nope')->assertNotFound();
    }

    public function test_dashboard_page_renders(): void
    {
        $this->actingAs($this->admin)->get(route('cores.dashboard'))
            ->assertOk()->assertSee('Tableau de bord');
    }

    public function test_page_contains_filters_and_containers(): void
    {
        $res = $this->actingAs($this->admin)->get(route('cores.dashboard'));

        $res->assertOk();
        foreach (['kpi-period', 'kpi-group', 'kpi-weekly-chart', 'kpi-completion-chart', 'kpi-failed-table', 'kpi-inactive-table'] as $id) {
            $res->assertSee('id="'.$id.'"', false);
        }
        $res->assertSee(term('Group'));
        $res->assertSee('Tous les '.term('groups'));
    }

    public function test_trainer_filter_lists_only_own_groups(): void
    {
        $trainer = $this->makeTrainer('coach', [$this->groupA]);

        $res = $this->actingAs($trainer)->get(route('cores.dashboard'));

        $res->assertOk();
        $res->assertSee('>A</option>', false);
        $res->assertDontSee('>B</option>', false);
    }
}
