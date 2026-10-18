# Mode découverte (invité) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** un visiteur sans compte découvre l'app (PWA et app Kotlin) sur des contenus « vitrine », envoie une demande d'accès ; l'admin suit le paiement Mobile Money et crée le compte, auquel l'apprenant se connecte par téléphone ou e-mail.

**Architecture :** côté serveur, une API publique en lecture seule (`/api/showcase/v1`) sert les contenus cochés `is_showcase` au même format que le bootstrap apprenant, et délivre un jeton invité signé (cookie + valeur JSON) qui ouvre uniquement les médias vitrine. Les demandes d'accès vivent dans trois tables et un écran back-office. Côté PWA, le mode invité est une session locale (`UserProfile.is_guest = true`) qui réutilise les vues existantes : `dispatch()` n'écrit rien dans l'outbox et `sync()` recharge la vitrine au lieu du delta.

**Tech Stack :** Laravel 12 (module `Modules/Core`), PostgreSQL (tests SQLite mémoire), Blade + jQuery + Bootstrap 5 (back-office), PWA TypeScript strict + Web Components + Dexie + Workbox, Vitest (happy-dom), PHPUnit.

**Spec :** `docs/superpowers/specs/2026-10-18-mode-invite-design.md`

## Global Constraints

- Interface 100 % en français ; les textes visibles suivent le ton des écrans existants.
- Jamais d'examen, de donnée d'apprenant ni de contenu non coché dans une réponse de l'API vitrine.
- Médias : la route `/storage/{path}` reste l'unique point d'accès ; aucun lien symbolique `public/storage`.
- Téléphone stocké au format E.164 (`+` suivi de 8 à 15 chiffres), unique quand renseigné ; indicatif par défaut : réglage `showcase.default_country_code`, valeur initiale `225`.
- L'e-mail devient facultatif **pour les apprenants seulement** ; il reste obligatoire pour admins et formateurs.
- Connexion acceptée par e-mail, téléphone ou `user_name` (PWA, API mobile, back-office), sans régression des connexions existantes.
- Seuls les rôles `super-admin`, `admin`, `Admin` cochent la vitrine, gèrent les réglages et voient les demandes d'accès.
- Paiement : saisie manuelle uniquement ; jamais de PIN ni d'identifiant Mobile Money ; devise par défaut `XOF` ; opérateurs `orange_money`, `mtn_momo`, `moov_money`, `wave`, `other`.
- Statuts de demande, valeurs exactes : `new`, `contacted`, `payment_pending`, `paid`, `account_created`, `rejected`, `abandoned`.
- Jeton invité : durée de vie 24 h, renouvelé à chaque appel de `/content`.
- Purge des demandes `rejected` / `abandoned` après 12 mois (réglage `showcase.retention_months`).
- Les messages flash et erreurs de l'API suivent le format existant : `{success: bool, message: string}`.
- Pas de commit tant que l'utilisateur ne l'a pas demandé : les étapes « Commit » ne s'exécutent qu'avec son accord.

## Review Focus

1. **Numéros saisis « à la locale »** (`07 00 00 00 00`, `+225 07-00-00-00-00`, `002250700000000`) : ils doivent tous retrouver le même compte au login et être détectés comme doublon à la création. Test : Task 1 `test_normalizes_local_international_and_00_prefixes`.
2. **Un contenu décoché ou désactivé après la mise en cache** : il doit disparaître de la vitrine au prochain chargement, et ses médias ne doivent plus être servis aux invités. Test : Task 7 `test_unchecking_content_revokes_media_access`.
3. **Un invité qui se connecte ensuite avec un vrai compte** : aucune donnée invité ne doit survivre ni partir vers le serveur (outbox vide, vitrine purgée). Test : Task 11 `leaving guest mode clears every local table`.
4. **Deux demandes successives du même téléphone** : la seconde met à jour la demande ouverte au lieu d'en créer une, mais une demande close (`account_created`, `rejected`, `abandoned`) n'empêche pas d'en créer une nouvelle. Test : Task 8 `test_second_request_from_same_phone_updates_open_one`.
5. **Un apprenant au mot de passe temporaire qui tente d'utiliser l'app** : toute route de l'API apprenant hors session et mot de passe répond 403 `password_change_required`, et la PWA l'envoie sur l'écran de changement. Test : Task 2 `test_must_change_password_blocks_content_until_changed`.

---

## Carte des fichiers

| Fichier | Responsabilité |
|---|---|
| `Modules/Core/app/Support/PhoneNumber.php` | normalisation E.164 |
| `Modules/Core/app/Support/LoginResolver.php` | retrouver un `User` depuis « e-mail / téléphone / identifiant » |
| `Modules/Core/app/Support/Credentials.php` | génération de `user_name` unique et de mot de passe temporaire |
| `Modules/Core/app/Models/AppSetting.php` + `Modules/Core/app/Services/ShowcaseSettings.php` | réglages clé/valeur typés |
| `Modules/Core/app/Services/ContentSerializer.php` | sérialisation quiz/article/deck partagée (apprenant + vitrine) |
| `Modules/Core/app/Services/ShowcaseMedia.php` | liste blanche des médias vitrine (cache) |
| `Modules/Core/app/Services/GuestToken.php` | jeton invité signé |
| `Modules/Core/app/Http/Controllers/Showcase/*` | API publique vitrine + demandes |
| `Modules/Core/app/Http/Controllers/ShowcaseAdminController.php` | cochage vitrine + réglages |
| `Modules/Core/app/Http/Controllers/AccessRequestController.php` | écran admin des demandes |
| `Modules/Core/app/Models/AccessRequest*.php` | demandes, paiements, historique |
| `resources/js/learner/guest/*` | logique PWA du mode invité |
| `resources/js/learner/views/demande-acces.ts`, `views/mot-de-passe.ts` | nouveaux écrans PWA |

