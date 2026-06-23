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
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_attempts')->default(3)->after('is_active');
            $table->boolean('show_correct_answers')->default(true)->after('max_attempts');
            $table->boolean('allow_partial_score')->default(false)->after('show_correct_answers');
            $table->timestamp('available_from')->nullable()->after('allow_partial_score');
            $table->timestamp('available_until')->nullable()->after('available_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'show_correct_answers', 'allow_partial_score', 'available_from', 'available_until']);
        });
    }
};
