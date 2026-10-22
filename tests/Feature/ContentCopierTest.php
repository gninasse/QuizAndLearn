<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Article;
use Modules\Core\Models\ArticleMedia;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamQuestion;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Resource;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Modules\Core\Services\ContentCopier;
use Modules\Core\Services\ContentSharing;
use Modules\Core\Services\MediaReferences;
use Tests\TestCase;

/**
 * « Copier vers… » : copie profonde d'un contenu vers un autre groupe.
 */
class ContentCopierTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $creator;

    private User $outsider;

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

        // Créateur : formateur de A et B. Outsider : formateur de B seulement.
        $this->creator = $this->makeUser('createur');
        $this->creator->assignRole('trainer');
        Trainer::create(['user_id' => $this->creator->id])->groups()->sync([$this->groupA->id, $this->groupB->id]);

        $this->outsider = $this->makeUser('outsider');
        $this->outsider->assignRole('trainer');
        Trainer::create(['user_id' => $this->outsider->id])->groups()->sync([$this->groupB->id]);
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

    private function copier(): ContentCopier
    {
        return app(ContentCopier::class);
    }

    private function quiz(): Quiz
    {
        $quiz = Quiz::create([
            'title' => 'Quiz source', 'description' => 'Desc', 'duration' => 10, 'passing_score' => 60,
            'is_active' => true, 'max_attempts' => 3, 'shuffle_questions' => true, 'created_by' => $this->creator->id,
        ]);
        $quiz->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        foreach ([1, 2] as $i) {
            Question::create([
                'quiz_id' => $quiz->id, 'question_text' => "Q{$i}", 'type' => 'single', 'points' => $i, 'order' => $i,
                'options' => [['text' => 'Oui', 'is_correct' => true], ['text' => 'Non', 'is_correct' => false]],
            ]);
        }

        return $quiz;
    }

    private function url(Group $source, string $type, int $id): string
    {
        return route('cores.groups.contents.items.copy', ['id' => $source->id, 'type' => $type, 'itemId' => $id]);
    }

    public function test_copy_quiz_with_questions_and_map(): void
    {
        $quiz = $this->quiz();
        Folder::create(['group_id' => $this->groupB->id, 'name' => 'Existant', 'position' => 0]);

        $result = $this->copier()->copy('quiz', $quiz->id, $this->groupB->id, null, $this->admin);

        $copy = $result['model'];
        $this->assertInstanceOf(Quiz::class, $copy);
        $this->assertNotSame($quiz->id, $copy->id);
        $this->assertSame('Quiz source', $copy->title);
        $this->assertSame(60, $copy->fresh()->passing_score);
        $this->assertTrue($copy->fresh()->shuffle_questions);
        $this->assertSame($this->admin->id, (int) $copy->created_by);

        // Seul le groupe cible, à la racine, après le dossier existant.
        $this->assertSame([$this->groupB->id], $copy->groups()->pluck('groups.id')->all());
        $pivot = DB::table('group_quiz')->where('quiz_id', $copy->id)->first();
        $this->assertNull($pivot->folder_id);
        $this->assertSame(1, (int) $pivot->position);
        // L'original reste où il était.
        $this->assertSame([$this->groupA->id], $quiz->groups()->pluck('groups.id')->all());

        $originals = $quiz->questions()->get();
        $copies = $copy->questions()->get();
        $this->assertCount(2, $copies);
        $this->assertSame(['Q1', 'Q2'], $copies->pluck('question_text')->all());
        $this->assertEquals($originals->pluck('options')->all(), $copies->pluck('options')->all());

        $map = $result['map'];
        $this->assertSame([], $map['items']);
        $this->assertSame([], $map['exam_questions']);
        $this->assertCount(2, $map['questions']);
        foreach ($originals as $i => $q) {
            $this->assertSame($copies[$i]->id, $map['questions'][$q->id]);
        }
    }

    public function test_copy_deck_items(): void
    {
        $deck = FlashcardDeck::create([
            'titre' => 'Deck source', 'algorithme' => 'sm2', 'easiness_default' => 2.5, 'interval_min' => 1,
            'interval_max' => 365, 'active' => true, 'source_type' => 'quiz', 'source_id' => 42, 'created_by' => $this->creator->id,
        ]);
        $deck->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        $item = FlashcardItem::create([
            'deck_id' => $deck->id, 'recto' => 'R', 'verso' => 'V', 'ordre' => 1,
            'recto_media' => ['type' => 'image', 'path' => 'flashcards/a.png'], 'verso_media' => null,
            'total_revisions' => 7, 'taux_reussite' => 80,
        ]);
        $folder = Folder::create(['group_id' => $this->groupB->id, 'name' => 'Cible', 'position' => 0]);

        $result = $this->copier()->copy('deck', $deck->id, $this->groupB->id, $folder->id, $this->creator);

        $copy = $result['model'];
        $this->assertSame('Deck source', $copy->titre);
        $this->assertSame('quiz', $copy->source_type);
        $this->assertSame(42, (int) $copy->source_id);
        $this->assertSame($folder->id, (int) DB::table('group_flashcard_deck')->where('flashcard_deck_id', $copy->id)->value('folder_id'));

        $cards = $copy->cards()->get();
        $this->assertCount(1, $cards);
        $this->assertSame(['type' => 'image', 'path' => 'flashcards/a.png'], $cards[0]->recto_media);
        $this->assertSame('R', $cards[0]->recto);
        // Aucun historique : statistiques remises à zéro.
        $this->assertSame(0, $cards[0]->total_revisions);
        $this->assertEquals(0.0, $cards[0]->taux_reussite);
        $this->assertSame([$item->id => $cards[0]->id], $result['map']['items']);
    }

    public function test_copy_exam_questions(): void
    {
        $exam = Exam::create([
            'title' => 'Examen source', 'duration' => 30, 'passing_score' => 50, 'max_attempts' => 1,
            'publication_resultats' => 'immediate', 'note_max' => 20, 'is_active' => true, 'created_by' => $this->creator->id,
        ]);
        $exam->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        $q = ExamQuestion::create([
            'exam_id' => $exam->id, 'question_text' => 'EQ', 'type' => 'single', 'points' => 2,
            'points_negatifs' => 0.5, 'order' => 1, 'options' => [['text' => 'A', 'is_correct' => true]],
        ]);

        $result = $this->copier()->copy('exam', $exam->id, $this->groupB->id, null, $this->admin);

        $copy = $result['model'];
        $this->assertSame('Examen source', $copy->title);
        $copies = $copy->questions()->get();
        $this->assertCount(1, $copies);
        $this->assertSame('0.50', $copies[0]->points_negatifs);
        $this->assertSame([$q->id => $copies[0]->id], $result['map']['exam_questions']);
        $this->assertSame(0, $copy->attempts()->count());
    }

    public function test_copy_resource_shares_file(): void
    {
        $resource = Resource::create([
            'kind' => 'document', 'title' => 'Cours', 'disk' => 'local', 'path' => 'resources/1/abc.docx',
            'pdf_path' => 'resources/1/abc.pdf', 'conversion_status' => 'ready', 'mime' => 'application/msword',
            'size' => 123, 'original_name' => 'cours.docx', 'allow_download' => true, 'is_active' => true,
            'created_by' => $this->creator->id,
        ]);
        $resource->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);

        $copy = $this->copier()->copy('resource', $resource->id, $this->groupB->id, null, $this->admin)['model'];

        $this->assertNotSame($resource->id, $copy->id);
        $this->assertSame('resources/1/abc.docx', $copy->path);
        $this->assertSame('resources/1/abc.pdf', $copy->pdf_path);
        $this->assertSame('ready', $copy->conversion_status);
        $this->assertTrue($copy->allow_download);
        $this->assertSame([$this->groupB->id], $copy->groups()->pluck('groups.id')->all());
    }

    public function test_copy_article_shares_media_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/img.png', 'x');
        $article = Article::create(['title' => 'Article', 'content' => '<p>Texte</p>', 'is_active' => true, 'created_by' => $this->creator->id]);
        $article->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        ArticleMedia::create(['article_id' => $article->id, 'file_path' => 'articles/img.png', 'file_type' => 'image', 'original_name' => 'img.png']);

        $copy = $this->copier()->copy('article', $article->id, $this->groupB->id, null, $this->admin)['model'];

        $this->assertSame('<p>Texte</p>', $copy->content);
        $this->assertSame(['articles/img.png'], $copy->media()->pluck('file_path')->all());

        // Supprimer l'original ne supprime pas le fichier encore utilisé par la copie.
        $article->delete();
        Storage::disk('public')->assertExists('articles/img.png');
        $copy->fresh()->delete();
        Storage::disk('public')->assertMissing('articles/img.png');
    }

    public function test_copy_is_independent(): void
    {
        $quiz = $this->quiz();
        $copy = $this->copier()->copy('quiz', $quiz->id, $this->groupB->id, null, $this->admin)['model'];

        $copy->update(['title' => 'Copie modifiée']);
        $copy->questions()->first()->update(['question_text' => 'Changée']);
        $copy->questions()->orderByDesc('order')->first()->delete();

        $this->assertSame('Quiz source', $quiz->fresh()->title);
        $this->assertSame(['Q1', 'Q2'], $quiz->questions()->pluck('question_text')->all());
    }

    public function test_copy_at_position(): void
    {
        $quiz = $this->quiz();
        $first = Folder::create(['group_id' => $this->groupB->id, 'name' => 'Un', 'position' => 0]);
        Folder::create(['group_id' => $this->groupB->id, 'name' => 'Deux', 'position' => 1]);

        $copy = $this->copier()->copy('quiz', $quiz->id, $this->groupB->id, null, $this->admin, 0)['model'];

        $this->assertSame(0, (int) DB::table('group_quiz')->where('quiz_id', $copy->id)->value('position'));
        $this->assertSame(1, (int) $first->fresh()->position);
    }

    public function test_copy_to_folder_of_other_group_is_rejected(): void
    {
        $quiz = $this->quiz();
        $foreign = Folder::create(['group_id' => $this->groupA->id, 'name' => 'A', 'position' => 0]);

        $this->actingAs($this->admin)
            ->postJson($this->url($this->groupA, 'quiz', $quiz->id), ['target_group_id' => $this->groupB->id, 'target_folder_id' => $foreign->id])
            ->assertStatus(422);

        $this->assertSame(1, Quiz::count());
    }

    public function test_copy_endpoint_in_isolated_mode(): void
    {
        ContentSharing::setMode('isolated');
        $quiz = $this->quiz();
        $folder = Folder::create(['group_id' => $this->groupB->id, 'name' => 'Cible', 'position' => 0]);

        $res = $this->actingAs($this->creator)
            ->postJson($this->url($this->groupA, 'quiz', $quiz->id), ['target_group_id' => $this->groupB->id, 'target_folder_id' => $folder->id])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $copy = Quiz::findOrFail($res->json('item.id'));
        $this->assertSame([$this->groupB->id], $copy->groups()->pluck('groups.id')->all());
        $this->assertSame($folder->id, (int) DB::table('group_quiz')->where('quiz_id', $copy->id)->value('folder_id'));
        $this->assertSame($this->creator->id, (int) $copy->created_by);
    }

    public function test_editors_render_copy_button_in_isolated_mode(): void
    {
        ContentSharing::setMode('isolated');
        $quiz = $this->quiz();

        $this->actingAs($this->creator)->get(route('cores.editor.quizzes.edit', $quiz->id))
            ->assertOk()
            ->assertSee('btn-copy-to-group', false)
            ->assertSee('/contenus/items/quiz/'.$quiz->id.'/copy', false)
            ->assertSee('Promo B');
    }

    public function test_editors_render_copy_button_in_shared_mode(): void
    {
        $quiz = $this->quiz();

        $this->actingAs($this->creator)->get(route('cores.editor.quizzes.edit', $quiz->id))
            ->assertOk()
            ->assertSee('data-isolated="0"', false)
            ->assertSee('btn-copy-to-group', false)
            ->assertSee('/contenus/items/quiz/'.$quiz->id.'/copy', false);
    }

    private function img(string $path): string
    {
        return '<p>Voir</p><img src="'.asset('storage/'.$path).'" alt="">';
    }

    public function test_deleting_original_question_keeps_inline_image_used_by_copy(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('editor/q.png', 'x');
        $quiz = $this->quiz();
        $question = $quiz->questions()->first();
        $question->update(['question_text' => $this->img('editor/q.png')]);

        $copy = $this->copier()->copy('quiz', $quiz->id, $this->groupB->id, null, $this->admin)['model'];

        $question->delete();
        Storage::disk('public')->assertExists('editor/q.png');

        // Dernière référence supprimée : le fichier part.
        $copy->questions()->where('question_text', 'like', '%editor/q.png%')->first()->delete();
        Storage::disk('public')->assertMissing('editor/q.png');
    }

    public function test_deleting_exam_and_deck_copies_keeps_shared_inline_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('editor/e.png', 'x');
        Storage::disk('public')->put('editor/r.png', 'x');

        $exam = Exam::create([
            'title' => 'Examen', 'duration' => 30, 'passing_score' => 50, 'max_attempts' => 1,
            'publication_resultats' => 'immediate', 'note_max' => 20, 'is_active' => true, 'created_by' => $this->creator->id,
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_text' => $this->img('editor/e.png'), 'type' => 'single', 'points' => 1, 'order' => 1, 'options' => []]);
        $deck = FlashcardDeck::create([
            'titre' => 'Deck', 'algorithme' => 'sm2', 'easiness_default' => 2.5, 'interval_min' => 1,
            'interval_max' => 365, 'active' => true, 'created_by' => $this->creator->id,
        ]);
        FlashcardItem::create(['deck_id' => $deck->id, 'recto' => $this->img('editor/r.png'), 'verso' => 'V', 'ordre' => 1]);

        $examCopy = $this->copier()->copy('exam', $exam->id, $this->groupB->id, null, $this->admin)['model'];
        $deckCopy = $this->copier()->copy('deck', $deck->id, $this->groupB->id, null, $this->admin)['model'];

        $examCopy->delete();
        $deckCopy->delete();
        Storage::disk('public')->assertExists('editor/e.png');
        Storage::disk('public')->assertExists('editor/r.png');

        $exam->fresh()->delete();
        $deck->fresh()->delete();
        Storage::disk('public')->assertMissing('editor/e.png');
        Storage::disk('public')->assertMissing('editor/r.png');
    }

    public function test_media_references_cover_json_and_article_columns(): void
    {
        $item = FlashcardItem::create([
            'deck_id' => FlashcardDeck::create([
                'titre' => 'Deck', 'algorithme' => 'sm2', 'easiness_default' => 2.5, 'interval_min' => 1,
                'interval_max' => 365, 'active' => true, 'created_by' => $this->creator->id,
            ])->id,
            'recto' => 'R', 'verso' => 'V', 'ordre' => 1, 'recto_media' => ['type' => 'image', 'path' => 'cards/m_1.png'],
        ]);
        $article = Article::create(['title' => 'A', 'content' => $this->img('articles/inline.png'), 'is_active' => true, 'created_by' => $this->creator->id]);

        $this->assertTrue(MediaReferences::isReferenced('cards/m_1.png'));
        $this->assertFalse(MediaReferences::isReferenced('cards/m_1.png', $item));
        // « _ » n'est pas un joker LIKE.
        $this->assertFalse(MediaReferences::isReferenced('cards/mX1.png'));
        $this->assertTrue(MediaReferences::isReferenced('articles/inline.png'));
        $this->assertFalse(MediaReferences::isReferenced('articles/inline.png', $article));
        $this->assertFalse(MediaReferences::isReferenced('nowhere.png'));
    }

    public function test_creator_can_copy_from_a_source_group_they_do_not_manage(): void
    {
        // Créateur formateur de Y et Z seulement ; le quiz est dans X (affiché en premier) et Y.
        $x = Group::create(['name' => 'Promo X', 'is_active' => true]);
        $y = Group::create(['name' => 'Promo Y', 'is_active' => true]);
        $z = Group::create(['name' => 'Promo Z', 'is_active' => true]);
        $author = $this->makeUser('auteur');
        $author->assignRole('trainer');
        Trainer::create(['user_id' => $author->id])->groups()->sync([$y->id, $z->id]);
        $quiz = Quiz::create(['title' => 'Q', 'duration' => 10, 'passing_score' => 50, 'is_active' => true, 'max_attempts' => 1, 'created_by' => $author->id]);
        $quiz->groups()->attach([$x->id => ['folder_id' => null, 'position' => 0], $y->id => ['folder_id' => null, 'position' => 0]]);

        $res = $this->actingAs($author)
            ->postJson($this->url($x, 'quiz', $quiz->id), ['target_group_id' => $z->id])
            ->assertCreated();

        $this->assertSame([$z->id], Quiz::findOrFail($res->json('item.id'))->groups()->pluck('groups.id')->all());

        // La cible doit, elle, être gérée.
        $this->actingAs($author)->postJson($this->url($x, 'quiz', $quiz->id), ['target_group_id' => $x->id])->assertForbidden();
    }

    public function test_copy_of_public_deck_is_not_public(): void
    {
        $deck = FlashcardDeck::create([
            'titre' => 'Deck public', 'algorithme' => 'sm2', 'easiness_default' => 2.5, 'interval_min' => 1,
            'interval_max' => 365, 'active' => true, 'is_public' => true, 'created_by' => $this->creator->id,
        ]);
        $deck->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        $folder = Folder::create(['group_id' => $this->groupB->id, 'name' => 'Cible', 'position' => 0]);

        $copy = $this->copier()->copy('deck', $deck->id, $this->groupB->id, $folder->id, $this->admin)['model'];

        $this->assertFalse($copy->fresh()->is_public);
        $this->assertTrue($deck->fresh()->is_public);
        $this->assertSame($folder->id, (int) DB::table('group_flashcard_deck')->where('flashcard_deck_id', $copy->id)->value('folder_id'));
    }

    public function test_copy_requires_rights(): void
    {
        $quiz = $this->quiz();
        $url = $this->url($this->groupA, 'quiz', $quiz->id);
        $body = ['target_group_id' => $this->groupB->id];

        // Ne peut pas modifier le contenu (gère pourtant le groupe cible).
        $this->actingAs($this->outsider)->postJson($url, $body)->assertForbidden();

        // Gère la source mais ne peut pas modifier le contenu (créé par un autre, mode shared).
        $other = $this->makeUser('autre');
        $other->assignRole('trainer');
        Trainer::create(['user_id' => $other->id])->groups()->sync([$this->groupA->id, $this->groupB->id]);
        $this->actingAs($other)->postJson($url, $body)->assertForbidden();

        // Peut modifier le contenu mais ne gère pas le groupe cible.
        $groupC = Group::create(['name' => 'Promo C', 'is_active' => true]);
        $this->actingAs($this->creator)->postJson($url, ['target_group_id' => $groupC->id])->assertForbidden();

        // Contenu non rattaché au groupe source : 404.
        $this->actingAs($this->admin)
            ->postJson($this->url($this->groupB, 'quiz', $quiz->id), $body)
            ->assertNotFound();

        // Groupe cible requis.
        $this->actingAs($this->admin)->postJson($url, [])->assertStatus(422);

        $this->assertSame(1, Quiz::count());

        // Admin : autorisé.
        $this->actingAs($this->admin)->postJson($url, ['target_group_id' => $groupC->id])->assertCreated();
        $this->assertSame(2, Quiz::count());
    }
}
