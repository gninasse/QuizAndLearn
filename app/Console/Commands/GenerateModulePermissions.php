<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;

class GenerateModulePermissions extends Command
{
    protected $signature = 'permissions:generate {module?} {--sync}';

    protected $description = 'Générer les permissions pour les modules.';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $sync = $this->option('sync');

        if ($moduleName) {
            $this->generateForModule($moduleName, $sync);
        } else {
            $this->generateForAllModules($sync);
        }

        $this->info('Permissions générées avec succès!');
    }

    protected function generateForAllModules($sync)
    {
        $modules = Module::all();

        foreach ($modules as $module) {
            $this->generateForModule($module->getName(), $sync);
        }
    }

    protected function generateForModule($moduleName, $sync)
    {
        $module = Module::find($moduleName);

        if (! $module) {
            $this->error("Le module {$moduleName} n'existe pas.");

            return;
        }

        $this->info("Génération des permissions pour le module: {$moduleName}");

        // Lire le fichier de configuration des permissions
        $configPath = $module->getPath().'/config/permissions.php';

        if (! file_exists($configPath)) {
            $this->warn("Aucun fichier permissions.php trouvé dans {$moduleName}/config/");

            return;
        }

        $permissions = require $configPath;

        if ($sync) {
            // Supprimer les anciennes permissions
            Permission::where('module', strtolower($moduleName))->delete();
            $this->warn("Anciennes permissions supprimées pour {$moduleName}");
        }

        foreach ($permissions as $key => $permission) {
            $permissionModel = Permission::firstOrCreate(
                ['name' => $key],
                [
                    'label' => $permission,
                    'description' => $permission,
                    'module' => strtolower($moduleName),
                    'guard_name' => 'web',
                    'category' => $this->extractCategory($key),
                ]
            );

            if ($permissionModel->wasRecentlyCreated) {
                $this->line("  ✓ {$permission}");
            } else {
                $this->line("  - {$permission} (existe déjà)");
            }
        }
    }

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
}
