<?php

return [
    /*
    | Web Push (standard VAPID, sans service tiers). Clés absentes : tout le
    | chantier est inerte (aucun envoi, clé publique non exposée à la PWA).
    | Générer avec `php artisan push:vapid`.
    */
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT'),
    ],

    // Fuseau des horaires d'envoi (plage silencieuse, rappels quotidiens).
    'timezone' => env('PUSH_TIMEZONE', config('app.timezone')),

    'enabled' => filled(env('VAPID_PUBLIC_KEY')) && filled(env('VAPID_PRIVATE_KEY')),
];
