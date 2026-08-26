<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Learner $learner;

    protected Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Mobile',
            'last_name' => 'Learner',
            'user_name' => 'mobilelearner',
            'email' => 'mobile@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->learner = Learner::create(['user_id' => $this->user->id, 'matricule' => 'MAT-MOB']);

        $group = Group::create(['name' => 'Mobile Group', 'is_active' => true]);
        $this->learner->groups()->attach($group->id);

        $this->quiz = Quiz::create([
            'title' => 'Mobile Quiz',
            'is_active' => true,
            'passing_score' => 60,
            'max_attempts' => 3,
            'created_by' => $this->user->id,
        ]);
        $this->quiz->groups()->attach($group->id);

        Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Vrai ?',
            'type' => 'true_false',
            'points' => 2,
            'order' => 1,
            'options' => ['correct_answer' => 'true'],
        ]);
    }

    private function login(): string
    {
        $response = $this->postJson('/api/mobile/v1/login', [
            'login' => 'mobilelearner',
            'password' => 'password123',
            'device_name' => 'Pixel Test',
        ]);

        return $response->json('token');
    }

    // ----------------------------------------------------------------- Auth

    public function test_login_returns_bearer_token_and_profile(): void
    {
        $response = $this->postJson('/api/mobile/v1/login', [
            'login' => 'mobile@learnandquiz.fr',
            'password' => 'password123',
            'device_name' => 'Pixel Test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', 'mobile@learnandquiz.fr')
            ->assertJsonStructure(['token', 'expires_in_days', 'user' => ['xp']]);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Pixel Test']);
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mobile/v1/login', [
                'login' => 'mobilelearner',
                'password' => 'mauvais',
                'device_name' => 'Pixel Test',
            ])->assertStatus(401);
        }

        $this->postJson('/api/mobile/v1/login', [
            'login' => 'mobilelearner',
            'password' => 'mauvais',
            'device_name' => 'Pixel Test',
        ])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_login_rejects_non_learner_account(): void
    {
        User::create([
            'name' => 'Staff', 'last_name' => 'Only', 'user_name' => 'staffmob',
            'email' => 'staffmob@learnandquiz.fr', 'password' => bcrypt('password123'), 'is_active' => true,
        ]);

        $this->postJson('/api/mobile/v1/login', [
            'login' => 'staffmob',
            'password' => 'password123',
            'device_name' => 'Pixel Test',
        ])->assertStatus(403);
    }

    public function test_same_device_reconnection_replaces_its_token(): void
    {
        $this->login();
        $this->login();

        $this->assertSame(1, $this->user->tokens()->where('name', 'Pixel Test')->count());
    }

    public function test_protected_routes_require_a_valid_token(): void
    {
        $this->getJson('/api/mobile/v1/bootstrap')->assertStatus(401);
        $this->getJson('/api/mobile/v1/bootstrap', ['Authorization' => 'Bearer invalide'])->assertStatus(401);
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->login();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/mobile/v1/logout', [], $headers)->assertStatus(200);

        // Les guards sont mis en cache entre deux requêtes du même test.
        $this->app->make('auth')->forgetGuards();

        $this->getJson('/api/mobile/v1/me', $headers)->assertStatus(401);
    }

    public function test_password_change_revokes_other_devices(): void
    {
        $token = $this->login();
        $other = $this->user->createToken('Autre appareil', ['learner'])->plainTextToken;

        $this->putJson('/api/mobile/v1/password', [
            'current_password' => 'password123',
            'password' => 'nouveau-mdp-mobile',
            'password_confirmation' => 'nouveau-mdp-mobile',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);

        // L'appareil courant reste connecté, l'autre est révoqué.
        $this->app->make('auth')->forgetGuards();
        $this->getJson('/api/mobile/v1/me', ['Authorization' => "Bearer {$token}"])->assertStatus(200);
        $this->app->make('auth')->forgetGuards();
        $this->getJson('/api/mobile/v1/me', ['Authorization' => "Bearer {$other}"])->assertStatus(401);
    }

    // ------------------------------------------------------------- Métier

    public function test_bootstrap_and_idempotent_actions_work_with_bearer(): void
    {
        $token = $this->login();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/mobile/v1/bootstrap', $headers)
            ->assertStatus(200)
            ->assertJsonPath('quizzes.0.title', 'Mobile Quiz')
            ->assertJsonStructure(['cursor', 'decks', 'exams', 'badges']);

        $actionId = (string) Str::uuid();
        $payload = [
            'actions' => [[
                'id' => $actionId,
                'type' => 'quiz_attempt',
                'data' => [
                    'quiz_id' => $this->quiz->id,
                    'answers' => [$this->quiz->questions->first()->id => 'true'],
                ],
            ]],
        ];

        $this->postJson('/api/mobile/v1/actions', $payload, $headers)
            ->assertStatus(200)
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.0.result.passed', true);

        // Rejeu → duplicate, pas de double XP.
        $this->postJson('/api/mobile/v1/actions', $payload, $headers)
            ->assertJsonPath('results.0.status', 'duplicate');
    }
}
