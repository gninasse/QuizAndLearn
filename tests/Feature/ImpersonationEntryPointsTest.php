<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Group;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * « Voir en tant que » : points d'entrée du back-office (réservés au super admin).
 */
class ImpersonationEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    private Learner $learner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'admin', 'learner'] as $role) {
            Role::findOrCreate($role);
        }

        $learnerUser = $this->makeUser('awa');
        $learnerUser->assignRole('learner');
        $this->learner = Learner::create(['user_id' => $learnerUser->id, 'matricule' => 'MAT-001']);

        $this->group = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $this->learner->groups()->attach($this->group->id);
    }

    private function makeUser(string $handle, ?string $role = null): User
    {
        $user = User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_members_data_exposes_can_impersonate_only_to_super_admin(): void
    {
        $url = route('cores.groups.members.data', $this->group->id);

        $this->actingAs($this->makeUser('root', 'super-admin'))
            ->getJson($url)->assertOk()->assertJsonPath('can_impersonate', true);

        $this->actingAs($this->makeUser('boss', 'admin'))
            ->getJson($url)->assertOk()->assertJsonPath('can_impersonate', false);
    }

    public function test_non_super_admin_gets_no_last_consultation(): void
    {
        $superAdmin = $this->makeUser('root', 'super-admin');
        ImpersonationSession::create([
            'admin_id' => $superAdmin->id,
            'learner_id' => $this->learner->id,
            'token_hash' => hash('sha256', 'abc'),
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($this->makeUser('boss', 'admin'))
            ->getJson(route('cores.groups.members.data', $this->group->id))
            ->assertOk()
            ->assertJsonPath('can_impersonate', false);

        $this->assertNull($response->json('learners.0.last_consultation'));
    }

    public function test_members_data_includes_last_consultation(): void
    {
        $admin = $this->makeUser('root', 'super-admin');

        $this->actingAs($admin)->getJson(route('cores.groups.members.data', $this->group->id))
            ->assertJsonPath('learners.0.last_consultation', null);

        ImpersonationSession::create([
            'admin_id' => $admin->id,
            'learner_id' => $this->learner->id,
            'token_hash' => hash('sha256', 'abc'),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($admin)->getJson(route('cores.groups.members.data', $this->group->id))
            ->assertJsonPath('learners.0.last_consultation.by', $admin->full_name)
            ->assertJsonStructure(['learners' => [['last_consultation' => ['at', 'by']]]]);
    }

    public function test_learners_index_shows_button_only_to_super_admin(): void
    {
        $this->actingAs($this->makeUser('root', 'super-admin'))
            ->get(route('cores.learners.index'))
            ->assertOk()->assertSee('Voir en tant que');

        $this->actingAs($this->makeUser('boss', 'admin'))
            ->get(route('cores.learners.index'))
            ->assertOk()->assertDontSee('Voir en tant que');
    }

    public function test_learners_data_exposes_learner_id(): void
    {
        $this->actingAs($this->makeUser('root', 'super-admin'))
            ->getJson(route('cores.learners.data'))
            ->assertOk()->assertJsonPath('rows.0.learner_id', $this->learner->id);
    }
}
