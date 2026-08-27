# Correctif AI Studio #3 — Réorganiser la navigation comme la PWA

> L'application mobile expose actuellement **7 onglets** (Accueil, Articles,
> Quiz, Flashcards, Examens, Classement, Profil). C'est au-delà de la
> recommandation Material Design (3 à 5) : les libellés se tronquent, les
> cibles tactiles rétrécissent et la hiérarchie devient illisible.
> La PWA de référence n'en a que **5**. Aligne l'application dessus.

---

## 1. Nouvelle barre de navigation — exactement 5 onglets

| Ordre | Libellé | Icône Material | Destination |
|---|---|---|---|
| 1 | **Accueil** | `Icons.Outlined.Home` | Tableau de bord |
| 2 | **Articles** | `Icons.AutoMirrored.Outlined.Article` | Liste des articles |
| 3 | **Entraînement** | `Icons.Outlined.Bolt` | Quiz **et** Flashcards (§2) |
| 4 | **Examens** | `Icons.Outlined.School` | Liste des examens |
| 5 | **Profil** | `Icons.Outlined.AccountCircle` | Profil |

Trois onglets **disparaissent de la barre** :
- **Quiz** et **Flashcards** → fusionnés dans « Entraînement » (§2) ;
- **Classement** → déplacé dans l'écran « Ma progression » (§3).

Aucun écran n'est supprimé : seules leurs portes d'entrée changent. Conserve
toutes les routes de navigation existantes (`quiz_play`, `flashcard_study`,
`leaderboard`…), elles restent atteignables par navigation interne.

### Pastilles de comptage

Deux onglets seulement portent une pastille (`Badge` Material 3, couleur
primaire), masquée à zéro, affichant « 9+ » au-delà de 9 :

- **Articles** = nombre d'articles dont `status != "completed"` ;
- **Entraînement** = quiz à faire + cartes dues, c'est-à-dire
  `quiz where status != "completed" && !maxAttemptsReached`
  **plus** `cartes where review == null || review.nextReview <= maintenant`.

Ces compteurs se recalculent à partir des `Flow` Room, sans reconstruire la
barre de navigation.

---

## 2. Nouvel écran « Entraînement » (fusion Quiz + Flashcards)

Crée `TrainingScreen` qui remplace `QuizzesListScreen` et `DecksListScreen`
comme destination d'onglet (les deux composables existants deviennent le
contenu des deux segments — ne les réécris pas, réutilise-les).

**Structure de l'écran**

1. Titre de la barre supérieure : **« Entraînement »**.
2. Phrase d'accroche sous la barre, en `bodySmall` grisé :
   > « Entraînez-vous sans pression : les quiz sont rejouables et les cartes
   > suivent votre mémoire. »
3. **Sélecteur segmenté** Material 3 (`SingleChoiceSegmentedButtonRow`),
   largeur maximale ~360 dp, deux segments :
   - « **Quiz (n)** » — icône `HelpOutline`, n = nombre total de quiz ;
   - « **Flashcards** » — icône `Layers`, suivi d'une petite pastille
     **violette** avec le nombre total de cartes dues (masquée si zéro).
4. Le segment sélectionné est **mémorisé** (`rememberSaveable`) et restauré au
   retour sur l'onglet.

**Contenu du segment Quiz** (reprendre l'existant) : champ de recherche,
puces de filtre Tous / Nouveaux / En cours / Terminés, carte « Rejouer mes
erreurs » quand des erreurs sont enregistrées, puis la liste des quiz.

**Contenu du segment Flashcards** : la liste des decks telle qu'elle existe
déjà (bandeau matière, titre, description, progression de maîtrise, bouton
« Réviser n cartes »).

**Cohérence chromatique** : accent **bleu** pour le segment Quiz, **violet**
pour Flashcards, **ambre** pour les examens — comme la PWA.

---

## 3. Écran « Ma progression » — nouvelle maison du classement

Le classement quitte la barre d'onglets pour rejoindre un écran de synthèse,
accessible depuis **un bouton dans la carte héro du tableau de bord** :
bouton clair sur le dégradé, libellé « **Ma progression** », icône
`Icons.AutoMirrored.Outlined.TrendingUp`, aligné à droite sous les puces
série/badges.

`ProgressScreen` empile, dans cet ordre :

1. **Résumé** — quatre tuiles : XP total, Niveau, 🔥 Série (avec « max n »),
   % de réussite aux quiz ; puis la barre de progression du niveau
   (`XP % 100` sur 100).
2. **Activité des 12 dernières semaines** — grille 7 lignes × 12 colonnes,
   un carré par jour, quatre intensités (0 / 1 / 2 / 3+ tentatives ce
   jour-là), calculée **localement** depuis les tentatives en base : cet
   écran doit donc fonctionner hors-ligne.
3. **Mes meilleurs scores** — une barre horizontale par quiz tenté, verte à
   100 %.
4. **Classement de mes groupes** — le contenu de l'actuel `LeaderboardScreen`,
   intégré ici : par groupe, « Vous êtes Xᵉ / N », lignes classées avec
   médailles 🥇🥈🥉 pour le podium, avatar à initiales, série, XP, et **la
   ligne de l'utilisateur surlignée** avec la mention « (vous) ».
   Hors-ligne : afficher le dernier classement mis en cache avec la mention
   « Classement issu de la dernière synchronisation ».

---

## 4. Détails d'exécution

- **Barre de navigation** : `NavigationBar` Material 3, `alwaysShowLabel = true`,
  état sélectionné en couleur primaire avec l'indicateur de pilule Material 3.
  Chaque onglet reste ≥ 48 dp de haut.
- **Navigation active** : un onglet reste marqué actif quand on est sur l'un de
  ses écrans enfants — « Entraînement » l'est pendant un quiz, une fiche quiz
  ou une session de révision ; « Examens » l'est pendant un examen.
- **Masquer la barre** pendant les activités immersives : lecteur de quiz,
  passage d'examen et session de flashcards s'affichent en plein écran, sans
  barre de navigation (elle réapparaît à la sortie).
- **Retour système** : depuis n'importe quel onglet, le bouton retour ramène
  d'abord à Accueil, puis quitte l'application (comportement Android usuel).

## 5. Vérification attendue

- La barre affiche **cinq** onglets, tous les libellés lisibles sans troncature
  sur un écran de 360 dp de large.
- L'onglet Entraînement bascule entre Quiz et Flashcards sans quitter l'écran,
  et retrouve son segment au retour.
- Le classement n'est plus dans la barre mais reste accessible en deux
  touches : Accueil → « Ma progression ».
- Les pastilles Articles et Entraînement affichent les bons comptes et
  disparaissent quand tout est à jour.
- Pendant un quiz, un examen ou une révision, la barre de navigation est
  masquée.
