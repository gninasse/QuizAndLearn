<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamAttempt;
use Modules\Core\Models\ExamQuestion;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;
use Tests\TestCase;

class LearnerApiV1ExamTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Learner $learner;

    protected Group $group;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Exam',
            'last_name' => 'Taker',
            'user_name' => 'examtaker',
            'email' => 'examtaker@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->learner = Learner::create([
            'user_id' => $this->user->id,
            'matricule' => 'MAT-EXAM',
        ]);

        $this->group = Group::create(['name' => 'Exam Group', 'is_active' => true]);
        $this->learner->groups()->attach($this->group->id);

        $this->exam = Exam::create([
            'title' => 'Examen API v1',
            'description' => 'Examen de test',
            'duration' => 30,
            'passing_score' => 50,
            'is_active' => true,
            'max_attempts' => 1,
            'plein_ecran_force' => true,
            'anti_capture_strict' => true,
            'navigation_interdite' => true,
            'note_max' => 20,
            'created_by' => $this->user->id,
        ]);
        $this->exam->groups()->attach($this->group->id);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_text' => 'Vrai ou faux ?',
            'type' => 'true_false',
            'points' => 5,
            'points_negatifs' => 1,
            'order' => 1,
            'options' => ['correct_answer' => 'true'],
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_text' => 'Choisissez',
            'type' => 'mcq',
            'points' => 5,
            'points_negatifs' => 0,
            'order' => 2,
            'options' => ['choices' => [
                ['text' => 'Bonne réponse', 'is_correct' => true],
                ['text' => 'Mauvaise réponse', 'is_correct' => false],
            ]],
        ]);
    }

    public function test_start_attempt_strips_correct_answers(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['attempt_id', 'questions', 'remaining_seconds'])
            ->assertJsonPath('plein_ecran_force', true);

        $payload = $response->json();
        foreach ($payload['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_answer', $question['options']);
            foreach ($question['options']['choices'] ?? [] as $choice) {
                $this->assertArrayNotHasKey('is_correct', $choice);
            }
        }
    }

    public function test_start_rejected_outside_availability_window(): void
    {
        $this->exam->update(['available_until' => now()->subHour()]);

        $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id))
            ->assertStatus(403);
    }

    public function test_complete_scores_with_note_sur_vingt(): void
    {
        $start = $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id));
        $attemptId = $start->json('attempt_id');

        $questions = $this->exam->questions;

        $response = $this->actingAs($this->user)->postJson(
            route('learn.v1.exams.attempts.complete', [$this->exam->id, $attemptId]),
            ['answers' => [
                $questions[0]->id => 'true',
                $questions[1]->id => ['Bonne réponse'],
            ]],
        );

        $response->assertStatus(200)
            ->assertJsonPath('score_brut', 10)
            ->assertJsonPath('pourcentage', 100)
            ->assertJsonPath('note_sur_vingt', 20)
            ->assertJsonPath('passed', true)
            ->assertJsonPath('rank', 1)
            ->assertJsonPath('total_participants', 1);

        // +50 XP pour la réussite d'examen.
        $this->assertDatabaseHas('learner_xp', [
            'learner_id' => $this->learner->id,
            'total_xp' => 50,
        ]);
    }

    public function test_negative_points_applied_on_wrong_answer(): void
    {
        $start = $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id));
        $attemptId = $start->json('attempt_id');

        $questions = $this->exam->questions;

        $response = $this->actingAs($this->user)->postJson(
            route('learn.v1.exams.attempts.complete', [$this->exam->id, $attemptId]),
            ['answers' => [
                $questions[0]->id => 'false',            // faux → -1 point négatif
                $questions[1]->id => ['Bonne réponse'],  // juste → +5
            ]],
        );

        // 5 - 1 = 4 points bruts sur 10 → 40 % → 8/20, sous passing_score 50.
        $response->assertStatus(200)
            ->assertJsonPath('score_brut', 4)
            ->assertJsonPath('pourcentage', 40)
            ->assertJsonPath('note_sur_vingt', 8)
            ->assertJsonPath('passed', false);
    }

    public function test_strict_screenshot_cancels_immediately(): void
    {
        $start = $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id));
        $attemptId = $start->json('attempt_id');

        $response = $this->actingAs($this->user)->postJson(
            route('learn.v1.exams.attempts.violations', [$this->exam->id, $attemptId]),
            ['type' => 'screenshot'],
        );

        $response->assertStatus(200)->assertJsonPath('cancelled', true);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'annule',
            'capture_attempts' => 1,
        ]);
    }

    public function test_third_navigation_violation_cancels(): void
    {
        $start = $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id));
        $attemptId = $start->json('attempt_id');

        foreach ([1, 2] as $count) {
            $this->actingAs($this->user)->postJson(
                route('learn.v1.exams.attempts.violations', [$this->exam->id, $attemptId]),
                ['type' => 'navigation'],
            )->assertJsonPath('cancelled', false)
                ->assertJsonPath('violations_count', $count);
        }

        $this->actingAs($this->user)->postJson(
            route('learn.v1.exams.attempts.violations', [$this->exam->id, $attemptId]),
            ['type' => 'navigation'],
        )->assertJsonPath('cancelled', true);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'annule',
            'navigation_violations' => 3,
        ]);
    }

    public function test_max_attempts_enforced(): void
    {
        ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'learner_id' => $this->learner->id,
            'date_debut' => now()->subHour(),
            'date_fin' => now()->subMinutes(30),
            'status' => 'termine',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id))
            ->assertStatus(403);
    }

    public function test_autosave_updates_answers(): void
    {
        $start = $this->actingAs($this->user)
            ->postJson(route('learn.v1.exams.attempts.store', $this->exam->id));
        $attemptId = $start->json('attempt_id');
        $questionId = $this->exam->questions->first()->id;

        $this->actingAs($this->user)->patchJson(
            route('learn.v1.exams.attempts.update', [$this->exam->id, $attemptId]),
            ['answers' => [$questionId => 'true']],
        )->assertStatus(200)->assertJsonPath('success', true);

        $attempt = ExamAttempt::find($attemptId);
        $this->assertSame('true', $attempt->answers[$questionId] ?? $attempt->answers[(string) $questionId]);
    }
}
