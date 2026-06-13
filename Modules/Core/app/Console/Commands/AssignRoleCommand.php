<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class AssignRoleCommand extends Command
{
    protected $signature = 'cores:assign-role {role} {--users=*}';

    protected $description = 'Assigner un rôle à un ou plusieurs utilisateurs';

    public function handle()
    {
        $roleName = $this->argument('role');
        $userIds = $this->option('users');

        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            $this->error("Rôle '{$roleName}' non trouvé.");

            $this->info('Rôles disponibles:');
            foreach (Role::all() as $r) {
                $this->line("  • {$r->name}");
            }

            return 1;
        }

        if (empty($userIds)) {
            $this->error('Veuillez spécifier au moins un ID utilisateur avec --users');

            return 1;
        }

        $assigned = 0;

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                // Try finding by email
                $user = User::where('email', $userId)->first();
            }
            // Try finding by username
            if (! $user) {
                $user = User::where('user_name', $userId)->first();
            }

            if ($user) {
                $user->assignRole($role);
                $this->info("✓ Rôle '{$roleName}' assigné à {$user->name} {$user->last_name}");
                $assigned++;
            } else {
                $this->warn("✗ Utilisateur '{$userId}' non trouvé");
            }
        }

        $this->newLine();
        $this->info("Total: {$assigned} utilisateur(s) mis à jour");

        return 0;
    }
}
