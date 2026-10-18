<?php

namespace Modules\Core\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Group;
use Modules\Core\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Périmètre (apprenants) + fenêtre de temps d'un calcul de KPIs.
 */
final class KpiScope
{
    /**
     * @param  array<int>|null  $learnerIds  null = tous les apprenants
     * @param  array<int>  $groupIds  [] = tous (admin sans filtre)
     */
    private function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?array $learnerIds,
        public readonly array $groupIds,
        public readonly bool $isAdmin,
        public readonly string $cacheKey,
        private readonly string $scopeKey = 'admin',
        private readonly ?int $groupFilter = null,
    ) {}

    public static function for(User $user, string $period = '30', ?string $from = null, ?string $to = null, ?int $groupId = null): KpiScope
    {
        [$start, $end] = self::resolvePeriod($period, $from, $to);

        $isAdmin = (bool) $user->hasAnyRole(['super-admin', 'admin', 'Admin']);

        if ($isAdmin) {
            $scopeKey = 'admin';
            $groupIds = $groupId ? [$groupId] : [];
            $learnerIds = $groupId ? self::learnersOf([$groupId]) : null;
        } else {
            $scopeKey = 'trainer'.$user->id;
            $trainer = $user->trainer;
            $own = $trainer
                ? Group::whereHas('trainers', fn ($q) => $q->where('trainers.id', $trainer->id))
                    ->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];

            if ($groupId !== null && ! in_array($groupId, $own, true)) {
                throw new AccessDeniedHttpException('Groupe hors de votre périmètre.');
            }

            $groupIds = $groupId ? [$groupId] : $own;
            $learnerIds = self::learnersOf($groupIds);
        }

        return new self($start, $end, $learnerIds, $groupIds, $isAdmin,
            self::makeKey($scopeKey, $start, $end, $groupId), $scopeKey, $groupId);
    }

    /** Même périmètre, fenêtre précédente de même longueur. */
    public function previous(): KpiScope
    {
        $days = (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
        $start = $this->from->copy()->subDays($days)->startOfDay();
        $end = $this->from->copy()->subDay()->endOfDay();

        return new self($start, $end, $this->learnerIds, $this->groupIds, $this->isAdmin,
            self::makeKey($this->scopeKey, $start, $end, $this->groupFilter), $this->scopeKey, $this->groupFilter);
    }

    /**
     * @template T of BuilderContract
     *
     * @param  T  $q
     * @return T
     */
    public function applyLearners($q, string $column)
    {
        if ($this->learnerIds !== null) {
            $q->whereIn($column, $this->learnerIds);
        }

        return $q;
    }

    /** @return array<int> */
    private static function learnersOf(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return DB::table('group_learner')
            ->whereIn('group_id', $groupIds)
            ->distinct()
            ->pluck('learner_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private static function makeKey(string $scopeKey, Carbon $from, Carbon $to, ?int $groupId): string
    {
        return sprintf('kpi:%s:%s:%s:g%s', $scopeKey, $from->toDateString(), $to->toDateString(), $groupId ?? 'all');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function resolvePeriod(string $period, ?string $from, ?string $to): array
    {
        $validator = Validator::make(
            ['period' => $period, 'from' => $from, 'to' => $to],
            [
                'period' => ['required', 'in:7,30,90,custom'],
                'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
                'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            ],
        );
        $validator->validate();

        if ($period === 'custom') {
            $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();

            if ($start->diffInDays($end->copy()->startOfDay()) + 1 > 366) {
                throw ValidationException::withMessages(['to' => 'La période ne peut pas dépasser 366 jours.']);
            }

            return [$start, $end];
        }

        $end = Carbon::now()->endOfDay();

        return [$end->copy()->subDays((int) $period - 1)->startOfDay(), $end];
    }
}
