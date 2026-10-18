<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Article;
use Modules\Core\Services\GuestToken;
use Tests\TestCase;

/**
 * Un invité ne lit que les médias du contenu vitrine actif.
 */
class ShowcaseMediaTest extends TestCase
{
    use RefreshDatabase;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::put('showcase.enabled', true);
        Storage::fake('public');
        Storage::disk('public')->put('uploads/media/vitrine.png', 'PNG1');
        Storage::disk('public')->put('uploads/media/prive.png', 'PNG2');

        $this->article = Article::create([
            'title' => 'Vitrine',
            'content' => '<p><img src="http://localhost/storage/uploads/media/vitrine.png"></p>',
            'is_active' => true,
            'is_showcase' => true,
            'estimated_reading_time' => 1,
        ]);
        Article::create([
            'title' => 'Privé',
            'content' => '<img src="/storage/uploads/media/prive.png">',
            'is_active' => true,
            'is_showcase' => false,
            'estimated_reading_time' => 1,
        ]);
    }

    public function test_guest_with_token_reads_showcase_media(): void
    {
        $this->call('GET', '/storage/uploads/media/vitrine.png', [], [GuestToken::COOKIE => GuestToken::issue()])
            ->assertOk();
    }

    public function test_valid_token_is_refused_when_mode_disabled(): void
    {
        $token = GuestToken::issue();
        AppSetting::put('showcase.enabled', false);

        $this->call('GET', '/storage/uploads/media/vitrine.png', [], [GuestToken::COOKIE => $token])
            ->assertForbidden();
    }

    public function test_guest_without_token_is_refused(): void
    {
        $this->get('/storage/uploads/media/vitrine.png')->assertForbidden();
    }

    public function test_guest_cannot_read_non_showcase_media(): void
    {
        $this->call('GET', '/storage/uploads/media/prive.png', [], [GuestToken::COOKIE => GuestToken::issue()])
            ->assertForbidden();
    }

    public function test_expired_token_is_refused(): void
    {
        $token = GuestToken::issue();
        Carbon::setTestNow(now()->addHours(25));

        $this->call('GET', '/storage/uploads/media/vitrine.png', [], [GuestToken::COOKIE => $token])
            ->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_header_token_works_for_native_app(): void
    {
        $this->withHeader('X-Guest-Token', GuestToken::issue())
            ->get('/storage/uploads/media/vitrine.png')
            ->assertOk();
    }

    public function test_unchecking_content_revokes_media_access(): void
    {
        $token = GuestToken::issue();
        $this->withHeader('X-Guest-Token', $token)->get('/storage/uploads/media/vitrine.png')->assertOk();

        $this->article->update(['is_showcase' => false]);

        $this->withHeader('X-Guest-Token', $token)->get('/storage/uploads/media/vitrine.png')->assertForbidden();
    }
}
