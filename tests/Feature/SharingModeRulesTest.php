<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Modules\Core\Services\ContentSharing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Règle du mode `isolated` (un seul groupe par contenu) sur tous les points
 * d'attribution, et droits de modification des formateurs du groupe.
 */
class SharingModeRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $creator;

    private User $cotrainer;

    private Group $groupA;

    private Group $groupB;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Role::findOrCreate('trainer');

        $this->admin = $this->makeUser('admin');
        $this->admin->assignRole('admin');

        $this->groupA = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $this->groupB = Group::create(['name' => 'Promo B', 'is_active' => true]);

        // Créateur : formateur de A et B. Co-formateur : formateur de A seulement.
        $this->creator = $this->makeUser('createur');
        $this->creator->assignRole('trainer');
        $t1 = Trainer::create(['user_id' => $this->creator->id]);
        $t1->groups()->sync([$this->groupA->id, $this->groupB->id]);

        $this->cotrainer = $this->makeUser('coformateur');
        $this->cotrainer->assignRole('trainer');
        $t2 = Trainer::create(['user_id' => $this->cotrainer->id]);
        $t2->groups()->sync([$this->groupA->id]);
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

    private function isolated(): void
    {
        ContentSharing::setMode('isolated');
    }

    private function make(string $type, ?Group $group = null, bool $public = false): Model
    {
        $by = $this->creator->id;
        $content = match ($type) {
            'article' => Article::create(['title' => 'Article', 'content' => 'Contenu', 'is_active' => true, 'created_by' => $by]),
            'quiz' => Quiz::create(['title' => 'Quiz', 'duration' => 10, 'passing_score' => 50, 'is_active' => true, 'max_attempts' => 5, 'created_by' => $by]),
            'exam' => Exam::create([
                'title' => 'Examen', 'duration' => 30, 'passing_score' => 50, 'max_attempts' => 1,
                'publication_resultats' => 'immediate', 'note_max' => 20, 'is_active' => true, 'created_by' => $by,
            ]),
            'deck' => FlashcardDeck::create([
                'titre' => 'Deck', 'algorithme' => 'sm2', 'easiness_default' => 2.5,
                'interval_min' => 1, 'interval_max' => 365, 'active' => true, 'is_public' => $public, 'created_by' => $by,
            ]),
        };
        if ($group) {
            $content->groups()->attach($group->id, ['folder_id' => null, 'position' => 0]);
        }

        return $content;
    }

    private function assignUrl(string $type, int $id): string
    {
        return match ($type) {
            'article' => route('cores.editor.articles.groups.assign', $id),
            'quiz' => route('cores.editor.quizzes.groups.assign', $id),
            'deck' => route('cores.editor.flashcards.groups.assign', $id),
        };
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>} [méthode, url, données]
     */
    private function syncRequest(string $type, ?int $id, array $groupIds): array
    {
        $payload = match ($type) {
            'article' => ['title' => 'Article'],
            'quiz' => ['title' => 'Quiz', 'duration' => 10, 'passing_score' => 50],
            'exam' => [
                'title' => 'Examen', 'duration' => 30, 'passing_score' => 50, 'max_attempts' => 1,
                'publication_resultats' => 'immediate', 'note_max' => 20,
            ],
            'deck' => ['titre' => 'Deck', 'algorithme' => 'sm2'],
        };
        $prefix = match ($type) {
            'article' => '/cores/articles',
            'quiz' => '/cores/quizzes',
            'exam' => '/cores/exams',
            'deck' => '/cores/flashcard-decks',
        };

        return $id === null
            ? ['POST', $prefix, $payload + ['group_ids' => $groupIds]]
            : ['PUT', "{$prefix}/{$id}", $payload + ['group_ids' => $groupIds]];
    }

    private function groupIds(Model $content): array
    {
        return $content->groups()->pluck('groups.id')->sort()->values()->all();
    }

    // ------------------------------------------------------------------

    public function test_shared_allows_second_group(): void
    {
        $article = $this->make('article', $this->groupA);

        $this->actingAs($this->admin)
            ->postJson($this->assignUrl('article', $article->id), ['group_id' => $this->groupB->id])
            ->assertOk();

        [$method, $url, $data] = $this->syncRequest('exam', null, [$this->groupA->id, $this->groupB->id]);
        $this->actingAs($this->admin)->json($method, $url, $data)->assertOk();

        $this->assertSame([$this->groupA->id, $this->groupB->id], $this->groupIds($article));
        $this->assertSame([$this->groupA->id, $this->groupB->id], $this->groupIds(Exam::latest('id')->first()));
        $this->actingAs($this->admin)->get('/cores/exams')->assertOk()->assertDontSee("n'appartient qu'à", false);
    }

    public static function endpoints(): array
    {
        return [
            'article assign' => ['article', 'assign'],
            'quiz assign' => ['quiz', 'assign'],
            'deck assign' => ['deck', 'assign'],
            'article sync' => ['article', 'sync'],
            'quiz sync' => ['quiz', 'sync'],
            'exam sync' => ['exam', 'sync'],
            'deck sync' => ['deck', 'sync'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_isolated_rejects_second_group_all_endpoints(string $type, string $form): void
    {
        $this->isolated();
        $content = $this->make($type, $this->groupA);
        $expected = [$this->groupA->id];

        if ($form === 'assign') {
            $this->actingAs($this->creator)
                ->postJson($this->assignUrl($type, $content->id), ['group_id' => $this->groupB->id])
                ->assertStatus(422)
                ->assertJsonFragment(['message' => ContentSharing::ALREADY_OWNED]);

            // Réattribuer le même groupe reste permis.
            $this->actingAs($this->creator)
                ->postJson($this->assignUrl($type, $content->id), ['group_id' => $this->groupA->id])
                ->assertOk();
        } else {
            // Plus d'un groupe : refus, à la création comme à la modification.
            foreach ([null, $content->id] as $id) {
                [$method, $url, $data] = $this->syncRequest($type, $id, [$this->groupA->id, $this->groupB->id]);
                $this->actingAs($this->creator)->json($method, $url, $data)
                    ->assertStatus(422)
                    ->assertJsonFragment(['message' => ContentSharing::ALREADY_OWNED]);
            }
            // Un seul groupe, mais différent du groupe propriétaire : refus.
            [$method, $url, $data] = $this->syncRequest($type, $content->id, [$this->groupB->id]);
            $this->actingAs($this->creator)->json($method, $url, $data)
                ->assertStatus(422)
                ->assertJsonFragment(['message' => ContentSharing::ALREADY_OWNED]);

            // Rien n'a été créé à la création refusée.
            $this->assertSame(1, $content::query()->count());
        }

        $this->assertSame($expected, $this->groupIds($content));
    }

    public function test_isolated_public_deck_exempt(): void
    {
        $this->isolated();
        $deck = $this->make('deck', $this->groupA, public: true);

        $this->actingAs($this->creator)
            ->postJson($this->assignUrl('deck', $deck->id), ['group_id' => $this->groupB->id])
            ->assertOk();

        $created = $this->actingAs($this->creator)
            ->postJson('/cores/flashcard-decks', [
                'titre' => 'Public', 'algorithme' => 'sm2', 'is_public' => true,
                'group_ids' => [$this->groupA->id, $this->groupB->id],
            ])->assertOk()->json('deck.id');

        $this->assertSame([$this->groupA->id, $this->groupB->id], $this->groupIds($deck));
        $this->assertSame([$this->groupA->id, $this->groupB->id], $this->groupIds(FlashcardDeck::find($created)));
    }

    public function test_ungrouped_content_attach_ok(): void
    {
        $this->isolated();
        $article = $this->make('article');
        $exam = $this->make('exam');

        $this->actingAs($this->creator)
            ->postJson($this->assignUrl('article', $article->id), ['group_id' => $this->groupB->id])
            ->assertOk();

        [$method, $url, $data] = $this->syncRequest('exam', $exam->id, [$this->groupA->id]);
        $this->actingAs($this->creator)->json($method, $url, $data)->assertOk();

        $this->assertSame([$this->groupB->id], $this->groupIds($article));
        $this->assertSame([$this->groupA->id], $this->groupIds($exam));
    }

    public function test_sync_keeps_existing_placement(): void
    {
        $this->isolated();
        $folder = Folder::create(['group_id' => $this->groupA->id, 'name' => 'Module 1', 'position' => 0]);
        $deck = $this->make('deck');
        $deck->groups()->attach($this->groupA->id, ['folder_id' => $folder->id, 'position' => 4]);

        [$method, $url, $data] = $this->syncRequest('deck', $deck->id, [$this->groupA->id]);
        $this->actingAs($this->creator)->json($method, $url, $data)->assertOk();

        $pivot = DB::table('group_flashcard_deck')->where('flashcard_deck_id', $deck->id)->first();
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(4, (int) $pivot->position);
    }

    public function test_exam_update_without_group_ids_keeps_groups(): void
    {
        $this->isolated();
        $folder = Folder::create(['group_id' => $this->groupA->id, 'name' => 'Module 1', 'position' => 0]);
        $exam = $this->make('exam');
        $exam->groups()->attach($this->groupA->id, ['folder_id' => $folder->id, 'position' => 2]);

        // Autosave de l'éditeur : pas de clé group_ids.
        [$method, $url, $data] = $this->syncRequest('exam', $exam->id, []);
        unset($data['group_ids']);
        $this->actingAs($this->creator)->json($method, $url, $data)->assertOk();
        $this->assertSame($folder->id, (int) DB::table('group_exam')->where('exam_id', $exam->id)->value('folder_id'));

        // Sélection unique vidée : `group_ids` vide => plus aucun groupe.
        $data['group_ids'] = '';
        $this->actingAs($this->creator)->json($method, $url, $data)->assertOk();
        $this->assertSame([], $this->groupIds($exam));
    }

    public function test_empty_single_selection_is_ignored(): void
    {
        $this->isolated();

        $this->actingAs($this->creator)
            ->postJson('/cores/articles', ['title' => 'Sans groupe', 'group_ids' => ['']])
            ->assertOk();

        $this->assertSame([], $this->groupIds(Article::latest('id')->first()));
    }

    public function test_isolated_pages_render_single_selection(): void
    {
        $this->isolated();
        $article = $this->make('article', $this->groupA);

        $this->actingAs($this->admin)->get(route('cores.editor.articles.edit', $article->id))
            ->assertOk()
            ->assertSee('data-isolated="1"', false)
            ->assertSee('data-group-placement', false)
            ->assertSee('group-folder-slot', false);

        foreach (['/cores/articles', '/cores/quizzes', '/cores/exams', '/cores/flashcard-decks'] as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk()->assertSee("n'appartient qu'à", false);
        }
    }

    public function test_isolated_cotrainer_can_edit(): void
    {
        $this->isolated();
        $article = $this->make('article', $this->groupA);
        $quiz = $this->make('quiz', $this->groupA);
        $exam = $this->make('exam', $this->groupA);
        $deck = $this->make('deck', $this->groupA);

        $this->actingAs($this->cotrainer);
        $this->get(route('cores.editor.articles.edit', $article->id))->assertOk();
        $this->get(route('cores.editor.quizzes.edit', $quiz->id))->assertOk();
        $this->get(route('cores.editor.exams.edit', $exam->id))->assertOk();
        $this->get(route('cores.editor.flashcards.edit', $deck->id))->assertOk();

        [$method, $url, $data] = $this->syncRequest('exam', $exam->id, [$this->groupA->id]);
        $this->json($method, $url, $data)->assertOk();
        [$method, $url, $data] = $this->syncRequest('quiz', $quiz->id, [$this->groupA->id]);
        $this->json($method, $url, $data)->assertOk();

        // Un contenu hors de ses groupes reste interdit.
        $foreign = $this->make('article', $this->groupB);
        $this->get(route('cores.editor.articles.edit', $foreign->id))->assertForbidden();
    }

    public function test_shared_cotrainer_cannot_edit(): void
    {
        $article = $this->make('article', $this->groupA);
        $quiz = $this->make('quiz', $this->groupA);
        $exam = $this->make('exam', $this->groupA);
        $deck = $this->make('deck', $this->groupA);

        $this->actingAs($this->cotrainer);
        $this->get(route('cores.editor.articles.edit', $article->id))->assertForbidden();
        $this->get(route('cores.editor.quizzes.edit', $quiz->id))->assertForbidden();
        $this->get(route('cores.editor.exams.edit', $exam->id))->assertForbidden();
        $this->get(route('cores.editor.flashcards.edit', $deck->id))->assertForbidden();

        [$method, $url, $data] = $this->syncRequest('quiz', $quiz->id, [$this->groupA->id]);
        $this->json($method, $url, $data)->assertForbidden();
    }

    private function listIds(string $type): array
    {
        $prefix = ['article' => 'articles', 'quiz' => 'quizzes', 'exam' => 'exams', 'deck' => 'flashcard-decks'][$type];

        return collect($this->getJson("/cores/{$prefix}/data?limit=100")->assertOk()->json('rows'))
            ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    public static function types(): array
    {
        return ['article' => ['article'], 'quiz' => ['quiz'], 'exam' => ['exam'], 'deck' => ['deck']];
    }

    #[DataProvider('types')]
    public function test_isolated_cotrainer_lists_group_content(string $type): void
    {
        $inA = $this->make($type, $this->groupA);
        $inB = $this->make($type, $this->groupB);
        $own = $this->make($type);
        $own->update(['created_by' => $this->cotrainer->id]);

        // Mode partagé : inchangé, seulement ses propres contenus.
        $this->actingAs($this->cotrainer);
        $this->assertSame([$own->id], $this->listIds($type));

        $this->isolated();
        $this->assertSame([$inA->id, $own->id], $this->listIds($type));
        $this->assertNotContains($inB->id, $this->listIds($type));
    }

    #[DataProvider('types')]
    public function test_isolated_cotrainer_can_update_but_not_delete(string $type): void
    {
        $this->isolated();
        $content = $this->make($type, $this->groupA);
        $prefix = ['article' => '/cores/articles', 'quiz' => '/cores/quizzes', 'exam' => '/cores/exams', 'deck' => '/cores/flashcard-decks'][$type];

        $this->actingAs($this->cotrainer);
        [$method, $url, $data] = $this->syncRequest($type, $content->id, [$this->groupA->id]);
        $this->json($method, $url, $data)->assertOk();

        $this->deleteJson("{$prefix}/{$content->id}")->assertForbidden();
        $this->assertNotNull($content->fresh());

        // Le créateur garde le droit de supprimer.
        $this->actingAs($this->creator)->deleteJson("{$prefix}/{$content->id}")->assertOk();
    }

    public function test_assign_with_folder_id(): void
    {
        $this->isolated();
        $folder = Folder::create(['group_id' => $this->groupA->id, 'name' => 'Module 1', 'position' => 0]);
        $foreignFolder = Folder::create(['group_id' => $this->groupB->id, 'name' => 'Ailleurs', 'position' => 0]);
        $other = $this->make('article');
        $other->groups()->attach($this->groupA->id, ['folder_id' => $folder->id, 'position' => 0]);

        $quiz = $this->make('quiz');
        $this->actingAs($this->creator)
            ->postJson($this->assignUrl('quiz', $quiz->id), ['group_id' => $this->groupA->id, 'folder_id' => $foreignFolder->id])
            ->assertStatus(422);
        $this->assertSame([], $this->groupIds($quiz));

        $this->actingAs($this->creator)
            ->postJson($this->assignUrl('quiz', $quiz->id), ['group_id' => $this->groupA->id, 'folder_id' => $folder->id])
            ->assertOk();
        $pivot = DB::table('group_quiz')->where('quiz_id', $quiz->id)->first();
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(1, (int) $pivot->position);

        // Contenu déjà dans le groupe : folder_id le déplace en fin de dossier.
        $deck = $this->make('deck', $this->groupA);
        $this->actingAs($this->creator)
            ->postJson($this->assignUrl('deck', $deck->id), ['group_id' => $this->groupA->id, 'folder_id' => $folder->id])
            ->assertOk();
        $pivot = DB::table('group_flashcard_deck')->where('flashcard_deck_id', $deck->id)->first();
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(2, (int) $pivot->position);

        $article = $this->make('article');
        $this->actingAs($this->creator)
            ->postJson($this->assignUrl('article', $article->id), ['group_id' => $this->groupA->id, 'folder_id' => $folder->id])
            ->assertOk();
        $this->assertSame($folder->id, (int) DB::table('group_article')->where('article_id', $article->id)->value('folder_id'));
    }
}
