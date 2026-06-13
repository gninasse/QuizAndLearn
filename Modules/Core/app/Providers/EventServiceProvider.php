<?php

namespace Modules\Core\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Listeners\HandleUserLogin;
use Modules\Core\Listeners\HandleUserLoginFailed;
use Modules\Core\Listeners\HandleUserLogout;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // Login::class => [
        //     [LogAuthenticationEvents::class, 'handleLogin'],
        // ],
        // Logout::class => [
        //     [LogAuthenticationEvents::class, 'handleLogout'],
        // ],
        // Failed::class => [
        //     [LogAuthenticationEvents::class, 'handleFailed'],
        // ],
        Login::class => [HandleUserLogin::class],
        Failed::class => [HandleUserLoginFailed::class],
        Logout::class => [HandleUserLogout::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
