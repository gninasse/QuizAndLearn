<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LearnerAuthController extends Controller
{
    /**
     * Afficher le formulaire de connexion de l'apprenant.
     */
    public function showLogin(): RedirectResponse|View
    {
        if (Auth::check() && Auth::user()->learner()->exists()) {
            return redirect()->route('learner.dashboard');
        }

        return view('core::learner.auth.login');
    }

    /**
     * Authentifier l'apprenant.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $request->input('login');
        $password = $request->input('password');

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';

        if (Auth::attempt([$field => $login, 'password' => $password, 'is_active' => true], true)) {
            $user = Auth::user();

            // Vérifier s'il s'agit bien d'un compte apprenant
            if (! $user->learner()->exists()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'login_error' => 'Ce compte n\'est pas configuré comme un compte apprenant.',
                ])->onlyInput('login');
            }

            // Mettre à jour l'historique de connexion de l'utilisateur
            $user->logLogin();

            $request->session()->regenerate();

            return redirect()->intended(route('learner.dashboard'));
        }

        return back()->withErrors([
            'login_error' => __('auth.failed'),
        ])->onlyInput('login');
    }

    /**
     * Déconnecter l'apprenant.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            Auth::user()->logLogout();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('learner.login');
    }
}
