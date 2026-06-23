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
        Schema::create('learner_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->string('content_type', 50); // 'article', 'quiz'
            $table->unsignedBigInteger('content_id');
            $table->string('status', 20)->default('not_started'); // 'not_started', 'in_progress', 'completed'
            $table->unsignedTinyInteger('progress_percentage')->default(0); // 0-100
            $table->integer('time_spent')->default(0)->comment('Temps en secondes');
            $table->unsignedTinyInteger('rating')->nullable()->comment('Note de 1 à 5 étoiles');
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('learner_id');
            $table->index(['content_type', 'content_id']);
            $table->index('status');
            $table->index('completed_at');
            $table->index(['learner_id', 'status'], 'idx_learner_status');

            // Contrainte: un seul enregistrement par contenu par apprenant
            $table->unique(['learner_id', 'content_type', 'content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learner_progress');
    }
};
