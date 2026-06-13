<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\PermissionService;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'cores:sync-permissions {module?}';

    protected $description = 'Synchroniser les permissions des modules';

    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        parent::__construct();
        $this->permissionService = $permissionService;
    }

    public function handle()
    {
        $moduleSlug = $this->argument('module');

        if ($moduleSlug) {
            $this->info("Synchronisation des permissions du module: {$moduleSlug}");

            try {
                $result = $this->permissionService->syncModulePermissions($moduleSlug);

                $this->info("✓ {$result['created']} permission(s) créée(s)");
                $this->info("✓ {$result['updated']} permission(s) mise(s) à jour");
            } catch (\Exception $e) {
                $this->error("Erreur: {$e->getMessage()}");

                return 1;
            }
        } else {
            $this->info('Synchronisation de tous les modules...');

            $result = $this->permissionService->syncAllModulePermissions();

            $this->info("✓ {$result['created']} permission(s) créée(s)");
            $this->info("✓ {$result['updated']} permission(s) mise(s) à jour");
        }

        return 0;
    }
}
