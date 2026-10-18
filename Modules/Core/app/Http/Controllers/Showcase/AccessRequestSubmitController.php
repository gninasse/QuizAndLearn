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
    private const MIN_FILL_SECONDS = 3;

    public const CONSENT_TEXT = 'J\'accepte que mes informations soient utilisées pour traiter ma demande d\'accès, conformément à la politique de confidentialité.';

    public function __construct(
        private readonly ShowcaseSettings $settings,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $this->settings->enabled()) {
            return ShowcaseController::disabledResponse();
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp_same' => ['nullable', 'boolean'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('open_for_requests', true)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string'],
            'started_at' => ['nullable', 'numeric'],
        ], [
            'consent.accepted' => 'Vous devez accepter le traitement de vos informations.',
            'group_id.exists' => 'Ce groupe n\'accepte pas de demandes.',
        ]);

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
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $existing = AccessRequest::where('phone', $phone)
            ->whereIn('status', AccessRequest::OPEN)
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->update($attributes);

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
            Mail::to($recipients)->send(new NewAccessRequestMail($accessRequest));
        }

        return $response;
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

        if (isset($data['started_at']) && (now()->getTimestampMs() - (float) $data['started_at']) < self::MIN_FILL_SECONDS * 1000) {
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
