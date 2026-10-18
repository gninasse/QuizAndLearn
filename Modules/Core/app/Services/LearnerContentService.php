<?php

namespace Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Models\Article;
use Modules\Core\Models\Badge;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamAttempt;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAttempt;

/**
 * Contenu pédagogique du volet apprenant : payload bootstrap complet
 * et deltas incrémentaux pour la synchronisation hors-ligne.
 */
class LearnerContentService
{
    public function __construct(
        private readonly GamificationService $gamification,
        private readonly Terminology $terminology,
    ) {}

    /**
     * Payload complet pour l'amorçage hors-ligne, avec cursor de delta.
     */
    public function bootstrap(Learner $learner): array
    {
        $cursor = now();
        $groupIds = $learner->groups()->current()->pluck('groups.id');

        return [
            'cursor' => $cursor->toIso8601String(),
            'user' => $this->serializeUser($learner),
            'groups' => $this->groupsFor($learner),
            'articles' => $this->articlesFor($learner, $groupIds)->values(),
            'quizzes' => $this->quizzesFor($learner, $groupIds)->values(),
            'decks' => $this->decksFor($learner, $groupIds)->values(),
            'exams' => $this->examsFor($learner, $groupIds)->values(),
            'badges' => $this->serializeBadges($learner),
            'preferences' => $this->preferencesFor($learner),
            'terms' => $this->terminology->toArray(),
        ];
    }

    /**
     * Delta depuis un cursor : par collection, les éléments modifiés + la
     * liste compacte des IDs encore autorisés (le client supprime le reste).
     */
    public function changes(Learner $learner, CarbonInterface $since): array
    {
        $cursor = now();
        $groupIds = $learner->groups()->current()->pluck('groups.id');

        $articles = $this->articlesFor($learner, $groupIds);
        $quizzes = $this->quizzesFor($learner, $groupIds);
        $decks = $this->decksFor($learner, $groupIds);
        $exams = $this->examsFor($learner, $groupIds);

        return [
            'cursor' => $cursor->toIso8601String(),
            'articles' => [
                'updated' => $articles->filter(fn ($a) => $a['updated_at'] > $since->toIso8601String())->values(),
                'authorized_ids' => $articles->pluck('id')->values(),
            ],
            'quizzes' => [
                'updated' => $quizzes->filter(fn ($q) => $q['updated_at'] > $since->toIso8601String())->values(),
                'authorized_ids' => $quizzes->pluck('id')->values(),
            ],
            'decks' => [
                'updated' => $decks->filter(fn ($d) => $d['updated_at'] > $since->toIso8601String())->values(),
                'authorized_ids' => $decks->pluck('id')->values(),
            ],
            'exams' => [
                'updated' => $exams->filter(fn ($e) => $e['updated_at'] > $since->toIso8601String())->values(),
                'authorized_ids' => $exams->pluck('id')->values(),
            ],
            'groups' => $this->groupsFor($learner),
            'badges' => $this->serializeBadges($learner),
            'xp' => $this->gamification->snapshot($learner),
            'terms' => $this->terminology->toArray(),
        ];
    }

