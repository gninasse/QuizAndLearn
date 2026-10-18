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

    public function test_endpoint_host_must_be_a_known_push_service(): void
    {
        $learner = $this->makeLearner('awa');

        foreach ([
            'https://evil.example.com/push',
            'https://169.254.169.254/latest',
            'https://fcm.googleapis.com.evil.example/x',
            'https://notgoogleapis.com/x',
        ] as $bad) {
            $this->actingAs($learner->user)
                ->postJson('/api/learner/v1/push-subscriptions', $this->payload($bad))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('endpoint');
        }
        $this->assertSame(0, PushSubscription::count());

        foreach ([
            'https://fcm.googleapis.com/fcm/send/a',
            'https://updates.push.services.mozilla.com/wpush/v2/b',
            'https://web.push.apple.com/c',
            'https://wns2-par02p.notify.windows.com/w/?token=d',
        ] as $good) {
            $this->actingAs($learner->user)
                ->postJson('/api/learner/v1/push-subscriptions', $this->payload($good))
                ->assertCreated();
        }
        $this->assertSame(4, PushSubscription::count());
    }

    public function test_subscriptions_are_capped_per_learner_pruning_oldest(): void
    {
        $learner = $this->makeLearner('awa');
        config(['push.max_subscriptions_per_learner' => 3]);

        foreach ([1, 2, 3] as $i) {
            $this->travelTo(now()->addMinute());
            $this->actingAs($learner->user)
                ->postJson('/api/learner/v1/push-subscriptions', $this->payload("https://fcm.googleapis.com/fcm/send/{$i}"))
                ->assertCreated();
        }
        // Le n°1 a servi récemment : c'est le n°2 (le moins récemment utilisé) qui part.
        PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/1')
            ->update(['last_used_at' => now()->addMinute()]);

        $this->travelTo(now()->addMinutes(2));
        $this->actingAs($learner->user)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload('https://fcm.googleapis.com/fcm/send/4'))
            ->assertCreated();

        $endpoints = PushSubscription::where('learner_id', $learner->id)->orderBy('id')->pluck('endpoint')->all();
        $this->assertSame([
            'https://fcm.googleapis.com/fcm/send/1',
            'https://fcm.googleapis.com/fcm/send/3',
            'https://fcm.googleapis.com/fcm/send/4',
        ], $endpoints);

        // Un autre apprenant n'est pas concerné par le plafond du premier.
        $other = $this->makeLearner('koffi');
        $this->actingAs($other->user)
            ->postJson('/api/learner/v1/push-subscriptions', $this->payload('https://fcm.googleapis.com/fcm/send/other'))
            ->assertCreated();
        $this->assertSame(3, PushSubscription::where('learner_id', $learner->id)->count());
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

        // Sujet VAPID absent : la bibliothèque refuserait d'envoyer, clé masquée.
        config(['push.vapid.public_key' => 'PUBKEY', 'push.vapid.private_key' => 'PRIVKEY', 'push.vapid.subject' => null]);
        $this->actingAs($learner->user)->getJson('/api/learner/v1/me')
            ->assertJsonPath('user.push.public_key', null);

        config(['push.vapid.subject' => 'mailto:test@example.com']);
        $this->actingAs($learner->user)->getJson('/api/learner/v1/me')
            ->assertOk()
            ->assertJsonPath('user.push.public_key', 'PUBKEY');

        // Clé privée absente : fonctionnalité inerte, clé publique masquée.
        config(['push.vapid.private_key' => null]);
        $this->actingAs($learner->user)->getJson('/api/learner/v1/me')
            ->assertJsonPath('user.push.public_key', null);
    }
}
