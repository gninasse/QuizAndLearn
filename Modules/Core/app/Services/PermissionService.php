<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Module;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    /**
     * Synchroniser les permissions de tous les modules
     */
    public function syncAllModulePermissions(): array
    {
        $modules = Module::all();
        $created = 0;
        $updated = 0;

        foreach ($modules as $module) {
            $result = $this->syncModulePermissions($module->slug);
            $created += $result['created'];
            $updated += $result['updated'];
        }

        // Vider le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Synchroniser les permissions d'un module spécifique
     */
    public function syncModulePermissions(string $moduleSlug): array
    {
        $moduleInfo = ModuleFacade::find($moduleSlug);

        if (! $moduleInfo) {
            throw new \Exception("Le module '{$moduleSlug}' n'existe pas.");
        }

        // Charger le fichier de configuration des permissions
        $configPath = $moduleInfo->getPath().'/config/permissions.php';

        if (! file_exists($configPath)) {
            return ['created' => 0, 'updated' => 0];
        }

        $permissionsConfig = require $configPath;
        $created = 0;
        $updated = 0;

        foreach ($permissionsConfig as $name => $label) {
            $permission = Permission::firstOrNew(['name' => $name]);

            $wasRecentlyCreated = ! $permission->exists;

            $permission->fill([
                'module' => strtolower($moduleSlug),
                'label' => $label,
                'description' => $label,
                'category' => $this->extractCategory($name),
                'guard_name' => 'web',
            ]);

            $permission->save();

            if ($wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        // Vider le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Extraire la catégorie depuis le nom de permission
     */
    protected function extractCategory(string $permissionName): string
    {
        if (str_contains($permissionName, '.view')) {
            return 'view';
        }
        if (str_contains($permissionName, '.index')) {
            return 'view';
        }
        if (str_contains($permissionName, '.create')) {
            return 'create';
        }
        if (str_contains($permissionName, '.store')) {
            return 'create';
        }
        if (str_contains($permissionName, '.edit')) {
            return 'edit';
        }
        if (str_contains($permissionName, '.update')) {
            return 'edit';
        }
        if (str_contains($permissionName, '.delete')) {
            return 'delete';
        }
        if (str_contains($permissionName, '.destroy')) {
            return 'delete';
        }
        if (str_contains($permissionName, '.toggle')) {
            return 'toggle';
        }
        if (str_contains($permissionName, '.show')) {
            return 'view';
        }
        if (str_contains($permissionName, '.manage')) {
            return 'manage';
        }
        if (str_contains($permissionName, '.assign')) {
            return 'assign';
        }
        if (str_contains($permissionName, '.configure')) {
            return 'configure';
        }
        if (str_contains($permissionName, '.enable')) {
            return 'enable';
        }
        if (str_contains($permissionName, '.disable')) {
            return 'disable';
        }
        if (str_contains($permissionName, '.install')) {
            return 'install';
        }
        if (str_contains($permissionName, '.uninstall')) {
            return 'uninstall';
        }

        return 'other';
    }

    /**
     * Obtenir les permissions groupées par module
     */
    public function getPermissionsByModule(): array
    {
        return Permission::all()
            ->groupBy('module')
            ->map(function ($permissions) {
                return $permissions->groupBy('category');
            })
            ->toArray();
    }

    /**
     * Créer des permissions en masse pour un module
     */
    public function createModulePermissions(string $module, array $permissions): int
    {
        $created = 0;

        foreach ($permissions as $key => $permission) {
            if (is_int($key) && is_string($permission)) {
                $name = $permission;
                $label = null;
            } elseif (is_int($key) && is_array($permission)) {
                $name = $permission['name'];
                $label = $permission['label'] ?? null;
            } elseif (is_string($key) && is_string($permission)) {
                $name = $key;
                $label = $permission;
            }

            $permissionModel = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'module' => $module,
                    'label' => $label,
                    'description' => $label,
                    'category' => $this->extractCategory($name),
                    'guard_name' => 'web',
                ]
            );

            if ($permissionModel->wasRecentlyCreated) {
                $created++;
            }
        }

        // Vider le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $created;
    }

    /**
     * Supprimer toutes les permissions d'un module
     */
    public function deleteModulePermissions(string $module): int
    {
        $count = Permission::where('module', $module)->count();
        Permission::where('module', $module)->delete();

        // Vider le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $count;
    }

    /**
     * Obtenir les statistiques des permissions
     */
    public function getPermissionStats(): array
    {
        return [
            'total' => Permission::count(),
            'by_module' => Permission::selectRaw('module, COUNT(*) as count')
                ->whereNotNull('module')
                ->groupBy('module')
                ->pluck('count', 'module')
                ->toArray(),
            'by_category' => Permission::selectRaw('category, COUNT(*) as count')
                ->whereNotNull('category')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
        ];
    }
}
