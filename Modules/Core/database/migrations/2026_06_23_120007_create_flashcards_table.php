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
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->decimal('difficulty_factor', 3, 2)->default(2.50)->comment('EF (SM-2 Easiness Factor)');
            $table->integer('interval_days')->default(1);
            $table->integer('repetitions')->default(0);
            $table->date('next_review_date');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->string('ease_rating', 20)->nullable(); // 'easy', 'medium', 'hard', 'again'
            $table->timestamps();

            $table->unique(['learner_id', 'question_id']);
            $table->index('next_review_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
