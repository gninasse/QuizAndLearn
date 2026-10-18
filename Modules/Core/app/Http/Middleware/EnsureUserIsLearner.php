<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsLearner
{
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
        if ($learner->isSuspended()) {
            return $this->deny($request, $learner->suspensionMessage(), 'account_suspended');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, string $code = 'not_learner'): Response
    {
        // Requêtes JSON ou stateless (API mobile Sanctum) : 403 sec.
        if ($request->expectsJson() || ! $request->hasSession()) {
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
