<?php

namespace Modules\Core\Traits;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

trait HasModulePermissions
{
    /**
     * Vérifier si l'utilisateur a accès à un module
     */
    public function hasModuleAccess(string $module): bool
    {
        $permissions = Permission::where('module', $module)->pluck('name');

        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtenir tous les modules accessibles
     */
    public function getAccessibleModules(): Collection
    {
        return $this->getAllPermissions()
            ->pluck('module')
            ->unique()
            ->filter()
            ->values();
    }

    /**
     * Obtenir les permissions d'un module spécifique
     */
    public function getModulePermissions(string $module): Collection
    {
        return $this->getAllPermissions()
            ->where('module', $module)
            ->pluck('name');
    }

    /**
     * Vérifier si l'utilisateur a toutes les permissions d'un module
     */
    public function hasAllModulePermissions(string $module): bool
    {
        $modulePermissions = Permission::where('module', $module)->get();

        foreach ($modulePermissions as $permission) {
            if (! $this->can($permission->name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assigner toutes les permissions d'un module
     */
    public function giveModulePermissions(string $module): void
    {
        $permissions = Permission::where('module', $module)->get();
        $this->givePermissionTo($permissions);
    }

    /**
     * Retirer toutes les permissions d'un module
     */
    public function revokeModulePermissions(string $module): void
    {
        $permissions = Permission::where('module', $module)->pluck('name');
        $this->revokePermissionTo($permissions);
    }

    /**
     * Vérifier si l'utilisateur peut accéder à une ressource du module
     */
    public function canAccessModuleResource(string $module, string $resource, string $action): bool
    {
        return $this->can("{$module}.{$resource}.{$action}");
    }

    /**
     * Obtenir un menu de navigation basé sur les modules accessibles
     */
    public function getModuleNavigation(): array
    {
        $modules = $this->getAccessibleModules();
        $navigation = [];

        foreach ($modules as $module) {
            $config = config("modules.{$module}.navigation");
            if ($config) {
                $navigation[$module] = $config;
            }
        }

        return $navigation;
    }
}
