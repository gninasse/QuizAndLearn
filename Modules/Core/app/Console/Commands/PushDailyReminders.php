<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Console\Commands\Concerns\TargetsPushLearners;
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
        // last_activity_date est écrit en heure applicative (GamificationService) :
        // « hier » se calcule donc dans le fuseau applicatif, pas le fuseau push.
        $yesterday = $now->copy()->setTimezone((string) config('app.timezone'))->subDay()->format('Y-m-d');

        if ($local->minute >= 15) {
            return self::SUCCESS;
        }

        $sent = 0;

        if ($local->hour === $settings->dailyHour()) {
            $this->pushableLearners($now)
                ->whereExists(fn ($q) => $this->dueReviews($q->selectRaw('1')->from('flashcard_item_reviews as r'), $now)
                    ->whereColumn('r.learner_id', 'learners.id'))
                ->each(function (Learner $learner) use ($notifier, $now, $today, &$sent) {
                    $due = $this->dueReviews(DB::table('flashcard_item_reviews as r'), $now)
                        ->where('r.learner_id', $learner->id)->count();
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

    /**
     * Révisions échues (alias `r`) dont la carte appartient à un paquet actif
     * encore assigné à l'un des groupes de l'apprenant.
     */
    private function dueReviews(Builder $q, Carbon $now): Builder
    {
        return $q->join('flashcard_items as i', 'i.id', '=', 'r.flashcard_item_id')
            ->join('flashcard_decks as d', 'd.id', '=', 'i.deck_id')
            ->where('d.active', true)
            ->where('r.next_review', '<=', $now)
            ->whereExists(fn ($g) => $g->selectRaw('1')->from('group_flashcard_deck as gd')
                ->join('group_learner as gl', 'gl.group_id', '=', 'gd.group_id')
                ->whereColumn('gd.flashcard_deck_id', 'd.id')
                ->whereColumn('gl.learner_id', 'r.learner_id'));
    }
}
