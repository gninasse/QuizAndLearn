<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

class LearnerAccountRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'last_name' => 'User', 'user_name' => 'adminuser',
            'email' => 'adminuser@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Awa',
            'last_name' => 'Koné',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_learner_can_be_created_without_email_with_phone(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), $this->payload(['phone' => '07 00 00 00 00']))
            ->assertOk()->assertJsonPath('success', true);

        $user = User::where('phone', '+2250700000000')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
        $this->assertTrue($user->hasRole('learner'));
    }

    public function test_phone_duplicate_in_another_format_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), $this->payload(['phone' => '0700000000']))->assertOk();

        $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), $this->payload(['name' => 'Bob', 'phone' => '+225 07 00 00 00 00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Ce numéro de téléphone est déjà utilisé.');
    }

    public function test_user_name_is_generated_when_missing(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), $this->payload(['last_name' => 'Kone', 'phone' => '0700000001']))->assertOk();
        $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), $this->payload(['last_name' => 'Kone', 'phone' => '0700000002']))->assertOk();

        $this->assertDatabaseHas('users', ['user_name' => 'awa.kone']);
        $this->assertDatabaseHas('users', ['user_name' => 'awa.kone2']);
    }

    public function test_learner_needs_email_or_phone(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_staff_still_requires_email(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('cores.trainers.store'), [
                'name' => 'Tom', 'last_name' => 'Trainer', 'user_name' => 'tomtrainer',
                'phone' => '0700000009', 'password' => 'password123', 'password_confirmation' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_temporary_password_is_generated_and_flagged(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('cores.learners.store'), [
                'name' => 'Awa', 'last_name' => 'Kone', 'phone' => '0700000003', 'temporary_password' => 1,
            ])->assertOk();

        $password = $response->json('temporary_password');
        $this->assertSame(10, strlen($password));
        $this->assertMatchesRegularExpression('/\d/', $password);
        $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $password);

        $user = User::where('phone', '+2250700000003')->first();
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $user->password));
    }

    public function test_must_change_password_blocks_content_until_changed(): void
    {
        $user = User::create([
            'name' => 'Awa', 'last_name' => 'Kone', 'user_name' => 'awa.kone',
            'phone' => '+2250700000000', 'is_active' => true, 'password' => bcrypt('Temp1234'),
        ]);
        $user->forceFill(['must_change_password' => true])->save();
        Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-1']);

        $this->actingAs($user);

        $this->getJson('/api/learner/v1/bootstrap')
            ->assertStatus(403)->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/learner/v1/me')
            ->assertOk()->assertJsonPath('user.must_change_password', true);

        $this->putJson('/api/learner/v1/password', [
            'current_password' => 'Temp1234',
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ])->assertOk();

        $this->getJson('/api/learner/v1/bootstrap')->assertOk();
        $this->getJson('/api/learner/v1/me')->assertJsonPath('user.must_change_password', false);
    }

    public function test_mobile_flag_blocks_and_clears(): void
    {
        $user = User::create([
            'name' => 'Awa', 'last_name' => 'Kone', 'user_name' => 'awa.kone',
            'phone' => '+2250700000000', 'is_active' => true, 'password' => bcrypt('Temp1234'),
        ]);
        $user->forceFill(['must_change_password' => true])->save();
        Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-1']);

        $this->postJson('/api/mobile/v1/login', [
            'login' => '0700000000', 'password' => 'Temp1234', 'device_name' => 'pixel',
        ])->assertOk()->assertJsonPath('user.must_change_password', true);

        $plain = $user->createToken('t', ['learner'])->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$plain];

        $this->getJson('/api/mobile/v1/bootstrap', $h)->assertStatus(403)->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/mobile/v1/me', $h)->assertOk()->assertJsonPath('user.must_change_password', true);

        $this->putJson('/api/mobile/v1/password', [
            'current_password' => 'Temp1234', 'password' => 'NewPass1234', 'password_confirmation' => 'NewPass1234',
        ], $h)->assertOk();

        $this->assertFalse((bool) $user->fresh()->must_change_password);
    }
}
