<?php

namespace Modules\Core\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Services\ShowcaseSettings;

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

    /**
     * Tous les indicateurs du tableau de bord, mis en cache 10 minutes.
     * (`$s->cacheKey` porte déjà le préfixe « kpi: ».)
     *
     * @return array<string, mixed>
     */
    public function all(KpiScope $s): array
    {
        return Cache::remember($s->cacheKey, 600, fn () => array_merge(
            $this->engagement($s),
            $this->learning($s),
            $this->followUp($s),
            ['weekly_active' => $this->weeklyActive($s), 'generated_at' => Carbon::now()->toIso8601String()],
        ));
    }

    /**
     * @return array{quiz_avg_score:?float, exam_avg_note:?float, completion:list<array>, failed_questions:list<array>}
     */
    public function learning(KpiScope $s): array
    {
        $quizAvg = $s->applyLearners(DB::table('quiz_attempts'), 'learner_id')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$s->from, $s->to])
            ->avg('score');

        $examAvg = $s->applyLearners(DB::table('exam_attempts'), 'learner_id')
            ->whereIn('status', ['termine', 'time_up'])
            ->whereBetween('date_debut', [$s->from, $s->to])
            ->avg('note_sur_vingt');

        return [
            'quiz_avg_score' => $quizAvg !== null ? round((float) $quizAvg, 1) : null,
            'exam_avg_note' => $examAvg !== null ? round((float) $examAvg, 1) : null,
            'completion' => $this->completion($s),
            'failed_questions' => $this->failedQuestions($s),
        ];
    }

    /**
     * @return array{inactive:list<array>, incidents:array, showcase:?array}
     */
    public function followUp(KpiScope $s): array
    {
        return [
            'inactive' => $this->inactive($s),
            'incidents' => $this->incidents($s),
            'showcase' => $this->showcase($s),
        ];
    }

    /** @return list<array{type:string,id:int,title:string,assigned:int,completed:int,rate:float}> */
    private function completion(KpiScope $s): array
    {
        if ($s->learnerIds === []) {
            return [];
        }

        $defs = [
            ['quiz', 'group_quiz', 'quiz_id', 'quizzes', 'title'],
            ['article', 'group_article', 'article_id', 'articles', 'title'],
            ['deck', 'group_flashcard_deck', 'flashcard_deck_id', 'flashcard_decks', 'titre'],
        ];

        $rows = [];
        foreach ($defs as [$type, $pivot, $fk, $table, $titleCol]) {
            $q = DB::table($pivot)->select('group_id', $fk.' as content_id');
            if ($s->groupIds !== []) {
                $q->whereIn('group_id', $s->groupIds);
            }
            $assignments = $q->get();
            if ($assignments->isEmpty()) {
                continue;
            }

            // apprenants (distincts) de chaque groupe concerné
            $members = DB::table('group_learner')
                ->whereIn('group_id', $assignments->pluck('group_id')->unique()->all())
                ->get()->groupBy('group_id');

            $assigned = [];
            foreach ($assignments as $a) {
                foreach ($members[$a->group_id] ?? [] as $m) {
                    $assigned[(int) $a->content_id][(int) $m->learner_id] = true;
                }
            }

            $contentIds = array_keys($assigned);
            $titles = DB::table($table)->whereIn('id', $contentIds)->pluck($titleCol, 'id');
            $done = $this->completedBy($type, $contentIds);

            foreach ($assigned as $contentId => $learners) {
                $learnerIds = array_keys($learners);
                $completed = count(array_intersect_key($learners, $done[$contentId] ?? []));
                $title = trim((string) ($titles[$contentId] ?? ''));
                $rows[] = [
                    'type' => $type,
                    'id' => $contentId,
                    'title' => $title !== '' ? $title : '—',
                    'assigned' => count($learnerIds),
                    'completed' => $completed,
                    'rate' => round($completed / count($learnerIds) * 100, 1),
                ];
            }
        }

        usort($rows, fn ($a, $b) => $a['rate'] <=> $b['rate']);

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  array<int>  $contentIds
     * @return array<int, array<int, true>> content_id => [learner_id => true]
     */
    private function completedBy(string $type, array $contentIds): array
    {
        $out = [];
        if ($type === 'deck') {
            $rows = DB::table('flashcard_item_reviews as r')
                ->join('flashcard_items as i', 'i.id', '=', 'r.flashcard_item_id')
                ->whereIn('i.deck_id', $contentIds)
                ->select('i.deck_id as content_id', 'r.learner_id')->distinct()->get();
        } else {
            $rows = DB::table('learner_progress')
                ->where('content_type', $type)->where('status', 'completed')
                ->whereIn('content_id', $contentIds)
                ->select('content_id', 'learner_id')->get();
        }
        foreach ($rows as $r) {
            $out[(int) $r->content_id][(int) $r->learner_id] = true;
        }

        return $out;
    }

    /** @return list<array{question_id:int,quiz_title:string,label:string,answers:int,fail_rate:float}> */
    private function failedQuestions(KpiScope $s): array
    {
        $q = DB::table('quiz_answers as a')
            ->join('quiz_attempts as t', 't.id', '=', 'a.attempt_id')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->join('quizzes as z', 'z.id', '=', 'q.quiz_id')
            ->whereBetween('t.completed_at', [$s->from, $s->to])
            ->groupBy('q.id', 'q.question_text', 'z.title')
            ->havingRaw('COUNT(*) >= 5')
            ->selectRaw('q.id as question_id, q.question_text, z.title as quiz_title, COUNT(*) as answers, SUM(CASE WHEN a.is_correct THEN 0 ELSE 1 END) as failed');
        $s->applyLearners($q, 't.learner_id');

        return $q->get()
            ->map(fn ($r) => [
                'question_id' => (int) $r->question_id,
                'quiz_title' => (string) $r->quiz_title,
                'label' => Str::limit(trim(strip_tags((string) $r->question_text)), 120),
                'answers' => (int) $r->answers,
                'fail_rate' => round((int) $r->failed / (int) $r->answers * 100, 1),
            ])
            ->sort(fn ($a, $b) => [$b['fail_rate'], $b['answers']] <=> [$a['fail_rate'], $a['answers']])
            ->take(10)->values()->all();
    }

    /** @return list<array{learner_id:int,name:string,matricule:?string,last_activity:?string,group_id:?int}> */
    private function inactive(KpiScope $s): array
    {
        if ($s->learnerIds === []) {
            return [];
        }

        $q = DB::table('learners')
            ->join('users', 'users.id', '=', 'learners.user_id')
            ->where('users.is_active', true)
            ->where(fn ($w) => $w->whereNull('learners.suspended_at')
                ->orWhere(fn ($e) => $e->whereNotNull('learners.suspended_until')
                    ->where('learners.suspended_until', '<=', Carbon::now())))
            ->select('learners.id', 'learners.matricule', 'users.name', 'users.last_name');
        $learners = $s->applyLearners($q, 'learners.id')->get();

        $threshold = $s->to->copy()->startOfDay()->subDays(6);
        $last = ActivityLog::lastActivity($s);

        $rows = [];
        foreach ($learners as $l) {
            $at = $last[(int) $l->id] ?? null;
            if ($at === null || $at->lt($threshold)) {
                $rows[] = ['l' => $l, 'at' => $at];
            }
        }
        usort($rows, fn ($a, $b) => ($a['at']?->timestamp ?? PHP_INT_MIN) <=> ($b['at']?->timestamp ?? PHP_INT_MIN));
        $rows = array_slice($rows, 0, 50);

        $gq = DB::table('group_learner')
            ->whereIn('learner_id', array_map(fn ($r) => (int) $r['l']->id, $rows))
            ->orderBy('group_id');
        if ($s->groupIds !== []) {
            $gq->whereIn('group_id', $s->groupIds);
        }
        $firstGroup = [];
        foreach ($gq->get() as $g) {
            $firstGroup[(int) $g->learner_id] ??= (int) $g->group_id;
        }

        return array_map(fn ($r) => [
            'learner_id' => (int) $r['l']->id,
            'name' => trim($r['l']->name.' '.$r['l']->last_name),
            'matricule' => $r['l']->matricule,
            'last_activity' => $r['at']?->toIso8601String(),
            'group_id' => $firstGroup[(int) $r['l']->id] ?? null,
        ], $rows);
    }

    /** @return array{screenshots:int, exam_captures:int, exam_navigation:int, exams_cancelled:int} */
    private function incidents(KpiScope $s): array
    {
        $screens = $s->applyLearners(DB::table('screenshot_attempts'), 'learner_id')
            ->whereBetween('detected_at', [$s->from, $s->to])->count();

        $exams = $s->applyLearners(DB::table('exam_attempts'), 'learner_id')
            ->whereBetween('date_debut', [$s->from, $s->to]);

        return [
            'screenshots' => $screens,
            'exam_captures' => (int) (clone $exams)->sum('capture_attempts'),
            'exam_navigation' => (int) (clone $exams)->sum('navigation_violations'),
            'exams_cancelled' => (clone $exams)->where('status', 'annule')->count(),
        ];
    }

    /** @return array{requests:int, converted:int, rate:?float}|null */
    private function showcase(KpiScope $s): ?array
    {
        if (! $s->isAdmin || ! app(ShowcaseSettings::class)->enabled()) {
            return null;
        }

        $base = DB::table('access_requests')->whereBetween('created_at', [$s->from, $s->to]);
        $requests = (clone $base)->count();
        $converted = (clone $base)->where('status', 'account_created')->count();

        return ['requests' => $requests, 'converted' => $converted, 'rate' => $this->percent($converted, $requests)];
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
