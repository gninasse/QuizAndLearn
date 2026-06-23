<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les anciens quiz pour éviter les doublons
        Quiz::query()->delete();

        // Récupérer un formateur comme créateur
        $trainerUser = User::role('trainer')->first();
        if (! $trainerUser) {
            $trainerUser = User::first(); // Repli de secours
        }

        // Récupérer les groupes
        $groupUiUx = Group::where('name', 'Design UI/UX - Soirée')->first();
        $groupWeb = Group::where('name', 'Développement Web Fullstack')->first();
        $groupLaravel = Group::where('name', 'Introduction à Laravel 12')->first();
        $groupDevOps = Group::where('name', 'DevOps & Cloud Computing')->first();

        // ==========================================
        // 1. Quiz : UI/UX Design
        // ==========================================
        $quizUi = Quiz::create([
            'title' => 'Bases du Design UI/UX',
            'description' => 'Testez vos fondamentaux sur les principes de design d\'interface et d\'expérience utilisateur (Loi de Fitts, contrastes, hiérarchie).',
            'duration' => 10,
            'passing_score' => 60,
            'is_active' => true,
            'max_attempts' => 3,
            'show_correct_answers' => true,
            'allow_partial_score' => true,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupUiUx) {
            $quizUi->groups()->attach($groupUiUx->id);
        }

        // Questions UI/UX
        Question::create([
            'quiz_id' => $quizUi->id,
            'question_text' => 'Selon la loi de Fitts, plus une cible est proche et grande, plus elle est rapide à atteindre.',
            'type' => 'true_false',
            'points' => 2,
            'order' => 1,
            'options' => ['correct_answer' => 'true'],
        ]);

        Question::create([
            'quiz_id' => $quizUi->id,
            'question_text' => 'Quelles sont les trois couleurs primaires dans le modèle de synthèse additive (RVB) utilisé pour les écrans ?',
            'type' => 'mcq',
            'points' => 3,
            'order' => 2,
            'options' => [
                'multiple' => true,
                'partial_score' => true,
                'answers' => [
                    ['text' => 'Rouge', 'is_correct' => true],
                    ['text' => 'Jaune', 'is_correct' => false],
                    ['text' => 'Vert', 'is_correct' => true],
                    ['text' => 'Bleu', 'is_correct' => true],
                ],
            ],
        ]);

        Question::create([
            'quiz_id' => $quizUi->id,
            'question_text' => 'Quel principe de la Gestalt stipule que les éléments proches les uns des autres sont perçus comme faisant partie du même groupe ?',
            'type' => 'mcq',
            'points' => 2,
            'order' => 3,
            'options' => [
                'multiple' => false,
                'answers' => [
                    ['text' => 'Principe de Proximité', 'is_correct' => true],
                    ['text' => 'Principe de Similitude', 'is_correct' => false],
                    ['text' => 'Principe de Clôture', 'is_correct' => false],
                ],
            ],
        ]);

        // ==========================================
        // 2. Quiz : Javascript Avancé
        // ==========================================
        $quizJs = Quiz::create([
            'title' => 'Javascript Avancé & Asynchrone',
            'description' => 'Évaluation sur les promesses, l\'asynchrone, le scope et la portée des closures en Javascript.',
            'duration' => 15,
            'passing_score' => 60,
            'is_active' => true,
            'max_attempts' => 2,
            'show_correct_answers' => true,
            'allow_partial_score' => false,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupWeb) {
            $quizJs->groups()->attach($groupWeb->id);
        }

        // Questions JS
        Question::create([
            'quiz_id' => $quizJs->id,
            'question_text' => 'Quelle méthode de Promise s\'exécute et retourne dès que la première promesse du tableau est résolue ou rejetée ?',
            'type' => 'mcq',
            'points' => 2,
            'order' => 1,
            'options' => [
                'multiple' => false,
                'answers' => [
                    ['text' => 'Promise.race()', 'is_correct' => true],
                    ['text' => 'Promise.any()', 'is_correct' => false],
                    ['text' => 'Promise.all()', 'is_correct' => false],
                ],
            ],
        ]);

        Question::create([
            'quiz_id' => $quizJs->id,
            'question_text' => 'Le mot-clé const garantit que la valeur d\'un objet assigné ne peut jamais être modifiée (mutation).',
            'type' => 'true_false',
            'points' => 2,
            'order' => 2,
            'options' => ['correct_answer' => 'false'],
        ]);

        Question::create([
            'quiz_id' => $quizJs->id,
            'question_text' => 'Expliquez brièvement en vos propres termes ce qu\'est une "Closure" en Javascript.',
            'type' => 'open_text',
            'points' => 4,
            'order' => 3,
            'options' => [],
        ]);

        // ==========================================
        // 3. Quiz : Laravel 12
        // ==========================================
        $quizLaravel = Quiz::create([
            'title' => 'Laravel 12 - Routage & ORM',
            'description' => 'Testez vos compétences sur la configuration du routage moderne et Eloquent ORM dans Laravel 12.',
            'duration' => 12,
            'passing_score' => 70,
            'is_active' => true,
            'max_attempts' => 3,
            'show_correct_answers' => true,
            'allow_partial_score' => true,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupLaravel) {
            $quizLaravel->groups()->attach($groupLaravel->id);
        }
        if ($groupWeb) {
            $quizLaravel->groups()->attach($groupWeb->id);
        }

        // Questions Laravel
        Question::create([
            'quiz_id' => $quizLaravel->id,
            'question_text' => 'Depuis Laravel 11/12, le fichier bootstrap/app.php sert à configurer le routage et les middlewares.',
            'type' => 'true_false',
            'points' => 2,
            'order' => 1,
            'options' => ['correct_answer' => 'true'],
        ]);

        Question::create([
            'quiz_id' => $quizLaravel->id,
            'question_text' => 'Associez chaque relation Eloquent à sa définition correcte :',
            'type' => 'matching',
            'points' => 3,
            'order' => 2,
            'options' => [
                'pairs' => [
                    ['term' => 'hasMany', 'definition' => 'Relation de type Un-à-Plusieurs descendante'],
                    ['term' => 'belongsTo', 'definition' => 'Relation de type Un-à-Plusieurs remontante'],
                    ['term' => 'belongsToMany', 'definition' => 'Relation de type Plusieurs-à-Plusieurs'],
                ],
            ],
        ]);

        Question::create([
            'quiz_id' => $quizLaravel->id,
            'question_text' => 'Complétez le namespace par défaut des modèles Laravel :',
            'type' => 'fill_blank',
            'points' => 2,
            'order' => 3,
            'options' => [
                'blanks' => [
                    [
                        'answers' => ['App\\Models', 'App\Models'],
                        'case_sensitive' => false,
                    ],
                ],
            ],
        ]);

        // ==========================================
        // 4. Quiz : DevOps & Docker
        // ==========================================
        $quizDevOps = Quiz::create([
            'title' => 'Déploiement Docker & DevOps',
            'description' => 'Évaluation sur l\'écriture des Dockerfiles et l\'ordonnancement des builds DevOps.',
            'duration' => 8,
            'passing_score' => 60,
            'is_active' => true,
            'max_attempts' => 2,
            'show_correct_answers' => true,
            'allow_partial_score' => true,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupDevOps) {
            $quizDevOps->groups()->attach($groupDevOps->id);
        }

        // Questions DevOps
        Question::create([
            'quiz_id' => $quizDevOps->id,
            'question_text' => 'Ordonnez les étapes d\'exécution typiques d\'un Dockerfile :',
            'type' => 'ordering',
            'points' => 3,
            'order' => 1,
            'options' => [
                'items' => [
                    'FROM ubuntu:24.04',
                    'RUN apt-get update && apt-get install -y nginx',
                    'COPY index.html /var/www/html/',
                    'EXPOSE 80',
                    'CMD ["nginx", "-g", "daemon off;"]',
                ],
            ],
        ]);

        Question::create([
            'quiz_id' => $quizDevOps->id,
            'question_text' => 'Quelle commande permet de compiler une image Docker à partir du répertoire courant ?',
            'type' => 'mcq',
            'points' => 2,
            'order' => 2,
            'options' => [
                'multiple' => false,
                'answers' => [
                    ['text' => 'docker build -t mon-app .', 'is_correct' => true],
                    ['text' => 'docker run mon-app', 'is_correct' => false],
                    ['text' => 'docker create mon-app', 'is_correct' => false],
                ],
            ],
        ]);
    }
}
