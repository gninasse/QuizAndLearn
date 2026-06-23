<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Models\Article;
use Modules\Core\Models\ErrorReport;
use Modules\Core\Models\LearnerProgress;

class LearnerArticleController extends Controller
{
    /**
     * Liste des articles disponibles pour l'apprenant.
     */
    public function index(): View
    {
        $user = Auth::user();
        $learner = $user->learner;
        $groupIds = $learner->groups()->pluck('groups.id');

        $articles = Article::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->latest()
            ->get();

        $articlesProgress = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->get()
            ->keyBy('content_id');

        return view('core::learner.articles.index', compact('articles', 'articlesProgress'));
    }

    /**
     * Lire un article spécifique.
     */
    public function show(int $id): View
    {
        $user = Auth::user();
        $learner = $user->learner;
        $groupIds = $learner->groups()->pluck('groups.id');

        $article = Article::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->findOrFail($id);

        $progress = LearnerProgress::firstOrCreate(
            [
                'learner_id' => $learner->id,
                'content_type' => 'article',
                'content_id' => $article->id,
            ],
            [
                'status' => 'in_progress',
                'progress_percentage' => 0,
                'started_at' => now(),
            ]
        );

        return view('core::learner.articles.show', compact('article', 'progress'));
    }

    /**
     * Mettre à jour la progression de lecture (asynchrone).
     */
    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'progress_percentage' => 'required|integer|min:0|max:100',
        ]);

        $user = Auth::user();
        $learner = $user->learner;

        $progress = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->where('content_id', $id)
            ->firstOrFail();

        $completedAt = $progress->completed_at;
        $status = $progress->status;

        // Si le progrès atteint 80% ou plus et n'était pas marqué terminé
        if ($request->progress_percentage >= 80 && $progress->status !== 'completed') {
            $status = 'completed';
            $completedAt = now();

            // Créditer de l'XP de lecture (ex: 15 XP)
            $xp = $learner->xp ?? $learner->xp()->create([
                'total_xp' => 0,
                'current_level' => 1,
                'current_streak' => 0,
                'longest_streak' => 0,
                'last_activity_date' => null,
            ]);

            $xp->update([
                'total_xp' => $xp->total_xp + 15,
            ]);
        }

        $progress->update([
            'progress_percentage' => max($progress->progress_percentage, $request->progress_percentage),
            'status' => $status,
            'completed_at' => $completedAt,
            'last_accessed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => $status,
        ]);
    }

    /**
     * Noter un article (1-5 étoiles).
     */
    public function rateArticle(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $user = Auth::user();
        $learner = $user->learner;

        $progress = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->where('content_id', $id)
            ->firstOrFail();

        $progress->update([
            'rating' => $request->rating,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Évaluation enregistrée.',
        ]);
    }

    /**
     * Mettre en favori / retirer des favoris.
     */
    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $learner = $user->learner;

        $progress = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->where('content_id', $id)
            ->firstOrFail();

        $newFav = ! $progress->is_favorite;
        $progress->update([
            'is_favorite' => $newFav,
        ]);

        return response()->json([
            'success' => true,
            'is_favorite' => $newFav,
        ]);
    }

    /**
     * Signaler une erreur sur un article.
     */
    public function reportError(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'error_type' => 'required|string',
            'comment' => 'required|string',
        ]);

        ErrorReport::create([
            'learner_id' => Auth::user()->learner->id,
            'content_type' => 'article',
            'content_id' => $id,
            'error_type' => $request->error_type,
            'comment' => $request->comment,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signalement envoyé avec succès.',
        ]);
    }
}
