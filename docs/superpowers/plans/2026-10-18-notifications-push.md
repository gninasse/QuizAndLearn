# Notifications push (Web Push) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Envoyer des notifications système Web Push (sans Firebase) aux apprenants de la PWA : nouveau contenu, cartes à réviser, série en danger, examen ouvert — avec plafond quotidien, plage silencieuse et réglages admin.

**Architecture:** Les abonnements navigateur sont stockés dans `push_subscriptions`. Un `PushNotifier` décide (préférences, plafond, plage silencieuse, journal `push_deliveries`) et délègue l'envoi à une interface `PushSender` (implémentation `WebPushSender` sur `minishlink/web-push`, faux en test). Trois commandes planifiées toutes les 15 min détectent les événements ; aucune modification des contrôleurs d'édition : les nouvelles affectations sont détectées par comparaison avec un instantané (`push_assignment_snapshots`). Le service worker affiche et ouvre les notifications ; le profil PWA propose l'activation.

**Tech Stack:** Laravel 12 (Modules/Core), `minishlink/web-push` ^11 (MIT, PHP 8.2, sans ext-gmp), file `database`, scheduler Laravel, PWA TypeScript + Workbox `sw.ts`, vitest.

**Spec:** `docs/superpowers/specs/2026-10-18-notifications-push-design.md`

## Global Constraints

- Web Push standard, **aucun** SDK Firebase ni service tiers.
- Clés : `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` (`mailto:…`) lues via `config/push.php`. Clés absentes → tout le chantier est inerte (aucun envoi, `push.public_key` = `null` côté PWA, bouton masqué) et aucune erreur.
- Fuseau des horaires : `config('push.timezone')` = `env('PUSH_TIMEZONE', config('app.timezone'))`.
- Plafond quotidien par apprenant : réglage `push.daily_cap` (défaut **2**). Priorité : `exam_open` > `new_content` > `cards_due` > `streak`. `exam_open` part toujours (et compte) ; les autres sont abandonnés si le plafond du jour est atteint.
- Plage silencieuse : rien entre **21 h et 7 h** (heure `push.timezone`), sauf `exam_open`.
- Rappel quotidien « cartes » à l'heure `push.daily_hour` (défaut **18**) ; « série en danger » à **20 h** si `current_streak ≥ 2` et `last_activity_date` ≠ aujourd'hui.
- Réglages admin `push.types.{new_content,cards_due,streak,exam_open}` (défaut `true`), stockés via `AppSetting` comme `showcase.*`.
- Confidentialité : corps de notification génériques ; le titre d'un contenu est permis, jamais une question, une réponse ou une note.
- La permission navigateur n'est demandée **qu'au clic** sur « Activer les notifications ».
- Mode consultation (« Voir en tant que ») et mode invité : aucune inscription possible (la liste blanche lecture seule bloque déjà les écritures ; la PWA masque le bouton).
- Textes en français accentué ; « groupe » via `term()` / `t()`.
- Ne jamais lancer `php artisan storage:link`. Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Textes exacts (FR)

| Type | Titre | Corps | URL |
|---|---|---|---|
| `new_content` (1 contenu) | `Nouveau quiz disponible` / `Nouvel article disponible` / `Nouveau paquet de cartes disponible` | `{titre}` | `/quiz/{id}` · `/articles/{id}` · `/reviser` |
| `new_content` (n > 1) | `{n} nouveaux contenus disponibles` | `Ouvrez l'application pour les découvrir.` | `/` |
| `cards_due` | `Révisions du jour` | `{n} carte(s) vous attendent pour réviser.` | `/reviser` |
| `streak` | `Votre série est en danger 🔥` | `Votre série de {n} jours se termine ce soir.` | `/` |
| `exam_open` | `Examen ouvert` | `L'examen « {titre} » est ouvert.` | `/examens` |

