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
        Schema::create('flashcard_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_item_id')->constrained('flashcard_items')->onDelete('cascade');
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->decimal('easiness_factor', 3, 2)->default(2.50);
            $table->integer('interval_days')->default(0);
            $table->integer('repetitions')->default(0);
            $table->timestamp('last_reviewed')->nullable();
            $table->timestamp('next_review')->nullable();
            $table->string('status')->default('new'); // 'new', 'learning', 'review', 'relearning', 'mastered'
            $table->json('review_history')->nullable();
            $table->timestamps();

            $table->unique(['flashcard_item_id', 'learner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashcard_item_reviews');
    }
};
