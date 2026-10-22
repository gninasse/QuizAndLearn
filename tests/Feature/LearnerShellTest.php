<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;
use Tests\TestCase;

class LearnerShellTest extends TestCase
{
    use RefreshDatabase;

    private function makeLearnerUser(): User
    {
        $user = User::create([
            'name' => 'Shell',
            'last_name' => 'Tester',
            'user_name' => 'shelltester',
            'email' => 'shell@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-SHELL']);

        return $user;
    }

    public function test_shell_served_publicly_on_root_and_login(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('id="app"', false);
        $this->get('/connexion')->assertStatus(200)->assertSee('id="app"', false);
    }

    public function test_deep_links_serve_shell_for_authenticated_learner(): void
    {
        $user = $this->makeLearnerUser();

        foreach (['/articles', '/articles/12', '/entrainement', '/examens', '/quizzes/3', '/quizzes/3/play', '/reviser', '/profil'] as $path) {
            $this->actingAs($user)->get($path)->assertStatus(200)->assertSee('id="app"', false);
        }
    }

    public function test_courses_and_resource_deep_links_serve_shell_for_authenticated_learner(): void
    {
        $user = $this->makeLearnerUser();

        foreach (['/cours', '/cours/5', '/cours/5/9', '/ressources/7'] as $path) {
            $this->actingAs($user)->get($path)->assertStatus(200)->assertSee('id="app"', false);
        }
    }

    public function test_spa_deep_routes_serve_shell_without_auth(): void
    {
        foreach (['/articles', '/articles/12', '/entrainement', '/examens', '/quizzes/3', '/quizzes/3/play', '/exams/4/play', '/reviser', '/reviser/2', '/profil', '/progression', '/mot-de-passe', '/demande-acces'] as $path) {
            $this->get($path)->assertStatus(200)->assertSee('id="app"', false);
        }
    }

    public function test_non_learner_is_refused_by_learner_api(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'last_name' => 'NoProfile',
            'user_name' => 'staffshell',
            'email' => 'staffshell@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        // Le shell est public, mais aucune donnée apprenant n'est servie.
        $this->actingAs($staff)->get('/articles')->assertStatus(200);
        $this->actingAs($staff)->getJson('/api/learner/v1/bootstrap')
            ->assertStatus(403)
            ->assertJsonPath('code', 'not_learner');
    }

    public function test_dashboard_redirects_to_root(): void
    {
        $user = $this->makeLearnerUser();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/');
    }
}
