<?php

namespace Modules\Core\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Modules\Core\Mail\NewAccessRequestMail;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Services\ShowcaseSettings;
use Modules\Core\Support\PhoneNumber;

/**
 * Dépôt public d'une demande d'accès depuis le mode découverte.
 */
class AccessRequestSubmitController extends Controller
{
    private const MIN_FILL_MS = 3000;

    /** Champs de contact complétés (jamais écrasés) lors d'un renvoi. */
    private const CONTACT_FIELDS = ['first_name', 'last_name', 'whatsapp', 'email', 'organization', 'group_id'];

    public const CONSENT_TEXT = 'J\'accepte que mes informations soient utilisées pour traiter ma demande d\'accès, conformément à la politique de confidentialité.';

    public function __construct(
        private readonly ShowcaseSettings $settings,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $this->settings->enabled()) {
            return ShowcaseController::disabledResponse();
        }

        if (! $this->settings->accessRequestsOpen()) {
            return response()->json([
                'success' => false,
                'code' => 'access_requests_closed',
                'message' => 'Les demandes d\'accès sont fermées pour le moment.',
            ], 403);
        }

        $config = $this->settings->accessRequest();
        $modes = $config['fields'];

        $optional = [
            'whatsapp_same' => ['nullable', 'boolean'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('open_for_requests', true)->where('is_active', true)],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
        // Mode de chaque champ réglable ; « group » s'applique à group_id.
        $modeOf = ['whatsapp_same' => 'whatsapp', 'whatsapp' => 'whatsapp', 'email' => 'email', 'organization' => 'organization', 'group_id' => 'group', 'message' => 'message'];

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string'],
            'elapsed_ms' => ['nullable', 'integer', 'min:0'],
        ];
        $hidden = [];
        foreach ($optional as $name => $fieldRules) {
            $mode = $modes[$modeOf[$name]] ?? 'optional';
            if ($mode === 'hidden') {
                $hidden[] = $name;

                continue;
            }
            if ($mode === 'required' && $name !== 'whatsapp_same') {
                $fieldRules = $name === 'whatsapp'
                    ? ['required_without:whatsapp_same', ...array_slice($fieldRules, 1)]
                    : ['required', ...array_slice($fieldRules, 1)];
            }
            $rules[$name] = $fieldRules;
        }

        $customFields = [];
        foreach ($config['custom_fields'] as $field) {
            $key = $field['key'];
            $customFields[$key] = $field;
            $fieldRules = [$field['required'] ? 'required' : 'nullable', 'string'];
            $fieldRules[] = match ($field['type']) {
                'select' => Rule::in($field['options'] ?? []),
                'textarea' => 'max:2000',
                default => 'max:255',
            };
            $rules['extra.'.$key] = $fieldRules;
        }

        $data = $request->validate($rules, [
            'consent.accepted' => 'Vous devez accepter le traitement de vos informations.',
            'group_id.exists' => ucfirst(term('this_group')).' n\'accepte pas de demandes.',
        ]);
        // Champ masqué envoyé malgré tout : ignoré, jamais stocké.
        foreach ($hidden as $name) {
            unset($data[$name]);
        }

        $extraFields = [];
        foreach ($customFields as $key => $field) {
            $value = $data['extra'][$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $extraFields[] = ['key' => $key, 'label' => $field['label'], 'value' => trim($value)];
            }
        }

        $country = $this->settings->defaultCountryCode();
        $phone = PhoneNumber::normalize($data['phone'], $country);
        if ($phone === null) {
            return $this->invalid('phone', 'Numéro de téléphone invalide.');
        }

        $whatsapp = null;
        if (! empty($data['whatsapp_same'])) {
            $whatsapp = $phone;
        } elseif (! empty($data['whatsapp'])) {
            $whatsapp = PhoneNumber::normalize($data['whatsapp'], $country);
            if ($whatsapp === null) {
                return $this->invalid('whatsapp', 'Numéro WhatsApp invalide.');
            }
        }

        $response = response()->json([
            'success' => true,
            'message' => 'Demande envoyée.',
            'confirmation' => $config['confirmation'],
            'phone' => $phone,
            'registration' => $this->settings->publicPayload()['registration'],
        ], 201);

        if ($this->isBot($data)) {
            return $response;
        }

        $attributes = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'email' => $data['email'] ?? null,
            'organization' => $data['organization'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'message' => $data['message'] ?? null,
            'extra_fields' => $extraFields !== [] ? $extraFields : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $existing = AccessRequest::where('phone', $phone)
            ->whereIn('status', AccessRequest::OPEN)
            ->latest('id')
            ->first();

        if ($existing) {
            $this->resubmit($existing, $attributes);

            return $response;
        }

        $accessRequest = AccessRequest::create($attributes + [
            'consent_at' => now(),
            'consent_text' => self::CONSENT_TEXT,
            'status' => 'new',
        ]);
        $accessRequest->events()->create(['from_status' => null, 'to_status' => 'new']);

        $recipients = $this->settings->notificationEmails();
        if ($recipients !== []) {
            try {
                Mail::to($recipients)->send(new NewAccessRequestMail($accessRequest));
            } catch (\Throwable $e) {
                // La demande est enregistrée : un échec d'envoi ne doit pas la faire échouer.
                report($e);
            }
        }

        return $response;
    }

    /**
     * Renvoi d'une demande depuis un numéro ayant déjà une demande ouverte.
     * Les coordonnées déjà renseignées ne sont jamais écrasées (un tiers
     * connaissant le numéro ne doit pas pouvoir détourner le contact) ;
     * seuls les champs vides sont complétés, et uniquement au statut `new`.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resubmit(AccessRequest $existing, array $attributes): void
    {
        if ($existing->status !== 'new') {
            $existing->events()->create([
                'from_status' => $existing->status,
                'to_status' => $existing->status,
                'comment' => 'Demande renvoyée (aucune modification)',
            ]);

            return;
        }

        $changed = [];
        foreach (self::CONTACT_FIELDS as $field) {
            $current = $existing->{$field};
            $incoming = $attributes[$field] ?? null;
            if (($current === null || $current === '') && $incoming !== null && $incoming !== '') {
                $existing->{$field} = $incoming;
                $changed[] = $field;
            }
        }

        $message = $attributes['message'] ?? null;
        if ($message !== null && trim($message) !== '' && ! str_contains((string) $existing->message, trim($message))) {
            $existing->message = $existing->message ? $existing->message."\n\n".trim($message) : trim($message);
            $changed[] = 'message';
        }

        $existing->save();

        $existing->events()->create([
            'from_status' => 'new',
            'to_status' => 'new',
            'comment' => 'Demande renvoyée'.($changed !== [] ? ' — champs complétés : '.implode(', ', $changed) : ' (aucune modification)'),
        ]);
    }

    /**
     * Champ piège rempli ou formulaire envoyé en moins de 3 s.
     *
     * @param  array<string, mixed>  $data
     */
    private function isBot(array $data): bool
    {
        if (! empty($data['website'])) {
            return true;
        }

        // Durée de remplissage mesurée côté client (pas d'horloge à comparer).
        if (isset($data['elapsed_ms']) && (int) $data['elapsed_ms'] < self::MIN_FILL_MS) {
            return true;
        }

        return false;
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => [$field => [$message]],
        ], 422);
    }
}
