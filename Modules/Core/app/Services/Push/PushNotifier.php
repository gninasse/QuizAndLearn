<?php

namespace Modules\Core\Services\Push;

use Illuminate\Support\Carbon;
use Modules\Core\Jobs\SendPushNotification;
use Modules\Core\Models\Learner;
use Modules\Core\Models\PushDelivery;

class PushNotifier
{
    public function __construct(private PushSettings $settings) {}

    /**
     * Envoie `$m` à tous les navigateurs de l'apprenant, sauf règle bloquante
     * (clés, réglage admin, abonnement, doublon, plage silencieuse, plafond,
     * préférence apprenant). Renvoie true si au moins un envoi a été mis en file.
     */
    public function notify(Learner $learner, PushMessage $m, ?Carbon $now = null): bool
    {
        if (! PushSettings::vapidConfigured() || ! $this->settings->typeEnabled($m->type)) {
            return false;
        }

        $subscriptions = $learner->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return false;
        }

        if ($m->ref !== null && PushDelivery::where('learner_id', $learner->id)
            ->where('type', $m->type)->where('ref', $m->ref)->exists()) {
            return false;
        }

        if ($this->blockedByPreference($learner, $m)) {
            return false;
        }

        $tz = (string) config('push.timezone');
        $local = ($now ?? Carbon::now())->copy()->setTimezone($tz);

        if ($m->type !== 'exam_open') {
            if ($local->hour >= 21 || $local->hour < 7) {
                return false;
            }

            $sentToday = PushDelivery::where('learner_id', $learner->id)
                ->whereBetween('sent_at', [$local->copy()->startOfDay(), $local->copy()->endOfDay()])
                ->count();
            if ($sentToday >= $this->settings->dailyCap()) {
                return false;
            }
        }

        PushDelivery::create([
            'learner_id' => $learner->id,
            'type' => $m->type,
            'ref' => $m->ref,
            'sent_at' => $local->copy(),
        ]);

        foreach ($subscriptions as $subscription) {
            SendPushNotification::dispatch($subscription, $m);
        }

        return true;
    }

    private function blockedByPreference(Learner $learner, PushMessage $m): bool
    {
        if ($m->type !== 'new_content' || $m->ref === null) {
            return false;
        }

        $key = match (strstr($m->ref, ':', true)) {
            'quiz' => 'new_quiz',
            'article' => 'new_article',
            default => null,
        };
        if ($key === null) {
            return false;
        }

        $prefs = $learner->preferences?->notifications_enabled;

        return is_array($prefs) && ($prefs[$key] ?? true) === false;
    }
}
