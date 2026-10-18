<?php

namespace Modules\Core\Services\Push;

use Modules\Core\Models\PushSubscription;

interface PushSender
{
    /** @return 'sent'|'expired'|'failed' */
    public function send(PushSubscription $s, PushMessage $m): string;
}
