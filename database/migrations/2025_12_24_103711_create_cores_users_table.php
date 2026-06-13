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
        Schema::create('cores_users', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Prénom
            $table->string('last_name'); // Nom
            $table->string('user_name')->unique(); // Nom d'utilisateur
            $table->string('email')->unique();
            $table->string('service')->nullable(); // Service
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cores_users');
    }
};
