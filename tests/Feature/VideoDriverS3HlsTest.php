<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Core\Jobs\TranscodeVideoToHls;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\Drivers\S3HlsVideoDriver;
use Modules\Core\Services\Video\Transcoder;
use Modules\Core\Services\Video\VideoManager;
use RuntimeException;
use Tests\TestCase;

class VideoDriverS3HlsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.video.driver' => 's3_hls', 'media.video.s3_hls.disk' => 'videos']);
        Storage::fake('videos')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration) => 'https://s3.test/'.$path.'?exp='.$expiration->getTimestamp().'&sig=abc'
        );
        Storage::fake('videos_health');
        $this->user = User::create([
            'name' => 'Awa', 'last_name' => 'Test', 'user_name' => 'awa',
            'email' => 'awa@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
    }

    private function fakeTranscoder(bool $fail = false): void
    {
        $this->app->instance(Transcoder::class, new class($fail) implements Transcoder
        {
            public function __construct(private bool $fail) {}

            public function toHls(string $inputPath, string $outputDir): array
            {
                if ($this->fail) {
                    throw new RuntimeException('ffmpeg a échoué');
                }
                file_put_contents($outputDir.'/index.m3u8', "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:6\n#EXTINF:6.0,\nseg_0000.ts\n#EXTINF:4.0,\nseg_0001.ts\n#EXT-X-ENDLIST\n");
                file_put_contents($outputDir.'/seg_0000.ts', 'a');
                file_put_contents($outputDir.'/seg_0001.ts', 'b');

                return [$outputDir.'/index.m3u8', $outputDir.'/seg_0000.ts', $outputDir.'/seg_0001.ts'];
            }
        });
    }

    private function storeVideo(): Video
    {
        return app(VideoManager::class)->store(UploadedFile::fake()->create('cours.mp4', 10, 'video/mp4'), $this->user);
    }

    public function test_store_uploads_original_and_queues_transcode(): void
    {
        Queue::fake();
        $video = $this->storeVideo();

        $this->assertSame('s3_hls', $video->driver);
        $this->assertSame('processing', $video->status);
        $this->assertSame("originals/{$video->ref}.mp4", $video->location);
        Storage::disk('videos')->assertExists($video->location);
        Queue::assertPushed(TranscodeVideoToHls::class, fn ($j) => $j->video->is($video) && $j->tries === 1 && $j->timeout === 4000 && $j->queue === 'videos');
    }

    public function test_job_uploads_hls_and_marks_ready(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        $this->fakeTranscoder();

        (new TranscodeVideoToHls($video))->handle(app(Transcoder::class), app(VideoManager::class));

        $this->assertSame('ready', $video->fresh()->status);
        Storage::disk('videos')->assertExists("hls/{$video->ref}/index.m3u8");
        Storage::disk('videos')->assertExists("hls/{$video->ref}/seg_0001.ts");
        $this->assertDirectoryDoesNotExist(storage_path("app/tmp/hls-{$video->ref}"));
    }

    public function test_job_failure_marks_failed(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        $this->fakeTranscoder(fail: true);

        (new TranscodeVideoToHls($video))->handle(app(Transcoder::class), app(VideoManager::class));

        $this->assertSame('failed', $video->fresh()->status);
        Storage::disk('videos')->assertMissing("hls/{$video->ref}/index.m3u8");
        $this->assertDirectoryDoesNotExist(storage_path("app/tmp/hls-{$video->ref}"));
    }

    public function test_failed_hook_marks_failed_and_cleans_partial_output(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        Storage::disk('videos')->put("hls/{$video->ref}/seg_0000.ts", 'partiel');
        $tmp = TranscodeVideoToHls::tmpDir($video);
        mkdir($tmp, 0777, true);
        file_put_contents("{$tmp}/original.mp4", 'x');

        (new TranscodeVideoToHls($video))->failed(new RuntimeException('timeout'));

        $this->assertSame('failed', $video->fresh()->status);
        Storage::disk('videos')->assertMissing("hls/{$video->ref}/seg_0000.ts");
        Storage::disk('videos')->assertExists($video->location);
        $this->assertDirectoryDoesNotExist($tmp);
    }

    public function test_playlist_rewrites_segments_to_signed_urls(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        $this->fakeTranscoder();
        (new TranscodeVideoToHls($video))->handle(app(Transcoder::class), app(VideoManager::class));

        $source = app(VideoManager::class)->playback($video->fresh(), null);
        $this->assertSame('hls', $source->type);
        $this->assertNotNull($source->expiresAt);
        $this->assertStringContainsString('signature=', $source->url);

        // Sans session : la signature fait foi (invités compris).
        $body = $this->get($source->url)->assertOk()->getContent();
        $this->assertStringContainsString('#EXTM3U', $body);
        $this->assertStringContainsString("https://s3.test/hls/{$video->ref}/seg_0000.ts?exp=", $body);
        $this->assertStringContainsString("https://s3.test/hls/{$video->ref}/seg_0001.ts?exp=", $body);
        $this->assertDoesNotMatchRegularExpression('/^seg_/m', $body);
    }

    public function test_playlist_route_rejects_bad_signature_and_bad_filename(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        $this->fakeTranscoder();
        (new TranscodeVideoToHls($video))->handle(app(Transcoder::class), app(VideoManager::class));

        $this->get(route('learn.v1.videos.hls', [$video->ref, 'index.m3u8']))->assertForbidden();

        $bad = URL::temporarySignedRoute('learn.v1.videos.hls', now()->addMinutes(15), [$video->ref, '../secret.m3u8']);
        $this->get($bad)->assertNotFound();
        $ts = URL::temporarySignedRoute('learn.v1.videos.hls', now()->addMinutes(15), [$video->ref, 'seg_0000.ts']);
        $this->get($ts)->assertNotFound();

        $expired = URL::temporarySignedRoute('learn.v1.videos.hls', now()->subMinute(), [$video->ref, 'index.m3u8']);
        $this->get($expired)->assertForbidden();
    }

    public function test_playback_endpoint_returns_signed_hls_url_for_learner_and_guest_contract(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        $video->update(['status' => 'ready']);

        $driver = app(VideoManager::class)->driver('s3_hls');
        $this->assertInstanceOf(S3HlsVideoDriver::class, $driver);
        $this->assertSame('hls', $driver->playback($video, null)->type);
        $this->assertSame('hls', $driver->playback($video, $this->user)->type);
    }

    public function test_delete_removes_original_and_hls(): void
    {
        Queue::fake();
        $video = $this->storeVideo();
        $disk = Storage::disk('videos');
        $disk->put("hls/{$video->ref}/index.m3u8", 'x');

        app(VideoManager::class)->driver('s3_hls')->delete($video);

        $disk->assertMissing($video->location);
        $disk->assertMissing("hls/{$video->ref}/index.m3u8");
    }

    public function test_health_check(): void
    {
        Process::fake();
        $result = app(VideoManager::class)->driver('s3_hls')->healthCheck();
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('ffmpeg disponible', $result['message']);
        // Test de connexion sur le disque dédié (délais courts), sans résidu.
        $this->assertSame([], Storage::disk('videos_health')->files('healthcheck'));
        Process::assertRan(fn ($process) => $process->command === ['ffmpeg', '-version']);

        Storage::shouldReceive('disk')->with('videos')->andThrow(new RuntimeException('boom'));
        $driver = new S3HlsVideoDriver('videos');
        $this->assertFalse($driver->healthCheck()['ok']);
    }

    public function test_health_check_reports_missing_ffmpeg(): void
    {
        Process::fake(['*' => Process::result(errorOutput: '/opt/secret/ffmpeg: not found', exitCode: 127)]);
        $result = (new S3HlsVideoDriver('videos', null, '/opt/secret/ffmpeg'))->healthCheck();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Stockage S3 accessible.', $result['message']);
        $this->assertStringContainsString('ffmpeg introuvable', $result['message']);
        $this->assertStringNotContainsString('/opt/secret', $result['message']);
    }
}
