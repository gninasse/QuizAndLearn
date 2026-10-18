<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Services\ShowcaseSettings;
use Modules\Core\Support\PhoneNumber;

/**
 * Cochage « Visible en mode découverte » : réservé aux administrateurs.
 */
class ShowcaseAdminController extends Controller
{
    private const MODELS = [
        'quiz' => Quiz::class,
        'article' => Article::class,
        'deck' => FlashcardDeck::class,
    ];

    public function toggle(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin();
        $modelClass = self::MODELS[$type] ?? null;
        abort_if($modelClass === null, 404);

        $validated = $request->validate(['is_showcase' => ['required', 'boolean']]);

        $item = $modelClass::findOrFail($id);
        $item->forceFill(['is_showcase' => (bool) $validated['is_showcase']])->save();

        return response()->json(['success' => true, 'is_showcase' => (bool) $item->is_showcase]);
    }

    public function openForRequests(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate(['open_for_requests' => ['required', 'boolean']]);

        $group = Group::findOrFail($id);
        $group->forceFill(['open_for_requests' => (bool) $validated['open_for_requests']])->save();

        return response()->json(['success' => true, 'open_for_requests' => (bool) $group->open_for_requests]);
    }

    public function settings(ShowcaseSettings $settings): View
    {
        $this->authorizeAdmin();

        return view('core::showcase.settings', [
            'settings' => $settings,
            'hasOpenGroups' => Group::openForRequests()->exists(),
            'operators' => [
                'orange_money' => 'Orange Money',
                'mtn_momo' => 'MTN MoMo',
                'moov_money' => 'Moov Money',
                'wave' => 'Wave',
                'other' => 'Autre',
            ],
        ]);
    }

