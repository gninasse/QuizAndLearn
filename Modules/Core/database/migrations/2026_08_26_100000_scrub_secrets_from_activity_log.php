<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purge les secrets (hash de mot de passe, remember_token) déjà écrits
     * dans activity_log.properties avant l'ajout de logExcept() au trait
     * LogsActivityWithModule. Traitement en PHP pour rester compatible
     * avec tous les drivers (PostgreSQL en prod, SQLite en tests).
     */
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        DB::table('activity_log')
            ->where('properties', 'like', '%password%')
            ->orWhere('properties', 'like', '%remember_token%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $properties = json_decode($row->properties, true);
                    if (! is_array($properties)) {
                        continue;
                    }

                    $changed = false;
                    foreach (['attributes', 'old'] as $bucket) {
                        foreach (['password', 'remember_token'] as $secret) {
                            if (isset($properties[$bucket][$secret])) {
                                unset($properties[$bucket][$secret]);
                                $changed = true;
                            }
                        }
                        // Un bucket vidé de son seul contenu disparaît.
                        if (isset($properties[$bucket]) && $properties[$bucket] === []) {
                            unset($properties[$bucket]);
                        }
                    }

                    if ($changed) {
                        DB::table('activity_log')
                            ->where('id', $row->id)
                            ->update(['properties' => json_encode($properties)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Les secrets purgés ne peuvent (et ne doivent) pas être restaurés.
    }
};
