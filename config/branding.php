<?php

/*
|--------------------------------------------------------------------------
| Marque blanche
|--------------------------------------------------------------------------
| Nom, logo et mentions affichés dans le back-office, les pages d'erreur et
| les e-mails. Aucune référence au framework n'est exposée à l'utilisateur :
| un déployeur renomme le produit sans toucher au code.
*/

return [
    'name' => env('BRAND_NAME', 'Learn&Quiz'),
    'tagline' => env('BRAND_TAGLINE', 'Apprendre, s\'entraîner, réussir.'),
    'logo' => env('BRAND_LOGO', '/icons/icon-192x192.png'),
    'owner' => env('BRAND_OWNER', env('BRAND_NAME', 'Learn&Quiz')),
    'support_email' => env('BRAND_SUPPORT_EMAIL'),
];
