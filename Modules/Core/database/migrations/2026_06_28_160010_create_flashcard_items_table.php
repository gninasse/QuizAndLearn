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
        Schema::create('flashcard_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained('flashcard_decks')->onDelete('cascade');
            $table->text('recto');
            $table->text('verso');
            $table->json('recto_media')->nullable();
            $table->json('verso_media')->nullable();
            $table->text('tags')->nullable();
            $table->text('note')->nullable();
            $table->integer('ordre')->default(0);
            $table->integer('total_revisions')->default(0);
            $table->decimal('taux_reussite', 5, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashcard_items');
    }
};
