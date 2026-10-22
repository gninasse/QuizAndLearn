<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Resource;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Tests\TestCase;

/**
 * Dossiers, ressources et placements dans l'API apprenant (PWA + mobile),
 * et contrôles d'accès sur les contenus des dossiers masqués.
 */
class LearnerFoldersApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Learner $learner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));

        $this->user = User::create([
            'name' => 'Dossier',
            'last_name' => 'Learner',
            'user_name' => 'folderlearner',
            'email' => 'folder@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->learner = Learner::create(['user_id' => $this->user->id, 'matricule' => 'MAT-FOLD']);
        $this->group = Group::create(['name' => 'Promo Dossiers', 'is_active' => true]);
        $this->learner->groups()->attach($this->group->id);
    }

    private function folder(array $attrs = []): Folder
    {
        return Folder::create($attrs + ['group_id' => $this->group->id, 'name' => 'Module 1', 'position' => 0, 'is_visible' => true]);
    }

    private function article(?Folder $folder = null, int $position = 0): Article
    {
        $article = Article::create(['title' => 'Article', 'content' => 'Contenu', 'is_active' => true]);
        $this->group->articles()->attach($article->id, ['folder_id' => $folder?->id, 'position' => $position]);

        return $article;
    }

    private function quiz(?Folder $folder = null, int $position = 0): Quiz
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'duration' => 10, 'passing_score' => 50, 'is_active' => true, 'max_attempts' => 5]);
        $this->group->quizzes()->attach($quiz->id, ['folder_id' => $folder?->id, 'position' => $position]);
        Question::create([
            'quiz_id' => $quiz->id, 'question_text' => 'Vrai ?', 'type' => 'true_false',
            'points' => 2, 'order' => 1, 'options' => ['correct_answer' => 'true'],
        ]);

        return $quiz;
    }

    private function exam(?Folder $folder = null): Exam
    {
        $exam = Exam::create(['title' => 'Examen', 'duration' => 30, 'passing_score' => 50, 'is_active' => true, 'note_max' => 20]);
        $this->group->exams()->attach($exam->id, ['folder_id' => $folder?->id, 'position' => 0]);

        return $exam;
    }

    private function deck(?Folder $folder = null, bool $public = false): FlashcardDeck
    {
        $deck = FlashcardDeck::create([
            'titre' => $public ? 'Deck public' : 'Deck', 'algorithme' => 'sm2', 'easiness_default' => 2.5,
            'interval_min' => 1, 'interval_max' => 365, 'active' => true, 'is_public' => $public,
        ]);
        $this->group->flashcardDecks()->attach($deck->id, ['folder_id' => $folder?->id, 'position' => 0]);

        return $deck;
    }

    private function resource(array $attrs, ?Folder $folder = null, int $position = 0): Resource
    {
        $resource = Resource::create($attrs + ['title' => 'Ressource', 'is_active' => true]);
        $this->group->resources()->attach($resource->id, ['folder_id' => $folder?->id, 'position' => $position]);

        return $resource;
    }

    private function mobileToken(): string
    {
        return $this->postJson('/api/mobile/v1/login', [
            'login' => 'folderlearner',
            'password' => 'password123',
            'device_name' => 'Pixel Test',
        ])->json('token');
    }

    public function test_bootstrap_includes_folders_resources_placements(): void
    {
        $folder = $this->folder(['name' => 'Semaine 1', 'position' => 2]);
        $child = $this->folder(['name' => 'Exercices', 'parent_id' => $folder->id, 'position' => 0]);
        $article = $this->article(null, 4);
        $quiz = $this->quiz($folder, 1);
        $video = Video::create(['driver' => 'local', 'location' => 'videos/a.mp4', 'status' => 'ready']);
        $link = $this->resource(['kind' => 'link', 'title' => 'Site officiel', 'url' => 'https://example.org/doc'], $folder, 0);
        $vid = $this->resource(['kind' => 'video', 'title' => 'Cours filmé', 'video_id' => $video->id, 'url' => 'https://ignored'], $child, 0);
        $pdf = $this->resource(['kind' => 'pdf', 'title' => 'Support', 'mime' => 'application/pdf', 'size' => 1234, 'path' => 'resources/x.pdf', 'allow_download' => true]);
        $this->resource(['kind' => 'pdf', 'title' => 'Inactive', 'is_active' => false]);

        $response = $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))->assertOk();

        // Tri par groupe puis position : le client reconstruit l'arbre via parent_id.
        $response->assertJsonPath('folders', [
            ['id' => $child->id, 'group_id' => $this->group->id, 'parent_id' => $folder->id, 'name' => 'Exercices', 'position' => 0],
            ['id' => $folder->id, 'group_id' => $this->group->id, 'parent_id' => null, 'name' => 'Semaine 1', 'position' => 2],
        ]);

        $resources = collect($response->json('resources'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$link->id, $vid->id, $pdf->id], $resources->keys()->all());
        $this->assertSame([
            'id' => $link->id, 'kind' => 'link', 'title' => 'Site officiel', 'description' => null,
            'mime' => null, 'size' => null, 'allow_download' => false, 'video_ref' => null,
            'url' => 'https://example.org/doc', 'conversion_status' => null,
            'updated_at' => $link->updated_at->toIso8601String(),
        ], $resources[$link->id]);
        $this->assertSame($video->ref, $resources[$vid->id]['video_ref']);
        $this->assertNull($resources[$vid->id]['url']);
        $this->assertNull($resources[$pdf->id]['url']);
        $this->assertSame(1234, $resources[$pdf->id]['size']);
        $this->assertTrue($resources[$pdf->id]['allow_download']);
        $this->assertArrayNotHasKey('path', $resources[$pdf->id]);

        $placements = collect($response->json('placements'));
        $this->assertContains(['type' => 'article', 'id' => $article->id, 'group_id' => $this->group->id, 'folder_id' => null, 'position' => 4], $placements->all());
        $this->assertContains(['type' => 'quiz', 'id' => $quiz->id, 'group_id' => $this->group->id, 'folder_id' => $folder->id, 'position' => 1], $placements->all());
        $this->assertContains(['type' => 'resource', 'id' => $vid->id, 'group_id' => $this->group->id, 'folder_id' => $child->id, 'position' => 0], $placements->all());
        // Les placements ne désignent que des contenus présents dans le payload.
        $this->assertCount(5, $placements);
    }

    public function test_hidden_folder_removes_content_from_lists_and_authorized_ids(): void
    {
        $hidden = $this->folder(['name' => 'Masqué', 'is_visible' => false]);
        $sub = $this->folder(['name' => 'Sous-dossier', 'parent_id' => $hidden->id]);
        $later = $this->folder(['name' => 'Plus tard', 'available_from' => now()->addDay()]);
        $visibleArticle = $this->article();
        $article = $this->article($sub);
        $quiz = $this->quiz($hidden);
        $exam = $this->exam($later);
        $deck = $this->deck($hidden);
        $resource = $this->resource(['kind' => 'link', 'url' => 'https://example.org'], $hidden);

        $bootstrap = $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))->assertOk();
        $this->assertSame([$visibleArticle->id], collect($bootstrap->json('articles'))->pluck('id')->all());
        $this->assertSame([], $bootstrap->json('quizzes'));
        $this->assertSame([], $bootstrap->json('exams'));
        $this->assertSame([], $bootstrap->json('decks'));
        $this->assertSame([], $bootstrap->json('resources'));
        $this->assertSame([], $bootstrap->json('folders'));
        $this->assertSame([['type' => 'article', 'id' => $visibleArticle->id, 'group_id' => $this->group->id, 'folder_id' => null, 'position' => 0]], $bootstrap->json('placements'));

        $changes = $this->actingAs($this->user)
            ->getJson(route('learn.v1.changes', ['since' => now()->subDay()->toIso8601String()]))->assertOk();
        $this->assertSame([$visibleArticle->id], $changes->json('articles.authorized_ids'));
        $this->assertSame([], $changes->json('quizzes.authorized_ids'));
        $this->assertSame([], $changes->json('exams.authorized_ids'));
        $this->assertSame([], $changes->json('decks.authorized_ids'));
        $this->assertSame([], $changes->json('resources.authorized_ids'));
        $this->assertSame([], $changes->json('folders'));

        // Le dossier redevient visible : tout revient (requête suivante, même processus).
        $hidden->update(['is_visible' => true]);
        $later->update(['available_from' => null]);

        $changes = $this->actingAs($this->user)
            ->getJson(route('learn.v1.changes', ['since' => now()->subDay()->toIso8601String()]))->assertOk();
        $this->assertEqualsCanonicalizing([$visibleArticle->id, $article->id], $changes->json('articles.authorized_ids'));
        $this->assertSame([$quiz->id], $changes->json('quizzes.authorized_ids'));
        $this->assertSame([$exam->id], $changes->json('exams.authorized_ids'));
        $this->assertSame([$deck->id], $changes->json('decks.authorized_ids'));
        $this->assertSame([$resource->id], $changes->json('resources.authorized_ids'));
        $this->assertSame([$resource->id], collect($changes->json('resources.updated'))->pluck('id')->all());
        $this->assertCount(3, $changes->json('folders'));
        $this->assertCount(6, $changes->json('placements'));
    }

    public function test_public_deck_unaffected(): void
    {
        $hidden = $this->folder(['is_visible' => false]);
        $public = $this->deck($hidden, public: true);
        $card = FlashcardItem::create(['deck_id' => $public->id, 'recto' => 'R', 'verso' => 'V', 'ordre' => 1]);

        $bootstrap = $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))->assertOk();
        $this->assertSame([$public->id], collect($bootstrap->json('decks'))->pluck('id')->all());
        $this->assertSame([], $bootstrap->json('placements'));

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), ['actions' => [[
            'id' => (string) Str::uuid(), 'type' => 'card_review', 'data' => ['card_id' => $card->id, 'quality' => 4],
        ]]])->assertOk()->assertJsonPath('results.0.status', 'applied');
    }

    public function test_card_review_rejected_when_deck_hidden(): void
    {
        $deck = $this->deck($this->folder(['is_visible' => false]));
        $card = FlashcardItem::create(['deck_id' => $deck->id, 'recto' => 'R', 'verso' => 'V', 'ordre' => 1]);

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), ['actions' => [[
            'id' => (string) Str::uuid(), 'type' => 'card_review', 'data' => ['card_id' => $card->id, 'quality' => 4],
        ]]])->assertOk()->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_mobile_bootstrap_has_same_keys(): void
    {
        $folder = $this->folder();
        $this->quiz($folder);
        $this->resource(['kind' => 'link', 'url' => 'https://example.org'], $folder);

        $pwa = $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))->assertOk()->json();
        $pwaChanges = $this->getJson(route('learn.v1.changes', ['since' => now()->subDay()->toIso8601String()]))->json();
        $this->app['auth']->forgetGuards();

        $headers = ['Authorization' => 'Bearer '.$this->mobileToken()];
        $mobile = $this->getJson('/api/mobile/v1/bootstrap', $headers)->assertOk()
            ->assertJsonStructure(['folders' => [['id', 'group_id', 'parent_id', 'name', 'position']], 'resources', 'placements'])
            ->json();
        $mobileChanges = $this->getJson('/api/mobile/v1/changes?since='.urlencode(now()->subDay()->toIso8601String()), $headers)
            ->assertOk()
            ->assertJsonStructure(['resources' => ['updated', 'authorized_ids'], 'folders', 'placements'])
            ->json();

        $this->assertSame(array_keys($pwa), array_keys($mobile));
        $this->assertSame(array_keys($pwaChanges), array_keys($mobileChanges));
        $this->assertSame($pwa['folders'], $mobile['folders']);
        $this->assertSame($pwa['resources'], $mobile['resources']);
        $this->assertSame($pwa['placements'], $mobile['placements']);
    }

    public function test_exam_start_forbidden_when_hidden(): void
    {
        $folder = $this->folder(['available_until' => now()->subMinute()]);
        $exam = $this->exam($folder);

        $this->actingAs($this->user)->postJson(route('learn.v1.exams.attempts.store', $exam->id))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
        $this->assertDatabaseCount('exam_attempts', 0);

        $folder->update(['available_until' => null]);
        $this->actingAs($this->user)->postJson(route('learn.v1.exams.attempts.store', $exam->id))->assertOk();
    }

    public function test_offline_quiz_attempt_before_hiding_accepted(): void
    {
        $folder = $this->folder();
        $quiz = $this->quiz($folder);
        $questionId = $quiz->questions()->first()->id;

        // Le formateur masque le dossier à 10:00 ; l'apprenant synchronise ensuite.
        $folder->update(['is_visible' => false]);
        $this->travel(2)->hours();

        $action = fn (array $times) => ['id' => (string) Str::uuid(), 'type' => 'quiz_attempt', 'data' => [
            'quiz_id' => $quiz->id, 'answers' => [$questionId => 'true'], ...$times,
        ]];

        $response = $this->actingAs($this->user)->postJson(route('learn.v1.actions'), ['actions' => [
            // Passée hors-ligne avant le masquage : acceptée.
            $action(['started_at' => '2026-10-20 09:30:00', 'completed_at' => '2026-10-20 09:40:00']),
            // Sans started_at : completed_at fait foi.
            $action(['completed_at' => '2026-10-20 09:50:00']),
            // Commencée après le masquage : refusée.
            $action(['started_at' => '2026-10-20 10:30:00', 'completed_at' => '2026-10-20 10:40:00']),
            // Sans horodatage : refusée.
            $action([]),
        ]])->assertOk();

        $response->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied')
            ->assertJsonPath('results.2.status', 'rejected')
            ->assertJsonPath('results.2.message', "Ce contenu n'est pas disponible.")
            ->assertJsonPath('results.3.status', 'rejected');
        $this->assertDatabaseCount('quiz_attempts', 2);
    }

    public function test_offline_quiz_attempt_before_window_expiry_accepted(): void
    {
        // Dossier modifié pour la dernière fois à 08:00, fermé par sa fenêtre à 10:00.
        $this->travelTo(Carbon::parse('2026-10-20 08:00:00'));
        $folder = $this->folder(['available_until' => Carbon::parse('2026-10-20 10:00:00')]);
        $quiz = $this->quiz($folder);
        $questionId = $quiz->questions()->first()->id;
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));

        $action = fn (string $startedAt) => ['id' => (string) Str::uuid(), 'type' => 'quiz_attempt', 'data' => [
            'quiz_id' => $quiz->id, 'answers' => [$questionId => 'true'], 'started_at' => $startedAt,
        ]];

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), ['actions' => [
            $action('2026-10-20 09:30:00'),
            $action('2026-10-20 10:30:00'),
        ]])->assertOk()
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'rejected');
        $this->assertSame('2026-10-20 08:00:00', $folder->fresh()->updated_at->toDateTimeString());
    }

    public function test_resource_view_action_records_completed_progress_without_xp(): void
    {
        $resource = $this->resource(['kind' => 'link', 'title' => 'Site', 'url' => 'https://example.org/cours'], $this->folder());
        $action = fn () => ['id' => (string) Str::uuid(), 'type' => 'resource_view', 'data' => ['resource_id' => $resource->id]];

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), ['actions' => [$action(), $action()]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied')
            ->assertJsonPath('xp.total_xp', 0);

        $this->assertDatabaseCount('learner_progress', 1);
        $this->assertDatabaseHas('learner_progress', [
            'learner_id' => $this->learner->id,
            'content_type' => 'resource',
            'content_id' => $resource->id,
            'status' => 'completed',
        ]);
        $progress = $this->learner->progress()->where('content_type', 'resource')->first();
        $this->assertNotNull($progress->completed_at);
        $this->assertNotNull($progress->last_accessed_at);
    }

    public function test_resource_view_action_rejected_when_resource_hidden(): void
    {
        $hidden = $this->resource(['kind' => 'link', 'title' => 'Masquée', 'url' => 'https://example.org/x'], $this->folder(['is_visible' => false]));

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), ['actions' => [
            ['id' => (string) Str::uuid(), 'type' => 'resource_view', 'data' => ['resource_id' => $hidden->id]],
            ['id' => (string) Str::uuid(), 'type' => 'resource_view', 'data' => []],
        ]])->assertOk()
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.message', "Ce contenu n'est pas disponible.")
            ->assertJsonPath('results.1.status', 'rejected');

        $this->assertDatabaseCount('learner_progress', 0);
    }
}
