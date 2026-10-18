<?php

namespace Modules\Core\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Learner;
use Modules\Core\Services\Push\PushSettings;

trait TargetsPushLearners
{
    /**
     * Apprenants pouvant recevoir un push : compte actif, non suspendu,
     * au moins un abonnement (sous-requête sur `push_subscriptions` d'abord).
     *
     * @return Builder<Learner>
     */
    protected function pushableLearners(?Carbon $now = null): Builder
    {
        $now ??= now();

        return Learner::query()
            ->whereHas('pushSubscriptions')
            ->whereHas('user', fn (Builder $q) => $q->where('is_active', true))
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('suspended_at')
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('suspended_until')->where('suspended_until', '<=', $now));
            });
    }

    /** Heure locale (fuseau `push.timezone`). */
    protected function pushLocalNow(): Carbon
    {
        return PushSettings::localNow();
    }

    protected function inQuietHours(Carbon $local): bool
    {
        return PushSettings::inQuietHours($local);
    }
}
