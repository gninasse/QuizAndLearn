<?php

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationEvents
{
    public function handleLogin(Login $event)
    {
        $event->user->logLogin();
    }

    public function handleLogout(Logout $event)
    {
        $event->user?->logLogout();
    }

    public function handleFailed(Failed $event)
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
