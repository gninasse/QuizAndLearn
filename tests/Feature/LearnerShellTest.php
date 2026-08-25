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

    public function test_deep_links_redirect_guests_to_login(): void
    {
        $this->get('/articles')->assertRedirect();
    }

    public function test_non_learner_is_logged_out_and_redirected(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'last_name' => 'NoProfile',
            'user_name' => 'staffshell',
            'email' => 'staffshell@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->actingAs($staff)->get('/articles')->assertRedirect(route('learn.login'));
    }

    public function test_dashboard_redirects_to_root(): void
    {
        $user = $this->makeLearnerUser();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/');
    }
}
