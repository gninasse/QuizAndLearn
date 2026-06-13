<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Http\Requests\StoreUserRequest;
use Modules\Core\Http\Requests\UpdateUserRequest;
use Modules\Core\Models\User;

class UserController extends Controller
{
    /**
     * Afficher la liste des utilisateurs
     */
    public function index()
    {
        return view('core::users.index');
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request)
    {
        $query = User::query();

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('service', 'like', "%{$search}%");
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
        $users = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'total' => $total,
            'rows' => $users,
        ]);
    }

    /**
     * Récupérer un utilisateur (pour édition)
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            // If request expects JSON (modal edit check), return JSON
            // But now we want a full page for details.
            // We can keep JSON for flexibility if header present, or just redirect?
            // User requested "change button modify... opening a page details".
            // So we return a view.

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $user,
                ]);
            }

            return view('core::users.show', compact('user'));

        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                ], 404);
            }
            abort(404);
        }
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function store(StoreUserRequest $request)
    {
        // Validation is handled by StoreUserRequest

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
                // dd($avatarName);
                $avatar->move(public_path('avatars'), $avatarName);
                $avatarPath = 'avatars/'.$avatarName;
            }

            $user = User::create([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'user_name' => $request->user_name,
                'email' => $request->email,
                'service' => $request->service,
                'password' => Hash::make($request->password),
                'avatar' => $avatarPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
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
     * Mettre à jour un utilisateur
     */
    public function update(UpdateUserRequest $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            // Validation is handled by UpdateUserRequest

            $user->name = $request->name;
            $user->last_name = $request->last_name;
            $user->user_name = $request->user_name;
            $user->email = $request->email;
            $user->service = $request->service;

            if ($request->hasFile('avatar')) {
                if ($user->avatar && file_exists(public_path($user->avatar))) {
                    unlink(public_path($user->avatar));
                }

                // Upload new avatar
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

            // Mettre à jour le mot de passe seulement s'il est fourni
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur modifié avec succès',
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
     * Supprimer un utilisateur
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Empêcher la suppression de son propre compte
            if ($user->getKey() === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte',
                ], 403);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword($id)
    {
        try {
            $user = User::findOrFail($id);
            $newPassword = config('core.user_default_password', 'password'); // Default password
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

    /**
     * Activer/Désactiver un utilisateur
     */
    public function toggleStatus($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->getKey() === auth()->id()) {
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
                'message' => "Utilisateur $status avec succès",
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
     * Mettre à jour le profil utilisateur (AJAX)
     */
    public function updateProfile(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'user_name' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
                'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
                'service' => ['nullable', 'string', 'max:255'],
            ]);

            $user->update([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'user_name' => $request->user_name,
                'email' => $request->email,
                'service' => $request->service,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $user,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour l'avatar utilisateur (AJAX)
     */
    public function updateAvatar(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ]);

            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($user->avatar && file_exists(public_path($user->avatar))) {
                    unlink(public_path($user->avatar));
                }

                // Upload new avatar
                $destinationPath = public_path('avatars');
                if (! file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $avatar = $request->file('avatar');
                $cleanUserName = str_replace(' ', '_', strtolower($user->user_name));
                $avatarName = time().'_'.$cleanUserName.'.'.$avatar->extension();
                $avatar->move($destinationPath, $avatarName);
                $user->avatar = 'avatars/'.$avatarName;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar mis à jour avec succès',
                'avatar_url' => $user->avatar_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available roles for assignment
     */
    public function getAvailableRoles($id)
    {
        try {
            $user = User::findOrFail($id);
            $assignedRoleIds = $user->roles->pluck('id')->toArray();

            $availableRoles = \Spatie\Permission\Models\Role::whereNotIn('id', $assignedRoleIds)
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description ?? 'No description available',
                    ];
                });

            return response()->json([
                'success' => true,
                'roles' => $availableRoles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign a role to user
     */
    public function assignRole(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'role_id' => ['required', 'exists:roles,id'],
            ]);

            $role = \Spatie\Permission\Models\Role::findOrFail($request->role_id);

            // Check if user already has this role
            if ($user->hasRole($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur a déjà ce rôle',
                ], 422);
            }

            $user->assignRole($role);
            $user->logRoleToggle($role->name, 'assigned');

            return response()->json([
                'success' => true,
                'message' => 'Rôle assigné avec succès',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'created_at' => now()->format('M d, Y'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a role from user
     */
    public function removeRole(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'role_id' => ['required', 'exists:roles,id'],
            ]);

            $role = \Spatie\Permission\Models\Role::findOrFail($request->role_id);

            if (! $user->hasRole($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur n\'a pas ce rôle',
                ], 422);
            }

            $user->removeRole($role);
            $user->logRoleToggle($role->name, 'removed');

            return response()->json([
                'success' => true,
                'message' => 'Rôle retiré avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a direct permission from user
     */
    public function removePermission(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'permission_id' => ['required', 'exists:permissions,id'],
            ]);

            $permission = \Spatie\Permission\Models\Permission::findOrFail($request->permission_id);

            if (! $user->hasDirectPermission($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur n\'a pas cette permission en direct',
                ], 422);
            }

            $user->revokePermissionTo($permission);
            $user->logPermissionToggle($permission->name, 'revoked');

            return response()->json([
                'success' => true,
                'message' => 'Permission retirée avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available permissions for assignment
     */
    public function getAvailablePermissions($id)
    {
        try {
            $user = User::findOrFail($id);
            $assignedPermissionIds = $user->getAllPermissions()->pluck('id')->toArray();

            $availablePermissions = \Spatie\Permission\Models\Permission::whereNotIn('id', $assignedPermissionIds)
                ->get()
                ->groupBy('module')
                ->map(function ($permissions, $module) {
                    return [
                        'module' => $module ?: 'System',
                        'permissions' => $permissions->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'label' => $permission->label ?: $permission->name,
                            ];
                        }),
                    ];
                })->values();

            $modules = \Spatie\Permission\Models\Permission::distinct()->pluck('module')->filter()->values();

            return response()->json([
                'success' => true,
                'permissions_by_module' => $availablePermissions,
                'modules' => $modules,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign permissions to user
     */
    public function assignPermissions(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'permission_ids' => ['required', 'array'],
                'permission_ids.*' => ['exists:permissions,id'],
            ]);

            $permissions = \Spatie\Permission\Models\Permission::whereIn('id', $request->permission_ids)->get();
            $user->givePermissionTo($permissions);
            $user->logPermissionToggle($permissions, 'given');

            return response()->json([
                'success' => true,
                'message' => 'Permissions assignées avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }
}
