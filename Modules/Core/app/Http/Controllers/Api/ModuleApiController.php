<?php

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Models\Module;
use Modules\Core\Services\ModuleService;

class ModuleApiController extends Controller
{
    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Liste des modules
     */
    public function index(): JsonResponse
    {
        $modules = Module::orderBy('id')->get(); // Adjusted to use 'id' as 'sort_order' might not exist

        return response()->json($modules);
    }

    /**
     * Afficher un module
     */
    public function show($slug): JsonResponse
    {
        $module = Module::where('slug', $slug)->firstOrFail();

        return response()->json([
            'module' => $module,
            'permissions' => $module->permissions, // Relationship call without brackets returns collection
            'stats' => [
                'permissions_count' => $module->permissions()->count(),
                'users_count' => $module->users_count, // Check if this attribute exists or needs loading
            ],
        ]);
    }

    /**
     * Activer un module
     */
    public function enable($slug): JsonResponse
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        try {
            $this->moduleService->enableModule($module);

            return response()->json([
                'message' => 'Module activé',
                'module' => $module->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Désactiver un module
     */
    public function disable($slug): JsonResponse
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        try {
            $this->moduleService->disableModule($module);

            return response()->json([
                'message' => 'Module désactivé',
                'module' => $module->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
