<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Models\Badge;
use Modules\Core\Models\ErrorReport;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAnswer;
use Modules\Core\Models\QuizAttempt;
use Modules\Core\Models\ScreenshotAttempt;

class LearnerQuizController extends Controller
{
    /**
     * Liste des quiz disponibles pour l'apprenant.
     */
    public function index(): View
    {
        $user = Auth::user();
        $learner = $user->learner;
        $groupIds = $learner->groups()->pluck('groups.id');

        $quizzes = Quiz::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->withCount('questions')
            ->get();

        $quizAttempts = QuizAttempt::where('learner_id', $learner->id)
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->get()
            ->groupBy('quiz_id');

        return view('core::learner.quizzes.index', compact('quizzes', 'quizAttempts'));
    }

    /**
     * Afficher l'écran d'introduction ou le passage d'un quiz.
     */
    public function show(int $id): View
    {
        $user = Auth::user();
        $learner = $user->learner;
        $groupIds = $learner->groups()->pluck('groups.id');

        $quiz = Quiz::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->with('questions')
            ->withCount('questions')
            ->findOrFail($id);

        $attempts = QuizAttempt::where('learner_id', $learner->id)
            ->where('quiz_id', $quiz->id)
            ->orderBy('attempt_number', 'desc')
            ->get();

        $activeAttempt = $attempts->where('status', 'in_progress')->first();
        $maxAttemptsReached = $quiz->max_attempts && $attempts->where('status', 'completed')->count() >= $quiz->max_attempts;

        // Si une tentative est en cours, on charge directement le passage du quiz
        if ($activeAttempt) {
            // Charger les questions et réponses déjà fournies
            $answers = QuizAnswer::where('attempt_id', $activeAttempt->id)->get()->keyBy('question_id');

            return view('core::learner.quizzes.play', compact('quiz', 'activeAttempt', 'answers'));
        }

        return view('core::learner.quizzes.show', compact('quiz', 'attempts', 'maxAttemptsReached'));
    }

    /**
     * Démarrer une nouvelle tentative de quiz.
     */
    public function startAttempt(int $id): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $learner = $user->learner;
        $groupIds = $learner->groups()->pluck('groups.id');

