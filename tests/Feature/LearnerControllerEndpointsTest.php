<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Article;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Tests\TestCase;

class LearnerControllerEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Learner $learner;

    protected Group $group;

    protected Quiz $quiz;

    protected Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test',
            'last_name' => 'Learner',
            'user_name' => 'tlearner',
            'email' => 'tlearner@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->learner = Learner::create([
            'user_id' => $this->user->id,
            'matricule' => 'MAT-TEST',
        ]);

        $this->group = Group::create([
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);

        $this->learner->groups()->attach($this->group->id);

        $this->quiz = Quiz::create([
            'title' => 'Sample Quiz',
            'description' => 'A quiz description',
            'duration' => 15,
            'passing_score' => 60,
            'is_active' => true,
            'max_attempts' => 2,
            'created_by' => $this->user->id,
        ]);
        $this->quiz->groups()->attach($this->group->id);

        Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Sample Question',
            'type' => 'true_false',
            'points' => 2,
            'order' => 1,
            'options' => ['correct_answer' => 'true'],
        ]);

        $this->article = Article::create([
            'title' => 'Sample Article',
            'content' => 'Sample body content',
            'is_active' => true,
            'estimated_reading_time' => 5,
            'created_by' => $this->user->id,
        ]);
        $this->article->groups()->attach($this->group->id);
    }

    public function test_guests_cannot_access_learner_routes(): void
    {
        $this->get(route('learner.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_can_access_dashboard_and_assigned_content(): void
    {
        $this->actingAs($this->user)
            ->get(route('learner.dashboard'))
            ->assertStatus(200)
            ->assertSee('Sample Quiz')
            ->assertSee('Sample Article');
    }

    public function test_can_access_quizzes_list_and_details(): void
    {
        $this->actingAs($this->user)
            ->get(route('learner.quizzes.index'))
            ->assertStatus(200)
            ->assertSee('Sample Quiz');

        $this->actingAs($this->user)
            ->get(route('learner.quizzes.show', $this->quiz->id))
            ->assertStatus(200)
            ->assertSee('Démarrer le quiz');
    }

    public function test_can_manage_quiz_attempts_and_answers(): void
    {
        // Start attempt
        $this->actingAs($this->user)
            ->post(route('learner.quizzes.attempts.start', $this->quiz->id))
            ->assertRedirect(route('learner.quizzes.show', $this->quiz->id));

        $attempt = $this->learner->attempts()->first();
        $this->assertNotNull($attempt);
        $this->assertEquals('in_progress', $attempt->status);

        // Submit answer
        $question = $this->quiz->questions()->first();
        $this->actingAs($this->user)
            ->postJson(route('learner.quizzes.attempts.answers.submit', [$this->quiz->id, $attempt->id]), [
                'question_id' => $question->id,
                'answer_given' => 'true',
            ])
            ->assertJson(['success' => true]);

        // Complete attempt
        $this->actingAs($this->user)
            ->postJson(route('learner.quizzes.attempts.complete', [$this->quiz->id, $attempt->id]))
            ->assertJson(['success' => true]);

        $attempt->refresh();
        $this->assertEquals('completed', $attempt->status);
        $this->assertEquals(100.00, $attempt->score);
        $this->assertTrue($attempt->passed);
    }

    public function test_can_update_article_reading_progress(): void
    {
        // Go to article to initialize progress
        $this->actingAs($this->user)
            ->get(route('learner.articles.show', $this->article->id))
            ->assertStatus(200);

        // Update progress
        $this->actingAs($this->user)
            ->postJson(route('learner.articles.progress', $this->article->id), [
                'progress_percentage' => 85,
            ])
            ->assertJson(['success' => true, 'status' => 'completed']);
    }
}
