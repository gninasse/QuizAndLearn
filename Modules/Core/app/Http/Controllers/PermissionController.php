<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Display the permissions matrix.
     */
    public function index(Request $request)
    {
        // Get filters from query
        $selectedRolesIds = $request->get('roles', []);
        $selectedModules = $request->get('modules', []);

        // Default: 10 first roles and 'Core' module if nothing selected
        if (empty($selectedRolesIds)) {
            $selectedRolesIds = Role::orderBy('id')->limit(10)->pluck('id')->toArray();
        }

        if (empty($selectedModules)) {
            $selectedModules = ['core'];
        }

        // Fetch data for the matrix
        $roles = Role::whereIn('id', $selectedRolesIds)->orderBy('id')->get();
        $permissions = Permission::whereIn('module', $selectedModules)->orderBy('id')->get();

        // Fetch metadata for the configuration modal
        $allRoles = Role::orderBy('name')->get();
        $allModules = Permission::distinct()->pluck('module')->filter()->values()->toArray();

        return view('core::permissions.index', compact(
            'roles',
            'permissions',
            'allRoles',
            'allModules',
            'selectedRolesIds',
            'selectedModules'
        ));
    }

    /**
     * Toggle a permission for a role via AJAX.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permission_id' => 'required|exists:permissions,id',
            'attach' => 'required|boolean',
        ]);

        try {
            $role = Role::findById($request->role_id);
            $permission = Permission::findById($request->permission_id);

            if ($role->name === 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Les permissions du rôle super-admin ne peuvent pas être modifiées',
                ], 403);
            }

            if ($request->attach) {
                $role->givePermissionTo($permission);
                $message = 'Permission accordée';
            } else {
                $role->revokePermissionTo($permission);
                $message = 'Permission révoquée';
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
