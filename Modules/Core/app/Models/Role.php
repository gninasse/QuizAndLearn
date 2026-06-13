<?php

namespace Modules\Core\Models;

use Modules\Core\Traits\LogsActivityWithModule;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivityWithModule;

    protected static $activityModule = 'core';

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    protected static $logAttributes = ['name', 'description', 'guard_name'];

    protected static $logOnlyDirty = true;

    /**
     * Logger l'assignation de permissions
     */
    public function logPermissionSync(array $permissions, string $action = 'synced')
    {
        activity('permissions')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'permissions' => $permissions,
                'action' => $action,
            ])
            ->tap(function ($activity) use ($action) {
                $activity->module = 'core';
                $activity->description = 'permissions_'.$action;
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log("permissions_{$action}");
    }

    public function logPermissionToggle(string $permission, string $action = 'given')
    {
        activity('permissions')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'permission' => $permission,
                'action' => $action,
            ])
            ->tap(function ($activity) use ($action) {
                $activity->module = 'core';
                $activity->description = 'permission_'.$action;
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log("permission_{$action}");
    }
}
