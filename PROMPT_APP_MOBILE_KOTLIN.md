# Prompt AI Studio — Application Android native « Learn&Quiz »

> Copiez tout ce document dans https://aistudio.google.com/apps (ou dans Gemini)
> pour générer l'application. Remplacez `{{BASE_URL}}` par l'URL réelle de votre
> API (ex. `https://5b91-102-67-126-233.ngrok-free.app` — attention, l'URL ngrok
> gratuite change à chaque relance).

---

Tu es un développeur Android senior. Développe une application Android native
complète en **Kotlin (dernière version stable) avec Jetpack Compose et
Material 3**, nommée **Learn&Quiz**, application d'apprentissage pour des
apprenants francophones. Elle doit être **iso-fonctionnelle avec la PWA web
existante** dont les spécifications détaillées sont au §4 — chaque règle métier,
chaque état d'écran et chaque interaction décrits ci-dessous doivent exister
dans l'app. L'application est **offline-first** : elle fonctionne intégralement
sans réseau après une première synchronisation et rejoue les actions en attente
au retour de la connexion. Toute l'interface est en **français**.

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
2. **Delta sync** (retour d'app au premier plan / retour réseau / pull-to-refresh) :
   `GET /api/mobile/v1/changes?since={cursor}` → pour chaque collection,
   `updated[]` est upserté dans Room et **tout id absent de `authorized_ids[]`
   est supprimé** (contenu désassigné). Nouveau cursor stocké. Après un delta,
   les écrans « liste » se rafraîchissent automatiquement (Flow Room) — mais
   jamais une activité en cours (quiz, examen, session de révision).
3. **Outbox** : chaque action locale est écrite dans une table Room `outbox`
   avec un **UUID client unique** (`id`), puis rejouée vers
   `POST /api/mobile/v1/actions` dès que possible (WorkManager). `applied` et
   `duplicate` → retirée de l'outbox ; `rejected` → retirée et snackbar
   d'explication. Le serveur est **idempotent** grâce à l'UUID : rejouer ne
   double jamais l'XP.
4. **Optimistic UI** : les écritures modifient Room immédiatement (XP local,
   statut du quiz, état SM-2) ; le serveur reste l'autorité, le delta réconcilie.
5. Bandeau ambre « Mode hors-ligne — vos actions seront synchronisées au retour
   du réseau » quand pas de connectivité ; indicateur permanent dans la top bar :
   point vert « Synchronisé » / animation « Synchronisation… » / point ambre
   « Hors-ligne », avec pastille du nombre d'actions en attente.
6. **Exception** : les EXAMENS sont online-only (intégrité) — §4.7.

## 3. Contrat API (existant — ne pas inventer d'autres endpoints)

Base : `{{BASE_URL}}/api/mobile/v1` — JSON (`Accept: application/json`).
Routes protégées : header `Authorization: Bearer {token}`.

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
`429` + header `Retry-After` après 5 échecs/minute (afficher le compte à rebours).
Token expiré/révoqué → toute réponse `401` sur une route protégée purge la
session locale (token + Room) et renvoie à l'écran de connexion.

`GET /me` → profil + XP à jour.
`PUT /password` `{current_password, password, password_confirmation}` →
`422` avec `errors.{champ}[]` à afficher sous chaque champ ; succès = les autres
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
                            "tags": null, "ordre": 1, "review": null } ] } ],
  "exams": [ { "id": 5, "title": "…", "duration": 20, "passing_score": 50, "note_max": 20,
               "max_attempts": 10, "available_from": null, "available_until": null,
               "plein_ecran_force": false, "anti_capture_strict": false,
               "navigation_interdite": false, "publication_resultats": "immediate",
               "classement_visible": true, "classement_anonyme": false,
               "status": "locked|available|expired|in_progress|completed",
               "max_attempts_reached": false, "updated_at": "ISO8601", "attempts": [ ] } ],
  "badges": [ { "id": 1, "name": "Premier pas", "description": "…", "icon": "🚀", "unlocked": true } ],
  "preferences": { "locale": "fr", "theme": "light|dark", "font_size": "small|medium|large",
                   "sound_enabled": true, "notifications_enabled": { } } }
