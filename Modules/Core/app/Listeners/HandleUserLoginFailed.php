<?php

namespace Modules\Core\Listeners;

class HandleUserLoginFailed
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
        activity('auth')
            ->withProperties([
                'login' => $event->credentials['login'] ?? null,
                'ip' => request()->ip(),
            ])
            ->tap(function ($activity) {
                $activity->module = 'core';
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log('login_failed');
    }
}
