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
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('type'); // true_false, mcq, single_choice, multiple_choice, open_text, fill_blank, ordering, matching
            $table->integer('points')->default(1);
            $table->decimal('points_negatifs', 3, 2)->default(0.00); // negative penalty for wrong answer
            $table->integer('order')->default(0);
            $table->json('options')->nullable(); // holds choices, matching pairs, fill blank answers
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};
