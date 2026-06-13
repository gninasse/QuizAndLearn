<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Traits\HasModulePermissions;
use Modules\Core\Traits\LogsActivityWithModule;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CausesActivity, HasFactory, HasModulePermissions, HasRoles, LogsActivityWithModule, Notifiable {
        tapActivity as tapActivityLogsActivityWithModule;
    }

    // protected $table = 'cores_users'; // Removed to use default 'users' table

    protected $fillable = [
        'name',
        'last_name',
        'user_name',
        'email',
        'service',
        'password',
        'is_active',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // activity fields
    protected static $activityModule = 'core';

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    // Ne pas logger les mots de passe
    protected static $logAttributes = [
        'name', 'email', 'email_verified_at',
    ];

    protected static $logOnlyDirty = true;

    protected static $logAttributesToIgnore = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'avatar' => 'string',
        ];
    }

    // Accessor pour le nom complet
    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar && \Illuminate\Support\Facades\Storage::exists($this->avatar)) {
            return \Illuminate\Support\Facades\Storage::url($this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->full_name).'&color=7F9CF5&background=EBF4FF';
    }

    // activity functions

    /**
     * Logger les connexions
     */
    public function logLogin()
    {
        activity('auth')
            ->causedBy($this)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->tap(function ($activity) {
                $activity->module = 'core';
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log('login');
    }

    /**
     * Logger les déconnexions
     */
    public function logLogout()
    {
        activity('auth')
            ->causedBy($this)
            ->tap(function ($activity) {
                $activity->module = 'core';
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log('logout');
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

    public function logRoleToggle(string $role, string $action = 'assigned')
    {
        activity('roles')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'role' => $role,
                'action' => $action,
            ])
            ->tap(function ($activity) use ($action) {
                $activity->module = 'core';
                $activity->description = 'role_'.$action;
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log("role_{$action}");
    }

    public function tapActivity($activity, string $eventName)
    {
        // $activity->module = static::$activityModule;
        // $activity->context = [
        //     'route' => request()->route()?->getName(),
        //     'method' => request()->method(),
        //     'url' => request()->fullUrl(),
        // ];
        // $activity->ip_address = request()->ip();
        // $activity->user_agent = request()->userAgent();
        // $activity->retention_months = static::$activityRetentionMonths;
        // if(is_int(static::$activityRetentionMonths)){
        //     $activity->expires_at = now()->addMonths(static::$activityRetentionMonths);
        // }
        // $activity->causer_roles = auth()->user()->roles()->pluck('name')->toArray();

        $this->tapActivityLogsActivityWithModule($activity, $eventName);

        // Ajouter des informations contextuelles
        if ($eventName === 'updated' && $this->wasChanged('is_active')) {
            $activity->description = $this->is_active ? 'user_activated' : 'user_deactivated';
        }
        if ($eventName === 'updated' && $this->wasChanged('avatar')) {
            $activity->description = 'user_avatar_updated';
        }
    }
}
