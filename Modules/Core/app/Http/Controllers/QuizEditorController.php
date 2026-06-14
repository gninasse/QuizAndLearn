<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;

class QuizEditorController extends Controller
{
    /**
     * Display the quiz editor view.
     */
    public function edit(int $quizId): View
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
            $quiz = Quiz::with(['questions', 'groups'])->findOrFail($quizId);
        } elseif ($user->hasRole('trainer') || $user->trainer) {
            $quiz = Quiz::with(['questions', 'groups' => function ($query) use ($user) {
                $query->whereHas('trainers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }])->findOrFail($quizId);
        } else {
            $quiz = Quiz::with(['questions', 'groups' => function ($query) {
                $query->whereRaw('1 = 0');
            }])->findOrFail($quizId);
        }

        return view('core::admin.quiz.editor', compact('quiz'));
    }

    /**
     * Autosave quiz settings via AJAX.
     */
    public function autosave(Request $request, int $quizId): JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($quizId);

            $quiz->update([
                'title' => $request->title,
                'description' => $request->description,
                'duration' => $request->duration,
                'passing_score' => $request->passing_score,
                'shuffle_questions' => $request->has('shuffle_questions'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Modification enregistrée avec succès.',
                'data' => $quiz,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement automatique : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the active status of the quiz.
     */
    public function toggleActive(int $quizId): JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($quizId);
            $quiz->is_active = ! $quiz->is_active;
            $quiz->save();

            $statusMessage = $quiz->is_active
                ? 'Le quiz a été publié avec succès.'
                : 'Le quiz a été repassé en mode brouillon.';

            return response()->json([
                'success' => true,
                'message' => $statusMessage,
                'is_active' => $quiz->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de changement de statut : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder quiz questions.
     */
    public function reorderQuestions(Request $request, int $quizId): JsonResponse
    {
        try {
            $request->validate([
                'question_ids' => 'required|array',
                'question_ids.*' => 'integer|exists:questions,id',
            ]);

            foreach ($request->question_ids as $index => $questionId) {
                Question::where('id', $questionId)
                    ->where('quiz_id', $quizId)
                    ->update(['order' => $index]);
            }

            return response()->json([
                'success' => true,
                'message' => 'L\'ordre des questions a été mis à jour.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de réorganisation : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search active groups for quiz assignment.
     */
    public function searchGroups(Request $request): JsonResponse
    {
        try {
            $query = strtolower($request->get('q', ''));
            $user = auth()->user();

            $groupsQuery = Group::active();

            if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
                // No extra filtering
            } elseif ($user->hasRole('trainer') || $user->trainer) {
                $groupsQuery->whereHas('trainers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } else {
                $groupsQuery->whereRaw('1 = 0');
            }

            $groups = $groupsQuery->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"])
                ->limit(10)
                ->get();

            $formattedGroups = $groups->map(function (Group $group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'learners_count' => $group->learners()->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedGroups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de recherche : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign a group to the quiz.
     */
    public function assignGroup(Request $request, int $quizId): JsonResponse
    {
        try {
            $request->validate([
                'group_id' => 'required|integer|exists:groups,id',
            ]);

            $user = auth()->user();
            $groupId = $request->group_id;

            // Enforce profile boundaries
            if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
                if ($user->hasRole('trainer') || $user->trainer) {
                    $hasGroup = $user->trainer && $user->trainer->groups()->where('groups.id', $groupId)->exists();
                    if (! $hasGroup) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Vous n\'êtes pas autorisé à assigner ce groupe.',
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Action non autorisée.',
                    ], 403);
                }
            }

            $quiz = Quiz::findOrFail($quizId);
            $quiz->groups()->syncWithoutDetaching([$groupId]);

            return response()->json([
                'success' => true,
                'message' => 'Le groupe a été assigné au quiz.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unassign a group from the quiz.
     */
    public function unassignGroup(int $quizId, int $groupId): JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($quizId);
            $quiz->groups()->detach($groupId);

            return response()->json([
                'success' => true,
                'message' => 'Le groupe a été retiré du quiz.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single question data.
     */
    public function showQuestion(int $quizId, int $questionId): JsonResponse
    {
        try {
            $question = Question::where('id', $questionId)
                ->where('quiz_id', $quizId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $question,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question introuvable.',
            ], 404);
        }
    }

    /**
     * Store a new question in the quiz.
     */
    public function storeQuestion(Request $request, int $quizId): JsonResponse
    {
        try {
            $request->validate([
                'question_text' => 'required|string',
                'type' => 'required|string',
                'points' => 'required|integer|min:1',
                'options' => 'nullable|array',
            ]);

            $quiz = Quiz::findOrFail($quizId);

            // Compute order index
            $maxOrder = Question::where('quiz_id', $quizId)->max('order');
            $order = $maxOrder !== null ? $maxOrder + 1 : 0;

            $question = Question::create([
                'quiz_id' => $quiz->id,
                'question_text' => $request->question_text,
                'type' => $request->type,
                'points' => $request->points,
                'order' => $order,
                'options' => $request->options,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Question créée avec succès.',
                'data' => $question,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de création de la question : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing question.
     */
    public function updateQuestion(Request $request, int $quizId, int $questionId): JsonResponse
    {
        try {
            $request->validate([
                'question_text' => 'required|string',
                'type' => 'required|string',
                'points' => 'required|integer|min:1',
                'options' => 'nullable|array',
            ]);

            $question = Question::where('id', $questionId)
                ->where('quiz_id', $quizId)
                ->firstOrFail();

            $question->update([
                'question_text' => $request->question_text,
                'type' => $request->type,
                'points' => $request->points,
                'options' => $request->options,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Question mise à jour avec succès.',
                'data' => $question,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de modification de la question : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a question.
     */
    public function destroyQuestion(int $quizId, int $questionId): JsonResponse
    {
        try {
            $question = Question::where('id', $questionId)
                ->where('quiz_id', $quizId)
                ->firstOrFail();

            $question->delete();

            return response()->json([
                'success' => true,
                'message' => 'Question supprimée avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the quiz preview container with device selector.
     */
    public function preview(int $quizId): View
    {
        $quiz = Quiz::findOrFail($quizId);

        return view('core::admin.quiz.preview', compact('quiz'));
    }

    /**
     * Display the actual quiz player inside the preview iframe.
     */
    public function previewIframe(int $quizId): View
    {
        $quiz = Quiz::with('questions')->findOrFail($quizId);

        return view('core::admin.quiz.preview-iframe', compact('quiz'));
    }
}
