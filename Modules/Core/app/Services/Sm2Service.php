<?php

namespace Modules\Core\Services;

/**
 * Algorithme SuperMemo-2 — source unique côté serveur.
 *
 * La même formule (mêmes constantes) est répliquée côté client dans
 * resources/js/learner/domain/sm2.ts ; les deux implémentations partagent
 * les vecteurs de test (tests/Unit/Sm2ServiceTest.php <-> sm2.test.ts).
 */
class Sm2Service
{
    public const MIN_EASINESS = 1.3;

    public const DEFAULT_EASINESS = 2.5;

    /**
     * Calcule le prochain état SM-2 d'une carte après une évaluation.
     *
     * @param  float  $easiness  facteur de facilité courant (EF)
     * @param  int  $repetitions  répétitions réussies consécutives
     * @param  int  $intervalDays  intervalle courant en jours
     * @param  int  $quality  note qualité q ∈ [0, 5]
     * @param  int  $intervalMin  borne basse de l'intervalle (config deck)
     * @param  int|null  $intervalMax  borne haute de l'intervalle (config deck)
     * @return array{easiness_factor: float, repetitions: int, interval_days: int}
     */
    public function review(
        float $easiness,
        int $repetitions,
        int $intervalDays,
        int $quality,
        int $intervalMin = 1,
        ?int $intervalMax = null,
    ): array {
        $quality = max(0, min(5, $quality));

        if ($quality < 3) {
            $repetitions = 0;
            $interval = 1;
        } else {
            if ($repetitions === 0) {
                $interval = 1;
            } elseif ($repetitions === 1) {
                $interval = 6;
            } else {
                $interval = (int) round($intervalDays * $easiness);
            }
            $repetitions++;
        }

        // EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02)), plancher 1.3
        $easiness = $easiness + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        if ($easiness < self::MIN_EASINESS) {
            $easiness = self::MIN_EASINESS;
        }

        $interval = max($intervalMin, $interval);
        if ($intervalMax !== null) {
            $interval = min($intervalMax, $interval);
        }

        return [
            'easiness_factor' => round($easiness, 2),
            'repetitions' => $repetitions,
            'interval_days' => $interval,
        ];
    }

    /**
     * Statut d'apprentissage dérivé de l'état SM-2 (colonne status de
     * flashcard_item_reviews : new/learning/review/relearning/mastered).
     */
    public function statusFor(int $repetitions, int $quality): string
    {
        if ($quality < 3) {
            return 'relearning';
        }
        if ($repetitions >= 5) {
            return 'mastered';
        }
        if ($repetitions >= 3) {
            return 'review';
        }

        return 'learning';
    }
}
