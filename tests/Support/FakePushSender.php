<?php

namespace Tests\Support;

use Modules\Core\Models\PushSubscription;
use Modules\Core\Services\Push\PushMessage;
use Modules\Core\Services\Push\PushSender;

class FakePushSender implements PushSender
{
    /** @var array<int, array{subscription: PushSubscription, message: PushMessage}> */
    public array $sent = [];

    public string $result = 'sent';

    public function send(PushSubscription $s, PushMessage $m): string
    {
        $this->sent[] = ['subscription' => $s, 'message' => $m];

        return $this->result;
    }
}
