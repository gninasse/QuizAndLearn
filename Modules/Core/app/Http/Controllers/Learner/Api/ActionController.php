<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\ErrorReport;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\FlashcardItemReview;
use Modules\Core\Models\FlashcardSession;
use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerActionLog;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\Quiz;
use Modules\Core\Services\GamificationService;
use Modules\Core\Services\QuizScoringService;
use Modules\Core\Services\Sm2Service;
use Throwable;

/**
 * Rejeu des actions hors-ligne (outbox pattern, API v1).
 *
 * Chaque action porte un client_action_id (UUID) : une action déjà appliquée
 * renvoie `duplicate` avec son résultat d'origine au lieu d'être rejouée.
 * Chaque action est traitée dans sa propre transaction : une action rejetée
 * ne bloque pas le reste du lot (contrairement à l'ancien /api/sync).
 */
class ActionController extends Controller
{
    private const SUPPORTED_TYPES = [
        'article_progress',
        'article_favorite',
        'article_rate',
        'article_error_report',
        'quiz_attempt',
        'quiz_error_report',
        'card_review',
        'review_session',
        'preferences_update',
    ];

    public function __construct(
        private readonly GamificationService $gamification,
        private readonly QuizScoringService $quizScoring,
        private readonly Sm2Service $sm2,
    ) {}

