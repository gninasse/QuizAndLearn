<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\GamificationService;

/**
 * Session du volet apprenant (API v1) : login, profil courant, logout.
 * Authentification par session/cookie (SPA same-origin, CSRF actif).
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly GamificationService $gamification,
    ) {}

    /**
     * POST /api/learner/v1/session — connexion.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $request->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';

        if (! Auth::attempt([
            $field => $login,
            'password' => $request->input('password'),
            'is_active' => true,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        $user = Auth::user();

        if (! $user->learner()->exists()) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'Ce compte n\'est pas configuré comme un compte apprenant.',
            ], 403);
        }

        $request->session()->regenerate();
        $user->logLogin();

        return response()->json([
            'success' => true,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * GET /api/learner/v1/me — profil courant.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * DELETE /api/learner/v1/session — déconnexion.
     */
    public function destroy(Request $request): JsonResponse
    {
        if (Auth::check()) {
            Auth::user()->logLogout();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    private function serializeUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'xp' => $this->gamification->snapshot($user->learner),
        ];
    }
}
