<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAttempt;
use Modules\Core\Models\Resource;

/**
 * Sérialisation commune des contenus (articles, quiz, decks) pour le volet
 * apprenant et l'API vitrine. Sans données d'apprenant (null), les champs
 * personnels prennent leur valeur neutre.
 */
class ContentSerializer
{
    /**
     * Réécrit les URLs absolues des médias uploadés (/storage/…) en URLs
     * relatives : le HTML stocké en base contient souvent le domaine du
     * back-office (asset()), injoignable depuis un tunnel ou le mobile.
     * Relatif = valable pour la PWA same-origin ET rebasable par les clients.
     */
    public static function relativizeMediaUrls(?string $html): ?string
    {
        if (! $html) {
            return $html;
        }

        return preg_replace('#https?://[^/"\'\s]+/storage/#i', '/storage/', $html);
    }

    public static function article(Article $article, ?LearnerProgress $progress): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'category' => $article->category,
            'content' => self::relativizeMediaUrls($article->content),
            'estimated_reading_time' => $article->estimated_reading_time,
            'is_favorite' => $progress ? (bool) $progress->is_favorite : false,
            'rating' => $progress ? (int) $progress->rating : 0,
            'status' => $progress ? $progress->status : 'unread',
            'progress_percentage' => $progress ? (int) $progress->progress_percentage : 0,
            'created_at' => $article->created_at->toIso8601String(),
            'updated_at' => $article->updated_at->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, QuizAttempt>|null  $attempts  tentatives de l'apprenant sur ce quiz
     */
    public static function quiz(Quiz $quiz, ?Collection $attempts): array
    {
        $attempts ??= collect();
        $hasCompleted = $attempts->contains('status', 'completed');
        $hasInProgress = $attempts->contains('status', 'in_progress');
        $maxAttemptsReached = $quiz->max_attempts
            && $attempts->where('status', 'completed')->count() >= $quiz->max_attempts;

        $status = 'unread';
        if ($hasInProgress) {
            $status = 'in_progress';
        } elseif ($hasCompleted) {
            $status = 'completed';
        }

        // Le delta doit refléter aussi les modifications de questions.
        $questionsMaxUpdated = $quiz->questions->max('updated_at');
        $effectiveUpdated = max($quiz->updated_at, $questionsMaxUpdated ?? $quiz->updated_at);

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'duration' => $quiz->duration,
            'passing_score' => $quiz->passing_score,
            'max_attempts' => $quiz->max_attempts,
            'shuffle_questions' => (bool) $quiz->shuffle_questions,
            'show_correct_answers' => (bool) $quiz->show_correct_answers,
            'status' => $status,
            'max_attempts_reached' => $maxAttemptsReached,
            'updated_at' => $effectiveUpdated->toIso8601String(),
            'attempts' => $attempts->map(fn (QuizAttempt $att) => [
                'id' => $att->id,
                'attempt_number' => $att->attempt_number,
                'status' => $att->status,
                'score' => $att->score !== null ? (float) $att->score : null,
                'points_earned' => $att->points_earned,
                'points_total' => $att->points_total,
                'passed' => (bool) $att->passed,
                'completed_at' => $att->completed_at?->toIso8601String(),
            ])->values(),
            'questions' => $quiz->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => self::relativizeMediaUrls($q->question_text),
                'type' => $q->type,
                'points' => $q->points,
                'order' => $q->order,
                // (object) : un tableau PHP vide se sérialiserait en []
                // (array JSON) et casserait les clients typés qui
                // attendent un objet.
                'options' => empty($q->options) ? (object) [] : $q->options,
            ])->values(),
        ];
    }

    /**
     * @param  Collection|null  $reviews  révisions de l'apprenant, indexées par flashcard_item_id
     */
    public static function deck(FlashcardDeck $deck, ?Collection $reviews): array
    {
        $cardsMaxUpdated = $deck->cards->max('updated_at');
        $effectiveUpdated = max($deck->updated_at, $cardsMaxUpdated ?? $deck->updated_at);

        return [
            'id' => $deck->id,
            'titre' => $deck->titre,
            'description' => $deck->description,
            'matiere' => $deck->matiere,
            'algorithme' => $deck->algorithme,
            'easiness_default' => (float) $deck->easiness_default,
            'interval_min' => (int) $deck->interval_min,
            'interval_max' => (int) $deck->interval_max,
            'updated_at' => $effectiveUpdated->toIso8601String(),
            'cards' => $deck->cards->map(function ($card) use ($reviews) {
                $review = $reviews?->get($card->id);

                return [
                    'id' => $card->id,
                    'recto' => self::relativizeMediaUrls($card->recto),
                    'verso' => self::relativizeMediaUrls($card->verso),
                    'recto_media' => $card->recto_media,
                    'verso_media' => $card->verso_media,
                    'tags' => $card->tags,
                    'ordre' => $card->ordre,
                    'review' => $review ? [
                        'easiness_factor' => (float) $review->easiness_factor,
                        'interval_days' => $review->interval_days,
                        'repetitions' => $review->repetitions,
                        'last_reviewed' => $review->last_reviewed?->toIso8601String(),
                        'next_review' => $review->next_review?->toIso8601String(),
                        'status' => $review->status,
                    ] : null,
                ];
            })->values(),
        ];
    }

    /**
     * Ressource pour l'apprenant : jamais de chemin de fichier (consultation
     * via URL signée), l'URL n'est exposée que pour un lien externe.
     */
    public static function resource(Resource $resource): array
    {
        return [
            'id' => $resource->id,
            'kind' => $resource->kind,
            'title' => $resource->title,
            'description' => $resource->description,
            'mime' => $resource->mime,
            'size' => $resource->size,
            'allow_download' => (bool) $resource->allow_download,
            'video_ref' => $resource->kind === 'video' ? $resource->video?->ref : null,
            'url' => $resource->kind === 'link' ? $resource->url : null,
            'conversion_status' => $resource->conversion_status,
            'updated_at' => $resource->updated_at->toIso8601String(),
        ];
    }
}
