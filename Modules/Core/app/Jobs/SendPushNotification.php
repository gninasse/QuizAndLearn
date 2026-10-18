<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\PushSubscription;
use Modules\Core\Services\Push\PushMessage;
use Modules\Core\Services\Push\PushSender;
use Modules\Core\Services\Push\PushSettings;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Pas de nouvel essai : un échec est simplement abandonné. */
    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    /** Types envoyés même en plage silencieuse (urgence, test admin). */
    private const QUIET_EXEMPT = ['exam_open', 'test'];

    /** Instant de mise en file (timestamp Unix). */
    public int $queuedAt;

    public function __construct(public PushSubscription $subscription, public PushMessage $message, ?int $queuedAt = null)
    {
        $this->queuedAt = $queuedAt ?? now()->getTimestamp();
    }

    public function handle(PushSender $sender): void
    {
        // File engorgée : une notification périmée, ou arrivée la nuit, est abandonnée.
        if ($this->isStale() || $this->blockedByQuietHours()) {
            return;
        }

        $result = $sender->send($this->subscription, $this->message);

        if ($result === 'expired') {
            $this->subscription->delete();
        } elseif ($result === 'sent') {
            $this->subscription->forceFill(['last_used_at' => now()])->save();
        }
    }

    private function isStale(): bool
    {
        return now()->getTimestamp() - $this->queuedAt > (int) config('push.max_queue_age', 3600);
    }

    private function blockedByQuietHours(): bool
    {
        return ! in_array($this->message->type, self::QUIET_EXEMPT, true)
            && PushSettings::inQuietHours(PushSettings::localNow());
    }
}
