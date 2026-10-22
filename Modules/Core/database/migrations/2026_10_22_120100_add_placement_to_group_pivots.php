<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const PIVOTS = ['group_article', 'group_quiz', 'group_exam', 'group_flashcard_deck'];

    /**
     * Place chaque contenu dans un dossier (null = racine du groupe) avec un rang.
     */
    public function up(): void
    {
        foreach (self::PIVOTS as $pivot) {
            Schema::table($pivot, function (Blueprint $table) use ($pivot) {
                if (! Schema::hasColumn($pivot, 'folder_id')) {
                    $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
                }
                if (! Schema::hasColumn($pivot, 'position')) {
                    $table->integer('position')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::PIVOTS as $pivot) {
            if (Schema::hasColumn($pivot, 'folder_id')) {
                Schema::table($pivot, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('folder_id');
                });
            }
            if (Schema::hasColumn($pivot, 'position')) {
                Schema::table($pivot, function (Blueprint $table) {
                    $table->dropColumn('position');
                });
            }
        }
    }
};
