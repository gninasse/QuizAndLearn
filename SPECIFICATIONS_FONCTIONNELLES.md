# Spécifications Fonctionnelles — QuizAndLearn

> Application web d'apprentissage et d'évaluation (quiz, examens, flashcards, articles),
> structurée en multi-modules Laravel 12, avec un back-office administratif et un volet
> apprenant au format PWA (mobile-first, hors-ligne).
>
> - **Stack** : Laravel 12, PHP 8.3, PostgreSQL, Tailwind CSS v4, AdminLTE (back-office),
>   JavaScript vanilla + IndexedDB + Service Worker (PWA apprenant), Spatie Laravel Permission,
>   Spatie Activity Log, `nwidart/laravel-modules`.
> - **Module principal** : `Core` (`Modules/Core`), unique module actif (`modules_statuses.json`).
> - **Conventions** : RBAC par permissions Spatie groupées par module ; journalisation d'audit sur
>   toutes les mutations sensibles.

---

## 1. Vue d'ensemble et architecture

L'application propose **deux interfaces distinctes** partageant la table `users` :

| Interface | Public | Technologie | Point d'entrée |
| --- | --- | --- | --- |
| **Back-office (Volet Admin)** | Administrateurs, formateurs, gestionnaires | Server-rendered (AdminLTE + Blade) | `/cores/*` (auth requise) |
| **Volet Apprenant (PWA)** | Apprenants | SPA JavaScript hors-ligne (vanilla JS + IndexedDB + Service Worker) | `/` (auth + middleware `learner`) |

Le back-office gère le **contenu pédagogique** (quiz, examens, decks de flashcards, articles),
les **utilisateurs** et le **contrôle d'accès** (rôles, permissions, modules). Le volet apprenant
permet de **consommer** ce contenu, de suivre sa progression et d'accumuler de la **gamification**
(XP, niveaux, séries, badges).

### 1.1 Composants fonctionnels majeurs

1. **Gestion des identités & accès** (utilisateurs, admins, formateurs, apprenants, groupes)
2. **Rôles, Permissions & Modules** (RBAC modulaire, audit)
3. **Éditeur de Quiz** (builder de questions multi-types)
4. **Éditeur d'Examens** (questions + proctoring / anti-fraude)
5. **Éditeur de Decks de Flashcards** (y compris génération automatique)
6. **Éditeur d'Articles** (riche, SEO, médias protégés, export HTML)
7. **Volet Apprenant PWA** (dashboard, quiz, articles, révision, examens, profil)
8. **Gamification & motivation** (XP, niveaux, séries, badges, classement)
9. **Moteur de répétition espacée SM-2** (flashcards)
10. **Système de modules** (installation/activation/désactivation à chaud)

---

## 2. Acteurs, rôles et profils

### 2.1 Base d'authentification
Tous les comptes sont des `User` (`users`). Un `User` peut posséder un ou plusieurs **profils**
indépendants des rôles :

- **Profil `learner`** → table `learners` (matricule, groupes). Obligatoire pour accéder au volet apprenant.
- **Profil `trainer`** → table `trainers` (spécialité, biographie, groupes).
- Un utilisateur peut être les deux, ou aucun.

### 2.2 Rôles (Spatie, orthogonaux aux profils)
Rôles amorcés (`InitRolesCommand`) : `super-admin`, `admin`, `trainer`, `learner`.
- `super-admin` : accès global, protégé (non supprimable, non modifiable).
- L'**autorisation est par permission** (`can()`), groupée par module via le trait
  `HasModulePermissions`, et vérifiée **par requête** dans chaque contrôleur
  (`authorizeQuiz`/`authorizeExam`/... + contrôle de périmètre formateur).

### 2.3 Middleware de protection
- `auth` : session staff.
- `learner` (`EnsureUserIsLearner`) : exige `Auth::user()->learner()->exists()` ; sinon 403 (API)
  ou déconnexion + redirection vers `learn.login` (web).
- `is_active=true` est la porte globale des deux flux de connexion.

---

## 3. Authentification

