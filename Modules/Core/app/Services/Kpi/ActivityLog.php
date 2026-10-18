<?php

namespace Modules\Core\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Journal d'activité des apprenants : union des 4 horodatages qui comptent
 * comme une « activité » (tentative de quiz terminée, début d'examen,
 * révision de flashcard, accès à un contenu).
 */
final class ActivityLog
{
    /** @var array<string, array{0: string, 1: string}> table => [colonne apprenant, colonne date] */
    private const SOURCES = [
        'quiz_attempts' => ['learner_id', 'completed_at'],
        'exam_attempts' => ['learner_id', 'date_debut'],
        'flashcard_item_reviews' => ['learner_id', 'last_reviewed'],
        'learner_progress' => ['learner_id', 'last_accessed_at'],
    ];

    /**
     * Activités du périmètre dans [from, to] (bornes incluses).
     *
     * @return Collection<int, object{learner_id: int, at: Carbon}>
     */
    public static function events(KpiScope $scope, Carbon $from, Carbon $to): Collection
    {
        if ($scope->learnerIds === []) {
            return collect();
        }

        $union = null;
        foreach (self::SOURCES as $table => [$learnerCol, $dateCol]) {
            $q = DB::table($table)
                ->select($learnerCol.' as learner_id', $dateCol.' as at')
                ->whereNotNull($dateCol)
                ->whereBetween($dateCol, [$from, $to]);
            $scope->applyLearners($q, $learnerCol);
            $union = $union ? $union->unionAll($q) : $q;
        }

        return $union->get()
            ->map(fn ($r) => (object) ['learner_id' => (int) $r->learner_id, 'at' => Carbon::parse($r->at)])
            ->unique(fn ($r) => $r->learner_id.'|'.$r->at->timestamp)
            ->values();
    }

    /**
     * Nombre d'apprenants distincts du périmètre ayant une activité dans
     * [from, to] (ou [from, to[ si $toExclusive). Agrégé en base : aucune
     * ligne d'activité n'est chargée en PHP.
     */
    public static function distinctLearners(KpiScope $scope, Carbon $from, Carbon $to, bool $toExclusive = false): int
    {
        if ($scope->learnerIds === []) {
            return 0;
        }

        $union = null;
        foreach (self::SOURCES as $table => [$learnerCol, $dateCol]) {
            $q = DB::table($table)
                ->select($learnerCol.' as learner_id')
                ->whereNotNull($dateCol)
                ->where($dateCol, '>=', $from)
                ->where($dateCol, $toExclusive ? '<' : '<=', $to);
            $scope->applyLearners($q, $learnerCol);
            $union = $union ? $union->unionAll($q) : $q;
        }

        return (int) DB::query()->fromSub($union, 'activity')
            ->selectRaw('COUNT(DISTINCT learner_id) as n')
            ->value('n');
    }

    /**
     * Dernière activité de chaque apprenant du périmètre (null si aucune),
     * éventuellement limitée aux activités antérieures ou égales à $until.
     *
     * @return array<int, ?Carbon>
     */
    public static function lastActivity(KpiScope $scope, ?Carbon $until = null): array
    {
        return self::aggregate($scope, 'max', $until);
    }

    /**
     * Première activité de chaque apprenant du périmètre ayant une activité.
     *
     * @return array<int, Carbon>
     */
    public static function firstActivity(KpiScope $scope): array
    {
        return array_filter(self::aggregate($scope, 'min'));
    }

    /** @return array<int, ?Carbon> */
    private static function aggregate(KpiScope $scope, string $fn, ?Carbon $until = null): array
    {
        $result = [];
        $learners = $scope->applyLearners(DB::table('learners'), 'id')->pluck('id');
        foreach ($learners as $id) {
            $result[(int) $id] = null;
        }
        if ($result === []) {
            return [];
        }

        foreach (self::SOURCES as $table => [$learnerCol, $dateCol]) {
            /** @var Builder $q */
            $q = DB::table($table)
                ->selectRaw("{$learnerCol} as learner_id, {$fn}({$dateCol}) as at")
                ->whereNotNull($dateCol)
                ->when($until, fn ($w) => $w->where($dateCol, '<=', $until))
                ->groupBy($learnerCol);
            $scope->applyLearners($q, $learnerCol);

            foreach ($q->get() as $row) {
                $id = (int) $row->learner_id;
                $at = Carbon::parse($row->at);
                $cur = $result[$id] ?? null;
                if ($cur === null || ($fn === 'max' ? $at->gt($cur) : $at->lt($cur))) {
                    $result[$id] = $at;
                }
            }
        }

        return $result;
    }
}
