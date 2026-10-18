<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * Cochage « Visible en mode découverte » (vitrine) + groupes ouverts aux demandes.
 */
class ShowcaseFlagTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $handle, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
        Role::findOrCreate($role);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_toggles_showcase_on_each_type(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $items = [
            'quiz' => Quiz::create(['title' => 'Q', 'is_active' => true]),
            'article' => Article::create(['title' => 'A', 'content' => 'x', 'is_active' => true]),
            'deck' => FlashcardDeck::create(['titre' => 'D']),
        ];

        foreach ($items as $type => $model) {
            $this->actingAs($admin)
                ->patchJson("/cores/showcase/{$type}/{$model->id}", ['is_showcase' => true])
                ->assertOk()
                ->assertJson(['success' => true, 'is_showcase' => true]);
            $this->assertTrue($model->fresh()->is_showcase);

            $this->actingAs($admin)
                ->patchJson("/cores/showcase/{$type}/{$model->id}", ['is_showcase' => false])
                ->assertOk()
                ->assertJson(['success' => true, 'is_showcase' => false]);
            $this->assertFalse($model->fresh()->is_showcase);
        }
    }

    public function test_trainer_cannot_toggle_showcase(): void
    {
        $trainer = $this->makeUser('prof', 'trainer');
        $quiz = Quiz::create(['title' => 'Q', 'is_active' => true, 'created_by' => $trainer->id]);

        $this->actingAs($trainer)
            ->patchJson("/cores/showcase/quiz/{$quiz->id}", ['is_showcase' => true])
            ->assertForbidden();
        $this->assertFalse($quiz->fresh()->is_showcase);
    }

    public function test_exam_type_is_rejected(): void
    {
        $admin = $this->makeUser('boss', 'admin');

        $this->actingAs($admin)
            ->patchJson('/cores/showcase/exam/1', ['is_showcase' => true])
            ->assertNotFound();
    }

    public function test_deck_is_public_is_untouched_by_showcase_toggle(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $deck = FlashcardDeck::create(['titre' => 'D', 'is_public' => true]);

        $this->actingAs($admin)
            ->patchJson("/cores/showcase/deck/{$deck->id}", ['is_showcase' => true])
            ->assertOk();

        $deck->refresh();
        $this->assertTrue($deck->is_showcase);
        $this->assertTrue($deck->is_public);

        $this->actingAs($admin)
            ->patchJson("/cores/showcase/deck/{$deck->id}", ['is_showcase' => false])
            ->assertOk();
        $this->assertTrue($deck->fresh()->is_public);
    }

    public function test_admin_opens_group_for_requests(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $group = Group::create(['name' => 'Promo A', 'is_active' => true]);

        $this->actingAs($admin)
            ->patchJson("/cores/groups/{$group->id}/open-for-requests", ['open_for_requests' => true])
            ->assertOk()
            ->assertJson(['success' => true, 'open_for_requests' => true]);
        $this->assertTrue($group->fresh()->open_for_requests);

        $trainer = $this->makeUser('prof', 'trainer');
        $this->actingAs($trainer)
            ->patchJson("/cores/groups/{$group->id}/open-for-requests", ['open_for_requests' => false])
            ->assertForbidden();
        $this->assertTrue($group->fresh()->open_for_requests);
    }
}
