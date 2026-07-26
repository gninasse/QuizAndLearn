<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlashcardDeckAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected FlashcardDeck $deck;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin',
            'last_name' => 'Test',
            'user_name' => 'admintest',
            'email' => 'admin@example.com',
            'phone' => '987654321',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        Role::findOrCreate('super-admin');
        $this->admin->assignRole('super-admin');

        // Create a group
        $this->group = Group::create([
            'name' => 'Test Group',
            'description' => 'Group for testing decks',
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        // Create a deck
        $this->deck = FlashcardDeck::create([
            'titre' => 'Test Deck',
            'description' => 'Test Deck Description',
            'matiere' => 'Vocabulaire',
            'algorithme' => 'sm2',
            'is_public' => false,
            'created_by' => $this->admin->id,
            'active' => true,
        ]);
    }

    public function test_admin_can_access_flashcard_decks_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('cores.flashcard-decks.index'));

        $response->assertStatus(200);
        $response->assertViewIs('core::flashcards.index');
    }

    public function test_admin_can_get_decks_data(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('cores.flashcard-decks.data'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total',
            'rows' => [
                '*' => [
                    'id',
                    'titre',
                    'description',
                    'matiere',
                    'algorithme',
                    'active',
                    'creator_name',
                    'cards_count',
                    'groups_list',
                ],
            ],
        ]);
    }

    public function test_admin_can_show_deck_details(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('cores.flashcard-decks.show', $this->deck->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $this->deck->id,
                'titre' => 'Test Deck',
                'description' => 'Test Deck Description',
                'matiere' => 'Vocabulaire',
                'algorithme' => 'sm2',
                'is_public' => false,
            ],
        ]);
    }

    public function test_admin_can_store_deck(): void
    {
        $data = [
            'titre' => 'New Deck',
            'description' => 'New Deck Description',
            'matiere' => 'Anatomie',
            'algorithme' => 'sm2',
            'is_public' => '1',
            'group_ids' => [$this->group->id],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('cores.flashcard-decks.store'), $data);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('flashcard_decks', [
            'titre' => 'New Deck',
            'matiere' => 'Anatomie',
            'is_public' => true,
        ]);
    }

    public function test_admin_can_update_deck(): void
    {
        $data = [
            'titre' => 'Updated Title',
            'description' => 'Updated Description',
            'matiere' => 'Updated Category',
            'algorithme' => 'leitner',
            'is_public' => '0',
            'group_ids' => [],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('cores.flashcard-decks.update', $this->deck->id), $data);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('flashcard_decks', [
            'id' => $this->deck->id,
            'titre' => 'Updated Title',
            'algorithme' => 'leitner',
            'is_public' => false,
        ]);
    }

    public function test_admin_can_toggle_deck_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cores.flashcard-decks.toggle-status', $this->deck->id));

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'active' => false]);

        $this->assertDatabaseHas('flashcard_decks', [
            'id' => $this->deck->id,
            'active' => false,
        ]);
    }

    public function test_admin_can_delete_deck(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('cores.flashcard-decks.destroy', $this->deck->id));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('flashcard_decks', [
            'id' => $this->deck->id,
        ]);
    }

    public function test_admin_can_manage_cards_in_deck(): void
    {
        // 1. Add card
        $cardData = [
            'recto' => 'Recto Front Text',
            'verso' => 'Verso Back Text',
            'tags' => 'tag1, tag2',
            'note' => 'A small hint',
            'ordre' => 1,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('cores.editor.flashcards.cards.store', $this->deck->id), $cardData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $card = FlashcardItem::where('deck_id', $this->deck->id)->first();
        $this->assertNotNull($card);
        $this->assertEquals('Recto Front Text', $card->recto);

        // 2. Show card
        $response = $this->actingAs($this->admin)
            ->get(route('cores.editor.flashcards.cards.show', [$this->deck->id, $card->id]));
        $response->assertStatus(200)->assertJson(['success' => true]);

        // 3. Update card
        $updateData = [
            'recto' => 'Updated Recto',
            'verso' => 'Updated Verso',
            'tags' => 'tag3',
            'note' => 'No hint',
            'ordre' => 2,
        ];
        $response = $this->actingAs($this->admin)
            ->put(route('cores.editor.flashcards.cards.update', [$this->deck->id, $card->id]), $updateData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('flashcard_items', [
            'id' => $card->id,
            'recto' => 'Updated Recto',
        ]);

        // 4. Delete card
        $response = $this->actingAs($this->admin)
            ->delete(route('cores.editor.flashcards.cards.destroy', [$this->deck->id, $card->id]));
        $response->assertStatus(200);

        $this->assertDatabaseMissing('flashcard_items', [
            'id' => $card->id,
        ]);
    }

    public function test_admin_can_auto_generate_cards_from_quiz(): void
    {
        $quiz = Quiz::create([
            'title' => 'Sample Quiz',
            'description' => 'Desc',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What is 2+2?',
            'type' => 'true_false',
            'points' => 5,
            'options' => ['correct_answer' => 'four'],
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('cores.editor.flashcards.generate', $this->deck->id), [
                'source_type' => 'quiz',
                'source_id' => $quiz->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('flashcard_items', [
            'deck_id' => $this->deck->id,
            'recto' => 'What is 2+2?',
        ]);
    }
}
