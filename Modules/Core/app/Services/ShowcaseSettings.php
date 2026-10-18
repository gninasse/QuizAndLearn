<?php

namespace Modules\Core\Services;

use Modules\Core\Models\AppSetting;

/**
 * Réglages du mode découverte (vitrine), stockés dans `app_settings`.
 */
class ShowcaseSettings
{
    public const OPERATORS = ['orange_money', 'mtn_momo', 'moov_money', 'wave', 'other'];

    private function get(string $key, mixed $default = null): mixed
    {
        return AppSetting::get('showcase.'.$key, $default);
    }

    private function text(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    public function enabled(): bool
    {
        return (bool) $this->get('enabled', false);
    }

    public function tagline(): string
    {
        return $this->text('tagline') ?? (string) config('branding.tagline');
    }

    public function defaultCountryCode(): string
    {
        $code = preg_replace('/\D+/', '', (string) $this->get('default_country_code', '225'));

        return $code !== '' ? $code : '225';
    }

    public function currency(): string
    {
        return $this->text('currency') ?? 'XOF';
    }

    public function fee(): ?int
    {
        $fee = $this->get('fee');

        return is_numeric($fee) ? (int) $fee : null;
    }

    /**
     * @return array<int, array{operator: string, number: string, holder: string}>
     */
    public function paymentAccounts(): array
    {
        $accounts = $this->get('payment_accounts', []);

        return is_array($accounts) ? array_values($accounts) : [];
    }

    public function paymentReference(): ?string
    {
        return $this->text('payment_reference');
    }

    public function instructions(): ?string
    {
        return $this->text('instructions');
    }

    /**
     * @return array<int, string>
     */
    public function notificationEmails(): array
    {
        $emails = $this->get('notification_emails', []);

        return is_array($emails) ? array_values($emails) : [];
    }

    public function privacyText(): ?string
    {
        return $this->text('privacy_text');
    }

    public function privacyUrl(): ?string
    {
        return $this->text('privacy_url');
    }

    public function retentionMonths(): int
    {
        return max(1, (int) $this->get('retention_months', 12));
    }

    /**
     * Sous-ensemble exposé par l'API publique (jamais d'e-mails ni de durée de conservation).
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'enabled' => $this->enabled(),
            'brand' => [
                'name' => config('branding.name'),
                'tagline' => $this->tagline(),
                'logo' => config('branding.logo'),
            ],
            'registration' => [
                'fee' => $this->fee(),
                'currency' => $this->currency(),
                'accounts' => $this->paymentAccounts(),
                'reference' => $this->paymentReference(),
                'instructions' => $this->instructions(),
            ],
            'default_country_code' => $this->defaultCountryCode(),
            'privacy_url' => $this->privacyUrl() ?? '/confidentialite',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(array $validated): void
    {
        $emails = $validated['notification_emails'] ?? [];
        if (is_string($emails)) {
            $emails = preg_split('/[\s,;]+/', $emails, -1, PREG_SPLIT_NO_EMPTY);
        }

        $values = [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'tagline' => $validated['tagline'] ?? null,
            'default_country_code' => $validated['default_country_code'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'fee' => isset($validated['fee']) ? (int) $validated['fee'] : null,
            'payment_accounts' => array_values(array_map(fn ($a) => [
                'operator' => $a['operator'],
                'number' => $a['number'],
                'holder' => $a['holder'],
            ], $validated['payment_accounts'] ?? [])),
            'payment_reference' => $validated['payment_reference'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'notification_emails' => array_values(array_unique($emails)),
            'privacy_text' => $validated['privacy_text'] ?? null,
            'privacy_url' => $validated['privacy_url'] ?? null,
            'retention_months' => (int) ($validated['retention_months'] ?? 12),
        ];

        foreach ($values as $key => $value) {
            AppSetting::put('showcase.'.$key, $value);
        }
    }
}
