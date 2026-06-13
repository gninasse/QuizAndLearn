<?php

namespace Modules\Core\Listeners;

class HandleUserLogin
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $event->user->logLogin();
    }
}
