<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Models\Activity;
use Modules\Core\Models\Module;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class ActivityController extends Controller
{
    public function index()
    {
        $modules = Module::pluck('name', 'slug');
        $users = User::select(['id', 'name', 'last_name'])->get();
        $roles = Role::pluck('name', 'name');

        $logNames = Activity::distinct()->pluck('log_name');
        $subjectTypes = Activity::distinct()->pluck('subject_type')->filter()->map(function ($type) {
            return [
                'full' => $type,
                'short' => class_basename($type),
            ];
        });

        return view('core::activities.index', compact('modules', 'users', 'roles', 'logNames', 'subjectTypes'));
    }

    public function getData(Request $request)
    {
        $query = Activity::query()
            ->with(['causer'])
            ->latest();

        // Filtrage par module
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        // Filtrage par utilisateur
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id)
                ->where('causer_type', User::class);
        }

        // Filtrage par rôle
        if ($request->filled('role')) {
            $query->whereJsonContains('causer_roles', $request->role);
        }

        // Filtrage par type d'action (description)
        if ($request->filled('action_type')) {
            $query->where('description', $request->action_type);
        }

        // Filtrage par Journal (log_name)
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        // Filtrage par Type de modèle (subject_type)
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filtrage par ID du sujet
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filtrage par IP
        if ($request->filled('ip_address')) {
            $query->where(function ($q) use ($request) {
                $q->where('ip_address', 'like', "%{$request->ip_address}%")
                    ->orWhere('properties->ip', 'like', "%{$request->ip_address}%");
            });
        }

        // Filtrage par Type de Causer (Système vs Utilisateur)
        if ($request->filled('causer_type')) {
            if ($request->causer_type === 'system') {
                $query->whereNull('causer_id');
            } else {
                $query->whereNotNull('causer_id');
            }
        }

        // Filtrage par date
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Recherche (Bootstrap Table standard search)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $activities = $query->offset($offset)->limit($limit)->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'module' => $activity->module,
                'description' => $activity->description,
                'causer_name' => $activity->causer ? $activity->causer->name.' '.$activity->causer->last_name : 'Système',
                'causer_roles' => $activity->causer_roles,
                'subject_type' => class_basename($activity->subject_type),
                'subject_id' => $activity->subject_id,
                'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                'icon' => $activity->icon,
                'badge_color' => $activity->badge_color,
                'properties' => $activity->properties,
            ];
        });

        return response()->json([
            'total' => $total,
            'rows' => $activities,
        ]);
    }

    public function show($id)
    {
        $activity = Activity::with(['causer'])->findOrFail($id);

        return view('core::activities.show', compact('activity'));
    }
}
