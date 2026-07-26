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
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();

            // Timestamps
            $table->timestamp('date_debut')->useCurrent();
            $table->timestamp('date_fin')->nullable();
            $table->integer('duree_reelle')->nullable(); // in seconds

            // Detailed responses
            $table->json('answers')->nullable(); // holds JSON of questions answers, correctness, and points

            // Scores
            $table->decimal('score_brut', 6, 2)->nullable();
            $table->decimal('score_total', 6, 2)->nullable();
            $table->decimal('pourcentage', 5, 2)->nullable();
            $table->decimal('note_sur_vingt', 4, 2)->nullable();

            // Status and Metadata
            $table->string('status')->default('en_cours'); // en_cours, termine, annule, time_up
            $table->integer('capture_attempts')->default(0);
            $table->integer('navigation_violations')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
