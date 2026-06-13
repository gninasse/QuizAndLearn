<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\ModuleService;

class SyncModulesCommand extends Command
{
    protected $signature = 'cores:sync-modules';

    protected $description = 'Synchroniser les modules détectés avec la base de données';

    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        parent::__construct();
        $this->moduleService = $moduleService;
    }

    public function handle()
    {
        $this->info('Synchronisation des modules...');

        $synced = $this->moduleService->syncModules();

        $this->table(
            ['Nom', 'Slug', 'Statut', 'Version'],
            collect($synced)->map(fn ($module) => [
                $module->name,
                $module->slug,
                $module->is_active ? 'Actif' : 'Inactif',
                $module->version,
            ])
        );

        $this->info('✓ '.count($synced).' module(s) synchronisé(s)');

        return 0;
    }
}
