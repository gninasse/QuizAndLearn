# Audit ergonomie / UX — QuizAndLearn (Learn&Quiz)

- **Date** : 18 octobre 2026
- **Périmètre** : back-office Blade (AdminLTE 4 / Bootstrap 5 / jQuery) et PWA apprenant (TypeScript / Tailwind v4)
- **Référence client** : « L'ergonomie doit être professionnelle avec une structure visuelle soignée, des icônes explicites et des titres clairs. »
- **Méthode** : relecture écran par écran des gabarits et du code (sans navigateur). Chaque constat cite son fichier et sa ligne. Les icônes Bootstrap Icons ont été vérifiées contre `public/plugins/bootstrap-icons/font/bootstrap-icons.min.css`.
- **Sévérités** : **Bloquant** (visible tout de suite, contraire à la consigne du client, à corriger avant la recette) · **Important** (nuit à la compréhension ou à la cohérence) · **Amélioration** (finition).

---

## 1. Résumé

La PWA apprenant est de bon niveau : titres d'écran, états vides, focus clavier global (`resources/css/learner.css:198-201`), cibles tactiles de 44 px sur les contrôles de jeu, dialogues de confirmation natifs. Il lui manque surtout de l'homogénéité : émojis mêlés aux icônes, termes anglais (« Deck »), boutons d'examen sans libellé.
Le back-office est **hétérogène** et c'est lui qui expose le plus le projet face à la consigne. Il garde des restes du gabarit AdminLTE (« Dashboard », recherche et plein écran sans effet, « Hello World »). Ses éditeurs mélangent l'anglais et le français (« Help Center », « Overview », « Draft Mode », « Quiz Editor »). Six écrans de gestion n'ont que des barres d'outils d'icônes dont les infobulles ne s'affichent pas sur les boutons désactivés. Plusieurs icônes ne s'affichent pas car leur classe n'existe pas.
Deux familles d'icônes cohabitent (215 occurrences Font Awesome 7 et 349 Bootstrap Icons dans les vues), parfois dans un même écran. Trois couleurs de marque se contredisent : vert du back-office, bleu AdminLTE dans `theme-color`, bleu ciel dans la PWA.
Le menu latéral aligne 17 entrées à plat sous un parent unique qui porte le nom de la marque, sans regroupement métier.
La plupart des corrections sont mécaniques : libellés, classes d'icônes, fil d'Ariane. Un premier lot d'environ une journée suffit à lever les points bloquants.

**Score global indicatif : 6/10** (PWA 7,5/10 ; back-office 5/10).

**Décompte (constats par écran)** : 9 Bloquants · 37 Importants · 41 Améliorations (87 constats). Les 10 constats transverses (§2) les regroupent.

---

## 2. Constats transverses (top 10, classés par impact)

