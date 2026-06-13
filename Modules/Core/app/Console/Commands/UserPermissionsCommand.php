<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\User;

class UserPermissionsCommand extends Command
{
    protected $signature = 'cores:user-permissions {user}';

    protected $description = 'Afficher les permissions d\'un utilisateur';

    public function handle()
    {
        $userId = $this->argument('user');

        $user = User::findByIdOrUsername($userId); // Assuming finding logic, or just find($id)

        // Since original used find($userId), and userId argument name is {user}, it might be ID.
        // But let's check if we can support ID.
        $user = User::find($userId);

        // If not found by ID, maybe try email? Original code just did User::find($userId).
        if (! $user) {
            $user = User::where('email', $userId)->first();
        }

        if (! $user) {
            $this->error("Utilisateur '{$userId}' non trouvé (ID ou Email).");

            return 1;
        }

        $this->info("Permissions de: {$user->name} {$user->last_name} ({$user->email})");
        $this->newLine();

        // Rôles
        $this->info('RÔLES:');
        if ($user->roles->isEmpty()) {
            $this->warn('  Aucun rôle assigné');
        } else {
            foreach ($user->roles as $role) {
                $this->line("  • {$role->name}");
            }
        }
        $this->newLine();

        // Permissions directes
        $this->info('PERMISSIONS DIRECTES:');
        if ($user->permissions->isEmpty()) {
            $this->warn('  Aucune permission directe');
        } else {
            foreach ($user->permissions as $permission) {
                $this->line("  • {$permission->name}");
            }
        }
        $this->newLine();

        // Toutes les permissions (via rôles + directes)
        $allPermissions = $user->getAllPermissions();
        $this->info("TOTAL DES PERMISSIONS: {$allPermissions->count()}");

        if ($this->option('verbose')) {
            $permissionsByModule = $allPermissions->groupBy('module');

            foreach ($permissionsByModule as $module => $permissions) {
                $this->newLine();
                $this->info('Module: '.($module ?? 'Général'));
                foreach ($permissions as $permission) {
                    $this->line("  • {$permission->name}");
                }
            }
        }

        // Modules accessibles
        // Check if getAccessibleModules exists on Users (it should via HasModulePermissions trait)
        if (method_exists($user, 'getAccessibleModules')) {
            $modules = $user->getAccessibleModules();
            $this->newLine();
            $this->info('MODULES ACCESSIBLES: '.(is_countable($modules) ? count($modules) : 0));
            foreach ($modules as $module) {
                $this->line("  • {$module}");
            }
        } else {
            $this->warn('  getAccessibleModules() non disponible sur le modèle User.');
        }

        return 0;
    }
}
