<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Badge;
use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\LearnerXp;
use Modules\Core\Models\QuizAttempt;

/**
 * Source unique des règles de gamification (XP, niveaux, séries, badges).
 *
 * Remplace la logique auparavant dupliquée entre LearnerQuizController,
 * LearnerSpaController::sync, LearnerArticleController et LearnerCardController.
 *
 * Formule de niveau unifiée : floor(total_xp / 100) + 1 — la même que celle
 * exposée par l'API (le tableau de bord legacy utilisait un seuil divergent).
 */
class GamificationService
{
    public const XP_QUIZ_BASE = 20;

    public const XP_QUIZ_PASS_BONUS = 30;

    public const XP_PER_QUIZ_POINT = 5;

    public const XP_ARTICLE_COMPLETED = 15;

    public const XP_CARD_REVIEW = 5;

    public const XP_EXAM_PASSED = 50;

    public const XP_PER_LEVEL = 100;

    public function ensureXp(Learner $learner): LearnerXp
    {
        $xp = $learner->xp()->firstOrCreate([], [
            'total_xp' => 0,
            'current_level' => 1,
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
        ]);

        // Rafraîchit la relation en cache pour les appels suivants du même cycle.
        $learner->setRelation('xp', $xp);

        return $xp;
    }

    public function xpForQuizCompletion(bool $passed, int $scoredPoints): int
    {
        return self::XP_QUIZ_BASE
            + ($passed ? self::XP_QUIZ_PASS_BONUS : 0)
            + ($scoredPoints * self::XP_PER_QUIZ_POINT);
    }

    public static function levelFor(int $totalXp): int
    {
        return (int) floor($totalXp / self::XP_PER_LEVEL) + 1;
    }

    /**
     * Attribue de l'XP, recalcule le niveau et (optionnellement) la série.
     *
     * La série n'est mise à jour que par les activités « d'entraînement »
     * (quiz, examen, révision de cartes) — pas par la lecture d'articles.
     *
     * @return array{total_xp: int, current_level: int, current_streak: int, longest_streak: int, level_up: bool}
     */
    public function award(Learner $learner, int $xpGained, bool $updateStreak = true): array
    {
        $xp = $this->ensureXp($learner);

        $newTotal = $xp->total_xp + $xpGained;
        $newLevel = self::levelFor($newTotal);
        $levelUp = $newLevel > $xp->current_level;

        $attributes = [
            'total_xp' => $newTotal,
            'current_level' => $newLevel,
        ];

        if ($updateStreak) {
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();
            $lastActivity = $xp->last_activity_date?->toDateString();

            $streak = $xp->current_streak;
            if ($lastActivity === $yesterday) {
                $streak++;
            } elseif ($lastActivity !== $today) {
                $streak = 1;
            }

            $attributes['current_streak'] = $streak;
            $attributes['longest_streak'] = max($xp->longest_streak, $streak);
            $attributes['last_activity_date'] = $today;
        }

        $xp->update($attributes);

        return [
            'total_xp' => $xp->total_xp,
            'current_level' => $xp->current_level,
            'current_streak' => $xp->current_streak,
            'longest_streak' => $xp->longest_streak,
            'level_up' => $levelUp,
        ];
    }

    /**
     * @return array{total_xp: int, current_level: int, current_streak: int, longest_streak: int, last_activity_date: string|null}
     */
    public function snapshot(Learner $learner): array
    {
        $xp = $this->ensureXp($learner);

        return [
            'total_xp' => $xp->total_xp,
            'current_level' => $xp->current_level,
            'current_streak' => $xp->current_streak,
            'longest_streak' => $xp->longest_streak,
            'last_activity_date' => $xp->last_activity_date?->toDateString(),
        ];
    }

    /**
     * Débloque les badges dont la condition est atteinte.
     *
     * @return string[] noms des badges nouvellement débloqués
     */
    public function checkBadges(Learner $learner): array
    {
        $unlocked = [];
        $existingBadgeIds = $learner->badges()->pluck('badges.id')->toArray();

        $completedQuizCount = QuizAttempt::where('learner_id', $learner->id)
            ->where('status', 'completed')
            ->count();

        $completedArticlesCount = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->where('status', 'completed')
            ->count();

        $perfectQuizCount = QuizAttempt::where('learner_id', $learner->id)
            ->where('status', 'completed')
            ->where('score', '>=', 100)
            ->count();

        $currentStreak = $learner->xp?->current_streak ?? 0;

        foreach (Badge::all() as $badge) {
            if (in_array($badge->id, $existingBadgeIds)) {
                continue;
            }

            $val = $badge->condition_value;
            if (is_string($val)) {
                $val = json_decode($val, true);
            }
            $requiredCount = $val['count'] ?? 1;

            $unlock = match ($badge->condition_type) {
                'quiz_completed' => $completedQuizCount >= $requiredCount,
                'article_read' => $completedArticlesCount >= $requiredCount,
                'quiz_perfect' => $perfectQuizCount >= $requiredCount,
                'streak' => $currentStreak >= $requiredCount,
                default => false,
            };

            if ($unlock) {
                $learner->badges()->attach($badge->id, ['earned_at' => now()]);
                $unlocked[] = $badge->name;
            }
        }

        return $unlocked;
    }
}
