<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\Module;
use Modules\Core\Services\ModuleService;
use Nwidart\Modules\Facades\Module as ModuleFacade;

class ModuleController extends Controller
{
    protected $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Liste tous les modules
     */
    public function index()
    {
        $modules = Module::orderBy('id')->get(); // Changed sort_order to id as sort_order might not exist yet

        // Ajouter les modules détectés mais non enregistrés en base
        $detectedModules = $this->moduleService->getDetectedModules();

        return view('core::modules.index', compact('modules', 'detectedModules'));
    }

    /**
     * Afficher les détails d'un module
     */
    public function show($slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        $module->load('permissions');

        $moduleInfo = ModuleFacade::find($module->slug);

        $stats = [
            'permissions_count' => $module->permissions()->count(),
            'users_count' => $module->users_count,
            'dependencies' => $module->dependencies ?? [],
        ];

        return view('core::modules.show', compact('module', 'moduleInfo', 'stats'));
    }

    /**
     * Activer un module
     */
    public function enable($slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        try {
            $this->moduleService->enableModule($module);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Le module '{$module->name}' a été activé avec succès.",
                ]);
            }

            return redirect()
                ->route('cores.modules.index')
                ->with('success', "Le module '{$module->name}' a été activé avec succès.");
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Erreur lors de l'activation : {$e->getMessage()}",
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', "Erreur lors de l'activation : {$e->getMessage()}");
        }
    }

    /**
     * Désactiver un module
     */
    public function disable($slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        try {
            $this->moduleService->disableModule($module);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Le module '{$module->name}' a été désactivé.",
                ]);
            }

            return redirect()
                ->route('cores.modules.index')
                ->with('success', "Le module '{$module->name}' a été désactivé.");
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Erreur lors de la désactivation : {$e->getMessage()}",
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', "Erreur lors de la désactivation : {$e->getMessage()}");
        }
    }

    /**
     * Installer un nouveau module
     */
    public function install(Request $request)
    {
        $request->validate([
            'module_slug' => 'required|string',
        ]);

        try {
            $module = $this->moduleService->installModule($request->module_slug);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Le module '{$module->name}' a été installé avec succès.",
                    'redirect' => route('cores.modules.show', $module->slug),
                ]);
            }

            return redirect()
                ->route('cores.modules.show', $module->slug)
                ->with('success', "Le module '{$module->name}' a été installé avec succès.");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Erreur lors de l'installation : {$e->getMessage()}",
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', "Erreur lors de l'installation : {$e->getMessage()}");
        }
    }

    /**
     * Désinstaller un module
     */
    public function uninstall($slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        if ($module->is_required) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce module est requis et ne peut pas être désinstallé.',
                ], 403);
            }

            return redirect()
                ->back()
                ->with('error', 'Ce module est requis et ne peut pas être désinstallé.');
        }

        try {
            $moduleName = $module->name;
            $this->moduleService->uninstallModule($module);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Le module '{$moduleName}' a été désinstallé.",
                ]);
            }

            return redirect()
                ->route('cores.modules.index')
                ->with('success', "Le module '{$moduleName}' a été désinstallé.");
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Erreur lors de la désinstallation : {$e->getMessage()}",
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', "Erreur lors de la désinstallation : {$e->getMessage()}");
        }
    }

    /**
     * Afficher la configuration d'un module
     */
    public function configure($slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        $config = $module->config ?? [];

        // Charger le fichier de configuration du module si disponible
        $configFile = module_path($module->slug, 'Config/config.php');
        $defaultConfig = file_exists($configFile) ? require $configFile : [];

        return view('core::modules.configure', compact('module', 'config', 'defaultConfig'));
    }

    /**
     * Mettre à jour la configuration d'un module
     */
    public function updateConfiguration(Request $request, $slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        // $module->config = $request->except('_token', '_method'); // Assuming config column exists or is handled
        // $module->save();

        return redirect()
            ->route('cores.modules.show', $module->slug)
            ->with('success', 'Configuration mise à jour avec succès.');
    }

    /**
     * Sync permissions
     */
    public function syncPermissions()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('core:sync-permissions');

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Permissions synchronisées avec succès.',
                ]);
            }

            return back()->with('success', 'Permissions synchronisées avec succès.');
        } catch (\Exception $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la synchronisation : '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Erreur lors de la synchronisation : '.$e->getMessage());
        }
    }
}
