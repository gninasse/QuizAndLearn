<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Services\ShowcaseSettings;

class PurgeAccessRequestsCommand extends Command
{
    protected $signature = 'showcase:purge-requests';

    protected $description = "Supprime les demandes d'accès refusées ou abandonnées au-delà de la durée de conservation";

    public function handle(ShowcaseSettings $settings): int
    {
        $cutoff = now()->subMonths($settings->retentionMonths());

        $count = 0;
        AccessRequest::query()
            ->whereIn('status', ['rejected', 'abandoned'])
            ->where('updated_at', '<', $cutoff)
            ->each(function (AccessRequest $request) use (&$count) {
                $request->delete();
                $count++;
            });

        $this->info("{$count} demande(s) d'accès supprimée(s).");

        return self::SUCCESS;
    }
}
