<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Jobs\ConvertDocumentToPdf;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamQuestion;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Resource;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ContentSharing;
use Modules\Core\Services\IsolationMigrator;
use Tests\TestCase;

/**
 * Bascule `shared` → `isolated` : copie des contenus multi-groupes et
 * transfert de l'historique des apprenants vers la copie de leur groupe.
 */
class IsolationMigratorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Group $groupA;

    private Group $groupB;

    private Group $groupC;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');

        $this->admin = $this->makeUser('admin');
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->groupA = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $this->groupB = Group::create(['name' => 'Promo B', 'is_active' => true]);
        $this->groupC = Group::create(['name' => 'Promo C', 'is_active' => true]);
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => true, 'password' => bcrypt('password'),
        ]);
    }

    /** Apprenant membre des groupes donnés. */
    private function learner(Group ...$groups): Learner
    {
        $n = ++$this->seq;
        $learner = Learner::create(['user_id' => $this->makeUser("app{$n}")->id, 'matricule' => "MAT-{$n}"]);
        $learner->groups()->attach(array_map(fn (Group $g) => $g->id, $groups));

        return $learner;
    }

    private function migrator(): IsolationMigrator
    {
        return app(IsolationMigrator::class);
    }

    private function quiz(array $groups): Quiz
    {
        $quiz = Quiz::create([
            'title' => 'Quiz partagé', 'description' => 'Desc', 'duration' => 10, 'passing_score' => 60,
            'is_active' => true, 'max_attempts' => 3, 'created_by' => $this->admin->id,
        ]);
        foreach ($groups as $g) {
            $quiz->groups()->attach($g->id, ['folder_id' => null, 'position' => 0]);
        }
        foreach ([1, 2] as $i) {
            Question::create([
                'quiz_id' => $quiz->id, 'question_text' => "Q{$i}", 'type' => 'true_false', 'points' => 1, 'order' => $i,
                'options' => ['correct_answer' => 'true'],
            ]);
        }

        return $quiz;
    }

    private function deck(array $groups, bool $public = false): FlashcardDeck
    {
        $deck = FlashcardDeck::create([
            'titre' => $public ? 'Deck public' : 'Deck', 'algorithme' => 'sm2', 'easiness_default' => 2.5, 'interval_min' => 1,
            'interval_max' => 365, 'active' => true, 'is_public' => $public, 'created_by' => $this->admin->id,
        ]);
        foreach ($groups as $g) {
            $deck->groups()->attach($g->id, ['folder_id' => null, 'position' => 0]);
        }

        return $deck;
    }

    public function test_conflicts_exclude_public_decks(): void
    {
        $this->deck([$this->groupA, $this->groupB], public: true);
        $private = $this->deck([$this->groupA, $this->groupB]);
        $this->quiz([$this->groupA]);
        $shared = $this->quiz([$this->groupC, $this->groupA]);

        $conflicts = $this->migrator()->conflicts();

        $this->assertCount(2, $conflicts);
        $byKey = $conflicts->keyBy(fn ($c) => $c['type'].':'.$c['id']);
        $this->assertSame([$this->groupA->id, $this->groupB->id], $byKey["deck:{$private->id}"]['group_ids']);
        $this->assertSame('Deck', $byKey["deck:{$private->id}"]['title']);
        $this->assertSame([$this->groupA->id, $this->groupC->id], $byKey["quiz:{$shared->id}"]['group_ids']);
        $this->assertSame('Quiz partagé', $byKey["quiz:{$shared->id}"]['title']);
    }

    public function test_plan_keeps_largest_group(): void
    {
        $quiz = $this->quiz([$this->groupA, $this->groupB, $this->groupC]);
        $this->learner($this->groupA);
        $this->learner($this->groupB);
        $this->learner($this->groupB);
        $this->learner($this->groupC);
        $this->learner($this->groupC);
        // Égalité B / C : le plus petit id (B) garde l'original.
        $deck = $this->deck([$this->groupB, $this->groupC]);

        $plan = collect($this->migrator()->plan())->keyBy(fn ($p) => $p['type'].':'.$p['id']);

        $this->assertSame($this->groupB->id, $plan["quiz:{$quiz->id}"]['keep_group_id']);
        $this->assertSame([
            ['group_id' => $this->groupA->id, 'learners' => 1],
            ['group_id' => $this->groupC->id, 'learners' => 2],
        ], $plan["quiz:{$quiz->id}"]['copies']);
        $this->assertSame($this->groupB->id, $plan["deck:{$deck->id}"]['keep_group_id']);
        $this->assertSame([['group_id' => $this->groupC->id, 'learners' => 2]], $plan["deck:{$deck->id}"]['copies']);
    }

    public function test_apply_copies_into_same_folder(): void
    {
        $this->learner($this->groupA);
        $this->learner($this->groupA);
        $this->learner($this->groupB);

        $quiz = $this->quiz([$this->groupA]);
        $author = $this->makeUser('auteur');
        $quiz->update(['created_by' => $author->id]);
        $folder = Folder::create(['group_id' => $this->groupB->id, 'name' => 'Module 1', 'position' => 0]);
        $before = $this->quiz([]);
        $after = $this->quiz([]);
        $before->groups()->attach($this->groupB->id, ['folder_id' => $folder->id, 'position' => 0]);
        $after->groups()->attach($this->groupB->id, ['folder_id' => $folder->id, 'position' => 2]);
        $quiz->groups()->attach($this->groupB->id, ['folder_id' => $folder->id, 'position' => 1]);

        $result = $this->migrator()->apply();

        $this->assertSame(1, $result['copied']);
        // L'original reste seul dans A, à sa place.
        $this->assertSame([$this->groupA->id], $quiz->groups()->pluck('groups.id')->all());
        $copyId = DB::table('group_quiz')->where('group_id', $this->groupB->id)
            ->whereNotIn('quiz_id', [$before->id, $after->id])->value('quiz_id');
        $this->assertNotNull($copyId);
        $this->assertNotSame($quiz->id, (int) $copyId);
        $pivot = DB::table('group_quiz')->where('group_id', $this->groupB->id)->where('quiz_id', $copyId)->first();
        $this->assertSame($folder->id, (int) $pivot->folder_id);
        $this->assertSame(1, (int) $pivot->position);
        // Les voisins ne bougent pas.
        $this->assertSame(0, (int) DB::table('group_quiz')->where('quiz_id', $before->id)->value('position'));
        $this->assertSame(2, (int) DB::table('group_quiz')->where('quiz_id', $after->id)->value('position'));
        $this->assertSame(2, Quiz::find($copyId)->questions()->count());
        // Copie automatique : l'auteur de l'original est conservé.
        $this->assertSame($author->id, (int) Quiz::find($copyId)->created_by);

        // Rejouer ne trouve plus rien.
        $this->assertCount(0, $this->migrator()->conflicts());
        $this->assertSame(['copied' => 0, 'moved_rows' => 0, 'duplicated_attempts' => 0], $this->migrator()->apply());
    }

    public function test_pending_document_copy_gets_one_conversion_job(): void
    {
        Queue::fake();
        $resource = Resource::create([
            'kind' => 'document', 'title' => 'Cours', 'disk' => 'local', 'path' => 'resources/1/abc.docx',
            'conversion_status' => 'pending', 'mime' => 'application/msword', 'size' => 123,
            'original_name' => 'cours.docx', 'allow_download' => true, 'is_active' => true, 'created_by' => null,
        ]);
        $resource->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        $resource->groups()->attach($this->groupB->id, ['folder_id' => null, 'position' => 0]);

        $this->assertSame(1, $this->migrator()->apply()['copied']);

        $copyId = (int) DB::table('group_resource')->where('group_id', $this->groupB->id)->value('resource_id');
        $this->assertNotSame($resource->id, $copyId);
        Queue::assertPushed(ConvertDocumentToPdf::class, 1);
        Queue::assertPushed(ConvertDocumentToPdf::class, fn ($job) => $job->resourceId === $copyId && $job->afterCommit === true);
        // Auteur d'origine absent : repli sur l'admin qui bascule.
        $this->assertSame($this->admin->id, (int) Resource::find($copyId)->created_by);
    }

    public function test_exam_attempts_follow_copy_and_max_attempts_preserved(): void
    {
        $this->learner($this->groupA);
        $this->learner($this->groupA);
        $learner = $this->learner($this->groupB);

        $exam = Exam::create([
            'title' => 'Examen final', 'description' => 'Desc', 'duration' => 30, 'passing_score' => 50,
            'is_active' => true, 'max_attempts' => 1, 'note_max' => 20, 'created_by' => $this->admin->id,
        ]);
        $exam->groups()->attach($this->groupA->id, ['folder_id' => null, 'position' => 0]);
        $exam->groups()->attach($this->groupB->id, ['folder_id' => null, 'position' => 0]);
        $q1 = ExamQuestion::create([
            'exam_id' => $exam->id, 'question_text' => 'V/F', 'type' => 'true_false', 'points' => 5,
            'points_negatifs' => 0, 'order' => 1, 'options' => ['correct_answer' => 'true'],
        ]);
        $q2 = ExamQuestion::create([
            'exam_id' => $exam->id, 'question_text' => 'QCM', 'type' => 'mcq', 'points' => 5,
            'points_negatifs' => 0, 'order' => 2, 'options' => ['choices' => [['text' => 'A', 'is_correct' => true]]],
        ]);
        $attemptId = DB::table('exam_attempts')->insertGetId([
            'exam_id' => $exam->id, 'learner_id' => $learner->id, 'date_debut' => now()->subHour(),
            'date_fin' => now()->subMinutes(30), 'status' => 'termine', 'note_sur_vingt' => 14,
            'answers' => json_encode([$q1->id => 'true', $q2->id => ['A']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migrator()->apply();

        $copyId = (int) DB::table('group_exam')->where('group_id', $this->groupB->id)->value('exam_id');
        $this->assertNotSame($exam->id, $copyId);
        $copyQuestions = ExamQuestion::where('exam_id', $copyId)->orderBy('order')->pluck('id')->all();

        $attempt = DB::table('exam_attempts')->find($attemptId);
        $this->assertSame($copyId, (int) $attempt->exam_id);
        $this->assertEquals([$copyQuestions[0] => 'true', $copyQuestions[1] => ['A']], json_decode($attempt->answers, true));
        $this->assertSame(1, (int) Exam::find($copyId)->max_attempts);

        // Review Focus 4 : le maximum atteint reste atteint sur la copie.
        $this->actingAs($learner->user)
            ->postJson(route('learn.v1.exams.attempts.store', $copyId))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Nombre maximum de tentatives atteint.');
        $this->assertSame(1, DB::table('exam_attempts')->where('learner_id', $learner->id)->count());
    }

    /** Examen à 2 questions (max 1 tentative) attribué aux groupes donnés. */
    private function exam(array $groups): Exam
    {
        $exam = Exam::create([
            'title' => 'Examen partagé', 'description' => 'Desc', 'duration' => 30, 'passing_score' => 50,
            'is_active' => true, 'max_attempts' => 1, 'note_max' => 20, 'created_by' => $this->admin->id,
        ]);
        foreach ($groups as $g) {
            $exam->groups()->attach($g->id, ['folder_id' => null, 'position' => 0]);
        }
        foreach ([1, 2] as $i) {
            ExamQuestion::create([
                'exam_id' => $exam->id, 'question_text' => "Q{$i}", 'type' => 'true_false', 'points' => 5,
                'points_negatifs' => 0, 'order' => $i, 'options' => ['correct_answer' => 'true'],
            ]);
        }

        return $exam;
    }

    private function examAttempt(Exam $exam, Learner $learner, string $status = 'termine'): int
    {
        [$q1, $q2] = ExamQuestion::where('exam_id', $exam->id)->orderBy('order')->pluck('id')->all();

        return DB::table('exam_attempts')->insertGetId([
            'exam_id' => $exam->id, 'learner_id' => $learner->id, 'date_debut' => now()->subHour(),
            'date_fin' => now()->subMinutes(30), 'status' => $status, 'note_sur_vingt' => 12,
            'answers' => json_encode([$q1 => 'true', $q2 => 'false']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assertExamLocked(Learner $learner, int $examId): void
    {
        $this->actingAs($learner->user)
            ->postJson(route('learn.v1.exams.attempts.store', $examId))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Nombre maximum de tentatives atteint.');
    }

    public function test_exam_learner_in_kept_and_copy_group_cannot_retake_on_copy(): void
    {
        $this->learner($this->groupA);
        $this->learner($this->groupA);
        $both = $this->learner($this->groupA, $this->groupB); // voit l'original (A) et la copie (B)
        $exam = $this->exam([$this->groupA, $this->groupB]);
        $original = $this->examAttempt($exam, $both);
        $this->examAttempt($exam, $both, 'en_cours'); // non comptée : jamais dupliquée

        $result = $this->migrator()->apply();

        $copyId = (int) DB::table('group_exam')->where('group_id', $this->groupB->id)->value('exam_id');
        $this->assertNotSame($exam->id, $copyId);
        $this->assertSame(1, $result['duplicated_attempts']);
        // L'original garde sa tentative, la copie en reçoit un double aux réponses remappées.
        $this->assertSame($exam->id, (int) DB::table('exam_attempts')->where('id', $original)->value('exam_id'));
        $dup = DB::table('exam_attempts')->where('exam_id', $copyId)->where('learner_id', $both->id)->get();
        $this->assertCount(1, $dup);
        [$c1, $c2] = ExamQuestion::where('exam_id', $copyId)->orderBy('order')->pluck('id')->all();
        $this->assertEquals([$c1 => 'true', $c2 => 'false'], json_decode($dup[0]->answers, true));
        $this->assertSame('termine', $dup[0]->status);
        $this->assertEquals(12, $dup[0]->note_sur_vingt);

        $this->assertExamLocked($both, $copyId);
        $this->assertExamLocked($both, $exam->id);
    }

    public function test_exam_learner_in_two_copy_groups_cannot_retake_on_either_copy(): void
    {
        foreach (range(1, 3) as $i) {
            $this->learner($this->groupA); // A garde l'original
        }
        $twice = $this->learner($this->groupB, $this->groupC);
        $exam = $this->exam([$this->groupA, $this->groupB, $this->groupC]);
        $attemptId = $this->examAttempt($exam, $twice);

        $result = $this->migrator()->apply();

        $copyB = (int) DB::table('group_exam')->where('group_id', $this->groupB->id)->value('exam_id');
        $copyC = (int) DB::table('group_exam')->where('group_id', $this->groupC->id)->value('exam_id');
        $this->assertSame(2, $result['copied']);
        $this->assertSame(1, $result['duplicated_attempts']);
        // Déplacée vers la copie du premier groupe, dupliquée sur l'autre.
        $this->assertSame($copyB, (int) DB::table('exam_attempts')->where('id', $attemptId)->value('exam_id'));
        $this->assertSame(1, DB::table('exam_attempts')->where('exam_id', $copyC)->where('learner_id', $twice->id)->count());
        [$c1, $c2] = ExamQuestion::where('exam_id', $copyC)->orderBy('order')->pluck('id')->all();
        $this->assertEquals([$c1 => 'true', $c2 => 'false'],
            json_decode(DB::table('exam_attempts')->where('exam_id', $copyC)->value('answers'), true));

        $this->assertExamLocked($twice, $copyB);
        $this->assertExamLocked($twice, $copyC);
    }

    public function test_quiz_attempts_are_not_duplicated_for_learners_seeing_several_exemplars(): void
    {
        $this->learner($this->groupA);
        $both = $this->learner($this->groupA, $this->groupB);
        $quiz = $this->quiz([$this->groupA, $this->groupB]);
        DB::table('quiz_attempts')->insert([
            'learner_id' => $both->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->migrator()->apply()['duplicated_attempts']);
        $this->assertSame(1, DB::table('quiz_attempts')->count());
    }

    public function test_simulate_counts_learners_seeing_several_exemplars(): void
    {
        $this->learner($this->groupA);
        $this->learner($this->groupA);
        $this->learner($this->groupB);
        $this->learner($this->groupA, $this->groupB);
        $this->learner($this->groupB, $this->groupC);
        $this->quiz([$this->groupA, $this->groupB]);
        $this->exam([$this->groupB, $this->groupC]);

        $this->assertSame(2, $this->migrator()->multiExemplarLearners());
        $this->postJson(route('cores.settings.content-sharing.simulate'))
            ->assertOk()
            ->assertJsonPath('multi_exemplar_learners', 2)
            ->assertJsonPath('multi_exemplar_message', '2 apprenants verront plusieurs exemplaires');
    }

    public function test_quiz_answers_question_ids_remapped(): void
    {
        $this->learner($this->groupA);
        $this->learner($this->groupA);
        $learner = $this->learner($this->groupB);
        $quiz = $this->quiz([$this->groupA, $this->groupB]);
        [$q1, $q2] = $quiz->questions()->orderBy('order')->pluck('id')->all();

        $attemptId = DB::table('quiz_attempts')->insertGetId([
            'learner_id' => $learner->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'completed_at' => now(),
            'status' => 'completed', 'score' => 50, 'answers' => json_encode([$q1 => 'true', $q2 => 'false']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$q1, $q2] as $qid) {
            DB::table('quiz_answers')->insert([
                'attempt_id' => $attemptId, 'question_id' => $qid, 'answer_given' => 'true', 'is_correct' => true,
                'points_earned' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('learner_progress')->insert([
            'learner_id' => $learner->id, 'content_type' => 'quiz', 'content_id' => $quiz->id, 'status' => 'completed',
            'progress_percentage' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('flashcards')->insert([
            'learner_id' => $learner->id, 'question_id' => $q1, 'next_review_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('push_assignment_snapshots')->insert([
            ['group_id' => $this->groupA->id, 'content_type' => 'quiz', 'content_id' => $quiz->id],
            ['group_id' => $this->groupB->id, 'content_type' => 'quiz', 'content_id' => $quiz->id],
        ]);

        $result = $this->migrator()->apply();

        $copyId = (int) DB::table('group_quiz')->where('group_id', $this->groupB->id)->value('quiz_id');
        [$c1, $c2] = Question::where('quiz_id', $copyId)->orderBy('order')->pluck('id')->all();

        $attempt = DB::table('quiz_attempts')->find($attemptId);
        $this->assertSame($copyId, (int) $attempt->quiz_id);
        $this->assertEquals([$c1 => 'true', $c2 => 'false'], json_decode($attempt->answers, true));
        $this->assertEqualsCanonicalizing([$c1, $c2],
            DB::table('quiz_answers')->where('attempt_id', $attemptId)->pluck('question_id')->map(fn ($v) => (int) $v)->all());
        $this->assertDatabaseHas('learner_progress', ['learner_id' => $learner->id, 'content_type' => 'quiz', 'content_id' => $copyId]);
        $this->assertDatabaseHas('flashcards', ['learner_id' => $learner->id, 'question_id' => $c1]);
        // Instantané : seul le groupe B suit la copie (pas de « nouveau contenu » parasite).
        $this->assertDatabaseHas('push_assignment_snapshots', ['group_id' => $this->groupB->id, 'content_type' => 'quiz', 'content_id' => $copyId]);
        $this->assertDatabaseHas('push_assignment_snapshots', ['group_id' => $this->groupA->id, 'content_type' => 'quiz', 'content_id' => $quiz->id]);
        $this->assertDatabaseMissing('push_assignment_snapshots', ['group_id' => $this->groupB->id, 'content_id' => $quiz->id]);
        // 1 tentative + 2 réponses + 1 progression + 1 carte legacy + 1 instantané.
        $this->assertSame(6, $result['moved_rows']);
    }

    public function test_deck_reviews_remapped(): void
    {
        $this->learner($this->groupA);
        $this->learner($this->groupA);
        $learner = $this->learner($this->groupB);
        $deck = $this->deck([$this->groupA, $this->groupB]);
        $item = FlashcardItem::create(['deck_id' => $deck->id, 'recto' => 'R', 'verso' => 'V', 'ordre' => 1]);

        $reviewId = DB::table('flashcard_item_reviews')->insertGetId([
            'flashcard_item_id' => $item->id, 'learner_id' => $learner->id, 'repetitions' => 3, 'status' => 'review',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sessionId = DB::table('flashcard_sessions')->insertGetId([
            'learner_id' => $learner->id, 'deck_id' => $deck->id, 'date_debut' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migrator()->apply();

        $copyId = (int) DB::table('group_flashcard_deck')->where('group_id', $this->groupB->id)->value('flashcard_deck_id');
        $copyItem = (int) FlashcardItem::where('deck_id', $copyId)->value('id');
        $this->assertNotSame($deck->id, $copyId);
        $this->assertSame($copyItem, (int) DB::table('flashcard_item_reviews')->where('id', $reviewId)->value('flashcard_item_id'));
        $this->assertSame(3, (int) DB::table('flashcard_item_reviews')->where('id', $reviewId)->value('repetitions'));
        $this->assertSame($copyId, (int) DB::table('flashcard_sessions')->where('id', $sessionId)->value('deck_id'));
    }

    public function test_learners_of_kept_group_untouched(): void
    {
        $kept = $this->learner($this->groupA);
        $both = $this->learner($this->groupA, $this->groupB); // garde l'accès à l'original via A
        $quiz = $this->quiz([$this->groupA, $this->groupB]);
        $q1 = (int) $quiz->questions()->orderBy('order')->value('id');

        $ids = [];
        foreach ([$kept, $both] as $l) {
            $ids[] = $id = DB::table('quiz_attempts')->insertGetId([
                'learner_id' => $l->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'status' => 'completed',
                'answers' => json_encode([$q1 => 'true']), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('quiz_answers')->insert(['attempt_id' => $id, 'question_id' => $q1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('learner_progress')->insert([
                'learner_id' => $l->id, 'content_type' => 'quiz', 'content_id' => $quiz->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $result = $this->migrator()->apply();

        $this->assertSame(1, $result['copied']);
        $this->assertSame(0, $result['moved_rows']);
        foreach ($ids as $id) {
            $this->assertSame($quiz->id, (int) DB::table('quiz_attempts')->where('id', $id)->value('quiz_id'));
            $this->assertSame($q1, (int) DB::table('quiz_answers')->where('attempt_id', $id)->value('question_id'));
            $this->assertEquals([$q1 => 'true'], json_decode(DB::table('quiz_attempts')->where('id', $id)->value('answers'), true));
        }
        $this->assertSame(2, DB::table('learner_progress')->where('content_id', $quiz->id)->count());
    }

    public function test_mode_switch_blocked_with_conflicts(): void
    {
        $this->learner($this->groupA);
        $this->quiz([$this->groupA, $this->groupB]);

        $this->get(route('cores.settings.content-sharing'))->assertOk()->assertSee('Quiz partagé');

        $this->putJson(route('cores.settings.content-sharing.mode'), ['mode' => 'isolated'])->assertStatus(422);
        $this->assertSame('shared', ContentSharing::mode());
        $this->putJson(route('cores.settings.content-sharing.mode'), ['mode' => 'shared'])->assertOk();

        $this->postJson(route('cores.settings.content-sharing.apply'))
            ->assertOk()->assertJsonPath('copied', 1);

        $this->putJson(route('cores.settings.content-sharing.mode'), ['mode' => 'isolated'])->assertOk();
        $this->assertSame('isolated', ContentSharing::mode());
        $this->putJson(route('cores.settings.content-sharing.mode'), ['mode' => 'shared'])->assertOk();
        $this->assertSame('shared', ContentSharing::mode());
        $this->putJson(route('cores.settings.content-sharing.mode'), ['mode' => 'autre'])->assertStatus(422);

        // Réservé aux administrateurs.
        Role::findOrCreate('trainer');
        $trainer = $this->makeUser('formateur');
        $trainer->assignRole('trainer');
        $this->actingAs($trainer)->get(route('cores.settings.content-sharing'))->assertForbidden();
        $this->actingAs($trainer)->postJson(route('cores.settings.content-sharing.apply'))->assertForbidden();
        $this->actingAs($trainer)->putJson(route('cores.settings.content-sharing.mode'), ['mode' => 'shared'])->assertForbidden();
    }

    public function test_simulate_writes_nothing(): void
    {
        $this->learner($this->groupA);
        $learner = $this->learner($this->groupB);
        $quiz = $this->quiz([$this->groupA, $this->groupB]);
        $attemptId = DB::table('quiz_attempts')->insertGetId([
            'learner_id' => $learner->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tables = ['quizzes', 'questions', 'group_quiz', 'quiz_attempts', 'quiz_answers', 'learner_progress', 'app_settings'];
        $counts = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
        $before = $counts();

        $this->postJson(route('cores.settings.content-sharing.simulate'))
            ->assertOk()
            ->assertJsonPath('plan.0.type', 'quiz')
            ->assertJsonPath('plan.0.id', $quiz->id)
            ->assertJsonPath('plan.0.keep_group_id', $this->groupA->id)
            ->assertJsonPath('plan.0.copies.0.group_id', $this->groupB->id)
            ->assertJsonPath('plan.0.copies.0.learners', 1);

        $this->assertSame($before, $counts());
        $this->assertSame($quiz->id, (int) DB::table('quiz_attempts')->where('id', $attemptId)->value('quiz_id'));
        $this->assertSame(2, DB::table('group_quiz')->where('quiz_id', $quiz->id)->count());
    }
}
