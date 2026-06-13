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
        Schema::table('permissions', function (Blueprint $table) {
            // Catégorie de permission (view, create, edit, delete, manage)
            $table->string('category')->nullable()->after('module');
            $table->index('category');

            // Description de la permission
            $table->text('description')->nullable()->after('category');

            // Groupe de permissions (pour l'UI)
            $table->string('group')->nullable()->after('description');

            // Ordre d'affichage
            $table->integer('sort_order')->default(0)->after('group');

            // Si la permission est visible dans l'UI de gestion
            $table->boolean('is_visible')->default(true)->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'description',
                'group',
                'sort_order',
                'is_visible',
            ]);
        });
    }
};
