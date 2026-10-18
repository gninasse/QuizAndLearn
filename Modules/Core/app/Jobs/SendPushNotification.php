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

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Pas de nouvel essai : un échec est simplement abandonné. */
    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public PushSubscription $subscription, public PushMessage $message) {}

    public function handle(PushSender $sender): void
    {
        $result = $sender->send($this->subscription, $this->message);

        if ($result === 'expired') {
            $this->subscription->delete();
        } elseif ($result === 'sent') {
            $this->subscription->forceFill(['last_used_at' => now()])->save();
        }
    }
}
