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
        Schema::create('learner_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->unique()->constrained('learners')->onDelete('cascade');
            $table->string('locale', 10)->default('fr');
            $table->string('theme', 20)->default('light'); // 'light', 'dark', 'auto'
            $table->string('font_size', 20)->default('medium'); // 'small', 'medium', 'large'
            $table->boolean('sound_enabled')->default(true);
            $table->json('notifications_enabled')->nullable(); // pushes, reminders, etc.
            $table->time('streak_reminder_time')->default('20:00:00');
            $table->time('dnd_start')->default('22:00:00');
            $table->time('dnd_end')->default('08:00:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learner_preferences');
    }
};
