# Prompt AI Studio — Application Android native « Learn&Quiz »

> Copiez tout ce document dans https://aistudio.google.com/apps (ou dans Gemini)
> pour générer l'application. Remplacez `{{BASE_URL}}` par l'URL réelle de votre
> API (ex. `https://5b91-102-67-126-233.ngrok-free.app` — attention, l'URL ngrok
> gratuite change à chaque relance).

---

Tu es un développeur Android senior. Développe une application Android native
complète en **Kotlin (dernière version stable) avec Jetpack Compose et
Material 3**, nommée **Learn&Quiz**, application d'apprentissage pour des
apprenants francophones. L'application est **offline-first** : elle fonctionne
intégralement sans réseau après une première synchronisation, et rejoue les
actions en attente dès le retour de la connexion. Toute l'interface est en
**français**.

## 1. Stack technique imposée

- Kotlin 2.x, Jetpack Compose, Material 3 (couleurs dynamiques + thème sombre)
- Architecture MVVM : ViewModel + StateFlow, coroutines
- **Room** : base locale, **unique source de vérité** de l'UI
- **Retrofit + OkHttp + kotlinx.serialization** : client API
- **Hilt** : injection de dépendances
- **WorkManager** : synchronisation en arrière-plan (contrainte NetworkType.CONNECTED, backoff exponentiel)
- **DataStore chiffré** (ou EncryptedSharedPreferences) : stockage du token
- Navigation Compose ; une seule Activity

## 2. Architecture offline-first (OBLIGATOIRE — cœur de l'app)

L'UI ne lit JAMAIS le réseau directement. Flux de données :

1. **Bootstrap** (première connexion, en ligne) : `GET /api/mobile/v1/bootstrap`
   → tout le contenu est écrit dans Room + le `cursor` (timestamp serveur) est stocké.
