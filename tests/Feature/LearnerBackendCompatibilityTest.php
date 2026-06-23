<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Article;
use Modules\Core\Models\Badge;
use Modules\Core\Models\ErrorReport;
use Modules\Core\Models\Flashcard;
use Modules\Core\Models\Learner;
use Modules\Core\Models\LearnerPreference;
use Modules\Core\Models\LearnerProgress;
use Modules\Core\Models\LearnerXp;
use Modules\Core\Models\Notification;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAnswer;
use Modules\Core\Models\QuizAttempt;
use Modules\Core\Models\ScreenshotAttempt;
use Modules\Core\Models\User;
use Tests\TestCase;

class LearnerBackendCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Learner $learner;

    protected Quiz $quiz;

    protected Question $question;

    protected Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        // Create core user & learner
        $this->user = User::create([
            'name' => 'Jean',
            'last_name' => 'Dupont',
            'user_name' => 'jdupont',
            'email' => 'jdupont@learnandquiz.fr',
            'phone' => '0612345678',
            'is_active' => true,
            'password' => bcrypt('secret123'),
        ]);

        $this->learner = Learner::create([
            'user_id' => $this->user->id,
            'matricule' => 'MAT-2026-001',
        ]);

        // Create quiz
        $this->quiz = Quiz::create([
            'title' => 'Introduction to PHP',
            'description' => 'Test your PHP basics',
            'duration' => 20,
            'passing_score' => 60,
            'is_active' => true,
            'max_attempts' => 3,
            'show_correct_answers' => true,
            'allow_partial_score' => true,
            'created_by' => $this->user->id,
        ]);

        // Create question
        $this->question = Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'What does PHP stand for?',
            'type' => 'single_choice',
            'points' => 2,
            'order' => 1,
            'options' => json_encode(['A' => 'Personal Home Page', 'B' => 'PHP: Hypertext Preprocessor']),
        ]);

        // Create article
        $this->article = Article::create([
            'title' => 'Learn PHP variables',
            'content' => 'Lorem ipsum...',
            'is_active' => true,
            'estimated_reading_time' => 5,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test Quiz Attempts and Answers relationships.
     */
    public function test_can_manage_quiz_attempts_and_answers(): void
    {
        // 1. Create a quiz attempt
        $attempt = QuizAttempt::create([
            'learner_id' => $this->learner->id,
            'quiz_id' => $this->quiz->id,
            'started_at' => now(),
            'attempt_number' => 1,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $attempt->id,
            'learner_id' => $this->learner->id,
            'quiz_id' => $this->quiz->id,
            'status' => 'in_progress',
        ]);

        // 2. Submit an answer
        $answer = QuizAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->question->id,
            'answer_given' => 'B',
            'correct_answer' => 'B',
            'is_correct' => true,
            'points_earned' => 2,
            'answered_at' => now(),
        ]);

        $this->assertDatabaseHas('quiz_answers', [
            'id' => $answer->id,
            'attempt_id' => $attempt->id,
            'is_correct' => true,
        ]);

        // 3. Complete the attempt
        $attempt->update([
            'completed_at' => now(),
            'submitted_at' => now(),
            'score' => 100.00,
            'points_earned' => 2,
            'points_total' => 2,
            'passed' => true,
            'time_spent' => 45,
            'status' => 'completed',
        ]);

        $this->assertTrue($attempt->fresh()->passed);
        $this->assertCount(1, $attempt->quizAnswers);
        $this->assertEquals($attempt->learner->id, $this->learner->id);
    }

    /**
     * Test Learner Progress tracking and polymorphic relationships.
     */
    public function test_can_track_learner_progress_on_articles_and_quizzes(): void
    {
        // 1. Track article progress
        $progress = LearnerProgress::create([
            'learner_id' => $this->learner->id,
            'content_type' => 'article',
            'content_id' => $this->article->id,
            'status' => 'completed',
            'progress_percentage' => 100,
            'rating' => 5, // Star rating
            'is_favorite' => true,
            'completed_at' => now(),
        ]);

        $this->assertDatabaseHas('learner_progress', [
            'learner_id' => $this->learner->id,
            'content_type' => 'article',
            'content_id' => $this->article->id,
            'rating' => 5,
            'is_favorite' => true,
        ]);

        $this->assertInstanceOf(Article::class, $progress->content);
        $this->assertEquals($progress->content->title, 'Learn PHP variables');
    }

    /**
     * Test Spaced Repetition Flashcards.
     */
    public function test_can_manage_spaced_repetition_flashcards(): void
    {
        $flashcard = Flashcard::create([
            'learner_id' => $this->learner->id,
            'question_id' => $this->question->id,
            'difficulty_factor' => 2.50,
            'interval_days' => 3,
            'repetitions' => 1,
            'next_review_date' => now()->addDays(3)->toDateString(),
            'ease_rating' => 'easy',
        ]);

        $this->assertDatabaseHas('flashcards', [
            'learner_id' => $this->learner->id,
            'question_id' => $this->question->id,
            'ease_rating' => 'easy',
        ]);

        $this->assertEquals($flashcard->question->question_text, 'What does PHP stand for?');
    }

    /**
     * Test Gamification: Badges, XP, and Streaks.
     */
    public function test_can_manage_gamification_metrics(): void
    {
        // 1. Badges
        $badge = Badge::create([
            'code' => 'first_step',
            'name' => 'Premier pas',
            'description' => 'Terminer son premier quiz',
            'icon' => '🚀',
            'condition_type' => 'quiz_completed',
            'condition_value' => ['count' => 1],
        ]);

        $this->learner->badges()->attach($badge->id);

        $this->assertDatabaseHas('learner_badges', [
            'learner_id' => $this->learner->id,
            'badge_id' => $badge->id,
        ]);

        $this->assertCount(1, $this->learner->badges);

        // 2. XP & Streak tracking
        $xp = LearnerXp::create([
            'learner_id' => $this->learner->id,
            'total_xp' => 150,
            'current_level' => 2,
            'current_streak' => 3,
            'longest_streak' => 5,
            'last_activity_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('learner_xp', [
            'learner_id' => $this->learner->id,
            'total_xp' => 150,
            'current_streak' => 3,
        ]);

        $this->assertEquals($this->learner->xp->total_xp, 150);
    }

    /**
     * Test error reporting and screenshot security logging.
     */
    public function test_can_log_error_reports_and_screenshots(): void
    {
        // 1. Error report
        $report = ErrorReport::create([
            'learner_id' => $this->learner->id,
            'content_type' => 'quiz',
            'content_id' => $this->quiz->id,
            'error_type' => 'spelling',
            'comment' => 'Typo in question 1',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('error_reports', [
            'learner_id' => $this->learner->id,
            'content_type' => 'quiz',
            'error_type' => 'spelling',
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(Quiz::class, $report->content);

        // 2. Screenshot attempt
        $attempt = QuizAttempt::create([
            'learner_id' => $this->learner->id,
            'quiz_id' => $this->quiz->id,
            'started_at' => now(),
            'attempt_number' => 1,
            'status' => 'in_progress',
        ]);

        $screenshot = ScreenshotAttempt::create([
            'learner_id' => $this->learner->id,
            'attempt_id' => $attempt->id,
            'detected_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0...',
        ]);

        $this->assertDatabaseHas('screenshot_attempts', [
            'learner_id' => $this->learner->id,
            'attempt_id' => $attempt->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    /**
     * Test notifications and user preferences.
     */
    public function test_can_manage_preferences_and_notifications(): void
    {
        // 1. Preferences
        $pref = LearnerPreference::create([
            'learner_id' => $this->learner->id,
            'locale' => 'fr',
            'theme' => 'dark',
            'font_size' => 'large',
            'sound_enabled' => false,
            'notifications_enabled' => ['new_quiz' => true, 'new_article' => true],
        ]);

        $this->assertDatabaseHas('learner_preferences', [
            'learner_id' => $this->learner->id,
            'theme' => 'dark',
            'sound_enabled' => false,
        ]);

        $this->assertEquals($this->learner->preferences->font_size, 'large');

        // 2. Notification
        $notif = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'new_quiz',
            'title' => 'Nouveau quiz disponible',
            'message' => 'Un nouveau quiz PHP est disponible',
            'action_url' => '/quizzes/1',
            'icon' => '🚀',
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'new_quiz',
            'is_read' => false,
        ]);
    }
}
