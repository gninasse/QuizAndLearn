<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Services\GamificationService;
use Modules\Core\Services\ImpersonationService;
use Modules\Core\Support\LoginResolver;

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

        $user = LoginResolver::find($request->input('login'));

        if (! $user || ! $user->is_active || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        Auth::login($user, true);

        if (! $user->learner()->exists()) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'Ce compte n\'est pas configuré comme un compte apprenant.',
            ], 403);
        }

        if ($user->learner->isSuspended()) {
            $message = $user->learner->suspensionMessage();
            Auth::logout();

            return response()->json([
                'success' => false,
                'code' => 'account_suspended',
                'message' => $message,
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
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();
        $profile = $this->serializeUser($user);

        // Consultation « Voir en tant que » : état du compte pour le bandeau.
        $session = $request->attributes->get('impersonation');
        if ($session instanceof ImpersonationSession && $user->learner) {
            $profile = [...$profile, ...app(ImpersonationService::class)->profileExtras($session, $user->learner)];
        }

        // Clé publique VAPID : null (fonction inerte) si une des deux clés manque.
        $profile['push'] = [
            'public_key' => \Modules\Core\Services\Push\PushSettings::vapidConfigured()
                ? config('push.vapid.public_key')
                : null,
        ];

        return response()->json([
            'success' => true,
            'user' => $profile,
        ]);
    }

    /**
     * PUT /api/learner/v1/password — changement de mot de passe.
     *
     * Action de sécurité : en ligne uniquement (jamais rejouée via l'outbox).
     * La session courante est conservée (régénérée), les autres appareils
     * devront se reconnecter à l'expiration de leur session.
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

        $user = Auth::user();
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour.',
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
            'matricule' => $user->learner?->matricule,
            'must_change_password' => (bool) $user->must_change_password,
            'xp' => $this->gamification->snapshot($user->learner),
        ];
    }
}
