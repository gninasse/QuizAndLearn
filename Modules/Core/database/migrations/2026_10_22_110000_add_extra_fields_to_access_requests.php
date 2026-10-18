<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réponses aux champs personnalisés du formulaire de demande d'accès,
 * stockées avec leur libellé : [{key, label, value}].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_requests', function (Blueprint $table) {
            $table->json('extra_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('access_requests', function (Blueprint $table) {
            $table->dropColumn('extra_fields');
        });
    }
};
