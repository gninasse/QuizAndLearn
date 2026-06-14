<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Http\Requests\StoreLearnerRequest;
use Modules\Core\Http\Requests\UpdateLearnerRequest;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class LearnerController extends Controller
{
    /**
     * Afficher la liste des apprenants
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $groups = Group::active()->get();

        return view('core::learners.index', compact('groups'));
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = User::role('learner')->with(['learner.groups']);

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('learner', function ($sq) use ($search) {
                        $sq->where('matricule', 'like', "%{$search}%");
                    });
            });
        }

        // Tri
        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');

        if ($sortBy === 'matricule') {
            $query->select('users.*')
                ->leftJoin('learners', 'users.id', '=', 'learners.user_id')
                ->orderBy('learners.matricule', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $learners = $query->offset($offset)->limit($limit)->get();

        // Formater pour insérer le matricule et les groupes
        $rows = $learners->map(function ($user) {
            $user->matricule = $user->learner?->matricule;
            $user->groups_list = $user->learner?->groups->pluck('name')->toArray() ?? [];

            return $user;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Récupérer un apprenant (pour édition)
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('learner')->with(['learner.groups'])->findOrFail($id);

            $data = $user->toArray();
            $data['matricule'] = $user->learner?->matricule;
            $data['group_ids'] = $user->learner?->groups->pluck('id')->toArray() ?? [];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Apprenant non trouvé',
            ], 404);
        }
    }

    /**
     * Créer un nouvel apprenant (AJAX)
     */
    public function store(StoreLearnerRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $destinationPath = public_path('avatars');
                if (! file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $avatar = $request->file('avatar');
                $cleanUserName = str_replace(' ', '_', strtolower($request->user_name));
                $avatarName = time().'_'.$cleanUserName.'.'.$avatar->extension();
                $avatar->move($destinationPath, $avatarName);
                $avatarPath = 'avatars/'.$avatarName;
            }

            // Créer l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'user_name' => $request->user_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'avatar' => $avatarPath,
                'is_active' => true,
            ]);
            Role::findOrCreate('learner');

            $user->assignRole('learner');
            $user->logRoleToggle('learner', 'assigned');

            // Créer le profil apprenant
            $learner = Learner::create([
                'user_id' => $user->id,
                'matricule' => $request->matricule,
            ]);

            // Assigner les groupes si fournis
            if ($request->has('group_ids')) {
                $learner->groups()->sync($request->group_ids);
            }

            return response()->json([
                'success' => true,
                'message' => 'Apprenant créé avec succès',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un apprenant (AJAX)
     */
    public function update(UpdateLearnerRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('learner')->findOrFail($id);

            $user->name = $request->name;
            $user->last_name = $request->last_name;
            $user->user_name = $request->user_name;
            $user->email = $request->email;
            $user->phone = $request->phone;

            if ($request->hasFile('avatar')) {
                if ($user->avatar && file_exists(public_path($user->avatar))) {
                    unlink(public_path($user->avatar));
                }

                $destinationPath = public_path('avatars');
                if (! file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $avatar = $request->file('avatar');
                $cleanUserName = str_replace(' ', '_', strtolower($request->user_name));
                $avatarName = time().'_'.$cleanUserName.'.'.$avatar->extension();
                $avatar->move($destinationPath, $avatarName);
                $user->avatar = 'avatars/'.$avatarName;
            }

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            // Mettre à jour le profil apprenant
            $learner = Learner::updateOrCreate(
                ['user_id' => $user->id],
                ['matricule' => $request->matricule]
            );

            // Synchroniser les groupes
            if ($request->has('group_ids')) {
                $learner->groups()->sync($request->group_ids);
            } else {
                $learner->groups()->sync([]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Apprenant modifié avec succès',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un apprenant (AJAX)
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('learner')->findOrFail($id);

            if ($user->avatar && file_exists(public_path($user->avatar))) {
                unlink(public_path($user->avatar));
            }

            // La suppression de l'utilisateur supprimera l'apprenant par cascade SQL
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Apprenant supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un apprenant (AJAX)
     */
    public function toggleStatus(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('learner')->findOrFail($id);

            $user->is_active = ! $user->is_active;
            $user->save();

            $status = $user->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => "Apprenant $status avec succès",
                'is_active' => $user->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe (AJAX)
     */
    public function resetPassword(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('learner')->findOrFail($id);
            $newPassword = config('core.user_default_password', 'password');
            $user->password = Hash::make($newPassword);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé à : '.$newPassword,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }
}
