<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Terminology;
use Tests\TestCase;

class TerminologySettingsTest extends TestCase
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

    public function test_admin_saves_classe_and_backoffice_uses_it(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->get(route('cores.settings.general'))->assertOk()->assertSee('Terminologie');
        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'classe'])
            ->assertOk()->assertJsonPath('success', true);

        $this->actingAs($admin)->get(route('cores.groups.index'))
            ->assertOk()
            ->assertSee('Classes')
            ->assertDontSee('Groupes')
            ->assertSee('window.APP_TERMS', false);
    }

    public function test_default_backoffice_keeps_groupe_wording(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->get(route('cores.groups.index'))->assertOk()->assertSee('Groupes');
    }

    public function test_trainer_is_forbidden(): void
    {
        $trainer = $this->makeUser('coach', 'trainer');

        $this->actingAs($trainer)->get(route('cores.settings.general'))->assertForbidden();
        $this->actingAs($trainer)->putJson(route('cores.settings.general.save'), ['preset' => 'classe'])->assertForbidden();
    }

    public function test_custom_requires_valid_words(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'custom'])
            ->assertStatus(422)->assertJsonValidationErrors(['singular', 'plural']);
        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'custom', 'singular' => 'a<b>', 'plural' => str_repeat('x', 31)])
            ->assertStatus(422)->assertJsonValidationErrors(['singular', 'plural']);
        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors(['preset']);
    }

    public function test_custom_is_saved(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), [
            'preset' => 'custom', 'singular' => 'équipe', 'plural' => 'équipes', 'feminine' => true,
        ])->assertOk();

        $this->assertSame("l'équipe", app(Terminology::class)->label('the_group'));
    }

    public function test_showcase_settings_expose_terms(): void
    {
        app(Terminology::class)->save(['preset' => 'promotion']);

        $this->getJson(route('showcase.v1.settings'))
            ->assertOk()
            ->assertJsonPath('terms.group', 'promotion')
            ->assertJsonPath('terms.the_group', 'la promotion');
    }
}
