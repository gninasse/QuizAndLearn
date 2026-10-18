<?php

namespace Modules\Core\Services;

use Illuminate\Support\Str;
use Modules\Core\Models\AppSetting;
use Modules\Core\Support\PhoneNumber;

/**
 * Réglages du mode découverte (vitrine), stockés dans `app_settings`.
 */
class ShowcaseSettings
{
    public const OPERATORS = ['orange_money', 'mtn_momo', 'moov_money', 'wave', 'other'];

    public const ACCESS_CHANNELS = ['form', 'whatsapp', 'phone', 'email', 'link'];

    public const ACCESS_FIELDS = ['whatsapp', 'email', 'organization', 'group', 'message'];

    public const ACCESS_FIELD_MODES = ['hidden', 'optional', 'required'];

    public const ACCESS_CUSTOM_TYPES = ['text', 'textarea', 'select'];

    public const ACCESS_CUSTOM_KEY_PATTERN = '/^cf_[a-z0-9]{6}$/';

    public const ACCESS_LINK_LABEL = 'Ouvrir le lien';

    public const ACCESS_DEFAULTS = [
        'button_label' => 'Demander un accès',
        'title' => 'Demander un accès',
        'intro' => 'Laissez-nous vos coordonnées : notre équipe vous recontacte pour ouvrir votre compte.',
        'confirmation' => 'Demande envoyée.',
    ];

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
        $currency = $this->text('currency');

        return $currency !== null ? strtoupper($currency) : 'XOF';
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
     * Configuration de la demande d'accès, défauts appliqués (= comportement historique).
     *
     * @return array<string, mixed>
     */
    public function accessRequest(): array
    {
        $text = fn (string $key) => $this->text('access.'.$key) ?? self::ACCESS_DEFAULTS[$key];

        $channels = $this->get('access.channels');
        $channels = is_array($channels)
            ? array_values(array_map(fn ($c) => $this->normalizeChannel((array) $c), $channels))
            : [['type' => 'form']];

        $stored = $this->get('access.fields');
        $stored = is_array($stored) ? $stored : [];
        $fields = [];
        foreach (self::ACCESS_FIELDS as $name) {
            $mode = $stored[$name] ?? null;
            $fields[$name] = in_array($mode, self::ACCESS_FIELD_MODES, true) ? $mode : 'optional';
        }

        $custom = $this->get('access.custom_fields');

        return [
            'enabled' => (bool) $this->get('access.enabled', true),
            'button_label' => $text('button_label'),
            'title' => $text('title'),
            'intro' => $text('intro'),
            'confirmation' => $text('confirmation'),
            'channels' => $channels,
            'fields' => $fields,
            'custom_fields' => is_array($custom) ? array_values($custom) : [],
        ];
    }

    /**
     * Les demandes d'accès sont ouvertes : mode découverte actif, demandes activées et canal formulaire présent.
     */
    public function accessRequestsOpen(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $access = $this->accessRequest();

        return $access['enabled'] && in_array('form', array_column($access['channels'], 'type'), true);
    }

    /**
     * Textes facultatifs d'un canal jamais nuls (les chaînes vides arrivent à null) :
     * message / objet vides = '', libellé de lien vide = libellé par défaut.
     *
     * @param  array<string, mixed>  $channel
     * @return array<string, mixed>
     */
    private function normalizeChannel(array $channel): array
    {
        $str = fn ($v) => is_string($v) ? trim($v) : '';

        return match ($channel['type'] ?? null) {
            'whatsapp' => ['type' => 'whatsapp', 'number' => $channel['number'] ?? null, 'message' => $str($channel['message'] ?? null)],
            'email' => ['type' => 'email', 'address' => $channel['address'] ?? null, 'subject' => $str($channel['subject'] ?? null)],
            'link' => ['type' => 'link', 'url' => $channel['url'] ?? null, 'label' => $str($channel['label'] ?? null) !== '' ? $str($channel['label']) : self::ACCESS_LINK_LABEL],
            default => $channel,
        };
    }

    /**
     * Enregistre la configuration de la demande d'accès (déjà validée) en la normalisant.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveAccessRequest(array $validated): void
    {
        $country = $this->defaultCountryCode();
        $number = fn ($raw) => PhoneNumber::normalize((string) $raw, $country);

        $channels = [];
        foreach ($validated['channels'] ?? [] as $channel) {
            $channels[] = $this->normalizeChannel(match ($channel['type']) {
                'whatsapp' => ['type' => 'whatsapp', 'number' => $number($channel['number'] ?? null), 'message' => $channel['message'] ?? null],
                'phone' => ['type' => 'phone', 'number' => $number($channel['number'] ?? null)],
                'email' => ['type' => 'email', 'address' => $channel['address'] ?? null, 'subject' => $channel['subject'] ?? null],
                'link' => ['type' => 'link', 'url' => $channel['url'] ?? null, 'label' => $channel['label'] ?? null],
                default => ['type' => 'form'],
            });
        }

        $fields = [];
        foreach (self::ACCESS_FIELDS as $name) {
            $fields[$name] = $validated['fields'][$name] ?? 'optional';
        }

        $custom = [];
        $used = [];
        foreach ($validated['custom_fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || ! preg_match(self::ACCESS_CUSTOM_KEY_PATTERN, $key) || in_array($key, $used, true)) {
                do {
                    $key = 'cf_'.Str::lower(Str::random(6));
                } while (! preg_match(self::ACCESS_CUSTOM_KEY_PATTERN, $key) || in_array($key, $used, true));
            }
            $used[] = $key;

            $entry = ['key' => $key, 'label' => $field['label'], 'type' => $field['type']];
            if ($field['type'] === 'select') {
                $entry['options'] = array_values($field['options'] ?? []);
            }
            $entry['required'] = (bool) ($field['required'] ?? false);
            $custom[] = $entry;
        }

        $values = [
            'enabled' => (bool) ($validated['enabled'] ?? true),
            'button_label' => $validated['button_label'] ?? null,
            'title' => $validated['title'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'confirmation' => $validated['confirmation'] ?? null,
            'channels' => $channels,
            'fields' => $fields,
            'custom_fields' => $custom,
        ];

        foreach ($values as $key => $value) {
            AppSetting::put('showcase.access.'.$key, $value);
        }
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
            'access_request' => $this->accessRequest(),
            'privacy_url' => $this->privacyUrl() ?? '/confidentialite',
            'video' => app(\Modules\Core\Services\Video\VideoSettings::class)->toArray(),
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
            'currency' => isset($validated['currency']) ? strtoupper(trim((string) $validated['currency'])) : null,
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
