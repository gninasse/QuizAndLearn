<?php

namespace Tests\Feature;

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
            'started_at' => now()->getTimestampMs() - 10000,
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
        $this->postJson(self::URL, $this->payload(['started_at' => now()->getTimestampMs() - 500]))
            ->assertCreated()->assertJsonPath('message', 'Demande envoyée.');

        $this->assertSame(0, AccessRequest::count());
    }

    public function test_second_request_from_same_phone_updates_open_one(): void
    {
        $this->postJson(self::URL, $this->payload())->assertCreated();
        $this->postJson(self::URL, $this->payload(['first_name' => 'Aïcha']))->assertCreated();

        $this->assertSame(1, AccessRequest::count());
        $this->assertSame('Aïcha', AccessRequest::first()->first_name);
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

        $this->postJson(self::URL, $this->payload(['group_id' => $closed->id]))->assertStatus(422);
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

    public function test_post_without_csrf_token_works(): void
    {
        $this->withMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->postJson(self::URL, $this->payload())->assertCreated();
    }
}
