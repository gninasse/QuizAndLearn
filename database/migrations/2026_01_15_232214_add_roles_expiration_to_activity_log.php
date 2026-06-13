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
            // Stocker les rôles de l'utilisateur au moment de l'action
            $table->jsonb('causer_roles')->nullable()->after('causer_id');

            // Date d'expiration de l'activité (configurable par modèle)
            $table->timestamp('expires_at')->nullable()->after('created_at');

            // Durée de rétention en mois (pour info)
            $table->integer('retention_months')->nullable()->after('expires_at');

            // Index pour optimiser les requêtes
            // $table->index('causer_roles');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn(['causer_roles', 'expires_at', 'retention_months']);
        });
    }
};
