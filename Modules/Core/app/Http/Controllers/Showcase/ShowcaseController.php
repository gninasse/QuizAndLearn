<?php

namespace Modules\Core\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Services\ContentSerializer;
use Modules\Core\Services\GuestToken;
use Modules\Core\Services\ShowcaseSettings;
use Modules\Core\Services\Terminology;

/**
 * API publique du mode découverte (lecture seule, sans authentification).
 * Ne renvoie que les contenus cochés « vitrine » et actifs, jamais
 * d'examen ni de donnée d'apprenant.
 */
class ShowcaseController extends Controller
{
    public function __construct(
        private readonly ShowcaseSettings $settings,
        private readonly Terminology $terminology,
    ) {}

    public function content(Request $request): JsonResponse
    {
        if (! $this->settings->enabled()) {
            return self::disabledResponse();
        }

        $articles = Article::where('is_showcase', true)
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(fn (Article $article) => ContentSerializer::article($article, null))
            ->values();

        $quizzes = Quiz::where('is_showcase', true)
            ->where('is_active', true)
            ->with('questions')
            ->get()
            ->map(fn (Quiz $quiz) => ContentSerializer::quiz($quiz, null))
            ->values();

        $decks = FlashcardDeck::where('is_showcase', true)
            ->where('active', true)
            ->with(['cards' => fn ($q) => $q->orderBy('ordre')])
            ->get()
            ->map(fn (FlashcardDeck $deck) => ContentSerializer::deck($deck, null))
            ->values();

        $token = GuestToken::issue();

        return response()->json([
            'success' => true,
            'guest_token' => $token,
            'expires_at' => GuestToken::expiresAt($token)->toIso8601String(),
            'articles' => $articles,
            'quizzes' => $quizzes,
            'decks' => $decks,
        ])->cookie(
            GuestToken::COOKIE,
            $token,
            GuestToken::TTL_HOURS * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );
    }

    public function settings(): JsonResponse
    {
        $groups = Group::openForRequests()
            ->orderByRaw('start_date IS NULL, start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'start_date' => $group->start_date?->toDateString(),
                'end_date' => $group->end_date?->toDateString(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            ...$this->settings->publicPayload(),
            'groups' => $groups,
            'terms' => $this->terminology->toArray(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Réponse commune des points d'entrée vitrine quand le mode est désactivé.
     */
    public static function disabledResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Le mode découverte est désactivé.',
        ], 404);
    }
}
