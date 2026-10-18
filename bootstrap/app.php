<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tunnels/reverse-proxies (ngrok, etc.) : fait confiance aux en-têtes
        // X-Forwarded-* pour générer des URLs https correctes derrière le proxy.
        $middleware->trustProxies(at: '*');

        // Marque blanche : pas d'en-tête révélant la pile technique.
        $middleware->append(\App\Http\Middleware\HideTechnologyHeaders::class);

        // Jeton invité du mode découverte : valeur déjà chiffrée par GuestToken.
        $middleware->encryptCookies(except: ['lq_guest']);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'learner' => \Modules\Core\Http\Middleware\EnsureUserIsLearner::class,
            'staff' => \Modules\Core\Http\Middleware\EnsureUserIsStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
