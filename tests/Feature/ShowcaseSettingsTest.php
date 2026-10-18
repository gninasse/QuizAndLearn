<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ShowcaseSettings;
use Modules\Core\Support\PhoneNumber;
use Tests\TestCase;

/**
 * Réglages du mode découverte et page de confidentialité.
 */
class ShowcaseSettingsTest extends TestCase
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

    private function payload(array $override = []): array
    {
        return array_merge([
            'enabled' => true,
            'tagline' => 'Découvrez nos formations',
            'default_country_code' => '221',
            'currency' => 'XOF',
            'fee' => 5000,
            'payment_accounts' => [
                ['operator' => 'wave', 'number' => '0700000000', 'holder' => 'Awa Diop'],
            ],
            'payment_reference' => 'Votre nom',
            'instructions' => 'Envoyez le montant puis attendez notre appel.',
            'notification_emails' => "a@example.com\nb@example.com",
            'privacy_text' => null,
            'privacy_url' => null,
            'retention_months' => 6,
        ], $override);
    }

    public function test_defaults_when_nothing_saved(): void
    {
        $s = app(ShowcaseSettings::class);

        $this->assertFalse($s->enabled());
        $this->assertSame('225', $s->defaultCountryCode());
        $this->assertSame('XOF', $s->currency());
        $this->assertNull($s->fee());
        $this->assertSame([], $s->paymentAccounts());
        $this->assertSame([], $s->notificationEmails());
        $this->assertSame(12, $s->retentionMonths());
        $this->assertNull($s->privacyUrl());
        $this->assertSame('/confidentialite', $s->publicPayload()['privacy_url']);
        $this->assertSame('+2250700000000', PhoneNumber::normalize('0700000000'));
    }

    public function test_admin_saves_settings_and_payload_exposes_only_public_fields(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload())
            ->assertOk()->assertJson(['success' => true]);

        $s = app(ShowcaseSettings::class);
        $this->assertTrue($s->enabled());
        $this->assertSame(['a@example.com', 'b@example.com'], $s->notificationEmails());
        $this->assertSame(6, $s->retentionMonths());
        $this->assertSame('+221700000000', PhoneNumber::normalize('700000000'));

        $payload = $s->publicPayload();
        $this->assertEqualsCanonicalizing(
            ['enabled', 'brand', 'registration', 'default_country_code', 'privacy_url'],
            array_keys($payload)
        );
        $this->assertSame(['name', 'tagline', 'logo'], array_keys($payload['brand']));
        $this->assertSame('Découvrez nos formations', $payload['brand']['tagline']);
        $this->assertSame(['fee', 'currency', 'accounts', 'reference', 'instructions'], array_keys($payload['registration']));
        $this->assertSame(5000, $payload['registration']['fee']);
        $this->assertSame('wave', $payload['registration']['accounts'][0]['operator']);
        $this->assertStringNotContainsString('a@example.com', json_encode($payload));
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload([
            'payment_accounts' => [['operator' => 'bitcoin', 'number' => '1', 'holder' => 'x']],
        ]))->assertStatus(422);
    }

    public function test_currency_is_an_uppercased_iso_code(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload(['currency' => 'xof']))
            ->assertOk();
        $this->assertSame('XOF', app(ShowcaseSettings::class)->currency());

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload(['currency' => 'FCFA']))
            ->assertStatus(422);
        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload(['currency' => 'X1F']))
            ->assertStatus(422);
        $this->assertSame('XOF', app(ShowcaseSettings::class)->currency());
    }

    public function test_privacy_url_must_be_http(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload(['privacy_url' => 'javascript://x']))
            ->assertStatus(422);
        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload(['privacy_url' => 'ftp://example.com/x']))
            ->assertStatus(422);
        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload(['privacy_url' => 'https://example.com/privacy']))
            ->assertOk();
    }

    public function test_trainer_cannot_open_settings(): void
    {
        $trainer = $this->makeUser('prof', 'trainer');

        $this->actingAs($trainer)->get(route('cores.showcase.settings'))->assertForbidden();
        $this->actingAs($trainer)->putJson(route('cores.showcase.settings.save'), $this->payload())->assertForbidden();

        $admin = $this->makeUser('boss', 'admin');
        $this->actingAs($admin)->get(route('cores.showcase.settings'))->assertOk()->assertSee('Mode découverte');
    }

    public function test_privacy_page_shows_saved_text_or_redirects_to_url(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->get('/confidentialite')->assertOk()->assertSee('Politique de confidentialité');

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload([
            'privacy_text' => 'Nos règles maison.',
        ]))->assertOk();
        auth()->logout();
        $this->get('/confidentialite')->assertOk()->assertSee('Nos règles maison.');

        $this->actingAs($admin)->putJson(route('cores.showcase.settings.save'), $this->payload([
            'privacy_url' => 'https://example.org/privacy',
        ]))->assertOk();
        $this->get('/confidentialite')->assertRedirect('https://example.org/privacy');
        $this->assertSame('https://example.org/privacy', app(ShowcaseSettings::class)->publicPayload()['privacy_url']);
    }
}
