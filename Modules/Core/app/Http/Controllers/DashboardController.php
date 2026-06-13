<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stats = [
            'users_count' => \Modules\Core\Models\User::count(),
            'roles_count' => \Spatie\Permission\Models\Role::count(),
            'modules_count' => \Modules\Core\Models\Module::count(),
            'activities_today' => \Modules\Core\Models\Activity::whereDate('created_at', today())->count(),
        ];

        $recentActivities = \Modules\Core\Models\Activity::with('causer')
            ->latest()
            ->limit(10)
            ->get();

        return view('core::dashboard.index', compact('stats', 'recentActivities'));
    }
}
