<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\User;

class FlashcardDeckSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les anciens paquets et cartes pour repartir propre
        FlashcardDeck::query()->delete();

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
        // 1. Paquet: Vocabulaire & Concepts UI/UX
        // ==========================================
        $deckUi = FlashcardDeck::create([
            'titre' => 'Terminologies et Lois du Design UI/UX',
            'description' => 'Un paquet complet pour mémoriser les lois fondamentales du design et les règles ergonomiques.',
            'matiere' => 'UI/UX Design',
            'created_by' => $trainerUser->id,
            'is_public' => true,
            'algorithme' => 'sm2',
            'easiness_default' => 2.50,
            'interval_min' => 1,
            'interval_max' => 365,
            'active' => true,
        ]);

        if ($groupUiUx) {
            $deckUi->groups()->attach($groupUiUx->id);
        }

        // Card 1
        FlashcardItem::create([
            'deck_id' => $deckUi->id,
            'recto' => '<h3>Loi de Fitts</h3><p>Que stipule cette loi ergonomique majeure ?</p>',
            'verso' => '<p>Le temps nécessaire pour atteindre une cible est fonction de la <strong>distance</strong> à la cible et de sa <strong>taille</strong>.</p><p><em>Formule : T = a + b * log2(2D / W)</em></p>',
            'tags' => 'loi, ergonomie, fitts',
            'note' => 'Pensez à la taille des boutons d\'action principaux (CTA).',
            'ordre' => 1,
        ]);

        // Card 2
        FlashcardItem::create([
            'deck_id' => $deckUi->id,
            'recto' => '<h3>Loi de Hick</h3><p>Quel est l\'impact du nombre de choix sur le temps de décision ?</p>',
            'verso' => '<p>Le temps requis pour prendre une décision augmente logarithmiquement avec le <strong>nombre</strong> et la <strong>complexité</strong> des choix présentés.</p>',
            'tags' => 'loi, décision, hick',
            'note' => 'Lié à la simplification des menus et des formulaires.',
            'ordre' => 2,
        ]);

        // Card 3
        FlashcardItem::create([
            'deck_id' => $deckUi->id,
            'recto' => '<h3>Loi de Jakob</h3><p>Pourquoi ne faut-il pas trop réinventer la roue en termes d\'affichages standards ?</p>',
            'verso' => '<p>Les utilisateurs passent la majeure partie de leur temps sur <strong>d\'autres sites</strong>. Ils préfèrent que votre site fonctionne de la même manière que ceux qu\'ils connaissent déjà.</p>',
            'tags' => 'loi, Jakob, ergonomie, conventions',
            'note' => 'Favorise le transfert de compétences et réduit la charge cognitive.',
            'ordre' => 3,
        ]);

        // ==========================================
        // 2. Paquet: Raccourcis et Commandes Laravel 12
        // ==========================================
        $deckLaravel = FlashcardDeck::create([
            'titre' => 'Raccourcis Artisan & Concepts Laravel 12',
            'description' => 'Mémoriser les principales commandes et structures de fichiers dans Laravel v12.',
            'matiere' => 'Laravel Framework',
            'created_by' => $trainerUser->id,
            'is_public' => false,
            'algorithme' => 'leitner',
            'easiness_default' => 2.50,
            'interval_min' => 1,
            'interval_max' => 120,
            'active' => true,
        ]);

        if ($groupLaravel) {
            $deckLaravel->groups()->attach($groupLaravel->id);
        }
        if ($groupWeb) {
            $deckLaravel->groups()->attach($groupWeb->id);
        }

        // Card 1
        FlashcardItem::create([
            'deck_id' => $deckLaravel->id,
            'recto' => '<h3>Commandes Artisan</h3><p>Comment lister toutes les routes enregistrées dans votre application Laravel ?</p>',
            'verso' => '<p>Utilisez la commande : <code>php artisan route:list</code></p>',
            'tags' => 'artisan, routing, cli',
            'note' => 'Ajoutez -v pour voir les middlewares affectés.',
            'ordre' => 1,
        ]);

        // Card 2
        FlashcardItem::create([
            'deck_id' => $deckLaravel->id,
            'recto' => '<h3>Eloquent ORM</h3><p>Quelle méthode permet de charger des relations avec Eager Loading pour éviter le problème N+1 ?</p>',
            'verso' => '<p>La méthode <code>with()</code>.</p><p>Exemple : <code>Post::with(\'comments\')->get();</code></p>',
            'tags' => 'eloquent, orm, performance',
            'note' => 'À faire avant d\'exécuter ->get() ou ->paginate().',
            'ordre' => 2,
        ]);

        // Card 3
        FlashcardItem::create([
            'deck_id' => $deckLaravel->id,
            'recto' => '<h3>Routage - Laravel 12</h3><p>Où sont configurés les middlewares globaux et de routage depuis Laravel 11/12 ?</p>',
            'verso' => '<p>Dans le fichier <code>bootstrap/app.php</code> via le callback <code>withMiddleware()</code>.</p>',
            'tags' => 'routing, middleware, config',
            'note' => 'Il n\'y a plus de classe Kernel globale dans app/Http.',
            'ordre' => 3,
        ]);
    }
}
