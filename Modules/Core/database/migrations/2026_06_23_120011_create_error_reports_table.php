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
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->string('content_type', 50); // 'quiz' ou 'article'
            $table->unsignedBigInteger('content_id');
            $table->string('error_type', 50); // 'content', 'spelling', 'technical'
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending'); // 'pending', 'resolved', 'ignored'
            $table->timestamps();

            // Indexes
            $table->index(['content_type', 'content_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
