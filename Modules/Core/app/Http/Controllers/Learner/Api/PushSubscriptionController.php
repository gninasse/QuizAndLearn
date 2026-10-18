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
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
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

        return response()->json(['success' => true, 'id' => $subscription->id], 201);
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
