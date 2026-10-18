<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Console\Commands\Concerns\TargetsPushLearners;
use Modules\Core\Models\Exam;
use Modules\Core\Services\Push\PushMessage;
use Modules\Core\Services\Push\PushNotifier;

class PushExamOpen extends Command
{
    use TargetsPushLearners;

    protected $signature = 'push:exam-open';

    protected $description = 'Notifie les apprenants des groupes concernés quand un examen vient de s\'ouvrir';

    public function handle(PushNotifier $notifier): int
    {
        $now = now();
        $sent = 0;

        $exams = Exam::query()
            ->where('is_active', true)
            ->where('available_from', '>', $now->copy()->subDay())
            ->where('available_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>', $now))
            ->get(['id', 'title']);

        foreach ($exams as $exam) {
            $groupIds = DB::table('group_exam')->where('exam_id', $exam->id)->pluck('group_id');
            $learnerIds = DB::table('group_learner')->whereIn('group_id', $groupIds)->pluck('learner_id');

            $this->pushableLearners($now)->whereIn('id', $learnerIds)->each(
                function ($learner) use ($notifier, $exam, $now, &$sent) {
                    $sent += (int) $notifier->notify($learner, new PushMessage(
                        'exam_open', 'Examen ouvert', "L'examen « {$exam->title} » est ouvert.", '/examens', "exam:{$exam->id}",
                    ), $now);
                }
            );
        }

        $this->info("{$sent} notification(s) d'examen envoyée(s).");

        return self::SUCCESS;
    }
}
