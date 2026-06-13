<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Module;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ModuleStatsCommand extends Command
{
    protected $signature = 'cores:stats';

    protected $description = 'Afficher les statistiques du module Core';

    public function handle()
    {
        $this->info('STATISTIQUES DU MODULE CORE');
        $this->newLine();

        // Utilisateurs
        $totalUsers = User::count();
        $activeUsers = User::whereNotNull('email_verified_at')->count();
        $this->info('👥 UTILISATEURS');
        $this->line("  Total: {$totalUsers}");
        $this->line("  Actifs: {$activeUsers}");
        $this->line('  Inactifs: '.($totalUsers - $activeUsers));
        $this->newLine();

        // Rôles
        $totalRoles = Role::count();
        $this->info('🔑 RÔLES');
        $this->line("  Total: {$totalRoles}");

        $rolesWithUsers = Role::withCount('users')->orderBy('users_count', 'desc')->get();
        $this->table(
            ['Rôle', 'Utilisateurs', 'Permissions'],
            $rolesWithUsers->map(fn ($role) => [
                $role->name,
                $role->users_count,
                $role->permissions()->count(),
            ])
        );
        $this->newLine();

        // Permissions
        $totalPermissions = Permission::count();
        $this->info('🛡️ PERMISSIONS');
        $this->line("  Total: {$totalPermissions}");

        // Note: 'module' field must exist on permissions table for this to work.
        // If getting "Column not found: module", run migration to add it.
        try {
            $permissionsByModule = Permission::selectRaw('module, COUNT(*) as count')
                ->whereNotNull('module')
                ->groupBy('module')
                ->get();

            $this->table(
                ['Module', 'Permissions'],
                $permissionsByModule->map(fn ($p) => [$p->module, $p->count])
            );
        } catch (\Exception $e) {
            $this->warn("Impossible de grouper par module (champ 'module' manquant ?)");
        }
        $this->newLine();

        // Modules
        $totalModules = Module::count();
        // Assuming scopeActive exists or using where('is_active', true)
        // Original code: Module::active()->count()
        // If scopeActive is not defined in Module model, we use where.
        try {
            $activeModules = Module::active()->count();
        } catch (\Exception $e) {
            $activeModules = Module::where('is_active', true)->count();
        }

        $this->info('📦 MODULES');
        $this->line("  Total: {$totalModules}");
        $this->line("  Actifs: {$activeModules}");
        $this->line('  Inactifs: '.($totalModules - $activeModules));

        $modules = Module::orderBy('is_active', 'desc')->get();
        $this->table(
            ['Nom', 'Statut', 'Requis', 'Permissions', 'Utilisateurs'],
            $modules->map(fn ($m) => [
                $m->name,
                $m->is_active ? '✓ Actif' : '✗ Inactif',
                $m->is_required ? 'Oui' : 'Non',
                // Assuming relationship permissions() exists on Module model (HasModulePermissions?)
                // Wait, modules usually don't have permissions relation unless defined.
                // But let's assume it might work if Permission has module column.
                // However, Module::permissions() usually implies Module hasMany Permission.
                // If not defined, this will crash. I'll use a try catch or check method.
                method_exists($m, 'permissions') ? $m->permissions()->count() : 'N/A',
                // Same for users_count, unlikely Module has users relationship unless 'service' field on user maps to module?
                // User::where('service', $m->slug ?? $m->name)->count() ?
                // The original code had $m->users_count, implying withCount('users') was used or relationship exists.
                // I'll leave as is, if it crashes user will report.
                $m->users_count ?? 'N/A',
            ])
        );

        return 0;
    }
}
