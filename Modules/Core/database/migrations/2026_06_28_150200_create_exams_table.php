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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration'); // strict duration in minutes
            $table->integer('passing_score')->default(50); // percentage (e.g. 50% to pass)
            $table->boolean('is_active')->default(true);
            $table->integer('max_attempts')->default(1);

            // Availability window
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();

            // Security Toggles
            $table->boolean('plein_ecran_force')->default(true);
            $table->boolean('anti_capture_strict')->default(true);
            $table->boolean('navigation_interdite')->default(true);

            // Publication Options
            $table->string('publication_resultats')->default('immediate'); // immediate, apres_fermeture, manuelle
            $table->boolean('classement_visible')->default(true);
            $table->boolean('classement_anonyme')->default(false);

            // Grading Options
            $table->integer('note_max')->default(20); // standard scale 20

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
