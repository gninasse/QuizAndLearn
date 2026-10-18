<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\User;
use Modules\Core\Models\Role;
use Tests\TestCase;

class AccessRequestDynamicSubmitTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/showcase/v1/access-requests';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
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

    public function test_closed_returns_403_with_code(): void
    {
        AppSetting::put('showcase.access.enabled', false);

        $this->postJson(self::URL, ['website' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'access_requests_closed')
            ->assertJsonPath('message', 'Les demandes d\'accès sont fermées pour le moment.');
        $this->assertSame(0, AccessRequest::count());
    }

    public function test_closed_when_form_channel_absent(): void
    {
        AppSetting::put('showcase.access.channels', [['type' => 'phone', 'number' => '+2250700000000']]);

        $this->postJson(self::URL, $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('code', 'access_requests_closed');
    }

    public function test_required_optional_hidden_fields(): void
    {
        AppSetting::put('showcase.access.fields', ['organization' => 'required', 'email' => 'hidden', 'message' => 'optional']);

        $this->postJson(self::URL, $this->payload())->assertStatus(422)->assertJsonValidationErrors(['organization']);

        $this->postJson(self::URL, $this->payload(['organization' => 'ACME', 'email' => 'awa@example.com']))
            ->assertCreated()
            ->assertJsonPath('confirmation', 'Demande envoyée.');

        $r = AccessRequest::firstOrFail();
        $this->assertSame('ACME', $r->organization);
        $this->assertNull($r->email);
    }

    public function test_hidden_whatsapp_and_group_are_ignored(): void
    {
        AppSetting::put('showcase.access.fields', ['whatsapp' => 'hidden', 'group' => 'hidden']);

        $this->postJson(self::URL, $this->payload(['whatsapp' => '0102030405', 'group_id' => 999]))->assertCreated();

        $r = AccessRequest::firstOrFail();
        $this->assertNull($r->whatsapp);
        $this->assertNull($r->group_id);
    }

    private function customFields(): array
    {
        return [
            ['key' => 'cf_city', 'label' => 'Ville', 'type' => 'select', 'options' => ['Abidjan', 'Bouaké'], 'required' => true],
            ['key' => 'cf_why', 'label' => 'Motivation', 'type' => 'textarea', 'options' => [], 'required' => false],
            ['key' => 'cf_job', 'label' => 'Métier', 'type' => 'text', 'options' => [], 'required' => false],
        ];
    }

    public function test_custom_fields_validated_and_stored_with_label(): void
    {
        AppSetting::put('showcase.access.custom_fields', $this->customFields());

        $this->postJson(self::URL, $this->payload())->assertStatus(422)->assertJsonValidationErrors(['extra.cf_city']);
        $this->postJson(self::URL, $this->payload(['extra' => ['cf_city' => 'Paris']]))->assertStatus(422);

        $this->postJson(self::URL, $this->payload(['extra' => ['cf_city' => 'Bouaké', 'cf_why' => 'Apprendre', 'cf_job' => '']]))
            ->assertCreated();

        $this->assertSame([
            ['key' => 'cf_city', 'label' => 'Ville', 'value' => 'Bouaké'],
            ['key' => 'cf_why', 'label' => 'Motivation', 'value' => 'Apprendre'],
        ], AccessRequest::firstOrFail()->extra_fields);
    }

    public function test_old_request_keeps_label_after_field_removed(): void
    {
        AppSetting::put('showcase.access.custom_fields', $this->customFields());
        $this->postJson(self::URL, $this->payload(['extra' => ['cf_city' => 'Abidjan']]))->assertCreated();

        AppSetting::put('showcase.access.custom_fields', []);

        $this->assertSame(
            [['key' => 'cf_city', 'label' => 'Ville', 'value' => 'Abidjan']],
            AccessRequest::firstOrFail()->fresh()->extra_fields,
        );
    }

    public function test_admin_detail_returns_extra_fields(): void
    {
        AppSetting::put('showcase.access.custom_fields', $this->customFields());
        $this->postJson(self::URL, $this->payload(['extra' => ['cf_city' => 'Abidjan']]))->assertCreated();
        $r = AccessRequest::firstOrFail();

        $admin = User::create([
            'name' => 'Boss', 'last_name' => 'Test', 'user_name' => 'boss',
            'email' => 'boss@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        $this->actingAs($admin)->getJson(route('cores.access-requests.show', $r->id))
            ->assertOk()
            ->assertJsonFragment(['key' => 'cf_city', 'label' => 'Ville', 'value' => 'Abidjan']);
    }
}
