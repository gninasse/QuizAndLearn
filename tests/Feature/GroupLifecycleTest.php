<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\Exam;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * Cycle de vie des groupes côté apprenant : un groupe suspendu
 * (is_active=false), fermé (end_date passée) ou supprimé ne délivre
 * plus de contenu et refuse les actions — l'historique est conservé.
 */
class GroupLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Learner $learner;

    protected Group $group;

    protected Quiz $quiz;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Cycle', 'last_name' => 'Groupe', 'user_name' => 'cyclegroupe',
            'email' => 'cycle@learnandquiz.fr', 'password' => bcrypt('password123'), 'is_active' => true,
        ]);
        $this->learner = Learner::create(['user_id' => $this->user->id, 'matricule' => 'MAT-CYC']);

        $this->group = Group::create(['name' => 'Groupe Cycle', 'is_active' => true]);
        $this->learner->groups()->attach($this->group->id);

        $this->quiz = Quiz::create([
            'title' => 'Quiz du groupe', 'is_active' => true, 'passing_score' => 60,
            'max_attempts' => 3, 'created_by' => $this->user->id,
        ]);
        $this->quiz->groups()->attach($this->group->id);
        Question::create([
            'quiz_id' => $this->quiz->id, 'question_text' => 'Q', 'type' => 'true_false',
            'points' => 2, 'order' => 1, 'options' => ['correct_answer' => 'true'],
        ]);

        $this->exam = Exam::create([
            'title' => 'Examen du groupe', 'duration' => 30, 'passing_score' => 50,
            'is_active' => true, 'max_attempts' => 3, 'note_max' => 20, 'created_by' => $this->user->id,
        ]);
        $this->exam->groups()->attach($this->group->id);
    }

    private function quizAttemptPayload(): array
    {
        return [
            'actions' => [[
                'id' => (string) Str::uuid(),
                'type' => 'quiz_attempt',
                'data' => [
                    'quiz_id' => $this->quiz->id,
                    'answers' => [$this->quiz->questions->first()->id => 'true'],
                ],
            ]],
        ];
    }

    // ------------------------------------------------------------ Suspendu

    public function test_deactivated_group_stops_delivering_content(): void
    {
        $this->group->update(['is_active' => false]);

        $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))
            ->assertStatus(200)
            ->assertJsonPath('quizzes', [])
            ->assertJsonPath('exams', [])
            ->assertJsonPath('groups.0.status', 'suspended');
    }

    public function test_deactivated_group_shrinks_authorized_ids_in_delta(): void
    {
        $cursor = now()->toIso8601String();
        $this->group->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->getJson(route('learn.v1.changes', ['since' => $cursor]))
            ->assertStatus(200)
            ->assertJsonPath('quizzes.authorized_ids', [])
            ->assertJsonPath('exams.authorized_ids', [])
            ->assertJsonPath('groups.0.status', 'suspended');
    }

    public function test_deactivated_group_rejects_quiz_attempts(): void
    {
        $this->group->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->postJson(route('learn.v1.actions'), $this->quizAttemptPayload())
            ->assertStatus(200)
            ->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_deactivated_group_blocks_exam_start(): void
    {
        $this->group->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------- Fermé

    public function test_closed_group_stops_delivering_content(): void
    {
        $this->group->update(['start_date' => now()->subMonths(2), 'end_date' => now()->subDay()]);

        $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))
            ->assertStatus(200)
            ->assertJsonPath('quizzes', [])
            ->assertJsonPath('groups.0.status', 'closed');

        $this->actingAs($this->user)
            ->postJson(route('learn.v1.actions'), $this->quizAttemptPayload())
            ->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_upcoming_group_does_not_deliver_yet(): void
    {
        $this->group->update(['start_date' => now()->addWeek()]);

        $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))
            ->assertStatus(200)
            ->assertJsonPath('quizzes', [])
            ->assertJsonPath('groups.0.status', 'upcoming');
    }

    public function test_group_within_window_delivers_normally(): void
    {
        $this->group->update(['start_date' => now()->subWeek(), 'end_date' => now()->addWeek()]);

        $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))
            ->assertStatus(200)
            ->assertJsonPath('quizzes.0.title', 'Quiz du groupe')
            ->assertJsonPath('groups.0.status', 'active');
    }

    // ----------------------------------------------------------- Supprimé

    public function test_deleted_group_shrinks_authorized_ids_and_keeps_history(): void
    {
        // L'apprenant complète le quiz d'abord (historique).
        $this->actingAs($this->user)
            ->postJson(route('learn.v1.actions'), $this->quizAttemptPayload())
            ->assertJsonPath('results.0.status', 'applied');

        $cursor = now()->toIso8601String();
        $this->group->delete();

        $this->actingAs($this->user)
            ->getJson(route('learn.v1.changes', ['since' => $cursor]))
            ->assertStatus(200)
            ->assertJsonPath('quizzes.authorized_ids', [])
            ->assertJsonPath('groups', []);

        // L'historique et l'XP survivent à la suppression du groupe.
        $this->assertDatabaseHas('quiz_attempts', [
            'learner_id' => $this->learner->id,
            'quiz_id' => $this->quiz->id,
            'status' => 'completed',
        ]);
        $this->assertTrue($this->learner->xp()->first()->total_xp > 0);
    }

    // ------------------------------------------------------- Réactivation

    public function test_reactivated_group_delivers_again(): void
    {
        $this->group->update(['is_active' => false]);
        $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))->assertJsonPath('quizzes', []);

        $this->group->update(['is_active' => true]);
        $this->actingAs($this->user)->getJson(route('learn.v1.bootstrap'))
            ->assertJsonPath('quizzes.0.title', 'Quiz du groupe')
            ->assertJsonPath('groups.0.status', 'active');
    }
}
