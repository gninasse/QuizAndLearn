<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Badge;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les badges existants
        Badge::query()->delete();

        Badge::create([
            'code' => 'first_step',
            'name' => 'Premier pas',
            'description' => 'Terminer son premier quiz',
            'icon' => '🚀',
            'condition_type' => 'quiz_completed',
            'condition_value' => ['count' => 1],
        ]);

        Badge::create([
            'code' => 'bookworm',
            'name' => 'Rat de bibliothèque',
            'description' => 'Terminer la lecture de son premier cours ou article',
            'icon' => '📚',
            'condition_type' => 'article_read',
            'condition_value' => ['count' => 1],
        ]);
    }
}
