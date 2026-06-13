<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MakeSuperAdminCommand extends Command
{
    protected $signature = 'cores:make-superadmin {email}';

    protected $description = 'Créer ou promouvoir un utilisateur en super-admin';

    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Utilisateur avec l'email {$email} non trouvé.");

            if ($this->confirm('Voulez-vous créer cet utilisateur ?')) {
                $firstName = $this->ask('Prénom (name)');
                $lastName = $this->ask('Nom (last_name)');
                $userName = $this->ask('Nom d\'utilisateur (user_name)');
                $password = $this->secret('Mot de passe');

                $user = User::create([
                    'name' => $firstName,
                    'last_name' => $lastName,
                    'user_name' => $userName,
                    'email' => $email,
                    'password' => bcrypt($password),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                $this->info('Utilisateur créé avec succès.');
            } else {
                return 1;
            }
        }

        // Créer le rôle super-admin s'il n'existe pas
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        // Donner toutes les permissions au rôle
        $superAdminRole->syncPermissions(Permission::all());

        // Assigner le rôle à l'utilisateur
        $user->assignRole('super-admin');

        $this->info("✓ {$user->name} {$user->last_name} ({$user->email}) est maintenant super-admin!");

        return 0;
    }
}
