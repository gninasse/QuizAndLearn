<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Role;

class InitRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cores:init-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialiser les rôles principaux (super-admin, admin, trainer, learner)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $roles = [
            'super-admin' => 'Super Administrateur avec accès total',
            'admin' => 'Administrateur système',
            'trainer' => 'Formateur / Enseignant',
            'learner' => 'Apprenant / Étudiant',
        ];

        foreach ($roles as $name => $description) {
            $role = Role::where('name', $name)->first();

            if ($role) {
                $this->info("Rôle existant conservé : {$name}");
            } else {
                Role::create([
                    'name' => $name,
                    'description' => $description,
                    'guard_name' => 'web',
                ]);
                $this->info("Rôle créé avec succès : {$name}");
            }
        }

        $this->info('Initialisation des rôles terminée.');

        return 0;
    }
}
