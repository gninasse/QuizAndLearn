<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\Article;
use Modules\Core\Models\Badge;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;
use Tests\TestCase;

class LearnerApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Learner $learner;

    protected Group $group;

    protected Quiz $quiz;

    protected Article $article;

    protected FlashcardDeck $deck;

    protected FlashcardItem $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Api',
            'last_name' => 'Learner',
            'user_name' => 'apilearner',
            'email' => 'apilearner@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->learner = Learner::create([
            'user_id' => $this->user->id,
            'matricule' => 'MAT-API',
        ]);

        $this->group = Group::create([
            'name' => 'API Group',
            'is_active' => true,
        ]);
        $this->learner->groups()->attach($this->group->id);

        $this->quiz = Quiz::create([
            'title' => 'API Quiz',
            'description' => 'Quiz for API v1 tests',
            'duration' => 10,
            'passing_score' => 60,
            'is_active' => true,
            'max_attempts' => 3,
            'created_by' => $this->user->id,
        ]);
        $this->quiz->groups()->attach($this->group->id);

        Question::create([
            'quiz_id' => $this->quiz->id,
            'question_text' => 'Vrai ou faux ?',
            'type' => 'true_false',
            'points' => 4,
            'order' => 1,
            'options' => ['correct_answer' => 'true'],
        ]);

        $this->article = Article::create([
            'title' => 'API Article',
            'content' => 'Contenu de test',
            'is_active' => true,
            'estimated_reading_time' => 5,
            'created_by' => $this->user->id,
        ]);
        $this->article->groups()->attach($this->group->id);

        $this->deck = FlashcardDeck::create([
            'titre' => 'Deck API',
            'description' => 'Deck de test',
            'algorithme' => 'sm2',
            'easiness_default' => 2.5,
            'interval_min' => 1,
            'interval_max' => 365,
            'active' => true,
            'is_public' => false,
            'created_by' => $this->user->id,
        ]);
        $this->deck->groups()->attach($this->group->id);

        $this->card = FlashcardItem::create([
            'deck_id' => $this->deck->id,
            'recto' => 'Question recto',
            'verso' => 'Réponse verso',
            'ordre' => 1,
        ]);

        Badge::create([
            'code' => 'first_quiz',
            'name' => 'Premier pas',
            'description' => 'Premier quiz complété',
            'icon' => '🚀',
            'condition_type' => 'quiz_completed',
            'condition_value' => ['count' => 1],
        ]);
    }

    // -------------------------------------------------------------- Session

    public function test_login_via_session_endpoint(): void
    {
        $response = $this->postJson(route('learn.v1.session.store'), [
            'login' => 'apilearner',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', 'apilearner@learnandquiz.fr')
            ->assertJsonStructure(['user' => ['xp' => ['total_xp', 'current_level']]]);
    }

    public function test_login_rejects_non_learner_account(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'last_name' => 'Only',
            'user_name' => 'staffonly',
            'email' => 'staff@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson(route('learn.v1.session.store'), [
            'login' => 'staffonly',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_me_returns_current_profile(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('learn.v1.me'))
            ->assertStatus(200)
            ->assertJsonPath('user.id', $this->user->id);
    }

    public function test_learner_can_change_password(): void
    {
        $response = $this->actingAs($this->user)->putJson(route('learn.v1.password.update'), [
            'current_password' => 'password123',
            'password' => 'nouveau-mdp-solide',
            'password_confirmation' => 'nouveau-mdp-solide',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nouveau-mdp-solide', $this->user->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $this->actingAs($this->user)->putJson(route('learn.v1.password.update'), [
            'current_password' => 'mauvais-mdp',
            'password' => 'nouveau-mdp-solide',
            'password_confirmation' => 'nouveau-mdp-solide',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_requires_confirmation_and_length(): void
    {
        $this->actingAs($this->user)->putJson(route('learn.v1.password.update'), [
            'current_password' => 'password123',
            'password' => 'court',
            'password_confirmation' => 'autre',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs($this->user)
            ->deleteJson(route('learn.v1.session.destroy'))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGuest();
    }

    // ------------------------------------------------------------ Bootstrap

    public function test_bootstrap_returns_scoped_collections_and_cursor(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('learn.v1.bootstrap'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cursor', 'user', 'articles', 'quizzes', 'decks', 'exams', 'badges', 'preferences',
            ])
            ->assertJsonPath('articles.0.title', 'API Article')
            ->assertJsonPath('quizzes.0.title', 'API Quiz')
            ->assertJsonPath('decks.0.titre', 'Deck API')
            ->assertJsonPath('decks.0.cards.0.recto', 'Question recto')
            ->assertJsonPath('decks.0.cards.0.review', null);
    }

    public function test_bootstrap_excludes_content_from_other_groups(): void
    {
        $otherGroup = Group::create(['name' => 'Autre groupe', 'is_active' => true]);
        $otherQuiz = Quiz::create([
            'title' => 'Quiz étranger',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        $otherQuiz->groups()->attach($otherGroup->id);

        $this->actingAs($this->user)
            ->getJson(route('learn.v1.bootstrap'))
            ->assertStatus(200)
            ->assertJsonMissing(['title' => 'Quiz étranger']);
    }

    // -------------------------------------------------------------- Changes

    public function test_changes_returns_delta_and_authorized_ids(): void
    {
        $cursor = now()->toIso8601String();

        $this->travel(2)->minutes();
        $this->article->update(['title' => 'API Article modifié']);

        $response = $this->actingAs($this->user)
            ->getJson(route('learn.v1.changes', ['since' => $cursor]));

        $response->assertStatus(200)
            ->assertJsonPath('articles.updated.0.title', 'API Article modifié')
            ->assertJsonPath('quizzes.updated', [])
            ->assertJsonPath('quizzes.authorized_ids.0', $this->quiz->id)
            ->assertJsonStructure(['cursor', 'xp', 'badges']);
    }

    public function test_changes_authorized_ids_shrink_after_unassignment(): void
    {
        $cursor = now()->toIso8601String();
        $this->quiz->groups()->detach($this->group->id);

        $response = $this->actingAs($this->user)
            ->getJson(route('learn.v1.changes', ['since' => $cursor]));

        $response->assertStatus(200)
            ->assertJsonPath('quizzes.authorized_ids', []);
    }

    // ----------------------------------------------------------- Leaderboard

    public function test_leaderboard_ranks_peers_by_xp(): void
    {
        $peerUser = User::create([
            'name' => 'Peer', 'last_name' => 'Learner', 'user_name' => 'peerlearner',
            'email' => 'peer@learnandquiz.fr', 'password' => bcrypt('password123'), 'is_active' => true,
        ]);
        $peer = Learner::create(['user_id' => $peerUser->id, 'matricule' => 'MAT-PEER']);
        $peer->groups()->attach($this->group->id);
        $peer->xp()->create(['total_xp' => 500, 'current_level' => 6, 'current_streak' => 4, 'longest_streak' => 4]);
        $this->learner->xp()->create(['total_xp' => 120, 'current_level' => 2, 'current_streak' => 1, 'longest_streak' => 2]);

        $response = $this->actingAs($this->user)->getJson(route('learn.v1.leaderboard'));

        $response->assertStatus(200)
            ->assertJsonPath('groups.0.group_name', 'API Group')
            ->assertJsonPath('groups.0.total_participants', 2)
            ->assertJsonPath('groups.0.my_rank', 2)
            ->assertJsonPath('groups.0.rows.0.rank', 1)
            ->assertJsonPath('groups.0.rows.0.total_xp', 500)
            ->assertJsonPath('groups.0.rows.0.is_me', false)
            ->assertJsonPath('groups.0.rows.1.is_me', true);
    }

    // --------------------------------------------------------- Badges étendus

    public function test_streak_and_perfect_badges_unlock(): void
    {
        Badge::create([
            'code' => 'perfectionist', 'name' => 'Perfectionniste', 'description' => '100 %',
            'icon' => '💯', 'condition_type' => 'quiz_perfect', 'condition_value' => ['count' => 1],
        ]);
        Badge::create([
            'code' => 'streak_3', 'name' => 'Assidu', 'description' => '3 jours',
            'icon' => '🔥', 'condition_type' => 'streak', 'condition_value' => ['count' => 3],
        ]);
        // Actif hier avec une série de 2 : l'action du jour porte la série à 3.
        $this->learner->xp()->create([
            'total_xp' => 0, 'current_level' => 1, 'current_streak' => 2,
            'longest_streak' => 2, 'last_activity_date' => now()->subDay()->toDateString(),
        ]);

        // Tentative parfaite (score 100) via l'API actions.
        $response = $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [[
                'id' => (string) Str::uuid(),
                'type' => 'quiz_attempt',
                'data' => [
                    'quiz_id' => $this->quiz->id,
                    'answers' => [$this->quiz->questions->first()->id => 'true'],
                ],
            ]],
        ]);

        $unlocked = $response->json('badges_unlocked');
        $this->assertContains('Perfectionniste', $unlocked);
        $this->assertContains('Assidu', $unlocked);
    }

    // -------------------------------------------------------------- Actions

    public function test_article_progress_action_awards_xp_once(): void
    {
        $payload = fn (string $id) => [
            'actions' => [[
                'id' => $id,
                'type' => 'article_progress',
                'data' => [
                    'article_id' => $this->article->id,
                    'progress_percentage' => 100,
                    'status' => 'completed',
                ],
            ]],
        ];

        $first = $this->actingAs($this->user)
            ->postJson(route('learn.v1.actions'), $payload((string) Str::uuid()));

        $first->assertStatus(200)
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.0.result.xp_earned', 15)
            ->assertJsonPath('xp.total_xp', 15);

        // Une seconde complétion du même article ne redonne pas d'XP.
        $second = $this->actingAs($this->user)
            ->postJson(route('learn.v1.actions'), $payload((string) Str::uuid()));

        $second->assertJsonPath('results.0.result.xp_earned', 0)
            ->assertJsonPath('xp.total_xp', 15);
    }

    public function test_duplicate_action_is_not_replayed(): void
    {
        $actionId = (string) Str::uuid();
        $payload = [
            'actions' => [[
                'id' => $actionId,
                'type' => 'article_progress',
                'data' => [
                    'article_id' => $this->article->id,
                    'progress_percentage' => 100,
                    'status' => 'completed',
                ],
            ]],
        ];

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), $payload)
            ->assertJsonPath('results.0.status', 'applied');

        $replay = $this->actingAs($this->user)->postJson(route('learn.v1.actions'), $payload);

        $replay->assertStatus(200)
            ->assertJsonPath('results.0.status', 'duplicate')
            ->assertJsonPath('xp.total_xp', 15); // pas de double XP
    }

    public function test_quiz_attempt_action_scores_and_unlocks_badge(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [[
                'id' => (string) Str::uuid(),
                'type' => 'quiz_attempt',
                'data' => [
                    'quiz_id' => $this->quiz->id,
                    'answers' => [$this->quiz->questions->first()->id => 'true'],
                    'started_at' => now()->subMinutes(5)->toDateTimeString(),
                    'completed_at' => now()->toDateTimeString(),
                ],
            ]],
        ]);

        // 20 base + 30 réussite + 4 points × 5 = 70 XP
        $response->assertStatus(200)
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.0.result.score', 100)
            ->assertJsonPath('results.0.result.passed', true)
            ->assertJsonPath('results.0.result.xp_earned', 70)
            ->assertJsonPath('badges_unlocked.0', 'Premier pas');

        $this->assertDatabaseHas('quiz_attempts', [
            'learner_id' => $this->learner->id,
            'quiz_id' => $this->quiz->id,
            'passed' => true,
        ]);

        // La série démarre à 1 après une activité d'entraînement.
        $this->assertDatabaseHas('learner_xp', [
            'learner_id' => $this->learner->id,
            'current_streak' => 1,
        ]);
    }

    public function test_rejected_action_does_not_block_the_batch(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'type_inconnu',
                    'data' => [],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'article_favorite',
                    'data' => ['article_id' => $this->article->id, 'is_favorite' => true],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.1.status', 'applied');

        $this->assertDatabaseHas('learner_progress', [
            'learner_id' => $this->learner->id,
            'content_type' => 'article',
            'content_id' => $this->article->id,
            'is_favorite' => true,
        ]);
    }

    public function test_card_review_writes_sm2_state_to_deck_system(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [[
                'id' => (string) Str::uuid(),
                'type' => 'card_review',
                'data' => ['card_id' => $this->card->id, 'quality' => 5],
            ]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.0.result.interval_days', 1)
            ->assertJsonPath('results.0.result.repetitions', 1)
            ->assertJsonPath('results.0.result.status', 'learning')
            ->assertJsonPath('xp.total_xp', 5);

        $this->assertDatabaseHas('flashcard_item_reviews', [
            'flashcard_item_id' => $this->card->id,
            'learner_id' => $this->learner->id,
            'repetitions' => 1,
            'interval_days' => 1,
        ]);

        $this->assertDatabaseHas('flashcard_items', [
            'id' => $this->card->id,
            'total_revisions' => 1,
        ]);
    }

    public function test_card_review_rejected_for_unassigned_deck(): void
    {
        $foreignDeck = FlashcardDeck::create([
            'titre' => 'Deck étranger',
            'algorithme' => 'sm2',
            'active' => true,
            'is_public' => false,
            'created_by' => $this->user->id,
        ]);
        $foreignCard = FlashcardItem::create([
            'deck_id' => $foreignDeck->id,
            'recto' => 'R',
            'verso' => 'V',
        ]);

        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [[
                'id' => (string) Str::uuid(),
                'type' => 'card_review',
                'data' => ['card_id' => $foreignCard->id, 'quality' => 5],
            ]],
        ])->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_preferences_update_action(): void
    {
        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [[
                'id' => (string) Str::uuid(),
                'type' => 'preferences_update',
                'data' => ['theme' => 'dark', 'font_size' => 'large'],
            ]],
        ])->assertJsonPath('results.0.status', 'applied');

        $this->assertDatabaseHas('learner_preferences', [
            'learner_id' => $this->learner->id,
            'theme' => 'dark',
            'font_size' => 'large',
        ]);
    }

    public function test_actions_require_learner_profile(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'last_name' => 'NoLearner',
            'user_name' => 'staffnolearner',
            'email' => 'staff2@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->postJson(route('learn.v1.actions'), ['actions' => []])
            ->assertStatus(403);
    }
}
