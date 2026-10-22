<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Models\Activity;
use Modules\Core\Models\Group;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * « Voir en tant que » : consultation en lecture seule de l'espace d'un
 * apprenant par un super admin, via un jeton lié à sa session web.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'X-Impersonation-Token';

    // videos.hls : hors middleware impersonation (signature seule), GET en lecture seule.
    private const WHITELIST = ['learn.v1.me', 'learn.v1.bootstrap', 'learn.v1.changes', 'learn.v1.leaderboard', 'learn.v1.videos.playback', 'learn.v1.videos.hls', 'learn.v1.resources.view', 'learn.v1.resources.file'];

    private User $superAdmin;

    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'admin', 'trainer', 'learner'] as $role) {
            Role::findOrCreate($role);
        }

        $this->superAdmin = $this->makeUser('root');
        $this->superAdmin->assignRole('super-admin');

        $learnerUser = $this->makeUser('awa');
        $learnerUser->assignRole('learner');
        $this->learner = Learner::create(['user_id' => $learnerUser->id, 'matricule' => 'MAT-001']);

        $group = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $this->learner->groups()->attach($group->id);
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }

    private function start(?Learner $learner = null): string
    {
        $learner ??= $this->learner;

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/cores/learners/{$learner->id}/impersonate")
            ->assertOk()
            ->assertJsonPath('success', true);

        $url = $response->json('url');
        $this->assertStringStartsWith(url('/').'#consultation=', $url);
        $this->assertNotNull($response->json('expires_at'));

        return substr($url, strpos($url, '#consultation=') + strlen('#consultation='));
    }

    private function asAdmin(string $token): static
    {
        return $this->actingAs($this->superAdmin)->withHeader(self::HEADER, $token);
    }

    public function test_super_admin_starts_consultation_and_reads_learner_bootstrap(): void
    {
        $token = $this->start();

        $this->assertSame(64, strlen($token));
        $session = ImpersonationSession::sole();
        $this->assertSame(hash('sha256', $token), $session->token_hash);
        $this->assertSame($this->superAdmin->id, $session->admin_id);
        $this->assertSame($this->learner->id, $session->learner_id);
        $this->assertTrue($session->expires_at->equalTo($session->created_at->copy()->addMinutes(30)));

        $this->asAdmin($token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('user.id', $this->learner->user_id);

        $this->asAdmin($token)
            ->getJson('/api/learner/v1/me')
            ->assertOk();

        // La session web reste celle du super admin.
        $this->assertSame($this->superAdmin->id, auth()->guard('web')->id());
    }

    public function test_admin_and_trainer_cannot_start(): void
    {
        foreach (['admin', 'trainer'] as $role) {
            $user = $this->makeUser("u{$role}");
            $user->assignRole($role);

            $this->actingAs($user)
                ->postJson("/cores/learners/{$this->learner->id}/impersonate")
                ->assertForbidden();
        }

        $this->assertSame(0, ImpersonationSession::count());
    }

    public function test_token_without_admin_session_is_refused(): void
    {
        $token = $this->start();

        auth()->logout();

        $this->withHeader(self::HEADER, $token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended')
            ->assertJsonPath('message', 'Consultation terminée.');
    }

    public function test_token_used_by_another_super_admin_is_refused(): void
    {
        $token = $this->start();

        $other = $this->makeUser('other');
        $other->assignRole('super-admin');

        $this->actingAs($other)->withHeader(self::HEADER, $token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');
    }

    public function test_demoted_admin_ends_consultation(): void
    {
        $token = $this->start();

        $this->superAdmin->removeRole('super-admin');
        $this->superAdmin->assignRole('admin');

        $this->asAdmin($token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');
    }

    public function test_expired_and_ended_tokens_are_refused(): void
    {
        $token = $this->start();

        Carbon::setTestNow(now()->addMinutes(31));
        $this->asAdmin($token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');
        Carbon::setTestNow();

        $token = $this->start();
        $this->asAdmin($token)->getJson('/api/learner/v1/bootstrap')->assertOk();

        $this->asAdmin($token)
            ->deleteJson('/api/learner/v1/impersonation')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull(ImpersonationSession::where('token_hash', hash('sha256', $token))->value('ended_at'));

        $this->asAdmin($token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');

        $this->asAdmin('unknown-token')
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');
    }

    public function test_end_without_token_is_refused(): void
    {
        $this->actingAs($this->superAdmin)
            ->deleteJson('/api/learner/v1/impersonation')
            ->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');
    }

    public function test_every_non_whitelisted_learner_route_is_read_only(): void
    {
        $token = $this->start();

        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'learn.v1.')
                || in_array($name, [...self::WHITELIST, 'learn.v1.impersonation.end'], true)) {
                continue;
            }

            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());

            $this->asAdmin($token)
                ->json($method, $uri, ['type' => 'quiz_completed', 'actions' => []])
                ->assertForbidden()
                ->assertJsonPath('code', 'read_only')
                ->assertJsonPath('message', 'Action indisponible en consultation.');
            $checked++;
        }

        $this->assertGreaterThanOrEqual(7, $checked);
        $this->assertSame(0, DB::table('learner_action_log')->count());
        $this->assertSame(0, DB::table('quiz_attempts')->count());
        $this->assertSame(0, DB::table('exam_attempts')->count());
        // La consultation reste valide (DELETE /session n'a déconnecté personne).
        $this->asAdmin($token)->getJson('/api/learner/v1/bootstrap')->assertOk();
    }

    public function test_suspended_and_temp_password_learner_can_be_viewed(): void
    {
        $this->learner->suspend(null, 'Test', $this->superAdmin->id);
        $this->learner->user->forceFill(['must_change_password' => true])->save();

        $token = $this->start();

        $this->asAdmin($token)->getJson('/api/learner/v1/bootstrap')->assertOk();
        $this->asAdmin($token)->getJson('/api/learner/v1/leaderboard')->assertOk();
    }

    public function test_profile_exposes_account_state_in_consultation_only(): void
    {
        $until = Carbon::parse('2030-10-25 07:46:00');
        $this->learner->suspend($until, 'Test', $this->superAdmin->id);
        $this->learner->user->forceFill(['must_change_password' => true])->save();

        $token = $this->start();
        $session = ImpersonationSession::sole();

        foreach (['/api/learner/v1/bootstrap', '/api/learner/v1/me'] as $path) {
            $this->asAdmin($token)->getJson($path)
                ->assertOk()
                ->assertJsonPath('user.suspended', true)
                ->assertJsonPath('user.suspended_until', $until->toIso8601String())
                ->assertJsonPath('user.must_change_password', true)
                ->assertJsonPath('user.consultation.admin_name', $this->superAdmin->full_name)
                ->assertJsonPath('user.consultation.expires_at', $session->expires_at->toIso8601String());
        }

        // Suspension sans date de fin : suspended_until vaut null.
        $this->learner->liftSuspension();
        $this->learner->suspend(null, 'Test', $this->superAdmin->id);
        $this->asAdmin($token)->getJson('/api/learner/v1/me')
            ->assertOk()
            ->assertJsonPath('user.suspended', true)
            ->assertJsonPath('user.suspended_until', null);

        // Hors consultation, le profil apprenant ne change pas.
        $this->learner->liftSuspension();
        $this->learner->user->refresh()->forceFill(['must_change_password' => false])->save();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        foreach (['/api/learner/v1/bootstrap', '/api/learner/v1/me'] as $path) {
            $this->actingAs($this->learner->user)->getJson($path)
                ->assertOk()
                ->assertJsonMissingPath('user.suspended')
                ->assertJsonMissingPath('user.suspended_until')
                ->assertJsonMissingPath('user.consultation');
        }
    }

    public function test_inactive_learner_cannot_be_started(): void
    {
        $this->learner->user->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->postJson("/cores/learners/{$this->learner->id}/impersonate")
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Compte désactivé : consultation impossible.']);

        $this->assertSame(0, ImpersonationSession::count());
    }

    public function test_learner_deactivated_during_consultation_is_refused(): void
    {
        $token = $this->start();
        $this->learner->user->update(['is_active' => false]);

        $this->asAdmin($token)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'not_learner');
    }

    public function test_new_consultation_ends_previous_ones_of_same_admin(): void
    {
        $first = $this->start();

        $otherAdmin = $this->makeUser('root2');
        $otherAdmin->assignRole('super-admin');
        $this->actingAs($otherAdmin)
            ->postJson("/cores/learners/{$this->learner->id}/impersonate")
            ->assertOk();

        $second = $this->start();

        $this->asAdmin($first)->getJson('/api/learner/v1/bootstrap')->assertForbidden()
            ->assertJsonPath('code', 'impersonation_ended');
        $this->asAdmin($second)->getJson('/api/learner/v1/bootstrap')->assertOk();

        $this->assertSame(1, ImpersonationSession::where('admin_id', $this->superAdmin->id)->whereNull('ended_at')->count());
        $this->assertSame(1, ImpersonationSession::where('admin_id', $otherAdmin->id)->whereNull('ended_at')->count());
    }

    public function test_super_admin_without_header_is_not_a_learner(): void
    {
        $this->start();

        $this->actingAs($this->superAdmin)
            ->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'not_learner');
    }

    public function test_activity_logged_on_start_and_end(): void
    {
        $token = $this->start();
        $session = ImpersonationSession::sole();

        $started = Activity::where('description', 'impersonation_started')->sole();
        $this->assertSame('core', $started->log_name);
        $this->assertSame($this->superAdmin->id, (int) $started->causer_id);
        $this->assertSame($this->learner->id, (int) $started->subject_id);
        $this->assertSame($this->learner->id, $started->properties['learner_id']);
        $this->assertSame($session->id, $started->properties['session_id']);
        $this->assertSame('Awa Test', $started->properties['learner']);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->asAdmin($token)->deleteJson('/api/learner/v1/impersonation')->assertOk();
        Carbon::setTestNow();

        $ended = Activity::where('description', 'impersonation_ended')->sole();
        $this->assertSame($this->superAdmin->id, (int) $ended->causer_id);
        $this->assertSame($session->id, $ended->properties['session_id']);
        $this->assertSame(300, $ended->properties['duration_seconds']);
    }

    public function test_expired_session_is_closed_at_expiry_via_resolve(): void
    {
        $token = $this->start();
        $session = ImpersonationSession::sole();

        Carbon::setTestNow(now()->addHours(3));
        $this->asAdmin($token)->getJson('/api/learner/v1/bootstrap')
            ->assertForbidden()->assertJsonPath('code', 'impersonation_ended');
        Carbon::setTestNow();

        $session->refresh();
        $this->assertTrue($session->ended_at->equalTo($session->expires_at));
        $ended = Activity::where('description', 'impersonation_ended')->sole();
        $this->assertLessThanOrEqual(1800, $ended->properties['duration_seconds']);
    }

    public function test_stale_session_ended_by_new_start_is_capped_at_expiry(): void
    {
        $this->start();
        $first = ImpersonationSession::sole();

        Carbon::setTestNow(now()->addHours(3));
        $this->start();
        Carbon::setTestNow();

        $first->refresh();
        $this->assertTrue($first->ended_at->equalTo($first->expires_at));
        $ended = Activity::where('description', 'impersonation_ended')->sole();
        $this->assertLessThanOrEqual(1800, $ended->properties['duration_seconds']);
    }
}
