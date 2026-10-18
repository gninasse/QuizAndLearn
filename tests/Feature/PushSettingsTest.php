<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Learner;
use Modules\Core\Models\PushDelivery;
use Modules\Core\Models\PushSubscription;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Push\PushSender;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class PushSettingsTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'push.vapid.public_key' => 'PUBKEY-SECRET',
            'push.vapid.private_key' => 'PRIVKEY-SECRET',
            'push.vapid.subject' => 'mailto:test@example.com',
        ]);
        $this->sender = new FakePushSender;
        $this->app->instance(PushSender::class, $this->sender);
    }

    private function makeUser(string $handle, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate($role);
        $user->assignRole($role);

        return $user;
    }

    private function subscribe(Learner $learner, string $endpoint): void
    {
        PushSubscription::create([
            'learner_id' => $learner->id, 'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'BPkey', 'auth' => 'authkey',
        ]);
    }

    public function test_admin_saves_push_settings(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $push = [
            'types' => ['new_content' => true, 'cards_due' => false, 'streak' => true, 'exam_open' => false],
            'daily_hour' => 9, 'daily_cap' => 3,
        ];

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'classe', 'push' => $push])
            ->assertOk();

        $this->assertFalse((bool) AppSetting::get('push.types.cards_due'));
        $this->assertTrue((bool) AppSetting::get('push.types.streak'));
        $this->assertSame(9, (int) AppSetting::get('push.daily_hour'));
        $this->assertSame(3, (int) AppSetting::get('push.daily_cap'));

        // Heure du rappel bornée à 7–20 h (21 h–7 h = plage silencieuse).
        foreach ([['daily_hour' => 24, 'daily_cap' => 2], ['daily_hour' => -1, 'daily_cap' => 2],
            ['daily_hour' => 6, 'daily_cap' => 2], ['daily_hour' => 21, 'daily_cap' => 2],
            ['daily_hour' => 9, 'daily_cap' => 0], ['daily_hour' => 9, 'daily_cap' => 6]] as $bad) {
            $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'classe', 'push' => $bad + ['types' => []]])
                ->assertStatus(422);
        }
        $this->assertSame(9, (int) AppSetting::get('push.daily_hour'));
    }

    public function test_stored_daily_hour_is_clamped_outside_quiet_hours(): void
    {
        AppSetting::put('push.daily_hour', 23);
        $this->assertSame(20, app(\Modules\Core\Services\Push\PushSettings::class)->dailyHour());

        AppSetting::put('push.daily_hour', 3);
        $this->assertSame(7, app(\Modules\Core\Services\Push\PushSettings::class)->dailyHour());
    }

    public function test_absent_push_block_leaves_settings_unchanged(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        AppSetting::put('push.daily_cap', 4);

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'classe'])->assertOk();

        $this->assertSame(4, (int) AppSetting::get('push.daily_cap'));
    }

    public function test_trainer_cannot_change_push_settings(): void
    {
        $trainer = $this->makeUser('coach', 'trainer');

        $this->actingAs($trainer)->putJson(route('cores.settings.general.save'), [
            'preset' => 'classe', 'push' => ['daily_hour' => 9, 'daily_cap' => 3],
        ])->assertForbidden();
        $this->actingAs($trainer)->postJson(route('cores.settings.push.test'))->assertForbidden();
        $this->assertNull(AppSetting::get('push.daily_cap'));
    }

    public function test_test_button_sends_to_own_subscriptions_only(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $mine = Learner::create(['user_id' => $admin->id, 'matricule' => 'MAT-ADM']);
        $this->subscribe($mine, 'https://push.example/mine1');
        $this->subscribe($mine, 'https://push.example/mine2');
        $other = Learner::create(['user_id' => $this->makeUser('awa', 'learner')->id, 'matricule' => 'MAT-AWA']);
        $this->subscribe($other, 'https://push.example/other');
        AppSetting::put('push.types.exam_open', false);
        config(['push.timezone' => 'UTC']);

        $this->actingAs($admin)->postJson(route('cores.settings.push.test'))
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification envoyée à 2 appareil(s).');

        $this->assertCount(2, $this->sender->sent);
        $endpoints = array_map(fn ($s) => $s['subscription']->endpoint, $this->sender->sent);
        sort($endpoints);
        $this->assertSame(['https://push.example/mine1', 'https://push.example/mine2'], $endpoints);
        $this->assertSame('test', $this->sender->sent[0]['message']->type);
        $this->assertSame('Notification de test', $this->sender->sent[0]['message']->title);
        $this->assertSame(0, PushDelivery::count());
    }

    public function test_test_button_fails_without_subscription_or_vapid(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->postJson(route('cores.settings.push.test'))->assertStatus(422);

        $learner = Learner::create(['user_id' => $admin->id, 'matricule' => 'MAT-ADM']);
        $this->subscribe($learner, 'https://push.example/mine1');
        config(['push.vapid.public_key' => null]);

        $this->actingAs($admin)->postJson(route('cores.settings.push.test'))
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertCount(0, $this->sender->sent);
    }

    public function test_settings_page_shows_vapid_status(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->get(route('cores.settings.general'))
            ->assertOk()->assertSee('Clés VAPID configurées')
            ->assertDontSee('PUBKEY-SECRET')->assertDontSee('PRIVKEY-SECRET')
            ->assertSee('Abonnez-vous depuis l\'espace apprenant d\'un compte de test', false);

        config(['push.vapid.private_key' => null]);
        $this->actingAs($admin)->get(route('cores.settings.general'))
            ->assertOk()->assertSee('Non configuré — voir DEPLOIEMENT.md (php artisan push:vapid)');
    }
}