---

## Phase A — Connexion par téléphone

### Task 1 : normalisation du téléphone et résolution du login

**Files:**
- Create: `Modules/Core/app/Support/PhoneNumber.php`, `Modules/Core/app/Support/LoginResolver.php`
- Create: `Modules/Core/database/migrations/2026_10_19_100000_phone_login_on_users.php`
- Modify: `Modules/Core/app/Http/Controllers/Learner/Api/SessionController.php` (méthode `store`), `Modules/Core/app/Http/Controllers/Learner/Api/MobileAuthController.php` (méthode `login`), `Modules/Core/app/Http/Controllers/AuthController.php` (méthode `login`)
- Test: `tests/Unit/PhoneNumberTest.php`, `tests/Feature/PhoneLoginTest.php`

**Interfaces:**
- Produces: `PhoneNumber::normalize(?string $raw, ?string $defaultCountryCode = null): ?string`, qui renvoie du E.164 ou `null` si invalide ; `$defaultCountryCode` vaut par défaut `ShowcaseSettings::defaultCountryCode()` (Task 5). Tant que Task 5 n'existe pas, utiliser `config('showcase.default_country_code', '225')`, puis basculer en Task 5.
- Produces: `LoginResolver::find(string $login): ?User`. Règles : contient `@` → e-mail ; ne contient que `+`, chiffres, espaces, `-`, `.`, `(`, `)` et au moins 8 chiffres → téléphone normalisé ; sinon `user_name`. La comparaison d'e-mail ignore la casse.
- Produces: colonnes `users.email` nullable, `users.must_change_password` (bool, défaut `false`), index unique sur `users.phone` (les valeurs nulles sont autorisées).

- [ ] **Step 1 : tests unitaires** `tests/Unit/PhoneNumberTest.php` :
  - `test_normalizes_local_international_and_00_prefixes` : `'07 00 00 00 00'`, `'+225 07-00-00-00-00'`, `'002250700000000'` et `'2250700000000'` donnent tous `'+2250700000000'` (indicatif par défaut 225) ;
  - `test_rejects_garbage` : `'abc'`, `'12'`, `''` et `null` donnent `null` ;
  - `test_keeps_foreign_numbers` : `'+33 6 12 34 56 78'` donne `'+33612345678'`.
- [ ] **Step 2 :** `php artisan test --filter=PhoneNumberTest` → FAIL (classe absente).
- [ ] **Step 3 : implémenter `PhoneNumber::normalize`.** Retirer tous les caractères non chiffres sauf un `+` initial ; remplacer le préfixe `00` par `+`. Un numéro sans indicatif est préfixé par l'indicatif par défaut, avec le `0` de tête conservé (cas de la Côte d'Ivoire, numéros à 10 chiffres). Le résultat doit compter entre 8 et 15 chiffres.
- [ ] **Step 4 : migration.** Rendre `email` nullable, ajouter `must_change_password`, normaliser les `phone` existants avec `PhoneNumber::normalize`. En cas de doublon après normalisation, garder le premier par `id`, mettre les suivants à `null` et les journaliser avec `Log::warning`. Créer ensuite l'index unique.
- [ ] **Step 5 : tests feature** `tests/Feature/PhoneLoginTest.php` :
  - `test_learner_logs_in_with_phone_in_any_format` : POST `/api/learner/v1/session` avec `login = '07 00 00 00 00'` → 200 ;
  - `test_learner_logs_in_with_email_case_insensitive` ;
  - `test_existing_user_name_login_still_works` ;
  - `test_mobile_login_accepts_phone` (`/api/mobile/v1/login`) ;
  - `test_staff_login_accepts_phone` (`/login`, redirection vers `cores.dashboard`) ;
  - `test_inactive_user_with_phone_is_refused` → 401.
