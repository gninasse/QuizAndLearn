<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Modules\Core\Services\Kpi\KpiScope;
use Modules\Core\Services\Kpi\KpiService;
use Tests\TestCase;

class KpiLearningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Learner $l1;

    private Learner $l2;

    private Learner $l3;

    private Quiz $quiz;

    private Exam $exam;

    private Group $groupA;

    private Group $groupB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-18 12:00');
        Cache::flush();

        $this->admin = $this->makeUser('admin');
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');

        $this->l1 = $this->makeLearner('awa', 'M1');
        $this->l2 = $this->makeLearner('bob', 'M2');
        $this->l3 = $this->makeLearner('cyl', 'M3');

        $this->groupA = Group::create(['name' => 'A', 'is_active' => true]);
        $this->groupB = Group::create(['name' => 'B', 'is_active' => true]);

        $this->quiz = Quiz::create(['title' => 'Quiz 1', 'is_active' => true, 'created_by' => $this->admin->id]);
        $this->exam = Exam::create([
            'title' => 'E', 'duration' => 10, 'passing_score' => 50, 'is_active' => true,
            'max_attempts' => 1, 'note_max' => 20, 'created_by' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function makeUser(string $handle, bool $active = true): User
    {
        return User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => $active, 'password' => bcrypt('password'),
        ]);
    }

    private function makeLearner(string $handle, string $matricule): Learner
    {
        return Learner::create(['user_id' => $this->makeUser($handle)->id, 'matricule' => $matricule]);
    }

    private function scope(?int $groupId = null): KpiScope
    {
        return KpiScope::for($this->admin, '30', groupId: $groupId);
    }

    private function attempt(Learner $l, ?Quiz $quiz = null, string $status = 'completed', ?int $score = 80, string $at = '2026-10-10 10:00:00'): int
    {
        return DB::table('quiz_attempts')->insertGetId([
            'learner_id' => $l->id, 'quiz_id' => ($quiz ?? $this->quiz)->id, 'started_at' => $at,
            'completed_at' => $at, 'status' => $status, 'score' => $score,
        ]);
    }

    private function progress(Learner $l, string $type, int $id, string $status, ?string $at = null): void
    {
        DB::table('learner_progress')->insert([
            'learner_id' => $l->id, 'content_type' => $type, 'content_id' => $id,
            'status' => $status, 'last_accessed_at' => $at,
        ]);
    }

    private function question(string $text, ?Quiz $quiz = null): int
    {
        return DB::table('questions')->insertGetId([
            'quiz_id' => ($quiz ?? $this->quiz)->id, 'question_text' => $text, 'type' => 'single_choice',
        ]);
    }

    public function test_quiz_and_exam_averages(): void
    {
        $this->attempt($this->l1, score: 80);
        $this->attempt($this->l2, score: 71);
        $this->attempt($this->l3, status: 'in_progress', score: 0);          // ignoré
        $this->attempt($this->l3, score: 10, at: '2026-07-01 10:00:00');     // hors période

        foreach ([['termine', 12.5, '2026-10-10 10:00:00'], ['time_up', 10.0, '2026-10-11 10:00:00'],
            ['annule', 2.0, '2026-10-12 10:00:00'], ['termine', 1.0, '2026-07-01 10:00:00']] as [$st, $note, $at]) {
            DB::table('exam_attempts')->insert([
                'learner_id' => $this->l1->id, 'exam_id' => $this->exam->id, 'date_debut' => $at,
                'status' => $st, 'note_sur_vingt' => $note,
            ]);
        }

        $l = (new KpiService)->learning($this->scope());

        $this->assertSame(75.5, $l['quiz_avg_score']);
        $this->assertSame(11.3, $l['exam_avg_note']);
    }

    public function test_empty_database_is_valid(): void
    {
        $l = (new KpiService)->learning($this->scope());
        $f = (new KpiService)->followUp($this->scope());

        $this->assertNull($l['quiz_avg_score']);
        $this->assertNull($l['exam_avg_note']);
        $this->assertSame([], $l['completion']);
        $this->assertSame([], $l['failed_questions']);
        $this->assertSame(['screenshots' => 0, 'exam_captures' => 0, 'exam_navigation' => 0, 'exams_cancelled' => 0], $f['incidents']);
    }

    public function test_completion_rates_per_assigned_content(): void
    {
        $article = DB::table('articles')->insertGetId(['title' => 'Art', 'is_active' => true]);
        $deck = FlashcardDeck::create(['titre' => 'Deck', 'algorithme' => 'sm2', 'is_public' => false, 'active' => true, 'created_by' => $this->admin->id]);
        $item = FlashcardItem::create(['deck_id' => $deck->id, 'recto' => 'a', 'verso' => 'b', 'ordre' => 1]);

        $this->groupA->learners()->attach([$this->l1->id, $this->l2->id]);
        $this->groupB->learners()->attach([$this->l2->id, $this->l3->id]); // l2 dans 2 groupes
        foreach ([$this->groupA, $this->groupB] as $g) {
            DB::table('group_quiz')->insert(['group_id' => $g->id, 'quiz_id' => $this->quiz->id]);
        }
        DB::table('group_article')->insert(['group_id' => $this->groupA->id, 'article_id' => $article]);
        DB::table('group_flashcard_deck')->insert(['group_id' => $this->groupB->id, 'flashcard_deck_id' => $deck->id]);

        $this->progress($this->l1, 'quiz', $this->quiz->id, 'completed');
        $this->progress($this->l2, 'quiz', $this->quiz->id, 'completed');
        $this->progress($this->l2, 'article', $article, 'in_progress');
        $this->progress($this->l1, 'article', $article, 'completed');
        DB::table('flashcard_item_reviews')->insert([
            'learner_id' => $this->l3->id, 'flashcard_item_id' => $item->id,
            'last_reviewed' => '2026-10-01 10:00:00', 'next_review' => '2026-10-20 00:00:00', 'status' => 'review',
        ]);

        $rows = collect((new KpiService)->learning($this->scope())['completion'])->keyBy('type');

        // quiz : assigné aux 3 apprenants (l2 compté une fois), 2 terminés
        $this->assertSame(['type' => 'quiz', 'id' => $this->quiz->id, 'title' => 'Quiz 1', 'assigned' => 3, 'completed' => 2, 'rate' => 66.7], $rows['quiz']);
        $this->assertSame(['type' => 'article', 'id' => $article, 'title' => 'Art', 'assigned' => 2, 'completed' => 1, 'rate' => 50.0], $rows['article']);
        $this->assertSame(['type' => 'deck', 'id' => $deck->id, 'title' => 'Deck', 'assigned' => 2, 'completed' => 1, 'rate' => 50.0], $rows['deck']);

        $rates = array_column((new KpiService)->learning($this->scope())['completion'], 'rate');
        $sorted = $rates;
        sort($sorted);
        $this->assertSame($sorted, $rates);

        // quiz 2/4 = 50.0 avec un groupe filtré
        $this->groupA->learners()->attach($this->l3->id);
        $this->progress($this->l3, 'quiz', $this->quiz->id, 'in_progress');
        $only = collect((new KpiService)->learning($this->scope($this->groupA->id))['completion'])->keyBy('type');
        $this->assertSame(3, $only['quiz']['assigned']);
        $this->assertArrayNotHasKey('deck', $only->all());
    }

    public function test_completion_handles_missing_title(): void
    {
        $this->groupA->learners()->attach($this->l1->id);
        // contenus assignés mais introuvables (pas de FK en SQLite de test, ou titre vide)
        $article = DB::table('articles')->insertGetId(['title' => '', 'is_active' => true]);
        DB::table('group_article')->insert(['group_id' => $this->groupA->id, 'article_id' => $article]);

        $rows = (new KpiService)->learning($this->scope())['completion'];

        $this->assertCount(1, $rows);
        $this->assertSame('—', $rows[0]['title']);
        $this->assertSame(0.0, $rows[0]['rate']);
    }

    public function test_failed_questions_requires_5_answers_and_sorts_by_fail_rate(): void
    {
        $q1 = $this->question('<p>Facile ?</p>');
        $q2 = $this->question('Difficile ?');
        $q3 = $this->question('Peu de réponses ?');

        $mk = fn (int $q, int $att, bool $ok) => DB::table('quiz_answers')->insert([
            'attempt_id' => $att, 'question_id' => $q, 'is_correct' => $ok,
        ]);

        $learners = [$this->l1, $this->l2, $this->l3];
        for ($i = 0; $i < 6; $i++) {
            $att = $this->attempt($learners[$i % 3]);
            $mk($q1, $att, $i < 4);      // 2/6 échecs
            $mk($q2, $att, $i < 1);      // 5/6 échecs
            if ($i < 4) {
                $mk($q3, $att, false);   // 4 réponses seulement
            }
        }
        // réponse hors période : ignorée
        $old = $this->attempt($this->l1, at: '2026-06-01 10:00:00');
        $mk($q1, $old, false);

        $f = (new KpiService)->learning($this->scope())['failed_questions'];

        $this->assertCount(2, $f);
        $this->assertSame($q2, $f[0]['question_id']);
        $this->assertSame(['question_id' => $q2, 'quiz_title' => 'Quiz 1', 'label' => 'Difficile ?', 'answers' => 6, 'fail_rate' => 83.3], $f[0]);
        $this->assertSame('Facile ?', $f[1]['label']);
        $this->assertSame(33.3, $f[1]['fail_rate']);
    }

    public function test_inactive_lists_never_active_first_and_excludes_suspended(): void
    {
        $l4 = $this->makeLearner('dan', 'M4');
        $l5 = $this->makeLearner('eve', 'M5');
        $l6 = $this->makeLearner('fay', 'M6');
        $l7 = $this->makeLearner('gil', 'M7');
        $this->groupA->learners()->attach([$this->l1->id, $this->l2->id]);
        $this->groupB->learners()->attach([$this->l2->id]);

        $this->progress($this->l1, 'article', 1, 'in_progress', '2026-10-17 09:00:00'); // actif
        $this->progress($this->l2, 'article', 1, 'in_progress', '2026-09-20 09:00:00'); // inactif, ancien
        // l3 jamais actif ; l4 suspendu ; l5 suspension expirée ; l6 compte désactivé ; l7 suspendu sans fin
        DB::table('learners')->where('id', $l4->id)->update(['suspended_at' => '2026-10-01 00:00:00', 'suspended_until' => '2026-11-01 00:00:00']);
        DB::table('learners')->where('id', $l5->id)->update(['suspended_at' => '2026-09-01 00:00:00', 'suspended_until' => '2026-09-15 00:00:00']);
        DB::table('users')->where('id', $l6->user_id)->update(['is_active' => false]);
        DB::table('learners')->where('id', $l7->id)->update(['suspended_at' => '2026-10-01 00:00:00']);

        $inactive = (new KpiService)->followUp($this->scope())['inactive'];

        $this->assertSame([$this->l3->id, $l5->id, $this->l2->id], array_column($inactive, 'learner_id'));
        $this->assertNull($inactive[0]['last_activity']);
        $this->assertNull($inactive[0]['group_id']);
        $this->assertSame('Cyl Test', $inactive[0]['name']);
        $this->assertSame('M3', $inactive[0]['matricule']);
        $this->assertNotNull($inactive[2]['last_activity']);
        // l2 appartient à deux groupes : une seule ligne, premier groupe
        $this->assertSame($this->groupA->id, $inactive[2]['group_id']);

        // avec filtre groupe B : group_id est dans le périmètre
        $b = (new KpiService)->followUp($this->scope($this->groupB->id))['inactive'];
        $this->assertSame([$this->l2->id], array_column($b, 'learner_id'));
        $this->assertSame($this->groupB->id, $b[0]['group_id']);
    }

    public function test_incidents_sums(): void
    {
        $att = $this->attempt($this->l1);
        DB::table('screenshot_attempts')->insert([
            ['learner_id' => $this->l1->id, 'attempt_id' => $att, 'detected_at' => '2026-10-10 10:00:00'],
            ['learner_id' => $this->l2->id, 'attempt_id' => $att, 'detected_at' => '2026-10-11 10:00:00'],
            ['learner_id' => $this->l2->id, 'attempt_id' => $att, 'detected_at' => '2026-06-11 10:00:00'],
        ]);
        foreach ([['termine', 2, 1], ['annule', 3, 4], ['annule', 0, 0]] as [$st, $cap, $nav]) {
            DB::table('exam_attempts')->insert([
                'learner_id' => $this->l1->id, 'exam_id' => $this->exam->id, 'date_debut' => '2026-10-12 10:00:00',
                'status' => $st, 'capture_attempts' => $cap, 'navigation_violations' => $nav,
            ]);
        }

        $i = (new KpiService)->followUp($this->scope())['incidents'];

        $this->assertSame(['screenshots' => 2, 'exam_captures' => 5, 'exam_navigation' => 5, 'exams_cancelled' => 2], $i);
    }

    public function test_showcase_null_when_disabled_or_trainer(): void
    {
        $mk = function (string $status, string $at) {
            $r = AccessRequest::create([
                'first_name' => 'A', 'last_name' => 'B', 'phone' => '0102030405',
                'consent_at' => now(), 'consent_text' => 'ok', 'status' => $status,
            ]);
            DB::table('access_requests')->where('id', $r->id)->update(['created_at' => $at]);
        };
        $mk('account_created', '2026-10-10 10:00:00');
        $mk('new', '2026-10-11 10:00:00');
        $mk('new', '2026-10-12 10:00:00');
        $mk('account_created', '2026-06-12 10:00:00');
        $mk('new', '2026-10-13 10:00:00');

        $this->assertNull((new KpiService)->followUp($this->scope())['showcase']);

        AppSetting::put('showcase.enabled', true);
        Cache::flush();
        $this->assertSame(['requests' => 4, 'converted' => 1, 'rate' => 25.0], (new KpiService)->followUp($this->scope())['showcase']);

        $user = $this->makeUser('prof');
        Role::findOrCreate('trainer');
        $user->assignRole('trainer');
        $t = Trainer::create(['user_id' => $user->id]);
        $this->groupA->trainers()->attach($t->id);
        $this->assertNull((new KpiService)->followUp(KpiScope::for($user->fresh()))['showcase']);
    }

    public function test_all_is_cached_10_minutes(): void
    {
        $this->attempt($this->l1, score: 80);
        $svc = new KpiService;

        $first = $svc->all($this->scope());
        $this->assertSame(80.0, $first['quiz_avg_score']);
        foreach (['active_7d', 'activation_rate', 'quiz_avg_score', 'completion', 'inactive', 'incidents', 'showcase', 'weekly_active', 'generated_at'] as $k) {
            $this->assertArrayHasKey($k, $first);
        }
        $this->assertCount(12, $first['weekly_active']);
        $this->assertSame($first['generated_at'], \Carbon\Carbon::parse($first['generated_at'])->toIso8601String());

        $this->attempt($this->l2, score: 20);
        $this->assertSame($first, $svc->all($this->scope()));
        $this->assertTrue(Cache::has($this->scope()->cacheKey));

        $this->travel(11)->minutes();
        $this->assertSame(50.0, $svc->all($this->scope())['quiz_avg_score']);
    }
}