```
`review` d'une carte (état SM-2 par apprenant, `null` si jamais révisée) :
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

`GET /leaderboard` (en ligne ; mettre en cache Room pour l'affichage hors-ligne
avec la mention « Classement issu de la dernière synchronisation ») :
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
- `article_progress` : `{article_id, progress_percentage (0-100), status: "reading"|"completed"}`
- `article_favorite` : `{article_id, is_favorite}`
- `article_rate` : `{article_id, rating (1-5)}`
- `article_error_report` : `{content_id, error_type: "content"|"spelling"|"technical", comment}`
- `quiz_attempt` : `{quiz_id, answers: {questionId: réponse}, started_at ISO, completed_at ISO}`
- `quiz_error_report` : `{content_id, error_type, comment}`
- `card_review` : `{card_id, quality (0|3|4|5)}`
- `review_session` : `{deck_id, date_debut, date_fin, duree_seconds, cartes_etudiees, cartes_nouvelles, cartes_revues, cartes_maitrisees, grades: [int]}`
- `preferences_update` : `{theme?, font_size?, sound_enabled?, locale?}`

`badges_unlocked` non vide → dialogue de célébration 🏅 + carillon.

### 3.4 Examens (ONLINE UNIQUEMENT)

- `POST /exams/{id}/attempts` → `{attempt_id, questions[] (réponses correctes retirées, matching/ordering mélangés), elapsed_seconds, remaining_seconds, plein_ecran_force, anti_capture_strict, navigation_interdite}` ; `403` avec message si fenêtre fermée / pas encore ouverte / tentatives épuisées.
- `PATCH /exams/{id}/attempts/{attemptId}` `{answers}` : autosave (debounce ~1,5 s).
- `POST /exams/{id}/attempts/{attemptId}/complete` `{answers}` → `{score_brut, score_total, pourcentage, note_sur_vingt, passed, rank, total_participants}`.
- `POST /exams/{id}/attempts/{attemptId}/violations` `{type: "screenshot"|"navigation"}` → `{cancelled: bool, violations_count, message?}`.

Formats de réponses EXAMEN (différents du quiz !) : `true_false` → `"true"|"false"` ;
`mcq` → `["texte choisi", …]` ; `fill_blank` → `"texte"` ; `matching` →
`{"GaucheA": "DroiteChoisie", …}` ; `ordering` → `["élément1", …]` ; `open_text` → `"texte"`.

---

## 4. SPÉCIFICATIONS FONCTIONNELLES DÉTAILLÉES (parité stricte avec la PWA)

Navigation : **bottom bar 5 onglets** — Accueil 🏠, Articles 📰, Entraînement ⚡,
Examens 🎓, Profil 👤. Pastilles bleues « à faire » sur les onglets :
Articles = articles non lus ; Entraînement = quiz à faire + cartes dues
(« 9+ » au-delà de 9, masquée à zéro).

### 4.0 Règles de gamification (utilisées partout)

| Événement | XP |
|---|---|
| Quiz complété | `20 + (réussi ? 30 : 0) + points_obtenus × 5` |
| Article lu (premier passage à completed) | +15 |
| Carte révisée (toute note) | +5 |
| Examen réussi | +50 |

- **Niveau** = `floor(total_xp / 100) + 1` ; progression du niveau = `total_xp % 100` sur 100.
- **Série (streak)** : gérée par le serveur (actif hier → +1 ; ni hier ni aujourd'hui → 1 ;
  déjà actif aujourd'hui → inchangé). Afficher 🔥 n jour(s), record entre parenthèses.
- **Badges** (10, fournis par l'API avec `unlocked`) : conditions quiz complétés
  (1/5/20), articles lus (1/5), quiz parfaits 100 % (1/5), séries (3/7/30 jours).
  Déblocage → dialogue de célébration + son ; l'app affiche ce que l'API renvoie,
  elle ne calcule pas les conditions.
- **Sons** (si préférence activée) : générés par le moteur audio (pas de fichiers) —
  « correct » 2 notes montantes, « faux » note basse feutrée, « réussite » arpège
  majeur, « badge » carillon 3 notes.

### 4.1 Connexion

Logo 🎓 + « Learn&Quiz — Espace apprenant », champ « Email ou identifiant »
(autodétection), mot de passe, bouton avec état de chargement (« Connexion… »).
Erreurs inline : 401 « Identifiants invalides. », 403 « Ce compte n'est pas
configuré comme un compte apprenant. », 429 avec compte à rebours, réseau
« Impossible de joindre le serveur. Vérifiez votre connexion. ».
Après login : bootstrap complet avec indicateur, puis Accueil.
Au démarrage suivant : si un token existe → ouvrir directement l'app depuis
Room (même hors-ligne) puis delta sync en arrière-plan.

### 4.2 Accueil (tableau de bord)

- **Carte héro** (dégradé bleu → indigo, coins très arrondis) : « Bonjour, {prénom} 👋 »,
  gros total XP, « Niveau n », barre de progression `total%100`/100 avec libellés,
  chips 🔥 série et 🏅 nombre de badges, bouton « Ma progression » (§4.8).
- **4 tuiles raccourcis** (avec compteur) : Quiz à faire (statut ≠ completed et
  tentatives non épuisées) → Entraînement ; Articles à lire (≠ completed) →
  Articles ; Cartes à réviser (review null ou `next_review` ≤ maintenant) →
  Entraînement/Flashcards ; Examens ouverts (available|in_progress) → Examens.
- **« Continuer »** : jusqu'à 3 quiz à faire (icône play si en cours, badge
  « En cours » ambre / « Nouveau » bleu).
- **« Mes badges »** : chips des badges débloqués ; état vide : « Aucun badge
  pour l'instant — complétez un quiz ou lisez un article pour débloquer le premier ! »

### 4.3 Articles

- **Liste** : barre de recherche (titre + catégorie) ; chips filtres
  Tous / À lire / Lus / Favoris ♥ ; « Aucun article ne correspond. » si vide.
  Carte article : tag catégorie, badge d'état (« Nouveau » bleu / « x % » ambre /
  « ✓ Lu » émeraude), cœur si favori, temps de lecture, date, étoiles si noté,
  mini barre de progression si entamé.
- **Lecture** : titre, temps de lecture, boutons favori (cœur, toggle immédiat +
  action `article_favorite`) et signalement (dialogue de confirmation → action
  `article_error_report` type "content" + toast « Signalement envoyé. »).
  **Barre de progression de lecture** collée sous la barre d'app, liée au scroll,
  monotone croissante ; envoyer `article_progress` par paliers (throttle ~3 s,
  status "reading"). Rendu du **contenu HTML riche** (titres, listes, images
  redimensionnées, audio) — sur Android, WebView embarquée ou rendu HTML Compose.
  Bouton « **Marquer comme lu (+15 XP)** » → status completed, +15 XP optimiste,
  toast « +15 XP — article terminé ! », remplacé par « ✓ Article lu ».
  **Notation 1–5 étoiles** → action `article_rate` + toast « Merci pour votre note ! ».

### 4.4 Entraînement (segments Quiz | Flashcards)

Sous-titre : « Entraînez-vous sans pression : les quiz sont rejouables et les
cartes suivent votre mémoire. » Segment Flashcards avec pastille violette du
nombre total de cartes dues.

**Onglet Quiz**
- Recherche par titre + chips Tous / Nouveaux / En cours / Terminés.
- **Carte « Rejouer mes erreurs »** (bordure pointillée violette) si des erreurs
  existent : « n question(s) ratée(s) à retravailler — entraînement libre, sans
  XP. » → lance le mode erreurs (§4.5-bis).
- Carte quiz : icône, titre, « n questions · durée min · réussite ≥ x % »,
  « Meilleur score : x % » (vert si réussi), badge d'état.

**Fiche quiz** : titre, description, 4 tuiles (questions / points totaux /
minutes ou ∞ / % pour réussir), **répartition par type** avec icône + libellé
(Vrai/Faux, Choix unique, Choix multiples, Texte à trous, Associations, Remise
en ordre, Réponse libre), CTA « Commencer le quiz » / « Reprendre le quiz » +
« n tentatives restantes », ou état « Nombre maximum de tentatives atteint. » ;
**historique des tentatives** (les plus récentes en premier : pastille ✓/✗,
« Tentative n — x % », points, date/heure, Réussi/Échoué).

### 4.5 Lecteur de quiz (fonctionne 100 % hors-ligne)

- **Reprise de session** : un brouillon (réponses + ordre des questions + index +
  chrono) est sauvegardé dans Room à chaque réponse et toutes les 10 s de chrono.
  À l'ouverture, si un brouillon existe : dialogue « Reprendre votre session ? —
  Une session interrompue de ce quiz a été retrouvée sur cet appareil. »
  (Reprendre → tout restaurer ; refus → supprimer le brouillon.)
- En-tête : « Quitter » (dialogue : « Votre progression est enregistrée sur cet
  appareil : vous pourrez reprendre plus tard. »), barre de progression
  (question courante / total), **chrono décompte** si le quiz a une durée
  (mm:ss, rouge ≤ 60 s, soumission automatique à 0).
- Carte question : « Question n / total », points, **énoncé HTML riche**.
- Types (mêmes comportements que la PWA) :
  - `true_false` : deux grands boutons Vrai / Faux.
  - `mcq` simple : liste de boutons radio-like ; multiple (`options.multiple`
    ou type multiple_choice) : cases à cocher + mention « Plusieurs réponses possibles ».
  - `fill_blank` : un champ par trou, étiquetés « Trou 1… n ».
  - `matching` : pour chaque terme, un menu déroulant des définitions
    **mélangées une fois par question** (ordre stable pendant la session).
    Réponse envoyée : `{terms: [...], definitions: [...]}` alignés par index.
  - `ordering` : liste **mélangée** à la première présentation, réordonnable par
    **glisser-déposer (poignée ≡, drag tactile natif Compose)** ET par flèches
    ↑/↓ (accessibilité) ; numéros mis à jour en direct.
  - `open_text` : zone de texte multiligne.
- Navigation Précédent / Suivant / Terminer + « n/total répondues ».
- Si `shuffle_questions` : mélanger l'ordre des questions (conservé dans le brouillon).

**Écran de résultat** : emoji 🎉/😕, « Quiz réussi ! » / « Quiz terminé »,
gros pourcentage (vert/rouge), « points / total · seuil de réussite x % »,
« +n XP » ; **delta vs meilleur score précédent** (« ▲ +x pts », « ▼ −x pts
vs votre meilleur score (y %) », « = égal ») ; **mini-graphique en barres** des
7 dernières tentatives + la courante (courante en bleu) ; si
`show_correct_answers` : accordéon « Voir le détail des réponses » (✓/✗ par
question + points) ; boutons **Partager** (share sheet Android : « J'ai obtenu
x % au quiz “…” sur Learn&Quiz ! 🎓 (+n XP) », seulement si réussi), Détails,
Retour à l'entraînement. Sons réussite/échec. À la fin : action `quiz_attempt`
dans l'outbox + mise à jour optimiste (statut, tentative locale, XP) +
**enregistrement des erreurs** : chaque question ratée entre dans la table
Room `mistakes` (question_id, quiz_id, date) ; chaque question réussie en sort.

**§4.5-bis Mode « Rejouer mes erreurs »** : quiz synthétique construit à partir
des questions de `mistakes` (mélangées), bannière violette « Entraînement libre
sur vos erreurs — sans XP ni enregistrement. » Rien n'est envoyé au serveur,
aucun XP ; le résultat affiche « Entraînement terminé » + bouton « Recommencer ».
Les questions réussies sortent de `mistakes`. État vide : « Aucune erreur à
rejouer — les questions ratées lors de vos quiz apparaîtront ici. »

### 4.6 Flashcards (dans Entraînement)

- **Liste des decks** : icône pile violette, titre, matière, « n carte(s) ·
  m maîtrisée(s) », barre de progression de maîtrise, pastille violette du
  nombre de cartes dues (ou ✓ vert si aucune), CTA « Réviser n cartes » /
  « Réviser en avance » (grisé-neutre).
- **Session de révision** : file = cartes dues (review null ou next_review ≤
  maintenant) ; si aucune due, tout le deck (révision en avance). En-tête :
  « Arrêter », progression x/y. **Carte retournable avec animation de flip 3D** :
  face Question (accent violet, « Touchez pour retourner »), face Réponse
  (accent émeraude) — contenu recto/verso en HTML riche. Après le flip, révéler
  les 4 boutons : **À revoir (0, rouge) / Difficile (3, ambre) / Moyen (4, bleu)
  / Facile (5, émeraude)**.
- À chaque évaluation : calcul SM-2 **local** (§6), mise à jour Room immédiate
  (review de la carte), action `card_review` dans l'outbox, +5 XP optimiste,
  son correct (q≥3) / faux (q<3). **Une carte notée 0 est re-présentée en fin
  de session.**
- **Écran de fin** : 🧠 « Session terminée ! », tuiles (révisées / maîtrisées /
  +XP), boutons Recommencer / Mes decks ; envoyer l'action `review_session`
  avec les statistiques réelles (durée, nouvelles vs revues, notes).

### 4.7 Examens (online-only, sécurité renforcée)

- **Liste** : bandeau d'avertissement ambre (« Les examens sont des épreuves
  officielles surveillées : connexion requise, tentatives limitées, et toute
  infraction aux règles peut entraîner l'annulation. »), sections « À passer »
  et « Historique & à venir ». Carte : liseré dégradé ambre, statut pill
  (Disponible vert / En cours ambre / Terminé bleu / Verrouillé / Expiré),
  « durée min · note /20 · n tentative(s) », date de fermeture, chips sécurité
  (🛡 Surveillé, Plein écran, Anti-capture, Navigation bloquée), dernière note
  colorée (vert/rouge selon seuil), CTA « Commencer/Reprendre l'examen ».
- **Hors-ligne** : écran « Connexion requise — les examens surveillés ne peuvent
  être passés qu'en ligne. »
- **Consignes** avant démarrage : règles actives selon les flags + durée +
  « soumission automatique à la fin du temps » → « J'ai compris, commencer ».
- **Pendant l'épreuve** :
  - **`FLAG_SECURE`** sur la fenêtre : bloque réellement captures et
    enregistrements d'écran. Si `anti_capture_strict`, signaler toute tentative
    détectable via `…/violations {type:"screenshot"}`.
  - Si `navigation_interdite` : chaque passage en arrière-plan (`onPause`) →
    `…/violations {type:"navigation"}` ; toast « ⚠️ Sortie détectée (n/3) » ;
    si `cancelled` → dialogue « Examen annulé » + retour à la liste.
  - `FLAG_KEEP_SCREEN_ON` ; chrono visible (rouge ≤ 2 min), soumission auto à 0 ;
    autosave PATCH avec debounce 1,5 s ; mêmes UIs de types qu'au §4.5 mais
    formats de réponses du §3.4 ; badge « 🛡 Surveillé » dans l'en-tête.
- **Résultat** : 🎓/📋, note **n/note_max** en grand, points bruts/total, %,
  « +50 XP » si réussi, **classement** « Xᵉ sur N participant(s) » si
  `classement_visible`.

### 4.8 Ma progression

- Résumé : 4 tuiles (XP total, Niveau, 🔥 Série avec « max n », % réussite quiz)
  + barre de progression du niveau.
- **Heatmap d'activité 12 semaines** (grille 7×12, intensité 0/1/2/3+ tentatives
  par jour, calculée depuis les tentatives locales) + « n quiz complété(s) ·
  m/k cartes maîtrisées ».
- **Meilleurs scores** : barre par quiz (émeraude si 100 %).
- **Classement** (🏆) par groupe : médailles 🥇🥈🥉 pour le top 3, avatar à
  initiales, série 🔥, XP ; **ligne « (vous) » surlignée** ; « Vous êtes Xᵉ / N ».
  En ligne : fetch + mise en cache Room ; hors-ligne : cache avec mention
  « Classement issu de la dernière synchronisation », sinon message dédié.

### 4.9 Profil

- Identité : **avatar à initiales sur dégradé déterministe** (hash du nom → une
  des 6 paires de couleurs ; photo seulement si URL même origine — jamais de
  service externe), nom, email, matricule.
- Stats (3 tuiles) + barre de niveau.
- **Grille des badges** : débloqués (fond ambre) / verrouillés (grisés, opacité
  réduite), icône + nom + description.
- **Préférences** (persistées localement + action `preferences_update`) :
  thème Clair/Sombre (segments), taille du texte A-/A/A+ (échelle typographique
  globale), interrupteur Sons.
- **Sécurité** : « Changer mon mot de passe » (en ligne uniquement — message si
  hors-ligne) → dialogue 3 champs, erreurs 422 par champ, toast de succès,
  info « Les autres appareils ont été déconnectés ».
- **Synchronisation** : « Toutes vos données sont synchronisées. » ou « n
  action(s) en attente », heure de dernière sync, bouton « Synchroniser
  maintenant ».
- **Déconnexion** (rouge) : dialogue de confirmation — avertir si des actions
  sont en attente (« n action(s) non synchronisée(s) seront perdues ») ;
  tenter une sync puis `POST /logout`, purger token + Room.

---

## 5. Notation des quiz CÔTÉ CLIENT (résultat immédiat hors-ligne)

Le serveur re-note à la sync avec exactement ces règles — implémente-les à
l'identique (selon `type` et `options` de la question) :
- `true_false` : `options.correct_answer` ("true"/"false") — tout ou rien.
- `mcq` (simple, ou `options.multiple=true`, ou type `multiple_choice`) :
  `options.answers[] = {text, is_correct}`.
  Multiple strict : points si sélection == ensemble exact des bonnes réponses.
  Multiple avec `options.partial_score=true` : `points = round(bonnes_cochées /
  total_bonnes × points)` mais **0 si la moindre mauvaise est cochée** ;
  « correct » seulement si tout.
- `fill_blank` : `options.blanks[] = {answers: [...], case_sensitive}` —
  proportionnel par trou (round), casse selon le flag.
- `matching` : `options.pairs[] = {term, definition}` — réponse
  `{terms: [...], definitions: [...]}` par index, proportionnel par paire.
- `ordering` : `options.items[]` — proportionnel **position par position**.
- `open_text` : points complets si réponse non vide.
Score % = `round(points/total × 100, 2)` ; réussi si ≥ `passing_score`.
XP optimiste : `20 + (réussi ? 30 : 0) + points × 5`.

**Vecteurs de test JUnit obligatoires** : mcq partiel 4 pts, 2/3 bonnes cochées,
0 erreur → 3 pts ; même question avec 1 mauvaise cochée → 0 ; ordering 4 pts,
1 position juste sur 4 → 1 pt ; fill_blank "paris" vs ["Paris"] insensible →
juste ; "css" vs ["CSS"] sensible → faux.

## 6. Algorithme SM-2 CÔTÉ CLIENT (identique au serveur)

```
q ∈ {0, 3, 4, 5} ; état initial : EF = easiness_default du deck, repetitions = 0, interval = 0
si q < 3 : repetitions = 0 ; interval = 1
sinon    : interval = (repetitions == 0) ? 1 : (repetitions == 1) ? 6 : round(interval × EF)
           repetitions += 1
