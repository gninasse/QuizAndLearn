<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Modules\Core\Services\Kpi\ActivityLog;
use Modules\Core\Services\Kpi\KpiScope;
use Modules\Core\Services\Kpi\KpiService;
use Tests\TestCase;

class KpiEngagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Learner $l1;

    private Learner $l2;

    private Learner $l3;

    private Quiz $quiz;

    private Exam $exam;

    private FlashcardItem $item1;

    private FlashcardItem $item2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-18 12:00');

        $this->admin = $this->makeUser('admin');
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');

        $this->l1 = $this->makeLearner('awa', 'M1');
        $this->l2 = $this->makeLearner('bob', 'M2');
        $this->l3 = $this->makeLearner('cyl', 'M3');

        $this->quiz = Quiz::create(['title' => 'Q', 'is_active' => true, 'created_by' => $this->admin->id]);
        $this->exam = Exam::create([
            'title' => 'E', 'duration' => 10, 'passing_score' => 50, 'is_active' => true,
            'max_attempts' => 1, 'note_max' => 20, 'created_by' => $this->admin->id,
        ]);
        $deck = FlashcardDeck::create([
            'titre' => 'D', 'algorithme' => 'sm2', 'is_public' => false, 'active' => true,
            'created_by' => $this->admin->id,
        ]);
        $this->item1 = FlashcardItem::create(['deck_id' => $deck->id, 'recto' => 'a', 'verso' => 'b', 'ordre' => 1]);
        $this->item2 = FlashcardItem::create(['deck_id' => $deck->id, 'recto' => 'c', 'verso' => 'd', 'ordre' => 2]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => true, 'password' => bcrypt('password'),
        ]);
    }

    private function makeLearner(string $handle, string $matricule): Learner
    {
        return Learner::create(['user_id' => $this->makeUser($handle)->id, 'matricule' => $matricule]);
    }

    private function createdAt(Learner $l, string $date): void
    {
        DB::table('learners')->where('id', $l->id)->update(['created_at' => $date]);
    }

    private function quizAt(Learner $l, string $at): void
    {
        DB::table('quiz_attempts')->insert([
            'learner_id' => $l->id, 'quiz_id' => $this->quiz->id, 'started_at' => $at,
            'completed_at' => $at, 'status' => 'completed', 'score' => 80,
        ]);
    }

    private function examAt(Learner $l, string $at): void
    {
        DB::table('exam_attempts')->insert([
            'learner_id' => $l->id, 'exam_id' => $this->exam->id, 'date_debut' => $at, 'status' => 'termine',
        ]);
    }

    private function reviewAt(Learner $l, FlashcardItem $item, ?string $last, ?string $next): void
    {
        DB::table('flashcard_item_reviews')->insert([
            'learner_id' => $l->id, 'flashcard_item_id' => $item->id,
            'last_reviewed' => $last, 'next_review' => $next, 'status' => 'review',
        ]);
    }

    private function progressAt(Learner $l, string $at, int $contentId = 1): void
    {
        DB::table('learner_progress')->insert([
            'learner_id' => $l->id, 'content_type' => 'article', 'content_id' => $contentId,
            'status' => 'in_progress', 'last_accessed_at' => $at,
        ]);
    }

    private function scope(?int $groupId = null): KpiScope
    {
        return KpiScope::for($this->admin, '30', groupId: $groupId);
    }

    public function test_active_counts_distinct_learners_and_trend(): void
    {
        // l1 : quiz + revue le même jour => 1 seul apprenant
        $this->quizAt($this->l1, '2026-10-17 09:00:00');
        $this->reviewAt($this->l1, $this->item1, '2026-10-17 10:00:00', '2026-10-25 00:00:00');
        // l1 encore actif dans la fenêtre 30j précédente
        $this->progressAt($this->l1, '2026-09-01 10:00:00');
        // l2 : J-10 => dans 30d, pas dans 7d ; dans la fenêtre précédente 7d
        $this->progressAt($this->l2, '2026-10-08 10:00:00', 2);
        // l3 : J-8 (10 oct) => précédente fenêtre 7d
        $this->examAt($this->l3, '2026-10-10 10:00:00');

        $e = (new KpiService)->engagement($this->scope());

        $this->assertSame(['value' => 1, 'trend' => 2], $e['active_7d']);
        $this->assertSame(['value' => 3, 'trend' => 1], $e['active_30d']);
    }

    public function test_learner_in_two_groups_counted_once(): void
    {
        $a = Group::create(['name' => 'A', 'is_active' => true]);
        $b = Group::create(['name' => 'B', 'is_active' => true]);
        $a->learners()->attach($this->l1->id);
        $b->learners()->attach($this->l1->id);
        $trainerUser = $this->makeUser('prof');
        Role::findOrCreate('trainer');
        $trainerUser->assignRole('trainer');
        $trainer = Trainer::create(['user_id' => $trainerUser->id]);
        $a->trainers()->attach($trainer->id);
        $b->trainers()->attach($trainer->id);

        $this->quizAt($this->l1, '2026-10-17 09:00:00');
        $this->progressAt($this->l1, '2026-10-16 09:00:00');

        $e = (new KpiService)->engagement(KpiScope::for($trainerUser->fresh()));

        $this->assertSame(1, $e['active_7d']['value']);
        $this->assertSame(1, $e['active_30d']['value']);
    }

    public function test_activation_rate(): void
    {
        $this->createdAt($this->l1, '2026-10-01 08:00:00');
        $this->createdAt($this->l2, '2026-10-01 08:00:00');
        $this->createdAt($this->l3, '2026-08-01 08:00:00'); // hors période
        $this->quizAt($this->l1, '2026-10-05 09:00:00');   // J+4 => activé
        $this->quizAt($this->l2, '2026-10-15 09:00:00');   // J+14 => non activé
        $this->quizAt($this->l3, '2026-10-02 09:00:00');   // hors cohorte

        $this->assertSame(50.0, (new KpiService)->engagement($this->scope())['activation_rate']);
    }

    public function test_retention_30d(): void
    {
        $this->quizAt($this->l1, '2026-08-01 09:00:00');
        $this->quizAt($this->l1, '2026-09-05 09:00:00'); // >= 30j après la première
        $this->quizAt($this->l2, '2026-08-10 09:00:00');
        $this->quizAt($this->l2, '2026-08-20 09:00:00'); // < 30j après
        $this->quizAt($this->l3, '2026-10-10 09:00:00'); // première activité trop récente

        $this->assertSame(50.0, (new KpiService)->engagement($this->scope())['retention_30d']);
    }

    public function test_retention_30d_ignores_activity_after_period_end(): void
    {
        // période personnalisée close le 30 sept. : la reprise d'octobre ne compte pas
        $this->quizAt($this->l1, '2026-08-01 09:00:00');
        $this->quizAt($this->l1, '2026-09-15 09:00:00'); // >= 30j, avant `to` => retenu
        $this->quizAt($this->l2, '2026-08-05 09:00:00');
        $this->quizAt($this->l2, '2026-08-20 09:00:00'); // < 30j
        $this->quizAt($this->l2, '2026-10-10 09:00:00'); // >= 30j mais après `to`

        $scope = KpiScope::for($this->admin, 'custom', '2026-09-01', '2026-09-30');

        $this->assertSame(50.0, (new KpiService)->engagement($scope)['retention_30d']);
    }

    public function test_weekly_active_week_boundaries(): void
    {
        $this->quizAt($this->l1, '2026-10-11 23:59:59'); // dimanche => semaine précédente
        $this->quizAt($this->l2, '2026-10-12 00:00:00'); // lundi => semaine courante

        $weeks = (new KpiService)->weeklyActive($this->scope());

        $this->assertSame(1, $weeks[10]['active']);
        $this->assertSame(1, $weeks[11]['active']);
    }

    public function test_review_regularity(): void
    {
        // révisée, pas en retard
        $this->reviewAt($this->l1, $this->item1, '2026-10-10 10:00:00', '2026-10-20 00:00:00');
        // jamais révisée, échue => en retard
        $this->reviewAt($this->l1, $this->item2, null, '2026-10-01 00:00:00');
        // révisée et encore en retard (révisée avant l'échéance déjà passée)
        $this->reviewAt($this->l2, $this->item1, '2026-10-12 10:00:00', '2026-10-14 00:00:00');
        // révisée après son échéance => pas en retard
        $this->reviewAt($this->l2, $this->item2, '2026-10-16 10:00:00', '2026-10-12 00:00:00');

        // reviewed = 3, overdue = 2
        $this->assertSame(60.0, (new KpiService)->engagement($this->scope())['review_regularity']);
    }

    public function test_empty_database_returns_zeros_and_nulls(): void
    {
        DB::table('flashcard_items')->delete();
        DB::table('learners')->delete();

        $svc = new KpiService;
        $e = $svc->engagement($this->scope());

        $this->assertSame(['value' => 0, 'trend' => 0], $e['active_7d']);
        $this->assertSame(['value' => 0, 'trend' => 0], $e['active_30d']);
        $this->assertNull($e['activation_rate']);
        $this->assertNull($e['retention_30d']);
        $this->assertNull($e['review_regularity']);
        $this->assertCount(12, $svc->weeklyActive($this->scope()));
        $this->assertSame([], ActivityLog::lastActivity($this->scope()));
    }

    public function test_weekly_active_has_12_weeks_ending_this_week(): void
    {
        $this->quizAt($this->l1, '2026-10-12 08:00:00'); // lundi de la semaine courante
        $this->progressAt($this->l1, '2026-10-14 08:00:00');
        $this->quizAt($this->l2, '2026-10-14 09:00:00');
        $this->quizAt($this->l1, '2026-10-06 09:00:00'); // semaine précédente
        $this->quizAt($this->l3, '2026-07-28 09:00:00'); // première semaine

        $weeks = (new KpiService)->weeklyActive($this->scope());

        $this->assertCount(12, $weeks);
        $this->assertSame('2026-07-27', $weeks[0]['week_start']);
        $this->assertSame('2026-10-12', $weeks[11]['week_start']);
        $this->assertSame(1, $weeks[0]['active']);
        $this->assertSame(1, $weeks[10]['active']);
        $this->assertSame(2, $weeks[11]['active']);
    }

    public function test_activity_log_first_and_last(): void
    {
        $this->quizAt($this->l1, '2026-08-01 09:00:00');
        $this->progressAt($this->l1, '2026-10-16 09:00:00');

        $last = ActivityLog::lastActivity($this->scope());
        $first = ActivityLog::firstActivity($this->scope());

        $this->assertSame('2026-10-16 09:00:00', $last[$this->l1->id]->format('Y-m-d H:i:s'));
        $this->assertNull($last[$this->l2->id]);
        $this->assertSame('2026-08-01 09:00:00', $first[$this->l1->id]->format('Y-m-d H:i:s'));
        $this->assertArrayNotHasKey($this->l2->id, $first);
    }
}