        $quiz = Quiz::where('is_active', true)
            ->whereHas('groups', function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            })
            ->findOrFail($id);

        $attempts = QuizAttempt::where('learner_id', $learner->id)
            ->where('quiz_id', $quiz->id)
            ->get();

        $activeAttempt = $attempts->where('status', 'in_progress')->first();
        if ($activeAttempt) {
            return redirect()->route('learner.quizzes.show', $quiz->id);
        }

        $completedAttemptsCount = $attempts->where('status', 'completed')->count();
        if ($quiz->max_attempts && $completedAttemptsCount >= $quiz->max_attempts) {
            return back()->withErrors(['error' => 'Nombre maximal de tentatives atteint.']);
        }

        $attempt = QuizAttempt::create([
            'learner_id' => $learner->id,
            'quiz_id' => $quiz->id,
            'started_at' => now(),
            'attempt_number' => $attempts->count() + 1,
            'status' => 'in_progress',
        ]);

        return redirect()->route('learner.quizzes.show', $quiz->id);
    }

    /**
     * Enregistrer une réponse en cours de tentative (asynchrone).
     */
    public function submitAnswer(Request $request, int $quizId, int $attemptId): JsonResponse
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_given' => 'required',
        ]);

        $user = Auth::user();
        $learner = $user->learner;

        $attempt = QuizAttempt::where('id', $attemptId)
            ->where('learner_id', $learner->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $question = Question::where('id', $request->question_id)
            ->where('quiz_id', $quizId)
            ->firstOrFail();

        // Enregistrer la réponse
        $answer = QuizAnswer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
            [
                'answer_given' => is_array($request->answer_given) ? json_encode($request->answer_given) : $request->answer_given,
                'answered_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Réponse enregistrée.',
        ]);
    }

    /**
     * Finaliser et évaluer la tentative de quiz.
     */
    public function completeAttempt(Request $request, int $quizId, int $attemptId): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $learner = $user->learner;

        $attempt = QuizAttempt::where('id', $attemptId)
            ->where('learner_id', $learner->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $quiz = Quiz::with('questions')->findOrFail($quizId);

        $totalPoints = 0;
        $scoredPoints = 0;

        foreach ($quiz->questions as $q) {
            $totalPoints += $q->points;
            $options = $q->options ?: [];
            $earned = 0;
            $isCorrect = false;

            // Retrouver la réponse fournie
            $answerRecord = QuizAnswer::where('attempt_id', $attempt->id)
                ->where('question_id', $q->id)
                ->first();

            $correctAnswerVal = '';

            if ($answerRecord) {
                $userAns = json_decode($answerRecord->answer_given, true);
                if ($userAns === null) {
                    $userAns = $answerRecord->answer_given;
                }

                if ($q->type === 'true_false') {
                    $correct = ($options['correct_answer'] ?? 'true') === 'true';
                    $correctAnswerVal = $correct ? 'true' : 'false';
                    $userAnsBool = filter_var($userAns, FILTER_VALIDATE_BOOLEAN);
                    if ($correct === $userAnsBool) {
                        $earned = $q->points;
                        $isCorrect = true;
                    }
                } elseif ($q->type === 'mcq' || $q->type === 'single_choice' || $q->type === 'multiple_choice') {
                    $isMultiple = filter_var($options['multiple'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($q->type === 'multiple_choice');
                    $answersList = $options['answers'] ?? [];
                    $correctAnswers = collect($answersList)->filter(fn ($a) => filter_var($a['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->pluck('text')->toArray();
                    $correctAnswerVal = implode(', ', $correctAnswers);

                    if ($isMultiple) {
                        $userAnsArray = is_array($userAns) ? $userAns : [$userAns];
                        $matches = array_intersect($userAnsArray, $correctAnswers);
                        $matchesCount = count($matches);
                        $incorrectCount = count(array_diff($userAnsArray, $correctAnswers));

                        $isPartial = filter_var($options['partial_score'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        if ($isPartial) {
                            if (count($correctAnswers) > 0 && $incorrectCount === 0) {
                                $earned = (int) round(($matchesCount / count($correctAnswers)) * $q->points);
                            }
                            if ($earned === $q->points) {
                                $isCorrect = true;
                            }
                        } else {
                            if ($matchesCount === count($correctAnswers) && $incorrectCount === 0 && count($userAnsArray) === count($correctAnswers)) {
                                $earned = $q->points;
                                $isCorrect = true;
                            }
                        }
                    } else {
                        $userAnsStr = is_array($userAns) ? reset($userAns) : $userAns;
                        if (in_array($userAnsStr, $correctAnswers)) {
                            $earned = $q->points;
                            $isCorrect = true;
                        }
                    }
                } elseif ($q->type === 'fill_blank') {
                    $blanks = $options['blanks'] ?? [];
                    $userAnsArray = is_array($userAns) ? $userAns : [$userAns];
                    $correctCount = 0;
                    $correctAnswerVal = json_encode(collect($blanks)->map(fn ($b) => $b['answers'] ?? [])->toArray());

                    foreach ($blanks as $bIdx => $blank) {
                        $uText = trim($userAnsArray[$bIdx] ?? '');
                        $answers = $blank['answers'] ?? [];
                        $caseSensitive = filter_var($blank['case_sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $match = false;

                        foreach ($answers as $ans) {
                            if ($caseSensitive) {
                                if ($ans === $uText) {
                                    $match = true;
                                    break;
                                }
                            } else {
                                if (strtolower($ans) === strtolower($uText)) {
                                    $match = true;
                                    break;
                                }
                            }
                        }
                        if ($match) {
                            $correctCount++;
                        }
                    }

                    if (count($blanks) > 0) {
                        $earned = (int) round(($correctCount / count($blanks)) * $q->points);
                        if ($correctCount === count($blanks)) {
                            $isCorrect = true;
                        }
                    }
                } elseif ($q->type === 'matching') {
                    $pairs = $options['pairs'] ?? [];
                    $userAnsDict = is_array($userAns) ? $userAns : ['terms' => [], 'definitions' => []];
                    $matchCount = 0;
                    $correctAnswerVal = json_encode($pairs);

                    $terms = $userAnsDict['terms'] ?? [];
                    $definitions = $userAnsDict['definitions'] ?? [];

                    foreach ($terms as $idx => $term) {
                        $userDef = $definitions[$idx] ?? '';
                        $originalPair = collect($pairs)->first(fn ($p) => ($p['term'] ?? '') === $term);
                        if ($originalPair && ($originalPair['definition'] ?? '') === $userDef) {
                            $matchCount++;
                        }
                    }

                    if (count($pairs) > 0) {
                        $earned = (int) round(($matchCount / count($pairs)) * $q->points);
                        if ($matchCount === count($pairs)) {
                            $isCorrect = true;
                        }
                    }
                } elseif ($q->type === 'ordering') {
                    $items = $options['items'] ?? [];
                    $userAnsArray = is_array($userAns) ? $userAns : [];
                    $correctCount = 0;
                    $correctAnswerVal = implode(', ', $items);

                    foreach ($userAnsArray as $idx => $item) {
                        if (($items[$idx] ?? null) === $item) {
                            $correctCount++;
                        }
                    }

                    if (count($items) > 0) {
                        $earned = (int) round(($correctCount / count($items)) * $q->points);
                        if ($correctCount === count($items)) {
                            $isCorrect = true;
                        }
                    }
                } elseif ($q->type === 'open_text') {
                    $userAnsStr = trim($userAns);
                    if (strlen($userAnsStr) > 0) {
                        $earned = $q->points;
                        $isCorrect = true;
                    }
                }

                $answerRecord->update([
                    'correct_answer' => $correctAnswerVal,
                    'is_correct' => $isCorrect,
                    'points_earned' => $earned,
                ]);
            } else {
                // Créer une réponse vide
                QuizAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $q->id,
                    'answer_given' => '',
                    'correct_answer' => $correctAnswerVal,
                    'is_correct' => false,
                    'points_earned' => 0,
                    'answered_at' => now(),
                ]);
            }

            $scoredPoints += $earned;
        }

        $scorePercent = $totalPoints > 0 ? round(($scoredPoints / $totalPoints) * 100, 2) : 100.00;
        $passed = $scorePercent >= ($quiz->passing_score ?? 60.00);

        // Mettre à jour la tentative
        $timeSpent = now()->diffInSeconds($attempt->started_at);
        $attempt->update([
            'completed_at' => now(),
            'submitted_at' => now(),
            'score' => $scorePercent,
            'points_earned' => $scoredPoints,
            'points_total' => $totalPoints,
            'passed' => $passed,
            'time_spent' => $timeSpent,
            'status' => 'completed',
        ]);

        // Progression de l'apprenant
        LearnerProgress::updateOrCreate(
            [
                'learner_id' => $learner->id,
                'content_type' => 'quiz',
                'content_id' => $quiz->id,
            ],
            [
                'status' => 'completed',
                'progress_percentage' => 100,
                'time_spent' => $timeSpent,
                'completed_at' => now(),
            ]
        );

        // Attribution des XP et de la Gamification
        $awardedXp = 20 + ($passed ? 30 : 0) + ($scoredPoints * 5);
        $xp = $learner->xp ?? $learner->xp()->create([
            'total_xp' => 0,
            'current_level' => 1,
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
        ]);

        $newTotalXp = $xp->total_xp + $awardedXp;
        $newLevel = (int) floor($newTotalXp / 100) + 1;
        $levelUp = $newLevel > $xp->current_level;

        // Gestion du streak
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $streak = $xp->current_streak;

        if ($xp->last_activity_date === $yesterday) {
            $streak++;
        } elseif ($xp->last_activity_date !== $today) {
            $streak = 1;
        }
        $longest = max($xp->longest_streak, $streak);

        $xp->update([
            'total_xp' => $newTotalXp,
            'current_level' => $newLevel,
            'current_streak' => $streak,
            'longest_streak' => $longest,
            'last_activity_date' => $today,
        ]);

        // Vérification des Badges débloqués
        $unlockedBadges = $this->checkBadges($learner);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'attempt' => $attempt,
                'xp_earned' => $awardedXp,
                'level_up' => $levelUp,
                'new_level' => $newLevel,
                'badges_unlocked' => $unlockedBadges,
            ]);
        }

        return redirect()->route('learner.quizzes.show', $quiz->id)->with([
            'success_quiz' => 'Quiz terminé avec succès !',
            'xp_earned' => $awardedXp,
            'level_up' => $levelUp ? $newLevel : null,
            'badges_unlocked' => count($unlockedBadges) ? $unlockedBadges : null,
        ]);
    }

    /**
     * Enregistrer un signalement de triche / screenshot.
     */
    public function logScreenshot(Request $request): JsonResponse
    {
        $request->validate([
            'attempt_id' => 'required|exists:quiz_attempts,id',
        ]);

        ScreenshotAttempt::create([
            'learner_id' => Auth::user()->learner->id,
            'attempt_id' => $request->attempt_id,
            'detected_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Signaler une erreur sur un quiz.
     */
    public function reportError(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'error_type' => 'required|string',
            'comment' => 'required|string',
        ]);

        ErrorReport::create([
            'learner_id' => Auth::user()->learner->id,
            'content_type' => 'quiz',
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

    /**
     * Helper pour débloquer les badges
     */
    protected function checkBadges($learner): array
    {
        $unlocked = [];
        $allBadges = Badge::all();
        $existingBadgeIds = $learner->badges()->pluck('badges.id')->toArray();

        $completedQuizCount = QuizAttempt::where('learner_id', $learner->id)
            ->where('status', 'completed')
            ->count();

        $completedArticlesCount = LearnerProgress::where('learner_id', $learner->id)
            ->where('content_type', 'article')
            ->where('status', 'completed')
            ->count();

        foreach ($allBadges as $badge) {
            if (in_array($badge->id, $existingBadgeIds)) {
                continue;
            }

            $unlock = false;
            $val = $badge->condition_value;
            if (is_string($val)) {
                $val = json_decode($val, true);
            }
            $requiredCount = $val['count'] ?? 1;

            if ($badge->condition_type === 'quiz_completed' && $completedQuizCount >= $requiredCount) {
                $unlock = true;
            } elseif ($badge->condition_type === 'article_read' && $completedArticlesCount >= $requiredCount) {
                $unlock = true;
            }

            if ($unlock) {
                $learner->badges()->attach($badge->id, ['earned_at' => now()]);
                $unlocked[] = $badge->name;
            }
        }

        return $unlocked;
    }
}
