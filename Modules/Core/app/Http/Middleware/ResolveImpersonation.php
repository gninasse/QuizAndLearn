<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\ImpersonationService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consultation « Voir en tant que » : si l'en-tête X-Impersonation-Token est
 * présent, l'apprenant consulté devient l'utilisateur de CETTE requête
 * seulement (aucune écriture de session : celle du super admin reste
 * intacte). Seules les routes de lecture de la liste blanche passent.
 */
class ResolveImpersonation
{
    public const HEADER = 'X-Impersonation-Token';

    /** Routes de lecture autorisées en consultation (liste blanche). */
    public const READ_ONLY_ALLOWED = [
        'learn.v1.me',
        'learn.v1.bootstrap',
        'learn.v1.changes',
        'learn.v1.leaderboard',
    ];

    public const END_ROUTE = 'learn.v1.impersonation.end';

    public function __construct(private ImpersonationService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has(self::HEADER)) {
            return $next($request);
        }

        // Utilisateur de la session web (le super admin), lu AVANT la substitution.
        $guard = Auth::guard('web');
        $webUser = $guard->user();

        $session = $this->service->resolve((string) $request->header(self::HEADER), $webUser);

        if (! $session) {
            return response()->json([
                'code' => 'impersonation_ended',
                'message' => 'Consultation terminée.',
            ], 403);
        }

        $route = $request->route();
        $routeName = is_object($route) ? $route->getName() : null;
        if (! in_array($routeName, [...self::READ_ONLY_ALLOWED, self::END_ROUTE], true)) {
            return response()->json([
                'code' => 'read_only',
                'message' => 'Action indisponible en consultation.',
            ], 403);
        }

        $request->attributes->set('impersonation', $session);

        try {
            $guard->setUser($session->learner->user);

            return $next($request);
        } finally {
            // Process longue durée (Octane, tests) : on restitue l'admin.
            $guard->setUser($webUser);
        }
    }
}
