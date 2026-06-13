<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->string('module')->nullable()->after('log_name');
            $table->json('context')->nullable()->after('properties'); // Contexte supplémentaire
            $table->string('ip_address')->nullable()->after('context');
            $table->text('user_agent')->nullable()->after('ip_address');

            $table->index('module');
            $table->index('causer_id');
            $table->index('subject_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn(['module', 'context', 'ip_address', 'user_agent']);
        });
    }
};
