<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'idempotence des actions hors-ligne rejouées par le client
     * (outbox pattern) : une action déjà appliquée n'est jamais rejouée.
     */
    public function up(): void
    {
        Schema::create('learner_action_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->uuid('client_action_id')->unique();
            $table->string('type', 50);
            $table->string('status', 20); // applied | rejected
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['learner_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_action_log');
    }
};
