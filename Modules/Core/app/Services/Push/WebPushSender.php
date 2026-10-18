<?php

namespace Modules\Core\Services\Push;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Modules\Core\Models\PushSubscription;
use Throwable;

class WebPushSender implements PushSender
{
    public function send(PushSubscription $s, PushMessage $m): string
    {
        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => (string) config('push.vapid.subject'),
                'publicKey' => (string) config('push.vapid.public_key'),
                'privateKey' => (string) config('push.vapid.private_key'),
            ]]);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $s->endpoint,
                    'publicKey' => $s->p256dh,
                    'authToken' => $s->auth,
                ]),
                $m->toPayload(),
                ['TTL' => 3600]
            );
        } catch (Throwable $e) {
            Log::warning('Web Push : envoi impossible', ['error' => $e->getMessage()]);

            return 'failed';
        }

        if ($report->isSuccess()) {
            return 'sent';
        }

        return $report->isSubscriptionExpired() ? 'expired' : 'failed';
    }
}
