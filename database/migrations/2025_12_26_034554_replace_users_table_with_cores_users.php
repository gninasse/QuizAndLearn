<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('users');
        Schema::rename('cores_users', 'users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('users', 'cores_users');
        // Note: We cannot easily restore the dropped 'users' table without its original structure definition.
        // Assuming 'cores_users' was the intended source of truth.
    }
};