| # | Constat | Preuves | Sévérité |
|---|---------|---------|----------|
| T1 | **Icônes inexistantes, rendues en case vide.** `bi-print` (il faut `bi-printer`) sur tous les boutons « Imprimer (A4) ». `bi-desktop` sur « Supervision Live ». `bi-type-paragraph` sur un bouton d'éditeur qui n'a pas de texte : le bouton paraît vide. `bi-arrow-right-left` dans l'aperçu des appariements. | `admin/quiz/editor.blade.php:111`, `admin/exam/editor.blade.php:58,112`, `admin/flashcard/editor.blade.php:113`, `admin/quiz/preview.blade.php:15`, `admin/exam/preview.blade.php:15`, `admin/article/editor.blade.php:105`, `public/js/admin/modals/modal-matching.js:102` | Bloquant |
| T2 | **Anglais et démo laissés dans l'interface.** « Dashboard » (menu, titre, fils d'Ariane, éditeurs), « Profile », « Help Center », « Overview », « Settings », « Preview », « Draft Mode », « Quiz Editor - », « Exam Editor - », « Quizzes », « Supervision Live », `alt="User Image"`, `aria-label="Main navigation"`, `aria-label="Close"` (36 occurrences). | `layouts/partials/sidebar.blade.php:29,46`, `dashboard/index.blade.php:3,7`, `layouts/master.blade.php:100`, `layouts/partials/navbar.blade.php:38,48,59`, `admin/quiz/editor.blade.php:3,5,18,31,43,49,57,73-74`, `admin/exam/editor.blade.php:3,5,23,59,74`, `admin/flashcard/editor.blade.php:42`, `layouts/admin-editor.blade.php:56,62,80` | Bloquant |
| T3 | **Contrôles factices.** Loupe de la barre du haut sans formulaire (`data-widget="navbar-search"` vient d'AdminLTE 3). Boutons « Aide » et « Notifications » avec une pastille rouge permanente. Champs « Rechercher des ressources… » et « Rechercher dans l'article… » sans gestionnaire (0 occurrence de `resourceSearch` ou `editorSearch` dans `public/js`). Bouton « Réordonner » sans gestionnaire. « Nouveau Quiz » renvoie vers la liste. | `layouts/partials/navbar.blade.php:17-21`, `layouts/admin-editor.blade.php:93-101`, `admin/quiz/editor.blade.php:70,76,113`, `admin/exam/editor.blade.php:71,114`, `admin/article/editor.blade.php:60` | Bloquant |
| T4 | **Barres d'outils en icônes seules** sur 6 écrans de gestion (Apprenants, Formateurs, Utilisateurs, Administrateurs, Rôles, Groupes). Une clé signifie « Réinitialiser MDP », un sens interdit signifie « Désactiver ». Les boutons sont `disabled` tant qu'aucune ligne n'est cochée : Bootstrap n'affiche pas d'infobulle sur un élément désactivé, donc on ne peut pas savoir à quoi ils servent avant de sélectionner. Les écrans Quiz, Examens, Flashcards et Articles affichent bien un libellé : les deux modèles cohabitent. | `learners/index.blade.php:18-41`, `trainers/index.blade.php:18-35`, `users/index.blade.php:18-43`, `admins/index.blade.php:18-33`, `roles/index.blade.php:18-33`, `groups/index.blade.php:18-35`, à comparer avec `quizzes/index.blade.php:17-41` | Bloquant |
| T5 | **Deux familles d'icônes** : Font Awesome 7 et Bootstrap Icons sont chargées ensemble (`layouts/master.blade.php:44-53`, `layouts/admin-editor.blade.php:14-15`) et mêlées dans un même écran. La même notion change aussi d'icône : un formateur est `bi-person-workspace` dans le menu, `bi-person-badge` (déjà celle des Administrateurs) dans les membres et `fa-chalkboard-teacher` dans la liste des groupes. `bi-mortarboard` désigne les Apprenants au back-office et les Examens dans la PWA. | `quizzes/index.blade.php` (fa=10 / bi=3), `exams/index.blade.php` (11/3), `flashcards/index.blade.php` (8/3), `learners/index.blade.php` (6/1) ; `sidebar.blade.php:96,64`, `groups/members.blade.php:35`, `groups/index.blade.php:90`, `resources/js/learner/components/app-shell.ts:35` | Important |
| T6 | **Menu non structuré** : un seul parent `href="#"` toujours « actif », intitulé du nom de marque avec une icône de compteur de vitesse, et 17 entrées à plat qui mêlent pédagogie, personnes, paramètres et outils techniques (Modules, Permissions). Quatre entrées concernent des personnes (Utilisateurs, Administrateurs, Formateurs, Apprenants). | `layouts/partials/sidebar.blade.php:33-176` | Important |
| T7 | **Fil d'Ariane cassé ou absent** : le lien « Accueil » pointe sur `href="#"` dans 15 vues. Pas de fil d'Ariane sur Paramètres généraux, Mode découverte et Profil. Demandes d'accès n'en a qu'un niveau. | `learners/index.blade.php:7` (et groups, quizzes, exams, flashcards, articles, trainers, admins, users, roles, permissions, activities, dashboard, quizzes/builder, admin/exam/supervision) ; `settings/general.blade.php:1-6`, `showcase/settings.blade.php:1-6`, `access-requests/index.blade.php:6-8` | Important |
| T8 | **Retours d'action bloquants et partiels** : 155 appels `Swal.fire`. Chaque succès ouvre une modale à fermer (« Succès »). Sur Examens, les erreurs d'activation et de suppression ne sont pas traitées : rien ne s'affiche si la requête échoue. Pas d'erreur sous chaque champ dans les modales Examen et Flashcard, alors que les 9 autres formulaires en ont (`displayErrors`). | `exams/index.blade.php:303,319-326,354-363` ; formulaires avec erreurs par champ : `public/js/modules/core/*/…Form.js` | Important |
| T9 | **Identité visuelle incohérente** : vert `#1e6f5c` au back-office (`public/css/admin/theme-custom.css:4`), `theme-color #007bff` d'AdminLTE (`layouts/master.blade.php:12`), bleu ciel `#0284c7` dans la PWA (`learner/app.blade.php:7`). Au back-office, `btn-primary` (`#1e6f5c`) et `btn-success` (`#289672`) sont presque identiques (`theme-custom.css:15,25`) : on ne distingue pas « Nouveau » d'« Aperçu ». | voir preuves | Important |
| T10 | **Termes flottants** : « Flashcards », « Decks », « paquets de cartes », « Cartes à réviser » ; « apprenants » et « étudiants » ; « Quiz » et « Quizzes » ; « Accueil » dans la navigation de la PWA mais « Tableau de bord » en titre. | `flashcards/index.blade.php:14,111`, `resources/js/learner/views/dashboard.ts:220`, `entrainement.ts:139,164`, `layouts/admin-editor.blade.php:62`, `app-shell.ts:24` contre `main.ts:45` | Important |

---

## 3. Constats par écran — Back-office

### 3.1 Gabarit principal (`layouts/master.blade.php`, `partials/navbar.blade.php`, `partials/sidebar.blade.php`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Titre par défaut « Dashboard » quand une vue ne définit pas `header` | `master.blade.php:100` | Important | Remplacer par `@yield('header', 'Tableau de bord')`. |
| « Profile » (anglais) et « Se deconnecter » (sans accent) dans le menu utilisateur | `navbar.blade.php:59,62` | Important | « Mon profil » / « Se déconnecter », avec les icônes `bi-person` / `bi-box-arrow-right`, comme dans `admin-editor.blade.php:110,118`. |
| Boutons « menu », « recherche » et « plein écran » sans `aria-label` ; la recherche n'a aucun effet | `navbar.blade.php:8-10,17-21,25-30` | Important | Supprimer la recherche. Ajouter `aria-label` et `title` : « Afficher/masquer le menu », « Plein écran ». |
| `alt="User Image"` sur les avatars | `navbar.blade.php:38,48` | Amélioration | `alt="Photo de profil de {{ Auth::user()->name }}"`. |
| Pied de page : « tous droits reservés » (sans accent), lien `href="#"` | `master.blade.php:131-133` | Amélioration | « Tous droits réservés. » ; supprimer le lien ou le faire pointer vers le site du propriétaire. |
| `<title>` absent sur Tableau de bord, Utilisateurs, Rôles, Permissions, Activités, Profil, fiche utilisateur : l'onglet n'affiche que la marque | `dashboard/index.blade.php:3`, `users/index.blade.php:3`, `roles/index.blade.php:3`, `permissions/index.blade.php:3`, `activities/index.blade.php:3`, `profile/index.blade.php:3`, `users/show.blade.php:3` | Amélioration | Ajouter `@section('title', …)` identique au `header`. |
| Menu à plat sous un parent qui porte le nom de la marque (voir T6) | `sidebar.blade.php:33-40` | Important | Utiliser des en-têtes AdminLTE `<li class="nav-header">` : **Pilotage** (Tableau de bord, Activités) · **Pédagogie** (Quiz, Examens, Flashcards, Articles) · **Personnes** (Apprenants, Formateurs, Groupes, Demandes d'accès) · **Administration** (Utilisateurs, Administrateurs, Rôles, Permissions, Paramètres généraux, Mode découverte, Modules). |
| `aria-label="Main navigation"` (anglais) | `sidebar.blade.php:29` | Amélioration | `aria-label="Navigation principale"`. |
| Demandes d'accès : badge rouge `float-end` dans le `<p>` du lien, sans texte pour les lecteurs d'écran | `sidebar.blade.php:84-86` | Amélioration | Ajouter `<span class="visually-hidden">nouvelles demandes</span>`. |
| Vue « Hello World » du module encore servie par `CoreController` | `index.blade.php:2`, `app/Http/Controllers/CoreController.php:15` | Important | Rediriger vers `cores.dashboard` ou supprimer la route. |

### 3.2 Tableau de bord (`dashboard/index.blade.php`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| En-tête et fil d'Ariane en anglais (« Dashboard ») | `:3,7` | Bloquant (fait partie de T2) | « Tableau de bord ». |
| Indicateurs orientés technique (Rôles, Modules) au lieu du métier (apprenants actifs, quiz publiés, examens en cours, demandes d'accès en attente) | `:29-57` | Important | Remplacer les tuiles Rôles et Modules par des indicateurs pédagogiques, chacun avec son lien. |
| « Plus d'info » ×4 avec une icône de lien (`bi-link-45deg`) | `:23,38,53,68` | Amélioration | Libellés précis (« Voir les utilisateurs ») avec `bi-arrow-right-circle`. |
| Colonne « Sujet » affichée en `class_basename #id` (ex. « Quiz #12 ») : peu lisible | `:117` | Amélioration | Afficher le titre de l'objet, sinon un libellé traduit du type. |
| Feuilles de style jsvectormap et apexcharts chargées sans graphique | `:136-147` | Amélioration | Supprimer ces `@push('css')`. |
| Fil d'Ariane « Accueil » en `href="#"` | `:6` | Important (T7) | Pointer vers `route('cores.dashboard')`. |

### 3.3 Listes de gestion : Apprenants, Formateurs, Utilisateurs, Administrateurs, Rôles, Groupes

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Barre d'outils en icônes seules, infobulles invisibles sur les boutons désactivés (T4) | `learners/index.blade.php:18-41` et équivalents | Bloquant | Reprendre le modèle de `quizzes/index.blade.php:17-41` : icône et libellé (« Ajouter », « Modifier », « Réinitialiser le mot de passe », « Activer », « Désactiver », « Supprimer »), `d-flex flex-wrap gap-2`. Mettre la suppression à droite, séparée. |
| Abréviation « Réinitialiser MDP » | `learners/index.blade.php:27`, `users/index.blade.php:33`, `admins/index.blade.php:27`, `trainers/index.blade.php:27` | Important | « Réinitialiser le mot de passe ». |
| Couleurs incohérentes d'un écran à l'autre : « Ajouter » en `btn-primary` ici, en `btn-success` sur Quiz/Examens ; « Modifier » en `btn-info` (cyan) ; « Membres » en `btn-warning` | `learners/index.blade.php:18,21` contre `quizzes/index.blade.php:18,21` ; `groups/index.blade.php:27` | Important | Fixer une règle : création = `btn-primary`, actions neutres = `btn-outline-secondary`, activer/désactiver = `btn-outline-*`, suppression = `btn-outline-danger` à droite. |
| Tableaux de 10 à 11 colonnes (ID, Nom, Prénom, Identifiant, Email, Téléphone, Matricule…) sans adaptation mobile : aucun `data-mobile-responsive` ni `data-card-view` dans les 12 tableaux bootstrap-table | `learners/index.blade.php:59-70`, `trainers/index.blade.php:53-64` | Important | Ajouter `data-mobile-responsive="true" data-check-on-init="true"`. Masquer par défaut ID et Nom d'utilisateur (`data-visible="false"`), fusionner Nom et Prénom. |
| Statut : « Bloqué » pour les personnes, « Inactif » pour les groupes et contenus, alors que le bouton dit « Désactiver » | `learners/index.blade.php:95`, `trainers/index.blade.php:90` contre `groups/index.blade.php:86` | Important | Un seul vocabulaire : « Actif » / « Désactivé ». |
| URL écrite en dur dans le formateur de la colonne Apprenants (`/cores/groups/${row.id}/membres`) et badge `bg-black` | `groups/index.blade.php:94` | Amélioration | Utiliser `route('cores.groups.members', …)` (Ziggy) et `text-bg-secondary`. |
| Modales : classes Bootstrap 4 `form-group` sans `form-label` ni `mb-3` (34 occurrences), espacement compensé par un CSS local `.m-t-small` | `learners/_modal.blade.php:28-35,73`, `groups/_modal.blade.php`, `users/_modal.blade.php`, `admins/_modal.blade.php` | Important | Passer en Bootstrap 5 : `mb-3` et `label.form-label` ; supprimer `.m-t-small`. |
| Bouton afficher/masquer le mot de passe sans `aria-label` (11 occurrences) ; caméra d'avatar en `<label>` sans texte | `learners/_modal.blade.php:99,111,19-21`, `profile/index.blade.php:120,129,139`, `trainers/_modal.blade.php:109,119` | Important | `aria-label="Afficher le mot de passe"` (texte mis à jour au clic) ; `<span class="visually-hidden">Changer la photo</span>`. |
| Règle du mot de passe en abrégé (« Min 8 caractères, majuscule… ») et consigne « Laisser vide si vous ne voulez pas modifier » affichée aussi à la création | `learners/_modal.blade.php:84,103` | Amélioration | « Au moins 8 caractères, dont une majuscule, une minuscule, un chiffre et un symbole » ; n'afficher la consigne qu'en modification. |
| La règle « e-mail ou téléphone obligatoire » est sous le champ Téléphone seulement, alors que l'e-mail est marqué « (facultatif) » | `learners/_modal.blade.php:49,62` | Important | Indiquer « E-mail ou téléphone requis » au-dessus des deux champs, regroupés dans un `fieldset`. |

### 3.4 Quiz, Examens, Flashcards, Articles (listes)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Colonne « Note Max » illisible : `bg-purple` n'existe ni dans AdminLTE 4 ni dans Bootstrap 5, il reste donc du texte blanc sur fond blanc | `exams/index.blade.php:133` | Bloquant | `text-bg-secondary` (ou `text-bg-dark`), sans `text-white`. |
| Neuf boutons d'action sur une ligne (Examens) : sur mobile, ils passent sur trois lignes au-dessus du tableau | `exams/index.blade.php:17-45` | Important | Garder « Nouveau », « Modifier », « Questions » en boutons visibles ; regrouper Aperçu, Imprimer, Supervision, Activer/Désactiver et Supprimer dans un menu « Plus d'actions ». |
| Infobulles non initialisées sur Examens et Flashcards (pas de `new bootstrap.Tooltip`, contrairement aux 7 autres écrans) | `exams/index.blade.php:147-398` ; à comparer avec `public/js/modules/core/quizzes/index.js:23-25` | Amélioration | Initialiser les infobulles une fois dans `master.blade.php` pour toutes les pages. |
| Modale d'impression recopiée trois fois, avec des couleurs en dur différentes (`#1e6f5c` contre `#1a365d`) et « Aperçu Avant Impression » avec des majuscules à l'anglaise | `quizzes/index.blade.php:78-101`, `exams/index.blade.php:80-103`, `flashcards/index.blade.php:129-…` | Amélioration | Une seule partielle `_print_modal.blade.php` avec `btn-primary` ; « Aperçu avant impression (A4) ». |
| Flashcards : titre « Liste des paquets de cartes (Decks) », champ « Assigner aux groupes d'étudiants » | `flashcards/index.blade.php:14,111` | Important | « Paquets de cartes » ; « Assigner aux {{ term('groups') }} d'apprenants ». |
| Modale Flashcard : bouton « Fermer » alors que les autres modales disent « Annuler », boutons sans icône, pas d'erreur par champ | `flashcards/index.blade.php:120-121` | Amélioration | « Annuler » / « Enregistrer » avec icônes, comme dans `learners/_modal.blade.php:123-128`. |
| Modale Examen : section « Paramètres de Sécurité et Triche » ; `font-weight-bold` (Bootstrap 4, sans effet) sur 7 libellés | `exams/_modal.blade.php:84,90,97,104,124,128,135` | Important | « Sécurité et anti-triche » ; `fw-semibold`. |
| Examens : la durée vide s'affiche « — » alors que Quiz affiche « Illimitée » pour la même donnée | `exams/index.blade.php:125` contre `quizzes/index.blade.php:130` | Amélioration | Aligner sur « Illimitée ». |
| Examens : URL écrites en dur (`/cores/exams/...`, `/cores/editor/exams/...`) au lieu de `route()` | `exams/index.blade.php:231,272,320,355,371-388` | Amélioration | Utiliser Ziggy `route(...)` comme `QuizActions.js:27`. |

### 3.5 Éditeurs plein écran (`layouts/admin-editor.blade.php`, `admin/{quiz,exam,flashcard,article}/editor.blade.php`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Menu de l'éditeur Quiz en anglais (« Overview », « Settings », « Preview », « Help Center » qui ne mène nulle part) ; Examens, Flashcards et Articles sont en français : incohérent | `admin/quiz/editor.blade.php:31,43,49,55-57` contre `admin/exam/editor.blade.php:34-59` | Bloquant (T2) | Reprendre le menu de l'éditeur Examen : « Retour à la liste », « Questions », « Paramètres », « Prévisualiser » ; supprimer « Help Center ». |
| Badge « Draft Mode » | `admin/quiz/editor.blade.php:18`, `admin/exam/editor.blade.php:23,517`, `admin/flashcard/editor.blade.php:42` | Bloquant (T2) | « Brouillon ». |
| Titre « Quiz Editor - … » / « Exam Editor - … » | `admin/quiz/editor.blade.php:3,5`, `admin/exam/editor.blade.php:3,5` | Bloquant (T2) | « Édition du quiz — … » / « Édition de l'examen — … ». |
| Barre secondaire : « Dashboard », « Quizzes », recherche sans effet ; « Nouveau Quiz » renvoie à la liste | `admin/quiz/editor.blade.php:68-77`, `admin/exam/editor.blade.php:69-79`, `admin/article/editor.blade.php:58-62` | Bloquant (T3) | Remplacer la barre par un fil d'Ariane et les actions réelles (Prévisualiser, Imprimer). Supprimer la recherche, ou la brancher sur le filtre des questions. |
| Boutons « Aide » et « Notifications » sans fonction, avec une pastille rouge permanente | `layouts/admin-editor.blade.php:93-101` | Bloquant (T3) | Les supprimer. |
| Avatar de secours repris de la démo AdminLTE (`adminlte/assets/img/avatar5.png`) au lieu de `avatar_url` | `layouts/admin-editor.blade.php:105` | Important | `Auth::user()->avatar_url`, comme dans `navbar.blade.php:36`. |
| Le badge du rôle affiche le nom technique (`roles->first()->name`, ex. « super-admin ») | `layouts/admin-editor.blade.php:89` | Amélioration | Correspondance vers un libellé (« Super administrateur »). |
| Menu de l'éditeur générique (« Dashboard / Quizzes / Articles ») qui ne correspond à aucun éditeur réel | `layouts/admin-editor.blade.php:53-70` | Amélioration | Remplacer par « Retour au tableau de bord ». |
| Type de question affiché brut (`str_replace('_',' ', type)`, ex. « mcq », « true false ») | `admin/quiz/editor.blade.php:134`, `admin/exam/editor.blade.php:135` | Important | Correspondance vers « QCM », « Vrai / Faux », « Texte à trous », « Appariement », « Ordonnancement », « Texte libre », comme dans les cartes du sélecteur (`:259-288`). |
| « Réordonner » sans gestionnaire JS (le glisser-déposer passe par la poignée) | `admin/quiz/editor.blade.php:113`, `admin/exam/editor.blade.php:114` | Important (T3) | Supprimer le bouton, ou lui faire activer un mode « réorganisation » explicite. |
| Sélecteur de type de question en `<div class="type-select-card">` : pas accessible au clavier | `admin/quiz/editor.blade.php:259-288` | Important | `<button type="button">` avec `aria-describedby` sur la description. |
| Boutons de suppression d'option, de paire ou de ligne en icône seule, sans `title` ni `aria-label` | `admin/exam/editor.blade.php:572,595,615` | Important | `aria-label="Supprimer cette option"` et `title`. |
| Bouton « Paragraphe » de la barre d'outils vide (`bi-type-paragraph` n'existe pas) ; « Supprimer le lien » simulé par un `text-decoration: line-through` sur une icône, ce qui ne s'affiche pas | `admin/article/editor.blade.php:105,120-122` | Bloquant (T1) | `bi-paragraph` ; `bi-link-45deg` remplacé par une icône barrée native (`bi-x-circle`), ou un libellé « Retirer le lien ». |
| Styles en ligne répétés (`border-radius: 8px`, `background-color: var(--green-dark)`) sur presque chaque bouton | `admin/quiz/editor.blade.php:110-117,125` | Amélioration | Une classe `.btn-editor` dans `quiz-editor.css`. |
| « Enregistrement automatique activé » affiché en permanence, sans état « Enregistrement… » / « Enregistré à 14 h 32 » | `admin/quiz/editor.blade.php:195-197` | Amélioration | Mettre à jour `#autosaveStatus` à chaque sauvegarde (`aria-live="polite"`). |

### 3.6 Demandes d'accès (`access-requests/index.blade.php`)

C'est l'écran le plus abouti du back-office (indicateurs, onglets de statut avec compteurs, recherche avec `aria-label`, panneau latéral, état « Chargement… »). Il peut servir de modèle.

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Lignes cliquables (`cursor: pointer`) sans équivalent clavier, en plus du bouton « Ouvrir » | `access-requests/index.blade.php:88` | Amélioration | Garder le bouton seul comme cible, ou rendre la ligne focusable. |
| Huit onglets de statut sur une ligne : très large sur mobile | `:30-39` | Amélioration | Sous 576 px, un `<select>` de statut. |
| Fil d'Ariane réduit à un niveau | `:6-8` | Important (T7) | Ajouter « Accueil » vers le tableau de bord. |

### 3.7 Paramètres généraux et Mode découverte (`settings/general.blade.php`, `showcase/settings.blade.php`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Pas de fil d'Ariane | `settings/general.blade.php:1-6`, `showcase/settings.blade.php:1-6` | Important (T7) | « Accueil › Administration › … ». |
| Le « Mode découverte » n'est pas expliqué en haut de page | `showcase/settings.blade.php:9-14` | Important | Ajouter un paragraphe d'introduction (« Permet aux visiteurs d'essayer des contenus vitrine sans compte et de demander un accès »). |
| Champ « Montant » sans unité ; « Accroche » et « Instructions » sans aide | `showcase/settings.blade.php:43-44,16-17,62-63` | Amélioration | Groupe `input-group` avec la devise ; `form-text` d'exemple. |
| Bouton « Enregistrer » de Paramètres généraux sans icône, contrairement aux modales | `settings/general.blade.php:45` | Amélioration | `<i class="bi bi-check2"></i> Enregistrer`. |

### 3.8 Connexion back-office (`auth/login.blade.php`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Champs sans `<label>` (placeholder seul) | `:25,31` | Important | `label.visually-hidden` ou `form-floating`. |
| Bascule du mot de passe sur une `<div>` : pas focusable ni annoncée | `:32-34` | Important | `<button type="button" aria-label="Afficher le mot de passe">`. |
| Icône enveloppe pour un champ qui accepte aussi un téléphone ou un identifiant | `:25-28` | Amélioration | `bi-person` ; uniformiser sur Bootstrap Icons. |

### 3.9 Membres d'un groupe (`groups/members.blade.php`)

Bon niveau : en-tête avec indicateurs, onglets avec compteurs, bouton retour avec `title`.

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Bouton retour en icône seule, sans `aria-label` | `groups/members.blade.php:25-27` | Amélioration | Ajouter `aria-label` ou le libellé « Retour ». |
| Fil d'Ariane sans racine « Accueil » | `:6-9` | Amélioration | L'ajouter. |

---

## 4. Constats par écran — PWA apprenant

### 4.1 Cadre de l'application (`components/app-shell.ts`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| La navigation dit « Accueil », le titre de l'écran dit « Tableau de bord » | `app-shell.ts:24` contre `main.ts:45` | Important | Un seul terme : « Accueil » partout, ou « Tableau de bord » partout. |
| Lien actif signalé seulement par la couleur (`text-sky-600`, `bg-sky-50`), sans `aria-current="page"` | `app-shell.ts:188-197` | Important | `a.setAttribute('aria-current', active ? 'page' : 'false')` ; en barre d'onglets, ajouter un trait au-dessus de l'icône active. |
| Sur mobile, l'état de synchronisation n'est qu'une pastille de couleur (texte `hidden sm:inline`) | `app-shell.ts:146,276` | Important | Garder la pastille mais ajouter `<span class="sr-only">` et un `title` (« Synchronisé » / « Hors-ligne »). |
| `<aside>` desktop et barre d'onglets sans `<nav aria-label>` ; icônes sans `aria-hidden` ; badges de compteur sans texte accessible | `app-shell.ts:253-255,300-302,219-220,230-231` | Amélioration | `<nav aria-label="Navigation principale">`, `aria-hidden="true"` sur les `<i>`, `aria-label="3 éléments à faire"` sur le badge. |
| Bouton de thème de 36 px (`w-9 h-9`), sous la cible tactile recommandée de 44 px | `app-shell.ts:283-284` | Amélioration | `w-11 h-11`. |

### 4.2 Accueil (`views/dashboard.ts`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Émojis 👋 🔥 🏅 à côté des Bootstrap Icons : le rendu dépend du système et le style reste ludique (prévu par la spec §48-49) | `dashboard.ts:96,114,117` | Amélioration | `bi-fire` / `bi-award` avec teinte, ou garder les émojis mais seulement dans la carte d'accueil (à valider avec le client, la spec les impose). |
| Tuile « Decks » (anglais) en mode découverte | `dashboard.ts:220` | Important | « Paquets de cartes » ou « Cartes ». |
| Barre de progression du niveau sans `role="progressbar"` ni `aria-valuenow` | `dashboard.ts:108-110` | Amélioration | Ajouter les attributs ARIA. |
| La section « Continuer » liste aussi des quiz « Nouveau » jamais commencés | `dashboard.ts:142,159` | Amélioration | Intituler « À faire » ; garder « Continuer » si tous sont `in_progress`. |

### 4.3 Entraînement (`views/entrainement.ts`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Onglet « Flashcards », état vide « Aucun deck de révision… Les decks… », compteur « maîtrisée(s) » : termes mélangés | `entrainement.ts:164,139,58` | Important | « Cartes » ; « Aucun paquet de cartes » ; `pluralize(mastered,'maîtrisée')`. |
| États vides en émoji (🃏 ❓), alors que les icônes de carte sont des Bootstrap Icons | `entrainement.ts:110-111,139,148` (et `articles.ts:61` 📚, `examens.ts:89` 🎓) | Amélioration | Composant `emptyState(icon)` partagé avec `bi-stack`, `bi-patch-question`, `bi-journal-text`, `bi-mortarboard`, dans un cercle teinté. |
| Puces de filtre d'environ 28 px de haut (`py-1.5 text-xs`) | `entrainement.ts:124-129` | Amélioration | `py-2.5 text-sm` (au moins 40-44 px). |
| Onglets `role="tab"` sans `aria-controls` ni conteneur `role="tabpanel"` | `entrainement.ts:157-167` | Amélioration | Ajouter `aria-controls="training-content"` et `role="tabpanel"`. |

### 4.4 Lecture de quiz (`views/quiz-play.ts`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| « Terminer » valide sans confirmation, même s'il reste des questions sans réponse (l'examen, lui, demande confirmation : `exam-play.ts:464-469`) | `quiz-play.ts:464-467` | Important | Si `answered < total`, `confirmDialog('Terminer le quiz ?', 'Il reste N question(s) sans réponse.')`. |
| Minuteur sans libellé (« 04:59 » seul) et passage au rouge sans annonce | `quiz-play.ts:336,134-137` | Amélioration | `aria-label="Temps restant"`, icône `bi-stopwatch` ; une annonce `aria-live` à 1 minute. |
| Barre de progression sans attributs ARIA | `quiz-play.ts:331-333` | Amélioration | `role="progressbar"` et valeurs. |

### 4.5 Passage d'examen (`views/exam-play.ts`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Boutons « Précédent » et « Suivant » en flèche seule, sans `aria-label`, alors qu'ils ont un libellé dans le quiz | `exam-play.ts:362-369` contre `quiz-play.ts:352-359` | Important | Reprendre les libellés du quiz (« Précédent », « Suivant »). |
| Pas de vue d'ensemble des questions : impossible de revenir directement à une question sautée | `exam-play.ts:340-372` | Important | Grille de numéros (répondue, non répondue, courante) dans une feuille dépliante. |
| Libellé « Surveillé » en ambre `text-amber-600` sur fond clair : contraste limite (environ 3:1) pour du texte de 12 px | `exam-play.ts:343-345` | Amélioration | `text-amber-700` (ou `-800`). |

### 4.6 Lecture d'article et révision (`views/article-detail.ts`, `views/reviser.ts`)

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Bouton « Favori » sans `aria-pressed` : l'état n'est pas annoncé ; cible de 36 px | `article-detail.ts:41-42` | Amélioration | `aria-pressed`, `w-11 h-11`. |
| Notation « 1 étoiles » (accord) | `article-detail.ts:77` | Amélioration | `${i} étoile${i > 1 ? 's' : ''}`. |
| États « Article / Quiz / Deck / Examen introuvable » sans lien de retour | `article-detail.ts:23`, `quiz-detail.ts:20`, `reviser.ts:38`, `exam-play.ts:30`, `quiz-play.ts:56` | Important | Ajouter un bouton « Retour à la liste », sur le modèle de `exam-play.ts:42` ; « Paquet introuvable ». |

### 4.7 Connexion et formulaires (`views/login.ts`, `profil.ts`, `mot-de-passe.ts`, `demande-acces.ts`)

Libellés présents et `autocomplete` correct. Les formulaires de la PWA sont bien faits.

| Constat | Preuve | Sévérité | Correction proposée |
|---|---|---|---|
| Zone d'erreur `#login-error` sans `role="alert"` : l'erreur n'est pas annoncée | `login.ts:36` | Important | `role="alert"` (ou `aria-live="assertive"`). |
| Pas d'option pour afficher le mot de passe (le back-office en a une) | `login.ts:30-35` | Amélioration | Bouton œil avec `aria-label`. |
| Aucune indication en cas de mot de passe oublié (ni PWA ni back-office) | `login.ts:20-50`, `auth/login.blade.php:20-50` | Amélioration | Texte « Mot de passe oublié ? Contactez votre formateur » (la réinitialisation est faite par l'administration). |

---

## 5. Plan d'action proposé

### Lot 1 — Corrections rapides (moins d'une journée)

Uniquement des libellés, des classes CSS et des attributs, sans changement de structure.

1. **Icônes cassées** : `bi-print` → `bi-printer` (5 fichiers), `bi-desktop` → `bi-display`, `bi-type-paragraph` → `bi-paragraph`, `bi-arrow-right-left` → `bi-arrow-left-right` (T1).
2. **Franciser l'interface** : Dashboard → Tableau de bord, Profile → Mon profil, Se deconnecter → Se déconnecter, Draft Mode → Brouillon, Quiz/Exam Editor → Édition du quiz/de l'examen, Overview/Settings/Preview → Retour à la liste/Paramètres/Prévisualiser, Quizzes → Quiz, Supervision Live → Supervision en direct, `aria-label` Close/Main navigation/Toggle Sidebar → Fermer/Navigation principale/Afficher le menu, `alt="User Image"`, « tous droits réservés » (T2).
3. **Supprimer les contrôles factices** : recherche de la barre du haut, Aide, Notifications, Help Center, recherches des éditeurs, « Réordonner », « Nouveau Quiz » (T3).
4. **Colonne « Note Max »** : `bg-purple` → `text-bg-secondary` (`exams/index.blade.php:133`).
5. **Fil d'Ariane** : `href="#"` → `route('cores.dashboard')` (15 vues) ; ajout sur Paramètres généraux, Mode découverte, Profil ; `@section('title')` sur les 7 vues qui n'en ont pas.
6. **Vocabulaire** : « Réinitialiser le mot de passe », « Bloqué » → « Désactivé », « étudiants » → apprenants, « Decks » → Paquets de cartes (back-office et PWA), « Paramètres de Sécurité et Triche » → « Sécurité et anti-triche », « 1 étoiles », « maîtrisée(s) ».
7. **Classes Bootstrap 4 sans effet** : `font-weight-bold` → `fw-semibold`.
8. **Accessibilité de base** : `aria-label` sur les bascules de mot de passe, les flèches de l'examen, les boutons poubelle des éditeurs ; `role="alert"` sur l'erreur de connexion de la PWA ; `aria-current` dans la navigation de la PWA.
9. **Supprimer** la vue « Hello World » (`CoreController@index` → redirection) et les CSS de graphiques inutilisés du tableau de bord.

### Lot 2 — Harmonisation (2 à 4 jours)

1. **Barres d'outils unifiées** sur les 10 listes : icône et libellé, règle de couleurs (création = primaire, neutres = contour, suppression = `outline-danger` à droite), menu « Plus d'actions » au-delà de 4 boutons (T4).
2. **Une seule famille d'icônes au back-office** : passer les ~215 occurrences Font Awesome sur Bootstrap Icons (déjà utilisé par la PWA), puis retirer `fontawesome/css/all.min.css` des deux gabarits. Écrire une table de correspondance « notion → icône » (Apprenant, Formateur, Groupe, Quiz, Examen, Cartes, Article) et l'appliquer au back-office comme à la PWA (T5).
3. **Menu regroupé** en 4 sections avec `nav-header` (T6).
4. **Retours d'action** : succès en toast SweetAlert2 non bloquant (`toast: true, timer: 2500`), erreurs gérées partout (activation et suppression des examens), erreurs par champ dans les modales Examen et Flashcard (T8).
5. **Modales Bootstrap 5** : `form-group` → `mb-3` et `form-label`, une seule partielle d'impression, « Annuler / Enregistrer » avec icônes partout.
6. **Tableaux adaptés au mobile** : `data-mobile-responsive`, colonnes secondaires masquées par défaut.
7. **Éditeurs** : libellés lisibles pour les types de question, sélecteur de type en `<button>`, état d'enregistrement automatique réel, avatar et rôle corrects dans la barre du haut.
8. **PWA** : confirmation de fin de quiz si des questions restent sans réponse, composant d'état vide à icône, liens de retour sur les états « introuvable », cibles tactiles de 44 px (thème, favori, filtres), ARIA des barres de progression.

### Lot 3 — Refonte ciblée (1 à 2 semaines)

1. **Charte commune** : choisir une couleur primaire unique pour le back-office et la PWA, aligner `theme-color` (`master.blade.php:12`) et `learner/app.blade.php:7`, séparer nettement `primary` et `success` dans `theme-custom.css`, définir les jetons dans un seul fichier (T9).
2. **Tableau de bord métier** : indicateurs pédagogiques (apprenants actifs, taux de réussite, examens en cours, demandes en attente) et activités lisibles (titre de l'objet).
3. **Gabarit d'éditeur unique** pour Quiz, Examens, Flashcards et Articles : même menu, même barre d'actions, même fil d'Ariane, styles en ligne remplacés par des classes.
4. **Examen dans la PWA** : vue d'ensemble des questions, minuteur annoncé, contraste du bandeau de surveillance.
5. **Émojis de la PWA** : avec le client, décider de les remplacer par des icônes, puis mettre à jour `SPECIFICATIONS_VOLET_APPRENANT.md` en conséquence.
6. **Recette accessibilité** : passage axe ou Lighthouse sur les 10 écrans principaux des deux volets, puis un test au clavier seul.
