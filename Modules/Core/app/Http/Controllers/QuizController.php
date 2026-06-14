<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Requests\StoreQuestionRequest;
use Modules\Core\Http\Requests\StoreQuizRequest;
use Modules\Core\Http\Requests\UpdateQuestionRequest;
use Modules\Core\Http\Requests\UpdateQuizRequest;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;

class QuizController extends Controller
{
    /**
     * Afficher la liste des quiz
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
            $groups = Group::active()->get();
        } elseif ($user->hasRole('trainer') || $user->trainer) {
            $groups = $user->trainer ? $user->trainer->groups()->active()->get() : collect();
        } else {
            $groups = collect();
        }

        return view('core::quizzes.index', compact('groups'));
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Quiz::with(['creator', 'groups']);

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $quizzes = $query->offset($offset)->limit($limit)->get();

        // Formater les lignes
        $rows = $quizzes->map(function ($quiz) {
            $quiz->creator_name = $quiz->creator ? $quiz->creator->name.' '.$quiz->creator->last_name : 'Système';
            $quiz->questions_count = $quiz->questions()->count();
            $quiz->groups_list = $quiz->groups->pluck('name')->toArray() ?? [];

            return $quiz;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Récupérer un quiz spécifique (AJAX)
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $quiz = Quiz::with('groups')->findOrFail($id);

            $data = $quiz->toArray();
            $data['group_ids'] = $quiz->groups->pluck('id')->toArray() ?? [];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz non trouvé',
            ], 404);
        }
    }

    /**
     * Créer un nouveau quiz (AJAX)
     */
    public function store(StoreQuizRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $quiz = Quiz::create([
                'title' => $request->title,
                'description' => $request->description,
                'duration' => $request->duration,
                'passing_score' => $request->passing_score,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
                'created_by' => auth()->id(),
            ]);

            // Synchroniser les groupes si fournis
            if ($request->has('group_ids')) {
                $quiz->groups()->sync($request->group_ids);
            }

            return response()->json([
                'success' => true,
                'message' => 'Quiz créé avec succès',
                'data' => $quiz,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un quiz (AJAX)
     */
    public function update(UpdateQuizRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($id);
            $quiz->update([
                'title' => $request->title,
                'description' => $request->description,
                'duration' => $request->duration,
                'passing_score' => $request->passing_score,
                'is_active' => $request->has('is_active') ? $request->is_active : $quiz->is_active,
            ]);

            // Synchroniser les groupes
            if ($request->has('group_ids')) {
                $quiz->groups()->sync($request->group_ids);
            } else {
                $quiz->groups()->sync([]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Quiz modifié avec succès',
                'data' => $quiz,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un quiz (AJAX)
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($id);
            $quiz->delete();

            return response()->json([
                'success' => true,
                'message' => 'Quiz supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un quiz (AJAX)
     */
    public function toggleStatus(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($id);
            $quiz->is_active = ! $quiz->is_active;
            $quiz->save();

            $status = $quiz->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => "Quiz $status avec succès",
                'is_active' => $quiz->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher le Quiz Builder pour concevoir les questions
     */
    public function builder(int $id): \Illuminate\Contracts\View\View
    {
        $quiz = Quiz::with('questions')->findOrFail($id);

        return view('core::quizzes.builder', compact('quiz'));
    }

    /**
     * Enregistrer une question dans le quiz (AJAX)
     */
    public function storeQuestion(StoreQuestionRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $quiz = Quiz::findOrFail($id);

            // Calculer l'ordre
            $maxOrder = Question::where('quiz_id', $id)->max('order');
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
                'message' => 'Question ajoutée avec succès.',
                'data' => $question,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Modifier une question du quiz (AJAX)
     */
    public function updateQuestion(UpdateQuestionRequest $request, int $id, int $questionId): \Illuminate\Http\JsonResponse
    {
        try {
            // Vérifier que le quiz existe et que la question lui appartient
            $question = Question::where('id', $questionId)->where('quiz_id', $id)->firstOrFail();

            $question->update([
                'question_text' => $request->question_text,
                'type' => $request->type,
                'points' => $request->points,
                'options' => $request->options,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Question modifiée avec succès.',
                'data' => $question,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une question du quiz (AJAX)
     */
    public function destroyQuestion(int $id, int $questionId): \Illuminate\Http\JsonResponse
    {
        try {
            $question = Question::where('id', $questionId)->where('quiz_id', $id)->firstOrFail();
            $question->delete();

            return response()->json([
                'success' => true,
                'message' => 'Question supprimée avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réordonner les questions du quiz (AJAX)
     */
    public function reorderQuestions(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'question_ids' => 'required|array',
                'question_ids.*' => 'integer|exists:questions,id',
            ]);

            foreach ($request->question_ids as $index => $questionId) {
                Question::where('id', $questionId)->where('quiz_id', $id)->update(['order' => $index]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Questions réordonnées avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }
}
