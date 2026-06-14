<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Requests\StoreGroupRequest;
use Modules\Core\Http\Requests\UpdateGroupRequest;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Trainer;

class GroupController extends Controller
{
    /**
     * Afficher la liste des groupes
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('core::groups.index');
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Group::query();

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
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
        $groups = $query->offset($offset)->limit($limit)->get();

        // Formater les dates pour l'affichage
        $rows = $groups->map(function ($group) {
            $group->trainers_count = $group->trainers()->count();
            $group->learners_count = $group->learners()->count();
            $group->start_date_formatted = $group->start_date ? $group->start_date->format('Y-m-d') : '-';
            $group->end_date_formatted = $group->end_date ? $group->end_date->format('Y-m-d') : '-';

            return $group;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Récupérer un groupe (pour édition)
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $group,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Groupe non trouvé',
            ], 404);
        }
    }

    /**
     * Créer un nouveau groupe (AJAX)
     */
    public function store(StoreGroupRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::create([
                'name' => $request->name,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Groupe créé avec succès',
                'data' => $group,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un groupe (AJAX)
     */
    public function update(UpdateGroupRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::findOrFail($id);
            $group->update([
                'name' => $request->name,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->has('is_active') ? $request->is_active : $group->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Groupe modifié avec succès',
                'data' => $group,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un groupe (AJAX)
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::findOrFail($id);

            // Les tables pivots group_trainer et group_learner ont des clés étrangères onDelete cascade,
            // ce qui signifie que la suppression logique ou physique du groupe nettoie proprement les liens.
            $group->delete();

            return response()->json([
                'success' => true,
                'message' => 'Groupe supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un groupe (AJAX)
     */
    public function toggleStatus(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::findOrFail($id);
            $group->is_active = ! $group->is_active;
            $group->save();

            $status = $group->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => "Groupe $status avec succès",
                'is_active' => $group->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir la liste des membres associés au groupe (AJAX)
     */
    public function getMembers(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::with('quizzes')->findOrFail($id);

            // Charger les formateurs assignés et disponibles
            $assignedTrainerIds = $group->trainers->pluck('id')->toArray();
            $trainers = Trainer::with('user')->get()->map(function ($trainer) use ($assignedTrainerIds) {
                return [
                    'id' => $trainer->id,
                    'name' => $trainer->user->full_name,
                    'email' => $trainer->user->email,
                    'avatar_url' => $trainer->user->avatar_url,
                    'assigned' => in_array($trainer->id, $assignedTrainerIds),
                ];
            });

            // Charger les apprenants assignés et disponibles
            $assignedLearnerIds = $group->learners->pluck('id')->toArray();
            $learners = Learner::with('user')->get()->map(function ($learner) use ($assignedLearnerIds) {
                return [
                    'id' => $learner->id,
                    'name' => $learner->user->full_name,
                    'email' => $learner->user->email,
                    'avatar_url' => $learner->user->avatar_url,
                    'assigned' => in_array($learner->id, $assignedLearnerIds),
                ];
            });

            // Charger les quiz assignés au groupe
            $quizzes = $group->quizzes->map(function ($quiz) {
                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'questions_count' => $quiz->questions()->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'group_name' => $group->name,
                'is_active' => $group->is_active,
                'start_date' => $group->start_date ? $group->start_date->format('Y-m-d') : null,
                'end_date' => $group->end_date ? $group->end_date->format('Y-m-d') : null,
                'trainers' => $trainers,
                'learners' => $learners,
                'quizzes' => $quizzes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assigner des membres au groupe (AJAX)
     */
    public function assignMembers(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::findOrFail($id);

            $request->validate([
                'trainer_ids' => 'nullable|array',
                'trainer_ids.*' => 'exists:trainers,id',
                'learner_ids' => 'nullable|array',
                'learner_ids.*' => 'exists:learners,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'nullable|boolean',
            ]);

            // Mettre à jour les paramètres de groupe édités dans la modale
            $group->update([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->has('is_active') ? $request->is_active : $group->is_active,
            ]);

            // Synchroniser les pivots
            $group->trainers()->sync($request->input('trainer_ids', []));
            $group->learners()->sync($request->input('learner_ids', []));

            return response()->json([
                'success' => true,
                'message' => 'Membres et paramètres du groupe mis à jour avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }
}
