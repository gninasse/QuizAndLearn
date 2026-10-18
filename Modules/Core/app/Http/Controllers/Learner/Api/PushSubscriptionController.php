<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\Models\PushSubscription;

/**
 * Abonnements Web Push de l'apprenant connecté (API v1).
 * Écritures bloquées en consultation par la liste blanche lecture seule.
 */
class PushSubscriptionController extends Controller
{
    /**
     * POST /api/learner/v1/push-subscriptions — enregistre ou réaffecte un abonnement.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail) {
                if (! self::allowedEndpoint((string) $value)) {
                    $fail('Service de notification non reconnu.');
                }
            }],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $learner = Auth::user()->learner;

        // Même endpoint = même navigateur : l'abonnement change de propriétaire
        // (jamais de doublon, aucune notification vers l'ancien compte).
        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'learner_id' => $learner->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ],
        );

        $this->pruneOldest($learner->id, $subscription->id);

        return response()->json(['success' => true, 'id' => $subscription->id], 201);
    }

    /** Hôte de l'endpoint dans la liste blanche `push.allowed_hosts` (`*.` = sous-domaines). */
    public static function allowedEndpoint(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ((array) config('push.allowed_hosts', []) as $pattern) {
            $pattern = strtolower((string) $pattern);
            if (str_starts_with($pattern, '*.')
                ? str_ends_with($host, substr($pattern, 1))
                : $host === $pattern) {
                return true;
            }
        }

        return false;
    }

    /** Au-delà du plafond par apprenant, retire les abonnements les moins récemment utilisés. */
    private function pruneOldest(int $learnerId, int $keepId): void
    {
        $max = max(1, (int) config('push.max_subscriptions_per_learner', 10));

        $excess = PushSubscription::query()
            ->where('learner_id', $learnerId)
            ->whereKeyNot($keepId)
            ->orderByRaw('COALESCE(last_used_at, created_at) DESC')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($max - 1);

        if ($excess->isNotEmpty()) {
            PushSubscription::whereKey($excess->all())->delete();
        }
    }

    /**
     * DELETE /api/learner/v1/push-subscriptions — supprime l'abonnement de l'apprenant.
     */
    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::query()
            ->where('learner_id', Auth::user()->learner->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->noContent();
    }
}
