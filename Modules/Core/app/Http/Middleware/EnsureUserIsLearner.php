<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsLearner
{
    private const PASSWORD_CHANGE_ALLOWED = [
        'learn.v1.me',
        'learn.v1.password.update',
        'learn.v1.session.destroy',
        'api.mobile.v1.me',
        'api.mobile.v1.password.update',
        'api.mobile.v1.logout',
        'api.mobile.v1.logout-all',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $learner = $user?->learner;

        if (! $user || ! $learner || ! $user->is_active) {
            return $this->deny($request, 'Veuillez vous connecter avec un compte apprenant actif.');
        }

        // Suspension temporaire : l'accès est coupé (sessions et tokens
        // existants compris) mais l'appartenance aux groupes est conservée.
        // Consultation « Voir en tant que » (super admin, lecture seule) : les
        // verrous de suspension et de mot de passe temporaire ne s'appliquent
        // pas ; un compte désactivé reste refusé ci-dessus.
        $impersonated = $request->attributes->get('impersonation') !== null;

        if (! $impersonated && $learner->isSuspended()) {
            return $this->deny($request, $learner->suspensionMessage(), 'account_suspended');
        }

        // Mot de passe temporaire : seules les routes de profil, de changement
        // de mot de passe et de déconnexion restent accessibles.
        // Le shell SPA (pages HTML) reste servi pour afficher l'écran de changement ;
        // seules les routes de données (API) sont bloquées.
        if (! $impersonated
            && $user->must_change_password
            && $request->routeIs('learn.v1.*', 'api.mobile.v1.*')
            && ! $request->routeIs(self::PASSWORD_CHANGE_ALLOWED)) {
            return response()->json([
                'message' => 'Vous devez choisir un nouveau mot de passe.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, string $code = 'not_learner'): Response
    {
        // Requêtes JSON ou stateless (API mobile Sanctum) : 403 sec.
        // En consultation, la session web est celle du super admin : ne jamais
        // la détruire.
        if ($request->expectsJson() || ! $request->hasSession()
            || $request->attributes->get('impersonation') !== null) {
            return response()->json(['message' => $message, 'code' => $code], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('learn.login')->withErrors([
            'login_error' => $message,
        ]);
    }
}
