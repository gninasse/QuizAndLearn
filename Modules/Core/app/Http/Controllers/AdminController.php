<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Http\Requests\StoreAdminRequest;
use Modules\Core\Http\Requests\UpdateAdminRequest;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /**
     * Afficher la liste des administrateurs
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('core::admins.index');
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = User::role(['admin']);

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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
        $admins = $query->offset($offset)->limit($limit)->get();

        // Formater pour Bootstrap Table avec les rôles associés
        $rows = $admins->map(function ($admin) {
            $admin->roles_list = $admin->roles->pluck('name')->toArray();

            return $admin;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Récupérer un administrateur (pour édition)
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role(['super-admin', 'admin'])->findOrFail($id);
            $user->role = $user->roles->first()?->name;

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Administrateur non trouvé',
            ], 404);
        }
    }

    /**
     * Créer un nouvel administrateur (AJAX)
     */
    public function store(StoreAdminRequest $request): \Illuminate\Http\JsonResponse
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
            Role::findOrCreate('admin');

            $user->assignRole($request->role);
            $user->logRoleToggle($request->role, 'assigned');

            return response()->json([
                'success' => true,
                'message' => 'Administrateur créé avec succès',
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
     * Mettre à jour un administrateur (AJAX)
     */
    public function update(UpdateAdminRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role(['super-admin', 'admin'])->findOrFail($id);

            // Empêcher la modification de son propre rôle pour éviter de se bloquer soi-même
            if ($user->id === auth()->id() && $user->roles->first()?->name !== $request->role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas modifier votre propre rôle administratif',
                ], 403);
            }

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

            // Mettre à jour le rôle Spatie
            $oldRole = $user->roles->first()?->name;
            if ($oldRole !== $request->role) {
                $user->syncRoles([$request->role]);
                $user->logRoleToggle($request->role, 'assigned');
            }

            return response()->json([
                'success' => true,
                'message' => 'Administrateur modifié avec succès',
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
     * Supprimer un administrateur (AJAX)
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role(['super-admin', 'admin'])->findOrFail($id);

            // Empêcher la suppression de son propre compte
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte',
                ], 403);
            }

            // Empêcher la suppression du super-admin par un simple admin
            if ($user->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne disposez pas des privilèges suffisants pour supprimer un super-administrateur',
                ], 403);
            }

            if ($user->avatar && file_exists(public_path($user->avatar))) {
                unlink(public_path($user->avatar));
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Administrateur supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un administrateur (AJAX)
     */
    public function toggleStatus(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::role(['super-admin', 'admin'])->findOrFail($id);

            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas modifier le statut de votre propre compte',
                ], 403);
            }

            $user->is_active = ! $user->is_active;
            $user->save();

            $status = $user->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => "Administrateur $status avec succès",
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
            $user = User::role(['super-admin', 'admin'])->findOrFail($id);
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