    /**
     * POST /api/learner/v1/actions
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'actions' => ['required', 'array'],
            'actions.*.id' => ['required', 'uuid'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.data' => ['nullable', 'array'],
        ]);

        $learner = Auth::user()->learner;
        $results = [];
        $badgesUnlocked = [];

        foreach ($request->input('actions') as $action) {
            $actionId = $action['id'];
            $type = $action['type'];
            $data = $action['data'] ?? [];

            $existing = LearnerActionLog::where('client_action_id', $actionId)->first();
            if ($existing) {
                $results[] = [
                    'id' => $actionId,
                    'status' => 'duplicate',
                    'result' => $existing->result,
                ];

                continue;
            }

            if (! in_array($type, self::SUPPORTED_TYPES)) {
                $results[] = $this->reject($learner, $actionId, $type, "Type d'action inconnu : {$type}");

                continue;
            }

            try {
                $result = DB::transaction(function () use ($learner, $type, $data) {
                    return $this->handle($learner, $type, $data);
                });

                LearnerActionLog::create([
                    'learner_id' => $learner->id,
                    'client_action_id' => $actionId,
                    'type' => $type,
                    'status' => 'applied',
                    'result' => $result,
                ]);

                $results[] = [
                    'id' => $actionId,
                    'status' => 'applied',
                    'result' => $result,
                ];
            } catch (Throwable $e) {
                $results[] = $this->reject($learner, $actionId, $type, $e->getMessage());
            }
        }

        $badgesUnlocked = $this->gamification->checkBadges($learner);

        return response()->json([
            'success' => true,
            'results' => $results,
            'xp' => $this->gamification->snapshot($learner),
            'badges_unlocked' => $badgesUnlocked,
        ]);
    }

    private function reject(Learner $learner, string $actionId, string $type, string $message): array
    {
        LearnerActionLog::create([
            'learner_id' => $learner->id,
            'client_action_id' => $actionId,
            'type' => $type,
            'status' => 'rejected',
            'result' => ['message' => $message],
        ]);

        return [
            'id' => $actionId,
            'status' => 'rejected',
            'message' => $message,
        ];
    }

    private function handle(Learner $learner, string $type, array $data): array
    {
        return match ($type) {
            'article_progress' => $this->articleProgress($learner, $data),
            'article_favorite' => $this->articleFavorite($learner, $data),
            'article_rate' => $this->articleRate($learner, $data),
            'article_error_report' => $this->errorReport($learner, 'article', $data),
            'quiz_attempt' => $this->quizAttempt($learner, $data),
            'quiz_error_report' => $this->errorReport($learner, 'quiz', $data),
            'card_review' => $this->cardReview($learner, $data),
            'review_session' => $this->reviewSession($learner, $data),
            'preferences_update' => $this->preferencesUpdate($learner, $data),
        };
    }

    private function articleProgress(Learner $learner, array $data): array
    {
        $artId = $data['article_id'] ?? throw new InvalidArgumentException('article_id manquant.');
        $percent = (int) ($data['progress_percentage'] ?? 0);
        $status = $data['status'] ?? 'reading';

        $progress = LearnerProgress::where([
            'learner_id' => $learner->id,
            'content_type' => 'article',
            'content_id' => $artId,
        ])->first();

        $completedNow = false;

        if ($progress) {
            $completedNow = ($status === 'completed' && $progress->status !== 'completed');
            $progress->update([
                'progress_percentage' => max($progress->progress_percentage, $percent),
                'status' => $status,
                'time_spent' => $progress->time_spent + 10,
                'completed_at' => $completedNow ? now() : $progress->completed_at,
                'last_accessed_at' => now(),
            ]);
        } else {
            $completedNow = ($status === 'completed');
            LearnerProgress::create([
                'learner_id' => $learner->id,
                'content_type' => 'article',
                'content_id' => $artId,
                'progress_percentage' => $percent,
                'status' => $status,
                'time_spent' => 10,
                'started_at' => now(),
                'completed_at' => $completedNow ? now() : null,
                'last_accessed_at' => now(),
            ]);
        }

        $xpEarned = 0;
        if ($completedNow) {
            $xpEarned = GamificationService::XP_ARTICLE_COMPLETED;
            // La lecture d'articles ne met pas à jour la série.
            $this->gamification->award($learner, $xpEarned, updateStreak: false);
        }

        return ['xp_earned' => $xpEarned, 'status' => $status];
    }

    private function articleFavorite(Learner $learner, array $data): array
    {
        $artId = $data['article_id'] ?? throw new InvalidArgumentException('article_id manquant.');
        $isFav = (bool) ($data['is_favorite'] ?? false);

        LearnerProgress::updateOrCreate(
            ['learner_id' => $learner->id, 'content_type' => 'article', 'content_id' => $artId],
            ['is_favorite' => $isFav],
        );

        return ['is_favorite' => $isFav];
    }

    private function articleRate(Learner $learner, array $data): array
    {
        $artId = $data['article_id'] ?? throw new InvalidArgumentException('article_id manquant.');
        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('La note doit être comprise entre 1 et 5.');
        }

        LearnerProgress::updateOrCreate(
            ['learner_id' => $learner->id, 'content_type' => 'article', 'content_id' => $artId],
            ['rating' => $rating],
        );

        return ['rating' => $rating];
    }

    private function errorReport(Learner $learner, string $contentType, array $data): array
    {
        $contentId = $data['content_id'] ?? $data["{$contentType}_id"] ?? throw new InvalidArgumentException('content_id manquant.');

        ErrorReport::create([
            'learner_id' => $learner->id,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'error_type' => $data['error_type'] ?? 'content',
            'comment' => $data['comment'] ?? '',
            'status' => 'pending',
        ]);

        return ['reported' => true];
    }

    private function quizAttempt(Learner $learner, array $data): array
    {
        $quizId = $data['quiz_id'] ?? throw new InvalidArgumentException('quiz_id manquant.');
        $quiz = Quiz::with('questions')->find($quizId);
        if (! $quiz) {
            throw new InvalidArgumentException('Quiz introuvable.');
        }

        $groupIds = $learner->groups()->current()->pluck('groups.id');
        $isAssigned = $quiz->groups()->whereIn('groups.id', $groupIds)->exists();
        if (! $quiz->is_active || ! $isAssigned) {
            throw new InvalidArgumentException('Quiz non autorisé.');
        }

        $completedCount = $learner->attempts()
            ->where('quiz_id', $quiz->id)
            ->where('status', 'completed')
            ->count();
        if ($quiz->max_attempts && $completedCount >= $quiz->max_attempts) {
            throw new InvalidArgumentException('Nombre maximum de tentatives atteint.');
        }

        ['attempt' => $attempt, 'scoring' => $scoring] = $this->quizScoring->persistAttempt(
            $learner,
            $quiz,
            $data['answers'] ?? [],
            $data['started_at'] ?? null,
            $data['completed_at'] ?? null,
        );

        $xpEarned = $this->gamification->xpForQuizCompletion($scoring['passed'], $scoring['scored_points']);
        $this->gamification->award($learner, $xpEarned, updateStreak: true);

        return [
            'attempt_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'score' => $scoring['score_percent'],
            'points_earned' => $scoring['scored_points'],
            'points_total' => $scoring['total_points'],
            'passed' => $scoring['passed'],
            'xp_earned' => $xpEarned,
        ];
    }

    private function cardReview(Learner $learner, array $data): array
    {
        $cardId = $data['card_id'] ?? throw new InvalidArgumentException('card_id manquant.');
        $quality = (int) ($data['quality'] ?? -1);
        if ($quality < 0 || $quality > 5) {
            throw new InvalidArgumentException('La qualité doit être comprise entre 0 et 5.');
        }

        $card = FlashcardItem::with('deck')->find($cardId);
        if (! $card) {
            throw new InvalidArgumentException('Carte introuvable.');
        }

        $deck = $card->deck;
        $groupIds = $learner->groups()->current()->pluck('groups.id');
        $isAccessible = $deck->active
            && ($deck->is_public || $deck->groups()->whereIn('groups.id', $groupIds)->exists());
        if (! $isAccessible) {
            throw new InvalidArgumentException('Deck non autorisé.');
        }

        $review = FlashcardItemReview::firstOrNew([
            'flashcard_item_id' => $card->id,
            'learner_id' => $learner->id,
        ]);

        $next = $this->sm2->review(
            easiness: $review->exists ? (float) $review->easiness_factor : (float) $deck->easiness_default,
            repetitions: $review->exists ? $review->repetitions : 0,
            intervalDays: $review->exists ? $review->interval_days : 0,
            quality: $quality,
            intervalMin: (int) $deck->interval_min,
            intervalMax: (int) $deck->interval_max,
        );

        $history = $review->review_history ?? [];
        $history[] = ['q' => $quality, 'at' => now()->toIso8601String(), 'interval' => $next['interval_days']];

        $review->fill([
            'easiness_factor' => $next['easiness_factor'],
            'repetitions' => $next['repetitions'],
            'interval_days' => $next['interval_days'],
            'last_reviewed' => now(),
            'next_review' => now()->addDays($next['interval_days']),
            'status' => $this->sm2->statusFor($next['repetitions'], $quality),
            'review_history' => $history,
        ])->save();

        // Statistiques agrégées de la carte (tous apprenants confondus).
        $newTotal = $card->total_revisions + 1;
        $successes = ($card->taux_reussite / 100) * $card->total_revisions + ($quality >= 3 ? 1 : 0);
        $card->update([
            'total_revisions' => $newTotal,
            'taux_reussite' => round(($successes / $newTotal) * 100, 2),
        ]);

        // Réviser maintient la série (activité d'entraînement quotidienne).
        $this->gamification->award($learner, GamificationService::XP_CARD_REVIEW, updateStreak: true);

        return [
            'xp_earned' => GamificationService::XP_CARD_REVIEW,
            'easiness_factor' => $next['easiness_factor'],
            'repetitions' => $next['repetitions'],
            'interval_days' => $next['interval_days'],
            'next_review' => $review->next_review->toIso8601String(),
            'status' => $review->status,
        ];
    }

    private function reviewSession(Learner $learner, array $data): array
    {
        $deckId = $data['deck_id'] ?? throw new InvalidArgumentException('deck_id manquant.');

        $session = FlashcardSession::create([
            'learner_id' => $learner->id,
            'deck_id' => $deckId,
            'date_debut' => $data['date_debut'] ?? now(),
            'date_fin' => $data['date_fin'] ?? now(),
            'duree_seconds' => $data['duree_seconds'] ?? null,
            'cartes_etudiees' => (int) ($data['cartes_etudiees'] ?? 0),
            'cartes_nouvelles' => (int) ($data['cartes_nouvelles'] ?? 0),
            'cartes_revues' => (int) ($data['cartes_revues'] ?? 0),
            'cartes_maitrisees' => (int) ($data['cartes_maitrisees'] ?? 0),
            'grades' => $data['grades'] ?? null,
        ]);

        return ['session_id' => $session->id];
    }

    private function preferencesUpdate(Learner $learner, array $data): array
    {
        $allowed = [
            'theme' => ['light', 'dark'],
            'font_size' => ['small', 'medium', 'large'],
            'locale' => ['fr', 'en'],
        ];

        foreach ($allowed as $key => $values) {
            if (isset($data[$key]) && ! in_array($data[$key], $values)) {
                throw new InvalidArgumentException("Valeur invalide pour {$key}.");
            }
        }

        $preferences = $learner->preferences ?? $learner->preferences()->create([
            'locale' => 'fr',
            'theme' => 'light',
            'font_size' => 'medium',
            'sound_enabled' => true,
            'notifications_enabled' => ['new_quiz' => true, 'new_article' => true],
        ]);

        $preferences->update([
            'theme' => $data['theme'] ?? $preferences->theme,
            'font_size' => $data['font_size'] ?? $preferences->font_size,
            'sound_enabled' => array_key_exists('sound_enabled', $data)
                ? (bool) $data['sound_enabled']
                : $preferences->sound_enabled,
            'locale' => $data['locale'] ?? $preferences->locale,
        ]);

        return ['preferences' => [
            'locale' => $preferences->locale,
            'theme' => $preferences->theme,
            'font_size' => $preferences->font_size,
            'sound_enabled' => (bool) $preferences->sound_enabled,
        ]];
    }
}
