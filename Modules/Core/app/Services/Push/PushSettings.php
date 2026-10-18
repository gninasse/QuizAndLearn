<?php

namespace Modules\Core\Services\Push;

use Modules\Core\Models\AppSetting;

/**
 * Réglages admin des notifications push (`push.*` dans `app_settings`).
 */
class PushSettings
{
    public const TYPES = ['new_content', 'cards_due', 'streak', 'exam_open'];

    /** Source unique : les deux clés VAPID sont présentes (lues à l'exécution). */
    public static function vapidConfigured(): bool
    {
        return filled(config('push.vapid.public_key')) && filled(config('push.vapid.private_key'));
    }

    public function typeEnabled(string $type): bool
    {
        return (bool) AppSetting::get('push.types.'.$type, true);
    }

    public function dailyHour(): int
    {
        $hour = AppSetting::get('push.daily_hour', 18);

        return is_numeric($hour) ? max(0, min(23, (int) $hour)) : 18;
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