(Vérifier les chemins réels dans `resources/js/learner/router.ts` et ajuster les URL à ses routes ; consigner l'écart dans le rapport.)

## Review Focus

1. Abonnement expiré (le service push répond 404 ou 410) → ligne supprimée, pas de nouvel essai, pas d'exception remontée à la commande (test Task 2).
2. Même endpoint réinscrit par un autre apprenant sur le même navigateur → l'abonnement change de propriétaire, pas de doublon ni de fuite vers l'ancien compte (test Task 1).
3. Commande relancée deux fois dans le même quart d'heure → aucune notification en double (dédoublonnage par `ref` dans `push_deliveries`) (tests Task 3).
4. Premier passage de la détection de nouveaux contenus sur une base existante → instantané initialisé **sans** envoyer des centaines de notifications (test Task 3).
5. Navigateur sans `PushManager` (iOS hors écran d'accueil, vieux navigateur) ou permission refusée → message clair, aucun appel API (test Task 5).

---

### Task 1: Dépendance, configuration, abonnements

**Files:**
- Modify: `composer.json` / `composer.lock` (`composer require minishlink/web-push:^11`)
- Create: `config/push.php`, migration `Modules/Core/database/migrations/2026_10_21_100000_create_push_tables.php`, `Modules/Core/app/Models/PushSubscription.php`, `Modules/Core/app/Models/PushDelivery.php`, `Modules/Core/app/Http/Controllers/Learner/Api/PushSubscriptionController.php`, `Modules/Core/app/Console/Commands/GenerateVapidKeys.php` (`push:vapid`, affiche les deux clés au format `.env`, n'écrit aucun fichier)
- Modify: `Modules/Core/routes/web.php` (bloc `api/learner/v1`, groupe `['impersonation','auth','learner']`), `Modules/Core/app/Services/LearnerContentService.php` ou la réponse de `/me` (exposer `push: {public_key: ?string}`), `.env.example`, `DEPLOIEMENT.md` (section « Notifications push » : `push:vapid`, cron `schedule:run`, worker `queue:work`, HTTPS obligatoire)
- Test: `tests/Feature/PushSubscriptionTest.php`

**Interfaces:**
- Tables : `push_subscriptions(id, learner_id FK cascade, endpoint text, endpoint_hash char(64) unique, p256dh string, auth string, user_agent string null, last_used_at timestamp null, timestamps)` ; `push_deliveries(id, learner_id FK cascade, type string(20), ref string(64) null, sent_at timestamp, index(learner_id, sent_at), index(learner_id, type, ref))` ; `push_assignment_snapshots(id, group_id, content_type string(20), content_id, unique(group_id, content_type, content_id))`.
- Routes : `POST /api/learner/v1/push-subscriptions` (`learn.v1.push.store`, corps `{endpoint (url https), keys: {p256dh, auth}}`) → 201 ; `DELETE /api/learner/v1/push-subscriptions` (`learn.v1.push.destroy`, corps `{endpoint}`) → 204.
- `config('push.vapid.public_key')`, `config('push.vapid.private_key')`, `config('push.vapid.subject')`, `config('push.timezone')`, `config('push.enabled')` (= les deux clés non vides).

- [ ] **Step 1: Tests** : `test_learner_can_subscribe_and_unsubscribe`, `test_resubscribe_same_endpoint_moves_ownership` (Review Focus 2), `test_endpoint_must_be_https_url`, `test_guest_and_staff_cannot_subscribe` (401/403), `test_impersonation_cannot_subscribe` (code `read_only`, voir `tests/Feature/ImpersonationTest.php` pour fabriquer une consultation), `test_me_exposes_public_key_or_null`.
- [ ] **Step 2:** Run `php artisan test --filter=PushSubscriptionTest` → FAIL.
- [ ] **Step 3:** Implémenter ; `endpoint_hash = hash('sha256', endpoint)`.
- [ ] **Step 4:** Run → PASS ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(push): web push subscriptions and VAPID config`.

### Task 2: PushSender et PushNotifier (règles d'envoi)

**Files:**
- Create: `Modules/Core/app/Services/Push/PushSender.php` (interface), `Modules/Core/app/Services/Push/WebPushSender.php`, `Modules/Core/app/Services/Push/PushMessage.php`, `Modules/Core/app/Services/Push/PushNotifier.php`, `Modules/Core/app/Services/Push/PushSettings.php`, `Modules/Core/app/Jobs/SendPushNotification.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php` (bind `PushSender` → `WebPushSender`)
- Test: `tests/Feature/PushNotifierTest.php`, `tests/Support/FakePushSender.php`

**Interfaces:**
- `final class PushMessage { __construct(public string $type, public string $title, public string $body, public string $url, public ?string $ref = null) }` ; `toPayload(): string` (JSON `{title, body, url, tag: type, icon: '/icons/icon-192.png'}` — vérifier le chemin d'icône dans `public/manifest.json`).
- `interface PushSender { /** @return 'sent'|'expired'|'failed' */ public function send(PushSubscription $s, PushMessage $m): string; }`
- `PushSettings` : `typeEnabled(string $type): bool`, `dailyHour(): int`, `dailyCap(): int`, `save(array $validated): void`, `toArray(): array`.
- `PushNotifier::notify(Learner $learner, PushMessage $m, ?Carbon $now = null): bool` — renvoie `false` sans envoyer si : push désactivé (clés), type désactivé, aucun abonnement, déjà envoyé (même `learner_id`, `type`, `ref` non null, quel que soit le jour), plage silencieuse (sauf `exam_open`), plafond atteint (sauf `exam_open`), préférence apprenant `notifications_enabled.new_quiz` / `new_article` à `false` pour un contenu de ce type. Sinon : `SendPushNotification::dispatch()` par abonnement, une ligne `push_deliveries` (une par message, pas par abonnement), `true`.
- `SendPushNotification::handle(PushSender $sender)` : `expired` → supprime l'abonnement ; `sent` → `last_used_at = now()`.

- [ ] **Step 1: Tests** (avec `FakePushSender` lié dans le conteneur, `Queue` en `sync`) : `test_sends_to_every_subscription_and_logs_one_delivery`, `test_disabled_without_vapid_keys`, `test_type_disabled_by_admin`, `test_quiet_hours_block_all_but_exam` (20 h 59 envoie, 21 h 00 et 06 h 59 bloquent, `exam_open` à 23 h envoie), `test_daily_cap_with_exam_priority` (cap 2 : 2 envois, 3ᵉ `cards_due` refusé, `exam_open` passe quand même), `test_same_ref_never_sent_twice`, `test_learner_preference_disables_new_quiz`, `test_expired_subscription_is_deleted` (Review Focus 1), `test_payload_has_no_extra_fields`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter. `WebPushSender` : `new WebPush(['VAPID' => [...]])`, `sendOneNotification()`, `isSubscriptionExpired()` → `expired`. Les heures se comparent dans `config('push.timezone')`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(push): notifier with cap, quiet hours and expiry handling`.

### Task 3: Détection des événements (commandes planifiées)

**Files:**
- Create: `Modules/Core/app/Console/Commands/PushNewContent.php` (`push:new-content`), `PushDailyReminders.php` (`push:daily-reminders`), `PushExamOpen.php` (`push:exam-open`)
- Modify: `routes/console.php` (les trois `->everyFifteenMinutes()->withoutOverlapping()`), enregistrement des commandes dans le provider du module (comme `showcase:purge-requests`)
- Test: `tests/Feature/PushCommandsTest.php`

**Interfaces:**
- Consomme `PushNotifier::notify()`, `PushMessage`, tables `push_assignment_snapshots`, `push_deliveries`.
- `push:new-content` : si l'instantané est vide → l'initialiser avec toutes les paires actuelles de `group_quiz`, `group_article`, `group_flashcard_deck` et **ne rien envoyer** ; sinon, pour chaque paire absente de l'instantané : si le contenu est actif (`is_active` pour quiz et article ; quiz `available_from` null ou ≤ maintenant), viser les apprenants du groupe ; regrouper par apprenant (1 contenu → message dédié, `ref = "{type}:{id}"` ; plusieurs → message groupé, `ref = "new:" . sha1(liste triée des refs)`) ; insérer ensuite les paires dans l'instantané. Les paires retirées sont supprimées de l'instantané. En plage silencieuse, la commande sort sans rien faire (les paires restent nouvelles jusqu'à 7 h).
- `push:daily-reminders` : à l'heure `dailyHour` (quart d'heure 0–14 de cette heure) → `cards_due` pour chaque apprenant abonné ayant ≥ 1 ligne `flashcard_item_reviews` avec `next_review ≤ maintenant`, `ref = "cards:" . date du jour` ; à 20 h (quart 0–14) → `streak` selon `learner_xp`, `ref = "streak:" . date du jour`.
- `push:exam-open` : examens `is_active`, `available_from` dans `]maintenant − 1 jour, maintenant]`, pour les apprenants de leurs groupes (`group_exam`), `ref = "exam:{id}"`.
- Ne viser que des apprenants actifs (`users.is_active`), non suspendus, ayant au moins un abonnement (requête sur `push_subscriptions` d'abord pour rester léger).

- [ ] **Step 1: Tests** (`travelTo`, `FakePushSender`) : `test_first_run_initialises_snapshot_without_sending` (Review Focus 4), `test_new_assignment_notifies_group_learners_once` (deuxième exécution → rien, Review Focus 3), `test_several_new_contents_grouped_in_one_message`, `test_inactive_or_future_quiz_not_notified`, `test_quiet_hours_defer_new_content_to_morning`, `test_cards_due_sent_at_daily_hour_only`, `test_streak_at_20h_only_when_at_risk`, `test_exam_open_once_and_ignores_far_past`, `test_suspended_learner_not_notified`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(push): scheduled detection of new content, reminders and exam opening`.

### Task 4: Réglages admin « Notifications »

**Files:**
- Modify: `Modules/Core/app/Http/Controllers/GeneralSettingsController.php`, `Modules/Core/resources/views/settings/general.blade.php` (+ son script s'il existe sous `public/js/modules/core/settings/`), `Modules/Core/routes/web.php`
- Create: route `POST /cores/parametres/notifications/test` (`cores.settings.push.test`)
- Test: `tests/Feature/PushSettingsTest.php`

**Interfaces:**
- Consomme `PushSettings::save/toArray`, `PushNotifier`.
- Section « Notifications » : état (« Clés VAPID configurées » / « Non configuré — voir DEPLOIEMENT.md »), 4 interrupteurs, heure du rappel (`0`–`23`), plafond (`1`–`5`), bouton « Envoyer une notification de test ».
- Le test (Ruling : l'admin n'a pas d'abonnement propre, la spec dit « vers le navigateur de l'admin, s'il est abonné ») : le bouton est désactivé avec l'explication « Abonnez-vous depuis l'espace apprenant d'un compte de test » si l'utilisateur courant n'a pas de fiche `learner` avec abonnement ; sinon il envoie `PushMessage('test', 'Notification de test', 'Les notifications fonctionnent.', '/')` à ses propres abonnements en contournant plafond, plage et dédoublonnage (méthode `PushNotifier::sendTest(Learner): int` = nb d'abonnements visés).

- [ ] **Step 1: Tests** : `test_admin_saves_push_settings` (bornes 0–23 et 1–5 → 422 hors bornes), `test_trainer_cannot_change_push_settings` (403), `test_test_button_sends_to_own_subscriptions_only`, `test_settings_page_shows_vapid_status`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter en suivant le motif existant de la section Terminologie.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(push): admin notification settings and test send`.

### Task 5: PWA — service worker et activation depuis le profil

**Files:**
- Modify: `resources/js/learner/sw.ts`, `resources/js/learner/views/profil.ts`, `resources/js/learner/api/` (client des deux routes), type de `/me` (champ `push`)
- Create: `resources/js/learner/core/push.ts`, `resources/js/learner/core/push.test.ts`
- Test: vitest

**Interfaces:**
- `push.ts` : `pushSupport(): 'supported' | 'ios-install-needed' | 'unsupported'` ; `pushState(): Promise<'on' | 'off' | 'denied'>` ; `enablePush(publicKey: string): Promise<'on' | 'denied' | 'error'>` (demande `Notification.requestPermission()` puis `registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey})`, puis POST) ; `disablePush(): Promise<void>` (unsubscribe + DELETE) ; `urlBase64ToUint8Array(s: string): Uint8Array`.
- `sw.ts` : `push` → `showNotification(title, {body, icon, badge, tag, data: {url}})` (payload invalide → notification générique « Learn&Quiz » / « Nouvelle notification ») ; `notificationclick` → ferme, focalise un client de même origine et `navigate(url)`, sinon `clients.openWindow(url)` ; n'ouvre que des URL de même origine.
- Profil : carte « Notifications » avec interrupteur ; masquée si `push.public_key` null, en mode invité ou en consultation ; texte d'aide iOS « Sur iPhone, ajoutez d'abord l'application à l'écran d'accueil (Partager → Sur l'écran d'accueil). » si `ios-install-needed` ; message « Notifications bloquées dans les réglages du navigateur. » si `denied`. Aucune demande de permission au chargement.
- Déconnexion : appeler `disablePush()` avant `clearLocalData` pour que le navigateur partagé ne reçoive plus les notifications de l'ancien compte.

- [ ] **Step 1: Tests vitest** : `urlBase64ToUint8Array` (vecteur connu), `pushSupport` selon `navigator`/`PushManager` simulés (Review Focus 5), `enablePush` ne fait aucun appel API si permission refusée, `enablePush` POST le JSON de l'abonnement, profil ne rend pas la carte en invité / consultation / sans clé.
- [ ] **Step 2:** `npx vitest run resources/js/learner/core/push.test.ts` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** `npx vitest run` → PASS ; `npm run typecheck` → propre ; `npm run build` → OK.
- [ ] **Step 5:** Commit `feat(pwa): push notifications opt-in and service worker handlers`.
