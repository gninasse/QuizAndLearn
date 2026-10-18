<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ShowcaseSettings;
use Tests\TestCase;

/**
 * Réglages configurables de la demande d'accès.
 */
class AccessRequestSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $handle, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
        Role::findOrCreate($role);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $access = []): array
    {
        return [
            'enabled' => true,
            'tagline' => 'Découvrez nos formations',
            'default_country_code' => '225',
            'currency' => 'XOF',
            'fee' => 5000,
            'payment_accounts' => [],
            'notification_emails' => "a@example.com\nb@example.com",
            'retention_months' => 6,
            'access_request' => array_merge($this->access(), $access),
        ];
    }

    private function access(): array
    {
        return [
            'enabled' => true,
            'button_label' => 'Rejoindre',
            'title' => 'Rejoignez-nous',
            'intro' => 'Un mot.',
            'confirmation' => 'Merci !',
            'channels' => [['type' => 'form']],
            'fields' => ['whatsapp' => 'required', 'email' => 'hidden', 'organization' => 'optional', 'group' => 'optional', 'message' => 'hidden'],
            'custom_fields' => [],
        ];
    }

    private function save(array $payload)
    {
        $admin = User::where('user_name', 'admin')->first() ?? $this->makeUser('admin', 'admin');

        return $this->actingAs($admin)
            ->putJson(route('cores.showcase.settings.save'), $payload);
    }

    public function test_defaults_match_current_behaviour(): void
    {
        $a = app(ShowcaseSettings::class)->accessRequest();

        $this->assertTrue($a['enabled']);
        $this->assertSame('Demander un accès', $a['button_label']);
        $this->assertSame('Demander un accès', $a['title']);
        $this->assertSame('Laissez-nous vos coordonnées : notre équipe vous recontacte pour ouvrir votre compte.', $a['intro']);
        $this->assertSame('Demande envoyée.', $a['confirmation']);
        $this->assertSame([['type' => 'form']], $a['channels']);
        $this->assertSame(
            ['whatsapp' => 'optional', 'email' => 'optional', 'organization' => 'optional', 'group' => 'optional', 'message' => 'optional'],
            $a['fields']
        );
        $this->assertSame([], $a['custom_fields']);
        $this->assertArrayHasKey('access_request', app(ShowcaseSettings::class)->publicPayload());
    }

    public function test_admin_saves_full_access_configuration(): void
    {
        $this->save($this->payload([
            'channels' => [
                ['type' => 'form'],
                ['type' => 'whatsapp', 'number' => '+2250700000000', 'message' => 'Bonjour'],
                ['type' => 'phone', 'number' => '+2250711111111'],
                ['type' => 'email', 'address' => 'contact@exemple.ci', 'subject' => 'Accès'],
                ['type' => 'link', 'url' => 'https://forms.gle/abc', 'label' => 'Formulaire'],
            ],
            'custom_fields' => [
                ['label' => 'Ville', 'type' => 'select', 'options' => ['Abidjan', 'Bouaké'], 'required' => true],
            ],
        ]))->assertOk();

        $block = $this->getJson('/api/showcase/v1/settings')->assertOk()->json('data.access_request');
        if ($block === null) {
            $block = $this->getJson('/api/showcase/v1/settings')->json('access_request');
        }

        $this->assertSame('Rejoindre', $block['button_label']);
        $this->assertSame('Un mot.', $block['intro']);
        $this->assertSame(['form', 'whatsapp', 'phone', 'email', 'link'], array_column($block['channels'], 'type'));
        $this->assertSame('required', $block['fields']['whatsapp']);
        $this->assertSame('hidden', $block['fields']['email']);
        $this->assertSame('Ville', $block['custom_fields'][0]['label']);
        $this->assertSame(['Abidjan', 'Bouaké'], $block['custom_fields'][0]['options']);
        $this->assertTrue($block['custom_fields'][0]['required']);
        $this->assertMatchesRegularExpression('/^cf_[a-z0-9]{6}$/', $block['custom_fields'][0]['key']);
    }

    public function test_absent_access_request_leaves_settings_unchanged(): void
    {
        $this->save($this->payload())->assertOk();
        $payload = $this->payload();
        unset($payload['access_request']);
        $this->save($payload)->assertOk();

        $this->assertSame('Rejoindre', app(ShowcaseSettings::class)->accessRequest()['button_label']);
    }

    public function test_numbers_are_normalised_to_e164(): void
    {
        $this->save($this->payload([
            'channels' => [
                ['type' => 'whatsapp', 'number' => '07 00 00 00 00', 'message' => 'Salut'],
                ['type' => 'phone', 'number' => '0711111111'],
            ],
        ]))->assertOk();

        $channels = app(ShowcaseSettings::class)->accessRequest()['channels'];
        $this->assertSame('+2250700000000', $channels[0]['number']);
        $this->assertSame('+2250711111111', $channels[1]['number']);
    }

    public function test_invalid_number_is_rejected_on_channel_field(): void
    {
        $this->save($this->payload([
            'channels' => [['type' => 'phone', 'number' => 'abc']],
        ]))->assertStatus(422)->assertJsonValidationErrors(['access_request.channels.0.number']);
    }

    public function test_enabled_without_channel_is_rejected(): void
    {
        $this->save($this->payload(['channels' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['access_request.channels'])
            ->assertJsonFragment(['Activez au moins un canal de contact.']);

        $this->assertSame('Demander un accès', app(ShowcaseSettings::class)->accessRequest()['button_label']);

        // Désactivées, les demandes n'exigent aucun canal.
        $this->save($this->payload(['enabled' => false, 'channels' => []]))->assertOk();
        $this->assertFalse(app(ShowcaseSettings::class)->accessRequest()['enabled']);
    }

    public function test_limits(): void
    {
        $cf = fn (int $i) => ['label' => "Champ $i", 'type' => 'text', 'required' => false];

        $this->save($this->payload(['custom_fields' => array_map($cf, range(1, 6))]))
            ->assertStatus(422)->assertJsonValidationErrors(['access_request.custom_fields']);

        $this->save($this->payload(['custom_fields' => [
            ['label' => 'Ville', 'type' => 'select', 'options' => ['Seul'], 'required' => false],
        ]]))->assertStatus(422)->assertJsonValidationErrors(['access_request.custom_fields.0.options']);

        $this->save($this->payload(['channels' => [
            ['type' => 'link', 'url' => 'http://exemple.ci', 'label' => 'x'],
        ]]))->assertStatus(422)->assertJsonValidationErrors(['access_request.channels.0.url']);

        $this->save($this->payload(['channels' => [['type' => 'form'], ['type' => 'form']]]))
            ->assertStatus(422)->assertJsonValidationErrors(['access_request.channels']);

        $this->save($this->payload(['title' => str_repeat('a', 81)]))
            ->assertStatus(422)->assertJsonValidationErrors(['access_request.title']);
    }

    public function test_custom_field_keys_are_generated_and_kept(): void
    {
        $this->save($this->payload(['custom_fields' => [
            ['key' => 'cf_abc123', 'label' => 'Ville', 'type' => 'text', 'required' => false],
            ['label' => 'Bio', 'type' => 'textarea', 'required' => false],
            ['key' => 'mauvaise', 'label' => 'Autre', 'type' => 'text', 'required' => false],
        ]]))->assertOk();

        $fields = app(ShowcaseSettings::class)->accessRequest()['custom_fields'];
        $this->assertSame('cf_abc123', $fields[0]['key']);
        $this->assertMatchesRegularExpression('/^cf_[a-z0-9]{6}$/', $fields[1]['key']);
        $this->assertMatchesRegularExpression('/^cf_[a-z0-9]{6}$/', $fields[2]['key']);
        $this->assertCount(3, array_unique(array_column($fields, 'key')));
    }

    public function test_trainer_forbidden(): void
    {
        $this->actingAs($this->makeUser('former', 'formateur'))
            ->putJson(route('cores.showcase.settings.save'), $this->payload())
            ->assertForbidden();
    }

    public function test_public_payload_never_contains_notification_emails(): void
    {
        $this->save($this->payload())->assertOk();

        $json = json_encode(app(ShowcaseSettings::class)->publicPayload());
        $this->assertStringNotContainsString('a@example.com', $json);
        $this->assertStringNotContainsString('notification_emails', $json);
        $this->assertStringNotContainsString('retention', $json);
    }

    public function test_settings_page_shows_access_section(): void
    {
        $admin = $this->makeUser('admin', 'admin');

        $this->actingAs($admin)->get(route('cores.showcase.settings'))
            ->assertOk()
            ->assertSee("Demande d'accès", false)
            ->assertSee("Accepter les demandes d'accès", false)
            ->assertSee('Seul le formulaire alimente le suivi des demandes.', false);
    }

    public function test_settings_page_embeds_current_config(): void
    {
        $this->save($this->payload([
            'custom_fields' => [['key' => 'cf_zz9988', 'label' => 'Ville', 'type' => 'text', 'required' => false]],
        ]))->assertOk();

        $admin = User::where('user_name', 'admin')->first();
        $this->actingAs($admin)->get(route('cores.showcase.settings'))
            ->assertOk()
            ->assertSee('cf_zz9988', false);
    }
}
