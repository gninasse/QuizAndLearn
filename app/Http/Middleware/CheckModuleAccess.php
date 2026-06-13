<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Vérifier si l'utilisateur a au moins une permission du module
        if (! $user->hasModuleAccess($module)) {
            abort(403, "Vous n'avez pas accès au module {$module}.");
        }

        // Optionnel : Ajouter le module au contexte de la requête
        $request->attributes->add(['current_module' => $module]);

        return $next($request);
    }
}
