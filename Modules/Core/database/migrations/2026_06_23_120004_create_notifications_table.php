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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50); // 'new_quiz', 'new_article', 'streak_reminder', 'flashcard_due', 'exam_deadline'
            $table->string('title');
            $table->text('message');
            $table->text('action_url')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('priority', 20)->default('normal'); // 'low', 'normal', 'high', 'urgent'
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('type');
            $table->index(['is_read', 'created_at']);
            $table->index('priority');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
