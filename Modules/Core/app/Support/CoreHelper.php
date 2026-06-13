<?php

namespace Modules\Core\Support;

use Illuminate\Support\Collection;
use Modules\Core\Models\Module;

class CoreHelper
{
    /**
     * Valider la structure d'un nom de permission
     */
    public static function validatePermissionName(string $name): bool
    {
        return preg_match('/^[a-z]+\.[a-z]+\.[a-z]+$/', $name) === 1;
    }

    /**
     * Obtenir les statistiques détaillées d'un module
     */
    public static function getModuleStats(Module $module): array
    {
        return [
            'permissions_count' => $module->permissions()->count(),
            'users_count' => $module->users_count,
            'is_active' => $module->is_active,
            'is_required' => $module->is_required,
            'has_dependencies' => ! empty($module->dependencies),
            'dependencies_count' => count($module->dependencies ?? []),
        ];
    }

    /**
     * Vérifier les dépendances d'un module
     */
    public static function checkModuleDependencies(Module $module): array
    {
        $missing = [];

        if ($module->dependencies) {
            foreach ($module->dependencies as $dependency) {
                $dep = Module::where('slug', $dependency)->first();

                if (! $dep || ! $dep->is_active) {
                    $missing[] = $dependency;
                }
            }
        }

        return $missing;
    }

    /**
     * Obtenir les modules qui dépendent d'un module donné
     */
    public static function getDependentModules(Module $module): Collection
    {
        return Module::active()
            ->get()
            ->filter(function ($m) use ($module) {
                return in_array($module->slug, $m->dependencies ?? []);
            });
    }
}
