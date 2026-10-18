<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Article;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\GuestToken;
use Modules\Core\Services\Video\PlaybackSource;
use Modules\Core\Services\Video\VideoDriver;
use Modules\Core\Services\Video\VideoManager;
use Tests\TestCase;

class VideoDriverLocalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        foreach (['super-admin', 'admin', 'trainer', 'learner'] as $role) {
            Role::findOrCreate($role);
        }
        $this->user = $this->makeUser('awa');
        $this->user->assignRole('learner');
        Learner::create(['user_id' => $this->user->id, 'matricule' => 'MAT-1']);
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => true, 'password' => bcrypt('password'),
        ]);
    }

    private function makeVideo(array $attrs = []): Video
    {
        return Video::create($attrs + [
            'driver' => 'local', 'location' => 'uploads/media/a.mp4', 'status' => 'ready',
        ]);
    }

    public function test_upload_video_creates_video_row_and_returns_ref(): void
    {
        $admin = $this->makeUser('root');
        $admin->assignRole('super-admin');

        $res = $this->actingAs($admin)->postJson('/cores/media/upload', [
            'file' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
        ])->assertOk()->assertJsonPath('type', 'video')->assertJsonPath('status', 'ready');

        $video = Video::sole();
        $this->assertSame($video->ref, $res->json('ref'));
        $this->assertSame('local', $video->driver);
        $this->assertSame($admin->id, $video->uploaded_by);
        $this->assertStringContainsString('/storage/'.$video->location, $res->json('url'));
        Storage::disk('public')->assertExists($video->location);
    }

    public function test_playback_local_returns_storage_url(): void
    {
        $video = $this->makeVideo();

        $this->actingAs($this->user)->getJson("/api/learner/v1/videos/{$video->ref}/playback")
            ->assertOk()
            ->assertExactJson(['type' => 'mp4', 'url' => '/storage/uploads/media/a.mp4', 'expires_at' => null]);
    }

    public function test_playback_statuses_202_410_404(): void
    {
        $processing = $this->makeVideo(['status' => 'processing']);
        $failed = $this->makeVideo(['status' => 'failed']);
        $this->actingAs($this->user);

        $this->getJson("/api/learner/v1/videos/{$processing->ref}/playback")
            ->assertStatus(202)->assertExactJson(['status' => 'processing']);
        $this->getJson("/api/learner/v1/videos/{$failed->ref}/playback")
            ->assertStatus(410)->assertExactJson(['status' => 'failed']);
        $this->getJson('/api/learner/v1/videos/'.fake()->uuid().'/playback')->assertNotFound();
    }

    public function test_existing_video_keeps_its_driver_after_config_change(): void
    {
        $fake = new class implements VideoDriver
        {
            public function name(): string { return 'fake'; }
            public function store(UploadedFile $file): array { return ['location' => 'fake-guid', 'status' => 'ready']; }
            public function playback(Video $video, User $viewer): PlaybackSource { return new PlaybackSource('hls', 'https://cdn.test/'.$video->location.'.m3u8', now()->addMinutes(15)); }
            public function delete(Video $video): void {}
            public function refreshStatus(Video $video): string { return $video->status; }
            public function healthCheck(): array { return ['ok' => true, 'message' => 'ok']; }
        };
        $manager = app(VideoManager::class);
        $manager->extend('fake', fn () => $fake);

        $old = $this->makeVideo();
        config(['media.video.driver' => 'fake']);

        $new = $manager->store(UploadedFile::fake()->create('n.mp4', 10, 'video/mp4'), $this->user);
        $this->assertSame('fake', $new->driver);
        $this->assertSame('local', $old->fresh()->driver);

        $this->assertSame('mp4', $manager->playback($old->fresh(), $this->user)->type);
        $this->assertSame('hls', $manager->playback($new, $this->user)->type);
        $this->assertSame('hls', $manager->playback($new, $this->user)->toArray()['type']);
    }

    public function test_guest_playback_only_for_showcase_refs(): void
    {
        AppSetting::put('showcase.enabled', true);
        $shown = $this->makeVideo();
        $hidden = $this->makeVideo(['location' => 'uploads/media/b.mp4']);
        Article::create([
            'title' => 'Vitrine',
            'content' => '<div data-video-ref="'.$shown->ref.'"></div>',
            'is_active' => true, 'is_showcase' => true, 'estimated_reading_time' => 1,
        ]);
        $token = GuestToken::issue();

        $this->getJson("/api/showcase/v1/videos/{$shown->ref}/playback")->assertForbidden();
        $this->withHeader('X-Guest-Token', $token)->getJson("/api/showcase/v1/videos/{$shown->ref}/playback")
            ->assertOk()->assertJsonPath('url', '/storage/uploads/media/a.mp4');
        $this->withHeader('X-Guest-Token', $token)->getJson("/api/showcase/v1/videos/{$hidden->ref}/playback")
            ->assertForbidden();    }

    public function test_impersonation_can_read_playback(): void
    {
        $admin = $this->makeUser('root');
        $admin->assignRole('super-admin');
        $learner = $this->user->learner;
        $learner->groups()->attach(Group::create(['name' => 'Promo A', 'is_active' => true])->id);
        $video = $this->makeVideo();

        $url = $this->actingAs($admin)
            ->postJson("/cores/learners/{$learner->id}/impersonate")->assertOk()->json('url');
        $token = substr($url, strpos($url, '#consultation=') + strlen('#consultation='));
        $this->assertNotNull(ImpersonationSession::first());

        $this->actingAs($admin)->withHeader('X-Impersonation-Token', $token)
            ->getJson("/api/learner/v1/videos/{$video->ref}/playback")
            ->assertOk()->assertJsonPath('type', 'mp4');
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(VideoManager::class)->driver('nope');
    }
}
