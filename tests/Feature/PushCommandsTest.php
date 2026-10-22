<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\FlashcardItemReview;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerXp;
use Modules\Core\Models\PushDelivery;
use Modules\Core\Models\PushSubscription;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Push\PushSender;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class PushCommandsTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $sender;

    private Learner $learner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'push.timezone' => 'UTC',
            'push.vapid.public_key' => 'pub',
            'push.vapid.private_key' => 'priv',
            'push.vapid.subject' => 'mailto:test@example.com',
        ]);

        $this->sender = new FakePushSender;
        $this->app->instance(PushSender::class, $this->sender);

        Role::findOrCreate('learner');
        $this->group = Group::create(['name' => 'Promo A']);
        $this->learner = $this->makeLearner('awa');
        $this->group->learners()->attach($this->learner->id);

        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'UTC'));
    }

    private function makeLearner(string $name, bool $active = true): Learner
    {
        $user = User::create([
            'name' => $name, 'last_name' => 'Test', 'user_name' => $name,
            'email' => "{$name}@example.com", 'is_active' => $active, 'password' => bcrypt('password'),
        ]);
        $user->assignRole('learner');
        $learner = Learner::create(['user_id' => $user->id, 'matricule' => 'MAT-'.strtoupper($name)]);
        $endpoint = "https://push.example/{$name}";
        PushSubscription::create([
            'learner_id' => $learner->id, 'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'BPkey', 'auth' => 'authkey',
        ]);

        return $learner;
    }

    private function quiz(string $title, array $attrs = []): Quiz
    {
        $quiz = Quiz::create(array_merge(['title' => $title, 'duration' => 10, 'passing_score' => 50, 'is_active' => true], $attrs));
        DB::table('group_quiz')->insert(['group_id' => $this->group->id, 'quiz_id' => $quiz->id]);

        return $quiz;
    }

    private function article(string $title, array $attrs = []): Article
    {
        $article = Article::create(array_merge(['title' => $title, 'content' => 'x', 'is_active' => true], $attrs));
        DB::table('group_article')->insert(['group_id' => $this->group->id, 'article_id' => $article->id]);

        return $article;
    }

    private function run15(string $command, ?string $time = null): void
    {
        if ($time) {
            $this->travelTo(Carbon::parse("2026-10-20 {$time}", 'UTC'));
        }
        $this->artisan($command)->assertSuccessful();
    }

    private function sentMessages(): array
    {
        return array_map(fn ($s) => $s['message'], $this->sender->sent);
    }

    public function test_first_run_initialises_snapshot_without_sending(): void
    {
        $this->quiz('Q1');
        $this->article('A1');

        $this->run15('push:new-content');

        $this->assertCount(0, $this->sender->sent);
        $this->assertSame(2, DB::table('push_assignment_snapshots')->count());
    }

    public function test_new_assignment_notifies_group_learners_once(): void
    {
        $this->quiz('Q1');
        $this->run15('push:new-content');

        $quiz = $this->quiz('Algèbre');
        $this->run15('push:new-content');

        $this->assertCount(1, $this->sender->sent);
        $m = $this->sentMessages()[0];
        $this->assertSame('Nouveau quiz disponible', $m->title);
        $this->assertSame('Algèbre', $m->body);
        $this->assertSame("/quizzes/{$quiz->id}", $m->url);
        $this->assertSame("quiz:{$quiz->id}", $m->ref);

        $this->run15('push:new-content');
        $this->assertCount(1, $this->sender->sent);
        $this->assertSame(1, PushDelivery::count());
    }

    public function test_several_new_contents_grouped_in_one_message(): void
    {
        $this->quiz('Q0');
        $this->run15('push:new-content');

        $q = $this->quiz('Q1');
        $a = $this->article('A1');
        $this->run15('push:new-content');

        $this->assertCount(1, $this->sender->sent);
        $m = $this->sentMessages()[0];
        $this->assertSame('2 nouveaux contenus disponibles', $m->title);
        $this->assertSame("Ouvrez l'application pour les découvrir.", $m->body);
        $this->assertSame('/', $m->url);
        $this->assertSame('new:'.sha1("article:{$a->id},quiz:{$q->id}"), $m->ref);
    }

    public function test_deck_and_article_urls(): void
    {
        $this->quiz('Q0');
        $this->run15('push:new-content');

        $deck = FlashcardDeck::create(['titre' => 'Verbes']);
        DB::table('group_flashcard_deck')->insert(['group_id' => $this->group->id, 'flashcard_deck_id' => $deck->id]);
        $this->run15('push:new-content');
        $m = $this->sentMessages()[0];
        $this->assertSame('Nouveau paquet de cartes disponible', $m->title);
        $this->assertSame('Verbes', $m->body);
        $this->assertSame('/reviser', $m->url);
        $this->assertSame("deck:{$deck->id}", $m->ref);

        $a = $this->article('Histoire');
        $this->run15('push:new-content');
        $m = $this->sentMessages()[1];
        $this->assertSame('Nouvel article disponible', $m->title);
        $this->assertSame("/articles/{$a->id}", $m->url);
    }

    public function test_inactive_or_future_quiz_not_notified(): void
    {
        $this->quiz('Q0');
        $this->run15('push:new-content');

        $this->quiz('Inactif', ['is_active' => false]);
        $future = $this->quiz('Futur', ['available_from' => now()->addDay()]);
        $this->run15('push:new-content');
        $this->assertCount(0, $this->sender->sent);

        // Une fois disponible, le quiz est annoncé.
        $this->travelTo(now()->addDays(2));
        $this->run15('push:new-content');
        $this->assertCount(1, $this->sender->sent);
        $this->assertSame("quiz:{$future->id}", $this->sentMessages()[0]->ref);
    }

    public function test_quiet_hours_defer_new_content_to_morning(): void
    {
        $this->quiz('Q0');
        $this->run15('push:new-content');
        $this->quiz('Nuit');

        $this->run15('push:new-content', '22:00');
        $this->assertCount(0, $this->sender->sent);

        $this->run15('push:new-content', '06:45');
        $this->assertCount(0, $this->sender->sent);

        $this->run15('push:new-content', '07:00');
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_empty_base_at_first_run_then_new_assignment_is_notified(): void
    {
        $this->run15('push:new-content');
        $this->assertCount(0, $this->sender->sent);

        $this->quiz('Premier');
        $this->run15('push:new-content');

        $this->assertCount(1, $this->sender->sent);
    }

    public function test_all_pairs_removed_then_new_pair_is_notified(): void
    {
        $q = $this->quiz('Q0');
        $this->run15('push:new-content');
        DB::table('group_quiz')->where('quiz_id', $q->id)->delete();
        $this->run15('push:new-content');
        $this->assertSame(0, DB::table('push_assignment_snapshots')->count());

        $this->quiz('Q1');
        $this->run15('push:new-content');

        $this->assertCount(1, $this->sender->sent);
    }

    public function test_removed_pairs_leave_the_snapshot(): void
    {
        $q = $this->quiz('Q0');
        $this->run15('push:new-content');
        DB::table('group_quiz')->where('quiz_id', $q->id)->delete();
        $this->run15('push:new-content');

        $this->assertSame(0, DB::table('push_assignment_snapshots')->count());
    }

    private function dueReview(Learner $learner, ?Carbon $next = null, bool $assigned = true, bool $active = true): void
    {
        $deck = FlashcardDeck::create(['titre' => 'D'.$learner->id, 'active' => $active]);
        if ($assigned) {
            DB::table('group_flashcard_deck')->insert(['group_id' => $this->group->id, 'flashcard_deck_id' => $deck->id]);
        }
        $item = FlashcardItem::create(['deck_id' => $deck->id, 'recto' => 'r', 'verso' => 'v']);
        FlashcardItemReview::create([
            'flashcard_item_id' => $item->id, 'learner_id' => $learner->id,
            'next_review' => $next ?? now()->subHour(),
        ]);
    }

    public function test_cards_due_sent_at_daily_hour_only(): void
    {
        $this->dueReview($this->learner);
        $this->dueReview($this->learner);

        $this->run15('push:daily-reminders', '17:45');
        $this->assertCount(0, $this->sender->sent);

        $this->run15('push:daily-reminders', '18:15');
        $this->assertCount(0, $this->sender->sent);

        $this->run15('push:daily-reminders', '18:00');
        $this->assertCount(1, $this->sender->sent);
        $m = $this->sentMessages()[0];
        $this->assertSame('Révisions du jour', $m->title);
        $this->assertSame('2 carte(s) vous attendent pour réviser.', $m->body);
        $this->assertSame('/reviser', $m->url);
        $this->assertSame('cards:2026-10-20', $m->ref);

        // Relance dans le même quart d'heure : pas de doublon.
        $this->run15('push:daily-reminders', '18:10');
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_cards_due_skips_learner_without_due_cards(): void
    {
        $this->dueReview($this->learner, now()->addDay());

        $this->run15('push:daily-reminders', '18:00');

        $this->assertCount(0, $this->sender->sent);
    }

    public function test_cards_due_counts_only_active_decks_still_assigned(): void
    {
        $this->dueReview($this->learner);
        $this->dueReview($this->learner, assigned: false);
        $this->dueReview($this->learner, active: false);

        $this->run15('push:daily-reminders', '18:00');

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame('1 carte(s) vous attendent pour réviser.', $this->sentMessages()[0]->body);
    }

    public function test_cards_due_skips_learner_whose_due_cards_are_all_unassigned(): void
    {
        $this->dueReview($this->learner, assigned: false);
        $this->dueReview($this->learner, active: false);

        $this->run15('push:daily-reminders', '18:00');

        $this->assertCount(0, $this->sender->sent);
    }

    public function test_streak_yesterday_uses_app_timezone(): void
    {
        // 03:00 UTC le 20/10 = 20:00 le 19/10 à Los Angeles. last_activity_date
        // est en heure applicative (UTC) : « hier » = 19/10, pas 18/10 (jour d'hier à LA).
        config(['push.timezone' => 'America/Los_Angeles', 'app.timezone' => 'UTC']);
        LearnerXp::create(['learner_id' => $this->learner->id, 'current_streak' => 3, 'last_activity_date' => '2026-10-19']);

        $this->run15('push:daily-reminders', '03:00');

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame('streak', $this->sentMessages()[0]->type);
    }

    public function test_empty_push_timezone_falls_back_to_app_timezone(): void
    {
        config(['push.timezone' => '', 'app.timezone' => 'UTC']);
        $this->dueReview($this->learner);

        $this->run15('push:daily-reminders', '18:00');

        $this->assertCount(1, $this->sender->sent);
    }

    public function test_cards_due_follows_admin_daily_hour(): void
    {
        AppSetting::put('push.daily_hour', 9);
        $this->dueReview($this->learner);

        $this->run15('push:daily-reminders', '18:00');
        $this->assertCount(0, $this->sender->sent);

        $this->run15('push:daily-reminders', '09:05');
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_streak_at_20h_only_when_at_risk(): void
    {
        $at_risk = $this->learner;
        LearnerXp::create(['learner_id' => $at_risk->id, 'current_streak' => 5, 'last_activity_date' => '2026-10-19']);

        $active = $this->makeLearner('bob');
        LearnerXp::create(['learner_id' => $active->id, 'current_streak' => 5, 'last_activity_date' => '2026-10-20']);

        $short = $this->makeLearner('cleo');
        LearnerXp::create(['learner_id' => $short->id, 'current_streak' => 1, 'last_activity_date' => '2026-10-19']);

        $stale = $this->makeLearner('dan');
        LearnerXp::create(['learner_id' => $stale->id, 'current_streak' => 5, 'last_activity_date' => '2026-10-10']);

        $this->run15('push:daily-reminders', '19:45');
        $this->assertCount(0, $this->sender->sent);

        $this->run15('push:daily-reminders', '20:00');
        $this->assertCount(1, $this->sender->sent);
        $m = $this->sentMessages()[0];
        $this->assertSame('Votre série est en danger 🔥', $m->title);
        $this->assertSame('Votre série de 5 jours se termine ce soir.', $m->body);
        $this->assertSame('/', $m->url);
        $this->assertSame('streak:2026-10-20', $m->ref);
        $this->assertSame($at_risk->id, PushDelivery::first()->learner_id);

        $this->run15('push:daily-reminders', '20:10');
        $this->assertCount(1, $this->sender->sent);
    }

    private function exam(string $title, array $attrs = []): Exam
    {
        $exam = Exam::create(array_merge(['title' => $title, 'duration' => 60, 'is_active' => true, 'available_from' => now()->subHour()], $attrs));
        DB::table('group_exam')->insert(['group_id' => $this->group->id, 'exam_id' => $exam->id]);

        return $exam;
    }

    public function test_exam_open_once_and_ignores_far_past(): void
    {
        $open = $this->exam('Partiel');
        $this->exam('Ancien', ['available_from' => now()->subDays(3)]);
        $this->exam('Futur', ['available_from' => now()->addHour()]);
        $this->exam('Inactif', ['is_active' => false]);

        $this->run15('push:exam-open');
        $this->run15('push:exam-open');

        $this->assertCount(1, $this->sender->sent);
        $m = $this->sentMessages()[0];
        $this->assertSame('Examen ouvert', $m->title);
        $this->assertSame("L'examen « Partiel » est ouvert.", $m->body);
        $this->assertSame('/examens', $m->url);
        $this->assertSame("exam:{$open->id}", $m->ref);
    }

    public function test_exam_open_skips_closed_exams(): void
    {
        $this->exam('Clos', ['available_until' => now()->subMinute()]);
        $open = $this->exam('Ouvert', ['available_until' => now()->addHour()]);

        $this->run15('push:exam-open');

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame("exam:{$open->id}", $this->sentMessages()[0]->ref);
    }

    public function test_exam_open_ignores_other_groups(): void
    {
        $other = Group::create(['name' => 'Promo B']);
        $exam = Exam::create(['title' => 'Autre', 'duration' => 60, 'is_active' => true, 'available_from' => now()->subHour()]);
        DB::table('group_exam')->insert(['group_id' => $other->id, 'exam_id' => $exam->id]);

        $this->run15('push:exam-open');

        $this->assertCount(0, $this->sender->sent);
    }

    public function test_suspended_learner_not_notified(): void
    {
        $this->exam('Partiel');
        $this->learner->forceFill(['suspended_at' => now()->subDay(), 'suspended_until' => null])->save();

        $this->run15('push:exam-open');
        $this->assertCount(0, $this->sender->sent);

        // Suspension échue : l'apprenant est de nouveau visé.
        $this->learner->forceFill(['suspended_until' => now()->subHour()])->save();
        $this->run15('push:exam-open');
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_inactive_user_not_notified(): void
    {
        $this->exam('Partiel');
        $this->learner->user->update(['is_active' => false]);

        $this->run15('push:exam-open');

        $this->assertCount(0, $this->sender->sent);
    }

    public function test_push_waits_until_folder_visible(): void
    {
        $this->quiz('Q0');
        $this->run15('push:new-content');

        $folder = Folder::create(['group_id' => $this->group->id, 'name' => 'Semaine 2', 'position' => 0, 'is_visible' => false]);
        $quiz = $this->quiz('Caché');
        DB::table('group_quiz')->where('quiz_id', $quiz->id)->update(['folder_id' => $folder->id]);

        $this->run15('push:new-content');
        $this->assertCount(0, $this->sender->sent);
        $this->assertFalse(DB::table('push_assignment_snapshots')->where('content_id', $quiz->id)->where('content_type', 'quiz')->exists());

        $folder->update(['is_visible' => true]);
        $this->run15('push:new-content');
        $this->run15('push:new-content');

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame("quiz:{$quiz->id}", $this->sentMessages()[0]->ref);
    }

    public function test_exam_open_waits_until_folder_visible(): void
    {
        $folder = Folder::create(['group_id' => $this->group->id, 'name' => 'Examens', 'position' => 0, 'is_visible' => false]);
        $exam = $this->exam('Partiel');
        DB::table('group_exam')->where('exam_id', $exam->id)->update(['folder_id' => $folder->id]);

        $this->run15('push:exam-open');
        $this->assertCount(0, $this->sender->sent);

        $folder->update(['is_visible' => true]);
        $this->run15('push:exam-open');
        $this->run15('push:exam-open');

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame("exam:{$exam->id}", $this->sentMessages()[0]->ref);
    }
}
