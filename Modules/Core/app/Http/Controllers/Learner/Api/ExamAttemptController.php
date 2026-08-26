<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamAttempt;
use Modules\Core\Services\ExamEvaluationService;

/**
 * Cycle de passage d'examen sécurisé (API v1) — online-only par conception
 * (intégrité anti-fraude). Logique portée depuis LearnerSpaController.
 */
class ExamAttemptController extends Controller
{
    public function __construct(
        private readonly ExamEvaluationService $evaluation,
    ) {}

    /**
     * POST /api/learner/v1/exams/{exam}/attempts — démarre ou reprend une tentative.
     */
    public function store(Request $request, int $examId): JsonResponse
    {
        $learner = Auth::user()->learner;
        $groupIds = $learner->groups()->pluck('groups.id');

        $exam = Exam::where('is_active', true)
            ->whereHas('groups', fn ($q) => $q->whereIn('group_id', $groupIds))
            ->findOrFail($examId);

        $now = now();
        if ($exam->available_from && $now->lt($exam->available_from)) {
            return response()->json(['success' => false, 'message' => 'Cet examen n\'est pas encore ouvert.'], 403);
        }
        if ($exam->available_until && $now->gt($exam->available_until)) {
            return response()->json(['success' => false, 'message' => 'Cet examen est déjà fermé.'], 403);
        }

        $attemptsCount = ExamAttempt::where('learner_id', $learner->id)
            ->where('exam_id', $exam->id)
            ->whereIn('status', ['termine', 'completed', 'time_up'])
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            return response()->json(['success' => false, 'message' => 'Nombre maximum de tentatives atteint.'], 403);
        }

        $attempt = ExamAttempt::firstOrCreate(
            [
                'learner_id' => $learner->id,
                'exam_id' => $exam->id,
                'status' => 'en_cours',
            ],
            [
                'date_debut' => now(),
                'answers' => [],
            ]
        );

        // Les bonnes réponses sont retirées du payload ; matching/ordering mélangés.
        $questions = $exam->questions()->get()->map(function ($q) {
            $options = $q->options ?? [];

            if ($q->type === 'mcq' && isset($options['choices'])) {
                $options['choices'] = collect($options['choices'])->map(function ($c) {
                    unset($c['is_correct']);

                    return $c;
                })->toArray();
            } elseif ($q->type === 'true_false') {
                unset($options['correct_answer']);
            } elseif ($q->type === 'matching' && isset($options['pairs'])) {
                $options = [
                    'lefts' => collect($options['pairs'])->pluck('left')->toArray(),
                    'rights' => collect($options['pairs'])->pluck('right')->shuffle()->toArray(),
                ];
            } elseif ($q->type === 'ordering' && isset($options['items'])) {
                $options['items'] = collect($options['items'])->shuffle()->toArray();
            }

            return [
                'id' => $q->id,
                'question_text' => preg_replace('#https?://[^/"\'\s]+/storage/#i', '/storage/', $q->question_text),
                'type' => $q->type,
                'points' => $q->points,
                'order' => $q->order,
                'options' => $options,
            ];
        });

        $elapsed = now()->getTimestamp() - $attempt->date_debut->getTimestamp();
        $remaining = max(0, ($exam->duration * 60) - $elapsed);

        return response()->json([
            'success' => true,
            'attempt_id' => $attempt->id,
            'questions' => $questions,
            'elapsed_seconds' => $elapsed,
            'remaining_seconds' => $remaining,
            'plein_ecran_force' => (bool) $exam->plein_ecran_force,
            'anti_capture_strict' => (bool) $exam->anti_capture_strict,
            'navigation_interdite' => (bool) $exam->navigation_interdite,
        ]);
    }

    /**
     * PATCH /api/learner/v1/exams/{exam}/attempts/{attempt} — autosave des réponses.
     */
    public function update(Request $request, int $examId, int $attemptId): JsonResponse
    {
        $request->validate(['answers' => 'required|array']);

        $attempt = $this->findActiveAttempt($examId, $attemptId);
        $exam = $attempt->exam;

        $elapsed = now()->getTimestamp() - $attempt->date_debut->getTimestamp();
        if ($elapsed > ($exam->duration * 60) + 60) {
            return $this->autoCompleteTimeUp($attempt);
        }

        $attempt->update(['answers' => $request->answers]);

        return response()->json(['success' => true, 'message' => 'Réponse sauvegardée.']);
    }

    /**
     * POST /api/learner/v1/exams/{exam}/attempts/{attempt}/complete — soumission finale.
     */
    public function complete(Request $request, int $examId, int $attemptId): JsonResponse
    {
        $attempt = $this->findActiveAttempt($examId, $attemptId);

        if ($request->has('answers')) {
            $attempt->update(['answers' => $request->answers]);
        }

        $attempt->update([
            'status' => 'termine',
            'date_fin' => now(),
            'duree_reelle' => now()->getTimestamp() - $attempt->date_debut->getTimestamp(),
        ]);

        return response()->json([
            'success' => true,
            ...$this->evaluation->evaluate($attempt),
        ]);
    }

    /**
     * POST /api/learner/v1/exams/{exam}/attempts/{attempt}/violations — anti-fraude.
     *
     * screenshot : annulation immédiate si anti_capture_strict.
     * navigation : annulation à la 3e violation si navigation_interdite.
     */
    public function violations(Request $request, int $examId, int $attemptId): JsonResponse
    {
        $request->validate(['type' => 'required|string|in:screenshot,navigation']);

        $attempt = $this->findActiveAttempt($examId, $attemptId);
        $exam = $attempt->exam;

        if ($request->type === 'screenshot') {
            $attempt->increment('capture_attempts');

            if ($exam->anti_capture_strict) {
                $attempt->update(['status' => 'annule', 'date_fin' => now()]);

                return response()->json([
                    'success' => true,
                    'cancelled' => true,
                    'message' => 'Examen annulé immédiatement pour cause de capture d\'écran.',
                ]);
            }
        } else {
            $attempt->increment('navigation_violations');

            if ($exam->navigation_interdite && $attempt->navigation_violations >= 3) {
                $attempt->update(['status' => 'annule', 'date_fin' => now()]);

                return response()->json([
                    'success' => true,
                    'cancelled' => true,
                    'message' => 'Examen annulé suite à des sorties de plein écran répétées.',
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'cancelled' => false,
            'violations_count' => $request->type === 'screenshot'
                ? $attempt->capture_attempts
                : $attempt->navigation_violations,
        ]);
    }

    private function findActiveAttempt(int $examId, int $attemptId): ExamAttempt
    {
        return ExamAttempt::where('id', $attemptId)
            ->where('learner_id', Auth::user()->learner->id)
            ->where('exam_id', $examId)
            ->where('status', 'en_cours')
            ->firstOrFail();
    }

    private function autoCompleteTimeUp(ExamAttempt $attempt): JsonResponse
    {
        $attempt->update([
            'status' => 'time_up',
            'date_fin' => now(),
            'duree_reelle' => now()->getTimestamp() - $attempt->date_debut->getTimestamp(),
        ]);

        return response()->json([
            'success' => true,
            'time_up' => true,
            ...$this->evaluation->evaluate($attempt),
        ]);
    }
}
