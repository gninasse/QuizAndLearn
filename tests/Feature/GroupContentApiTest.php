<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Jobs\ConvertDocumentToPdf;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Resource;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Modules\Core\Services\ContentSharing;
use Tests\TestCase;

/**
 * Back-office : arbre des contenus d'un groupe (dossiers, placement,
 * réordonnancement, ressources, rattachement de contenus existants).
 */
class GroupContentApiTest extends TestCase
{
    use RefreshDatabase;

    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private User $admin;

    private User $trainerUser;

    private Group $group;

    private Group $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = $this->makeUser('admin');
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');

        $this->trainerUser = $this->makeUser('prof');
        Role::findOrCreate('trainer');
        $this->trainerUser->assignRole('trainer');
        $trainer = Trainer::create(['user_id' => $this->trainerUser->id]);

        $this->group = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $this->other = Group::create(['name' => 'Promo B', 'is_active' => true]);
        $this->group->trainers()->attach($trainer->id);
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }

    private function url(string $path = '', ?Group $group = null): string
    {
        return '/cores/groups/'.($group ?? $this->group)->id.'/contenus'.$path;
    }

    private function folder(string $name, ?Folder $parent = null, ?Group $group = null, int $position = 0): Folder
    {
        return Folder::create([
            'group_id' => ($group ?? $this->group)->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'position' => $position,
        ]);
    }

    private function article(?Folder $folder = null, int $position = 0, ?Group $group = null, ?User $by = null): Article
    {
        $article = Article::create(['title' => 'Article', 'content' => 'Contenu', 'is_active' => true, 'created_by' => $by?->id]);
        ($group ?? $this->group)->articles()->attach($article->id, ['folder_id' => $folder?->id, 'position' => $position]);

        return $article;
    }

    private function quiz(?Folder $folder = null, int $position = 0): Quiz
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'duration' => 10, 'passing_score' => 50, 'is_active' => true, 'max_attempts' => 5]);
        $this->group->quizzes()->attach($quiz->id, ['folder_id' => $folder?->id, 'position' => $position]);

        return $quiz;
    }

    private function deck(bool $public = false): FlashcardDeck
    {
        return FlashcardDeck::create([
            'titre' => 'Deck', 'algorithme' => 'sm2', 'easiness_default' => 2.5,
            'interval_min' => 1, 'interval_max' => 365, 'active' => true, 'is_public' => $public,
        ]);
    }

    private function pivot(string $table, string $fk, int $id, ?Group $group = null): ?object
    {
        return \DB::table($table)->where('group_id', ($group ?? $this->group)->id)->where($fk, $id)->first();
    }

    // ---------------------------------------------------------------- accès

    public function test_trainer_of_group_can_create_folder(): void
    {
        $this->actingAs($this->trainerUser)
            ->postJson($this->url('/folders'), ['name' => 'Chapitre 1'])
            ->assertCreated()
            ->assertJsonPath('folder.name', 'Chapitre 1');

        $this->assertDatabaseHas('folders', ['group_id' => $this->group->id, 'name' => 'Chapitre 1', 'parent_id' => null]);
    }

    public function test_other_trainer_forbidden(): void
    {
        $this->actingAs($this->trainerUser)
            ->postJson($this->url('/folders', $this->other), ['name' => 'Intrus'])
            ->assertForbidden();
        $this->actingAs($this->trainerUser)->getJson($this->url('/data', $this->other))->assertForbidden();

        $this->assertDatabaseMissing('folders', ['name' => 'Intrus']);
    }

    public function test_page_renders(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertSee('Promo A');
    }

    // ---------------------------------------------------------------- data

    public function test_data_returns_tree_and_items(): void
    {
        $folder = $this->folder('Chapitre');
        $quiz = $this->quiz($folder, 1);
        $article = $this->article();
        $link = Resource::create(['kind' => 'link', 'title' => 'Site', 'url' => 'https://example.com']);
        $this->group->resources()->attach($link->id, ['folder_id' => $folder->id, 'position' => 0]);

        $res = $this->actingAs($this->admin)->getJson($this->url('/data'))->assertOk();

        $res->assertJsonPath('folders.0.id', $folder->id)
            ->assertJsonPath('folders.0.name', 'Chapitre');
        $items = collect($res->json('items'));
        $this->assertEquals(
            ['type' => 'quiz', 'id' => $quiz->id, 'title' => 'Quiz', 'folder_id' => $folder->id, 'position' => 1],
            collect($items->firstWhere('type', 'quiz'))->only(['type', 'id', 'title', 'folder_id', 'position'])->all()
        );
        $this->assertNull($items->firstWhere('type', 'article')['folder_id']);
        $this->assertSame($article->id, $items->firstWhere('type', 'article')['id']);
        $this->assertSame('link', $items->firstWhere('type', 'resource')['kind']);
    }

    // ---------------------------------------------------------------- dossiers

    public function test_update_folder(): void
    {
        $folder = $this->folder('Ancien');

        $this->actingAs($this->admin)->patchJson($this->url("/folders/{$folder->id}"), [
            'name' => 'Nouveau',
            'is_visible' => false,
            'available_from' => '2026-11-01 08:00:00',
            'available_until' => '2026-12-01 08:00:00',
        ])->assertOk();

        $folder->refresh();
        $this->assertSame('Nouveau', $folder->name);
        $this->assertFalse($folder->is_visible);
        $this->assertSame('2026-11-01 08:00:00', $folder->available_from->format('Y-m-d H:i:s'));

        $this->actingAs($this->admin)->patchJson($this->url("/folders/{$folder->id}"), [
            'available_from' => '2026-12-01 08:00:00',
            'available_until' => '2026-11-01 08:00:00',
        ])->assertStatus(422);
    }

    public function test_folder_of_other_group_not_reachable_through_this_group(): void
    {
        $foreign = $this->folder('Étranger', null, $this->other);

        $this->actingAs($this->admin)->patchJson($this->url("/folders/{$foreign->id}"), ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->deleteJson($this->url("/folders/{$foreign->id}?mode=lift"))->assertNotFound();
        $this->assertSame('Étranger', $foreign->fresh()->name);
    }

    public function test_move_into_descendant_rejected(): void
    {
        $a = $this->folder('A');
        $b = $this->folder('B', $a);
        $c = $this->folder('C', $b);

        $this->actingAs($this->admin)
            ->postJson($this->url("/folders/{$a->id}/move"), ['parent_id' => $c->id, 'position' => 0])
            ->assertStatus(422);
        $this->actingAs($this->admin)
            ->postJson($this->url("/folders/{$a->id}/move"), ['parent_id' => $a->id, 'position' => 0])
            ->assertStatus(422);

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_move_into_other_group_folder_rejected(): void
    {
        $a = $this->folder('A');
        $foreign = $this->folder('Étranger', null, $this->other);

        $this->actingAs($this->admin)
            ->postJson($this->url("/folders/{$a->id}/move"), ['parent_id' => $foreign->id, 'position' => 0])
            ->assertStatus(422);

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_move_folder_inserts_at_position(): void
    {
        $target = $this->folder('Cible');
        $x = $this->folder('X', $target, null, 0);
        $quiz = $this->quiz($target, 1);
        $moving = $this->folder('Mobile');

        $this->actingAs($this->admin)
            ->postJson($this->url("/folders/{$moving->id}/move"), ['parent_id' => $target->id, 'position' => 1])
            ->assertOk();

        $this->assertSame($target->id, $moving->fresh()->parent_id);
        $this->assertSame(0, $x->fresh()->position);
        $this->assertSame(1, $moving->fresh()->position);
        $this->assertSame(2, (int) $this->pivot('group_quiz', 'quiz_id', $quiz->id)->position);
    }

    // ---------------------------------------------------------------- placement

    public function test_place_into_other_group_folder_rejected(): void
    {
        $quiz = $this->quiz();
        $foreign = $this->folder('Étranger', null, $this->other);

        $this->actingAs($this->admin)
            ->postJson($this->url('/items/place'), ['type' => 'quiz', 'id' => $quiz->id, 'folder_id' => $foreign->id, 'position' => 0])
            ->assertStatus(422);

        $this->assertNull($this->pivot('group_quiz', 'quiz_id', $quiz->id)->folder_id);
    }

    public function test_place_unattached_content_is_404(): void
    {
        $quiz = Quiz::create(['title' => 'Libre', 'duration' => 10, 'passing_score' => 50, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->postJson($this->url('/items/place'), ['type' => 'quiz', 'id' => $quiz->id, 'folder_id' => null, 'position' => 0])
            ->assertNotFound();
    }

    public function test_place_item_into_folder(): void
    {
        $folder = $this->folder('Chapitre');
        $first = $this->article($folder, 0);
        $quiz = $this->quiz();

        $this->actingAs($this->admin)
            ->postJson($this->url('/items/place'), ['type' => 'quiz', 'id' => $quiz->id, 'folder_id' => $folder->id, 'position' => 0])
            ->assertOk();

        $pivot = $this->pivot('group_quiz', 'quiz_id', $quiz->id);
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(0, (int) $pivot->position);
        $this->assertSame(1, (int) $this->pivot('group_article', 'article_id', $first->id)->position);
    }

    // ---------------------------------------------------------------- suppression

    public function test_delete_requires_mode(): void
    {
        $folder = $this->folder('Plein');
        $quiz = $this->quiz($folder);

        $this->actingAs($this->admin)->deleteJson($this->url("/folders/{$folder->id}"))->assertStatus(422);
        $this->actingAs($this->admin)->deleteJson($this->url("/folders/{$folder->id}?mode=boom"))->assertStatus(422);

        $this->assertNotNull($folder->fresh());
        $this->assertSame($folder->id, (int) $this->pivot('group_quiz', 'quiz_id', $quiz->id)->folder_id);
    }

    public function test_delete_lift(): void
    {
        $parent = $this->folder('Parent');
        $sibling = $this->article($parent, 0);
        $doomed = $this->folder('Condamné', $parent, null, 1);
        $sub = $this->folder('Sous', $doomed, null, 0);
        $quiz = $this->quiz($doomed, 1);
        $deep = $this->article($sub, 0);

        $this->actingAs($this->admin)->deleteJson($this->url("/folders/{$doomed->id}?mode=lift"))->assertOk();

        $this->assertNull($doomed->fresh());
        // Le sous-dossier et le quiz remontent dans le parent, à la suite de l'article (position 0).
        $this->assertSame($parent->id, $sub->fresh()->parent_id);
        $this->assertSame(1, $sub->fresh()->position);
        $qp = $this->pivot('group_quiz', 'quiz_id', $quiz->id);
        $this->assertSame($parent->id, (int) $qp->folder_id);
        $this->assertSame(2, (int) $qp->position);
        $this->assertSame(0, (int) $this->pivot('group_article', 'article_id', $sibling->id)->position);
        // Le contenu du sous-dossier n'a pas bougé.
        $this->assertSame($sub->id, (int) $this->pivot('group_article', 'article_id', $deep->id)->folder_id);
    }

    public function test_delete_lift_at_root(): void
    {
        $root = $this->article(null, 4);
        $doomed = $this->folder('Racine', null, null, 2);
        $quiz = $this->quiz($doomed, 0);

        $this->actingAs($this->admin)->deleteJson($this->url("/folders/{$doomed->id}?mode=lift"))->assertOk();

        $qp = $this->pivot('group_quiz', 'quiz_id', $quiz->id);
        $this->assertNull($qp->folder_id);
        $this->assertSame(5, (int) $qp->position);
        $this->assertSame(4, (int) $this->pivot('group_article', 'article_id', $root->id)->position);
    }

    public function test_delete_detach(): void
    {
        $doomed = $this->folder('Condamné');
        $sub = $this->folder('Sous', $doomed);
        $quiz = $this->quiz($doomed);
        $deep = $this->article($sub);
        $keep = $this->article();
        // Le même article est aussi dans un autre groupe : il y reste.
        $this->other->articles()->attach($deep->id, ['folder_id' => null, 'position' => 0]);

        $this->actingAs($this->admin)->deleteJson($this->url("/folders/{$doomed->id}?mode=detach"))->assertOk();

        $this->assertNull($doomed->fresh());
        $this->assertNull($sub->fresh());
        $this->assertNull($this->pivot('group_quiz', 'quiz_id', $quiz->id));
        $this->assertNull($this->pivot('group_article', 'article_id', $deep->id));
        $this->assertNotNull($this->pivot('group_article', 'article_id', $keep->id));
        $this->assertNotNull($this->pivot('group_article', 'article_id', $deep->id, $this->other));
        $this->assertNotNull(Quiz::find($quiz->id));
    }

    // ---------------------------------------------------------------- réordonnancement

    public function test_reorder_mixed_items(): void
    {
        $folder = $this->folder('Chapitre');
        $sub = $this->folder('Sous', $folder, null, 0);
        $quiz = $this->quiz($folder, 1);
        $article = $this->article($folder, 2);

        $this->actingAs($this->admin)->postJson($this->url('/items/reorder'), [
            'folder_id' => $folder->id,
            'items' => [
                ['type' => 'article', 'id' => $article->id],
                ['type' => 'folder', 'id' => $sub->id],
                ['type' => 'quiz', 'id' => $quiz->id],
            ],
        ])->assertOk();

        $this->assertSame(0, (int) $this->pivot('group_article', 'article_id', $article->id)->position);
        $this->assertSame(1, $sub->fresh()->position);
        $this->assertSame(2, (int) $this->pivot('group_quiz', 'quiz_id', $quiz->id)->position);
    }

    public function test_reorder_rejects_item_from_elsewhere(): void
    {
        $folder = $this->folder('Chapitre');
        $quiz = $this->quiz(null, 0);
        $foreign = $this->folder('Étranger', null, $this->other);

        $this->actingAs($this->admin)->postJson($this->url('/items/reorder'), [
            'folder_id' => $folder->id,
            'items' => [['type' => 'quiz', 'id' => $quiz->id]],
        ])->assertStatus(422);
        $this->actingAs($this->admin)->postJson($this->url('/items/reorder'), [
            'folder_id' => null,
            'items' => [['type' => 'folder', 'id' => $foreign->id]],
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------- ressources

    public function test_upload_resource_attaches_to_group_folder(): void
    {
        Queue::fake();
        $folder = $this->folder('Supports');
        $this->article($folder, 0);

        $res = $this->actingAs($this->trainerUser)->post($this->url('/resources'), [
            'file' => UploadedFile::fake()->create('support.docx', 12, self::DOCX),
            'title' => 'Support de cours',
            'allow_download' => '1',
            'folder_id' => $folder->id,
        ], ['Accept' => 'application/json'])->assertCreated();

        $resource = Resource::findOrFail($res->json('resource.id'));
        $this->assertSame('document', $resource->kind);
        $this->assertTrue($resource->allow_download);
        $pivot = $this->pivot('group_resource', 'resource_id', $resource->id);
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(1, (int) $pivot->position);
        Queue::assertPushed(ConvertDocumentToPdf::class);
    }

    public function test_upload_into_other_group_folder_rejected(): void
    {
        $foreign = $this->folder('Étranger', null, $this->other);

        $this->actingAs($this->admin)->post($this->url('/resources'), [
            'file' => UploadedFile::fake()->createWithContent('cours.pdf', '%PDF-1.4 x'),
            'folder_id' => $foreign->id,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, Resource::count());
    }

    public function test_add_link_at_root(): void
    {
        $res = $this->actingAs($this->admin)->postJson($this->url('/links'), [
            'url' => 'https://example.com/cours',
            'title' => 'Cours en ligne',
        ])->assertCreated();

        $pivot = $this->pivot('group_resource', 'resource_id', $res->json('resource.id'));
        $this->assertNull($pivot->folder_id);

        $this->actingAs($this->admin)->postJson($this->url('/links'), ['url' => 'javascript:alert(1)', 'title' => 'X'])
            ->assertStatus(422);
    }

    public function test_retry_conversion_requeues(): void
    {
        Queue::fake();
        $doc = Resource::create([
            'kind' => 'document', 'title' => 'Doc', 'disk' => 'local', 'path' => 'resources/1/a.docx',
            'conversion_status' => 'failed', 'conversion_error' => "LibreOffice n'est pas installé sur le serveur.",
        ]);
        $this->group->resources()->attach($doc->id, ['folder_id' => null, 'position' => 0]);

        $this->actingAs($this->admin)->postJson($this->url("/resources/{$doc->id}/retry-conversion"))->assertOk();

        $doc->refresh();
        $this->assertSame('pending', $doc->conversion_status);
        $this->assertNull($doc->conversion_error);
        Queue::assertPushed(ConvertDocumentToPdf::class, fn ($job) => $job->resourceId === $doc->id);
    }

    public function test_retry_conversion_rejects_non_document_and_foreign(): void
    {
        Queue::fake();
        $pdf = Resource::create(['kind' => 'pdf', 'title' => 'PDF', 'disk' => 'local', 'path' => 'resources/2/a.pdf']);
        $this->group->resources()->attach($pdf->id, ['folder_id' => null, 'position' => 0]);
        $foreign = Resource::create(['kind' => 'document', 'title' => 'Doc', 'disk' => 'local', 'path' => 'x.docx', 'conversion_status' => 'failed']);
        $this->other->resources()->attach($foreign->id, ['folder_id' => null, 'position' => 0]);

        $this->actingAs($this->admin)->postJson($this->url("/resources/{$pdf->id}/retry-conversion"))->assertStatus(422);
        $this->actingAs($this->admin)->postJson($this->url("/resources/{$foreign->id}/retry-conversion"))->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_conversion_job_does_not_overlap_per_resource(): void
    {
        $middleware = (new ConvertDocumentToPdf(42))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame('42', $middleware[0]->key);
    }

    // ---------------------------------------------------------------- rattachement / détachement

    public function test_attach_existing_content_at_end(): void
    {
        $folder = $this->folder('Chapitre');
        $this->article($folder, 3);
        $exam = Exam::create(['title' => 'Examen', 'duration' => 30, 'passing_score' => 50, 'is_active' => true, 'note_max' => 20]);

        $this->actingAs($this->admin)
            ->postJson($this->url('/items/attach'), ['type' => 'exam', 'id' => $exam->id, 'folder_id' => $folder->id])
            ->assertOk();

        $pivot = $this->pivot('group_exam', 'exam_id', $exam->id);
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(4, (int) $pivot->position);
    }

    public function test_attach_respects_isolated_mode(): void
    {
        ContentSharing::setMode('isolated');
        $article = $this->article(null, 0, $this->other);

        $this->actingAs($this->admin)
            ->postJson($this->url('/items/attach'), ['type' => 'article', 'id' => $article->id, 'folder_id' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_id']);

        $this->assertNull($this->pivot('group_article', 'article_id', $article->id));
    }

    public function test_trainer_cannot_attach_someone_elses_content(): void
    {
        $quiz = Quiz::create(['title' => 'Privé', 'duration' => 10, 'passing_score' => 50, 'is_active' => true, 'created_by' => $this->admin->id]);
        $own = Quiz::create(['title' => 'Mien', 'duration' => 10, 'passing_score' => 50, 'is_active' => true, 'created_by' => $this->trainerUser->id]);

        $this->actingAs($this->trainerUser)
            ->postJson($this->url('/items/attach'), ['type' => 'quiz', 'id' => $quiz->id])
            ->assertForbidden();
        $this->actingAs($this->trainerUser)
            ->postJson($this->url('/items/attach'), ['type' => 'quiz', 'id' => $own->id])
            ->assertOk();
    }

    public function test_detach_item(): void
    {
        $quiz = $this->quiz();

        $this->actingAs($this->admin)->deleteJson($this->url("/items/quiz/{$quiz->id}"))->assertOk();
        $this->assertNull($this->pivot('group_quiz', 'quiz_id', $quiz->id));
        $this->assertNotNull(Quiz::find($quiz->id));

        $this->actingAs($this->admin)->deleteJson($this->url("/items/quiz/{$quiz->id}"))->assertNotFound();
        $this->actingAs($this->admin)->deleteJson($this->url("/items/bogus/{$quiz->id}"))->assertNotFound();
    }

    // ---------------------------------------------------------------- ContentSharing::assertCanAttach

    public function test_assert_can_attach_shared_mode_allows_second_group(): void
    {
        $article = $this->article(null, 0, $this->other);

        ContentSharing::assertCanAttach('article', $article->id, $this->group->id);
        $this->assertTrue(true);
    }

    public function test_assert_can_attach_isolated_rejects_second_group(): void
    {
        ContentSharing::setMode('isolated');
        $article = $this->article(null, 0, $this->other);

        try {
            ContentSharing::assertCanAttach('article', $article->id, $this->group->id);
            $this->fail('ValidationException attendue');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['group_id' => ['Ce contenu appartient déjà à un autre groupe. Utilisez « Copier vers… ».']],
                $e->errors()
            );
        }

        // Le groupe propriétaire lui-même reste accepté.
        ContentSharing::assertCanAttach('article', $article->id, $this->other->id);
    }

    public function test_assert_can_attach_isolated_exempts_public_deck(): void
    {
        ContentSharing::setMode('isolated');
        $deck = $this->deck(public: true);
        $this->other->flashcardDecks()->attach($deck->id, ['folder_id' => null, 'position' => 0]);

        ContentSharing::assertCanAttach('deck', $deck->id, $this->group->id);

        $private = $this->deck();
        $this->other->flashcardDecks()->attach($private->id, ['folder_id' => null, 'position' => 0]);
        $this->expectException(ValidationException::class);
        ContentSharing::assertCanAttach('deck', $private->id, $this->group->id);
    }

    public function test_assert_can_attach_isolated_allows_ungrouped_content(): void
    {
        ContentSharing::setMode('isolated');
        $quiz = Quiz::create(['title' => 'Libre', 'duration' => 10, 'passing_score' => 50, 'is_active' => true]);

        ContentSharing::assertCanAttach('quiz', $quiz->id, $this->group->id);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------- page (UI)

    public function test_contents_page_renders_for_trainer(): void
    {
        $this->actingAs($this->trainerUser)->get($this->url())
            ->assertOk()
            ->assertSee('Promo A')
            ->assertSee('id="group-contents-app"', false)
            ->assertSee('data-url-candidates=', false)
            ->assertSee('data-url-copy=', false)
            ->assertSee('Nouveau dossier')
            ->assertSee('Ajouter un contenu existant');

        $this->actingAs($this->trainerUser)->get($this->url('', $this->other))->assertForbidden();
    }

    // --------------------------------------------------------- candidates

    public function test_candidates_lists_editable_contents_not_in_group(): void
    {
        $inGroup = $this->article(by: $this->trainerUser);
        $mine = Article::create(['title' => 'Algèbre linéaire', 'content' => 'x', 'is_active' => true, 'created_by' => $this->trainerUser->id]);
        Article::create(['title' => 'Algèbre — autre auteur', 'content' => 'x', 'is_active' => true, 'created_by' => $this->admin->id]);

        $res = $this->actingAs($this->trainerUser)
            ->getJson($this->url('/candidates?type=article'))
            ->assertOk();
        $this->assertSame([$mine->id], collect($res->json('items'))->pluck('id')->all());
        $this->assertNotContains($inGroup->id, collect($res->json('items'))->pluck('id')->all());
        $res->assertJsonPath('items.0.title', 'Algèbre linéaire')
            ->assertJsonPath('items.0.type', 'article');

        // Admin : tous les contenus hors groupe, filtrés par titre.
        $res = $this->actingAs($this->admin)->getJson($this->url('/candidates?type=article&q=autre'))->assertOk();
        $this->assertSame(['Algèbre — autre auteur'], collect($res->json('items'))->pluck('title')->all());
    }

    public function test_candidates_include_public_decks_and_flag_locked_in_isolated_mode(): void
    {
        $public = $this->deck(true);
        $owned = FlashcardDeck::create([
            'titre' => 'Deck privé', 'algorithme' => 'sm2', 'easiness_default' => 2.5,
            'interval_min' => 1, 'interval_max' => 365, 'active' => true, 'created_by' => $this->admin->id,
        ]);
        $this->other->flashcardDecks()->attach($owned->id);
        ContentSharing::setMode('isolated');

        $items = collect($this->actingAs($this->admin)->getJson($this->url('/candidates?type=deck'))->assertOk()->json('items'))->keyBy('id');
        $this->assertFalse($items[$public->id]['locked']);
        $this->assertTrue($items[$owned->id]['locked']);

        // Formateur : le deck public d'un autre auteur est proposé, pas le deck privé.
        $ids = collect($this->actingAs($this->trainerUser)->getJson($this->url('/candidates?type=deck'))->json('items'))->pluck('id')->all();
        $this->assertSame([$public->id], $ids);
    }

    public function test_candidates_validation_and_access(): void
    {
        $this->actingAs($this->admin)->getJson($this->url('/candidates?type=nope'))->assertStatus(422);
        $this->actingAs($this->trainerUser)->getJson($this->url('/candidates?type=quiz', $this->other))->assertForbidden();
    }
}
