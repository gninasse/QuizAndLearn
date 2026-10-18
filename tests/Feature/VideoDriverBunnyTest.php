<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\Drivers\BunnyVideoDriver;
use Modules\Core\Services\Video\VideoManager;
use RuntimeException;
use Tests\TestCase;

class VideoDriverBunnyTest extends TestCase
{
    use RefreshDatabase;

    private const GUID = '8f0c1c1e-2b7a-4d53-9a41-0c5f6a9d1e22';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'media.video.driver' => 'bunny',
            'media.video.bunny' => [
                'library_id' => '1234',
                'api_key' => 'SECRET-API-KEY',
                'cdn_host' => 'vz-test.b-cdn.net',
                'token_key' => 'token-secret',
            ],
        ]);
        $this->user = User::create([
            'name' => 'Awa', 'last_name' => 'Test', 'user_name' => 'awa',
            'email' => 'awa@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        foreach (['super-admin', 'admin', 'trainer', 'learner'] as $role) {
            Role::findOrCreate($role);
        }
        $this->user->assignRole('learner');
        Learner::create(['user_id' => $this->user->id, 'matricule' => 'MAT-1']);
    }

    /** Remplace les simulations précédentes (Http::fake s'ajoute sinon, premier gagnant). */
    private function fakeHttp(array $stubs): void
    {
        Http::clearResolvedInstance(Factory::class);
        Http::clearResolvedInstance('http');
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    private function makeVideo(string $status = 'processing'): Video
    {
        return Video::create([
            'ref' => self::GUID, 'driver' => 'bunny', 'location' => self::GUID, 'status' => $status,
            'mime' => 'video/mp4', 'size' => 1, 'original_name' => 'a.mp4', 'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_store_creates_then_uploads(): void
    {
        $this->fakeHttp([
            'video.bunnycdn.com/library/1234/videos' => Http::response(['guid' => self::GUID], 200),
            'video.bunnycdn.com/library/1234/videos/'.self::GUID => Http::response(['success' => true], 200),
        ]);

        $video = app(VideoManager::class)->store(UploadedFile::fake()->create('cours.mp4', 10, 'video/mp4'), $this->user);

        $this->assertSame('bunny', $video->driver);
        $this->assertSame(self::GUID, $video->ref);
        $this->assertSame(self::GUID, $video->location);
        $this->assertSame('processing', $video->status);
        Http::assertSentInOrder([
            fn ($r) => $r->method() === 'POST' && $r->url() === 'https://video.bunnycdn.com/library/1234/videos'
                && $r->header('AccessKey')[0] === 'SECRET-API-KEY' && $r['title'] === 'cours.mp4',
            fn ($r) => $r->method() === 'PUT' && $r->url() === 'https://video.bunnycdn.com/library/1234/videos/'.self::GUID
                && $r->header('AccessKey')[0] === 'SECRET-API-KEY',
        ]);
    }

    public function test_failed_upload_deletes_created_video(): void
    {
        $this->fakeHttp([
            'video.bunnycdn.com/library/1234/videos' => Http::response(['guid' => self::GUID], 200),
            'video.bunnycdn.com/library/1234/videos/'.self::GUID => fn ($r) => $r->method() === 'PUT'
                ? Http::response('', 500)
                : Http::response(['success' => true], 200),
        ]);

        try {
            app(VideoManager::class)->store(UploadedFile::fake()->create('cours.mp4', 10, 'video/mp4'), $this->user);
            $this->fail('Exception attendue.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('500', $e->getMessage());
        }

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->url() === 'https://video.bunnycdn.com/library/1234/videos/'.self::GUID);
        $this->assertSame(0, Video::count());
    }

    public function test_status_mapping(): void
    {
        $driver = app(VideoManager::class)->driver('bunny');
        $this->assertInstanceOf(BunnyVideoDriver::class, $driver);

        foreach ([0 => 'processing', 1 => 'processing', 2 => 'processing', 3 => 'processing', 4 => 'ready', 5 => 'failed', 6 => 'failed'] as $code => $expected) {
            $this->fakeHttp(['video.bunnycdn.com/*' => Http::response(['guid' => self::GUID, 'status' => $code], 200)]);
            $video = Video::where('ref', self::GUID)->first() ?? $this->makeVideo();
            $video->update(['status' => 'processing']);

            $this->assertSame($expected, $driver->refreshStatus($video), "status {$code}");
            $this->assertSame($expected, $video->fresh()->status);
        }
    }

    public function test_refresh_status_keeps_status_on_transient_error(): void
    {
        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response('boom', 500)]);
        $video = $this->makeVideo();

        $this->assertSame('processing', app(VideoManager::class)->driver('bunny')->refreshStatus($video));
    }

    public function test_playback_url_is_signed_and_expires_in_15_minutes(): void
    {
        $this->travelTo(now()->startOfSecond());
        $video = $this->makeVideo('ready');

        $source = app(VideoManager::class)->playback($video, null);

        $expires = now()->addMinutes(15)->getTimestamp();
        $path = '/'.self::GUID.'/';
        $token = 'HS256-'.rtrim(strtr(base64_encode(hash_hmac('sha256', $path.$expires.'token_path='.$path, 'token-secret', true)), '+/', '-_'), '=');

        $this->assertSame('hls', $source->type);
        $this->assertSame($expires, $source->expiresAt->getTimestamp());
        $this->assertSame(
            "https://vz-test.b-cdn.net/bcdn_token={$token}&token_path=".rawurlencode($path)."&expires={$expires}/".self::GUID.'/playlist.m3u8',
            $source->url
        );
        $this->assertStringNotContainsString('token-secret', $source->url);
        $this->assertStringNotContainsString('SECRET-API-KEY', $source->url);
    }

    public function test_api_error_marks_failed_without_leaking_key(): void
    {
        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response('{"Message":"Unauthorized SECRET-API-KEY"}', 401)]);

        try {
            app(VideoManager::class)->store(UploadedFile::fake()->create('cours.mp4', 10, 'video/mp4'), $this->user);
            $this->fail('Exception attendue.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('SECRET-API-KEY', $e->getMessage());
            $this->assertStringContainsString('401', $e->getMessage());
        }

        // Vidéo supprimée côté Bunny (404) → échec définitif.
        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response('', 404)]);
        $this->assertSame('failed', app(VideoManager::class)->driver('bunny')->refreshStatus($this->makeVideo()));
    }

    public function test_delete_calls_api(): void
    {
        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response(['success' => true], 200)]);
        app(VideoManager::class)->driver('bunny')->delete($this->makeVideo('ready'));

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->url() === 'https://video.bunnycdn.com/library/1234/videos/'.self::GUID);
    }

    public function test_health_check(): void
    {
        $this->fakeHttp(['video.bunnycdn.com/library/1234/videos*' => Http::response(['items' => []], 200)]);
        $this->assertTrue(app(VideoManager::class)->driver('bunny')->healthCheck()['ok']);

        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response('no', 401)]);
        $res = app(VideoManager::class)->driver('bunny')->healthCheck();
        $this->assertFalse($res['ok']);
        $this->assertStringNotContainsString('SECRET-API-KEY', $res['message']);

        config(['media.video.bunny.api_key' => null]);
        $this->assertFalse((new BunnyVideoDriver(config('media.video.bunny')))->healthCheck()['ok']);
    }

    public function test_playback_api_refreshes_processing_video_once_per_minute(): void
    {
        $video = $this->makeVideo();
        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response(['status' => 4], 200)]);

        $this->actingAs($this->user)->getJson('/api/learner/v1/videos/'.self::GUID.'/playback')->assertOk()->assertJsonPath('type', 'hls');
        $this->assertSame('ready', $video->fresh()->status);

        $video->fresh()->update(['status' => 'processing']);
        $this->fakeHttp(['video.bunnycdn.com/*' => Http::response(['status' => 4], 200)]);
        $this->actingAs($this->user)->getJson('/api/learner/v1/videos/'.self::GUID.'/playback')->assertStatus(202);
        Http::assertNothingSent();
    }
}
