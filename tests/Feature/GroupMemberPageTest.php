<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * Page dédiée « Membres du groupe » + suspension temporaire d'un apprenant.
 */
class GroupMemberPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Group $group;

    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin');
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');

        $this->group = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $this->learner = Learner::create(['user_id' => $this->makeUser('awa')->id, 'matricule' => 'MAT-001']);
        $this->group->learners()->attach($this->learner->id);
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

    public function test_admin_sees_members_page_and_data(): void
    {
        $this->actingAs($this->admin)
            ->get("/cores/groups/{$this->group->id}/membres")
            ->assertOk()
            ->assertSee('Membres du groupe');

        $this->actingAs($this->admin)
            ->getJson("/cores/groups/{$this->group->id}/membres/data")
            ->assertOk()
            ->assertJsonPath('group.name', 'Promo A')
            ->assertJsonPath('learners.0.matricule', 'MAT-001')
            ->assertJsonPath('learners.0.suspended', false);
    }

    public function test_trainer_only_reaches_assigned_groups(): void
    {
        $trainerUser = $this->makeUser('prof');
        Role::findOrCreate('trainer');
        $trainerUser->assignRole('trainer');
        $trainer = Trainer::create(['user_id' => $trainerUser->id]);

        $this->actingAs($trainerUser)
            ->getJson("/cores/groups/{$this->group->id}/membres/data")
            ->assertForbidden();

        $this->group->trainers()->attach($trainer->id);

        $this->actingAs($trainerUser)
            ->getJson("/cores/groups/{$this->group->id}/membres/data")
            ->assertOk();

        // L'affectation des formateurs reste réservée aux admins.
        $this->actingAs($trainerUser)
            ->postJson("/cores/groups/{$this->group->id}/membres/trainers", ['trainer_ids' => [$trainer->id]])
            ->assertForbidden();
    }

    public function test_add_search_and_remove_learners(): void
    {
        $other = Learner::create(['user_id' => $this->makeUser('binta')->id, 'matricule' => 'MAT-002']);

        $this->actingAs($this->admin)
            ->getJson("/cores/groups/{$this->group->id}/membres/candidates?q=mat-002")
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.id', $other->id);

        $this->actingAs($this->admin)
            ->postJson("/cores/groups/{$this->group->id}/membres/learners", ['learner_ids' => [$other->id]])
            ->assertOk();
        $this->assertTrue($this->group->learners()->where('learners.id', $other->id)->exists());

        $this->actingAs($this->admin)
            ->deleteJson("/cores/groups/{$this->group->id}/membres/learners/{$other->id}")
            ->assertOk();
        $this->assertFalse($this->group->learners()->where('learners.id', $other->id)->exists());
    }

    public function test_trainer_limit_is_enforced(): void
    {
        $ids = collect(range(1, 4))->map(fn ($i) => Trainer::create(['user_id' => $this->makeUser("t{$i}")->id])->id);

        $this->actingAs($this->admin)
            ->postJson("/cores/groups/{$this->group->id}/membres/trainers", ['trainer_ids' => $ids->all()])
            ->assertStatus(422);
    }

    public function test_suspension_blocks_learner_but_keeps_group_membership(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/cores/groups/{$this->group->id}/membres/learners/{$this->learner->id}/suspend", [
                'until' => now()->addDays(3)->toIso8601String(),
                'reason' => 'Absences répétées',
            ])
            ->assertOk();

        $this->learner->refresh();
        $this->assertTrue($this->learner->isSuspended());
        $this->assertTrue($this->group->learners()->where('learners.id', $this->learner->id)->exists());

        // API apprenant : session existante coupée, code dédié pour la PWA.
        $this->actingAs($this->learner->user)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_suspended');

        // Connexion refusée avec le motif.
        auth()->logout();
        $this->postJson('/api/learner/v1/session', ['login' => 'awa', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_suspended')
            ->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('Absences répétées', $this->learner->suspensionMessage());

        // Levée de la suspension.
        $this->actingAs($this->admin)
            ->deleteJson("/cores/groups/{$this->group->id}/membres/learners/{$this->learner->id}/suspend")
            ->assertOk();
        $this->assertFalse($this->learner->refresh()->isSuspended());
    }

    public function test_suspension_lifts_itself_once_expired(): void
    {
        $this->learner->suspend(now()->subMinute(), null, $this->admin->id);

        $this->assertFalse($this->learner->refresh()->isSuspended());
        $this->actingAs($this->learner->user)->getJson('/api/learner/v1/bootstrap')->assertOk();
    }

    public function test_suspension_requires_a_future_end_date(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/cores/groups/{$this->group->id}/membres/learners/{$this->learner->id}/suspend", [
                'until' => now()->subDay()->toIso8601String(),
            ])
            ->assertStatus(422);
    }

    public function test_suspension_revokes_mobile_tokens(): void
    {
        $token = $this->learner->user->createToken('phone', ['learner'])->plainTextToken;

        $this->actingAs($this->admin)
            ->postJson("/cores/groups/{$this->group->id}/membres/learners/{$this->learner->id}/suspend")
            ->assertOk();

        $this->assertSame(0, $this->learner->user->tokens()->count());
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/v1/bootstrap')->assertUnauthorized();
    }

    public function test_add_messages_default_and_feminine_wording(): void
    {
        $other = Learner::create(['user_id' => $this->makeUser('moussa')->id, 'matricule' => 'MAT-002']);
        $trainer = Trainer::create(['user_id' => $this->makeUser('coach')->id]);

        $this->actingAs($this->admin)
            ->postJson(route('cores.groups.members.learners.add', $this->group->id), ['learner_ids' => [$other->id]])
            ->assertOk()->assertJsonPath('message', 'Apprenant ajouté au groupe.');
        $this->actingAs($this->admin)
            ->postJson(route('cores.groups.members.trainers.add', $this->group->id), ['trainer_ids' => [$trainer->id]])
            ->assertOk()->assertJsonPath('message', 'Formateur(s) ajouté(s) au groupe.');

        app(\Modules\Core\Services\Terminology::class)->save(['preset' => 'classe']);
        $this->actingAs($this->admin)
            ->postJson(route('cores.groups.members.learners.add', $this->group->id), ['learner_ids' => [$other->id]])
            ->assertOk()->assertJsonPath('message', 'Apprenant ajouté à la classe.');
    }
}
