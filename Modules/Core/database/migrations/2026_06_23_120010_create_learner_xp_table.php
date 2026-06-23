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
        Schema::create('learner_xp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->unique()->constrained('learners')->onDelete('cascade');
            $table->integer('total_xp')->default(0);
            $table->integer('current_level')->default(1);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learner_xp');
    }
};
