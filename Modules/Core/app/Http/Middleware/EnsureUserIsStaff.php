<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porte du back-office : réservé au personnel (super-admin, admin,
 * formateur) ou à tout utilisateur détenant au moins une permission
 * du module core. Un compte purement apprenant est renvoyé vers son
 * espace — le volet admin ne doit jamais lui être visible.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        $isStaff = $user !== null && (
            $user->hasAnyRole(['super-admin', 'admin', 'trainer'])
            || $user->trainer()->exists()
            || $user->hasModuleAccess('core')
        );

        if (! $isStaff) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Accès réservé au personnel.'], 403);
            }

            // Un apprenant connecté retourne à son espace ; sinon login staff.
            return $user?->learner()->exists()
                ? redirect('/')
                : redirect()->route('login');
        }

        return $next($request);
    }
}
