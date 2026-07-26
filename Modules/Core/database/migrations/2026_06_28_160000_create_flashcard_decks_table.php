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
        Schema::create('flashcard_decks', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('matiere')->nullable();
            $table->string('source_type')->default('manuel'); // 'manuel', 'quiz', 'examen', 'article'
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->string('algorithme')->default('sm2'); // 'sm2', 'leitner'
            $table->decimal('easiness_default', 3, 2)->default(2.50);
            $table->integer('interval_min')->default(1);
            $table->integer('interval_max')->default(365);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashcard_decks');
    }
};
