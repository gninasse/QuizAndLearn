<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Tests\TestCase;

class QuizEditorTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Quiz $quiz;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user and assign a role if necessary
        $this->user = User::create([
            'name' => 'Test',
            'last_name' => 'User',
            'user_name' => 'testuser',
            'email' => 'testuser@example.com',
            'phone' => '123456789',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        \Spatie\Permission\Models\Role::findOrCreate('super-admin');
        $this->user->assignRole('super-admin');

        // Create a quiz
        $this->quiz = Quiz::create([
            'title' => 'Test Quiz',
            'description' => 'Test Description',
            'duration' => 10,
            'passing_score' => 70,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        // Create a group
        $this->group = Group::create([
            'name' => 'Test Group',
            'description' => 'Group for testing',
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);
    }

    /**
     * Test editing a quiz displays the editor page.
     */
    public function test_can_access_quiz_editor(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.quizzes.edit', $this->quiz->id));

        $response->assertStatus(200);
        $response->assertViewIs('core::admin.quiz.editor');
        $response->assertSee('Test Quiz');
    }

    /**
     * Test accessing the quiz preview page.
     */
    public function test_can_access_quiz_preview(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.quizzes.preview', $this->quiz->id));

        $response->assertStatus(200);
        $response->assertViewIs('core::admin.quiz.preview');
    }

    /**
     * Test accessing the quiz preview player iframe.
     */
    public function test_can_access_quiz_preview_iframe(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.quizzes.preview-iframe', $this->quiz->id));

        $response->assertStatus(200);
        $response->assertViewIs('core::admin.quiz.preview-iframe');
    }

    /**
     * Test quiz settings autosave.
     */
    public function test_can_autosave_quiz(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.autosave', $this->quiz->id), [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
                'duration' => 25,
                'passing_score' => 85,
                'shuffle_questions' => 'on',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Modification enregistrée avec succès.',
        ]);

        $this->quiz->refresh();
        $this->assertEquals('Updated Title', $this->quiz->title);
        $this->assertEquals('Updated Description', $this->quiz->description);
        $this->assertEquals(25, $this->quiz->duration);
        $this->assertEquals(85, $this->quiz->passing_score);
        $this->assertTrue($this->quiz->shuffle_questions);
    }

    /**
     * Test toggling active state of the quiz.
     */
    public function test_can_toggle_active_status(): void
    {
        $this->assertFalse($this->quiz->is_active);

        $response = $this->actingAs($this->user)
            ->patch(route('admin.quizzes.toggle-active', $this->quiz->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_active' => true,
        ]);

        $this->quiz->refresh();
        $this->assertTrue($this->quiz->is_active);
    }

    /**
     * Test reordering questions.
     */
    public function test_can_reorder_questions(): void
    {
        $q1 = Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Q1',
            'type' => 'true_false',
            'points' => 10,
            'order' => 0,
        ]);

        $q2 = Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Q2',
            'type' => 'mcq',
            'points' => 10,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.reorder', $this->quiz->id), [
                'question_ids' => [$q2->id, $q1->id],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $q1->refresh();
        $q2->refresh();

        $this->assertEquals(1, $q1->order);
        $this->assertEquals(0, $q2->order);
    }

    /**
     * Test search groups auto-complete.
     */
    public function test_can_search_active_groups(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.groups.search', ['q' => 'Test']));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'name' => 'Test Group',
        ]);
    }

    /**
     * Test group search is case-insensitive.
     */
    public function test_group_search_is_case_insensitive(): void
    {
        // Search using lowercase query for group with uppercase name
        $response = $this->actingAs($this->user)
            ->get(route('admin.groups.search', ['q' => 'test']));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Test Group',
        ]);
    }

    /**
     * Test group search filters groups for trainers.
     */
    public function test_group_search_filters_groups_for_trainers(): void
    {
        // 1. Create a trainer user
        $trainerUser = User::create([
            'name' => 'Trainer',
            'last_name' => 'One',
            'user_name' => 'trainerone',
            'email' => 'trainerone@example.com',
            'phone' => '987654321',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
        \Spatie\Permission\Models\Role::findOrCreate('trainer');
        $trainerUser->assignRole('trainer');

        // Create the trainer profile
        $trainerProfile = \Modules\Core\Models\Trainer::create([
            'user_id' => $trainerUser->id,
            'specialty' => 'PHP',
        ]);

        // Create two groups
        $assignedGroup = Group::create([
            'name' => 'Assigned Group',
            'is_active' => true,
        ]);
        $unassignedGroup = Group::create([
            'name' => 'Unassigned Group',
            'is_active' => true,
        ]);

        // Assign one group to the trainer
        $trainerProfile->groups()->sync([$assignedGroup->id]);

        // Search as trainer
        $response = $this->actingAs($trainerUser)
            ->get(route('admin.groups.search', ['q' => 'Group']));

        $response->assertStatus(200);

        // Should see the assigned group
        $response->assertJsonFragment([
            'name' => 'Assigned Group',
        ]);

        // Should NOT see the unassigned group
        $response->assertJsonMissing([
            'name' => 'Unassigned Group',
        ]);
    }

    /**
     * Test assign and unassign a group to/from the quiz.
     */
    public function test_can_assign_and_unassign_group(): void
    {
        // 1. Assign group
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.groups.assign', $this->quiz->id), [
                'group_id' => $this->group->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertTrue($this->quiz->groups()->where('group_id', $this->group->id)->exists());

        // 2. Unassign group
        $response = $this->actingAs($this->user)
            ->delete(route('admin.quizzes.groups.unassign', [
                'quiz' => $this->quiz->id,
                'group' => $this->group->id,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertFalse($this->quiz->groups()->where('group_id', $this->group->id)->exists());
    }

    /**
     * Test storing a question via AJAX.
     */
    public function test_can_store_question(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.questions.store', $this->quiz->id), [
                'question_text' => 'New True False Question',
                'type' => 'true_false',
                'points' => 12,
                'options' => ['correct_answer' => 'false'],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question créée avec succès.',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'question_text' => 'New True False Question',
            'type' => 'true_false',
            'points' => 12,
        ]);
    }

    /**
     * Test storing a fill-in-the-blank question via AJAX.
     */
    public function test_can_store_fill_blank_question(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.questions.store', $this->quiz->id), [
                'question_text' => 'The capital of France is [blank].',
                'type' => 'fill_blank',
                'points' => 15,
                'options' => [
                    'title' => 'France Capital',
                    'blanks' => [
                        [
                            'answers' => ['Paris', 'paris'],
                            'case_sensitive' => false,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question créée avec succès.',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'question_text' => 'The capital of France is [blank].',
            'type' => 'fill_blank',
            'points' => 15,
        ]);
    }

    /**
     * Test storing an ordering question via AJAX.
     */
    public function test_can_store_ordering_question(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.questions.store', $this->quiz->id), [
                'question_text' => 'Order these events chronologically.',
                'type' => 'ordering',
                'points' => 10,
                'options' => [
                    'title' => 'Chronological order',
                    'items' => [
                        'First event',
                        'Second event',
                        'Third event',
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question créée avec succès.',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Order these events chronologically.',
            'type' => 'ordering',
            'points' => 10,
        ]);
    }

    /**
     * Test storing a matching question via AJAX.
     */
    public function test_can_store_matching_question(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.questions.store', $this->quiz->id), [
                'question_text' => 'Match the capitals to their countries.',
                'type' => 'matching',
                'points' => 20,
                'options' => [
                    'group' => 'culture_générale',
                    'pairs' => [
                        ['term' => 'Paris', 'definition' => 'France'],
                        ['term' => 'Berlin', 'definition' => 'Germany'],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question créée avec succès.',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Match the capitals to their countries.',
            'type' => 'matching',
            'points' => 20,
        ]);
    }

    /**
     * Test storing an open text question via AJAX.
     */
    public function test_can_store_open_text_question(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.questions.store', $this->quiz->id), [
                'question_text' => 'Describe your favorite coding project.',
                'type' => 'open_text',
                'points' => 10,
                'options' => [
                    'max_characters' => 300,
                    'required' => true,
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question créée avec succès.',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Describe your favorite coding project.',
            'type' => 'open_text',
            'points' => 10,
        ]);
    }

    /**
     * Test storing a MCQ question via AJAX.
     */
    public function test_can_store_mcq_question(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.quizzes.questions.store', $this->quiz->id), [
                'question_text' => 'Which of the following are primary colors?',
                'type' => 'mcq',
                'points' => 15,
                'options' => [
                    'multiple' => true,
                    'partial_score' => true,
                    'group' => 'culture_générale',
                    'answers' => [
                        ['text' => 'Red', 'is_correct' => true],
                        ['text' => 'Green', 'is_correct' => false],
                        ['text' => 'Blue', 'is_correct' => true],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question créée avec succès.',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Which of the following are primary colors?',
            'type' => 'mcq',
            'points' => 15,
        ]);
    }

    /**
     * Test showing a question via AJAX.
     */
    public function test_can_show_question(): void
    {
        $q = Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Some Question Text',
            'type' => 'true_false',
            'points' => 10,
            'order' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.quizzes.questions.show', [
                'quiz' => $this->quiz->id,
                'question' => $q->id,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $q->id,
                'question_text' => 'Some Question Text',
            ],
        ]);
    }

    /**
     * Test updating a question via AJAX.
     */
    public function test_can_update_question(): void
    {
        $q = Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Old Question Text',
            'type' => 'true_false',
            'points' => 10,
            'order' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.quizzes.questions.update', [
                'quiz' => $this->quiz->id,
                'q' => $q->id,
            ]), [
                'question_text' => 'Updated Question Text',
                'type' => 'true_false',
                'points' => 15,
                'options' => ['correct_answer' => 'true'],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question mise à jour avec succès.',
        ]);

        $q->refresh();
        $this->assertEquals('Updated Question Text', $q->question_text);
        $this->assertEquals(15, $q->points);
    }

    /**
     * Test deleting a question via AJAX.
     */
    public function test_can_delete_question(): void
    {
        $q = Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'ToDelete',
            'type' => 'true_false',
            'points' => 10,
            'order' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.quizzes.questions.destroy', [
                'quiz' => $this->quiz->id,
                'q' => $q->id,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Question supprimée avec succès.',
        ]);

        $this->assertDatabaseMissing('questions', [
            'id' => $q->id,
        ]);
    }
}