- [ ] **Step 6 : brancher les trois contrôleurs sur `LoginResolver::find`.** Vérifier ensuite `Hash::check` et `is_active` (au lieu d'`Auth::attempt` par champ), puis `Auth::login($user, true)` pour la session. Conserver le throttling et les messages existants.
- [ ] **Step 7 :** `php artisan test` → tout passe, y compris les suites existantes `LearnerApiV1Test` et `MobileApiTest`.
- [ ] **Step 8 : commit** (sur accord) — `feat(auth): login by phone, email or username`

### Task 2 : création d'apprenant sans e-mail, mot de passe temporaire forcé

**Files:**
- Create: `Modules/Core/app/Support/Credentials.php`
- Modify: `Modules/Core/app/Http/Requests/StoreLearnerRequest.php`, `UpdateLearnerRequest.php`, `Modules/Core/app/Http/Controllers/LearnerController.php` (`store`, `update`)
- Modify: `Modules/Core/app/Http/Middleware/EnsureUserIsLearner.php`, `Learner/Api/SessionController.php` (`serializeUser`, `updatePassword`), `MobileAuthController.php` (`me`, `updatePassword`, réponse de login)
- Modify: `Modules/Core/resources/views/learners/*` et `public/js/modules/core/learners/LearnerForm.js` (libellé « E-mail (facultatif) », champ téléphone, bouton « Générer » pour le mot de passe)
- Test: `tests/Feature/LearnerAccountRulesTest.php`

**Interfaces:**
- Consumes: `PhoneNumber::normalize` (Task 1).
- Produces: `Credentials::uniqueUserName(string $firstName, string $lastName): string`, de forme `prenom.nom` en ASCII minuscule, avec suffixe `2`, `3`… en cas de doublon.
- Produces: `Credentials::temporaryPassword(): string`, 10 caractères lisibles, sans `0 O 1 l I`, avec au moins un chiffre.
- Produces: `LearnerAccountService::create(array $data, ?array $groupIds, bool $temporaryPassword): array{user: User, learner: Learner, password: ?string}` dans `Modules/Core/app/Services/LearnerAccountService.php`, extrait de `LearnerController::store` et réutilisé par la Task 10. `password` n'est renseigné que si `$temporaryPassword`.
- Produces: champ `must_change_password: bool` dans le profil renvoyé par `/me`, par le login PWA et par le login mobile. Code d'erreur `password_change_required`.

- [ ] **Step 1 : tests** :
  - `test_learner_can_be_created_without_email_with_phone` ;
  - `test_phone_duplicate_in_another_format_is_rejected` (422, message « Ce numéro de téléphone est déjà utilisé. ») ;
  - `test_user_name_is_generated_when_missing` (`awa.kone`, puis `awa.kone2`) ;
  - `test_learner_needs_email_or_phone` (422 si les deux sont absents) ;
  - `test_staff_still_requires_email` (création d'un formateur sans e-mail → 422) ;
  - `test_must_change_password_blocks_content_until_changed`.

  Ce dernier test crée un apprenant avec `must_change_password = true`. GET `/api/learner/v1/bootstrap` répond 403 avec `code = password_change_required`, tandis que GET `/me` répond 200. Après PUT `/password`, `bootstrap` répond 200 et le flag vaut `false`.
- [ ] **Step 2 :** lancer le filtre → FAIL.
- [ ] **Step 3 : règles de validation** (store et update). `email` : `nullable|email|unique` ; `phone` : normalisé dans `prepareForValidation`, `nullable|unique:users,phone` (en ignorant l'utilisateur courant en update) ; `required_without` croisé entre `email` et `phone` ; `user_name` : `nullable|unique`, généré si vide. Le mot de passe reste requis, sauf si `temporary_password = 1` est envoyé.
- [ ] **Step 4 : `LearnerAccountService`.** `LearnerController::store` délègue au service. Avec un mot de passe temporaire, `must_change_password` passe à `true`, et la réponse JSON contient `temporary_password` une seule fois.
- [ ] **Step 5 : middleware.** Si `must_change_password` vaut `true` et que la route n'est pas `learn.v1.me`, `learn.v1.password.update`, `learn.v1.session.destroy`, `mobile.v1.me`, `mobile.v1.password.update`, `mobile.v1.logout` ou `mobile.v1.logout-all` → 403 `{message: 'Vous devez choisir un nouveau mot de passe.', code: 'password_change_required'}`. `updatePassword` remet le flag à `false`.
- [ ] **Step 6 :** `php artisan test` → tout passe.
- [ ] **Step 7 : commit** — `feat(learners): optional email, unique phone, temporary password`

### Task 3 : PWA — connexion par téléphone et changement de mot de passe imposé

**Files:**
- Modify: `resources/js/learner/views/login.ts` (libellé « E-mail ou téléphone », placeholder `07 00 00 00 00 ou prenom.nom@exemple.fr`, `inputmode="email"` retiré, `autocomplete="username"`)
- Create: `resources/js/learner/views/mot-de-passe.ts`
- Modify: `resources/js/learner/main.ts` (route `/mot-de-passe`, redirection), `resources/js/learner/domain/types.ts` (`must_change_password?: boolean` sur `UserProfile`), `resources/js/learner/sync/engine.ts` (gestion de l'erreur `password_change_required`)
- Modify: `Modules/Core/routes/web.php` (ajouter `/mot-de-passe` aux routes profondes du SPA)
- Test: `resources/js/learner/views/mot-de-passe.test.ts`

**Interfaces:**
- Consumes: `api.updatePassword(current, password, confirmation)` (existant) ; champ `must_change_password` (Task 2).
- Produces: `mount(el, params)` pour `views/mot-de-passe.ts`, et l'événement `SyncEvent` `{ kind: 'password-change-required' }`.

- [ ] **Step 1 : test vitest** `mot-de-passe.test.ts` : la vue affiche trois champs (mot de passe actuel « reçu par votre formateur », nouveau, confirmation). Le bouton reste désactivé tant que le nouveau mot de passe fait moins de 8 caractères ou diffère de la confirmation.
- [ ] **Step 2 :** `npx vitest run mot-de-passe` → FAIL.
- [ ] **Step 3 : implémenter la vue.** En cas de succès : `sessionStore.set({...user, must_change_password: false})`, toast « Mot de passe enregistré », puis `fullBootstrap()` et `router.go('/', true)`.
- [ ] **Step 4 : redirection dans `main.ts`.** Après `learner:authenticated`, après `hydrateFromDb()` et à chaque navigation (garde dans `onNavigate`), si `sessionStore.get()?.must_change_password` vaut vrai et que le chemin n'est pas `/mot-de-passe` → `router.go('/mot-de-passe', true)`. L'événement de sync `password-change-required` provoque la même redirection.
- [ ] **Step 5 :** `npx vitest run && npx tsc --noEmit` → OK.
- [ ] **Step 6 : commit** — `feat(pwa): phone login label and forced password change`

---

## Phase B — Vitrine côté serveur

### Task 4 : cochage « Visible en mode découverte »

**Files:**
- Create: `Modules/Core/database/migrations/2026_10_19_110000_add_showcase_flags.php` (`is_showcase` booléen, défaut `false`, indexé, sur `quizzes`, `articles`, `flashcard_decks` ; `open_for_requests` booléen, défaut `false`, sur `groups`)
- Create: `Modules/Core/app/Http/Controllers/ShowcaseAdminController.php` (méthode `toggle`)
- Modify: `Modules/Core/routes/web.php`. Ajouter `PATCH /cores/showcase/{type}/{id}` (`type` ∈ `quiz|article|deck`), nommée `cores.showcase.toggle`, et `PATCH /cores/groups/{id}/open-for-requests`, nommée `cores.groups.open-for-requests`.
- Modify: modèles `Quiz`, `Article`, `FlashcardDeck`, `Group` (`$fillable` + cast booléen)
- Modify: en-têtes des éditeurs `admin/quiz/editor.blade.php`, `admin/article/editor.blade.php`, `admin/flashcard/editor.blade.php` : interrupteur « Visible en mode découverte », actif pour les admins et désactivé avec infobulle pour les formateurs.
- Modify: listes `quizzes/index`, `articles/index`, `flashcards` (index) : badge « Vitrine » et filtre `?showcase=1` dans le `getData` des contrôleurs.
- Modify: `groups/_modal.blade.php` + `GroupForm.js` : case « Proposé dans le formulaire de demande d'accès ».
- Test: `tests/Feature/ShowcaseFlagTest.php`

**Interfaces:**
- Produces: route `cores.showcase.toggle`. Corps `{is_showcase: bool}` → `{success, is_showcase}`.

- [ ] **Step 1 : tests** :
  - `test_admin_toggles_showcase_on_each_type` ;
  - `test_trainer_cannot_toggle_showcase` (403) ;
  - `test_exam_type_is_rejected` (404) ;
  - `test_deck_is_public_is_untouched_by_showcase_toggle` ;
  - `test_admin_opens_group_for_requests`.
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter la migration, le contrôleur et les vues → **Step 4 :** `php artisan test` passe.
- [ ] **Step 5 : commit** — `feat(showcase): admin flag on quizzes, articles and decks`

### Task 5 : réglages du mode découverte et page de confidentialité

**Files:**
- Create: migration `2026_10_19_120000_create_app_settings_table.php` (`key` string unique, `value` json, timestamps), `Modules/Core/app/Models/AppSetting.php`, `Modules/Core/app/Services/ShowcaseSettings.php`
- Modify: `ShowcaseAdminController` (méthodes `settings` pour la vue et `saveSettings` pour le PUT), `Modules/Core/resources/views/showcase/settings.blade.php`, entrée « Mode découverte » dans `layouts/partials/sidebar.blade.php` (admins)
- Create: route publique `GET /confidentialite` → `Modules/Core/resources/views/showcase/privacy.blade.php` (gabarit autonome, sur le modèle de `errors/minimal`)
- Test: `tests/Feature/ShowcaseSettingsTest.php`

**Interfaces:**
- Produces: `ShowcaseSettings` (singleton du conteneur) :
  - `enabled(): bool` (défaut `false`) ;
  - `tagline(): string` ;
  - `defaultCountryCode(): string` (`'225'`) ;
  - `currency(): string` (`'XOF'`) ;
  - `fee(): ?int` ;
  - `paymentAccounts(): array<array{operator: string, number: string, holder: string}>` ;
  - `paymentReference(): ?string` (mention à indiquer dans le transfert) ;
  - `instructions(): ?string` ;
  - `notificationEmails(): string[]` ;
  - `privacyText(): ?string` ;
  - `privacyUrl(): ?string` ;
  - `retentionMonths(): int` (12) ;
  - `publicPayload(): array` (sous-ensemble exposé par l'API) ;
  - `save(array $validated): void`.
- Produces: `AppSetting::get(string $key, mixed $default = null): mixed` et `AppSetting::put(string $key, mixed $value): void`, mis en cache et invalidés à l'écriture.

- [ ] **Step 1 : tests** :
  - `test_defaults_when_nothing_saved` ;
  - `test_admin_saves_settings_and_payload_exposes_only_public_fields` : `notificationEmails` et `retentionMonths` sont absents de `publicPayload()` ;
  - `test_trainer_cannot_open_settings` ;
  - `test_privacy_page_shows_saved_text_or_redirects_to_url` (texte → 200 avec ce texte ; URL externe → redirection 302 ; ni l'un ni l'autre → texte modèle par défaut).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter. La page de réglages regroupe : activation, accroche, instructions d'inscription (montant, devise, liste dynamique « opérateur / numéro / bénéficiaire », mention), indicatif, e-mails de notification, confidentialité (texte ou URL), durée de conservation → **Step 4 :** passe.
- [ ] **Step 5 :** remplacer dans `PhoneNumber` le repli `config(...)` par `ShowcaseSettings::defaultCountryCode()`.
- [ ] **Step 6 : commit** — `feat(showcase): settings page and privacy policy`

### Task 6 : API publique vitrine et jeton invité

**Files:**
- Create: `Modules/Core/app/Services/ContentSerializer.php`, `Modules/Core/app/Services/GuestToken.php`, `Modules/Core/app/Http/Controllers/Showcase/ShowcaseController.php`
- Modify: `Modules/Core/app/Services/LearnerContentService.php`, qui délègue sa sérialisation à `ContentSerializer` (sans changer son JSON)
- Modify: `Modules/Core/routes/web.php` : groupe `api/showcase/v1`, `throttle:60,1`, sans `auth` ; routes `GET /content` (`showcase.v1.content`) et `GET /settings` (`showcase.v1.settings`)
- Test: `tests/Feature/ShowcaseApiTest.php`

**Interfaces:**
- Produces: `ContentSerializer::quiz(Quiz $quiz, ?Collection $attempts): array`, `::article(Article $article, ?LearnerProgress $progress): array` et `::deck(FlashcardDeck $deck, ?Collection $reviews): array`. Avec `null`, les champs propres à l'apprenant prennent leur valeur neutre : `status = 'unread'`, `attempts = []`, `review = null`, `is_favorite = false`, `rating = 0`, `progress_percentage = 0`.
- Produces: `GuestToken::issue(): string` (valeur `Crypt::encryptString(json {exp})`) et `GuestToken::valid(?string $token): bool`.
- Produces: réponse de `GET /content` → `{success, guest_token, expires_at, articles[], quizzes[], decks[]}`, avec les contenus `is_showcase` et actifs (`is_active` pour quiz et articles, `active` pour les decks). La réponse pose aussi le cookie `lq_guest` (httpOnly, `SameSite=Lax`, secure si HTTPS, 24 h). Ce cookie est exclu du chiffrement des cookies via `encryptCookies(except: ['lq_guest'])` dans `bootstrap/app.php`, puisque la valeur est déjà chiffrée.
- Produces: `GET /settings` → `{success, enabled, brand: {name, tagline, logo}, registration: {fee, currency, accounts, reference, instructions}, default_country_code, groups: [{id, name, start_date, end_date}], privacy_url}`. `groups` liste les groupes `open_for_requests` dont la date de fin n'est pas dépassée. `privacy_url` vaut l'URL externe ou `/confidentialite`.
- Si `enabled()` vaut `false`, `/content` et `/access-requests` répondent 404 `{success: false, message: 'Le mode découverte est désactivé.'}`, et `/settings` répond `enabled: false`.

- [ ] **Step 1 : tests** :
  - `test_returns_only_checked_active_content` (un quiz coché, un décoché, un coché mais inactif) ;
  - `test_never_returns_exams_or_learner_fields` : le JSON ne contient pas la clé `exams`, et `attempts` est vide même si un apprenant a des tentatives sur ce quiz ;
  - `test_issues_guest_token_cookie_and_json` ;
  - `test_disabled_mode_returns_404` ;
  - `test_settings_payload_lists_open_groups_only` ;
  - `test_learner_bootstrap_json_unchanged` : instantané des clés du bootstrap avant et après refactorisation, sur la fixture de `LearnerApiV1Test`.
- [ ] **Step 2 :** FAIL → **Step 3 :** extraire `ContentSerializer` (refactorisation pure, le bootstrap apprenant reste identique), puis le contrôleur et `GuestToken` → **Step 4 :** `php artisan test` passe en entier.
- [ ] **Step 5 : commit** — `feat(showcase): public read-only API and guest token`

### Task 7 : médias vitrine accessibles aux invités

**Files:**
- Create: `Modules/Core/app/Services/ShowcaseMedia.php`
- Modify: `Modules/Core/app/Http/Controllers/MediaController.php`, modèles `Quiz`, `Question`, `Article`, `FlashcardDeck`, `FlashcardItem` (événements `saved` / `deleted` → `ShowcaseMedia::flush()`)
- Test: `tests/Feature/ShowcaseMediaTest.php`

**Interfaces:**
- Consumes: `GuestToken::valid` (Task 6).
- Produces: `ShowcaseMedia::paths(): array<string, true>`. Clés = chemins relatifs au disque `public` (par exemple `uploads/media/x.png`), extraits des attributs `src`/`href` en `/storage/...` (absolus ou relatifs) du HTML des contenus vitrine actifs (`question_text`, `content`, `recto`, `verso`). Mis en cache dans `Cache::rememberForever('showcase.media')`.
- Produces: `ShowcaseMedia::allows(string $path): bool` et `ShowcaseMedia::flush(): void`.
- Règle de `MediaController` : un utilisateur authentifié suit les règles actuelles ; sinon, l'accès est accordé si `GuestToken::valid(cookie 'lq_guest' ?? header 'X-Guest-Token')` et `ShowcaseMedia::allows($path)` ; dans tous les autres cas, 403.

- [ ] **Step 1 : tests** :
  - `test_guest_with_token_reads_showcase_media` ;
  - `test_guest_without_token_is_refused` ;
  - `test_guest_cannot_read_non_showcase_media` ;
  - `test_expired_token_is_refused` (via `Carbon::setTestNow` à +25 h) ;
  - `test_header_token_works_for_native_app` ;
  - `test_unchecking_content_revokes_media_access`.
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter → **Step 4 :** passe (avec `ProtectedMediaTest`).
- [ ] **Step 5 : commit** — `feat(showcase): guest access to showcase media only`

### Task 8 : demandes d'accès — modèle et envoi public

**Files:**
- Create: migration `2026_10_19_130000_create_access_requests_tables.php` avec trois tables :
  - `access_requests` : `first_name`, `last_name`, `phone` (E.164), `whatsapp` (E.164, nullable), `email` (nullable), `organization` (nullable), `group_id` (nullable, FK `groups`, `nullOnDelete`), `message` (text, nullable), `consent_at` (timestamp), `consent_text` (text), `status` (string, défaut `new`, indexé), `notes` (text, nullable), `ip`, `user_agent`, `learner_id` (nullable, FK `learners`, `nullOnDelete`), `handled_by` (nullable, FK `users`), timestamps ;
  - `access_request_payments` : `access_request_id` (FK, `cascadeOnDelete`), `amount` (unsigned integer), `currency` (string, 3), `operator`, `reference`, `paid_at` (date), `payer_phone`, `note` (nullable), `recorded_by` (FK `users`), timestamps ;
  - `access_request_events` : `access_request_id` (FK, `cascadeOnDelete`), `from_status` (nullable), `to_status`, `comment` (nullable), `user_id` (nullable FK), `created_at`.
- Create: `Modules/Core/app/Models/AccessRequest.php`, `AccessRequestPayment.php`, `AccessRequestEvent.php`, `Modules/Core/app/Http/Controllers/Showcase/AccessRequestSubmitController.php`, `Modules/Core/app/Mail/NewAccessRequestMail.php` + vue Markdown du mail
- Modify: `web.php` : `POST /api/showcase/v1/access-requests` (`showcase.v1.access-requests.store`), `throttle:5,60` par IP
- Test: `tests/Feature/AccessRequestSubmitTest.php`

**Interfaces:**
- Produces: constantes de statut `AccessRequest::STATUSES` (liste des Global Constraints), `AccessRequest::OPEN = ['new','contacted','payment_pending','paid']`.
- Produces: `AccessRequest::transitionTo(string $status, ?User $by, ?string $comment = null): void`, qui écrit un `AccessRequestEvent`.
- Corps attendu : `first_name`, `last_name`, `phone` (requis), `whatsapp_same` (bool), `whatsapp`, `email`, `organization`, `group_id` (doit être `open_for_requests`), `message` (2000 caractères max), `consent` (`accepted`), `website` (piège, doit rester vide), `started_at` (horodatage ms côté client).
- Réponse 201 : `{success: true, message: 'Demande envoyée.', phone: '+225…', registration: ShowcaseSettings::publicPayload()['registration']}`.
- Anti-abus : le champ piège rempli ou un formulaire envoyé moins de 3 s après `started_at` → réponse 201 identique, mais rien n'est enregistré.

- [ ] **Step 1 : tests** :
  - `test_valid_request_is_stored_with_consent_and_event` ;
  - `test_phone_is_normalized_and_required` ;
  - `test_honeypot_and_too_fast_submissions_are_silently_dropped` ;
  - `test_second_request_from_same_phone_updates_open_one` ;
  - `test_closed_request_allows_a_new_one` ;
  - `test_group_must_be_open_for_requests` ;
  - `test_notification_mail_sent_to_configured_addresses` (`Mail::fake`) ;
  - `test_rate_limited_after_five_per_hour` (429).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter → **Step 4 :** passe.
- [ ] **Step 5 : commit** — `feat(showcase): public access-request submission`

### Task 9 : écran back-office « Demandes d'accès »

**Files:**
- Create: `Modules/Core/app/Http/Controllers/AccessRequestController.php`, `Modules/Core/resources/views/access-requests/index.blade.php`, `public/js/modules/core/access-requests/index.js`
- Modify: `web.php` : préfixe `cores/demandes-acces`, nommé `cores.access-requests.*`, admins uniquement (vérification dans le contrôleur, sur le modèle de `GroupMemberController::isAdmin`). Routes `index` (vue), `data` (JSON), `show`, `status` (POST), `notes` (PUT), `payments` (POST), `payments.destroy` (DELETE).
- Modify: `layouts/partials/sidebar.blade.php` : entrée « Demandes d'accès » avec badge du nombre de demandes `new`, calculé par un View Composer enregistré dans `CoreServiceProvider` et réservé aux admins.
- Test: `tests/Feature/AccessRequestAdminTest.php`

**Interfaces:**
- Consumes: `AccessRequest::transitionTo`, `ShowcaseSettings::currency()`.
- Produces: `data` → `{rows: [...], counts: {new, contacted, payment_pending, paid, account_created, rejected, abandoned}}`, avec recherche `q` (nom, téléphone, e-mail) et filtre `status`.
- Enregistrer un paiement (`amount` > 0, `operator` dans la liste, `reference` requise, `paid_at`, `payer_phone` normalisé) fait passer la demande à `paid` si elle était `new`, `contacted` ou `payment_pending`.

- [ ] **Step 1 : tests** :
  - `test_admin_lists_and_filters_requests` ;
  - `test_trainer_is_forbidden` (403 sur toutes les routes) ;
  - `test_status_change_is_journaled` ;
  - `test_recording_payment_moves_to_paid_and_keeps_history` ;
  - `test_multiple_payments_are_allowed` ;
  - `test_invalid_operator_is_rejected`.
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter le contrôleur, puis la vue :
  - liste filtrable par onglets de statut, avec compteurs ;
  - fiche en panneau latéral (offcanvas, comme `groups/members`) : liens `tel:` et `https://wa.me/<chiffres>` ; historique ; notes ; formulaire « Enregistrer un paiement » ; boutons de statut ; bouton « Créer le compte » (Task 10).
- [ ] **Step 4 :** passe.
- [ ] **Step 5 : commit** — `feat(admin): access requests screen with Mobile Money payments`

### Task 10 : créer le compte depuis une demande + purge

**Files:**
- Modify: `AccessRequestController` (méthode `createAccount`, `POST cores/demandes-acces/{id}/account`), vue et JS de la Task 9 (modale de création, puis modale « Identifiants à transmettre »)
- Create: `Modules/Core/app/Console/Commands/PurgeAccessRequestsCommand.php` (`showcase:purge-requests`), planifiée chaque jour dans `routes/console.php`
- Test: `tests/Feature/AccessRequestAccountTest.php`

**Interfaces:**
- Consumes: `LearnerAccountService::create(..., temporaryPassword: true)` (Task 2), `Credentials`.
- Produces: réponse → `{success, learner_id, login, temporary_password, whatsapp_text, whatsapp_url}`. `login` vaut le téléphone E.164, ou l'e-mail à défaut. `whatsapp_text` est le texte fixe ci-dessous :
  ```
  Bonjour {prénom}, votre accès {BRAND_NAME} est prêt.
  Application : {APP_URL}
  Identifiant : {login}
  Mot de passe provisoire : {mot de passe}
  Vous choisirez votre propre mot de passe à la première connexion.
  ```
  `whatsapp_url` vaut `https://wa.me/{chiffres du whatsapp ou du téléphone}?text={urlencode(texte)}`.

- [ ] **Step 1 : tests** :
  - `test_creates_learner_prefilled_from_request_with_group` : la demande passe à `account_created` et `learner_id` est renseigné ;
  - `test_account_can_be_created_without_payment` ;
  - `test_duplicate_phone_returns_422_with_existing_account_hint` ;
  - `test_temporary_password_is_returned_once_and_forces_change` ;
  - `test_purge_deletes_closed_requests_older_than_retention` (les demandes ouvertes, même anciennes, sont conservées).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter. La modale propose les champs prénom, nom, téléphone, e-mail, groupe (pré-sélection = groupe de la demande) et matricule facultatif. Après création, elle affiche les identifiants, un bouton « Copier » et un bouton « Ouvrir WhatsApp » ; fermer la modale efface le mot de passe de l'écran → **Step 4 :** passe.
- [ ] **Step 5 : commit** — `feat(admin): create learner account from access request`

---

## Phase C — Mode invité dans la PWA

### Task 11 : socle de la session invité

**Files:**
- Create: `resources/js/learner/guest/guest-session.ts`, `resources/js/learner/guest/guest-session.test.ts`
- Modify: `resources/js/learner/api/client.ts` (méthodes `showcaseContent()`, `showcaseSettings()` et `submitAccessRequest(body)` ; base `/api/showcase/v1`), `domain/types.ts` (`UserProfile.is_guest?: boolean`, types `ShowcaseContent`, `ShowcaseSettings`, `AccessRequestBody`)
- Modify: `resources/js/learner/sync/engine.ts` :
  - `dispatch()` : retour immédiat si invité ;
  - `doSync()` : si invité, appeler `refreshGuestContent()` à la place de l'outbox et du delta ;
  - `warmMediaCache()` reste actif ;
  - `clearLocalData()` vide aussi la table `meta`.
- Modify: `resources/js/learner/main.ts` : filigrane `'Mode découverte'` quand `is_guest`.
- Modify: `resources/js/learner/views/login.ts` : bouton secondaire « Découvrir sans compte », visible seulement si `showcaseSettings().enabled` (appel en ligne ; masqué hors-ligne ou en cas d'erreur).
- Modify: `Modules/Core/routes/web.php` : les routes profondes du SPA (liste `$spaPath`) sortent du middleware `auth`, puisque le shell ne contient aucune donnée. Ajouter `/demande-acces`.
- Test: `guest-session.test.ts`

**Interfaces:**
- Produces: `enterGuestMode(): Promise<void>` (charge `/content` et `/settings`, écrit un profil invité `{id: 0, name: 'Invité', last_name: '', full_name: 'Invité', email: '', avatar_url: '', matricule: null, is_guest: true, xp: {zéros}}` et la vitrine dans les tables existantes, réglages dans `meta['showcase-settings']`, puis `hydrateFromDb()`).
- Produces: `refreshGuestContent(): Promise<void>` (remplace articles, quiz et decks par la vitrine du serveur ; conserve l'XP et la progression locales).
- Produces: `leaveGuestMode(): Promise<void>` (`clearLocalData()` puis événement `learner:logged-out`), `isGuest(): boolean`, `guestSettings(): ShowcaseSettings | null`.

- [ ] **Step 1 : tests vitest** (`fake-indexeddb`, `api` mocké) :
  - `enterGuestMode stores showcase content and a guest profile` ;
  - `dispatch is a no-op in guest mode (outbox stays empty)` ;
  - `sync in guest mode refreshes showcase instead of calling bootstrap/changes` ;
  - `leaving guest mode clears every local table` (outbox, articles, quiz, decks, profil, meta).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter → **Step 4 :** `npx vitest run && npx tsc --noEmit` passent.
- [ ] **Step 5 :** test PHP `test_spa_deep_routes_serve_shell_without_auth` dans `tests/Feature/LearnerShellTest.php`.
- [ ] **Step 6 : commit** — `feat(pwa): guest session plumbing`

### Task 12 : interface du mode invité

**Files:**
- Create: `resources/js/learner/guest/guest-ui.ts` (`guestBannerHtml()`, `lockedCardHtml(feature)`, `accessCtaHtml(context)`), `resources/js/learner/guest/guest-ui.test.ts`
- Modify: `components/app-shell.ts` (bandeau sous le header si invité ; pied de sidebar « Mode découverte » au lieu du niveau), `views/dashboard.ts` (accueil vitrine), `views/examens.ts`, `views/progression.ts`, `views/profil.ts`, `views/quiz-play.ts` (écran de fin), `views/reviser.ts` (fin de session)
- Test: `guest-ui.test.ts`

**Interfaces:**
- Consumes: `isGuest()`, `guestSettings()`, `leaveGuestMode()` (Task 11).
- `feature` ∈ `'exams' | 'badges' | 'leaderboard' | 'progression'`, avec ces textes fixes :
  - `exams` : « Examens surveillés, notés sur 20, avec classement. » ;
  - `badges` : « Gagnez des badges en progressant. » ;
  - `leaderboard` : « Comparez-vous aux autres apprenants de votre formation. » ;
  - `progression` : « Suivez votre progression semaine après semaine. »

  Chaque carte affiche « Disponible avec un compte » et un bouton « Demander un accès » (lien `/demande-acces`).
- Bandeau : « Mode découverte — » suivi du lien « Demander un accès ».
- Fin de quiz et fin de révision en mode invité : « Vous avez aimé ? Obtenez votre accès complet. » + bouton.
- Profil invité : seulement le bloc Préférences (thème, taille, sons ; enregistrement local sans `dispatch`) et le bouton « Quitter le mode découverte ».

- [ ] **Step 1 : tests vitest** :
  - `locked card shows feature copy and access link` ;
  - `banner links to access request` ;
  - `shell shows banner only for guests` (monter `app-shell` avec un profil invité puis un profil apprenant).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter dans les vues. Le tableau de bord invité affiche l'accroche (`settings.brand.tagline`), les contenus vitrine et les cartes verrouillées `badges` et `leaderboard` ; l'onglet Examens n'affiche que la carte `exams` → **Step 4 :** passe ; `npx tsc --noEmit` OK.
- [ ] **Step 5 : contrôle visuel.** Lancer `php artisan serve --host=127.0.0.1 --port=8123` et vérifier dans Chrome, en largeur mobile (390 px) et en desktop, l'accueil, l'onglet Examens et la fin d'un quiz.
- [ ] **Step 6 : commit** — `feat(pwa): guest mode screens and locked features`

### Task 13 : formulaire de demande d'accès (PWA)

**Files:**
- Create: `resources/js/learner/views/demande-acces.ts`, `resources/js/learner/views/demande-acces.test.ts`
- Modify: `main.ts` (route publique `/demande-acces`, titre « Demander un accès »)

**Interfaces:**
- Consumes: `api.submitAccessRequest`, `guestSettings()` (indicatif, groupes, `privacy_url`, inscription).
- Champs et ordre (spec §6.1) : prénom*, nom*, téléphone* (préfixe affiché `+{indicatif}`), case « Même numéro sur WhatsApp » (cochée, sinon champ WhatsApp), e-mail, organisation, formation souhaitée (select si `groups` n'est pas vide), message, consentement* (« J'accepte que mes données soient utilisées pour traiter ma demande. » + lien « Politique de confidentialité »). Le formulaire contient aussi le champ piège caché `website` et `started_at` fixé au montage.
- Hors-ligne : bouton « Envoyer » désactivé + message « Vous êtes hors-ligne — votre demande sera envoyable dès le retour du réseau. » Le brouillon est sauvegardé dans `sessionStorage` (`learner:access-draft`) et réactivé sur l'événement `online`.
- Succès : écran « Demande envoyée. Nous vous contacterons au {téléphone}. » Si `registration.accounts` n'est pas vide, afficher aussi le bloc « Frais d'inscription » (montant + devise, liste opérateur → numéro → bénéficiaire, mention), la note « Attendez la confirmation de notre équipe avant tout transfert. » et un bouton « Retour à la découverte ».

- [ ] **Step 1 : tests vitest** :
  - `submit disabled until required fields and consent` ;
  - `offline disables submit and keeps draft` ;
  - `success screen shows payment instructions when configured` ;
  - `server validation errors are shown next to fields` (réponse 422 mockée).
- [ ] **Step 2 :** FAIL → **Step 3 :** implémenter → **Step 4 :** passe ; `npm run build` OK.
- [ ] **Step 5 : contrôle visuel** (même procédure qu'à la Task 12) : envoyer une demande de test sur 127.0.0.1, la voir apparaître dans « Demandes d'accès », enregistrer un paiement, créer le compte, se connecter avec le téléphone, changer le mot de passe.
- [ ] **Step 6 : commit** — `feat(pwa): access request form`

---

## Phase D — Documentation et app native

### Task 14 : documentation et prompt Kotlin

**Files:**
- Modify: `PROMPT_APP_MOBILE_KOTLIN.md` (nouvelle section « Mode découverte et connexion par téléphone » : endpoints `/api/showcase/v1/*`, en-tête `X-Guest-Token` pour les médias, stockage local isolé et purge à la sortie, écrans équivalents aux Tasks 11 à 13, login par téléphone, écran « Nouveau mot de passe » sur `password_change_required`)
- Modify: `SPECIFICATIONS_VOLET_APPRENANT.md` (§0.7 Mode découverte, écran 1 Connexion mis à jour, nouvel écran « Demander un accès »)
- Modify: `DEPLOIEMENT.md` (activation du mode découverte dans les réglages, planification de `showcase:purge-requests` déjà couverte par le cron `schedule:run`, configuration mail pour les notifications)
- [ ] **Step 1 :** rédiger.
- [ ] **Step 2 :** suites complètes : `php artisan test && npx vitest run && npx tsc --noEmit && npm run build` → tout passe.
- [ ] **Step 3 : commit** — `docs: guest mode, phone login and Kotlin prompt`
