<?php

namespace Tests\Feature;

use Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Learner;
use Modules\Core\Models\PushSubscription;
use Modules\Core\Models\Role;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'admin', 'trainer', 'learner'] as $role) {
            Role::findOrCreate($role);
        }
    }

    private function makeUser(string $handle, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeLearner(string $handle): Learner
    {
        $user = $this->makeUser($handle, 'learner');

        return Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-'.strtoupper($handle)]);
    }

    private function payload(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPkey', 'auth' => 'authkey']];
    }

    public function test_learner_can_subscribe_and_unsubscribe(): void
    {
        $learner = $this->makeLearner('awa');

        $this->actingAs($learner->user)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload())
            ->assertCreated();

        $sub = PushSubscription::sole();
        $this->assertSame($learner->id, $sub->learner_id);
        $this->assertSame(hash('sha256', self::ENDPOINT), $sub->endpoint_hash);
        $this->assertSame(self::ENDPOINT, $sub->endpoint);
        $this->assertSame('BPkey', $sub->p256dh);
        $this->assertSame('authkey', $sub->auth);

        $this->actingAs($learner->user)
            ->deleteJson('/api/learner/v1/push-subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertNoContent();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_resubscribing_same_endpoint_is_idempotent(): void
    {
        $learner = $this->makeLearner('awa');

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($learner->user)
                ->postJson('/api/learner/v1/push-subscriptions', $this->payload())
                ->assertCreated();
        }

        $this->assertSame(1, PushSubscription::count());
    }

    public function test_resubscribe_same_endpoint_moves_ownership(): void
    {
        $first = $this->makeLearner('awa');
        $second = $this->makeLearner('koffi');

        $this->actingAs($first->user)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload())
            ->assertCreated();

        $this->actingAs($second->user)
            ->postJson('/api/learner/v1/push-subscriptions', [
                'endpoint' => self::ENDPOINT,
                'keys' => ['p256dh' => 'NewKey', 'auth' => 'NewAuth'],
            ])
            ->assertCreated();

        $sub = PushSubscription::sole();
        $this->assertSame($second->id, $sub->learner_id);
        $this->assertSame('NewKey', $sub->p256dh);
        $this->assertSame(0, PushSubscription::where('learner_id', $first->id)->count());
    }

    public function test_unsubscribe_cannot_remove_another_learners_subscription(): void
    {
        $owner = $this->makeLearner('awa');
        $other = $this->makeLearner('koffi');

        $this->actingAs($owner->user)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload())
            ->assertCreated();

        $this->actingAs($other->user)
            ->deleteJson('/api/learner/v1/push-subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertNoContent();

        $this->assertSame(1, PushSubscription::where('learner_id', $owner->id)->count());
    }

    public function test_endpoint_must_be_https_url(): void
    {
        $learner = $this->makeLearner('awa');

        foreach (['http://example.com/push', 'not-a-url', ''] as $bad) {
            $this->actingAs($learner->user)
                ->postJson('/api/learner/v1/push-subscriptions', $this->payload($bad))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('endpoint');
        }

        $this->actingAs($learner->user)
            ->postJson('/api/learner/v1/push-subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_guest_and_staff_cannot_subscribe(): void
    {
        $this->postJson('/api/learner/v1/push-subscriptions', $this->payload())->assertUnauthorized();

        $staff = $this->makeUser('boss', 'admin');
        $this->actingAs($staff)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload())
            ->assertForbidden();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_impersonation_cannot_subscribe(): void
    {
        $learner = $this->makeLearner('awa');
        $root = $this->makeUser('root', 'super-admin');

        $url = $this->actingAs($root)
            ->postJson("/cores/learners/{$learner->id}/impersonate")
            ->assertOk()
            ->json('url');
        $token = substr($url, strpos($url, '#consultation=') + strlen('#consultation='));

        $this->actingAs($root)->withHeader('X-Impersonation-Token', $token)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('code', 'read_only');

        $this->actingAs($root)->withHeader('X-Impersonation-Token', $token)
            ->deleteJson('/api/learner/v1/push-subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertForbidden()
            ->assertJsonPath('code', 'read_only');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_me_exposes_public_key_or_null(): void
    {
        $learner = $this->makeLearner('awa');

        config(['push.vapid.public_key' => null, 'push.vapid.private_key' => null]);
        $this->actingAs($learner->user)->getJson('/api/learner/v1/me')
            ->assertOk()
            ->assertJsonPath('user.push.public_key', null);

        config(['push.vapid.public_key' => 'PUBKEY', 'push.vapid.private_key' => 'PRIVKEY']);
        $this->actingAs($learner->user)->getJson('/api/learner/v1/me')
            ->assertOk()
            ->assertJsonPath('user.push.public_key', 'PUBKEY');

        // Clé privée absente : fonctionnalité inerte, clé publique masquée.
        config(['push.vapid.private_key' => null]);
        $this->actingAs($learner->user)->getJson('/api/learner/v1/me')
            ->assertJsonPath('user.push.public_key', null);
    }
}
