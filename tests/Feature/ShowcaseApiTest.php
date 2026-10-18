<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\QuizAttempt;
use Modules\Core\Models\User;
use Modules\Core\Services\GuestToken;
use Tests\TestCase;

/**
 * API publique du mode découverte (lecture seule) et jeton invité.
 */
class ShowcaseApiTest extends TestCase
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

        // Même fixture que LearnerApiV1Test.
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
    }

    private function enable(bool $on = true): void
    {
        AppSetting::put('showcase.enabled', $on);
    }

    private function makeQuiz(string $title, bool $showcase, bool $active): Quiz
    {
        return Quiz::create([
            'title' => $title,
            'is_active' => $active,
            'is_showcase' => $showcase,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Donne à l'apprenant une tentative, une progression et une révision,
     * pour que les champs propres à l'apprenant ne soient pas neutres.
     */
    private function giveLearnerActivity(): void
    {
        $this->actingAs($this->user)->postJson(route('learn.v1.actions'), [
            'actions' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'quiz_attempt',
                    'data' => [
                        'quiz_id' => $this->quiz->id,
                        'answers' => [$this->quiz->questions->first()->id => 'true'],
                        'started_at' => now()->subMinutes(5)->toDateTimeString(),
                        'completed_at' => now()->toDateTimeString(),
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'article_progress',
                    'data' => ['article_id' => $this->article->id, 'progress_percentage' => 100, 'status' => 'completed'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'card_review',
                    'data' => ['card_id' => $this->card->id, 'quality' => 5],
                ],
            ],
        ])->assertStatus(200);

        // Repartir en invité : l'API vitrine est publique.
        auth()->logout();
        $this->flushSession();
    }

    // ------------------------------------------------------------- Content

    public function test_returns_only_checked_active_content(): void
    {
        $this->enable();
        $this->makeQuiz('Quiz coché', true, true);
        $this->makeQuiz('Quiz décoché', false, true);
        $this->makeQuiz('Quiz coché inactif', true, false);
        $this->article->update(['is_showcase' => true]);
        Article::create(['title' => 'Article inactif', 'content' => 'x', 'is_active' => false, 'is_showcase' => true, 'created_by' => $this->user->id]);
        $this->deck->update(['is_showcase' => true]);
        FlashcardDeck::create(['titre' => 'Deck inactif', 'algorithme' => 'sm2', 'active' => false, 'is_showcase' => true, 'created_by' => $this->user->id]);

        $response = $this->getJson(route('showcase.v1.content'));

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame(['Quiz coché'], $response->json('quizzes.*.title'));
        $this->assertSame(['API Article'], $response->json('articles.*.title'));
        $this->assertSame(['Deck API'], $response->json('decks.*.titre'));
    }

    public function test_never_returns_exams_or_learner_fields(): void
    {
        $this->enable();
        $this->quiz->update(['is_showcase' => true]);
        $this->article->update(['is_showcase' => true]);
        $this->deck->update(['is_showcase' => true]);
        Exam::create(['title' => 'Examen secret', 'duration' => 30, 'is_active' => true, 'created_by' => $this->user->id]);
        $this->giveLearnerActivity();
        $this->assertSame(1, QuizAttempt::where('quiz_id', $this->quiz->id)->count());

        $response = $this->getJson(route('showcase.v1.content'))->assertStatus(200);
        $json = $response->json();

        $this->assertArrayNotHasKey('exams', $json);
        $this->assertStringNotContainsString('Examen secret', $response->getContent());
        $this->assertSame([], $response->json('quizzes.0.attempts'));
        $this->assertSame('unread', $response->json('quizzes.0.status'));
        $this->assertFalse($response->json('quizzes.0.max_attempts_reached'));
        $this->assertSame('unread', $response->json('articles.0.status'));
        $this->assertFalse($response->json('articles.0.is_favorite'));
        $this->assertSame(0, $response->json('articles.0.rating'));
        $this->assertSame(0, $response->json('articles.0.progress_percentage'));
        $this->assertNull($response->json('decks.0.cards.0.review'));
    }

    public function test_issues_guest_token_cookie_and_json(): void
    {
        $this->enable();
        $this->freezeSecond();

        $response = $this->getJson(route('showcase.v1.content'))->assertStatus(200);

        $token = $response->json('guest_token');
        $this->assertIsString($token);
        $this->assertTrue(GuestToken::valid($token));
        $this->assertSame(now()->addDay()->toIso8601String(), $response->json('expires_at'));

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'lq_guest');
        $this->assertNotNull($cookie);
        $this->assertSame($token, $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertSame(now()->addDay()->getTimestamp(), $cookie->getExpiresTime());

        // Le cookie n'est pas rechiffré par EncryptCookies.
        $this->assertSame(['exp' => now()->addDay()->getTimestamp()], json_decode(\Illuminate\Support\Facades\Crypt::decryptString($cookie->getValue()), true));
    }

    public function test_guest_token_validity(): void
    {
        $this->assertFalse(GuestToken::valid(null));
        $this->assertFalse(GuestToken::valid(''));
        $this->assertFalse(GuestToken::valid('pas-un-jeton'));

        $token = GuestToken::issue();
        $this->assertTrue(GuestToken::valid($token));

        $this->travel(25)->hours();
        $this->assertFalse(GuestToken::valid($token));
    }

    public function test_disabled_mode_returns_404(): void
    {
        $this->quiz->update(['is_showcase' => true]);

        $this->getJson(route('showcase.v1.content'))
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Le mode découverte est désactivé.']);

        $this->getJson(route('showcase.v1.settings'))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('enabled', false);
    }

    // ------------------------------------------------------------ Settings

    public function test_settings_payload_lists_open_groups_only(): void
    {
        $this->enable();
        AppSetting::put('showcase.notification_emails', ['secret@example.com']);
        $open = Group::create(['name' => 'Session ouverte', 'is_active' => true, 'open_for_requests' => true, 'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()]);
        $openNoEnd = Group::create(['name' => 'Ouverte sans fin', 'is_active' => true, 'open_for_requests' => true]);
        Group::create(['name' => 'Fermée', 'is_active' => true, 'open_for_requests' => false]);
        Group::create(['name' => 'Terminée', 'is_active' => true, 'open_for_requests' => true, 'end_date' => now()->subDay()->toDateString()]);
        Group::create(['name' => 'Inactive', 'is_active' => false, 'open_for_requests' => true]);

        $response = $this->getJson(route('showcase.v1.settings'));

        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('privacy_url', '/confidentialite')
            ->assertJsonPath('default_country_code', '225')
            ->assertJsonPath('registration.currency', 'XOF')
            ->assertJsonStructure([
                'success', 'enabled',
                'brand' => ['name', 'tagline', 'logo'],
                'registration' => ['fee', 'currency', 'accounts', 'reference', 'instructions'],
                'default_country_code', 'privacy_url',
                'groups' => [['id', 'name', 'start_date', 'end_date']],
            ]);

        $groups = collect($response->json('groups'));
        $this->assertEqualsCanonicalizing([$open->id, $openNoEnd->id], $groups->pluck('id')->all());
        $this->assertSame(['id', 'name', 'start_date', 'end_date'], array_keys($groups->firstWhere('id', $open->id)));
        $this->assertSame($open->end_date->toDateString(), $groups->firstWhere('id', $open->id)['end_date']);
        $this->assertStringNotContainsString('secret@example.com', $response->getContent());
        $this->assertStringNotContainsString('retention', $response->getContent());
    }

    // ------------------------------------------------- Learner non-regression

    public function test_learner_bootstrap_json_unchanged(): void
    {
        $this->giveLearnerActivity();

        $json = $this->actingAs($this->user)
            ->getJson(route('learn.v1.bootstrap'))
            ->assertStatus(200)
            ->json();

        $this->assertSame(['success', 'cursor', 'user', 'groups', 'articles', 'quizzes', 'decks', 'exams', 'badges', 'preferences'], array_keys($json));

        // Instantané pris avant l'extraction de ContentSerializer : clés,
        // ordre et valeurs identiques (dates normalisées).
        $actual = json_encode(
            ['articles' => $json['articles'], 'quizzes' => $json['quizzes'], 'decks' => $json['decks']],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $actual = preg_replace('/"\d{4}-\d\d-\d\dT[\d:]+\+00:00"/', '"<date>"', $actual);

        $this->assertSame(self::BOOTSTRAP_SNAPSHOT, $actual);
    }

    private const BOOTSTRAP_SNAPSHOT = <<<'JSON'
{
    "articles": [
        {
            "id": 1,
            "title": "API Article",
            "category": null,
            "content": "Contenu de test",
            "estimated_reading_time": 5,
            "is_favorite": false,
            "rating": 0,
            "status": "completed",
            "progress_percentage": 100,
            "created_at": "<date>",
            "updated_at": "<date>"
        }
    ],
    "quizzes": [
        {
            "id": 1,
            "title": "API Quiz",
            "description": "Quiz for API v1 tests",
            "duration": 10,
            "passing_score": 60,
            "max_attempts": 3,
            "shuffle_questions": false,
            "show_correct_answers": true,
            "status": "completed",
            "max_attempts_reached": false,
            "updated_at": "<date>",
            "attempts": [
                {
                    "id": 1,
                    "attempt_number": 1,
                    "status": "completed",
                    "score": 100,
                    "points_earned": 4,
                    "points_total": 4,
                    "passed": true,
                    "completed_at": "<date>"
                }
            ],
            "questions": [
                {
                    "id": 1,
                    "question_text": "Vrai ou faux ?",
                    "type": "true_false",
                    "points": 4,
                    "order": 1,
                    "options": {
                        "correct_answer": "true"
                    }
                }
            ]
        }
    ],
    "decks": [
        {
            "id": 1,
            "titre": "Deck API",
            "description": "Deck de test",
            "matiere": null,
            "algorithme": "sm2",
            "easiness_default": 2.5,
            "interval_min": 1,
            "interval_max": 365,
            "updated_at": "<date>",
            "cards": [
                {
                    "id": 1,
                    "recto": "Question recto",
                    "verso": "Réponse verso",
                    "recto_media": null,
                    "verso_media": null,
                    "tags": null,
                    "ordre": 1,
                    "review": {
                        "easiness_factor": 2.6,
                        "interval_days": 1,
                        "repetitions": 1,
                        "last_reviewed": "<date>",
                        "next_review": "<date>",
                        "status": "learning"
                    }
                }
            ]
        }
    ]
}
JSON;
}
