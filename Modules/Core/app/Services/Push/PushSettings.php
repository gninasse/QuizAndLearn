<?php

namespace Modules\Core\Services\Push;

use Illuminate\Support\Carbon;
use Modules\Core\Models\AppSetting;

/**
 * Réglages admin des notifications push (`push.*` dans `app_settings`).
 */
class PushSettings
{
    public const TYPES = ['new_content', 'cards_due', 'streak', 'exam_open'];

    /** Plage silencieuse : de QUIET_START h à QUIET_END h (heure push.timezone). */
    public const QUIET_START = 21;

    public const QUIET_END = 7;

    /** Bornes de l'heure du rappel quotidien (hors plage silencieuse). */
    public const DAILY_HOUR_MIN = 7;

    public const DAILY_HOUR_MAX = 20;

    /**
     * Source unique : clés VAPID et sujet présents (lus à l'exécution). Sans
     * sujet, la bibliothèque refuse d'envoyer : la fonctionnalité reste inerte.
     */
    public static function vapidConfigured(): bool
    {
        return filled(config('push.vapid.public_key'))
            && filled(config('push.vapid.private_key'))
            && filled(config('push.vapid.subject'));
    }

    /** Fuseau des envois ; une valeur vide retombe sur le fuseau applicatif. */
    public static function timezone(): string
    {
        return (string) (config('push.timezone') ?: config('app.timezone') ?: 'UTC');
    }

    /** Heure courante dans le fuseau push. */
    public static function localNow(?Carbon $now = null): Carbon
    {
        return ($now ?? Carbon::now())->copy()->setTimezone(self::timezone());
    }

    public static function inQuietHours(Carbon $local): bool
    {
        return $local->hour >= self::QUIET_START || $local->hour < self::QUIET_END;
    }

    public function typeEnabled(string $type): bool
    {
        return (bool) AppSetting::get('push.types.'.$type, true);
    }

    public function dailyHour(): int
    {
        $hour = AppSetting::get('push.daily_hour', 18);

        return is_numeric($hour) ? max(self::DAILY_HOUR_MIN, min(self::DAILY_HOUR_MAX, (int) $hour)) : 18;
    }

    public function dailyCap(): int
    {
        $cap = AppSetting::get('push.daily_cap', 2);

        return is_numeric($cap) ? max(1, (int) $cap) : 2;
    }

    /**
     * @param  array{types?: array<string, mixed>, daily_hour?: mixed, daily_cap?: mixed}  $validated
     */
    public function save(array $validated): void
    {
        foreach (self::TYPES as $type) {
            AppSetting::put('push.types.'.$type, (bool) ($validated['types'][$type] ?? false));
        }
        if (isset($validated['daily_hour'])) {
            AppSetting::put('push.daily_hour', (int) $validated['daily_hour']);
        }
        if (isset($validated['daily_cap'])) {
            AppSetting::put('push.daily_cap', (int) $validated['daily_cap']);
        }
    }

    /**
     * @return array{types: array<string, bool>, daily_hour: int, daily_cap: int}
     */
    public function toArray(): array
    {
        $types = [];
        foreach (self::TYPES as $type) {
            $types[$type] = $this->typeEnabled($type);
        }

        return ['types' => $types, 'daily_hour' => $this->dailyHour(), 'daily_cap' => $this->dailyCap()];
    }
}
