<?php

namespace Modules\Core\Services\Push;

use GuzzleHttp\Client;
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
            // Client PSR-18 borné : un service push lent ne bloque pas le worker.
            $client = new Client([
                'timeout' => (float) config('push.http_timeout', 10),
                'connect_timeout' => (float) config('push.http_connect_timeout', 5),
            ]);

            $webPush = new WebPush(['VAPID' => [
                'subject' => (string) config('push.vapid.subject'),
                'publicKey' => (string) config('push.vapid.public_key'),
                'privateKey' => (string) config('push.vapid.private_key'),
            ]], [], $client);

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

        if ($report->isSubscriptionExpired()) {
            return 'expired';
        }

        // Échec non lié à l'expiration (403 clés VAPID, 413, 429, 5xx…) :
        // journalisé, abonnement conservé.
        Log::warning('Web Push : envoi refusé', [
            'subscription_id' => $s->id,
            'status' => $report->getResponse()?->getStatusCode(),
            'reason' => $report->getReason(),
        ]);

        return 'failed';
    }
}
