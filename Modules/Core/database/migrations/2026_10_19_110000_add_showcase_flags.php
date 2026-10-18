<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SHOWCASE_TABLES = ['quizzes', 'articles', 'flashcard_decks'];

    public function up(): void
    {
        foreach (self::SHOWCASE_TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_showcase')->default(false)->index();
            });
        }

        Schema::table('groups', function (Blueprint $t) {
            $t->boolean('open_for_requests')->default(false);
        });
    }

    public function down(): void
    {
        foreach (self::SHOWCASE_TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_is_showcase_index');
                $t->dropColumn('is_showcase');
            });
        }

        Schema::table('groups', function (Blueprint $t) {
            $t->dropColumn('open_for_requests');
        });
    }
};
