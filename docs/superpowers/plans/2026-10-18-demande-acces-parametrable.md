# Demande d'accès paramétrable — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre au super admin et aux admins de régler l'action « Demander un accès » du mode découverte : activation, canaux de contact, champs du formulaire (dont 5 champs personnalisés) et textes.

**Architecture:** Les réglages vivent dans `app_settings` sous `showcase.access.*`, lus et normalisés par `ShowcaseSettings` (une méthode `accessRequest(): array` renvoie une configuration complète avec valeurs par défaut) et exposés dans `publicPayload()['access_request']`. L'envoi du formulaire construit ses règles de validation à partir de cette configuration et stocke les champs personnalisés dans une colonne JSON. La PWA (bandeau, appels à l'action, page `/demande-acces`) et l'app Kotlin lisent le même bloc public.

**Tech Stack:** Laravel 12 (Modules/Core), Blade AdminLTE + jQuery, PWA TypeScript + vitest.

**Spec:** `docs/superpowers/specs/2026-10-18-demande-acces-parametrable-design.md`

## Global Constraints

- Droits : super admin **et** admins (le contrôle `authorizeAdmin()` existant de `ShowcaseAdminController`) ; formateurs exclus.
- Défauts = comportement actuel : demandes acceptées, un seul canal `form`, champs `whatsapp`, `email`, `organization`, `group`, `message` en `optional`, aucun champ personnalisé, textes actuels (« Demander un accès », etc.).
- Toujours présents et obligatoires : `first_name`, `last_name`, `phone`, `consent`. Jamais réglables.
- Au plus **5** champs personnalisés ; types `text` (≤ 255 car.), `textarea` (≤ 2000), `select` (2 à 20 choix, chacun ≤ 100 car.) ; libellé ≤ 80 car.
- Canaux : `form`, `whatsapp` (`number`, `message` ≤ 500), `phone` (`number`), `email` (`address`, `subject` ≤ 150), `link` (`url` https, `label` ≤ 60). Chaque type au plus une fois ; ordre conservé ; au moins un canal si `enabled`. Numéros normalisés en E.164 avec `PhoneNumber::normalize($n, ShowcaseSettings::defaultCountryCode())`.
- Textes : `button_label` ≤ 40, `title` ≤ 80, `intro` ≤ 1000, `confirmation` ≤ 500 ; vide → texte par défaut.
- Demandes fermées : API `POST /api/showcase/v1/access-requests` → **403** `{success:false, code:'access_requests_closed', message:'Les demandes d'accès sont fermées pour le moment.'}` ; aussi quand le canal `form` est absent.
- Le bloc public ne contient jamais `notification_emails` ni la durée de conservation.
- Textes FR accentués ; « groupe » via `term()` (Blade/PHP), `window.T` (back-office JS), `t()` (PWA).
- Ne jamais lancer `php artisan storage:link`. Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Forme exacte du bloc public `access_request`

```json
{
  "enabled": true,
  "button_label": "Demander un accès",
  "title": "Demander un accès",
  "intro": null,
  "confirmation": "Votre demande a bien été envoyée. Nous vous recontactons rapidement.",
  "channels": [
    {"type": "form"},
    {"type": "whatsapp", "number": "+2250700000000", "message": "Bonjour, je souhaite un accès à Learn&Quiz."},
    {"type": "phone", "number": "+2250700000000"},
    {"type": "email", "address": "contact@exemple.ci", "subject": "Demande d'accès"},
    {"type": "link", "url": "https://forms.gle/…", "label": "Formulaire en ligne"}
  ],
  "fields": {"whatsapp": "optional", "email": "optional", "organization": "optional", "group": "optional", "message": "optional"},
  "custom_fields": [
    {"key": "cf_a1b2c3", "label": "Ville", "type": "select", "options": ["Abidjan", "Bouaké"], "required": true}
  ]
}
```

