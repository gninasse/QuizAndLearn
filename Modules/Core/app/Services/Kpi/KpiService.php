<?php

namespace Modules\Core\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calcul des indicateurs du tableau de bord (voir les définitions du plan).
 */
class KpiService
{
    /**
     * @return array{
     *   active_7d: array{value:int, trend:int},
     *   active_30d: array{value:int, trend:int},
     *   activation_rate: ?float,
     *   retention_30d: ?float,
     *   review_regularity: ?float
     * }
     */
    public function engagement(KpiScope $s): array
    {
        return [
            'active_7d' => $this->activeWindow($s, 7),
            'active_30d' => $this->activeWindow($s, 30),
            'activation_rate' => $this->activationRate($s),
            'retention_30d' => $this->retention30d($s),
            'review_regularity' => $this->reviewRegularity($s),
        ];
    }

    /** @return array<int, array{week_start:string, active:int}> */
    public function weeklyActive(KpiScope $s): array
    {
        $lastMonday = $s->to->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $firstMonday = $lastMonday->copy()->subWeeks(11);

        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $buckets[$firstMonday->copy()->addWeeks($i)->toDateString()] = [];
        }

        foreach (ActivityLog::events($s, $firstMonday, $s->to) as $e) {
            $key = $e->at->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            if (array_key_exists($key, $buckets)) {
                $buckets[$key][$e->learner_id] = true;
            }
        }

        $out = [];
        foreach ($buckets as $weekStart => $learners) {
            $out[] = ['week_start' => $weekStart, 'active' => count($learners)];
        }

        return $out;
    }

    /** @return array{value:int, trend:int} */
    private function activeWindow(KpiScope $s, int $days): array
    {
        $start = $s->to->copy()->startOfDay()->subDays($days - 1);
        $prevStart = $start->copy()->subDays($days);
        $prevEnd = $start->copy()->subSecond();

        $events = ActivityLog::events($s, $prevStart, $s->to);

        $value = $events->filter(fn ($e) => $e->at->gte($start))->pluck('learner_id')->unique()->count();
        $trend = $events->filter(fn ($e) => $e->at->lte($prevEnd))->pluck('learner_id')->unique()->count();

        return ['value' => $value, 'trend' => $trend];
    }

    private function activationRate(KpiScope $s): ?float
    {
        $q = DB::table('learners')
            ->whereBetween('created_at', [$s->from, $s->to])
            ->select('id', 'created_at');
        $created = $s->applyLearners($q, 'id')->get();

        if ($created->isEmpty()) {
            return null;
        }

        $byLearner = ActivityLog::events($s, $s->from, $s->to->copy()->addDays(7))->groupBy('learner_id');

        $activated = $created->filter(function ($l) use ($byLearner) {
            $start = Carbon::parse($l->created_at);
            $end = $start->copy()->addDays(7);

            return ($byLearner[(int) $l->id] ?? collect())
                ->contains(fn ($e) => $e->at->gte($start) && $e->at->lte($end));
        })->count();

        return $this->percent($activated, $created->count());
    }

    private function retention30d(KpiScope $s): ?float
    {
        $limit = $s->to->copy()->subDays(30);
        $cohort = array_filter(ActivityLog::firstActivity($s), fn (Carbon $first) => $first->lte($limit));

        if ($cohort === []) {
            return null;
        }

        $earliest = min(array_map(fn (Carbon $c) => $c->timestamp, $cohort));
        $byLearner = ActivityLog::events($s, Carbon::createFromTimestamp($earliest)->addDays(30), $s->to)
            ->groupBy('learner_id');

        $retained = 0;
        foreach ($cohort as $id => $first) {
            $threshold = $first->copy()->addDays(30);
            if (($byLearner[$id] ?? collect())->contains(fn ($e) => $e->at->gte($threshold))) {
                $retained++;
            }
        }

        return $this->percent($retained, count($cohort));
    }

    private function reviewRegularity(KpiScope $s): ?float
    {
        $reviewed = $s->applyLearners(DB::table('flashcard_item_reviews'), 'learner_id')
            ->whereBetween('last_reviewed', [$s->from, $s->to])
            ->count();

        $overdue = $s->applyLearners(DB::table('flashcard_item_reviews'), 'learner_id')
            ->where('next_review', '<', $s->to)
            ->where(fn ($q) => $q->whereNull('last_reviewed')->orWhereColumn('last_reviewed', '<', 'next_review'))
            ->count();

        return $this->percent($reviewed, $reviewed + $overdue);
    }

    private function percent(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }
}
