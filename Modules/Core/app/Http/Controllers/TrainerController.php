<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Http\Requests\StoreTrainerRequest;
use Modules\Core\Http\Requests\UpdateTrainerRequest;
use Modules\Core\Models\Group;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class TrainerController extends Controller
{
    /**
     * Afficher la liste des formateurs
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $groups = Group::active()->get();

        return view('core::trainers.index', compact('groups'));
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = User::role('trainer')->with(['trainer.groups']);

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('trainer', function ($sq) use ($search) {
                        $sq->where('specialty', 'like', "%{$search}%");
                    });
            });
        }

        // Tri
        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');

        // Tri spécifique aux champs du profil
        if ($sortBy === 'specialty') {
            $query->select('users.*')
                ->leftJoin('trainers', 'users.id', '=', 'trainers.user_id')
                ->orderBy('trainers.specialty', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $trainers = $query->offset($offset)->limit($limit)->get();

        // Formater pour insérer la spécialité dans les lignes
        $rows = $trainers->map(function ($user) {
            $user->specialty = $user->trainer?->specialty;
            $user->biography = $user->trainer?->biography;
            $user->groups_list = $user->trainer?->groups->pluck('name')->toArray() ?? [];

            return $user;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Récupérer un formateur (pour édition)
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('trainer')->with(['trainer.groups'])->findOrFail($id);

            // Préparer les données pour le formulaire
            $data = $user->toArray();
            $data['specialty'] = $user->trainer?->specialty;
            $data['biography'] = $user->trainer?->biography;
            $data['group_ids'] = $user->trainer?->groups->pluck('id')->toArray() ?? [];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé',
            ], 404);
        }
    }

    /**
     * Créer un nouveau formateur (AJAX)
     */
    public function store(StoreTrainerRequest $request): \Illuminate\Http\JsonResponse
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

            Role::findOrCreate('trainer');

            $user->assignRole('trainer');
            $user->logRoleToggle('trainer', 'assigned');

            // Créer le profil formateur
            $trainer = Trainer::create([
                'user_id' => $user->id,
                'specialty' => $request->specialty,
                'biography' => $request->biography,
            ]);

            // Assigner les groupes si fournis
            if ($request->has('group_ids')) {
                $trainer->groups()->sync($request->group_ids);
            }

            return response()->json([
                'success' => true,
                'message' => 'Formateur créé avec succès',
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
     * Mettre à jour un formateur (AJAX)
     */
    public function update(UpdateTrainerRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('trainer')->findOrFail($id);

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

            // Mettre à jour le profil formateur
            $trainer = Trainer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty' => $request->specialty,
                    'biography' => $request->biography,
                ]
            );

            // Synchroniser les groupes
            if ($request->has('group_ids')) {
                $trainer->groups()->sync($request->group_ids);
            } else {
                $trainer->groups()->sync([]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Formateur modifié avec succès',
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
     * Supprimer un formateur (AJAX)
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('trainer')->findOrFail($id);

            if ($user->avatar && file_exists(public_path($user->avatar))) {
                unlink(public_path($user->avatar));
            }

            // La suppression de l'utilisateur supprimera le formateur par cascade SQL
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Formateur supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un formateur (AJAX)
     */
    public function toggleStatus(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role('trainer')->findOrFail($id);

            $user->is_active = ! $user->is_active;
            $user->save();

            $status = $user->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => "Formateur $status avec succès",
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
            $user = User::role('trainer')->findOrFail($id);
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
