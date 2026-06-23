<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Article;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Tests\TestCase;

class ArticleEditorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Article $article;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user
        $this->user = User::create([
            'name' => 'Writer',
            'last_name' => 'User',
            'user_name' => 'writeruser',
            'email' => 'writeruser@example.com',
            'phone' => '987654321',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        \Spatie\Permission\Models\Role::findOrCreate('super-admin');
        $this->user->assignRole('super-admin');

        // Create an article
        $this->article = Article::create([
            'title' => 'Test Article',
            'content' => '<p>Test Article Content</p>',
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        // Create a group
        $this->group = Group::create([
            'name' => 'Access Group',
            'description' => 'Group for testing access',
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);
    }

    /**
     * Test access to the article editor view.
     */
    public function test_can_access_article_editor(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.articles.edit', $this->article->id));

        $response->assertStatus(200);
        $response->assertViewIs('core::admin.article.editor');
        $response->assertSee('Test Article');
    }

    /**
     * Test article settings & content autosaving via AJAX.
     */
    public function test_can_autosave_article(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.articles.autosave', $this->article->id), [
                'title' => 'Updated Article Title',
                'content' => '<h2>Heading</h2><p>Updated content</p>',
                'category' => 'Technology',
                'seo_description' => 'Test SEO description',
                'seo_keywords' => 'test, article, seo',
                'is_active' => 1,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Article enregistré avec succès.',
        ]);

        $this->article->refresh();
        $this->assertEquals('Updated Article Title', $this->article->title);
        $this->assertEquals('<h2>Heading</h2><p>Updated content</p>', $this->article->content);
        $this->assertEquals('Technology', $this->article->category);
        $this->assertEquals('Test SEO description', $this->article->seo_description);
        $this->assertEquals('test, article, seo', $this->article->seo_keywords);
        $this->assertTrue($this->article->is_active);
    }

    /**
     * Test toggling the active publication status of the article.
     */
    public function test_can_toggle_article_active_status(): void
    {
        $this->assertFalse($this->article->is_active);

        $response = $this->actingAs($this->user)
            ->patch(route('admin.articles.toggle-active', $this->article->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_active' => true,
        ]);

        $this->article->refresh();
        $this->assertTrue($this->article->is_active);
    }

    /**
     * Test assigning and unassigning a group from the article.
     */
    public function test_can_assign_and_unassign_group_access(): void
    {
        // 1. Assign group access
        $response = $this->actingAs($this->user)
            ->post(route('admin.articles.groups.assign', $this->article->id), [
                'group_id' => $this->group->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertTrue($this->article->groups()->where('group_id', $this->group->id)->exists());

        // 2. Unassign group access
        $response = $this->actingAs($this->user)
            ->delete(route('admin.articles.groups.unassign', [
                'article' => $this->article->id,
                'group' => $this->group->id,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertFalse($this->article->groups()->where('group_id', $this->group->id)->exists());
    }

    /**
     * Test searching active quizzes for embed widgets.
     */
    public function test_can_search_active_quizzes(): void
    {
        // Create an active quiz
        $quiz = Quiz::create([
            'title' => 'Math Quiz',
            'description' => 'Algebra basics',
            'duration' => 20,
            'passing_score' => 60,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.articles.quizzes.search', ['q' => 'Math']));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'title' => 'Math Quiz',
        ]);
    }

    /**
     * Test uploading an image.
     */
    public function test_can_upload_media_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('photo.jpg', 1024, 'image/jpeg');

        $response = $this->actingAs($this->user)
            ->post(route('admin.articles.upload-media'), [
                'file' => $file,
                'type' => 'image',
                'article_id' => $this->article->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertNotNull($response->json('url'));

        $storedPath = str_replace(asset('storage/'), '', $response->json('url'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(ltrim($storedPath, '/'));
    }

    /**
     * Test uploading an audio.
     */
    public function test_can_upload_media_audio(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('track.mp3', 2048, 'audio/mpeg'); // 2MB

        $response = $this->actingAs($this->user)
            ->post(route('admin.articles.upload-media'), [
                'file' => $file,
                'type' => 'audio',
                'article_id' => $this->article->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $storedPath = str_replace(asset('storage/'), '', $response->json('url'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(ltrim($storedPath, '/'));
    }

    /**
     * Test uploading audio exceeding reasonable size.
     */
    public function test_cannot_upload_large_audio(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('large.mp3', 15000, 'audio/mpeg'); // 15MB

        $response = $this->actingAs($this->user)
            ->post(route('admin.articles.upload-media'), [
                'file' => $file,
                'type' => 'audio',
                'article_id' => $this->article->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /**
     * Test media files are deleted physically when the article is deleted.
     */
    public function test_media_deleted_with_article(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('track.mp3', 2048, 'audio/mpeg');

        $response = $this->actingAs($this->user)
            ->post(route('admin.articles.upload-media'), [
                'file' => $file,
                'type' => 'audio',
                'article_id' => $this->article->id,
            ]);

        $storedPath = str_replace(asset('storage/'), '', $response->json('url'));
        $cleanPath = ltrim($storedPath, '/');

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($cleanPath);

        // Delete article
        $this->article->delete();

        // File should be physically deleted
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($cleanPath);
        $this->assertDatabaseMissing('article_media', [
            'article_id' => $this->article->id,
        ]);
    }
}
