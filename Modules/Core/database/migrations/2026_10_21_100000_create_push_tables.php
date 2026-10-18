<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('ref', 64)->nullable();
            $table->timestamp('sent_at');

            $table->index(['learner_id', 'sent_at']);
            $table->index(['learner_id', 'type', 'ref']);
        });

        // Instantané des contenus déjà assignés à chaque groupe (détection de nouveautés).
        Schema::create('push_assignment_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('content_type', 20);
            $table->unsignedBigInteger('content_id');

            $table->unique(['group_id', 'content_type', 'content_id'], 'push_snapshots_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_assignment_snapshots');
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_subscriptions');
    }
};
