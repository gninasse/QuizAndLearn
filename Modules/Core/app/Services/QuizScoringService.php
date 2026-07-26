<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAnswer;
use Modules\Core\Models\QuizAttempt;

/**
 * Notation des quiz — source unique pour tous les types de questions
 * (true_false, mcq/single_choice/multiple_choice, fill_blank, matching,
 * ordering, open_text).
 *
 * Logique portée à l'identique depuis LearnerSpaController::sync /
 * LearnerQuizController::completeAttempt (qui la dupliquaient).
 */
class QuizScoringService
{
    public const DEFAULT_PASSING_SCORE = 60.00;

    /**
     * Note une réponse pour une question donnée.
     *
     * @param  mixed  $userAns  réponse brute du client (string|array|null)
     * @return array{earned: int, is_correct: bool, correct_answer: string}
     */
    public function scoreQuestion(Question $question, mixed $userAns): array
    {
        $options = $question->options ?: [];
        $earned = 0;
        $isCorrect = false;
        $correctAnswerVal = '';

        if ($userAns === null) {
            return ['earned' => 0, 'is_correct' => false, 'correct_answer' => ''];
        }

        switch ($question->type) {
            case 'true_false':
                $correct = ($options['correct_answer'] ?? 'true') === 'true';
                $correctAnswerVal = $correct ? 'true' : 'false';
                $userAnsBool = filter_var($userAns, FILTER_VALIDATE_BOOLEAN);
                if ($correct === $userAnsBool) {
                    $earned = $question->points;
                    $isCorrect = true;
                }
                break;

            case 'mcq':
            case 'single_choice':
            case 'multiple_choice':
                $isMultiple = filter_var($options['multiple'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || ($question->type === 'multiple_choice');
                $answersList = $options['answers'] ?? [];
                $correctAnswers = collect($answersList)
                    ->filter(fn ($a) => filter_var($a['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                    ->pluck('text')
                    ->toArray();
                $correctAnswerVal = implode(', ', $correctAnswers);

                if ($isMultiple) {
                    $userAnsArray = is_array($userAns) ? $userAns : [$userAns];
                    $matchesCount = count(array_intersect($userAnsArray, $correctAnswers));
                    $incorrectCount = count(array_diff($userAnsArray, $correctAnswers));

                    $isPartial = filter_var($options['partial_score'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($isPartial) {
                        if (count($correctAnswers) > 0 && $incorrectCount === 0) {
                            $earned = (int) round(($matchesCount / count($correctAnswers)) * $question->points);
                        }
                        $isCorrect = ($earned === $question->points);
                    } elseif ($matchesCount === count($correctAnswers)
                        && $incorrectCount === 0
                        && count($userAnsArray) === count($correctAnswers)) {
                        $earned = $question->points;
                        $isCorrect = true;
                    }
                } else {
                    $userAnsStr = is_array($userAns) ? reset($userAns) : $userAns;
                    if (in_array($userAnsStr, $correctAnswers)) {
                        $earned = $question->points;
                        $isCorrect = true;
                    }
                }
                break;

            case 'fill_blank':
                $blanks = $options['blanks'] ?? [];
                $userAnsArray = is_array($userAns) ? $userAns : [$userAns];
                $correctCount = 0;
                $correctAnswerVal = json_encode(collect($blanks)->map(fn ($b) => $b['answers'] ?? [])->toArray());

                foreach ($blanks as $bIdx => $blank) {
                    $uText = trim($userAnsArray[$bIdx] ?? '');
                    $caseSensitive = filter_var($blank['case_sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    foreach ($blank['answers'] ?? [] as $ans) {
                        $match = $caseSensitive
                            ? $ans === $uText
                            : strtolower($ans) === strtolower($uText);
                        if ($match) {
                            $correctCount++;
                            break;
                        }
                    }
                }

                if (count($blanks) > 0) {
                    $earned = (int) round(($correctCount / count($blanks)) * $question->points);
                    $isCorrect = ($correctCount === count($blanks));
                }
                break;

            case 'matching':
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
                    $earned = (int) round(($matchCount / count($pairs)) * $question->points);
                    $isCorrect = ($matchCount === count($pairs));
                }
                break;

            case 'ordering':
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
                    $earned = (int) round(($correctCount / count($items)) * $question->points);
                    $isCorrect = ($correctCount === count($items));
                }
                break;

            case 'open_text':
                if (is_string($userAns) && strlen(trim($userAns)) > 0) {
                    $earned = $question->points;
                    $isCorrect = true;
                }
                break;
        }

        return ['earned' => $earned, 'is_correct' => $isCorrect, 'correct_answer' => $correctAnswerVal];
    }

    /**
     * Note un quiz complet.
     *
     * @param  array<int|string, mixed>  $answers  réponses indexées par id de question
     * @return array{
     *     total_points: int,
     *     scored_points: int,
     *     score_percent: float,
     *     passed: bool,
     *     per_question: array<int, array{earned: int, is_correct: bool, correct_answer: string, answer_given: mixed}>
     * }
     */
    public function score(Quiz $quiz, array $answers): array
    {
        $totalPoints = 0;
        $scoredPoints = 0;
        $perQuestion = [];

        foreach ($quiz->questions as $question) {
            $totalPoints += $question->points;
            $userAns = $answers[$question->id] ?? null;

            $result = $this->scoreQuestion($question, $userAns);
            $result['answer_given'] = $userAns;
            $perQuestion[$question->id] = $result;
            $scoredPoints += $result['earned'];
        }

        $scorePercent = $totalPoints > 0 ? round(($scoredPoints / $totalPoints) * 100, 2) : 100.00;
        $passed = $scorePercent >= ($quiz->passing_score ?? self::DEFAULT_PASSING_SCORE);

        return [
            'total_points' => $totalPoints,
            'scored_points' => $scoredPoints,
            'score_percent' => $scorePercent,
            'passed' => $passed,
            'per_question' => $perQuestion,
        ];
    }

    /**
     * Note et persiste une tentative complète (tentative + réponses + progression).
     *
     * @param  array<int|string, mixed>  $answers
     * @return array{attempt: QuizAttempt, scoring: array}
     */
    public function persistAttempt(
        Learner $learner,
        Quiz $quiz,
        array $answers,
        mixed $startedAt = null,
        mixed $completedAt = null,
    ): array {
        $startedAt ??= now();
        $completedAt ??= now();

        $scoring = $this->score($quiz, $answers);

        $prevAttempts = QuizAttempt::where('learner_id', $learner->id)
            ->where('quiz_id', $quiz->id)
            ->count();

        $attempt = QuizAttempt::create([
            'learner_id' => $learner->id,
            'quiz_id' => $quiz->id,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'submitted_at' => $completedAt,
            'attempt_number' => $prevAttempts + 1,
            'status' => 'completed',
            'score' => $scoring['score_percent'],
            'points_earned' => $scoring['scored_points'],
            'points_total' => $scoring['total_points'],
            'passed' => $scoring['passed'],
        ]);

        foreach ($scoring['per_question'] as $questionId => $result) {
            QuizAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'answer_given' => is_array($result['answer_given'])
                    ? json_encode($result['answer_given'])
                    : ($result['answer_given'] ?? ''),
                'correct_answer' => $result['correct_answer'],
                'is_correct' => $result['is_correct'],
                'points_earned' => $result['earned'],
                'answered_at' => now(),
            ]);
        }

        LearnerProgress::updateOrCreate(
            [
                'learner_id' => $learner->id,
                'content_type' => 'quiz',
                'content_id' => $quiz->id,
            ],
            [
                'status' => 'completed',
                'progress_percentage' => 100,
                'completed_at' => now(),
            ]
        );

        return ['attempt' => $attempt, 'scoring' => $scoring];
    }
}
