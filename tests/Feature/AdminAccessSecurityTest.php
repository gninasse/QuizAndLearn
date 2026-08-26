<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Activity;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Tests\TestCase;

class AdminAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $userName, string $email): User
    {
        return User::create([
            'name' => 'Test',
            'last_name' => ucfirst($userName),
            'user_name' => $userName,
            'email' => $email,
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    // ------------------------------------------- Faille 1 : porte du back-office

    public function test_learner_only_account_cannot_access_back_office(): void
    {
        $user = $this->makeUser('pure_learner', 'pl@test.fr');
        Role::findOrCreate('learner');
        $user->assignRole('learner');
        Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-X']);

        // Web : renvoyé vers son espace apprenant.
        $this->actingAs($user)->get('/cores/dashboard')->assertRedirect('/');
        $this->actingAs($user)->get('/cores/users')->assertRedirect('/');

        // JSON : 403 explicite.
        $this->actingAs($user)->getJson('/cores/users/data')->assertStatus(403);
    }

    public function test_trainer_role_can_access_back_office(): void
    {
        $user = $this->makeUser('staff_trainer', 'st@test.fr');
        Role::findOrCreate('trainer');
        $user->assignRole('trainer');

        $this->actingAs($user)->get('/cores/dashboard')->assertStatus(200);
    }

    public function test_trainer_profile_without_role_can_access_back_office(): void
    {
        $user = $this->makeUser('profile_trainer', 'pt@test.fr');
        Trainer::create(['user_id' => $user->id, 'specialty' => 'Test']);

        $this->actingAs($user)->get('/cores/dashboard')->assertStatus(200);
    }

    public function test_admin_role_can_access_back_office(): void
    {
        $user = $this->makeUser('staff_admin', 'sa@test.fr');
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $this->actingAs($user)->get('/cores/dashboard')->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/cores/dashboard')->assertRedirect(route('login'));
    }

    public function test_scaffold_cores_resource_route_is_gone(): void
    {
        $this->get('/cores')->assertStatus(404);
    }

    // --------------------------------- Faille 2 : secrets dans le journal d'audit

    public function test_password_changes_never_log_the_hash(): void
    {
        $user = $this->makeUser('hash_test', 'hash@test.fr');

        $user->update(['password' => 'nouveau-mot-de-passe']);

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        // Le changement seul du mot de passe ne doit produire aucune activité
        // (champ exclu + dontSubmitEmptyLogs), ou une activité sans le hash.
        if ($activity !== null) {
            $properties = $activity->properties->toArray();
            $this->assertArrayNotHasKey('password', $properties['attributes'] ?? []);
            $this->assertArrayNotHasKey('password', $properties['old'] ?? []);
            $this->assertArrayNotHasKey('remember_token', $properties['attributes'] ?? []);
        } else {
            $this->assertNull($activity);
        }
    }
}
