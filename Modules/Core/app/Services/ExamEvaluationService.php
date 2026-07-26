<?php

namespace Modules\Core\Services;

use Modules\Core\Models\ExamAttempt;

/**
 * Évaluation d'une tentative d'examen : notation par type de question,
 * points négatifs, note sur note_max, XP de réussite et classement.
 *
 * Logique portée depuis LearnerSpaController::evaluateAttempt.
 */
class ExamEvaluationService
{
    public function __construct(
        private readonly GamificationService $gamification,
    ) {}

    /**
     * Évalue, persiste et renvoie le résultat d'une tentative.
     *
     * @return array{
     *     score_brut: float, score_total: int, pourcentage: float,
     *     note_sur_vingt: float, passed: bool, status: string,
     *     rank: int|null, total_participants: int
     * }
     */
    public function evaluate(ExamAttempt $attempt): array
    {
        $exam = $attempt->exam;
        $questions = $exam->questions;
        $userAnswers = $attempt->answers ?? [];

        $scoreBrut = 0;
        $totalPoints = 0;

        foreach ($questions as $q) {
            $totalPoints += $q->points;
            $ans = $userAnswers[$q->id] ?? null;

            if ($this->isCorrect($q->type, $q->options ?? [], $ans)) {
                $scoreBrut += $q->points;
            } else {
                $scoreBrut -= (float) ($q->points_negatifs ?? 0.00);
            }
        }

        $scoreBrut = max(0, $scoreBrut);
        $pourcentage = $totalPoints > 0 ? ($scoreBrut / $totalPoints) * 100 : 0;
        $noteSurVingt = ($pourcentage / 100) * $exam->note_max;
        $passed = $pourcentage >= $exam->passing_score;

        $attempt->update([
            'score_brut' => $scoreBrut,
            'score_total' => $totalPoints,
            'pourcentage' => round($pourcentage, 2),
            'note_sur_vingt' => round($noteSurVingt, 2),
            'answers' => $userAnswers,
        ]);

        if ($passed) {
            $this->gamification->award(
                $attempt->learner,
                GamificationService::XP_EXAM_PASSED,
                updateStreak: true,
            );
        }

        [$rank, $totalParticipants] = $this->rankOf($attempt);

        return [
            'score_brut' => $scoreBrut,
            'score_total' => $totalPoints,
            'pourcentage' => round($pourcentage, 2),
            'note_sur_vingt' => round($noteSurVingt, 2),
            'passed' => $passed,
            'status' => $attempt->status,
            'rank' => $rank,
            'total_participants' => $totalParticipants,
        ];
    }

    /**
     * Classement d'une tentative : note décroissante, durée croissante.
     *
     * @return array{0: int|null, 1: int}
     */
    public function rankOf(ExamAttempt $attempt): array
    {
        $ordered = ExamAttempt::where('exam_id', $attempt->exam_id)
            ->whereIn('status', ['termine', 'completed', 'time_up'])
            ->orderByDesc('note_sur_vingt')
            ->orderBy('duree_reelle')
            ->pluck('learner_id')
            ->toArray();

        $index = array_search($attempt->learner_id, $ordered);

        return [$index !== false ? $index + 1 : null, count($ordered)];
    }

    private function isCorrect(string $type, array $options, mixed $ans): bool
    {
        switch ($type) {
            case 'true_false':
                $expected = $options['correct_answer'] ?? 'true';

                return strval($ans) === strval($expected);

            case 'mcq':
                $choices = $options['choices'] ?? [];
                $correctChoices = collect($choices)
                    ->filter(fn ($c) => $c['is_correct'] ?? false)
                    ->pluck('text')
                    ->toArray();
                $userSel = is_array($ans) ? $ans : [];

                return count($correctChoices) === count($userSel)
                    && collect($correctChoices)->every(fn ($c) => in_array($c, $userSel));

            case 'fill_blank':
                $format = $options['format'] ?? '';
                preg_match_all('/\[\[(.*?)\]\]/', $format, $matches);
                $answersList = $matches[1] ?? [];
                $userVal = is_string($ans) ? trim($ans) : '';

                foreach ($answersList as $optStr) {
                    foreach (explode('|', $optStr) as $o) {
                        if (strtolower(trim($o)) === strtolower($userVal)) {
                            return true;
                        }
                    }
                }

                return false;

            case 'matching':
                $pairs = $options['pairs'] ?? [];
                $userPairs = is_array($ans) ? $ans : [];
                foreach ($pairs as $p) {
                    $uVal = $userPairs[$p['left']] ?? '';
                    if (strtolower(trim($uVal)) !== strtolower(trim($p['right']))) {
                        return false;
                    }
                }

                return true;

            case 'ordering':
                $items = $options['items'] ?? [];
                $userOrder = is_string($ans)
                    ? array_map('trim', explode(',', $ans))
                    : (is_array($ans) ? $ans : []);
                if (count($items) !== count($userOrder)) {
                    return false;
                }
                foreach ($items as $idx => $it) {
                    if (strtolower(trim($it)) !== strtolower(trim($userOrder[$idx] ?? ''))) {
                        return false;
                    }
                }

                return true;

            case 'open_text':
                return true;

            default:
                return false;
        }
    }
}
