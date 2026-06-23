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
        if (! Auth::check() || ! Auth::user()->learner()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Accès non autorisé.'], 403);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('learner.login')->withErrors([
                'login_error' => 'Veuillez vous connecter avec un compte apprenant actif.',
            ]);
        }

        return $next($request);
    }
}
