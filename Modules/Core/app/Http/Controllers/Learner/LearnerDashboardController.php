<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Models\Article;
use Modules\Core\Models\Flashcard;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAttempt;

class LearnerDashboardController extends Controller
{
    /**
     * Afficher le tableau de bord de l'apprenant.
     */
    public function index(): View
    {
        $user = Auth::user();
        $learner = $user->learner;

        // Assurer que le profil XP existe
        $xp = $learner->xp ?? $learner->xp()->create([
            'total_xp' => 0,
            'current_level' => 1,
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
        ]);

        // Calculer l'XP pour le prochain niveau (formule simple : niveau * 100)
        $level = $xp->current_level ?: 1;
        $xpForNextLevel = $level * 100;
        $xpProgressPercentage = min(100, max(0, ($xp->total_xp / $xpForNextLevel) * 100));

        // Récupérer les groupes de l'apprenant
        $groupIds = $learner->groups()->pluck('groups.id');

        // Récupérer les quiz assignés actifs
        $assignedQuizzes = Quiz::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->withCount('questions')
            ->get();

        // Récupérer les tentatives pour ces quiz
        $quizAttempts = QuizAttempt::where('learner_id', $learner->id)
            ->whereIn('quiz_id', $assignedQuizzes->pluck('id'))
            ->get()
            ->groupBy('quiz_id');

        // Filtrer les quiz en attente (non commencés ou en cours)
        $pendingQuizzes = $assignedQuizzes->filter(function ($quiz) use ($quizAttempts) {
            $attempts = $quizAttempts->get($quiz->id);
            if (! $attempts) {
                return true; // Jamais tenté
            }

            // S'il existe une tentative en cours ou si on n'a pas encore atteint le max d'essais
            $hasCompleted = $attempts->contains('status', 'completed');
            $hasInProgress = $attempts->contains('status', 'in_progress');
            $maxAttemptsReached = $quiz->max_attempts && $attempts->count() >= $quiz->max_attempts;

            return $hasInProgress || (! $hasCompleted && ! $maxAttemptsReached);
        })->take(5);

        // Récupérer les articles assignés actifs
        $assignedArticles = Article::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->latest()
            ->take(5)
            ->get();

        // Récupérer la progression sur les articles
        $articlesProgress = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->whereIn('content_id', $assignedArticles->pluck('id'))
            ->get()
            ->keyBy('content_id');

        // Compter les flashcards dues aujourd'hui
        $dueCardsCount = Flashcard::where('learner_id', $learner->id)
            ->where('next_review_date', '<=', now()->toDateString())
            ->count();

        return view('core::learner.dashboard', compact(
            'xp',
            'xpForNextLevel',
            'xpProgressPercentage',
            'pendingQuizzes',
            'quizAttempts',
            'assignedArticles',
            'articlesProgress',
            'dueCardsCount'
        ));
    }
}
