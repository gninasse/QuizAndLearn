<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\LogsActivityWithModule;

class Module extends Model
{
    use HasFactory, LogsActivityWithModule, SoftDeletes{
        tapActivity as tapActivityLogsActivityWithModule;
    }

    protected static $activityModule = 'core';

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    // Attributs sensibles à logger
    protected static $logAttributes = [
        'name', 'slug', 'is_active', 'is_required',
        'dependencies', 'version',
    ];

    protected static $logOnlyDirty = true;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'is_active',
        'is_required',
        'dependencies',
        'config',
        'icon',
        'sort_order',
        'installed_at',
        'activated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'dependencies' => 'array',
        'config' => 'array',
        'installed_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    /**
     * Scope pour les modules actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les modules requis
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Vérifier si le module peut être désactivé
     */
    public function canBeDeactivated(): bool
    {
        if ($this->is_required) {
            return false;
        }

        // Vérifier si d'autres modules dépendent de celui-ci
        $dependentModules = static::active()
            ->where('id', '!=', $this->id)
            ->get()
            ->filter(function ($module) {
                return in_array($this->slug, $module->dependencies ?? []);
            });

        return $dependentModules->isEmpty();
    }

    /**
     * Activer le module
     */
    public function activate(): bool
    {
        // Vérifier les dépendances
        if ($this->dependencies) {
            foreach ($this->dependencies as $dependency) {
                $dependencyModule = static::where('slug', $dependency)->first();
                if (! $dependencyModule || ! $dependencyModule->is_active) {
                    throw new \Exception("Le module dépendant '{$dependency}' n'est pas actif.");
                }
            }
        }

        $this->is_active = true;
        $this->activated_at = now();

        return $this->save();
    }

    /**
     * Désactiver le module
     */
    public function deactivate(): bool
    {
        if (! $this->canBeDeactivated()) {
            throw new \Exception('Ce module ne peut pas être désactivé.');
        }

        $this->is_active = false;
        $this->activated_at = null;

        return $this->save();
    }

    /**
     * Obtenir les permissions associées au module
     */
    public function permissions()
    {
        return $this->hasMany(\Spatie\Permission\Models\Permission::class, 'module', 'slug');
    }

    /**
     * Obtenir le nombre d'utilisateurs ayant accès au module
     */
    public function getUsersCountAttribute(): int
    {
        $permissions = $this->permissions()->pluck('id');

        return \DB::table('model_has_permissions')
            ->whereIn('permission_id', $permissions)
            ->distinct('model_id')
            ->count();
    }

    // Surcharge de la méthode tapActivity pour ajouter des informations contextuelles
    public function tapActivity($activity, string $eventName)
    {
        // Ajouter des informations contextuelles
        $this->tapActivityLogsActivityWithModule($activity, $eventName);
        if ($eventName === 'updated' && $this->wasChanged('is_active')) {
            $activity->description = $this->is_active ? 'module_activated' : 'module_deactivated';
        }
    }
}
