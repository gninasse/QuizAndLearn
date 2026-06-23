<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Article;
use Modules\Core\Models\Group;
use Modules\Core\Models\User;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les anciens articles
        Article::query()->delete();

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
        // 1. Article : UI/UX
        // ==========================================
        $artUi = Article::create([
            'title' => 'Introduction aux Principes Ergonomiques du Design UI',
            'content' => '
                <h2>Comprendre l\'ergonomie digitale</h2>
                <p>L\'ergonomie des interfaces (UI) a pour objectif principal d\'optimiser la facilité d\'utilisation d\'un produit numérique. Une interface bien conçue doit minimiser la charge cognitive de l\'utilisateur.</p>
                
                <h3>La Loi de Fitts</h3>
                <p>La Loi de Fitts est un principe fondamental de l\'interaction homme-machine. Elle postule que le temps requis pour atteindre rapidement une cible est fonction de la distance à la cible et de la taille de celle-ci.</p>
                <blockquote><strong>Règle :</strong> Les boutons d\'action primaires doivent être de taille généreuse et faciles d\'accès sur l\'écran (notamment au bas sur mobile).</blockquote>

                <h3>La Loi de Jakob</h3>
                <p>La Loi de Jakob stipule que les utilisateurs passent la majeure partie de leur temps sur d\'autres sites. Par conséquent, ils s\'attendent à ce que votre site fonctionne de la même manière que ceux qu\'ils connaissent déjà.</p>
                <p>N\'essayez pas de réinventer les conventions universelles (comme le panier d\'achat en haut à droite, ou les menus d\'onglets inférieurs sur mobile).</p>
            ',
            'is_active' => true,
            'category' => 'Design',
            'estimated_reading_time' => 4,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupUiUx) {
            $artUi->groups()->attach($groupUiUx->id);
        }

        // ==========================================
        // 2. Article : JS Advanced Closures
        // ==========================================
        $artJs = Article::create([
            'title' => 'Les Closures et la Portée Lexicale en Javascript',
            'content' => '
                <h2>La Portée Lexicale (Lexical Scoping)</h2>
                <p>En Javascript, la portée d\'une variable est définie par sa position physique dans le code source. Une fonction interne a accès aux variables déclarées dans sa fonction parente.</p>
                
                <h3>Qu\'est-ce qu\'une Closure ?</h3>
                <p>Une <em>closure</em> (ou fermeture) est la combinaison d\'une fonction et de l\'environnement lexical dans lequel cette fonction a été déclarée. Elle permet à une fonction d\'accéder à des variables d\'une portée externe, même après la fin d\'exécution de cette portée externe.</p>
                <pre><code>function creerCompteur() {
    let count = 0;
    return function() {
        count++;
        return count;
    };
}
const monCompteur = creerCompteur();
console.log(monCompteur()); // 1
console.log(monCompteur()); // 2</code></pre>

                <h3>Cas d\'utilisation courants</h3>
                <p>Les closures sont principalement utilisées pour :</p>
                <ul>
                    <li>L\'encapsulation et la création de variables privées.</li>
                    <li>La création de fonctions usines (Factory functions).</li>
                    <li>La gestion des événements et des callbacks asynchrones.</li>
                </ul>
            ',
            'is_active' => true,
            'category' => 'Development',
            'estimated_reading_time' => 5,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupWeb) {
            $artJs->groups()->attach($groupWeb->id);
        }

        // ==========================================
        // 3. Article : Laravel Polymorphism
        // ==========================================
        $artLaravel = Article::create([
            'title' => 'Comprendre les Relations Polymorphiques dans Laravel 12',
            'content' => '
                <h2>Pourquoi utiliser le polymorphisme ?</h2>
                <p>Le polymorphisme dans l\'ORM Eloquent permet à un modèle d\'appartenir à plus d\'un autre type de modèle sur une seule association. Par exemple, un modèle <code>Comment</code> peut appartenir à la fois à un modèle <code>Post</code> et à un modèle <code>Video</code>.</p>
                
                <h3>Structure de la Table</h3>
                <p>Pour faire fonctionner le polymorphisme, votre table pivot ou table cible nécessite deux colonnes spécifiques : un ID et un type textuel.</p>
                <pre><code>Schema::create(\'comments\', function (Blueprint $table) {
    $table-&gt;id();
    $table-&gt;text(\'body\');
    $table-&gt;numeric(\'commentable_id\');
    $table-&gt;string(\'commentable_type\'); // Contiendra App\\Models\\Post ou App\\Models\\Video
    $table-&gt;timestamps();
});</code></pre>

                <h3>Définir les Relations dans les Modèles</h3>
                <p>Dans le modèle enfant, vous utilisez la méthode <code>morphTo()</code> :</p>
                <pre><code>class Comment extends Model {
    public function commentable() {
        return $this-&gt;morphTo();
    }
}</code></pre>
                <p>Dans les modèles parents, vous utilisez <code>morphMany()</code> :</p>
                <pre><code>class Post extends Model {
    public function comments() {
        return $this-&gt;morphMany(Comment::class, \'commentable\');
    }
}</code></pre>
            ',
            'is_active' => true,
            'category' => 'Framework',
            'estimated_reading_time' => 6,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupLaravel) {
            $artLaravel->groups()->attach($groupLaravel->id);
        }
        if ($groupWeb) {
            $artLaravel->groups()->attach($groupWeb->id);
        }

        // ==========================================
        // 4. Article : Docker & DevOps
        // ==========================================
        $artDocker = Article::create([
            'title' => 'Guide DevOps : Conteneuriser son application avec Docker',
            'content' => '
                <h2>Pourquoi conteneuriser son application ?</h2>
                <p>Docker résout le fameux problème "ça fonctionne sur ma machine". Il emballe l\'application, son runtime, ses bibliothèques système et ses dépendances dans un conteneur isolé et reproductible.</p>
                
                <h3>Écrire un Dockerfile efficace</h3>
                <p>Un Dockerfile est un script d\'instructions étape par étape pour compiler une image. Utilisez le multi-stage build pour minimiser le poids final de vos conteneurs en production.</p>
                
                <h3>Exemple pratique de Dockerfile Node.js</h3>
                <pre><code># Étape 1 : Build
FROM node:22-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Étape 2 : Runtime
FROM node:22-alpine
WORKDIR /app
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/package*.json ./
RUN npm install --only=production
EXPOSE 3000
CMD ["node", "dist/index.js"]</code></pre>
            ',
            'is_active' => true,
            'category' => 'DevOps',
            'estimated_reading_time' => 7,
            'created_by' => $trainerUser->id,
        ]);

        if ($groupDevOps) {
            $artDocker->groups()->attach($groupDevOps->id);
        }
    }
}
