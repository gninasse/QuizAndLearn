<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Models\User;
use Modules\Core\Services\GamificationService;

/**
 * Authentification de l'application mobile (Sanctum Bearer tokens).
 *
 * Sécurité :
 * - Throttling brute-force : 5 essais / minute par couple IP+login,
 *   avec délai de reprise communiqué (Retry-After).
 * - Un token par appareil (nommé), expiration 30 jours (config sanctum),
 *   hashé en base par Sanctum.
 * - Révocation : logout (token courant), logout-all, et automatique à
 *   chaque changement de mot de passe.
 * - Aucun cookie ni session : stateless, CSRF sans objet.
 */
class MobileAuthController extends Controller
{
    public function __construct(
        private readonly GamificationService $gamification,
    ) {}

    /**
     * POST /api/mobile/v1/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $throttleKey = 'mobile-login:'.sha1(mb_strtolower($request->input('login')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
            ], 429, ['Retry-After' => $seconds]);
        }

        $login = $request->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';

        $user = User::where($field, $login)->where('is_active', true)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        if (! $user->learner()->exists()) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Ce compte n\'est pas configuré comme un compte apprenant.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        // Un seul token actif par appareil : reconnecter le même appareil
        // remplace son ancien token au lieu de l'empiler.
        $user->tokens()->where('name', $request->input('device_name'))->delete();
        $token = $user->createToken($request->input('device_name'), ['learner']);

        $user->logLogin();

        return response()->json([
            'success' => true,
            'token' => $token->plainTextToken,
            'expires_in_days' => 30,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * GET /api/mobile/v1/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    /**
     * PUT /api/mobile/v1/password — révoque tous les autres tokens.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation ne correspond pas.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l\'actuel.',
        ]);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);

        // Sécurité : tout autre appareil (token potentiellement compromis)
        // doit se reconnecter avec le nouveau mot de passe.
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour. Les autres appareils ont été déconnectés.',
        ]);
    }

    /**
     * POST /api/mobile/v1/logout — révoque le token de cet appareil.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->logLogout();
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/mobile/v1/logout-all — révoque tous les appareils.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->logLogout();
        $request->user()->tokens()->delete();

        return response()->json(['success' => true]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'matricule' => $user->learner?->matricule,
            'xp' => $this->gamification->snapshot($user->learner),
        ];
    }
}
