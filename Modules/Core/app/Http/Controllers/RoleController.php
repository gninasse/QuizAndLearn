<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Requests\StoreRoleRequest;
use Modules\Core\Http\Requests\UpdateRoleRequest;
use Modules\Core\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('core::roles.index');
    }

    /**
     * Get data for Bootstrap Table.
     */
    public function getData(Request $request)
    {
        $query = Role::query();

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $roles = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'total' => $total,
            'rows' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
                'guard_name' => 'web',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rôle créé avec succès',
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        try {
            $role = Role::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rôle non trouvé',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            if ($role->name === 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le rôle super-admin ne peut pas être modifié',
                ], 403);
            }

            $role->name = $request->name;
            $role->description = $request->description;
            $role->save();

            return response()->json([
                'success' => true,
                'message' => 'Rôle modifié avec succès',
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);

            if ($role->name === 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le rôle super-admin ne peut pas être supprimé',
                ], 403);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rôle supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permissions for a role.
     */
    public function getPermissions($id)
    {
        try {
            $role = Role::findOrFail($id);
            $rolePermissions = $role->permissions->pluck('id')->toArray();

            $permissionsByModule = \Spatie\Permission\Models\Permission::all()
                ->groupBy('module')
                ->map(function ($permissions, $module) use ($rolePermissions) {
                    return [
                        'module' => $module ?: 'Système',
                        'permissions' => $permissions->map(function ($permission) use ($rolePermissions) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'label' => $permission->label ?: $permission->name,
                                'assigned' => in_array($permission->id, $rolePermissions),
                            ];
                        }),
                    ];
                })->values();

            $modules = \Spatie\Permission\Models\Permission::distinct()->pluck('module')->filter()->values();

            return response()->json([
                'success' => true,
                'role_name' => $role->name,
                'permissions_by_module' => $permissionsByModule,
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
     * Toggle a permission for a role.
     */
    public function togglePermission(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            if ($role->name === 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Les permissions du rôle super-admin ne peuvent pas être modifiées',
                ], 403);
            }

            $request->validate([
                'permission_id' => 'required|exists:permissions,id',
            ]);

            $permission = \Spatie\Permission\Models\Permission::findOrFail($request->permission_id);

            if ($role->hasPermissionTo($permission->name)) {
                $role->revokePermissionTo($permission->name);
                $role->logPermissionToggle($permission->name, 'revoked');
                $message = 'Permission révoquée avec succès';
            } else {
                $role->givePermissionTo($permission->name);
                $role->logPermissionToggle($permission->name, 'given');
                $message = 'Permission assignée avec succès';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }
}
