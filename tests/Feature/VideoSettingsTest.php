<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Video\VideoDriver;
use Modules\Core\Services\Video\VideoManager;
use Modules\Core\Services\Video\VideoSettings;
use Tests\TestCase;

class VideoSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $handle, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate($role);
        $user->assignRole($role);

        return $user;
    }

    public function test_defaults_enable_youtube_and_vimeo(): void
    {
        $s = app(VideoSettings::class);
        $this->assertTrue($s->embedsEnabled());
        $this->assertSame(['youtube', 'vimeo'], $s->providers());
    }

    public function test_admin_saves_embed_settings(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), [
            'preset' => 'classe',
            'video' => ['embeds' => ['enabled' => true, 'providers' => ['vimeo']]],
        ])->assertOk();

        $s = app(VideoSettings::class);
        $this->assertTrue($s->embedsEnabled());
        $this->assertSame(['vimeo'], $s->providers());

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), [
            'preset' => 'classe',
            'video' => ['embeds' => ['enabled' => false, 'providers' => []]],
        ])->assertOk();
        $this->assertFalse(app(VideoSettings::class)->embedsEnabled());

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), [
            'preset' => 'classe',
            'video' => ['embeds' => ['enabled' => true, 'providers' => ['dailymotion']]],
        ])->assertStatus(422);
    }

    public function test_absent_video_block_leaves_settings_unchanged(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        AppSetting::put('video.embeds.enabled', false);

        $this->actingAs($admin)->putJson(route('cores.settings.general.save'), ['preset' => 'classe'])->assertOk();

        $this->assertFalse(app(VideoSettings::class)->embedsEnabled());
    }

    public function test_trainer_forbidden(): void
    {
        $trainer = $this->makeUser('coach', 'trainer');

        $this->actingAs($trainer)->putJson(route('cores.settings.general.save'), [
            'preset' => 'classe', 'video' => ['embeds' => ['enabled' => false, 'providers' => []]],
        ])->assertForbidden();
        $this->actingAs($trainer)->postJson(route('cores.settings.video.test'))->assertForbidden();
        $this->assertNull(AppSetting::get('video.embeds.enabled'));
    }

    public function test_health_check_endpoint_returns_driver_result(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $driver = \Mockery::mock(VideoDriver::class);
        $driver->shouldReceive('healthCheck')->once()->andReturn(['ok' => true, 'message' => 'Tout va bien.']);
        $manager = \Mockery::mock(VideoManager::class)->makePartial();
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(VideoManager::class, $manager);

        $this->actingAs($admin)->postJson(route('cores.settings.video.test'))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'message' => 'Tout va bien.']);
    }

    public function test_settings_page_never_shows_secrets(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        config([
            'media.video.driver' => 'bunny',
            'media.video.bunny.library_id' => '12345',
            'media.video.bunny.api_key' => 'SECRET-API-KEY-VALUE',
            'media.video.bunny.cdn_host' => 'secret-host.b-cdn.net',
            'media.video.bunny.token_key' => null,
        ]);

        $html = $this->actingAs($admin)->get(route('cores.settings.general'))->assertOk()->getContent();

        $this->assertStringNotContainsString('SECRET-API-KEY-VALUE', $html);
        $this->assertStringNotContainsString('secret-host.b-cdn.net', $html);
        $this->assertStringContainsString('BUNNY_STREAM_API_KEY', $html);
        $this->assertStringContainsString('configurée', $html);
        $this->assertStringContainsString('manquante', $html);
    }

    public function test_learner_me_and_showcase_expose_embed_settings(): void
    {
        $user = $this->makeUser('apprenant', 'learner');
        Learner::create(['user_id' => $user->id, 'matricule' => 'M1']);
        $this->withoutExceptionHandling();
        AppSetting::put('video.embeds.providers', ['youtube']);

        $this->actingAs($user)->getJson(route('learn.v1.me'))
            ->assertOk()
            ->assertJsonPath('user.video.embeds.enabled', true)
            ->assertJsonPath('user.video.embeds.providers', ['youtube']);

        AppSetting::put('showcase.enabled', true);
        $this->getJson(route('showcase.v1.settings'))
            ->assertJsonPath('video.embeds.providers', ['youtube']);
    }

    public function test_editor_hides_embed_button_when_disabled(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        AppSetting::put('video.embeds.enabled', false);
        $article = \Modules\Core\Models\Article::create(['title' => 'A', 'content' => '<p>x</p>', 'is_active' => false, 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)->get(route('cores.editor.articles.edit', $article->id))->getContent();
        $this->assertStringNotContainsString('data-element="video-placeholder"', $html);
        $this->assertStringContainsString('VIDEO_EMBEDS_ENABLED = false', $html);
    }

    public function test_health_check_never_forwards_exception_text(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $driver = \Mockery::mock(VideoDriver::class);
        $driver->shouldReceive('healthCheck')->andThrow(new \RuntimeException('AKIAFAKEKEY123 bucket-secret'));
        $manager = \Mockery::mock(VideoManager::class)->makePartial();
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(VideoManager::class, $manager);

        $res = $this->actingAs($admin)->postJson(route('cores.settings.video.test'))->assertOk();
        $this->assertFalse($res->json('ok'));
        $this->assertStringNotContainsString('AKIAFAKEKEY123', $res->getContent());
    }

    public function test_s3_health_check_message_hides_exception_detail(): void
    {
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('videos')
            ->andThrow(new \RuntimeException('AKIAFAKEKEY123 https://bucket.s3.example/key'));
        $result = (new \Modules\Core\Services\Video\Drivers\S3HlsVideoDriver('videos'))->healthCheck();

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('AKIAFAKEKEY123', $result['message']);
    }
}
