<?php

namespace Modules\Core\Models;

use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected $fillable = [
        'log_name',
        'module',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'context',
        'causer_roles',
        'expires_at',
        'retention_months',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'collection',
        'context' => 'array',
        'causer_roles' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Scope pour filtrer par module
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope pour les activités critiques
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('description', [
            'deleted',
            'updated_sensitive',
            'permission_changed',
            'role_changed',
        ]);
    }

    /**
     * Obtenir l'icône selon le type d'activité
     */
    public function getIconAttribute(): string
    {
        return match ($this->description) {
            'created' => 'fa-plus-circle text-success',
            'updated' => 'fa-edit text-warning',
            'deleted' => 'fa-trash text-danger',
            'restored' => 'fa-undo text-info',
            'login' => 'fa-sign-in-alt text-primary',
            'logout' => 'fa-sign-out-alt text-secondary',
            'permission_changed' => 'fa-shield-alt text-warning',
            'role_changed' => 'fa-user-tag text-info',
            default => 'fa-circle text-muted',
        };
    }

    /**
     * Obtenir la couleur du badge
     */
    public function getBadgeColorAttribute(): string
    {
        return match ($this->description) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            'login' => 'primary',
            'logout' => 'secondary',
            default => 'secondary',
        };
    }
}