EF = EF + (0.1 − (5 − q) × (0.08 + (5 − q) × 0.02)) ; EF = max(EF, 1.3) ; arrondi 2 décimales
interval = clamp(interval, deck.interval_min, deck.interval_max)
next_review = maintenant + interval jours
status : q<3 → "relearning" ; repetitions≥5 → "mastered" ; ≥3 → "review" ; sinon "learning"
```
**Vecteurs de test JUnit** : (EF 2.5, rep 0, int 0, q5) → int 1, rep 1, EF 2.6 ;
(2.6, 1, 1, q5) → int 6 ; (2.6, 2, 6, q4) → int 16, EF 2.6 ;
(2.6, 4, 30, q1) → int 1, rep 0, EF 2.06 ; EF ne descend jamais sous 1.3 ;
clamp sur interval_max du deck.

## 7. Sécurité de l'application (OBLIGATOIRE)

- Token **uniquement** dans DataStore chiffré / EncryptedSharedPreferences —
  jamais en clair, jamais loggué.
- Interceptor OkHttp : ajoute `Authorization` ; sur **401** → purge token + Room
  → navigation Connexion (événement global).
- HTTPS uniquement ; `usesCleartextTraffic=false`.
- Ne jamais logguer les corps des requêtes d'authentification.
- Gérer 422 (`errors` par champ), 429 (`Retry-After`), 5xx (backoff WorkManager
  pour l'outbox, message clair pour l'interactif).
- `FLAG_SECURE` pendant les examens ; ProGuard/R8 en release ; BASE_URL en
  `BuildConfig`, aucun secret dans le code.

## 8. Livrables attendus

Projet Android Studio complet et compilable : structure Gradle (version
catalogs), tous les écrans et règles du §4, thème Material 3 (clair/sombre,
couleur seed bleu ciel #0284C7, violet pour les flashcards, ambre pour les
examens), tests unitaires SM-2 + notation quiz avec les vecteurs fournis,
README d'installation (BASE_URL, compte de test). Code commenté en français.
