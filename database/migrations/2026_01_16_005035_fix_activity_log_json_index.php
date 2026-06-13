<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            //
            // 1. On supprime l'index problématique s'il existe
            // $table->dropIndex('activity_log_causer_roles_index');

            // 2. On change le type de json à jsonb (mieux pour Postgres)
            // Note: change() nécessite le package doctrine/dbal ou Laravel 10+
            $table->jsonb('causer_roles')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            //
            $table->json('causer_roles')->nullable()->change();
            // $table->index('causer_roles', 'activity_log_causer_roles_index');
        });
    }
};
