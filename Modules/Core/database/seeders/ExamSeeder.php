<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Exam;
use Modules\Core\Models\ExamQuestion;
use Modules\Core\Models\Group;
use Modules\Core\Models\User;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les anciens examens pour repartir sur une base propre
        Exam::query()->delete();

        // Récupérer un formateur comme créateur
        $trainerUser = User::role('trainer')->first();
        if (! $trainerUser) {
            $trainerUser = User::first();
        }

        // Récupérer les groupes
        $groupUiUx = Group::where('name', 'Design UI/UX - Soirée')->first();
        $groupWeb = Group::where('name', 'Développement Web Fullstack')->first();
        $groupLaravel = Group::where('name', 'Introduction à Laravel 12')->first();

        // ==========================================
        // 1. Examen : Certification UI/UX & Design Web
        // ==========================================
        $examUi = Exam::create([
            'title' => 'Certification Officielle UI/UX Design',
            'description' => '<h3>Consignes de l\'examen de certification</h3><p>Cet examen est strictement minuté. Veillez à bien respecter les contraintes de plein écran.</p>',
            'duration' => 20,
            'passing_score' => 70,
            'is_active' => true,
            'max_attempts' => 1,
            'available_from' => now()->subDays(2),
            'available_until' => now()->addDays(30),
            'plein_ecran_force' => true,
            'anti_capture_strict' => true,
            'navigation_interdite' => true,
            'publication_resultats' => true,
            'classement_visible' => true,
            'classement_anonyme' => true,
            'note_max' => 20,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupUiUx) {
            $examUi->groups()->attach($groupUiUx->id);
        }

        // Q1: True / False
        ExamQuestion::create([
            'exam_id' => $examUi->id,
            'question_text' => '<p>La règle des 60-30-10 en UI Design conseille d\'allouer 60% de l\'espace à la couleur secondaire.</p>',
            'type' => 'true_false',
            'points' => 3,
            'points_negatifs' => 1,
            'order' => 1,
            'options' => ['correct_answer' => 'false'],
        ]);

        // Q2: MCQ Multiple Choice
        ExamQuestion::create([
            'exam_id' => $examUi->id,
            'question_text' => '<p>Quelles sont les caractéristiques d\'un bouton accessible (a11y) ? <em>(Plusieurs réponses possibles)</em></p>',
            'type' => 'mcq',
            'points' => 4,
            'points_negatifs' => 0,
            'order' => 2,
            'options' => [
                'multiple' => true,
                'partial_score' => true,
                'answers' => [
                    ['text' => 'Un contraste de couleur d\'au moins 4.5:1 avec le fond', 'is_correct' => true],
                    ['text' => 'Une taille de zone cliquable minimale de 44x44 pixels', 'is_correct' => true],
                    ['text' => 'Une bordure rouge obligatoire', 'is_correct' => false],
                    ['text' => 'Un attribut aria-label si le bouton ne contient qu\'une icône', 'is_correct' => true],
                ],
            ],
        ]);

        // Q3: Fill in the blank
        ExamQuestion::create([
            'exam_id' => $examUi->id,
            'question_text' => '<p>Définissez la loi de Fitts dans le contexte de la taille des cibles cliquables :</p>',
            'type' => 'fill_blank',
            'points' => 3,
            'points_negatifs' => 0,
            'order' => 3,
            'options' => [
                'format' => '<p>Plus la cible est [[proche]] et [[grande]], plus elle est facile à atteindre.</p>',
                'blanks' => [
                    ['answers' => ['proche', 'rapprochée'], 'case_sensitive' => false],
                    ['answers' => ['grande', 'large'], 'case_sensitive' => false],
                ],
            ],
        ]);

        // Q4: Matching
        ExamQuestion::create([
            'exam_id' => $examUi->id,
            'question_text' => '<p>Associez chaque loi ergonomique à son auteur ou concept :</p>',
            'type' => 'matching',
            'points' => 4,
            'points_negatifs' => 0,
            'order' => 4,
            'options' => [
                'pairs' => [
                    ['term' => 'Loi de Hick', 'definition' => 'Le temps de décision augmente avec le nombre d\'options.'],
                    ['term' => 'Loi de Fitts', 'definition' => 'Le temps requis pour atteindre une cible dépend de sa taille/distance.'],
                    ['term' => 'Loi de Jakob', 'definition' => 'Les utilisateurs passent leur temps sur d\'autres sites et attendent les mêmes conventions.'],
                    ['term' => 'Effet de von Restorff', 'definition' => 'Un élément qui se distingue des autres est plus susceptible d\'être mémorisé.'],
                ],
            ],
        ]);

        // Q5: Ordering
        ExamQuestion::create([
            'exam_id' => $examUi->id,
            'question_text' => '<p>Ordonnez les étapes classiques de la démarche Design Thinking :</p>',
            'type' => 'ordering',
            'points' => 3,
            'points_negatifs' => 0,
            'order' => 5,
            'options' => [
                'items' => [
                    'Empathie (Comprendre les utilisateurs)',
                    'Définition (Cadrer le problème)',
                    'Idéation (Brainstormer des solutions)',
                    'Prototypage (Créer des maquettes)',
                    'Test (Valider auprès des usagers)',
                ],
            ],
        ]);

        // Q6: Open Text
        ExamQuestion::create([
            'exam_id' => $examUi->id,
            'question_text' => '<p>Qu\'est-ce que le responsive design et pourquoi est-il crucial dans la conception web moderne ? Expliquez brièvement.</p>',
            'type' => 'open_text',
            'points' => 3,
            'points_negatifs' => 0,
            'order' => 6,
            'options' => [],
        ]);

        // ==========================================
        // 2. Examen : Examen Final Développement Web & Laravel
        // ==========================================
        $examWeb = Exam::create([
            'title' => 'Examen de Fin de Cursus - Développement Web & Laravel',
            'description' => '<h3>Validation des compétences Back-end</h3><p>Questions théoriques et pratiques sur le développement moderne avec Laravel 12.</p>',
            'duration' => 30,
            'passing_score' => 65,
            'is_active' => true,
            'max_attempts' => 1,
            'available_from' => now()->subDays(1),
            'available_until' => now()->addDays(60),
            'plein_ecran_force' => true,
            'anti_capture_strict' => false,
            'navigation_interdite' => true,
            'publication_resultats' => true,
            'classement_visible' => true,
            'classement_anonyme' => false,
            'note_max' => 20,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupWeb) {
            $examWeb->groups()->attach($groupWeb->id);
        }
        if ($groupLaravel) {
            $examWeb->groups()->attach($groupLaravel->id);
        }

        // Q1: MCQ Single Choice
        ExamQuestion::create([
            'exam_id' => $examWeb->id,
            'question_text' => '<p>Depuis quelle version de Laravel les fichiers de middleware ne sont plus stockés dans <code>app/Http/Middleware</code> par défaut, mais configurés dans <code>bootstrap/app.php</code> ?</p>',
            'type' => 'mcq',
            'points' => 4,
            'points_negatifs' => 1,
            'order' => 1,
            'options' => [
                'multiple' => false,
                'answers' => [
                    ['text' => 'Laravel 9', 'is_correct' => false],
                    ['text' => 'Laravel 10', 'is_correct' => false],
                    ['text' => 'Laravel 11', 'is_correct' => true],
                    ['text' => 'Laravel 8', 'is_correct' => false],
                ],
            ],
        ]);

        // Q2: Fill in the blank
        ExamQuestion::create([
            'exam_id' => $examWeb->id,
            'question_text' => '<p>Complétez l\'instruction de routage suivante dans un fichier web.php de Laravel 12 :</p>',
            'type' => 'fill_blank',
            'points' => 4,
            'points_negatifs' => 0,
            'order' => 2,
            'options' => [
                'format' => '<p>Route::[[get]](\'/users\', [UserController::class, \'[[index]]\']);</p>',
                'blanks' => [
                    ['answers' => ['get'], 'case_sensitive' => false],
                    ['answers' => ['index'], 'case_sensitive' => false],
                ],
            ],
        ]);

        // Q3: Matching
        ExamQuestion::create([
            'exam_id' => $examWeb->id,
            'question_text' => '<p>Associez chaque commande artisan de Laravel à sa description correspondante :</p>',
            'type' => 'matching',
            'points' => 4,
            'points_negatifs' => 0,
            'order' => 3,
            'options' => [
                'pairs' => [
                    ['term' => 'php artisan migrate', 'definition' => 'Exécute les migrations de base de données en attente.'],
                    ['term' => 'php artisan make:model', 'definition' => 'Crée un nouveau modèle Eloquent.'],
                    ['term' => 'php artisan route:list', 'definition' => 'Affiche la liste de toutes les routes enregistrées.'],
                    ['term' => 'php artisan make:controller', 'definition' => 'Génère un nouveau contrôleur HTTP.'],
                ],
            ],
        ]);

        // Q4: Open Text
        ExamQuestion::create([
            'exam_id' => $examWeb->id,
            'question_text' => '<p>Expliquez la différence entre le chargement hâtif (Eager Loading) et le chargement différé (Lazy Loading) dans Eloquent ORM, et comment cela résout le problème des requêtes N+1.</p>',
            'type' => 'open_text',
            'points' => 8,
            'points_negatifs' => 0,
            'order' => 4,
            'options' => [],
        ]);
    }
}
