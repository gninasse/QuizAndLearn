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
        Schema::create('flashcard_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('deck_id')->constrained('flashcard_decks')->onDelete('cascade');
            $table->timestamp('date_debut');
            $table->timestamp('date_fin')->nullable();
            $table->integer('duree_seconds')->nullable();
            $table->integer('cartes_etudiees')->default(0);
            $table->integer('cartes_nouvelles')->default(0);
            $table->integer('cartes_revues')->default(0);
            $table->integer('cartes_maitrisees')->default(0);
            $table->json('grades')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashcard_sessions');
    }
};
