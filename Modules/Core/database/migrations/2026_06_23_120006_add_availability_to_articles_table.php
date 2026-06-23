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
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedSmallInteger('estimated_reading_time')->nullable()->after('content');
            $table->timestamp('available_from')->nullable()->after('is_active');
            $table->timestamp('available_until')->nullable()->after('available_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['estimated_reading_time', 'available_from', 'available_until']);
        });
    }
};