(Vérifier le texte de confirmation et l'intro actuellement affichés par `resources/js/learner/views/demande-acces.ts` et les reprendre comme défauts exacts.)

## Review Focus

1. Champ masqué envoyé quand même (client ancien ou malveillant) → ignoré, jamais stocké (test Task 2).
2. Champ personnalisé supprimé après réception de demandes → les anciennes demandes affichent toujours libellé et réponse (stockés ensemble) (test Task 2).
3. Admin qui désactive tous les canaux en laissant les demandes ouvertes → 422 clair, rien n'est enregistré (test Task 1).
4. Invité dont la PWA a gardé en cache d'anciens réglages (hors ligne) puis demandes fermées → l'envoi reçoit 403 et la page affiche le message de fermeture, pas une erreur générique (test Task 4).
5. Numéro WhatsApp saisi au format local (`07 00 00 00 00`) → normalisé E.164 à l'enregistrement, lien `https://wa.me/2250700000000?text=…` correctement encodé (tests Tasks 1 et 4).

---

### Task 1: Réglages (modèle, validation, bloc public)

**Files:**
- Modify: `Modules/Core/app/Services/ShowcaseSettings.php`, `Modules/Core/app/Http/Controllers/ShowcaseAdminController.php` (`saveSettings`)
- Test: `tests/Feature/AccessRequestSettingsTest.php`

**Interfaces:**
- `ShowcaseSettings::accessRequest(): array` — bloc complet ci-dessus, défauts appliqués.
- `ShowcaseSettings::accessRequestsOpen(): bool` — `enabled()` && `accessRequest()['enabled']` && un canal `form` présent.
- `ShowcaseSettings::saveAccessRequest(array $validated): void` — normalise (numéros E.164, clés `cf_` + 6 caractères aléatoires `[a-z0-9]` pour les nouveaux champs personnalisés, clé existante conservée si fournie et valide `^cf_[a-z0-9]{6}$`).
- `publicPayload()` ajoute `'access_request' => $this->accessRequest()`.
- `saveSettings` accepte un sous-tableau optionnel `access_request` (absent → réglages d'accès inchangés), validé selon les Global Constraints ; erreur « Activez au moins un canal de contact. » sur `access_request.channels` (Review Focus 3) ; numéro invalide → erreur sur le champ du canal.

- [ ] **Step 1: Tests** : `test_defaults_match_current_behaviour`, `test_admin_saves_full_access_configuration` (puis `GET /api/showcase/v1/settings` renvoie le bloc), `test_numbers_are_normalised_to_e164` (Review Focus 5), `test_enabled_without_channel_is_rejected`, `test_limits` (6 champs personnalisés, select à 1 choix, URL http, type de canal dupliqué → 422), `test_custom_field_keys_are_generated_and_kept`, `test_trainer_forbidden`, `test_public_payload_never_contains_notification_emails`.
- [ ] **Step 2:** `php artisan test --filter=AccessRequestSettingsTest` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** → PASS ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(showcase): configurable access request settings`.

### Task 2: Envoi dynamique, fermeture, champs personnalisés

**Files:**
- Create: migration `Modules/Core/database/migrations/2026_10_22_110000_add_extra_fields_to_access_requests.php` (`extra_fields` JSON nullable)
- Modify: `Modules/Core/app/Http/Controllers/Showcase/AccessRequestSubmitController.php`, `Modules/Core/app/Models/AccessRequest.php` (cast `array`), `Modules/Core/app/Http/Controllers/AccessRequestController.php` (`detail()` ajoute `extra_fields`), `public/js/modules/core/access-requests/index.js` (affiche chaque `{label, value}` dans la fiche, échappé avec `esc`)
- Test: `tests/Feature/AccessRequestDynamicSubmitTest.php`

**Interfaces:**
- Consomme `accessRequest()`, `accessRequestsOpen()`.
- Corps accepté : champs actuels + `extra.{key}` pour chaque champ personnalisé. Règles : champ `hidden` → retiré des données avant enregistrement ; `required` → `required` ; `optional` → `nullable` ; `select` → `Rule::in(options)`. Stockage `extra_fields = [{key, label, value}]` (libellé copié au moment de l'envoi, valeurs vides omises).
- Fermé → 403 `access_requests_closed` (avant toute validation). Réponse 201 inchangée, plus `confirmation` (texte réglé).

- [ ] **Step 1: Tests** : `test_closed_returns_403_with_code`, `test_closed_when_form_channel_absent`, `test_required_optional_hidden_fields` (organisation obligatoire → 422 si vide ; e-mail masqué envoyé → non stocké, Review Focus 1), `test_custom_fields_validated_and_stored_with_label`, `test_old_request_keeps_label_after_field_removed` (Review Focus 2), `test_admin_detail_returns_extra_fields`, `test_existing_tests_unchanged` (lancer `AccessRequestSubmitTest` : il doit passer sans modification).
- [ ] **Step 2:** → FAIL.
- [ ] **Step 3:** Implémenter ; `php artisan migrate` sur la base de dev après les tests.
- [ ] **Step 4:** → PASS ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(showcase): dynamic access request form and closing`.

### Task 3: Section « Demande d'accès » du back-office

**Files:**
- Modify: `Modules/Core/resources/views/showcase/settings.blade.php` (nouvelle carte et son script dans le `@push('js')` existant, ou fichier `public/js/modules/core/showcase/access-request.js` si le script dépasse ~150 lignes)
- Test: `tests/Feature/AccessRequestSettingsTest.php` (ajouts)

**Interfaces:**
- Consomme `ShowcaseSettings::accessRequest()` (valeurs initiales injectées en JSON avec `@json`), envoie `access_request` dans le même `PUT` que le reste du formulaire.
- Contenu de la carte : interrupteur « Accepter les demandes d'accès » ; liste des canaux (case à cocher par type, flèches ↑/↓ pour l'ordre, sous-champs du canal affichés quand il est coché, mention « Seul le formulaire alimente le suivi des demandes. ») ; tableau des champs réglables (Masqué / Facultatif / Obligatoire en boutons radio ; ligne grisée non modifiable pour Prénom, Nom, Téléphone, Consentement) ; champs personnalisés (ajouter jusqu'à 5, libellé, type, choix un par ligne pour une liste, obligatoire, supprimer) ; textes (4 champs, placeholder = texte par défaut). Erreurs de validation affichées sous le champ concerné. Libellé du champ groupe via `term('Group')`.

- [ ] **Step 1: Tests** : `test_settings_page_shows_access_section` (`assertSee` de « Demande d'accès », « Accepter les demandes d'accès », « Seul le formulaire alimente le suivi des demandes. »), `test_settings_page_embeds_current_config` (la clé d'un champ personnalisé enregistré apparaît dans le JSON injecté).
- [ ] **Step 2:** → FAIL.
- [ ] **Step 3:** Implémenter ; `node --check` sur tout fichier JS créé.
- [ ] **Step 4:** → PASS.
- [ ] **Step 5:** Commit `feat(showcase): access request settings UI`.

### Task 4: PWA et app Kotlin

**Files:**
- Modify: `resources/js/learner/guest/guest-session.ts` (type `ShowcaseSettings` : bloc `access_request`), `resources/js/learner/guest/guest-ui.ts`, `resources/js/learner/views/demande-acces.ts`, leurs tests vitest, `PROMPT_APP_MOBILE_KOTLIN.md` (section vitrine : bloc `access_request`, canaux, champs, 403 `access_requests_closed`), `SPECIFICATIONS_VOLET_APPRENANT.md` (§ mode découverte)
- Create: `resources/js/learner/guest/access-channels.ts`, `resources/js/learner/guest/access-channels.test.ts`

**Interfaces:**
- `access-channels.ts` : `channelHref(c: AccessChannel): string` (`whatsapp` → `https://wa.me/{chiffres sans +}?text={encodeURIComponent(message)}` ; `phone` → `tel:{number}` ; `email` → `mailto:{address}?subject={encodeURIComponent(subject)}` ; `link` → `url` ; `form` → `/demande-acces?canal=form`) ; `accessEntryHref(cfg): string | null` (null si fermé ; un seul canal → son lien ; plusieurs → `/demande-acces`) ; `channelLabel(c): string` (« Remplir le formulaire », « Écrire sur WhatsApp », « Appeler », « Envoyer un e-mail », `label` du lien).
- `guest-ui.ts` : bandeau, carte verrouillée et appel à l'action utilisent `button_label` et `accessEntryHref` ; rien n'est rendu (ni lien) si fermé ; un lien externe porte `target="_blank" rel="noopener"`, un lien interne garde `data-link`.
- `demande-acces.ts` : fermé → message de fermeture ; plusieurs canaux sans `?canal=form` → cartes de choix (formulaire en premier s'il existe) ; formulaire : titre, intro, champs selon `fields` (le champ groupe reste aussi conditionné à l'existence de groupes ouverts), champs personnalisés envoyés sous `extra[key]`, message `confirmation` après succès ; réponse 403 `access_requests_closed` → message de fermeture (Review Focus 4). Valeurs réglées toujours échappées (`html`/`escapeHtml`).

- [ ] **Step 1: Tests vitest** : `channelHref` pour les 5 types (encodage du texte WhatsApp, Review Focus 5), `accessEntryHref` (fermé → null, un canal → direct, plusieurs → page), bandeau absent si fermé, libellé personnalisé rendu échappé (`<b>` affiché en texte), page : choix des canaux, champ masqué absent, champ obligatoire marqué `*`, champ personnalisé `select` envoyé dans `extra`, 403 → message de fermeture.
- [ ] **Step 2:** `npx vitest run resources/js/learner/guest resources/js/learner/views/demande-acces.test.ts` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** `npx vitest run` → PASS ; `npm run typecheck` → propre ; `npm run build` → OK ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(pwa): configurable access request entry and form`.
