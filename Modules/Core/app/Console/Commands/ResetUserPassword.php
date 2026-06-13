<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\User;

class ResetUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cores:reset-user-password {user_name : Le nom d\'utilisateur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Réinitialiser le mot de passe d\'un utilisateur via son user_name';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userName = $this->argument('user_name');

        $user = User::where('user_name', $userName)->first();

        if (! $user) {
            $this->error("Utilisateur avec le nom d'utilisateur '{$userName}' introuvable.");

            return 1;
        }

        $defaultPassword = config('core.user_default_password', 'password');

        $user->password = Hash::make($defaultPassword);
        $user->save();

        $this->info("Mot de passe réinitialisé avec succès pour user_name: {$userName}");
        $this->info("Nouveau mot de passe: {$defaultPassword}");

        return 0;
    }
}
