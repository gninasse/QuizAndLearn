<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Mail\NewAccessRequestMail;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Group;
use Tests\TestCase;

class AccessRequestSubmitTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/showcase/v1/access-requests';

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::put('showcase.enabled', true);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Awa',
            'last_name' => 'Kone',
            'phone' => '07 01 02 03 04',
            'whatsapp_same' => true,
            'consent' => true,
            'elapsed_ms' => 10000,
        ], $override);
    }

    public function test_valid_request_is_stored_with_consent_and_event(): void
    {
        Mail::fake();

        $this->postJson(self::URL, $this->payload(['email' => 'awa@example.com', 'message' => 'Bonjour']))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Demande envoyée.')
            ->assertJsonPath('phone', '+2250701020304')
            ->assertJsonStructure(['registration' => ['fee', 'currency', 'accounts', 'reference', 'instructions']]);

        $request = AccessRequest::firstOrFail();
        $this->assertSame('+2250701020304', $request->phone);
        $this->assertSame('+2250701020304', $request->whatsapp);
        $this->assertSame('new', $request->status);
        $this->assertNotNull($request->consent_at);
        $this->assertNotEmpty($request->consent_text);
        $this->assertSame(1, $request->events()->where('to_status', 'new')->count());
    }

    public function test_phone_is_normalized_and_required(): void
    {
        $this->postJson(self::URL, $this->payload(['phone' => null]))->assertStatus(422);
        $this->postJson(self::URL, $this->payload(['phone' => 'abc']))->assertStatus(422);
        $this->postJson(self::URL, $this->payload(['consent' => false]))->assertStatus(422);

        $this->postJson(self::URL, $this->payload(['phone' => '+225 05 11 22 33 44', 'whatsapp_same' => false, 'whatsapp' => '0102030405']))
            ->assertCreated();
        $request = AccessRequest::firstOrFail();
        $this->assertSame('+2250511223344', $request->phone);
        $this->assertSame('+2250102030405', $request->whatsapp);
    }

    public function test_honeypot_and_too_fast_submissions_are_silently_dropped(): void
    {
        $this->postJson(self::URL, $this->payload(['website' => 'http://spam']))
            ->assertCreated()->assertJsonPath('message', 'Demande envoyée.');
        $this->postJson(self::URL, $this->payload(['elapsed_ms' => 500]))
            ->assertCreated()->assertJsonPath('message', 'Demande envoyée.');

        $this->assertSame(0, AccessRequest::count());
    }

    public function test_elapsed_ms_must_be_a_non_negative_integer(): void
    {
        $this->postJson(self::URL, $this->payload(['elapsed_ms' => -1]))->assertStatus(422);
        $this->postJson(self::URL, $this->payload(['elapsed_ms' => 'abc']))->assertStatus(422);
        $this->postJson(self::URL, $this->payload(['elapsed_ms' => 3000]))->assertCreated();
        $this->assertSame(1, AccessRequest::count());
    }

    public function test_resubmission_on_new_request_never_overwrites_contact_fields(): void
    {
        $this->postJson(self::URL, $this->payload(['email' => 'awa@example.com', 'message' => 'Bonjour']))->assertCreated();

        $this->postJson(self::URL, $this->payload([
            'first_name' => 'Pirate',
            'whatsapp_same' => false,
            'whatsapp' => '0509090909',
            'email' => 'attacker@example.com',
            'message' => 'Autre message',
        ]))->assertCreated()->assertJsonPath('message', 'Demande envoyée.');

        $this->assertSame(1, AccessRequest::count());
        $request = AccessRequest::first();
        $this->assertSame('Awa', $request->first_name);
        $this->assertSame('+2250701020304', $request->whatsapp);
        $this->assertSame('awa@example.com', $request->email);
        $this->assertStringContainsString('Bonjour', $request->message);
        $this->assertStringContainsString('Autre message', $request->message);

        $event = $request->events()->where('comment', 'like', 'Demande renvoyée%')->first();
        $this->assertNotNull($event);
        $this->assertSame('new', $event->from_status);
        $this->assertSame('new', $event->to_status);
    }

    public function test_resubmission_fills_empty_fields_only(): void
    {
        $group = Group::create(['name' => 'Ouvert', 'is_active' => true, 'open_for_requests' => true]);
        $this->postJson(self::URL, $this->payload(['whatsapp_same' => false]))->assertCreated();

        $this->postJson(self::URL, $this->payload([
            'whatsapp_same' => false,
            'whatsapp' => '0509090909',
            'email' => 'awa@example.com',
            'organization' => 'ACME',
            'group_id' => $group->id,
        ]))->assertCreated();

        $request = AccessRequest::first();
        $this->assertSame('+2250509090909', $request->whatsapp);
        $this->assertSame('awa@example.com', $request->email);
        $this->assertSame('ACME', $request->organization);
        $this->assertSame($group->id, $request->group_id);
        $event = $request->events()->where('comment', 'like', 'Demande renvoyée%')->firstOrFail();
        $this->assertStringContainsString('email', $event->comment);
        $this->assertStringContainsString('whatsapp', $event->comment);
    }

    public function test_resubmission_on_paid_request_changes_nothing_but_logs_event(): void
    {
        $this->postJson(self::URL, $this->payload(['whatsapp_same' => false]))->assertCreated();
        $request = AccessRequest::first();
        $request->transitionTo('paid', null);

        $this->postJson(self::URL, $this->payload([
            'first_name' => 'Pirate',
            'whatsapp_same' => false,
            'whatsapp' => '0509090909',
            'email' => 'attacker@example.com',
        ]))->assertCreated();

        $request->refresh();
        $this->assertSame(1, AccessRequest::count());
        $this->assertSame('Awa', $request->first_name);
        $this->assertNull($request->whatsapp);
        $this->assertNull($request->email);
        $this->assertSame('paid', $request->status);
        $event = $request->events()->latest('id')->first();
        $this->assertSame('Demande renvoyée (aucune modification)', $event->comment);
        $this->assertSame('paid', $event->from_status);
        $this->assertSame('paid', $event->to_status);
    }

    public function test_closed_request_allows_a_new_one(): void
    {
        $this->postJson(self::URL, $this->payload())->assertCreated();
        AccessRequest::first()->transitionTo('rejected', null, 'Non');

        $this->postJson(self::URL, $this->payload())->assertCreated();
        $this->assertSame(2, AccessRequest::count());
    }

    public function test_group_must_be_open_for_requests(): void
    {
        $closed = Group::create(['name' => 'Fermé', 'is_active' => true, 'open_for_requests' => false]);
        $open = Group::create(['name' => 'Ouvert', 'is_active' => true, 'open_for_requests' => true]);

        $inactive = Group::create(['name' => 'Inactif', 'is_active' => false, 'open_for_requests' => true]);

        $this->postJson(self::URL, $this->payload(['group_id' => $closed->id]))->assertStatus(422);
        $this->postJson(self::URL, $this->payload(['group_id' => $inactive->id]))->assertStatus(422);
        $this->postJson(self::URL, $this->payload(['group_id' => $open->id]))->assertCreated();
        $this->assertSame($open->id, AccessRequest::first()->group_id);
    }

    public function test_notification_mail_sent_to_configured_addresses(): void
    {
        Mail::fake();
        AppSetting::put('showcase.notification_emails', ['admin@example.com']);

        $this->postJson(self::URL, $this->payload())->assertCreated();

        Mail::assertSent(NewAccessRequestMail::class, fn ($m) => $m->hasTo('admin@example.com'));
    }

    public function test_mail_failure_does_not_fail_the_request(): void
    {
        AppSetting::put('showcase.notification_emails', ['admin@example.com']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP indisponible'));

        $this->postJson(self::URL, $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertSame(1, AccessRequest::count());
    }

    public function test_mail_escapes_markdown_in_user_fields(): void
    {
        $this->postJson(self::URL, $this->payload([
            'first_name' => '[Cliquez](http://evil)',
            'message' => '**gras** [lien](http://evil2)',
        ]))->assertCreated();

        $html = (new NewAccessRequestMail(AccessRequest::firstOrFail()))->render();

        $this->assertStringNotContainsString('href="http://evil', $html);
        $this->assertStringNotContainsString('<strong>gras</strong>', $html);
        $this->assertStringContainsString('Cliquez', $html);
    }

    public function test_rate_limited_after_five_per_hour(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::URL, $this->payload(['website' => 'x']))->assertCreated();
        }
        $this->postJson(self::URL, $this->payload())->assertStatus(429);
    }

    public function test_disabled_mode_rejects_access_requests(): void
    {
        AppSetting::put('showcase.enabled', false);

        $this->postJson(self::URL, $this->payload())
            ->assertNotFound()
            ->assertJsonPath('message', 'Le mode découverte est désactivé.');
        $this->assertSame(0, AccessRequest::count());
    }

    /**
     * Active la vraie validation CSRF (désactivée par défaut sous PHPUnit).
     */
    private function enforceCsrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_post_without_csrf_token_works(): void
    {
        $this->enforceCsrf();

        // Contrôle : une route POST non exemptée du même groupe refuse sans jeton.
        $this->postJson('/api/learner/v1/session', [])->assertStatus(419);

        $this->postJson(self::URL, $this->payload())->assertCreated();
    }
}
