<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Tests\TestCase;

class GroupMembersTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Group $group;

    protected Trainer $trainer;

    protected Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active admin user
        $this->adminUser = User::create([
            'name' => 'Admin',
            'last_name' => 'User',
            'user_name' => 'adminuser',
            'email' => 'adminuser@example.com',
            'phone' => '111222333',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        // Create a group
        $this->group = Group::create([
            'name' => 'Original Group Name',
            'description' => 'Original description',
            'is_active' => true,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
        ]);

        // Create a user for Trainer
        $trainerUser = User::create([
            'name' => 'Trainer',
            'last_name' => 'One',
            'user_name' => 'trainerone',
            'email' => 'trainerone@example.com',
            'phone' => '555666777',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->trainer = Trainer::create([
            'user_id' => $trainerUser->id,
            'specialty' => 'Maths',
        ]);

        // Create a user for Learner
        $learnerUser = User::create([
            'name' => 'Learner',
            'last_name' => 'One',
            'user_name' => 'learnerone',
            'email' => 'learnerone@example.com',
            'phone' => '888999000',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->learner = Learner::create([
            'user_id' => $learnerUser->id,
            'matricule' => 'L123456',
        ]);
    }

    /**
     * Test fetching group members and parameters structure.
     */
    public function test_can_fetch_group_members_and_parameters(): void
    {
        // Assign trainer and learner to group
        $this->group->trainers()->attach($this->trainer->id);
        $this->group->learners()->attach($this->learner->id);

        // Assign a quiz
        $quiz = Quiz::create([
            'title' => 'Assigned Group Quiz',
            'description' => 'Group test quiz',
            'duration' => 30,
            'passing_score' => 60,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);
        $this->group->quizzes()->attach($quiz->id);

        $response = $this->actingAs($this->adminUser)
            ->get(route('cores.groups.members', $this->group->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'group_name' => 'Original Group Name',
            'is_active' => true,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
        ]);

        // Assert trainers list
        $response->assertJsonFragment([
            'id' => $this->trainer->id,
            'assigned' => true,
        ]);

        // Assert learners list
        $response->assertJsonFragment([
            'id' => $this->learner->id,
            'assigned' => true,
        ]);

        // Assert quizzes list
        $response->assertJsonFragment([
            'id' => $quiz->id,
            'title' => 'Assigned Group Quiz',
        ]);
    }

    /**
     * Test updating group dates, status, and members.
     */
    public function test_can_update_group_members_and_parameters(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('cores.groups.members.assign', $this->group->id), [
                'trainer_ids' => [$this->trainer->id],
                'learner_ids' => [$this->learner->id],
                'start_date' => '2026-02-01',
                'end_date' => '2026-08-01',
                'is_active' => 0,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Membres et paramètres du groupe mis à jour avec succès',
        ]);

        // Refresh and verify attributes
        $this->group->refresh();
        $this->assertEquals('2026-02-01', $this->group->start_date->format('Y-m-d'));
        $this->assertEquals('2026-08-01', $this->group->end_date->format('Y-m-d'));
        $this->assertFalse($this->group->is_active);

        // Verify relationships
        $this->assertTrue($this->group->trainers()->where('trainer_id', $this->trainer->id)->exists());
        $this->assertTrue($this->group->learners()->where('learner_id', $this->learner->id)->exists());
    }
}
