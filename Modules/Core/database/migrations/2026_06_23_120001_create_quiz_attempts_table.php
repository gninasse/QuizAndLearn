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
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable()->comment('Score en pourcentage 0-100');
            $table->integer('points_earned')->nullable();
            $table->integer('points_total')->nullable();
            $table->boolean('passed')->nullable();
            $table->integer('time_spent')->nullable()->comment('Temps en secondes');
            $table->json('answers')->nullable()->comment('Réponses par question');
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 20)->default('in_progress'); // 'in_progress', 'completed', 'abandoned'
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['learner_id', 'quiz_id']);
            $table->index('status');
            $table->index('completed_at');
            $table->index(['learner_id', 'quiz_id', 'completed_at'], 'idx_attempts_composite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
