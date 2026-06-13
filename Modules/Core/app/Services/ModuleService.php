<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\Module;
use Nwidart\Modules\Facades\Module as ModuleFacade;

class ModuleService
{
    /**
     * Obtenir les modules détectés mais non enregistrés
     */
    public function getDetectedModules(): array
    {
        $allModules = ModuleFacade::all();
        $registeredModules = Module::pluck('slug')->toArray();

        $detected = [];

        foreach ($allModules as $moduleInfo) {
            $slug = strtolower($moduleInfo->getName());

            if (! in_array($slug, $registeredModules)) {
                $detected[] = [
                    'name' => $moduleInfo->getName(),
                    'slug' => $slug,
                    'description' => $moduleInfo->getDescription(),
                    'path' => $moduleInfo->getPath(),
                ];
            }
        }

        return $detected;
    }

    /**
     * Installer un module
     */
    public function installModule(string $moduleSlug): Module
    {
        $moduleInfo = ModuleFacade::find($moduleSlug);

        if (! $moduleInfo) {
            throw new \Exception("Le module '{$moduleSlug}' n'existe pas.");
        }

        // Vérifier si déjà installé
        $existingModule = Module::where('slug', $moduleSlug)->first();
        if ($existingModule) {
            throw new \Exception('Le module est déjà installé.');
        }

        // Créer l'enregistrement
        $module = Module::create([
            'name' => $moduleInfo->getName(),
            'slug' => strtolower($moduleSlug),
            'description' => $moduleInfo->getDescription(),
            'version' => '1.0.0',
            'is_active' => false,
            'is_required' => false,
            'installed_at' => now(),
        ]);

        // Exécuter les migrations du module
        try {
            Artisan::call('module:migrate', ['module' => $moduleSlug]);
        } catch (\Exception $e) {
            $module->delete();
            throw new \Exception("Erreur lors de l'exécution des migrations : {$e->getMessage()}");
        }

        // Exécuter les seeders de permissions
        try {
            Artisan::call('module:seed', [
                'module' => $moduleSlug,
                '--class' => "{$moduleSlug}PermissionsSeeder",
            ]);
        } catch (\Exception $e) {
            // Les seeders peuvent ne pas exister, on continue
        }

        return $module;
    }

    /**
     * Désinstaller un module
     */
    public function uninstallModule(Module $module): void
    {
        if ($module->is_required) {
            throw new \Exception('Ce module est requis et ne peut pas être désinstallé.');
        }

        if ($module->is_active) {
            throw new \Exception("Désactivez d'abord le module avant de le désinstaller.");
        }

        // Supprimer les permissions associées
        \Spatie\Permission\Models\Permission::where('module', $module->slug)->delete();

        // Rollback des migrations (optionnel et risqué)
        // Artisan::call('module:migrate-rollback', ['module' => $module->slug]);

        $module->delete();
    }

    /**
     * Activer un module
     */
    public function enableModule(Module $module): void
    {
        // Vérifier et activer les dépendances
        if ($module->dependencies) {
            foreach ($module->dependencies as $dependencySlug) {
                $dependency = Module::where('slug', $dependencySlug)->first();

                if (! $dependency) {
                    throw new \Exception("Le module dépendant '{$dependencySlug}' n'est pas installé.");
                }

                if (! $dependency->is_active) {
                    $this->enableModule($dependency);
                }
            }
        }

        // Activer le module dans Laravel Modules
        ModuleFacade::enable($module->slug);

        // Mettre à jour en base
        $module->activate();
    }

    /**
     * Désactiver un module
     */
    public function disableModule(Module $module): void
    {
        if (! $module->canBeDeactivated()) {
            // Trouver les modules dépendants
            $dependents = Module::active()
                ->where('id', '!=', $module->id)
                ->get()
                ->filter(function ($m) use ($module) {
                    return in_array($module->slug, $m->dependencies ?? []);
                })
                ->pluck('name')
                ->implode(', ');

            throw new \Exception("Ce module ne peut pas être désactivé car d'autres modules en dépendent : {$dependents}");
        }

        // Désactiver le module dans Laravel Modules
        ModuleFacade::disable($module->slug);

        // Mettre à jour en base
        $module->deactivate();
    }

    /**
     * Synchroniser les modules détectés avec la base de données
     */
    public function syncModules(): array
    {
        $allModules = ModuleFacade::all();
        $synced = [];

        foreach ($allModules as $moduleInfo) {
            $slug = strtolower($moduleInfo->getName());

            $module = Module::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $moduleInfo->getName(),
                    'description' => $moduleInfo->getDescription(),
                    'is_active' => $moduleInfo->isEnabled(),
                ]
            );

            $synced[] = $module;
        }

        return $synced;
    }
}
