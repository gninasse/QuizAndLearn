<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamAttempt;
use Modules\Core\Models\ExamQuestion;
use Modules\Core\Models\Group;

class ExamController extends Controller
{
    private function authorizeExam(int $id): Exam
    {
        $exam = Exam::findOrFail($id);
        $user = auth()->user();
        if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
            if ($exam->created_by !== $user->id) {
                abort(403, 'Action non autorisée.');
            }
        }

        return $exam;
    }

    /**
     * Display the index view for exams.
     */
    public function index(): View
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
            $groups = Group::active()->get();
        } elseif ($user->hasRole('trainer') || $user->trainer) {
            $groups = $user->trainer ? $user->trainer->groups()->active()->get() : collect();
        } else {
            $groups = collect();
        }

        return view('core::exams.index', compact('groups'));
    }

    /**
     * Fetch exams for Bootstrap Table (AJAX).
     */
    public function getData(Request $request): JsonResponse
    {
        $query = Exam::with(['creator', 'groups']);

        $user = auth()->user();
        if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
            $query->where('created_by', $user->id);
        }

        // Search
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $exams = $query->offset($offset)->limit($limit)->get();

        // Format rows
        $rows = $exams->map(function ($exam) {
            $exam->creator_name = $exam->creator ? $exam->creator->name.' '.$exam->creator->last_name : 'Système';
            $exam->questions_count = $exam->questions()->count();
            $exam->groups_list = $exam->groups->pluck('name')->toArray() ?? [];

            return $exam;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Fetch specific exam metadata.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $exam = $this->authorizeExam($id);
            $exam->load('groups');
            $data = $exam->toArray();
            $data['group_ids'] = $exam->groups->pluck('id')->toArray() ?? [];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Examen non trouvé',
            ], 404);
        }
    }

    /**
     * Store a new exam.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'duration' => 'required|integer|min:1',
                'passing_score' => 'required|integer|min:0|max:100',
                'max_attempts' => 'required|integer|min:1',
                'available_from' => 'nullable|date',
                'available_until' => 'nullable|date',
                'plein_ecran_force' => 'nullable|boolean',
                'anti_capture_strict' => 'nullable|boolean',
                'navigation_interdite' => 'nullable|boolean',
                'publication_resultats' => 'required|string|in:immediate,apres_fermeture,manuelle',
                'classement_visible' => 'nullable|boolean',
                'classement_anonyme' => 'nullable|boolean',
                'note_max' => 'required|integer|min:1',
                'group_ids' => 'nullable|array',
            ]);

            $exam = Exam::create([
                'title' => $request->title,
                'description' => $request->description,
                'duration' => $request->duration,
                'passing_score' => $request->passing_score,
                'max_attempts' => $request->max_attempts,
                'available_from' => $request->available_from,
                'available_until' => $request->available_until,
                'plein_ecran_force' => $request->has('plein_ecran_force') ? (bool) $request->plein_ecran_force : true,
                'anti_capture_strict' => $request->has('anti_capture_strict') ? (bool) $request->anti_capture_strict : true,
                'navigation_interdite' => $request->has('navigation_interdite') ? (bool) $request->navigation_interdite : true,
                'publication_resultats' => $request->publication_resultats,
                'classement_visible' => $request->has('classement_visible') ? (bool) $request->classement_visible : true,
                'classement_anonyme' => $request->has('classement_anonyme') ? (bool) $request->classement_anonyme : false,
                'note_max' => $request->note_max,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
                'created_by' => auth()->id(),
            ]);

            if ($request->has('group_ids')) {
                $exam->groups()->sync($request->group_ids);
            }

            return response()->json([
                'success' => true,
                'message' => 'Examen créé avec succès',
                'data' => $exam,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update exam settings.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'duration' => 'required|integer|min:1',
                'passing_score' => 'required|integer|min:0|max:100',
                'max_attempts' => 'required|integer|min:1',
                'available_from' => 'nullable|date',
                'available_until' => 'nullable|date',
                'plein_ecran_force' => 'nullable|boolean',
                'anti_capture_strict' => 'nullable|boolean',
                'navigation_interdite' => 'nullable|boolean',
                'publication_resultats' => 'required|string|in:immediate,apres_fermeture,manuelle',
                'classement_visible' => 'nullable|boolean',
                'classement_anonyme' => 'nullable|boolean',
                'note_max' => 'required|integer|min:1',
                'group_ids' => 'nullable|array',
            ]);

            $exam = $this->authorizeExam($id);
            $exam->update([
                'title' => $request->title,
                'description' => $request->description,
                'duration' => $request->duration,
                'passing_score' => $request->passing_score,
                'max_attempts' => $request->max_attempts,
                'available_from' => $request->available_from,
                'available_until' => $request->available_until,
                'plein_ecran_force' => $request->has('plein_ecran_force') ? (bool) $request->plein_ecran_force : false,
                'anti_capture_strict' => $request->has('anti_capture_strict') ? (bool) $request->anti_capture_strict : false,
                'navigation_interdite' => $request->has('navigation_interdite') ? (bool) $request->navigation_interdite : false,
                'publication_resultats' => $request->publication_resultats,
                'classement_visible' => $request->has('classement_visible') ? (bool) $request->classement_visible : false,
                'classement_anonyme' => $request->has('classement_anonyme') ? (bool) $request->classement_anonyme : false,
                'note_max' => $request->note_max,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : $exam->is_active,
            ]);

            if ($request->has('group_ids')) {
                $exam->groups()->sync($request->group_ids);
            } else {
                $exam->groups()->sync([]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Examen modifié avec succès',
                'data' => $exam,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an exam.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $exam = $this->authorizeExam($id);
            $exam->delete();

            return response()->json([
                'success' => true,
                'message' => 'Examen supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the published status of the exam.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $exam = $this->authorizeExam($id);
            $exam->is_active = ! $exam->is_active;
            $exam->save();

            $status = $exam->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => 'Examen '.$status.' avec succès',
                'is_active' => $exam->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de changement de statut : '.$e->getMessage(),
            ], 500);
        }
    }

    public function builder(int $id): View
    {
        $exam = $this->authorizeExam($id);
        $exam->load(['questions', 'groups']);

        return view('core::admin.exam.editor', compact('exam'));
    }

    /**
     * Store a question for the exam builder.
     */
    public function storeQuestion(Request $request, int $examId): JsonResponse
    {
        try {
            $request->validate([
                'question_text' => 'required|string',
                'type' => 'required|string',
                'points' => 'required|integer|min:1',
                'points_negatifs' => 'nullable|numeric|min:0',
                'options' => 'nullable|array',
            ]);

            $exam = $this->authorizeExam($examId);

            $maxOrder = ExamQuestion::where('exam_id', $examId)->max('order');
            $order = $maxOrder !== null ? $maxOrder + 1 : 0;

            $question = ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_text' => $request->question_text,
                'type' => $request->type,
                'points' => $request->points,
                'points_negatifs' => $request->input('points_negatifs', 0.00),
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
                'message' => 'Erreur lors de l\'ajout de la question : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve question data.
     */
    public function showQuestion(int $examId, int $questionId): JsonResponse
    {
        try {
            $this->authorizeExam($examId);
            $question = ExamQuestion::where('id', $questionId)
                ->where('exam_id', $examId)
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
     * Update an existing question.
     */
    public function updateQuestion(Request $request, int $examId, int $questionId): JsonResponse
    {
        try {
            $request->validate([
                'question_text' => 'required|string',
                'type' => 'required|string',
                'points' => 'required|integer|min:1',
                'points_negatifs' => 'nullable|numeric|min:0',
                'options' => 'nullable|array',
            ]);

            $this->authorizeExam($examId);
            $question = ExamQuestion::where('id', $questionId)
                ->where('exam_id', $examId)
                ->firstOrFail();

            $question->update([
                'question_text' => $request->question_text,
                'type' => $request->type,
                'points' => $request->points,
                'points_negatifs' => $request->input('points_negatifs', 0.00),
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
                'message' => 'Erreur lors de la modification de la question : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an exam question.
     */
    public function destroyQuestion(int $examId, int $questionId): JsonResponse
    {
        try {
            $this->authorizeExam($examId);
            $question = ExamQuestion::where('id', $questionId)
                ->where('exam_id', $examId)
                ->firstOrFail();

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
     * Reorder questions in an exam.
     */
    public function reorderQuestions(Request $request, int $examId): JsonResponse
    {
        try {
            $this->authorizeExam($examId);

            $request->validate([
                'question_ids' => 'required|array',
                'question_ids.*' => 'integer|exists:exam_questions,id',
            ]);

            foreach ($request->question_ids as $index => $questionId) {
                ExamQuestion::where('id', $questionId)
                    ->where('exam_id', $examId)
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
     * Show preview index.
     */
    public function preview(int $id): View
    {
        $exam = $this->authorizeExam($id);

        return view('core::admin.exam.preview', compact('exam'));
    }

    /**
     * Render the exam player iframe.
     */
    public function previewIframe(int $id): View
    {
        $exam = $this->authorizeExam($id)->load('questions');

        return view('core::admin.exam.preview-iframe', compact('exam'));
    }

    /**
     * Render the real-time supervision dashboard.
     */
    public function supervision(int $id): View
    {
        $exam = $this->authorizeExam($id);

        return view('core::admin.exam.supervision', compact('exam'));
    }

    /**
     * Get supervision live data (AJAX).
     */
    public function getSupervisionData(int $id): JsonResponse
    {
        $this->authorizeExam($id);

        $attempts = ExamAttempt::where('exam_id', $id)
            ->with(['learner.user'])
            ->orderBy('date_debut', 'desc')
            ->get();

        $rows = $attempts->map(function ($att) {
            $user = $att->learner->user;

            // Format elapsed or remaining time
            $elapsedSeconds = $att->date_fin
                ? $att->date_fin->getTimestamp() - $att->date_debut->getTimestamp()
                : now()->getTimestamp() - $att->date_debut->getTimestamp();

            return [
                'id' => $att->id,
                'apprenant_name' => $user ? $user->name.' '.$user->last_name : 'Inconnu',
                'date_debut' => $att->date_debut->format('d/m/Y H:i:s'),
                'duree_reelle' => gmdate('H:i:s', $elapsedSeconds),
                'score_brut' => $att->score_brut,
                'score_total' => $att->score_total,
                'note_sur_vingt' => $att->note_sur_vingt ? $att->note_sur_vingt.'/20' : '—',
                'status' => $att->status,
                'capture_attempts' => $att->capture_attempts,
                'navigation_violations' => $att->navigation_violations,
            ];
        });

        return response()->json([
            'success' => true,
            'rows' => $rows,
        ]);
    }

    /**
     * Display the print preview page for the exam.
     */
    public function printIframe(int $id): View
    {
        $exam = $this->authorizeExam($id)->load('questions');

        return view('core::admin.exam.print-iframe', compact('exam'));
    }
}