    /**
     * Groupes de l'apprenant avec leur statut — permet au client d'expliquer
     * pourquoi un contenu a disparu (groupe suspendu/fermé) au lieu de le
     * laisser s'évaporer silencieusement.
     */
    private function groupsFor(Learner $learner): array
    {
        return $learner->groups()->get()->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
            'status' => $group->learnerStatus(),
            'start_date' => $group->start_date?->toDateString(),
            'end_date' => $group->end_date?->toDateString(),
        ])->values()->toArray();
    }

    private function serializeUser(Learner $learner): array
    {
        $user = $learner->user;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'matricule' => $learner->matricule,
            'xp' => $this->gamification->snapshot($learner),
        ];
    }

    private function articlesFor(Learner $learner, Collection $groupIds): Collection
    {
        $progress = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->get()
            ->keyBy('content_id');

        return Article::where('is_active', true)
            ->whereHas('groups', fn ($q) => $q->whereIn('group_id', $groupIds))
            ->latest()
            ->get()
            ->map(fn (Article $art) => ContentSerializer::article($art, $progress->get($art->id)));
    }

    private function quizzesFor(Learner $learner, Collection $groupIds): Collection
    {
        $attempts = QuizAttempt::where('learner_id', $learner->id)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('quiz_id');

        return Quiz::where('is_active', true)
            ->whereHas('groups', fn ($q) => $q->whereIn('group_id', $groupIds))
            ->with('questions')
            ->get()
            ->map(fn (Quiz $qz) => ContentSerializer::quiz($qz, $attempts->get($qz->id) ?? collect()));
    }

    private function decksFor(Learner $learner, Collection $groupIds): Collection
    {
        $reviews = $learner->cardReviews()->get()->keyBy('flashcard_item_id');

        return FlashcardDeck::where('active', true)
            ->where(function ($query) use ($groupIds) {
                $query->whereHas('groups', fn ($q) => $q->whereIn('group_id', $groupIds))
                    ->orWhere('is_public', true);
            })
            ->with(['cards' => fn ($q) => $q->orderBy('ordre')])
            ->get()
            ->map(fn (FlashcardDeck $deck) => ContentSerializer::deck($deck, $reviews));
    }

    private function examsFor(Learner $learner, Collection $groupIds): Collection
    {
        $examAttempts = ExamAttempt::where('learner_id', $learner->id)
            ->orderBy('date_debut')
            ->get()
            ->groupBy('exam_id');

        return Exam::where('is_active', true)
            ->whereHas('groups', fn ($q) => $q->whereIn('group_id', $groupIds))
            ->get()
            ->map(function (Exam $ex) use ($examAttempts) {
                $exAttempts = $examAttempts->get($ex->id) ?? collect();
                $hasInProgress = $exAttempts->contains('status', 'en_cours');
                $maxAttemptsReached = $ex->max_attempts
                    && $exAttempts->whereIn('status', ['termine', 'completed', 'time_up'])->count() >= $ex->max_attempts;

                $status = 'available';
                $now = now();
                if ($ex->available_from && $now->lt($ex->available_from)) {
                    $status = 'locked';
                } elseif ($ex->available_until && $now->gt($ex->available_until)) {
                    $status = 'expired';
                } elseif ($hasInProgress) {
                    $status = 'in_progress';
                } elseif ($maxAttemptsReached) {
                    $status = 'completed';
                }

                return [
                    'id' => $ex->id,
                    'title' => $ex->title,
                    'description' => $ex->description,
                    'duration' => $ex->duration,
                    'passing_score' => $ex->passing_score,
                    'note_max' => $ex->note_max,
                    'max_attempts' => $ex->max_attempts,
                    'available_from' => $ex->available_from?->toIso8601String(),
                    'available_until' => $ex->available_until?->toIso8601String(),
                    'plein_ecran_force' => (bool) $ex->plein_ecran_force,
                    'anti_capture_strict' => (bool) $ex->anti_capture_strict,
                    'navigation_interdite' => (bool) $ex->navigation_interdite,
                    'publication_resultats' => $ex->publication_resultats,
                    'classement_visible' => (bool) $ex->classement_visible,
                    'classement_anonyme' => (bool) $ex->classement_anonyme,
                    'status' => $status,
                    'max_attempts_reached' => $maxAttemptsReached,
                    'updated_at' => $ex->updated_at->toIso8601String(),
                    'attempts' => $exAttempts->map(fn (ExamAttempt $att) => [
                        'id' => $att->id,
                        'date_debut' => $att->date_debut?->toIso8601String(),
                        'date_fin' => $att->date_fin?->toIso8601String(),
                        'score_brut' => $att->score_brut !== null ? (float) $att->score_brut : null,
                        'score_total' => $att->score_total !== null ? (float) $att->score_total : null,
                        'note_sur_vingt' => $att->note_sur_vingt !== null ? (float) $att->note_sur_vingt : null,
                        'pourcentage' => $att->pourcentage !== null ? (float) $att->pourcentage : null,
                        'status' => $att->status,
                        'capture_attempts' => $att->capture_attempts,
                        'navigation_violations' => $att->navigation_violations,
                    ])->values(),
                ];
            });
    }

    private function serializeBadges(Learner $learner): array
    {
        $unlockedBadgeIds = $learner->badges()->pluck('badges.id')->toArray();

        return Badge::all()->map(fn (Badge $bdg) => [
            'id' => $bdg->id,
            'name' => $bdg->name,
            'description' => $bdg->description,
            'icon' => $bdg->icon,
            'unlocked' => in_array($bdg->id, $unlockedBadgeIds),
        ])->values()->toArray();
    }

    private function preferencesFor(Learner $learner): array
    {
        // firstOrCreate + setRelation : la relation en cache peut être périmée
        // (null) si le même modèle sert plusieurs requêtes dans un cycle.
        $preferences = $learner->preferences()->firstOrCreate([], [
            'locale' => 'fr',
            'theme' => 'light',
            'font_size' => 'medium',
            'sound_enabled' => true,
            'notifications_enabled' => ['new_quiz' => true, 'new_article' => true],
        ]);
        $learner->setRelation('preferences', $preferences);

        return [
            'locale' => $preferences->locale,
            'theme' => $preferences->theme,
            'font_size' => $preferences->font_size,
            'sound_enabled' => (bool) $preferences->sound_enabled,
            'notifications_enabled' => $preferences->notifications_enabled,
        ];
    }
}
