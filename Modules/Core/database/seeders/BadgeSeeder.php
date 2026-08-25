<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Badge;

/**
 * Badges de gamification — idempotent (updateOrCreate par code),
 * ne supprime jamais les badges déjà gagnés par les apprenants.
 */
class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Quiz complétés
            ['code' => 'first_step', 'name' => 'Premier pas', 'icon' => '🚀', 'condition_type' => 'quiz_completed', 'count' => 1, 'description' => 'Terminer son premier quiz.'],
            ['code' => 'quiz_master', 'name' => 'Maître des quiz', 'icon' => '🧠', 'condition_type' => 'quiz_completed', 'count' => 5, 'description' => 'Terminer 5 quiz.'],
            ['code' => 'quiz_legend', 'name' => 'Légende des quiz', 'icon' => '🏆', 'condition_type' => 'quiz_completed', 'count' => 20, 'description' => 'Terminer 20 quiz.'],

            // Lecture
            ['code' => 'bookworm', 'name' => 'Rat de bibliothèque', 'icon' => '📚', 'condition_type' => 'article_read', 'count' => 1, 'description' => 'Lire son premier article en entier.'],
            ['code' => 'great_reader', 'name' => 'Grand lecteur', 'icon' => '🎓', 'condition_type' => 'article_read', 'count' => 5, 'description' => 'Lire 5 articles en entier.'],

            // Sans-faute
            ['code' => 'perfectionist', 'name' => 'Perfectionniste', 'icon' => '💯', 'condition_type' => 'quiz_perfect', 'count' => 1, 'description' => 'Réussir un quiz à 100 %.'],
            ['code' => 'flawless_five', 'name' => 'Sans faute ×5', 'icon' => '💎', 'condition_type' => 'quiz_perfect', 'count' => 5, 'description' => 'Réussir 5 quiz à 100 %.'],

            // Régularité (séries)
            ['code' => 'streak_3', 'name' => 'Assidu', 'icon' => '🔥', 'condition_type' => 'streak', 'count' => 3, 'description' => '3 jours d\'activité consécutifs.'],
            ['code' => 'streak_7', 'name' => 'Inarrêtable', 'icon' => '⚡', 'condition_type' => 'streak', 'count' => 7, 'description' => '7 jours d\'activité consécutifs.'],
            ['code' => 'streak_30', 'name' => 'Marathonien', 'icon' => '🏅', 'condition_type' => 'streak', 'count' => 30, 'description' => '30 jours d\'activité consécutifs.'],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(
                ['code' => $badge['code']],
                [
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'icon' => $badge['icon'],
                    'condition_type' => $badge['condition_type'],
                    'condition_value' => ['count' => $badge['count']],
                ]
            );
        }

        $this->command?->info('Badges synchronisés : '.count($badges));
    }
}
