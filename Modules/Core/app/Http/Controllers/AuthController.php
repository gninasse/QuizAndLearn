<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Support\LoginResolver;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('cores.dashboard');
        }

        return view('core::auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = LoginResolver::find($request->input('login'));

        if ($user && $user->is_active && Hash::check($request->input('password'), $user->password)) {
            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('cores.dashboard'));
        }

        // Return with a generic error message key 'login_error' instead of field-specific errors
        return back()->withErrors([
            'login_error' => __('auth.failed'),
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
