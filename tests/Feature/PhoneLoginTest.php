<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;
use Tests\TestCase;

class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = [], bool $learner = true): User
    {
        $user = User::create(array_merge([
            'name' => 'Awa',
            'last_name' => 'Kone',
            'user_name' => 'awakone',
            'email' => 'Awa@Example.com',
            'phone' => '+2250700000000',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ], $overrides));

        if ($learner) {
            Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-'.$user->id]);
        }

        return $user;
    }

    public function test_learner_logs_in_with_phone_in_any_format(): void
    {
        $this->makeUser();

        $this->postJson('/api/learner/v1/session', ['login' => '07 00 00 00 00', 'password' => 'password123'])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_learner_logs_in_with_email_case_insensitive(): void
    {
        $this->makeUser();

        $this->postJson('/api/learner/v1/session', ['login' => 'AWA@example.COM', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_existing_user_name_login_still_works(): void
    {
        $this->makeUser();

        $this->postJson('/api/learner/v1/session', ['login' => 'awakone', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_mobile_login_accepts_phone(): void
    {
        $this->makeUser();

        $this->postJson('/api/mobile/v1/login', [
            'login' => '+225 07 00 00 00 00', 'password' => 'password123', 'device_name' => 'pixel',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_staff_login_accepts_phone(): void
    {
        $this->makeUser(learner: false);

        $this->post('/login', ['login' => '0700000000', 'password' => 'password123'])
            ->assertRedirect(route('cores.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_inactive_user_with_phone_is_refused(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->postJson('/api/learner/v1/session', ['login' => '0700000000', 'password' => 'password123'])
            ->assertStatus(401);
    }

    public function test_numeric_user_name_still_logs_in(): void
    {
        $this->makeUser(['user_name' => '12345678', 'email' => 'num@example.com', 'phone' => '+2250711111111']);

        $this->postJson('/api/learner/v1/session', ['login' => '12345678', 'password' => 'password123'])
            ->assertOk()->assertJsonPath('success', true);
    }
}
