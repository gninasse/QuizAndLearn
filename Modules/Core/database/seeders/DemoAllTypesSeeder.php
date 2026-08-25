<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamQuestion;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;

/**
 * Jeu de démonstration couvrant TOUS les types de questions du volet
 * apprenant — un quiz (8 variantes) et un examen (6 types au format examen).
 *
 * Idempotent (updateOrCreate par titre), assigné à tous les groupes actifs.
 * Non appelé par CoreDatabaseSeeder : à lancer explicitement via
 *   php artisan db:seed --class="Modules\Core\Database\Seeders\DemoAllTypesSeeder"
 */
class DemoAllTypesSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::role('trainer')->first() ?? User::first();
        $groupIds = Group::where('is_active', true)->pluck('id');

        $this->seedQuiz($creator?->id, $groupIds);
        $this->seedExam($creator?->id, $groupIds);
    }

    private function seedQuiz(?int $creatorId, $groupIds): void
    {
        $quiz = Quiz::updateOrCreate(
            ['title' => 'Démonstration — Tous les types de questions'],
            [
                'description' => 'Quiz de démonstration couvrant chaque type de question et ses variantes : vrai/faux, choix unique, choix multiples (strict et partiel), texte à trous, associations, remise en ordre et réponse libre.',
                'duration' => 15,
                'passing_score' => 60,
                'is_active' => true,
                'max_attempts' => 10,
                'show_correct_answers' => true,
                'allow_partial_score' => true,
                'shuffle_questions' => false,
                'created_by' => $creatorId,
            ]
        );

        $quiz->questions()->delete();

        $questions = [
            [
                'type' => 'true_false',
                'question_text' => '<p>Le Web et Internet désignent exactement la même chose.</p>',
                'points' => 2,
                'options' => ['correct_answer' => 'false'],
            ],
            [
                'type' => 'mcq',
                'question_text' => '<p>Quel langage s\'exécute nativement dans le navigateur ?</p>',
                'points' => 2,
                'options' => [
                    'answers' => [
                        ['text' => 'PHP', 'is_correct' => false],
                        ['text' => 'JavaScript', 'is_correct' => true],
                        ['text' => 'Python', 'is_correct' => false],
                        ['text' => 'Java', 'is_correct' => false],
                    ],
                ],
            ],
            [
                'type' => 'mcq',
                'question_text' => '<p>Lesquelles de ces balises HTML sont <strong>sémantiques</strong> ? <em>(tout ou rien)</em></p>',
                'points' => 3,
                'options' => [
                    'multiple' => true,
                    'answers' => [
                        ['text' => '<article>', 'is_correct' => true],
                        ['text' => '<div>', 'is_correct' => false],
                        ['text' => '<nav>', 'is_correct' => true],
                        ['text' => '<span>', 'is_correct' => false],
                    ],
                ],
            ],
            [
                'type' => 'mcq',
                'question_text' => '<p>Quelles méthodes HTTP sont idempotentes ? <em>(score partiel : chaque bonne réponse compte, une erreur annule tout)</em></p>',
                'points' => 4,
                'options' => [
                    'multiple' => true,
                    'partial_score' => true,
                    'answers' => [
                        ['text' => 'GET', 'is_correct' => true],
                        ['text' => 'PUT', 'is_correct' => true],
                        ['text' => 'DELETE', 'is_correct' => true],
                        ['text' => 'POST', 'is_correct' => false],
                    ],
                ],
            ],
            [
                'type' => 'fill_blank',
                'question_text' => '<p>Complétez : la structure d\'une page est décrite en <strong>trou 1</strong>, sa présentation en <strong>trou 2</strong> (attention à la casse pour le trou 2 : sigle en majuscules).</p>',
                'points' => 4,
                'options' => [
                    'blanks' => [
                        ['answers' => ['HTML', 'html'], 'case_sensitive' => false],
                        ['answers' => ['CSS'], 'case_sensitive' => true],
                    ],
                ],
            ],
            [
                'type' => 'matching',
                'question_text' => '<p>Associez chaque code de statut HTTP à sa signification :</p>',
                'points' => 3,
                'options' => [
                    'pairs' => [
                        ['term' => '200', 'definition' => 'Succès'],
                        ['term' => '404', 'definition' => 'Ressource introuvable'],
                        ['term' => '500', 'definition' => 'Erreur serveur'],
                    ],
                ],
            ],
            [
                'type' => 'ordering',
                'question_text' => '<p>Remettez les étapes d\'une requête web dans l\'ordre chronologique :</p>',
                'points' => 4,
                'options' => [
                    'items' => [
                        'Résolution DNS',
                        'Connexion TCP',
                        'Requête HTTP',
                        'Rendu de la page',
                    ],
                ],
            ],
            [
                'type' => 'open_text',
                'question_text' => '<p>Expliquez en quelques phrases la différence entre authentification et autorisation.</p>',
                'points' => 2,
                'options' => [],
            ],
        ];

        foreach ($questions as $order => $data) {
            Question::create([
                'quiz_id' => $quiz->id,
                'question_text' => $data['question_text'],
                'type' => $data['type'],
                'points' => $data['points'],
                'order' => $order + 1,
                'options' => $data['options'],
            ]);
        }

        $quiz->groups()->sync($groupIds);
        $this->command?->info("Quiz démo créé : {$quiz->title} ({$quiz->questions()->count()} questions)");
    }

    private function seedExam(?int $creatorId, $groupIds): void
    {
        $exam = Exam::updateOrCreate(
            ['title' => 'Démonstration — Examen tous types'],
            [
                'description' => 'Examen de démonstration couvrant les six types de questions au format examen, avec points négatifs sur les QCM.',
                'duration' => 20,
                'passing_score' => 50,
                'is_active' => true,
                'max_attempts' => 10,
                // Sécurité allégée : cet examen sert à tester l'interface.
                'plein_ecran_force' => false,
                'anti_capture_strict' => false,
                'navigation_interdite' => false,
                'publication_resultats' => 'immediate',
                'classement_visible' => true,
                'classement_anonyme' => false,
                'note_max' => 20,
                'created_by' => $creatorId,
            ]
        );

        $exam->questions()->delete();

        $questions = [
            [
                'type' => 'true_false',
                'question_text' => '<p>Une clé étrangère peut référencer une colonne non unique.</p>',
                'points' => 3,
                'points_negatifs' => 0,
                'options' => ['correct_answer' => 'false'],
            ],
            [
                'type' => 'mcq',
                'question_text' => '<p>Quelles propriétés garantit une transaction ACID ? <em>(mauvaise réponse : -1 point)</em></p>',
                'points' => 4,
                'points_negatifs' => 1,
                'options' => [
                    'choices' => [
                        ['text' => 'Atomicité', 'is_correct' => true],
                        ['text' => 'Cohérence', 'is_correct' => true],
                        ['text' => 'Isolation', 'is_correct' => true],
                        ['text' => 'Redondance', 'is_correct' => false],
                    ],
                ],
            ],
            [
                'type' => 'fill_blank',
                'question_text' => '<p>Complétez la phrase ci-dessous :</p>',
                'points' => 3,
                'points_negatifs' => 0,
                'options' => [
                    'format' => 'Le protocole [[HTTPS|https]] chiffre les échanges entre le navigateur et le serveur.',
                ],
            ],
            [
                'type' => 'matching',
                'question_text' => '<p>Associez chaque commande SQL à son rôle :</p>',
                'points' => 4,
                'points_negatifs' => 1,
                'options' => [
                    'pairs' => [
                        ['left' => 'SELECT', 'right' => 'Lire des données'],
                        ['left' => 'INSERT', 'right' => 'Ajouter une ligne'],
                        ['left' => 'UPDATE', 'right' => 'Modifier des lignes'],
                    ],
                ],
            ],
            [
                'type' => 'ordering',
                'question_text' => '<p>Ordonnez les phases d\'un déploiement continu :</p>',
                'points' => 4,
                'points_negatifs' => 0,
                'options' => [
                    'items' => ['Commit', 'Build', 'Tests', 'Déploiement'],
                ],
            ],
            [
                'type' => 'open_text',
                'question_text' => '<p>Décrivez brièvement l\'intérêt d\'un index en base de données.</p>',
                'points' => 2,
                'points_negatifs' => 0,
                'options' => [],
            ],
        ];

        foreach ($questions as $order => $data) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_text' => $data['question_text'],
                'type' => $data['type'],
                'points' => $data['points'],
                'points_negatifs' => $data['points_negatifs'],
                'order' => $order + 1,
                'options' => $data['options'],
            ]);
        }

        $exam->groups()->sync($groupIds);
        $this->command?->info("Examen démo créé : {$exam->title} ({$exam->questions()->count()} questions)");
    }
}
