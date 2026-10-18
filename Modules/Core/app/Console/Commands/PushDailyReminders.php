<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Commands\Concerns\TargetsPushLearners;
use Modules\Core\Models\FlashcardItemReview;
use Modules\Core\Models\Learner;
use Modules\Core\Services\Push\PushMessage;
use Modules\Core\Services\Push\PushNotifier;
use Modules\Core\Services\Push\PushSettings;

class PushDailyReminders extends Command
{
    use TargetsPushLearners;

    protected $signature = 'push:daily-reminders';

    protected $description = 'Rappels quotidiens : cartes à réviser (heure réglable) et série en danger (20 h)';

    public function handle(PushNotifier $notifier, PushSettings $settings): int
    {
        $now = now();
        $local = $this->pushLocalNow();
        $today = $local->format('Y-m-d');
        // last_activity_date est écrit en heure applicative (GamificationService) ;
        // comparé ici au jour d'hier dans le fuseau push.
        $yesterday = $local->copy()->subDay()->format('Y-m-d');

        if ($local->minute >= 15) {
            return self::SUCCESS;
        }

        $sent = 0;

        if ($local->hour === $settings->dailyHour()) {
            $this->pushableLearners($now)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('flashcard_item_reviews')
                    ->whereColumn('flashcard_item_reviews.learner_id', 'learners.id')
                    ->where('next_review', '<=', $now))
                ->each(function (Learner $learner) use ($notifier, $now, $today, &$sent) {
                    $due = FlashcardItemReview::where('learner_id', $learner->id)->where('next_review', '<=', $now)->count();
                    $sent += (int) $notifier->notify($learner, new PushMessage(
                        'cards_due', 'Révisions du jour', "{$due} carte(s) vous attendent pour réviser.", '/reviser', "cards:{$today}",
                    ), $now);
                });
        }

        if ($local->hour === 20) {
            $this->pushableLearners($now)
                ->join('learner_xp', 'learner_xp.learner_id', '=', 'learners.id')
                ->where('learner_xp.current_streak', '>=', 2)
                ->whereDate('learner_xp.last_activity_date', $yesterday)
                ->select('learners.*', 'learner_xp.current_streak as streak_days')
                ->each(function (Learner $learner) use ($notifier, $now, $today, &$sent) {
                    $sent += (int) $notifier->notify($learner, new PushMessage(
                        'streak', 'Votre série est en danger 🔥',
                        "Votre série de {$learner->streak_days} jours se termine ce soir.", '/', "streak:{$today}",
                    ), $now);
                });
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
