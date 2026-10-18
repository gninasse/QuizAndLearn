# « Voir en tant que » — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** un super admin ouvre, dans un nouvel onglet, l'espace PWA d'un apprenant précis, en lecture seule et journalisé.

**Architecture :** une table `impersonation_sessions` stocke des jetons hachés, liés au super admin qui les crée. Un middleware `impersonation`, placé avant `auth` sur `api/learner/v1`, substitue l'apprenant pour la seule requête quand l'en-tête `X-Impersonation-Token` est valide **et** que la session web appartient toujours à ce super admin. Il marque aussi la requête en lecture seule ; une liste blanche de routes GET la fait respecter côté serveur. La PWA détecte le jeton (fragment `#consultation=`, puis `sessionStorage`), ouvre une base IndexedDB dédiée, n'envoie aucune action et affiche un bandeau.

**Tech Stack :** Laravel 12 (module `Modules/Core`), PHPUnit (SQLite mémoire), Blade + jQuery (back-office), PWA TypeScript strict + Dexie + Vitest.

**Spec :** `docs/superpowers/specs/2026-10-18-voir-en-tant-que-design.md`

## Global Constraints

- Droit réservé au rôle Spatie `super-admin` (ni `admin`, ni `Admin`, ni formateur).
- Expiration fixe : 30 minutes après le démarrage, non renouvelable.
- Jeton : `Str::random(64)` en clair, renvoyé une seule fois ; stocké en SHA-256 (`hash('sha256', $token)`).
- URL d'ouverture : `/#consultation=<jeton>` (fragment, jamais en query string).
- En-tête : `X-Impersonation-Token`.
- Routes de lecture autorisées en consultation, valeurs exactes : `learn.v1.me`, `learn.v1.bootstrap`, `learn.v1.changes`, `learn.v1.leaderboard`. Toute autre route `learn.v1.*` → 403 `{code:'read_only', message:'Action indisponible en consultation.'}`.
- Session invalide (jeton inconnu, expiré, terminé, admin déconnecté ou qui n'est plus super-admin) → 403 `{code:'impersonation_ended', message:'Consultation terminée.'}`.
- En consultation, les verrous `account_suspended` et `password_change_required` sont contournés ; un compte `is_active = false` reste refusé (`not_learner`).
- Démarrer une consultation termine les consultations encore ouvertes du même admin.
- Journal : `activity('core')` avec les événements `impersonation_started` et `impersonation_ended` (propriétés : `learner_id`, `learner`, `session_id`, `duration_seconds` à la fin), comme `GroupMemberController::log`.
- Base locale PWA en consultation : `LearnQuizDB-v2026-consultation`.
- Interface en français. Commits autorisés sur la branche de travail, jamais de push ; messages terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Jeton volé sans la session de l'admin** (autre navigateur, curl) → 403 `impersonation_ended`, jamais de données. Test : Task 1 `test_token_without_admin_session_is_refused`.
2. **Admin rétrogradé ou déconnecté pendant la consultation** → la requête suivante est refusée. Test : Task 1 `test_demoted_admin_ends_consultation`.
3. **Route d'écriture ajoutée plus tard** sans y penser : refusée par défaut grâce à la liste blanche. Test : Task 1 `test_every_non_whitelisted_learner_route_is_read_only` (boucle sur toutes les routes `learn.v1.*` enregistrées).
4. **Navigateur partagé avec un vrai apprenant** : la consultation n'écrit jamais dans `LearnQuizDB-v2026`. Test : Task 3 `consultation uses its own database and never touches the learner one`.
5. **Appel réel sans en-tête** (session web super-admin seule) sur l'API apprenant : comportement inchangé (403 `not_learner`). Test : Task 1 `test_super_admin_without_header_is_not_a_learner`.

---

### Task 1 : sessions de consultation, middleware et lecture seule (serveur)

**Files:**
- Create: `Modules/Core/database/migrations/2026_10_20_100000_create_impersonation_sessions_table.php` — `admin_id` (FK users, cascade), `learner_id` (FK learners, cascade), `token_hash` (string 64, unique), `expires_at`, `ended_at` (nullable), `ip`, `user_agent` (text, nullable), timestamps.
- Create: `Modules/Core/app/Models/ImpersonationSession.php`, `Modules/Core/app/Services/ImpersonationService.php`, `Modules/Core/app/Http/Middleware/ResolveImpersonation.php`, `Modules/Core/app/Http/Controllers/ImpersonationController.php`
- Modify: `bootstrap/app.php` (alias `impersonation`), `Modules/Core/routes/web.php` :
  - groupe `api/learner/v1` : `middleware(['impersonation', 'auth', 'learner'])` ;
  - `POST cores/learners/{learnerId}/impersonate` → `cores.learners.impersonate` (staff group) ;
  - `DELETE api/learner/v1/impersonation` → `learn.v1.impersonation.end`, hors `auth`/`learner`, avec seulement `impersonation`.
- Modify: `Modules/Core/app/Http/Middleware/EnsureUserIsLearner.php` : contourner `account_suspended` et `password_change_required` quand `$request->attributes->get('impersonation')` est présent.
- Test: `tests/Feature/ImpersonationTest.php`

**Interfaces:**
- Produces: `ImpersonationService::start(User $admin, Learner $learner, Request $request): array{session: ImpersonationSession, token: string, url: string}`. Il termine les sessions ouvertes de cet admin, crée la nouvelle (expire à +30 min) et journalise `impersonation_started`. `url` vaut `url('/').'#consultation='.$token`.
- Produces: `ImpersonationService::resolve(?string $token, ?User $webUser): ?ImpersonationSession`. Renvoie null si le jeton est inconnu, expiré ou terminé, si `$webUser` est null ou différent de `admin_id`, ou si `$webUser` n'a plus le rôle `super-admin`.
- Produces: `ImpersonationService::end(ImpersonationSession $session): void` (pose `ended_at`, journalise `impersonation_ended` avec la durée).
- Produces: attribut de requête `impersonation` (l'objet `ImpersonationSession`) posé par le middleware.
- Produces: réponse de `POST cores/learners/{learnerId}/impersonate` → `{success, url, expires_at}`. Non super-admin → 403. Apprenant `is_active = false` → 422 `{success:false, message:'Compte désactivé : consultation impossible.'}`.
- Middleware `ResolveImpersonation` :
  - pas d'en-tête → `$next` sans rien changer ;
  - en-tête présent et `resolve` null → 403 `impersonation_ended` ;
  - sinon : `Auth::setUser($session->learner->user)` (sans toucher à la session), puis l'attribut. Si la route n'est pas dans la liste blanche ni `learn.v1.impersonation.end` → 403 `read_only`.

- [ ] **Step 1 : tests** (`ImpersonationTest`) :
  - `test_super_admin_starts_consultation_and_reads_learner_bootstrap` : super-admin connecté (session) → POST impersonate → GET `/api/learner/v1/bootstrap` avec l'en-tête → 200, `user.id` = l'apprenant ;
  - `test_admin_and_trainer_cannot_start` (403) ;
  - `test_token_without_admin_session_is_refused` (`auth()->logout()` puis requête avec l'en-tête → 403 `impersonation_ended`) ;
  - `test_demoted_admin_ends_consultation` (retrait du rôle → 403) ;
  - `test_expired_and_ended_tokens_are_refused` (`Carbon::setTestNow(+31 min)` ; après DELETE `/api/learner/v1/impersonation`) ;
  - `test_every_non_whitelisted_learner_route_is_read_only` : pour chaque route enregistrée dont le nom commence par `learn.v1.` et n'est ni dans la liste blanche ni `impersonation.end`/`session.store`, la requête avec l'en-tête répond 403 `read_only` ; aucune ligne créée dans `learner_action_log` ni `quiz_attempts` ;
  - `test_suspended_and_temp_password_learner_can_be_viewed` (200 sur bootstrap) ; `test_inactive_learner_cannot_be_started` (422) ;
  - `test_new_consultation_ends_previous_ones_of_same_admin` ;
  - `test_super_admin_without_header_is_not_a_learner` (403 `not_learner`, inchangé) ;
  - `test_activity_logged_on_start_and_end`.
- [ ] **Step 2 :** `php artisan test --filter=ImpersonationTest` → FAIL.
- [ ] **Step 3 : implémenter** le service, le middleware, le contrôleur, les routes et la migration.
- [ ] **Step 4 :** `php artisan test` → tout passe ; `vendor/bin/pint --dirty`.
- [ ] **Step 5 : commit** — `feat(impersonation): read-only learner consultation sessions for super admins`

### Task 2 : points d'entrée back-office

**Files:**
- Modify: `Modules/Core/app/Http/Controllers/GroupMemberController.php` (`data()` : ajouter par apprenant `last_consultation: {at, by} | null` et, au niveau racine, `can_impersonate: bool` = rôle `super-admin`)
- Modify: `public/js/modules/core/groups/members.js` : bouton « Voir en tant que » (icône `bi-person-video3`) dans la ligne et dans la fiche, visible si `can_impersonate`. Au clic : POST vers l'URL `data-url-impersonate` (motif `/cores/learners/{id}/impersonate`, passé par un attribut `data-*` de la vue), puis `window.open(url, '_blank', 'noopener')`. La fiche affiche « Dernière consultation : {date} par {nom} ».
- Modify: `Modules/Core/resources/views/groups/members.blade.php` (attribut `data-url-impersonate`)
- Modify: liste des apprenants `Modules/Core/resources/views/learners/index.blade.php` + `public/js/modules/core/learners/LearnerActions.js` / `LearnerTable.js` : bouton de barre d'outils « Voir en tant que » (activé sur sélection unique), affiché seulement pour un super-admin. Attention : les ids de la liste sont des **user ids**, à résoudre vers le learner id côté serveur ou via les données de ligne.
- Test: `tests/Feature/ImpersonationEntryPointsTest.php`

**Interfaces:**
- Consumes: route `cores.learners.impersonate` et service (Task 1).

- [ ] **Step 1 : tests** :
  - `test_members_data_exposes_can_impersonate_only_to_super_admin` ;
  - `test_members_data_includes_last_consultation` ;
  - `test_learners_index_shows_button_only_to_super_admin` (`assertSee` / `assertDontSee` « Voir en tant que »).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter → **Step 4 :** `php artisan test` passe, `node --check` sur le JS modifié.
- [ ] **Step 5 : commit** — `feat(admin): « Voir en tant que » entry points for super admins`

### Task 3 : mode consultation dans la PWA

**Files:**
- Create: `resources/js/learner/consultation/consultation.ts`, `resources/js/learner/consultation/consultation.test.ts`
- Modify: `resources/js/learner/db/schema.ts` (nom de base choisi à l'import via `consultationDbName()`), `api/client.ts` (en-tête `X-Impersonation-Token` quand un jeton est présent ; méthode `endConsultation()` → `DELETE /impersonation`), `sync/engine.ts` (`dispatch()` sans effet en consultation ; erreurs `impersonation_ended` / `read_only` traitées), `main.ts` (démarrage : lecture du fragment, bootstrap direct sans écran de connexion, filigrane `Consultation · {nom de l'apprenant}`), `components/app-shell.ts` (bandeau), vues `exam-play.ts` / `examens.ts` (démarrage bloqué), `profil.ts` (mot de passe, déconnexion et préférences remplacés par « Quitter la consultation »), `article-detail.ts` (favori, note, signalement bloqués)

**Interfaces:**
- Produces:
  - `readConsultationToken(): string | null` : lit `#consultation=` dans `location.hash`, l'enregistre dans `sessionStorage['learner:consultation']`, retire le fragment avec `history.replaceState`, sinon lit `sessionStorage` ;
  - `isConsultation(): boolean` ;
  - `consultationDbName(): string` (`'LearnQuizDB-v2026-consultation'` si consultation, sinon `'LearnQuizDB-v2026'`) ;
  - `endConsultation(reason: 'quit' | 'ended'): Promise<void>` : appel serveur si `quit`, vidage de la base consultation et du `sessionStorage`, puis écran « Consultation terminée » avec « Vous pouvez fermer cet onglet. » ;
  - `blockedInConsultation(): void` : toast « Action indisponible en consultation. ».
- Bandeau, texte exact : `Consultation de l'espace de {full_name} — lecture seule`, avec le bouton « Quitter ». Bandeau secondaire si `suspended` ou `must_change_password` dans le profil : « Compte suspendu » / « Mot de passe provisoire non encore changé ». S'il manque, ajouter `suspended: bool` au profil renvoyé par `/me` et le bootstrap côté serveur, en consultation seulement.
- Quiz et révision de flashcards : jouables localement ; aucun envoi, car `dispatch()` est sans effet.

- [ ] **Step 1 : tests vitest** :
  - `reads the token from the fragment, stores it per tab and strips the URL` ;
  - `consultation uses its own database and never touches the learner one` ;
  - `dispatch does nothing in consultation` ;
  - `api requests carry X-Impersonation-Token` ;
  - `impersonation_ended response ends the consultation screen` ;
  - `banner shows learner name and quit button`.
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter → **Step 4 :** `npx vitest run && npx tsc --noEmit && npm run build` passent ; `php artisan test` passe si le PHP a été touché.
- [ ] **Step 5 : commit** — `feat(pwa): read-only consultation mode`

### Task 4 : documentation

**Files:**
- Modify: `SPECIFICATIONS_VOLET_APPRENANT.md` (§0.8 Consultation par un super admin), `DEPLOIEMENT.md` (mention dans la checklist post-déploiement : consultation en lecture seule)
- [ ] **Step 1 :** rédiger à partir du code.
- [ ] **Step 2 :** `php artisan test && npx vitest run && npx tsc --noEmit && npm run build`.
- [ ] **Step 3 : commit** — `docs: super admin read-only consultation`
