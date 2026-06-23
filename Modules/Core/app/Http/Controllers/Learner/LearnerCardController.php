<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Models\Flashcard;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;

class LearnerCardController extends Controller
{
    /**
     * Espace de révision des flashcards (SM-2).
     */
    public function index(): View
    {
        $user = Auth::user();
        $learner = $user->learner;

        // Auto-peuplement des flashcards si l'apprenant a des quiz assignés
        $groupIds = $learner->groups()->pluck('groups.id');
        $assignedQuizIds = Quiz::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })->pluck('id');

        $assignedQuestionIds = Question::whereIn('quiz_id', $assignedQuizIds)->pluck('id');
        $existingQuestionIds = Flashcard::where('learner_id', $learner->id)->pluck('question_id')->toArray();
        $missingQuestionIds = $assignedQuestionIds->diff($existingQuestionIds);

        foreach ($missingQuestionIds as $qId) {
            Flashcard::create([
                'learner_id' => $learner->id,
                'question_id' => $qId,
                'difficulty_factor' => 2.50,
                'interval_days' => 0,
                'repetitions' => 0,
                'next_review_date' => now()->toDateString(),
                'ease_rating' => 'new',
            ]);
        }

        // Récupérer les cartes dues pour révision aujourd'hui
        $dueCards = Flashcard::where('learner_id', $learner->id)
            ->where('next_review_date', '<=', now()->toDateString())
            ->with('question')
            ->get();

        return view('core::learner.reviser', compact('dueCards'));
    }

    /**
     * Évaluer une carte et calculer son prochain intervalle (algorithme SM-2).
     */
    public function evaluateCard(Request $request): JsonResponse
    {
        $request->validate([
            'flashcard_id' => 'required|exists:flashcards,id',
            'rating' => 'required|integer|min:0|max:5', // 0: à revoir, 3: difficile, 4: moyen, 5: facile
        ]);

        $user = Auth::user();
        $learner = $user->learner;

        $card = Flashcard::where('id', $request->flashcard_id)
            ->where('learner_id', $learner->id)
            ->firstOrFail();

        $q = $request->rating;
        $ef = $card->difficulty_factor ?: 2.5;
        $repetitions = $card->repetitions ?: 0;
        $interval = $card->interval_days ?: 0;

        if ($q < 3) {
            $repetitions = 0;
            $interval = 1;
        } else {
            if ($repetitions == 0) {
                $interval = 1;
            } elseif ($repetitions == 1) {
                $interval = 6;
            } else {
                $interval = (int) round($interval * $ef);
            }
            $repetitions++;
        }

        // EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02))
        $ef = $ef + (0.1 - (5 - $q) * (0.08 + (5 - $q) * 0.02));
        if ($ef < 1.3) {
            $ef = 1.3;
        }

        $card->update([
            'difficulty_factor' => $ef,
            'interval_days' => $interval,
            'repetitions' => $repetitions,
            'next_review_date' => now()->addDays($interval)->toDateString(),
            'ease_rating' => $this->getEaseRatingName($q),
        ]);

        // Créditer de l'XP de révision (ex: 5 XP)
        $xp = $learner->xp ?? $learner->xp()->create([
            'total_xp' => 0,
            'current_level' => 1,
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
        ]);
        $xp->update([
            'total_xp' => $xp->total_xp + 5,
        ]);

        return response()->json([
            'success' => true,
            'next_review_date' => $card->next_review_date,
            'interval_days' => $interval,
        ]);
    }

    protected function getEaseRatingName(int $q): string
    {
        if ($q >= 5) {
            return 'easy';
        }
        if ($q >= 4) {
            return 'good';
        }
        if ($q >= 3) {
            return 'hard';
        }

        return 'again';
    }
}
