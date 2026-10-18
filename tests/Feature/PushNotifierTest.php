<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Core\Jobs\SendPushNotification;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerPreference;
use Modules\Core\Models\PushDelivery;
use Modules\Core\Models\PushSubscription;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Push\PushMessage;
use Modules\Core\Services\Push\PushNotifier;
use Modules\Core\Services\Push\PushSender;
use Modules\Core\Services\Push\PushSettings;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class PushNotifierTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $sender;

    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'push.timezone' => 'UTC',
            'push.vapid.public_key' => 'pub',
            'push.vapid.private_key' => 'priv',
            'push.vapid.subject' => 'mailto:test@example.com',
        ]);

        $this->sender = new FakePushSender;
        $this->app->instance(PushSender::class, $this->sender);

        Role::findOrCreate('learner');
        $user = User::create([
            'name' => 'Awa', 'last_name' => 'Test', 'user_name' => 'awa',
            'email' => 'awa@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        $user->assignRole('learner');
        $this->learner = Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-AWA']);
        $this->subscribe($this->learner, 'https://push.example/1');

        // Le job (file synchrone) relit l'heure courante : on la fige à midi UTC.
        $this->travelTo($this->at('12:00'));
    }

    private function subscribe(Learner $learner, string $endpoint): PushSubscription
    {
        return PushSubscription::create([
            'learner_id' => $learner->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'BPkey',
            'auth' => 'authkey',
        ]);
    }

    private function message(string $type = 'cards_due', ?string $ref = null): PushMessage
    {
        return new PushMessage($type, 'Titre', 'Corps', '/', $ref);
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse("2026-10-20 {$time}", 'UTC');
    }

    private function notify(PushMessage $m, ?Carbon $now = null): bool
    {
        return app(PushNotifier::class)->notify($this->learner->fresh(), $m, $now ?? $this->at('12:00'));
    }

    public function test_sends_to_every_subscription_and_logs_one_delivery(): void
    {
        $this->subscribe($this->learner, 'https://push.example/2');

        $this->assertTrue($this->notify($this->message('cards_due', 'day:1')));

        $this->assertCount(2, $this->sender->sent);
        $this->assertSame(1, PushDelivery::where('learner_id', $this->learner->id)->count());
        $this->assertNotNull(PushSubscription::first()->last_used_at);
    }

    public function test_disabled_without_vapid_keys(): void
    {
        config(['push.vapid.private_key' => null]);

        $this->assertFalse($this->notify($this->message()));
        $this->assertCount(0, $this->sender->sent);
        $this->assertSame(0, PushDelivery::count());
    }

    public function test_type_disabled_by_admin(): void
    {
        AppSetting::put('push.types.cards_due', false);

        $this->assertFalse($this->notify($this->message('cards_due')));
        $this->assertTrue($this->notify($this->message('streak')));
    }

    public function test_no_subscription_means_no_send(): void
    {
        PushSubscription::query()->delete();

        $this->assertFalse($this->notify($this->message()));
        $this->assertSame(0, PushDelivery::count());
    }

    public function test_quiet_hours_block_all_but_exam(): void
    {
        $this->assertTrue($this->notify($this->message('streak'), $this->at('20:59')));

        PushDelivery::query()->delete();
        $this->assertFalse($this->notify($this->message('streak'), $this->at('21:00')));
        $this->assertFalse($this->notify($this->message('streak'), $this->at('06:59')));
        $this->assertTrue($this->notify($this->message('exam_open', 'exam:1'), $this->at('23:00')));
    }

    public function test_quiet_hours_use_configured_timezone(): void
    {
        config(['push.timezone' => 'Asia/Tokyo']);

        // 12:00 UTC = 21:00 à Tokyo : bloqué.
        $this->assertFalse($this->notify($this->message('streak'), $this->at('12:00')));
        // 10:00 UTC = 19:00 à Tokyo : autorisé.
        $this->assertTrue($this->notify($this->message('streak'), $this->at('10:00')));
    }

    public function test_daily_cap_with_exam_priority(): void
    {
        $this->assertTrue($this->notify($this->message('cards_due')));
        $this->assertTrue($this->notify($this->message('streak')));
        $this->assertFalse($this->notify($this->message('cards_due')));
        $this->assertTrue($this->notify($this->message('exam_open', 'exam:9')));

        // Le lendemain, le compteur repart de zéro.
        $this->assertTrue($this->notify($this->message('cards_due'), Carbon::parse('2026-10-21 12:00', 'UTC')));
    }

    public function test_sent_at_is_stored_in_app_time_and_cap_uses_push_day(): void
    {
        config(['push.timezone' => 'Asia/Tokyo', 'app.timezone' => 'UTC']);

        // 00:30 le 21/10 à Tokyo = 15:30 UTC le 20/10.
        $instant = Carbon::parse('2026-10-21 00:30', 'Asia/Tokyo');
        AppSetting::put('push.daily_cap', 1);
        $this->assertTrue($this->notify($this->message('exam_open', 'exam:1'), $instant));

        $this->assertSame('2026-10-20 15:30:00', PushDelivery::first()->getRawOriginal('sent_at'));

        // Même jour à Tokyo (08:00 le 21/10) : plafond atteint.
        $this->assertFalse($this->notify($this->message('cards_due'), Carbon::parse('2026-10-21 08:00', 'Asia/Tokyo')));
        // Jour suivant à Tokyo : repart de zéro.
        $this->assertTrue($this->notify($this->message('cards_due'), Carbon::parse('2026-10-22 08:00', 'Asia/Tokyo')));
    }

    public function test_daily_cap_is_configurable(): void
    {
        AppSetting::put('push.daily_cap', 1);

        $this->assertTrue($this->notify($this->message('cards_due')));
        $this->assertFalse($this->notify($this->message('streak')));
    }

    public function test_same_ref_never_sent_twice(): void
    {
        $this->assertTrue($this->notify($this->message('new_content', 'quiz:5')));
        $this->assertFalse($this->notify($this->message('new_content', 'quiz:5'), Carbon::parse('2026-10-25 12:00', 'UTC')));
        $this->assertTrue($this->notify($this->message('new_content', 'quiz:6'), Carbon::parse('2026-10-25 12:00', 'UTC')));
    }

    public function test_learner_preference_disables_new_quiz(): void
    {
        LearnerPreference::create([
            'learner_id' => $this->learner->id,
            'notifications_enabled' => ['new_quiz' => false, 'new_article' => true],
        ]);

        $this->assertFalse($this->notify($this->message('new_content', 'quiz:5')));
        $this->assertTrue($this->notify($this->message('new_content', 'article:5')));
    }

    public function test_expired_subscription_is_deleted(): void
    {
        $this->sender->result = 'expired';

        $this->assertTrue($this->notify($this->message()));

        $this->assertSame(0, PushSubscription::count());
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_failed_send_keeps_subscription(): void
    {
        $this->sender->result = 'failed';

        $this->notify($this->message());

        $this->assertSame(1, PushSubscription::count());
        $this->assertNull(PushSubscription::first()->last_used_at);
    }

    public function test_payload_has_no_extra_fields(): void
    {
        $payload = json_decode((new PushMessage('streak', 'T', 'B', '/x', 'ref:1'))->toPayload(), true);

        $this->assertSame(['title', 'body', 'url', 'tag', 'icon'], array_keys($payload));
        $this->assertSame('streak:ref:1', $payload['tag']);
        $this->assertSame('/icons/icon-192x192.png', $payload['icon']);
    }

    public function test_tag_is_ref_based_so_two_exams_do_not_replace_each_other(): void
    {
        $a = json_decode((new PushMessage('exam_open', 'T', 'B', '/', 'exam:1'))->toPayload(), true);
        $b = json_decode((new PushMessage('exam_open', 'T', 'B', '/', 'exam:2'))->toPayload(), true);
        $plain = json_decode((new PushMessage('test', 'T', 'B', '/'))->toPayload(), true);

        $this->assertNotSame($a['tag'], $b['tag']);
        $this->assertSame('test', $plain['tag']);
    }

    public function test_empty_push_timezone_falls_back_to_app_timezone(): void
    {
        config(['push.timezone' => '', 'app.timezone' => 'UTC']);

        $this->assertSame('UTC', PushSettings::timezone());
        $this->assertTrue($this->notify($this->message('streak')));
        $this->assertFalse($this->notify($this->message('cards_due', 'x'), $this->at('22:00')));
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_vapid_requires_subject(): void
    {
        config(['push.vapid.subject' => null]);

        $this->assertFalse(PushSettings::vapidConfigured());
        $this->assertFalse($this->notify($this->message()));
    }

    public function test_job_drops_stale_notification(): void
    {
        $subscription = PushSubscription::first();
        $job = new SendPushNotification($subscription, $this->message('exam_open', 'exam:1'));

        $this->travelTo($this->at('13:01'));
        app()->call([$job, 'handle']);

        $this->assertCount(0, $this->sender->sent);
        $this->assertNull($subscription->fresh()->last_used_at);
    }

    public function test_job_still_sends_within_an_hour(): void
    {
        $job = new SendPushNotification(PushSubscription::first(), $this->message('cards_due'));

        $this->travelTo($this->at('12:59'));
        app()->call([$job, 'handle']);

        $this->assertCount(1, $this->sender->sent);
    }

    public function test_job_drops_non_exam_notification_reaching_quiet_hours(): void
    {
        $this->travelTo($this->at('20:50'));
        $cards = new SendPushNotification(PushSubscription::first(), $this->message('cards_due'));
        $exam = new SendPushNotification(PushSubscription::first(), $this->message('exam_open', 'exam:1'));
        $test = new SendPushNotification(PushSubscription::first(), new PushMessage('test', 'T', 'B', '/'));

        // Worker en retard : traité à 21:10, dans la plage silencieuse.
        $this->travelTo($this->at('21:10'));
        foreach ([$cards, $exam, $test] as $job) {
            app()->call([$job, 'handle']);
        }

        $this->assertSame(['exam_open', 'test'], array_map(fn ($s) => $s['message']->type, $this->sender->sent));
    }
}
