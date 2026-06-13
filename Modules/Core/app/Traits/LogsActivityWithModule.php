<?php

namespace Modules\Core\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsActivityWithModule
{
    use LogsActivity;

    /**
     * Module auquel appartient ce modèle
     */
    protected static $activityModule = 'core';

    protected static $activityRetentionMonths = 12;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(static::$activityModule);
    }

    /**
     * Taper les événements pour ajouter le module
     */
    protected static function bootLogsActivityWithModule()
    {
        static::eventsToBeRecorded()->each(function ($eventName) {
            static::$eventName(function ($model) {
                if (method_exists($model, 'tapActivity')) {
                    return;
                }
            });
        });
    }

    public function tapActivity($activity, string $eventName)
    {
        $activity->module = static::$activityModule;
        $activity->context = [
            'route' => request()->route()?->getName(),
            'method' => request()->method(),
            'url' => request()->fullUrl(),
        ];
        $activity->ip_address = request()->ip();
        $activity->user_agent = request()->userAgent();
        $activity->retention_months = static::$activityRetentionMonths;
        if (is_int(static::$activityRetentionMonths)) {
            $activity->expires_at = now()->addMonths(static::$activityRetentionMonths);
        }
        $activity->causer_roles = auth()->user()?->roles()->pluck('name')->toArray();
    }
}
