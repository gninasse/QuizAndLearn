<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Génère une paire de clés VAPID (affichée au format .env, aucun fichier modifié)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Copiez ces lignes dans le .env (avec VAPID_SUBJECT=mailto:contact@votre-domaine), puis php artisan config:cache.');

        return self::SUCCESS;
    }
}
