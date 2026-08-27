<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Question;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\User;

/**
 * Contenu de développement pour l'apprenant de test.
 *
 * Couvre TOUS les types de questions, leurs variantes (score partiel,
 * casse sensible, choix multiples), les cas limites de configuration
 * (sans chrono, tentative unique, sans corrections, mélange) ainsi que
 * les médias embarqués (images + audio) dans les trois surfaces :
 * énoncés de questions, articles et flashcards.
 *
 * Idempotent — relançable à volonté :
 *   php artisan db:seed --class="Modules\Core\Database\Seeders\DevLearnerContentSeeder"
 */
class DevLearnerContentSeeder extends Seeder
{
    /** Apprenant ciblé (surchargeable par la variable d'env DEV_LEARNER_EMAIL). */
    private const DEFAULT_EMAIL = 'a.lemaire@example.com';

    private const IMG = '/storage/demo';

    public function run(): void
    {
        $email = env('DEV_LEARNER_EMAIL', self::DEFAULT_EMAIL);

        $learner = Learner::whereHas('user', fn ($q) => $q->where('email', $email))->first();
        if (! $learner) {
            $this->command?->error("Apprenant introuvable : {$email}");

            return;
        }

        $groupIds = $learner->groups()->pluck('groups.id');
        if ($groupIds->isEmpty()) {
            $this->command?->error("L'apprenant {$email} n'appartient à aucun groupe.");

            return;
        }

        $creator = User::role('trainer')->first() ?? $learner->user;
        $this->command?->info("Cible : {$email} — groupes ".$groupIds->implode(', '));

        $this->generateMedia();
        $this->seedQuizzes($creator->id, $groupIds);
        $this->seedArticles($creator->id, $groupIds);
        $this->seedDecks($creator->id, $groupIds);
    }

    // ----------------------------------------------------------- Médias

    /**
     * Génère les illustrations et extraits audio référencés par le contenu.
     * Le dossier storage/app/public n'étant pas versionné, le seeder produit
     * lui-même ses médias : il reste autonome sur un dépôt fraîchement cloné.
     */
    private function generateMedia(): void
    {
        $dir = storage_path('app/public/demo');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! function_exists('imagecreatetruecolor')) {
            $this->command?->warn('  extension GD absente : illustrations non générées.');
        } else {
            $images = [
                ['loi-fitts.png', 'Loi de Fitts', 'Temps = f(distance, taille de la cible)', [15, 23, 42], [14, 165, 233]],
                ['contraste.png', 'Contraste WCAG', 'Ratio minimal 4.5:1 pour le texte', [30, 20, 50], [139, 92, 246]],
                ['grille.png', 'Grille 8pt', 'Espacements multiples de 8 pixels', [10, 40, 35], [16, 185, 129]],
                ['couleurs.png', 'Cercle chromatique', 'Complementaires et analogues', [50, 25, 10], [245, 158, 11]],
                ['typo.png', 'Hierarchie typographique', 'Titre / Sous-titre / Corps', [40, 15, 30], [236, 72, 153]],
            ];
            foreach ($images as [$file, $title, $subtitle, $bg, $accent]) {
                $path = "{$dir}/{$file}";
                if (! file_exists($path)) {
                    $this->makeImage($path, $title, $subtitle, $bg, $accent);
                }
            }
        }

        $audios = [
            ['exemple-audio.wav', [[523, 300], [659, 300], [784, 400], [659, 200], [523, 500]]],
            ['prononciation.wav', [[440, 250], [554, 250], [659, 500]]],
        ];
        foreach ($audios as [$file, $notes]) {
            $path = "{$dir}/{$file}";
            if (! file_exists($path)) {
                $this->makeWav($path, $notes);
            }
        }