    public function saveSettings(Request $request, ShowcaseSettings $settings): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'default_country_code' => ['required', 'digits_between:1,4'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'payment_accounts' => ['nullable', 'array', 'max:10'],
            'payment_accounts.*.operator' => ['required', Rule::in(ShowcaseSettings::OPERATORS)],
            'payment_accounts.*.number' => ['required', 'string', 'max:30'],
            'payment_accounts.*.holder' => ['required', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'notification_emails' => ['nullable', 'string', 'max:1000'],
            'privacy_text' => ['nullable', 'string', 'max:20000'],
            'privacy_url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:500'],
            'retention_months' => ['required', 'integer', 'min:1', 'max:120'],
            ...$this->accessRequestRules(),
        ], [
            'access_request.channels.*.url.starts_with' => 'Le lien doit commencer par https://.',
            'access_request.custom_fields.max' => 'Cinq champs personnalisés au maximum.',
            'access_request.custom_fields.*.options.min' => 'Un choix multiple demande au moins 2 choix.',
            'access_request.custom_fields.*.options.max' => 'Un choix multiple accepte 20 choix au maximum.',
        ], [
            'access_request.button_label' => 'libellé du bouton',
            'access_request.title' => 'titre',
            'access_request.intro' => 'introduction',
            'access_request.confirmation' => 'message de confirmation',
            'access_request.channels.*.number' => 'numéro',
            'access_request.channels.*.message' => 'message',
            'access_request.channels.*.address' => 'adresse e-mail',
            'access_request.channels.*.subject' => 'objet',
            'access_request.channels.*.url' => 'lien',
            'access_request.channels.*.label' => 'libellé',
            'access_request.custom_fields.*.label' => 'libellé',
            'access_request.custom_fields.*.options' => 'choix',
            'access_request.custom_fields.*.options.*' => 'choix',
            'default_country_code' => 'indicatif',
            'payment_accounts.*.operator' => 'opérateur',
            'payment_accounts.*.number' => 'numéro',
            'payment_accounts.*.holder' => 'bénéficiaire',
            'currency' => 'devise',
            'privacy_url' => 'lien de confidentialité',
            'retention_months' => 'durée de conservation',
        ]);

        foreach (preg_split('/[\s,;]+/', (string) ($validated['notification_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => "Adresse e-mail invalide : {$email}",
                    'errors' => ['notification_emails' => ["Adresse e-mail invalide : {$email}"]],
                ], 422);
            }
        }

        if (isset($validated['access_request'])) {
            $this->checkAccessChannels($validated['access_request'], (string) $validated['default_country_code']);
        }

        $settings->save($validated);
        if (isset($validated['access_request'])) {
            $settings->saveAccessRequest($validated['access_request']);
        }

        return response()->json(['success' => true, 'message' => 'Réglages enregistrés.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function accessRequestRules(): array
    {
        return [
            'access_request' => ['sometimes', 'array'],
            'access_request.enabled' => ['required_with:access_request', 'boolean'],
            'access_request.button_label' => ['nullable', 'string', 'max:40'],
            'access_request.title' => ['nullable', 'string', 'max:80'],
            'access_request.intro' => ['nullable', 'string', 'max:1000'],
            'access_request.confirmation' => ['nullable', 'string', 'max:500'],
            'access_request.channels' => ['nullable', 'array', 'max:'.count(ShowcaseSettings::ACCESS_CHANNELS)],
            'access_request.channels.*.type' => ['required', Rule::in(ShowcaseSettings::ACCESS_CHANNELS)],
            'access_request.channels.*.number' => ['nullable', 'string', 'max:30'],
            'access_request.channels.*.message' => ['nullable', 'string', 'max:500'],
            'access_request.channels.*.address' => ['required_if:access_request.channels.*.type,email', 'nullable', 'email', 'max:190'],
            'access_request.channels.*.subject' => ['nullable', 'string', 'max:150'],
            'access_request.channels.*.url' => ['required_if:access_request.channels.*.type,link', 'nullable', 'url', 'starts_with:https://', 'max:500'],
            'access_request.channels.*.label' => ['nullable', 'string', 'max:60'],
            'access_request.fields' => ['nullable', 'array'],
            'access_request.fields.*' => ['nullable', Rule::in(ShowcaseSettings::ACCESS_FIELD_MODES)],
            'access_request.custom_fields' => ['nullable', 'array', 'max:5'],
            'access_request.custom_fields.*.key' => ['nullable', 'string', 'max:20'],
            'access_request.custom_fields.*.label' => ['required', 'string', 'max:80'],
            'access_request.custom_fields.*.type' => ['required', Rule::in(ShowcaseSettings::ACCESS_CUSTOM_TYPES)],
            'access_request.custom_fields.*.required' => ['nullable', 'boolean'],
            'access_request.custom_fields.*.options' => ['required_if:access_request.custom_fields.*.type,select', 'nullable', 'array', 'min:2', 'max:20'],
            'access_request.custom_fields.*.options.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Règles croisées : au moins un canal si activé, un seul par type, numéros valides,
     * choix distincts dans chaque liste de choix.
     *
     * @param  array<string, mixed>  $access
     */
    private function checkAccessChannels(array $access, string $country): void
    {
        $channels = $access['channels'] ?? [];
        $errors = [];

        if (! empty($access['enabled']) && $channels === []) {
            $errors['access_request.channels'] = 'Activez au moins un canal de contact.';
        }

        $types = array_column($channels, 'type');
        if (count($types) !== count(array_unique($types))) {
            $errors['access_request.channels'] = 'Chaque type de canal ne peut être utilisé qu\'une fois.';
        }

        foreach ($channels as $i => $channel) {
            if (in_array($channel['type'], ['whatsapp', 'phone'], true)
                && PhoneNumber::normalize($channel['number'] ?? null, $country) === null) {
                $errors["access_request.channels.$i.number"] = 'Numéro de téléphone invalide.';
            }
        }

        foreach ($access['custom_fields'] ?? [] as $i => $field) {
            $options = array_map(fn ($o) => mb_strtolower(trim((string) $o)), $field['options'] ?? []);
            if ($field['type'] === 'select' && count($options) !== count(array_unique($options))) {
                $errors["access_request.custom_fields.$i.options"] = 'Chaque choix ne peut figurer qu\'une fois.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super-admin', 'admin', 'Admin']),
            403,
            'Action non autorisée.'
        );
    }
}
