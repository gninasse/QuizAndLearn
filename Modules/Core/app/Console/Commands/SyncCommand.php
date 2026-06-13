<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\ModuleService;
use Modules\Core\Services\PermissionService;

class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cores:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronisation complète des modules et des permissions';

    /**
     * Execute the console command.
     */
    public function handle(ModuleService $moduleService, PermissionService $permissionService): int
    {
        $this->info('--- Début de la synchronisation globale ---');

        $this->info('1. Synchronisation des modules avec le système de fichiers...');
        $syncedModules = $moduleService->syncModules();

        $this->table(
            ['Nom', 'Slug', 'Statut', 'Version'],
            collect($syncedModules)->map(fn ($module) => [
                $module->name,
                $module->slug,
                $module->is_active ? 'Actif' : 'Inactif',
                $module->version,
            ])
        );
        $this->info('✓ Modules synchronisés.');

        $this->info('2. Synchronisation des permissions depuis les fichiers config...');
        $result = $permissionService->syncAllModulePermissions();
        $this->info("✓ Permissions synchronisées: {$result['created']} créée(s), {$result['updated']} mise(s) à jour.");

        $this->info('--- Synchronisation terminée avec succès ---');

        return 0;
    }
}
