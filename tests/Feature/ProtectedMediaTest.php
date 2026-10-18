<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * Les médias uploadés ne sont plus publics : /storage/{path} exige un
 * utilisateur connecté (session ou Bearer), actif et non suspendu.
 */
class ProtectedMediaTest extends TestCase
{
    use RefreshDatabase;

    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('uploads/media/photo.png', 'PNGDATA');

        $user = User::create([
            'name' => 'Awa', 'last_name' => 'Test', 'user_name' => 'awa',
            'email' => 'awa@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        $this->learner = Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-1']);
    }

    public function test_guest_cannot_fetch_media(): void
    {
        $this->get('/storage/uploads/media/photo.png')->assertForbidden();
    }

    public function test_learner_session_can_fetch_media_inline(): void
    {
        $response = $this->actingAs($this->learner->user)->get('/storage/uploads/media/photo.png');

        $response->assertOk();
        $this->assertSame('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_mobile_bearer_token_can_fetch_media(): void
    {
        $token = $this->learner->user->createToken('phone', ['learner'])->plainTextToken;

        $this->withToken($token)->get('/storage/uploads/media/photo.png')->assertOk();
    }

    public function test_suspended_or_inactive_learner_is_refused(): void
    {
        $this->learner->suspend(now()->addDay(), null, null);
        $this->actingAs($this->learner->user)->get('/storage/uploads/media/photo.png')->assertForbidden();

        $this->learner->liftSuspension();
        $this->learner->user->update(['is_active' => false]);
        $this->actingAs($this->learner->user->refresh())->get('/storage/uploads/media/photo.png')->assertForbidden();
    }

    public function test_missing_file_and_path_traversal_return_404(): void
    {
        $this->actingAs($this->learner->user)->get('/storage/uploads/media/nope.png')->assertNotFound();
        $this->actingAs($this->learner->user)->get('/storage/../.env')->assertNotFound();
        $this->actingAs($this->learner->user)->get('/storage/uploads/%2E%2E/%2E%2E/.env')->assertNotFound();
    }

    public function test_staff_can_upload_a_video(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'last_name' => 'Test', 'user_name' => 'admin',
            'email' => 'admin@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post('/cores/media/upload', [
            'file' => UploadedFile::fake()->create('cours.mp4', 2048, 'video/mp4'),
        ]);

        $response->assertOk()->assertJsonPath('type', 'video');
        $this->assertStringContainsString('/storage/uploads/media/', $response->json('url'));
    }

    public function test_video_over_the_limit_is_rejected(): void
    {
        config(['media.video_max_kb' => 1024]);
        $admin = User::create([
            'name' => 'Admin', 'last_name' => 'Test', 'user_name' => 'admin',
            'email' => 'admin@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        $this->actingAs($admin)->post('/cores/media/upload', [
            'file' => UploadedFile::fake()->create('cours.mp4', 2048, 'video/mp4'),
        ])->assertStatus(422)->assertJsonPath('success', false);
    }
}