        $this->command?->info('  médias : '.count(glob("{$dir}/*")).' fichiers dans storage/app/public/demo');
    }

    private function makeImage(string $path, string $title, string $subtitle, array $bg, array $accent): void
    {
        [$w, $h] = [800, 450];
        $img = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            imageline($img, 0, $y, $w, $y, imagecolorallocate(
                $img,
                (int) ($bg[0] + ($accent[0] - $bg[0]) * $t * 0.6),
                (int) ($bg[1] + ($accent[1] - $bg[1]) * $t * 0.6),
                (int) ($bg[2] + ($accent[2] - $bg[2]) * $t * 0.6),
            ));
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $acc = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
        imagefilledrectangle($img, 0, 0, $w, 8, $acc);
        imagestring($img, 5, 40, 180, $title, $white);
        imagestring($img, 3, 40, 215, $subtitle, $white);
        for ($i = 0; $i < 6; $i++) {
            imagefilledellipse($img, 640 + ($i % 3) * 50, 300 + intdiv($i, 3) * 50, 30, 30, $acc);
        }

        imagepng($img, $path, 9);
        imagedestroy($img);
    }

    /** WAV mono 8 kHz : quelques notes enveloppées, sans dépendance externe. */
    private function makeWav(string $path, array $notes): void
    {
        $rate = 8000;
        $data = '';

        foreach ($notes as [$freq, $ms]) {
            $samples = (int) ($rate * $ms / 1000);
            for ($i = 0; $i < $samples; $i++) {
                $envelope = min(1, $i / 200) * min(1, ($samples - $i) / 400);
                $value = (int) (127 + 90 * $envelope * sin(2 * M_PI * $freq * $i / $rate));
                $data .= chr(max(0, min(255, $value)));
            }
        }

        $header = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8)
            .'data'.pack('V', strlen($data));

        file_put_contents($path, $header.$data);
    }

    // ------------------------------------------------------------- Quiz

    private function seedQuizzes(int $creatorId, $groupIds): void
    {
        foreach ($this->quizDefinitions() as $definition) {
            $quiz = Quiz::updateOrCreate(
                ['title' => $definition['title']],
                array_merge([
                    'description' => $definition['description'],
                    'is_active' => true,
                    'created_by' => $creatorId,
                    'passing_score' => 60,
                    'max_attempts' => 10,
                    'duration' => 10,
                    'shuffle_questions' => false,
                    'show_correct_answers' => true,
                ], $definition['config'] ?? [])
            );

            $quiz->questions()->delete();
            foreach ($definition['questions'] as $order => $question) {
                Question::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => $question['text'],
                    'type' => $question['type'],
                    'points' => $question['points'],
                    'order' => $order + 1,
                    'options' => $question['options'],
                ]);
            }

            $quiz->groups()->syncWithoutDetaching($groupIds);
            $this->command?->info("  quiz : {$quiz->title} (".count($definition['questions']).' q)');
        }
    }

    private function quizDefinitions(): array
    {
        return [
            // 1. Média dans les énoncés (image + audio)
            [
                'title' => '[DEV] Questions avec images et audio',
                'description' => 'Vérifie le rendu des médias embarqués dans les énoncés : images redimensionnées et lecteur audio.',
                'config' => ['duration' => null], // sans limite de temps → affiche ∞
                'questions' => [
                    [
                        'type' => 'mcq',
                        'points' => 3,
                        'text' => '<p>Observez l\'illustration ci-dessous :</p>'
                            .'<p><img src="'.self::IMG.'/loi-fitts.png" alt="Loi de Fitts"></p>'
                            .'<p>Quelle loi ergonomique est représentée ?</p>',
                        'options' => ['answers' => [
                            ['text' => 'Loi de Fitts', 'is_correct' => true],
                            ['text' => 'Loi de Hick', 'is_correct' => false],
                            ['text' => 'Loi de Jakob', 'is_correct' => false],
                            ['text' => 'Loi de Miller', 'is_correct' => false],
                        ]],
                    ],
                    [
                        'type' => 'true_false',
                        'points' => 2,
                        'text' => '<p>Écoutez cet extrait sonore :</p>'
                            .'<p><audio controls src="'.self::IMG.'/exemple-audio.wav"></audio></p>'
                            .'<p>La mélodie se termine sur une note plus grave qu\'elle n\'a commencé.</p>',
                        'options' => ['correct_answer' => 'false'],
                    ],
                    [
                        'type' => 'mcq',
                        'points' => 3,
                        'text' => '<p><img src="'.self::IMG.'/contraste.png" alt="Contraste"></p>'
                            .'<p>Quel ratio de contraste minimal impose la norme WCAG AA pour du texte normal ?</p>',
                        'options' => ['answers' => [
                            ['text' => '3:1', 'is_correct' => false],
                            ['text' => '4.5:1', 'is_correct' => true],
                            ['text' => '7:1', 'is_correct' => false],
                        ]],
                    ],
                ],
            ],

            // 2. QCM : toutes les variantes
            [
                'title' => '[DEV] QCM — unique, multiple strict et score partiel',
                'description' => 'Trois variantes de QCM pour valider la notation : choix unique, multiple tout-ou-rien, multiple avec score partiel.',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'points' => 2,
                        'text' => '<p><b>Choix unique.</b> Quelle unité CSS est relative à la taille de police de l\'élément racine ?</p>',
                        'options' => ['answers' => [
                            ['text' => 'em', 'is_correct' => false],
                            ['text' => 'rem', 'is_correct' => true],
                            ['text' => 'px', 'is_correct' => false],
                            ['text' => 'vh', 'is_correct' => false],
                        ]],
                    ],
                    [
                        'type' => 'mcq',
                        'points' => 4,
                        'text' => '<p><b>Multiple strict</b> (tout ou rien). Quels principes appartiennent à la Gestalt ?</p>',
                        'options' => [
                            'multiple' => true,
                            'answers' => [
                                ['text' => 'Proximité', 'is_correct' => true],
                                ['text' => 'Similitude', 'is_correct' => true],
                                ['text' => 'Rentabilité', 'is_correct' => false],
                                ['text' => 'Clôture', 'is_correct' => true],
                            ],
                        ],
                    ],
                    [
                        'type' => 'mcq',
                        'points' => 6,
                        'text' => '<p><b>Score partiel.</b> Quelles pratiques améliorent l\'accessibilité ? '
                            .'<em>(chaque bonne réponse compte, une seule erreur annule tout)</em></p>',
                        'options' => [
                            'multiple' => true,
                            'partial_score' => true,
                            'answers' => [
                                ['text' => 'Texte alternatif sur les images', 'is_correct' => true],
                                ['text' => 'Navigation au clavier', 'is_correct' => true],
                                ['text' => 'Contraste suffisant', 'is_correct' => true],
                                ['text' => 'Désactiver le zoom', 'is_correct' => false],
                            ],
                        ],
                    ],
                ],
            ],

            // 3. Texte à trous : casse sensible et insensible
            [
                'title' => '[DEV] Texte à trous — casse sensible et multiples',
                'description' => 'Valide la notation proportionnelle par trou et la sensibilité à la casse.',
                'questions' => [
                    [
                        'type' => 'fill_blank',
                        'points' => 6,
                        'text' => '<p>Complétez les trois technologies du Web :</p>'
                            .'<p>Structure → trou 1 · Présentation → trou 2 <em>(majuscules exigées)</em> · Comportement → trou 3</p>',
                        'options' => ['blanks' => [
                            ['answers' => ['HTML', 'html'], 'case_sensitive' => false],
                            ['answers' => ['CSS'], 'case_sensitive' => true],
                            ['answers' => ['JavaScript', 'javascript', 'JS', 'js'], 'case_sensitive' => false],
                        ]],
                    ],
                    [
                        'type' => 'fill_blank',
                        'points' => 4,
                        'text' => '<p><img src="'.self::IMG.'/grille.png" alt="Grille 8pt"></p>'
                            .'<p>Une grille de 8 points impose des espacements multiples de <b>trou 1</b> pixels, '
                            .'soit par exemple <b>trou 2</b> pixels pour deux incréments.</p>',
                        'options' => ['blanks' => [
                            ['answers' => ['8', 'huit'], 'case_sensitive' => false],
                            ['answers' => ['16', 'seize'], 'case_sensitive' => false],
                        ]],
                    ],
                ],
            ],

            // 4. Associations et remise en ordre (glisser-déposer)
            [
                'title' => '[DEV] Associations et remise en ordre',
                'description' => 'Valide les menus déroulants d\'association et le glisser-déposer de remise en ordre.',
                'questions' => [
                    [
                        'type' => 'matching',
                        'points' => 4,
                        'text' => '<p>Associez chaque loi UX à son énoncé :</p>',
                        'options' => ['pairs' => [
                            ['term' => 'Loi de Fitts', 'definition' => 'Plus la cible est proche et grande, plus elle est rapide à atteindre'],
                            ['term' => 'Loi de Hick', 'definition' => 'Plus il y a de choix, plus la décision est lente'],
                            ['term' => 'Loi de Miller', 'definition' => 'La mémoire de travail retient environ 7 éléments'],
                            ['term' => 'Loi de Jakob', 'definition' => 'Les utilisateurs préfèrent que votre site fonctionne comme les autres'],
                        ]],
                    ],
                    [
                        'type' => 'ordering',
                        'points' => 5,
                        'text' => '<p>Remettez les étapes du processus de design dans l\'ordre :</p>',
                        'options' => ['items' => [
                            'Recherche utilisateur',
                            'Personas et parcours',
                            'Wireframes',
                            'Maquettes haute fidélité',
                            'Tests utilisateurs',
                        ]],
                    ],
                    [
                        'type' => 'ordering',
                        'points' => 3,
                        'text' => '<p>Classez ces tailles de police de la plus petite à la plus grande :</p>',
                        'options' => ['items' => ['12 px', '16 px', '24 px']],
                    ],
                ],
            ],

            // 5. Réponse libre + cas limite : tentative unique, sans corrections
            [
                'title' => '[DEV] Réponse libre — tentative unique, sans corrections',
                'description' => 'Cas limite : une seule tentative autorisée et corrections masquées après coup.',
                'config' => [
                    'max_attempts' => 1,
                    'show_correct_answers' => false,
                    'passing_score' => 50,
                    'duration' => 5,
                ],
                'questions' => [
                    [
                        'type' => 'open_text',
                        'points' => 5,
                        'text' => '<p>Expliquez en quelques phrases la différence entre <b>UX</b> et <b>UI</b>.</p>',
                        'options' => [],
                    ],
                    [
                        'type' => 'open_text',
                        'points' => 5,
                        'text' => '<p><img src="'.self::IMG.'/typo.png" alt="Hiérarchie typographique"></p>'
                            .'<p>Décrivez comment établir une hiérarchie typographique efficace.</p>',
                        'options' => [],
                    ],
                ],
            ],

            // 6. Chrono court + mélange des questions
            [
                'title' => '[DEV] Chrono serré et questions mélangées',
                'description' => 'Deux minutes seulement et ordre des questions aléatoire à chaque tentative.',
                'config' => [
                    'duration' => 2,
                    'shuffle_questions' => true,
                    'passing_score' => 40,
                ],
                'questions' => [
                    [
                        'type' => 'true_false',
                        'points' => 1,
                        'text' => '<p>Le blanc tournant (white space) est un élément de design à part entière.</p>',
                        'options' => ['correct_answer' => 'true'],
                    ],
                    [
                        'type' => 'true_false',
                        'points' => 1,
                        'text' => '<p>Un bouton doit toujours être bleu.</p>',
                        'options' => ['correct_answer' => 'false'],
                    ],
                    [
                        'type' => 'mcq',
                        'points' => 2,
                        'text' => '<p><img src="'.self::IMG.'/couleurs.png" alt="Cercle chromatique"></p>'
                            .'<p>Deux couleurs opposées sur le cercle chromatique sont dites :</p>',
                        'options' => ['answers' => [
                            ['text' => 'Analogues', 'is_correct' => false],
                            ['text' => 'Complémentaires', 'is_correct' => true],
                            ['text' => 'Monochromes', 'is_correct' => false],
                        ]],
                    ],
                    [
                        'type' => 'true_false',
                        'points' => 1,
                        'text' => '<p>Le contraste sert uniquement à l\'esthétique.</p>',
                        'options' => ['correct_answer' => 'false'],
                    ],
                ],
            ],
        ];
    }

    // --------------------------------------------------------- Articles

    private function seedArticles(int $creatorId, $groupIds): void
    {
        foreach ($this->articleDefinitions() as $definition) {
            $article = Article::updateOrCreate(
                ['title' => $definition['title']],
                [
                    'content' => $definition['content'],
                    'category' => $definition['category'],
                    'estimated_reading_time' => $definition['minutes'],
                    'is_active' => true,
                    'created_by' => $creatorId,
                    'seo_description' => $definition['description'] ?? null,
                ]
            );
            $article->groups()->syncWithoutDetaching($groupIds);
            $this->command?->info("  article : {$article->title}");
        }
    }

    private function articleDefinitions(): array
    {
        $lorem = function (string $theme): string {
            return "<p>{$theme} Cette section détaille les principes fondamentaux et leurs applications "
                .'concrètes dans un projet réel. Les exemples présentés proviennent de situations '
                ."rencontrées en agence comme en entreprise.</p>\n"
                .'<p>La mise en pratique demande de la rigueur : chaque décision de design doit pouvoir '
                ."être justifiée par un besoin utilisateur identifié, jamais par une simple préférence "
                ."esthétique personnelle.</p>\n";
        };

        return [
            [
                'title' => '[DEV] Guide complet — Ergonomie et lois UX',
                'category' => 'Design',
                'minutes' => 12,
                'description' => 'Article long avec images et audio pour tester la progression de lecture.',
                'content' => "<h2>Introduction</h2>\n"
                    .'<p>L\'ergonomie des interfaces repose sur des lois éprouvées par la recherche en '
                    ."psychologie cognitive. Ce guide en présente les principales.</p>\n"
                    .'<p><img src="'.self::IMG.'/loi-fitts.png" alt="Loi de Fitts"></p>'
                    ."<h2>La loi de Fitts</h2>\n"
                    .$lorem('Le temps nécessaire pour atteindre une cible dépend de sa distance et de sa taille.')
                    ."<h3>Applications pratiques</h3>\n<ul><li>Agrandir les zones cliquables sur mobile (44 px minimum)</li>"
                    ."<li>Placer les actions fréquentes à portée du pouce</li>"
                    ."<li>Regrouper les actions liées pour réduire les déplacements</li></ul>\n"
                    ."<h2>Le contraste et l'accessibilité</h2>\n"
                    .'<p><img src="'.self::IMG.'/contraste.png" alt="Contraste WCAG"></p>'
                    .$lorem('La norme WCAG impose un ratio de contraste minimal de 4,5:1 pour le texte courant.')
                    ."<h2>Écoutez l'explication</h2>\n"
                    .'<p>Un court extrait audio résume les points clés :</p>'
                    .'<p><audio controls src="'.self::IMG.'/exemple-audio.wav"></audio></p>'
                    ."<h2>La grille et le rythme vertical</h2>\n"
                    .'<p><img src="'.self::IMG.'/grille.png" alt="Grille 8pt"></p>'
                    .$lorem('Une grille de 8 points harmonise les espacements de toute l\'interface.')
                    .$lorem('Le rythme vertical crée une respiration visuelle qui guide naturellement la lecture.')
                    ."<h2>Conclusion</h2>\n"
                    .'<p>Ces principes ne sont pas des dogmes mais des repères : ils s\'appliquent avec '
                    .'discernement, en fonction du contexte et des utilisateurs réels du produit.</p>',
            ],
            [
                'title' => '[DEV] Fiche express — La règle des 8 points',
                'category' => 'Design',
                'minutes' => 2,
                'description' => 'Article court pour tester une lecture rapide.',
                'content' => '<p>Tous les espacements de l\'interface sont des multiples de 8 pixels : '
                    ."8, 16, 24, 32, 40…</p>\n"
                    .'<p><img src="'.self::IMG.'/grille.png" alt="Grille 8pt"></p>'
                    ."<p><b>Pourquoi ?</b> Les écrans ont des densités variables mais presque toutes "
                    ."divisibles par 8 : les valeurs restent nettes sans demi-pixels.</p>\n"
                    .'<ul><li>Marges internes : 8, 16, 24</li><li>Gouttières : 16 ou 24</li>'
                    .'<li>Sections : 40, 48, 64</li></ul>',
            ],
            [
                'title' => '[DEV] Théorie des couleurs appliquée',
                'category' => 'Couleur',
                'minutes' => 7,
                'content' => '<p><img src="'.self::IMG.'/couleurs.png" alt="Cercle chromatique"></p>'
                    ."<h2>Harmonies chromatiques</h2>\n"
                    .$lorem('Les couleurs complémentaires s\'opposent sur le cercle chromatique et créent un contraste maximal.')
                    ."<h3>Palette recommandée</h3>\n"
                    .'<ul><li><b>Primaire</b> : identité de la marque</li>'
                    .'<li><b>Secondaire</b> : actions de soutien</li>'
                    .'<li><b>Sémantiques</b> : succès, avertissement, erreur</li>'
                    ."<li><b>Neutres</b> : 5 à 7 niveaux de gris</li></ul>\n"
                    .$lorem('Une palette restreinte produit des interfaces plus cohérentes qu\'une palette foisonnante.'),
            ],
            [
                'title' => '[DEV] Typographie — hiérarchie et lisibilité',
                'category' => 'Typographie',
                'minutes' => 6,
                'content' => '<p><img src="'.self::IMG.'/typo.png" alt="Hiérarchie typographique"></p>'
                    ."<h2>Établir une échelle</h2>\n"
                    .$lorem('Une échelle typographique définit des tailles cohérentes plutôt qu\'arbitraires.')
                    ."<h3>Longueur de ligne</h3>\n"
                    ."<p>Entre 45 et 75 caractères par ligne : au-delà, l'œil peine à revenir "
                    ."au début de la ligne suivante.</p>\n"
                    .'<p>Écoutez la prononciation du terme « chasse » :</p>'
                    .'<p><audio controls src="'.self::IMG.'/prononciation.wav"></audio></p>'
                    .$lorem('L\'interlignage se situe généralement entre 1,4 et 1,6 fois la taille de police.'),
            ],
            [
                'title' => '[DEV] Méthodologie de recherche utilisateur',
                'category' => 'Recherche',
                'minutes' => 9,
                'content' => "<h2>Pourquoi chercher avant de dessiner</h2>\n"
                    .$lorem('La recherche utilisateur évite de construire une solution élégante à un problème inexistant.')
                    ."<h3>Méthodes qualitatives</h3>\n"
                    .'<ul><li>Entretiens semi-directifs (5 à 8 personnes suffisent)</li>'
                    .'<li>Observation contextuelle</li><li>Tests d\'utilisabilité modérés</li></ul>'
                    ."<h3>Méthodes quantitatives</h3>\n"
                    .'<ul><li>Questionnaires à grande échelle</li><li>Analytics comportementaux</li>'
                    .'<li>Tests A/B</li></ul>'
                    .$lorem('Les deux approches se complètent : le qualitatif explique ce que le quantitatif révèle.'),
            ],
        ];
    }

    // ----------------------------------------------------------- Decks

    private function seedDecks(int $creatorId, $groupIds): void
    {
        foreach ($this->deckDefinitions() as $definition) {
            $deck = FlashcardDeck::updateOrCreate(
                ['titre' => $definition['titre']],
                [
                    'description' => $definition['description'],
                    'matiere' => $definition['matiere'],
                    'algorithme' => 'sm2',
                    'easiness_default' => 2.5,
                    'interval_min' => 1,
                    'interval_max' => 365,
                    'active' => true,
                    'is_public' => false,
                    'created_by' => $creatorId,
                ]
            );

            $deck->cards()->delete();
            foreach ($definition['cards'] as $order => $card) {
                FlashcardItem::create([
                    'deck_id' => $deck->id,
                    'recto' => $card[0],
                    'verso' => $card[1],
                    'ordre' => $order + 1,
                    'tags' => $definition['matiere'],
                ]);
            }

            $deck->groups()->syncWithoutDetaching($groupIds);
            $this->command?->info("  deck : {$deck->titre} (".count($definition['cards']).' cartes)');
        }
    }

    private function deckDefinitions(): array
    {
        return [
            [
                'titre' => '[DEV] Cartes avec images et audio',
                'description' => 'Valide le rendu des médias au recto comme au verso des cartes.',
                'matiere' => 'Design',
                'cards' => [
                    [
                        '<p>Quelle loi ergonomique cette illustration représente-t-elle ?</p>'
                            .'<p><img src="'.self::IMG.'/loi-fitts.png" alt="Loi de Fitts"></p>',
                        '<p><b>La loi de Fitts.</b></p><p>Le temps pour atteindre une cible augmente avec '
                            .'sa distance et diminue avec sa taille.</p>',
                    ],
                    [
                        '<p>Quel est le ratio de contraste WCAG AA minimal pour le texte courant ?</p>',
                        '<p><b>4,5:1</b></p><p><img src="'.self::IMG.'/contraste.png" alt="Contraste"></p>',
                    ],
                    [
                        '<p>Écoutez cet extrait puis retournez la carte :</p>'
                            .'<p><audio controls src="'.self::IMG.'/prononciation.wav"></audio></p>'
                            .'<p>Combien de notes distinctes entendez-vous ?</p>',
                        '<p><b>Trois notes.</b></p><p>Une montée en tierce puis en quinte.</p>',
                    ],
                    [
                        '<p>Que représente cette figure ?</p><p><img src="'.self::IMG.'/couleurs.png" alt="Cercle"></p>',
                        '<p><b>Le cercle chromatique.</b></p><p>Il permet de repérer les couleurs '
                            .'complémentaires (opposées) et analogues (voisines).</p>',
                    ],
                ],
            ],
            [
                'titre' => '[DEV] Vocabulaire UX — session longue',
                'description' => 'Douze cartes pour tester une session de révision complète et la progression SM-2.',
                'matiere' => 'Vocabulaire UX',
                'cards' => [
                    ['<p><b>Wireframe</b></p>', '<p>Schéma fonctionnel en basse fidélité, sans couleur ni typographie définitive.</p>'],
                    ['<p><b>Persona</b></p>', '<p>Archétype d\'utilisateur construit à partir de données de recherche réelles.</p>'],
                    ['<p><b>Affordance</b></p>', '<p>Capacité d\'un élément à suggérer son propre mode d\'emploi.</p>'],
                    ['<p><b>Heuristique</b></p>', '<p>Règle empirique d\'évaluation d\'une interface (les 10 de Nielsen).</p>'],
                    ['<p><b>Parcours utilisateur</b></p>', '<p>Séquence d\'étapes qu\'un utilisateur traverse pour atteindre son objectif.</p>'],
                    ['<p><b>Design system</b></p>', '<p>Ensemble cohérent de composants, règles et principes réutilisables.</p>'],
                    ['<p><b>Atomic design</b></p>', '<p>Méthode de composition : atomes → molécules → organismes → gabarits → pages.</p>'],
                    ['<p><b>Test A/B</b></p>', '<p>Comparaison de deux versions auprès d\'échantillons distincts pour mesurer l\'effet.</p>'],
                    ['<p><b>Taux de conversion</b></p>', '<p>Proportion d\'utilisateurs réalisant l\'action visée.</p>'],
                    ['<p><b>Accessibilité (a11y)</b></p>', '<p>Conception permettant l\'usage par des personnes en situation de handicap.</p>'],
                    ['<p><b>Responsive design</b></p>', '<p>Adaptation de la mise en page à toutes les tailles d\'écran.</p>'],
                    ['<p><b>Charge cognitive</b></p>', '<p>Effort mental exigé par une interface ; à minimiser systématiquement.</p>'],
                ],
            ],
            [
                'titre' => '[DEV] Raccourcis et bonnes pratiques',
                'description' => 'Deck court pour une révision éclair.',
                'matiere' => 'Pratique',
                'cards' => [
                    ['<p>Taille minimale d\'une cible tactile ?</p>', '<p><b>44 × 44 pixels</b> (recommandation Apple et WCAG).</p>'],
                    ['<p>Nombre idéal de caractères par ligne ?</p>', '<p>Entre <b>45 et 75</b> caractères.</p>'],
                    ['<p>Interlignage conseillé pour du corps de texte ?</p>', '<p><b>1,4 à 1,6</b> fois la taille de police.</p>'],
                ],
            ],
        ];
    }
}