### 3.1 Connexion staff (`AuthController`)
- `GET /login`, `POST /login`. Identifiant = email **ou** `user_name` (détecté via `FILTER_VALIDATE_EMAIL`).
- Conditions : compte `is_active=true`. **Aucun contrôle de rôle** (l'accès est filtré par permission ensuite).
- Redirection vers `cores.dashboard` en cas de succès ; `POST /logout` pour la déconnexion.

### 3.2 Connexion apprenant (`LearnerAuthController` / `LearnerSpaController`)
- Pages : `GET /` (shell SPA) et `GET /login-learner` (login blade legacy).
- Endpoints : `POST /login-learner`, `POST /api/login`, `POST /api/logout`.
- Même logique d'identifiant, **+ exigence d'un profil `learner`** (sinon déconnexion forcée avec message
  « Ce compte n'est pas configuré comme un compte apprenant. »).
- `logLogin()` / `logLogout()` écrivent dans le journal d'activité (`module='core'`,
  `ip_address`, `user_agent`).

---

## 4. Volet Administration (Back-office)

UI commune : **Bootstrap Table** côté serveur (endpoints `…/data` : recherche/tri/pagination),
**modales** pour le CRUD, **Select2** pour les multi-sélections, **SweetAlert2** pour confirmations.

### 4.1 Tableau de bord (`DashboardController` → `cores.dashboard`)
- 4 widgets (small-box AdminLTE) : Utilisateurs, Rôles, Modules, Activités du jour.
- Chaque widget lien vers sa section ; tableau « Dernières Activités » (10 dernières, avec icône + badge d'action).
- *Note* : les assets ApexCharts/jsvectormap sont chargés mais **aucune série de graphique n'est peuplée**
  (stats purement textuelles).

### 4.2 Gestion des utilisateurs
Contrôleurs par typologie, pattern identique (index datatable → getData → store/update/destroy →
toggleStatus → resetPassword → show + gestion rôles/permissions).

| Contrôleur | Périmètre | Spécificités |
| --- | --- | --- |
| `UserController` | Tous les users | Recherche nom/prénom/login/email/service ; avatar → `public/avatars` ; reset vers mot de passe par défaut (`core.user_default_password`) ; **blocage auto-suppression/auto-desactivation** ; gestion des rôles/permissions depuis la page `show` (`assignRole`, `removeRole`, `assignPermissions`, `removePermission`). |
| `AdminController` | `role('admin')` | Empêche un admin de changer **son propre rôle** ; un non-super-admin ne peut supprimer un super-admin. |
| `TrainerController` | `role('trainer')` | Crée le profil `Trainer` (spécialty/biography) + synchronise ses groupes. Tri/saisie par spécialité. |
| `LearnerController` | `role('learner')` | Crée le profil `Learner` (matricule) + groupes. Recherche/tri par matricule. |
| `GroupController` | Groupes | `name, description, start_date, end_date, is_active` ; `getMembers` (formateurs + apprenants + quiz assignés) ; `assignMembers` (sync formateurs/apprenants, validation `end_date >= start_date`). Périmètre formateur : ne voit **que ses propres groupes**. |

### 4.3 Éditeur de Quiz

Deux surfaces :
- **`QuizController`** : CRUD de base + *builder* legacy (`quizzes.builder`).
- **`QuizEditorController`** : éditeur complet (`admin.quiz.editor`).

**Cycle de vie d'un quiz**
- `store` : `title, description, duration, passing_score, is_active` (défaut true), `created_by`, groupes assignés.
- **Autosave** AJAX (`title, description, duration, passing_score, shuffle_questions`).
- **Publish/Draft** (`toggleActive`) ; **réordonnancement** des questions (`reorderQuestions`).
- **Aperçu** (sélecteur de device) → `preview-iframe` (lecteur) → `print-iframe` (imprimable).
- **Assignation de groupes** (`assignGroup`/`unassignGroup`), avec respect du périmètre formateur (403 sinon).

**Questions (`Question`)**
Types exposés par l'UI : `true_false, mcq, fill_blank, matching, ordering, open_text`
(le code de notation accepte en plus `single_choice`/`multiple_choice` comme alias MCQ).
Champs : `question_text, type, points, order, options (array)`. Les questions de quiz héritent
d'un `order` calculé (max+1) ; suppression en cascade des `<img>` embarquées.

**Particularités**
- `shuffle_questions` : mélange à la lecture côté apprenant.
- `max_attempts, show_correct_answers, allow_partial_score, available_from/until`.

### 4.4 Éditeur d'Examens (`ExamController`)

Similaire aux quiz, **avec dimensions de sécurité et notation** :

- **Stockage** : `Exam` (`title, description, duration, passing_score, max_attempts, available_from/until,
  note_max, created_by`) + flags anti-fraude : `plein_ecran_force, anti_capture_strict, navigation_interdite`.
- **Publication des résultats** : `publication_resultats ∈ {immediate, apres_fermeture, manuelle}`.
- **Classement** : `classement_visible, classement_anonyme`.
- **Questions d'examen** (`ExamQuestion`) : mêmes types que quiz + **`points_negatifs`** (notation négative possible).
- Éditeur (`admin.exam.editor`), aperçu, impression, réordonnancement.

**Supervision / proctoring (trainer/admin)**
- `supervision` → vue `admin.exam.supervision` (Bootstrap Table live).
- `getSupervisionData` : flux AJAX par tentative — apprenant, heure de début, durée réelle,
  score brut/total, note/20, **`capture_attempts`**, **`navigation_violations`**, statut.

### 4.5 Decks de Flashcards (`FlashcardDeckController`)

- **Deck** (`FlashcardDeck`) : `titre, description, matiere, source_type, source_id, is_public,
  algorithme ∈ {sm2, leitner}, easiness_default, interval_min/max, active, created_by` + groupes.
- **Cartes** (`FlashcardItem`) : `recto, verso, recto_media (JSON), verso_media (JSON), tags, note, ordre,
  total_revisions, taux_reussite`.
- **Génération automatique** (`autoGenerate`) : depuis une source `quiz | examen | article`.
  - Quiz/Examen : chaque question → carte (verso formaté selon le type).
  - Article : extraction regex de paires `Terme : Définition` (markdown), repli sur une carte unique.
- Éditeur (`admin.flashcard.editor`), aperçu, impression, assignation de groupes.

### 4.6 Éditeur d'Articles (`ArticleController` + `ArticleEditorController`)

- **Article** (`Article`) : `title, content, is_active, category, seo_description, seo_keywords,
  estimated_reading_time, available_from/until, created_by` + médias (`ArticleMedia`) et groupes.
- **Autosave** : `title, content, category, seo_description, seo_keywords, is_active` (champs SEO).
- **Médias** (`uploadMedia`) : image ≤5 Mo **ou** audio (mp3/wav/ogg/m4a/flac ≤10 Mo) → `articles/media`
  + ligne `ArticleMedia`. Consommé par l'éditeur riche.
- **Embed de quiz** : `searchQuizzes` propose des quiz actifs à intégrer dans l'article.
- **Assignation de groupes** (périmètre formateur respecté) ; publish/draft.
- **Export HTML sécurisé** (`export`) : fichier autonome, images inline en **Base64**, audio/vidéo
  durcis (`controlsList="nodownload"`, `disablePictureInPicture`, `oncontextmenu` bloqué) + script
  bloquant Ctrl/Cmd+S/U et F12. Téléchargé sous `slug(title).html`.

### 4.7 Rôles, Permissions, Modules

**Rôles (`RoleController`)**
- CRUD ; matrice de permissions groupées par module ; `togglePermission` (don/revient, super-admin protégé).
- Suppression/modification de `super-admin` **bloquées**.

**Permissions (`PermissionController`)**
- **Matrice de permissions** : défaut 10 premiers rôles + module `core` ; sélecteurs rôles/modules,
  `toggle` (don/revient, super-admin protégé).

**Modules (`ModuleController` + `ModuleService` + `PermissionService`)**
- `index/show` : modules DB + modules détectés non installés ; stats (permissions, utilisateurs, dépendances).
- `install` : crée la ligne DB (`is_active=false`, `version=1.0.0`), lance `module:migrate`, puis le
  `<Slug>PermissionsSeeder` (tolérance d'échec).
- `enable/disable` : active/désactive (dépendances activées en amont pour `enable` ; `canBeDeactivated()`
  refuse si requis ou dépendance d'un module actif).
- `uninstall` : refuse si `is_required`/actif ; supprime les permissions du module + la ligne DB
  (rollback de migration intentionnellement désactivé).
- `configure` : fusionne `config` avec `Config/config.php` (mise à jour stub).
- `syncPermissions` : lance `core:sync-permissions`.
- Les permissions sont lues depuis `config/permissions.php` (catégorisation automatique
  view/create/delete/manage/assign/enable/…) et mises en cache Spatie.

### 4.8 Journal d'activité / Audit (`ActivityController`)

- Modèle `Activity` (étend Spatie) + colonnes `module, context, causer_roles, expires_at,
  retention_months, ip_address, user_agent`, accesseurs `icon`/`badge_color`.
- `index` + `getData` : **filtres riches** — module, utilisateur (causeur), rôle (`whereJsonContains
  causer_roles`), type d'action (description), log_name, subject_type/id, **ip** (colonne ou
  `properties->ip`), type de causeur (système/utilisateur), plage de dates. Tri par défaut décroissant.
- Lecture seule ; purgé par `CleanupExpiredActivitiesCommand`.

---

## 5. Volet Apprenant (PWA)

### 5.1 Architecture & hors-ligne
- Shell SPA : `learner/spa.blade.php` (JS vanilla, **Service Worker** `/service-worker.js`,
  **IndexedDB `LearnQuizDB` v2** avec stores : `profile, articles, quizzes, flashcards, badges,
  attempts, sync_queue, exams`).
- **Router par hash** : `#dashboard #articles #article-detail/{id} #quizzes #quiz-detail/{id}
  #quiz-play/{id} #reviser #profil #exams #exam-play/{id}`.
- **Mode hors-ligne** : au démarrage, login depuis le profil en cache si hors-ligne ; `loadInitialData()`
  fetch `/api/init` (en ligne) sinon lit IndexedDB ; `triggerSync()` POST la `sync_queue` vers
  `/api/sync` puis la vide. **Bannière online/offline** + badge de synchronisation.
- *Note* : des pages blade serveur legacy existent aussi (`learn.*`, `*-legacy`) mais le défaut est le SPA.

### 5.2 Init / Sync (`LearnerSpaController`)
- `GET /api/init` : renvoie tout le nécessaire hors-ligne — `user` (+ `xp`), `articles`
  (contenu, favori, note, progression), `quizzes` (questions+options, historique de tentatives,
  statut calculé `unread|in_progress|completed`, `max_attempts_reached`), `flashcards` (question/réponse/
  dernière révision), `badges` (tous + débloqués), `exams` (fenêtres de dispo, flags de sécurité, statut
  `locked|available|expired|in_progress|completed`, tentatives).
- `POST /api/sync` : traite un tableau d'actions hors-ligne — `article_progress` (+15 XP si complété,
  `time_spent+10`), `article_favorite`, `article_rate`, `card_evaluation` (+5 XP, `last_reviewed_at`),
  `quiz_attempt` (notation serveur side, voir §6). Recalcule XP/niveau/série, appelle `checkBadges()`,
  renvoie `xp` + `badges_unlocked`.

### 5.3 Quiz (côté apprenant)
- `LearnerQuizController` : `startAttempt` (crée/reprend une tentative `in_progress`), `submitAnswer`
  (sauvegarde AJAX par question via `updateOrCreate` `QuizAnswer`), `completeAttempt` (notation serveur side).
- Types notés : `true_false, mcq/single_choice/multiple_choice` (flag `multiple` + `partial_score`
  optionnel), `fill_blank` (par trou, sensibilité casse), `matching`, `ordering`, `open_text`.
- Score = `points_obtenus / points_totaux × 100` ; `passed` si ≥ `passing_score` (défaut 60).
- Sécurité : `logScreenshot` (crée `ScreenshotAttempt`), `reportError` (crée `ErrorReport` sur le quiz).

### 5.4 Articles (côté apprenant)
- `LearnerArticleController` : `updateProgress` (complété à ≥80 % → +15 XP), `rateArticle` (1–5),
  `toggleFavorite`, `reportError`.
- SPA : injection HTML du contenu, favori, notation étoile, barre de progression de lecture,
  bouton « Marquer comme lu (+15 XP) ».

### 5.5 Révision Flashcards — algorithme SM-2 (`LearnerCardController`)
- `index` **auto-amorce** les `Flashcard` de l'apprenant depuis les questions des quiz de ses groupes
  (`difficulty_factor=2.5, interval_days=0, repetitions=0, next_review_date=aujourd'hui, ease_rating='new'`).
- `evaluateCard` implémente **SM-2** (note qualité `q` ∈ 0–5) :
  - `q < 3` → `repetitions=0`, `interval=1` jour.
  - `q ≥ 3` → incrémente répétitions ; intervalle : **1 j** (1ʳᵉ réussite), **6 j** (2ᵉ),
    puis **`round(interval × EF)`**.
  - `EF' = EF + (0.1 − (5−q)(0.08 + (5−q)·0.02))`, **plancher 1.3**.
  - `next_review_date = aujourd'hui + interval` ; `+5 XP` par révision.
- SPA : cartes retournables « Je savais / Je ne savais pas » → `POST /reviser/evaluate`.

### 5.6 Examens sécurisés (côté apprenant)
- `startExamAttempt` : vérifie groupe, fenêtre de disponibilité, `max_attempts` ; `firstOrCreate`
  tentative `en_cours` ; renvoie les questions **sans les bonnes réponses** (mcq : `is_correct` retiré,
  true_false : `correct_answer` retiré, matching/ordering mélangés) + `elapsed/remaining` + flags de sécurité.
- `submitExamAnswer` : autosave des réponses ; `completeExamAttempt` → `evaluateAttempt` (notation
  true_false/mcq/fill_blank `[[..]]`/matching/ordering/open_text, applique `points_negatifs`,
  `scoreBrut = max(0, …)`, `note_sur_vingt = pourcentage/100 × note_max`). **+50 XP** si réussi.
- **Classement** : par tentative, `note_sur_vingt` décroissant puis `duree_reelle` croissante ;
  renvoyé comme `rank` + `total_participants` (governé par `classement_visible`/`classement_anonyme`).
- `logExamSecurityViolation` (`screenshot` | `navigation`) :
  - `screenshot` → `capture_attempts++` ; si `anti_capture_strict` → **annulation immédiate** (`status='annule'`).
  - `navigation` → `navigation_violations++` ; si `navigation_interdite` et **≥3 violations** → annulation.

### 5.7 Profil & préférences (`LearnerProfileController`)
- `index` : charge `xp` + `preferences` (défauts : `locale=fr`, `theme=light`, `font_size=medium`,
  `sound_enabled=true`, `notifications_enabled={new_quiz,new_article}`).
- `updatePreferences` : `theme ∈ {light,dark}`, `font_size ∈ {small,medium,large}`,
  `sound_enabled`, `locale ∈ {fr,en}`. SPA : bascule de thème clair/sombre + bouton rafraîchir.

---

## 6. Gamification

Source unique : `LearnerXp` (une ligne par apprenant : `total_xp, current_level, current_streak,
longest_streak, last_activity_date`), auto-créée à `0/1/0/0`.

### 6.1 Attribution d'XP
| Événement | XP |
| --- | --- |
| Complétion quiz | `20 + (passed ? 30 : 0) + points_obtenus × 5` |
| Lecture article ≥80 % | +15 |
| Révision flashcard | +5 |
| Examen réussi | +50 |

### 6.2 Niveaux
- Dashboard : seuil niveau suivant = `current_level × 100` ; progression % = `min(100, total_xp / (level×100) × 100)`.
- À la complétion de quiz : `nouveauNiveau = floor(nouveauTotalXp / 100) + 1` ; `levelUp` si supérieur.
- *Incohérence connue* : le dashboard utilise un seuil `level×100`, l'attribution de quiz un seuil plat `/100`.

### 6.3 Séries (streaks)
Logique (quiz & examen) :
- activité hier → `streak + 1` ;
- activité ni hier ni aujourd'hui → `streak = 1` ;
- `longest_streak = max(longest_streak, streak)`.
- *Note* : les lectures d'articles **ne mettent pas à jour** les séries.

### 6.4 Badges (`Badge` / `LearnerBadge`)
- `Badge` : `code` unique, `name, description, icon, condition_type, condition_value (JSON {count})`.
- `checkBadges($learner)` : compte les `QuizAttempt` complétés et `LearnerProgress` articles complétés ;
  débloque si `condition_type='quiz_completed'` ou `'article_read'` et seuil atteint →
  `badges()->attach(id, ['earned_at' => now()])`.
- *Incohérence connue* : seuls `quiz_completed` et `article_read` sont implémentés (le schéma prévoit
  `quiz_perfect`, `streak`, … sans logique de déblocage).

### 6.5 Classement
- **Par examen uniquement** (pas de classement XP global) : voir §5.6.

---

## 7. Modèle de données (résumé)

**Identité** : `User`, `Learner`, `Trainer`, `Role` (Spatie+), `Module`.
**Groupes** : `Group` (pivots `group_learner`, `group_trainer`, `group_quiz`, `group_article`,
`group_exam`, `group_flashcard_deck`).
**Évaluation** : `Quiz` → `Question` ; `QuizAttempt` → `QuizAnswer` ; `Exam` → `ExamQuestion`,
`ExamAttempt`.
**Contenu** : `Article` → `ArticleMedia` ; `FlashcardDeck` → `FlashcardItem` → `FlashcardItemReview`,
`FlashcardSession` ; `Flashcard` (legacy par apprenant).
**Gamification** : `LearnerXp`, `Badge`, `LearnerBadge`, `LearnerProgress`, `LearnerPreference`,
`Notification`.
**Audit/Sécurité** : `Activity`, `ErrorReport`, `ScreenshotAttempt`.

Détails des colonnes/relations : voir les migrations `Modules/Core/database/migrations/` et les
modèles `Modules/Core/app/Models/`.

---

## 8. Sécurité & anti-fraude (synthèse)

- **Connexion** : `is_active` obligatoire ; apprenant exige un profil `learner` (middleware `learner`).
- **Examens** :
  - Masquage des réponses pendant le passage.
  - Compteur `capture_attempts` / `navigation_violations` sur `ExamAttempt`.
  - Annulation automatique si `anti_capture_strict` (capture) ou `navigation_interdite` + 3 navigations.
  - Supervision temps réel (`getSupervisionData`).
  - Notation négative (`points_negatifs`), note/20 via `note_max`.
- **Quiz** : journalisation des captures d'écran (`ScreenshotAttempt`) et signalements d'erreur
  (`ErrorReport`).
- **Articles exportés** : protections de téléchargement (no-download, no-PiP, blocage clavier).
- **Audit** : toute mutation sensible journalisée (module, IP, user-agent, rôles du causeur).

---

## 9. Système de modules

- Modèle `Module` (table `modules`) vs modules filesystem (`Modules/*`, détectés via facade `Module`).
- `ModuleService` : `installModule`, `uninstallModule` (refuse si requis/actif), `enableModule`
  (dépendances en amont), `disableModule` (`canBeDeactivated()`), `syncModules`, `getDetectedModules`.
- `PermissionService` : `syncModulePermissions` (lit `config/permissions.php`, catégorise, met en cache Spatie).
- Commandes artisan `cores:*` (sync, create-user, make-superadmin, reset-user-password, stats,
  user-permissions, cleanup-permissions).

---

## 10. Points d'attention / incohérences relevés

1. **Deux systèmes de flashcards** : le SM-2 est calculé sur le modèle legacy `Flashcard`
   (`LearnerCardController`), tandis que `FlashcardItemReview` (avec `status` et modèle de deck
   correct) n'est piloté par aucun endpoint.
2. **Conditions de badges** : seuls `quiz_completed` et `article_read` sont gérés ; `streak`,
   `quiz_perfect` existent en schéma mais sans logique.
3. **Formule de niveau** : divergente entre dashboard (`level×100`) et attribution quiz (plat `/100`).
4. **Séries** : non mises à jour par la lecture d'articles (uniquement quiz/examen).
5. **Pas de classement XP global** ; seul un classement par examen existe.
6. **Rollback de migration désactivé** dans `uninstallModule`.
7. **Vocabulaire des types de questions** : l'UI expose 6 types, le moteur de notation accepte
   aussi `single_choice`/`multiple_choice` (normalisation recommandée).
8. **Graphiques dashboard** : assets chargés mais série non peuplée.

---

*Spécification générée à partir de l'analyse du code source (`Modules/Core`) — contrôleurs, modèles,
migrations, vues, routes et services — au 12 juillet 2026.*
