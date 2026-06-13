<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Module;
use Spatie\Permission\Models\Permission;

class CleanupPermissionsCommand extends Command
{
    protected $signature = 'cores:cleanup-permissions';

    protected $description = 'Nettoyer les permissions orphelines (sans module)';

    public function handle()
    {
        $this->info('Recherche des permissions orphelines...');

        // Ensure module column exists
        try {
            $orphanPermissions = Permission::whereNull('module')
                ->orWhereNotIn('module', Module::pluck('slug'))
                ->get();
        } catch (\Exception $e) {
            $this->error('Erreur lors de la requête: '.$e->getMessage());

            return 1;
        }

        if ($orphanPermissions->isEmpty()) {
            $this->info('✓ Aucune permission orpheline trouvée.');

            return 0;
        }

        $this->warn("Permissions orphelines trouvées: {$orphanPermissions->count()}");

        $this->table(
            ['ID', 'Nom', 'Module'],
            $orphanPermissions->map(fn ($p) => [$p->id, $p->name, $p->module ?? 'N/A'])
        );

        if ($this->confirm('Voulez-vous supprimer ces permissions ?')) {
            $count = $orphanPermissions->count();

            foreach ($orphanPermissions as $permission) {
                $permission->delete();
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $this->info("✓ {$count} permission(s) supprimée(s)");
        } else {
            $this->info('Opération annulée.');
        }

        return 0;
    }
}
