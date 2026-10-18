<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Learner;
use Modules\Core\Services\ImpersonationService;

/**
 * « Voir en tant que » : démarrage (back-office) et fin (onglet de
 * consultation) d'une session de consultation en lecture seule.
 */
class ImpersonationController extends Controller
{
    public function __construct(private ImpersonationService $service) {}

    /**
     * POST /cores/learners/{learnerId}/impersonate
     */
    public function start(Request $request, int $learnerId): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin && $admin->hasRole(ImpersonationService::ROLE), 403);

        $learner = Learner::with('user')->findOrFail($learnerId);

        if (! $learner->user || ! $learner->user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Compte désactivé : consultation impossible.',
            ], 422);
        }

        $result = $this->service->start($admin, $learner, $request);

        return response()->json([
            'success' => true,
            'url' => $result['url'],
            'expires_at' => $result['session']->expires_at->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/learner/v1/impersonation (avec l'en-tête X-Impersonation-Token)
     */
    public function end(Request $request): JsonResponse
    {
        $session = $request->attributes->get('impersonation');

        if (! $session instanceof ImpersonationSession) {
            return response()->json([
                'code' => 'impersonation_ended',
                'message' => 'Consultation terminée.',
            ], 403);
        }

        $this->service->end($session);

        return response()->json(['success' => true]);
    }
}
