<?php

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;

/**
 * « Voir en tant que » : sessions de consultation en lecture seule de
 * l'espace d'un apprenant par un super admin.
 *
 * Le jeton en clair n'est renvoyé qu'une fois (fragment d'URL) ; il est lié
 * à la session web du super admin créateur et inutilisable sans elle.
 */
class ImpersonationService
{
    public const ROLE = 'super-admin';

    public const TTL_MINUTES = 30;

    /**
     * @return array{session: ImpersonationSession, token: string, url: string}
     */
    public function start(User $admin, Learner $learner, Request $request): array
    {
        ImpersonationSession::where('admin_id', $admin->id)
            ->whereNull('ended_at')
            ->get()
            ->each(fn (ImpersonationSession $open) => $this->end($open));

        $token = Str::random(64);

        $session = ImpersonationSession::create([
            'admin_id' => $admin->id,
            'learner_id' => $learner->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->log('impersonation_started', $session, $admin);

        return [
            'session' => $session,
            'token' => $token,
            'url' => url('/').'#consultation='.$token,
        ];
    }

    public function resolve(?string $token, ?User $webUser): ?ImpersonationSession
    {
        if ($token === null || $token === '' || $webUser === null) {
            return null;
        }

        $session = ImpersonationSession::where('token_hash', hash('sha256', $token))
            ->with(['admin', 'learner.user'])
            ->first();

        if (! $session || (int) $session->admin_id !== (int) $webUser->getKey()) {
            return null;
        }

        // Expirée mais jamais fermée : on la clôture (journal) au passage.
        if ($session->ended_at === null && ! $session->expires_at->isFuture()) {
            $this->end($session);

            return null;
        }

        if (! $session->isOpen()) {
            return null;
        }

        // Admin rechargé depuis la base : rôle et statut à jour, sans cache.
        $admin = $session->admin;
        if (! $admin || ! $admin->is_active || ! $admin->hasRole(self::ROLE)) {
            return null;
        }

        if (! $session->learner?->user) {
            return null;
        }

        return $session;
    }

    public function end(ImpersonationSession $session): void
    {
        if ($session->ended_at !== null) {
            return;
        }

        // Durée plafonnée à l'expiration : une session oubliée ne gonfle pas l'audit.
        $endedAt = now()->min($session->expires_at);

        $session->forceFill(['ended_at' => $endedAt])->save();

        $this->log('impersonation_ended', $session, $session->admin, [
            'duration_seconds' => (int) $session->created_at->diffInSeconds($session->ended_at, true),
        ]);
    }

    private function log(string $event, ImpersonationSession $session, ?User $admin, array $properties = []): void
    {
        $learner = $session->learner;

        activity('core')
            ->causedBy($admin)
            ->performedOn($learner)
            ->withProperties($properties + [
                'learner_id' => $learner->id,
                'learner' => $learner->user?->full_name,
                'session_id' => $session->id,
            ])
            ->tap(function ($activity) {
                $activity->module = 'core';
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log($event);
    }

    /**
     * Champs ajoutés au profil apprenant (/me, /bootstrap) en consultation
     * seulement : état du compte (bandeau) et auteur de la consultation
     * (filigrane des médias).
     *
     * @return array{suspended: bool, suspended_until: ?string, must_change_password: bool, consultation: array{admin_name: ?string, expires_at: string}}
     */
    public function profileExtras(ImpersonationSession $session, Learner $learner): array
    {
        $suspended = $learner->isSuspended();

        return [
            'suspended' => $suspended,
            'suspended_until' => $suspended ? $learner->suspended_until?->toIso8601String() : null,
            'must_change_password' => (bool) $learner->user?->must_change_password,
            'consultation' => [
                'admin_name' => $session->admin?->full_name,
                'expires_at' => $session->expires_at->toIso8601String(),
            ],
        ];
    }
}
