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
    // `?:` : une variable présente mais vide retombe sur le fuseau applicatif.
    'timezone' => env('PUSH_TIMEZONE') ?: config('app.timezone'),

    /*
    | Services push acceptés à l'abonnement (hôte de l'endpoint). `*.` couvre
    | les sous-domaines. Tout autre hôte est refusé (422) : le serveur n'envoie
    | jamais de requête vers une URL arbitraire fournie par un apprenant.
    */
    'allowed_hosts' => [
        'fcm.googleapis.com',
        '*.googleapis.com',
        'updates.push.services.mozilla.com',
        '*.push.services.mozilla.com',
        '*.push.apple.com',
        '*.notify.windows.com',
    ],

    // Navigateurs abonnés par apprenant ; au-delà, les plus anciens sont retirés.
    'max_subscriptions_per_learner' => 10,

    // Délais HTTP vers le service push (secondes).
    'http_timeout' => 10,
    'http_connect_timeout' => 5,

    // Au-delà, une notification restée en file est abandonnée (secondes).
    'max_queue_age' => 3600,
];