2. **Delta sync** (à chaque retour d'app / retour réseau / pull-to-refresh) :
   `GET /api/mobile/v1/changes?since={cursor}` → pour chaque collection,
   `updated[]` est upserté dans Room et **tout id absent de `authorized_ids[]`
   est supprimé** (contenu désassigné). Nouveau cursor stocké.
3. **Outbox** (écritures hors-ligne) : chaque action locale (progression
   d'article, tentative de quiz, révision de carte…) est écrite dans une table
   Room `outbox` avec un **UUID client unique** (`id`), puis rejouée vers
   `POST /api/mobile/v1/actions` dès que possible (WorkManager). Une action
   `applied` ou `duplicate` est retirée de l'outbox ; une action `rejected`
   est retirée aussi et signalée à l'utilisateur (snackbar). Le serveur est
   **idempotent** grâce à l'UUID : rejouer ne double jamais l'XP.
4. **Optimistic UI** : les écritures modifient Room immédiatement (XP local,
   statut du quiz, état SM-2 de la carte) ; le serveur reste l'autorité et le
   prochain delta réconcilie.
5. Bandeau discret « Hors-ligne » quand pas de réseau + compteur d'actions en
   attente ; icône de sync dans la barre supérieure.

**Exception** : les EXAMENS sont online-only (intégrité) — voir §7.

## 3. Contrat API (existant, ne pas inventer d'autres endpoints)

Base : `{{BASE_URL}}/api/mobile/v1` — toutes les requêtes en JSON
(`Accept: application/json`). Routes protégées : header
`Authorization: Bearer {token}`.

### 3.1 Authentification

`POST /login`
```json
{ "login": "email ou identifiant", "password": "…", "device_name": "Pixel 8 de Jean" }
```
→ 200 :
```json
{ "success": true, "token": "1|abcdef…", "expires_in_days": 30,
  "user": { "id": 1, "name": "Alice", "last_name": "Lemaire", "full_name": "Alice Lemaire",
            "email": "…", "matricule": "APP-2026-001",
            "xp": { "total_xp": 330, "current_level": 4, "current_streak": 1,
                    "longest_streak": 2, "last_activity_date": "2026-08-26" } } }
```
Erreurs : `401` identifiants invalides · `403` compte non apprenant ·
`429` + header `Retry-After` après 5 échecs/minute (afficher le délai).
Le token expire après 30 jours → toute réponse `401` sur une route protégée
doit purger la session locale et renvoyer à l'écran de connexion.

`GET /me` → profil + XP à jour.
`PUT /password` `{current_password, password, password_confirmation}` →
`422` avec `errors.{champ}[]` à afficher sous les champs ; succès = les autres
appareils sont déconnectés.
`POST /logout` (révoque cet appareil) · `POST /logout-all`.

### 3.2 Contenu

`GET /bootstrap` → payload complet :
```json
{ "cursor": "2026-08-26T10:00:00+02:00",
  "user": { …comme login… },
  "articles": [ { "id": 1, "title": "…", "category": "…", "content": "<p>HTML riche</p>",
                  "estimated_reading_time": 5, "is_favorite": false, "rating": 0,
                  "status": "unread|reading|completed", "progress_percentage": 0,
                  "created_at": "ISO8601", "updated_at": "ISO8601" } ],
  "quizzes": [ { "id": 183, "title": "…", "description": "…", "duration": 15,
                 "passing_score": 60, "max_attempts": 10, "shuffle_questions": false,
                 "show_correct_answers": true, "status": "unread|in_progress|completed",
                 "max_attempts_reached": false, "updated_at": "ISO8601",
                 "attempts": [ { "id": 9, "attempt_number": 1, "status": "completed",
                                 "score": 83.33, "points_earned": 20, "points_total": 24,
                                 "passed": true, "completed_at": "ISO8601" } ],
                 "questions": [ { "id": 401, "question_text": "<p>HTML</p>", "type": "…",
                                  "points": 2, "order": 1, "options": { } } ] } ],
  "decks": [ { "id": 2, "titre": "…", "description": "…", "matiere": "…",
               "algorithme": "sm2", "easiness_default": 2.5, "interval_min": 1,
               "interval_max": 365, "updated_at": "ISO8601",
               "cards": [ { "id": 8, "recto": "<p>HTML</p>", "verso": "<p>HTML</p>",
                            "tags": null, "ordre": 1,
                            "review": null } ] } ],
  "exams": [ { "id": 5, "title": "…", "duration": 20, "passing_score": 50, "note_max": 20,
               "max_attempts": 10, "available_from": null, "available_until": null,
               "plein_ecran_force": false, "anti_capture_strict": false,
               "navigation_interdite": false, "publication_resultats": "immediate",
               "classement_visible": true, "classement_anonyme": false,
               "status": "locked|available|expired|in_progress|completed",
               "max_attempts_reached": false, "updated_at": "ISO8601", "attempts": [ … ] } ],
  "badges": [ { "id": 1, "name": "Premier pas", "description": "…", "icon": "🚀", "unlocked": true } ],
  "preferences": { "locale": "fr", "theme": "light|dark", "font_size": "small|medium|large",
                   "sound_enabled": true, "notifications_enabled": { } } }
```
`review` d'une carte (état SM-2 par apprenant, null si jamais révisée) :
```json
{ "easiness_factor": 2.6, "interval_days": 6, "repetitions": 2,
  "last_reviewed": "ISO8601", "next_review": "ISO8601",
  "status": "new|learning|review|relearning|mastered" }
```

`GET /changes?since={cursor}` →
```json
{ "cursor": "…", 
  "articles": { "updated": [ …objets complets… ], "authorized_ids": [1, 2] },
  "quizzes": { "updated": [], "authorized_ids": [176, 183] },
  "decks":   { "updated": [], "authorized_ids": [2] },
  "exams":   { "updated": [], "authorized_ids": [5] },
  "badges": [ …liste complète… ],
  "xp": { …snapshot… } }
```

`GET /leaderboard` → classement XP par groupe (en ligne ; mettre en cache Room
pour affichage hors-ligne avec mention « dernière synchronisation ») :
```json
{ "groups": [ { "group_id": 224, "group_name": "…", "total_participants": 8, "my_rank": 2,
                "rows": [ { "rank": 1, "name": "…", "total_xp": 500, "current_level": 6,
                            "current_streak": 4, "is_me": false } ] } ] }
```

### 3.3 Actions (outbox) — `POST /actions`

```json
{ "actions": [ { "id": "uuid-v4-client", "type": "…", "data": { } } ] }
```
→ 200 :
```json
{ "success": true,
  "results": [ { "id": "uuid", "status": "applied|duplicate|rejected",
                 "result": { }, "message": "si rejected" } ],
  "xp": { …snapshot à jour… },
  "badges_unlocked": ["Perfectionniste"] }
```
Types d'action et `data` :
- `article_progress` : `{article_id, progress_percentage (0-100), status: "reading"|"completed"}` — +15 XP au premier passage à completed
- `article_favorite` : `{article_id, is_favorite}`
- `article_rate` : `{article_id, rating (1-5)}`
- `article_error_report` : `{content_id, error_type: "content"|"spelling"|"technical", comment}`
- `quiz_attempt` : `{quiz_id, answers: {questionId: réponse}, started_at ISO, completed_at ISO}` — le serveur re-note ; XP = 20 + 30 si réussi + 5×points
- `quiz_error_report` : `{content_id, error_type, comment}`
- `card_review` : `{card_id, quality (0|3|4|5)}` — +5 XP ; le serveur recalcule le SM-2 (même formule que §6)
- `review_session` : `{deck_id, date_debut, date_fin, duree_seconds, cartes_etudiees, cartes_nouvelles, cartes_revues, cartes_maitrisees, grades: [int]}`
- `preferences_update` : `{theme?, font_size?, sound_enabled?, locale?}`

`badges_unlocked` non vide → dialogue de célébration + son.

### 3.4 Examens (ONLINE UNIQUEMENT)

- `POST /exams/{id}/attempts` → `{attempt_id, questions[] (réponses correctes retirées, matching/ordering mélangés), elapsed_seconds, remaining_seconds, plein_ecran_force, anti_capture_strict, navigation_interdite}` ; `403` si fenêtre fermée ou tentatives épuisées.
- `PATCH /exams/{id}/attempts/{attemptId}` `{answers}` : autosave (déclencher ~1,5 s après chaque changement).
- `POST /exams/{id}/attempts/{attemptId}/complete` `{answers}` → `{score_brut, score_total, pourcentage, note_sur_vingt, passed, rank, total_participants}`.
- `POST /exams/{id}/attempts/{attemptId}/violations` `{type: "screenshot"|"navigation"}` → `{cancelled: bool, violations_count}` ; si `cancelled` : dialogue « Examen annulé » et retour à la liste.

Formats de réponses examen (différents du quiz !) : `true_false` → `"true"|"false"` ;
`mcq` → `["texte choisi", …]` ; `fill_blank` → `"texte"` ; `matching` →
`{"GaucheA": "DroiteChoisie", …}` ; `ordering` → `["élément1", "élément2", …]` ;
`open_text` → `"texte"`.

## 4. Écrans (Material 3, français, bottom navigation 5 onglets)

1. **Connexion** — email/identifiant + mot de passe, gestion 401/403/429 (avec compte à rebours), `device_name` = modèle de l'appareil.
2. **Accueil** — carte héro XP/niveau/série (barre de progression : `niveau = total_xp/100 + 1`, progression = `total_xp % 100`), raccourcis (quiz à faire, articles à lire, cartes dues, examens ouverts), badges, bouton « Ma progression ».
3. **Articles** — recherche + filtres (Tous/À lire/Lus/Favoris) ; lecture : rendu du HTML riche, progression au scroll (action `article_progress` par paliers), « Marquer comme lu (+15 XP) », favori, note étoiles, signalement.
4. **Entraînement** — segments **Quiz** / **Flashcards** :
   - Quiz : recherche + filtres de statut ; fiche quiz (stats, types de questions, historique) ; **lecteur de quiz** (voir §5) ; carte « Rejouer mes erreurs » (questions ratées stockées localement, mode sans XP).
   - Flashcards : liste des decks (cartes dues = `next_review` null ou ≤ maintenant) ; **session de révision** : carte retournable (animation flip), boutons « À revoir (0) / Difficile (3) / Moyen (4) / Facile (5) », SM-2 calculé localement (§6), carte ratée re-présentée en fin de session, écran récapitulatif.
5. **Examens** — bandeau d'avertissement solennel, sections « À passer »/« Historique », chips de sécurité ; passage voir §7.
6. **Profil** — identité, stats, badges (grisés si verrouillés), préférences (thème, taille de texte, sons), changement de mot de passe (dialogue, erreurs 422 par champ), état de sync, déconnexion (avertir si actions en attente).
7. **Ma progression** — stats, heatmap d'activité 12 semaines, meilleurs scores, classement (cache hors-ligne).

## 5. Notation des quiz CÔTÉ CLIENT (résultat immédiat hors-ligne)

Le serveur re-note à la sync avec exactement ces règles — implémente-les à
l'identique (types et `options` de la question) :
- `true_false` : `options.correct_answer` ("true"/"false") — tout ou rien.
- `mcq` (une réponse, ou `options.multiple=true`) : `options.answers[] = {text, is_correct}`.
  Multiple strict : points si sélection == ensemble exact des bonnes réponses.
  Multiple avec `options.partial_score=true` : `points_gagnés = round(bonnes_cochées / total_bonnes × points)` mais **0 si la moindre mauvaise est cochée** ; correct seulement si tout.
- `fill_blank` : `options.blanks[] = {answers: [..], case_sensitive}` — proportionnel par trou (round), casse selon le flag.
- `matching` : `options.pairs[] = {term, definition}` — réponse `{terms: [...], definitions: [...]}` alignées par index, proportionnel par paire.
- `ordering` : `options.items[]` — proportionnel **position par position**.
- `open_text` : points complets si réponse non vide.
Score % = `round(points/total × 100, 2)` ; réussi si ≥ `passing_score`.
XP local optimiste : `20 + (réussi ? 30 : 0) + points × 5`.

**Vecteurs de test** (JUnit) : mcq partiel 2/3 bonnes cochées, 0 erreur, 4 pts → 3 ;
ordering 1 position juste sur 4, 4 pts → 1 ; fill_blank "paris" vs ["Paris"]
insensible → juste, "css" vs ["CSS"] sensible → faux.

## 6. Algorithme SM-2 CÔTÉ CLIENT (identique au serveur)

```
q ∈ {0, 3, 4, 5} ; état initial : EF = easiness_default du deck, repetitions = 0, interval = 0
si q < 3 : repetitions = 0 ; interval = 1
sinon    : interval = (repetitions == 0) ? 1 : (repetitions == 1) ? 6 : round(interval × EF)
           repetitions += 1
EF = EF + (0.1 − (5 − q) × (0.08 + (5 − q) × 0.02)) ; EF = max(EF, 1.3) ; arrondir EF à 2 décimales
interval = clamp(interval, deck.interval_min, deck.interval_max)
next_review = maintenant + interval jours
status : q<3 → "relearning" ; repetitions≥5 → "mastered" ; ≥3 → "review" ; sinon "learning"
```
**Vecteurs de test** : (EF 2.5, rep 0, int 0, q5) → int 1, rep 1, EF 2.6 ;
(2.6, 1, 1, q5) → int 6 ; (2.6, 2, 6, q4) → int 16, EF 2.6 ;
(2.6, 4, 30, q1) → int 1, rep 0, EF 2.06 ; EF plancher 1.3.
Après révision : mise à jour Room immédiate + action `card_review` dans l'outbox
(le serveur recalcule et fait foi).

## 7. Examens sur mobile (sécurité renforcée)

- Vérifier la connectivité avant de démarrer ; écran « Connexion requise » sinon.
- Écran de consignes (règles actives selon les flags) → bouton « J'ai compris ».
- **`WindowManager.LayoutParams.FLAG_SECURE`** sur l'Activity pendant l'épreuve :
  bloque réellement captures et enregistrements d'écran (supérieur au web).
  Si `anti_capture_strict`, signaler quand même toute tentative détectable.
- **Cycle de vie** : si `navigation_interdite`, chaque passage `onPause`/arrière-plan
  → `POST …/violations {type: "navigation"}` ; afficher « Sortie détectée (n/3) » ;
  si `cancelled` → dialogue et sortie.
- Timer visible (mm:ss, rouge < 2 min), soumission automatique à 0.
- Keep screen on (`FLAG_KEEP_SCREEN_ON`) pendant l'épreuve.
- Autosave PATCH (debounce 1,5 s) ; résultat : note/20, %, rang « Xᵉ sur N ».

## 8. Sécurité de l'application (OBLIGATOIRE)

- Token **uniquement** dans DataStore chiffré / EncryptedSharedPreferences — jamais en clair, jamais loggué.
- Interceptor OkHttp : ajoute `Authorization` ; sur **401** → purge token + Room, navigation vers Connexion (event global).
- HTTPS uniquement ; `usesCleartextTraffic=false`.
- Ne jamais logguer les corps de requêtes d'authentification.
- Gérer 422 (`errors` par champ), 429 (`Retry-After`), 5xx (réessai avec backoff via WorkManager pour l'outbox, message pour l'interactif).
- ProGuard/R8 activé en release ; pas de secrets dans le code (BASE_URL en `BuildConfig`).

## 9. Livrables attendus

Projet Android Studio complet et compilable : structure Gradle (version
catalogs), tous les écrans ci-dessus, thème Material 3 (clair/sombre, couleur
seed bleu ciel #0284C7), tests unitaires du moteur SM-2 et de la notation quiz
avec les vecteurs fournis, README d'installation (où mettre BASE_URL, comment
créer un compte de test). Code commenté en français.
