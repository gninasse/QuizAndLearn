# Spécifications fonctionnelles — Volet Apprenant Learn&Quiz

> Spécification complète et détaillée de tous les écrans du volet apprenant
> (PWA), telle qu'implémentée. Sert de référence produit, de base de recette
> et de contrat de parité pour l'application mobile.
>
> **Stack** : SPA TypeScript / Web Components natifs, Tailwind CSS v4,
> IndexedDB (Dexie), Service Worker (Workbox), API Laravel `/api/learner/v1`
> (session) et `/api/mobile/v1` (Bearer). Interface 100 % en français.

---

## 0. Fondations transverses (s'appliquent à tous les écrans)

### 0.1 Coquille applicative (shell)

| Zone | Desktop (≥ 1024 px) | Mobile |
|---|---|---|
| Navigation | Sidebar gauche fixe (marque 🎓 Learn&Quiz, 5 entrées, bloc profil en bas : avatar, nom, « Niveau n · x XP ») | Tab bar inférieure fixe, 5 onglets (icône + libellé), safe-area respectée |
| Header | Titre de l'écran courant, indicateur de sync, bascule de thème | + marque 🎓 à gauche, + avatar cliquable (→ Profil) à droite |

- **Entrées de navigation** : Accueil `/`, Articles `/articles`, Entraînement `/entrainement`, Examens `/examens`, Profil `/profil`. Entrée active surlignée (fond bleu clair + texte bleu) ; la détection d'activation englobe les sous-routes (ex. `/quizzes/*` active « Entraînement »).
- **Pastilles « à faire »** sur Articles (articles non lus) et Entraînement (quiz à faire + cartes dues) : bleu, « 9+ » au-delà de 9, masquées à zéro, mises à jour en direct sans reconstruire le shell.
- **Indicateur de synchronisation** (header) : point vert « Synchronisé » / halo animé « Synchronisation… » / point ambre « Hors-ligne » ; pastille ambre = nombre d'actions en attente d'envoi.
- **Bannière hors-ligne** (sticky top, ambre) : « Mode hors-ligne — vos actions seront synchronisées au retour du réseau ».
- Le shell ne se reconstruit **que** sur bascule connecté/déconnecté ; les mises à jour (sync, XP, pastilles) sont patchées en place pour ne jamais détruire l'écran courant.

### 0.2 Modèle offline-first

1. **Amorçage** : `GET /bootstrap` → tout le contenu en IndexedDB + `cursor`.
2. **Delta** : `GET /changes?since=cursor` à chaque retour réseau / premier plan / action — `updated[]` upserté, tout id hors `authorized_ids[]` purgé ; **si un id autorisé est absent localement → re-bootstrap complet automatique** (cas groupe réactivé).
3. **Outbox** : toute écriture = action locale avec UUID, rejouée vers `POST /actions` (idempotent serveur) ; `applied`/`duplicate` → retirée, `rejected` → retirée + toast d'explication.
4. **Optimistic UI** : XP, statuts et états SM-2 mis à jour localement immédiatement, réconciliés par le serveur.
5. Après une sync, les écrans « liste » (`/`, `/articles`, `/entrainement`, `/examens`, `/profil`) se rafraîchissent seuls ; **jamais** un quiz/examen/session de révision en cours.
6. **Médias** : URLs `/storage/...` relatives (normalisées côté serveur) ; images/audio pré-téléchargés dans le cache du Service Worker (CacheFirst borné 200 entrées / 30 jours) — un article jamais ouvert s'affiche complet hors-ligne.
7. Examens = online-only (seule exception).

### 0.3 Gamification (règles globales)

| Événement | XP |
|---|---|
| Quiz complété | 20 + (30 si réussi) + 5 × points obtenus |
| Article lu (1ᵉʳ passage ≥ completed) | +15 |
| Carte révisée (toute note) | +5 |
| Examen réussi | +50 |

- Niveau = `⌊XP totale / 100⌋ + 1` ; progression du niveau = `XP % 100` / 100.
- Série 🔥 (serveur) : actif hier → +1 ; ni hier ni aujourd'hui → 1 ; déjà actif aujourd'hui → inchangée ; record conservé. Les articles n'alimentent pas la série ; quiz, examens et cartes oui.
- 10 badges (quiz complétés 1/5/20, articles lus 1/5, sans-faute 1/5, séries 3/7/30 j). Déblocage → **dialogue de célébration** 🏅 (nom du badge, bouton « Super ! ») + carillon.
- **Sons** (préférence « Sons », WebAudio local) : correct = 2 notes montantes ; faux = note basse ; réussite = arpège ; badge = carillon.

### 0.4 Thème, accessibilité, cycle de vie des groupes

- Thème clair/sombre (préférence + bascule header, anti-flash au chargement), taille de texte S/M/L appliquée globalement.
- Focus clavier visible partout (`:focus-visible` bleu), ARIA sur filtres/onglets/graphiques, cibles tactiles ≥ 44 px, `prefers-reduced-motion` respecté (transitions et shimmer désactivés).
- **Groupes** : seuls les groupes « en cours » (actifs + fenêtre de dates) délivrent du contenu. Statuts `active / upcoming / suspended / closed` exposés à l'app ; contenu purgé automatiquement, historique conservé, bannières d'explication sur l'Accueil (§2).
- Au chargement initial : **squelette** (shimmer) reproduisant la structure du tableau de bord.
- **Installation PWA** : bannière discrète ~4 s après connexion (« Installer Learn&Quiz — accès depuis l'écran d'accueil, plein écran, et tout fonctionne hors-ligne », boutons Installer / Plus tard, refus mémorisé) ; entrée permanente Profil → Application ; guide dédié iOS (Partager → Sur l'écran d'accueil) ; toast « Application mise à jour ✨ » après déploiement d'une nouvelle version.

---

## 1. Connexion — `/connexion`

**Objectif** : authentifier l'apprenant ; point d'entrée public unique.

**Composition** : fond dégradé nuit (bleu profond), logo 🎓, « Learn&Quiz », sous-titre « Espace apprenant » ; carte formulaire translucide : champ « Email ou identifiant » (autodétection email vs `user_name`), champ mot de passe, bouton « Se connecter » (état chargement : « Connexion… », désactivé). Mention : « Application installable — fonctionne aussi hors-ligne après la première connexion. »

**Comportements**
- Succès → `storage.persist()` demandé, bootstrap complet, toast « Bonjour {prénom} ! », redirection Accueil.
- Erreurs inline : 401 « Identifiants invalides. » · 403 « Ce compte n'est pas configuré comme un compte apprenant. » · réseau « Impossible de joindre le serveur. Vérifiez votre connexion. » · (API mobile : 429 avec compte à rebours).
- Session locale existante (IndexedDB) → l'app s'ouvre directement, même hors-ligne ; ce n'est qu'à l'expiration serveur (401 sur une sync) que l'utilisateur est ramené ici (toast « Session expirée — reconnectez-vous. »).

---

## 2. Accueil (tableau de bord) — `/`

**Objectif** : vue d'ensemble motivationnelle + reprise rapide.

**Composition (ordre vertical)**
1. **Bannières de groupe** (si groupes non actifs) — ambre (fermé : icône drapeau ; suspendu : icône pause) ou bleu (à venir : sablier) :
   - « La formation “X” est terminée depuis le {date} — son contenu n'est plus disponible, votre historique est conservé. »
   - « Le groupe “X” est momentanément suspendu — son contenu réapparaîtra à sa réactivation. »
   - « La formation “X” ouvrira le {date} — son contenu apparaîtra à ce moment-là. »
2. **Carte héro** (dégradé bleu→indigo, coins 24 px, halo décoratif) : « Bonjour, {prénom} 👋 », XP totale en grand + « Niveau n », barre de progression avec libellés (« Niveau n » / « x / 100 XP »), chips « 🔥 n jour(s) » et « 🏅 n badge(s) », bouton blanc « 📈 Ma progression ».
3. **4 tuiles raccourcis** (compteur + libellé + icône teintée ; survol : élévation) :
   - Quiz à faire (statut ≠ terminé ET tentatives non épuisées) → `/entrainement`
   - Articles à lire (statut ≠ lu) → `/articles`
   - Cartes à réviser (jamais revue OU `next_review` ≤ maintenant) → `/entrainement?tab=cartes`
   - Examens ouverts (disponible ou en cours) → `/examens`
4. **« CONTINUER »** : jusqu'à 3 quiz à faire — icône ▶ si en cours, badge « En cours » (ambre) / « Nouveau » (bleu), sous-titre « n questions · durée min ».
5. **« MES BADGES »** : chips des badges débloqués (icône + nom, description en infobulle) ; état vide : « Aucun badge pour l'instant — complétez un quiz ou lisez un article pour débloquer le premier ! »

---

## 3. Articles — `/articles`

**Objectif** : bibliothèque de lecture avec recherche et suivi de progression.

**Composition** : champ de recherche (🔍 « Rechercher un article… », filtre titre + catégorie, instantané) ; chips filtres exclusifs **Tous / À lire / Lus / Favoris ♥** (actif = fond bleu) ; grille de cartes (2 colonnes ≥ 640 px).

**Carte article** : tag catégorie (bleu, majuscules) ; badge d'état — « Nouveau » (bleu) / « x % » (ambre, si entamé) / « ✓ Lu » (émeraude) ; ♥ rose si favori ; titre ; « 🕒 n min », date ; étoiles si noté ; mini-barre de progression si entamé non terminé.

**États vides** : aucun article assigné → 📚 « Aucun article disponible — les articles assignés à vos groupes apparaîtront ici. » ; filtre sans résultat → « Aucun article ne correspond. »

---

## 4. Lecture d'article — `/articles/{id}`

**Objectif** : lecture confortable, progression automatique, interactions.

**Composition** : lien retour « ← Articles » ; titre ; « 🕒 n min de lecture » ; boutons ronds **favori** (♥) et **signalement** (drapeau) ; **barre de progression de lecture** sticky sous le header ; **contenu HTML riche** (styles typographiques dédiés : titres, listes, liens, `img` responsive arrondies, `audio` pleine largeur) ; pied de page : zone « Marquer comme lu », notation étoiles.

**Comportements**
- **Progression** : liée au scroll, **monotone croissante** (jamais de recul), envoyée par paliers (throttle 3 s) via action `article_progress` (statut `reading`). Persistée localement immédiatement.
- **« Marquer comme lu (+15 XP) »** (bouton émeraude) : statut → lu, barre à 100 %, +15 XP optimiste, toast « +15 XP — article terminé ! », zone remplacée par « ✓ Article lu ». Une seule attribution d'XP par article (le serveur garantit l'unicité).
- **Favori** : bascule immédiate du cœur + action `article_favorite`.
- **Étoiles 1–5** : mise à jour visuelle immédiate + action `article_rate` + toast « Merci pour votre note ! ».
- **Signalement** : dialogue de confirmation (« Signaler un problème de contenu sur cet article aux formateurs ? » / bouton « Signaler ») → action `article_error_report` + toast « Signalement envoyé. ».
- Introuvable → « Article introuvable. »

---

## 5. Entraînement — `/entrainement` (segments Quiz | Flashcards)

**Objectif** : regrouper l'auto-apprentissage sans enjeu (quiz rejouables + révision espacée), distinct des examens.

Sous-titre permanent : « Entraînez-vous sans pression : les quiz sont rejouables et les cartes suivent votre mémoire. » Sélecteur segmenté **« Quiz (n) » / « Flashcards »** (pastille violette = total de cartes dues) ; segment persisté dans l'URL (`?tab=cartes`).

### 5.1 Segment Quiz

- Recherche par titre + chips **Tous / Nouveaux / En cours / Terminés**.
- **Carte « Rejouer mes erreurs »** (bordure pointillée violette, si ≥ 1 erreur enregistrée) : icône 🔁, « Rejouer mes erreurs », « n question(s) ratée(s) à retravailler — entraînement libre, sans XP. » → §7-bis.
- **Carte quiz** : icône ❓ bleue, titre, « n questions · durée min · réussite ≥ x % », « Meilleur score : x % » (émeraude si réussi), badge « Terminé ✓ » / « En cours ▶ » / « Nouveau ».
- États vides : « Aucun quiz assigné » / « Aucun quiz ne correspond. »

### 5.2 Segment Flashcards

- **Carte deck** : icône 🗂 violette, titre, matière, « n carte(s) · m maîtrisée(s) », **barre de progression de maîtrise**, pastille violette = cartes dues (✓ vert si zéro), CTA « **Réviser n cartes** » (violet) ou « Réviser en avance » (neutre).
- État vide : 🃏 « Aucun deck de révision — les decks assignés à vos groupes apparaîtront ici. »

---

## 6. Fiche quiz — `/quizzes/{id}`

**Objectif** : tout savoir avant de commencer.

**Composition** : retour « ← Entraînement » ; carte en-tête : titre, description, **4 tuiles** (questions / points totaux / minutes ou ∞ / % pour réussir), CTA « **Commencer le quiz** » ou « **Reprendre le quiz** » + « n tentative(s) restante(s) », ou état bloquant rouge « Nombre maximum de tentatives atteint. » ; section **« STRUCTURE DU QUIZ »** : répartition par type avec icône + libellé + compte (Vrai/Faux, Choix unique, Choix multiples, Texte à trous, Associations, Remise en ordre, Réponse libre) ; section **« MES TENTATIVES »** (les plus récentes en premier) : pastille ✓/✗, « Tentative n — x % », « points/total points · date heure », mention « Réussi »/« Échoué ».

---

## 7. Lecteur de quiz — `/quizzes/{id}/play` (100 % hors-ligne)

**Objectif** : passage fluide, résultat immédiat, tolérant aux interruptions.

### 7.1 Reprise de session
Brouillon local (réponses, ordre des questions, position, chrono) sauvegardé **à chaque réponse** et toutes les 10 s de chrono. À l'ouverture, si brouillon : dialogue « **Reprendre votre session ?** — Une session interrompue de ce quiz a été retrouvée sur cet appareil. » → Reprendre (tout restaurer) / Annuler (brouillon supprimé, session neuve).

### 7.2 En-tête de jeu
« ✕ Quitter » (dialogue : « Votre progression est enregistrée sur cet appareil : vous pourrez reprendre plus tard. ») ; barre de progression (position/total) ; **chrono décompte** mm:ss si durée définie (rouge ≤ 60 s ; à 0 → soumission automatique).

### 7.3 Carte question
« Question n / total », « x pt(s) », **énoncé HTML riche** (images/audio inclus). Réponses par type :

| Type | Interface | Format de réponse |
|---|---|---|
| `true_false` | 2 grands boutons Vrai / Faux | `"true"` \| `"false"` |
| `mcq` simple | Boutons liste, sélection unique (◉) | texte du choix |
| `mcq` multiple (`options.multiple` ou `multiple_choice`) | Cases ☑ + mention « Plusieurs réponses possibles » | tableau de textes |
| `fill_blank` | 1 champ par trou, étiquetés « Trou n » | tableau de textes |
| `matching` | Terme + menu déroulant des définitions **mélangées une fois** (ordre stable) | `{terms: [...], definitions: [...]}` alignés |
| `ordering` | Liste **mélangée** réordonnable par **glisser-déposer** (poignée ≡, pointer events, seuil anti-tap 4 px) **et** flèches ↑/↓ 44 px ; numéros en direct | tableau ordonné |
| `open_text` | Zone de texte multiligne | texte |

Navigation « ← Précédent » / « Suivant → » / « Terminer ✓ » + compteur « n/total répondues ». `shuffle_questions` → ordre mélangé (conservé dans le brouillon).

### 7.4 Notation locale (identique au serveur, qui re-note à la sync)
- true_false : tout ou rien. — mcq simple : tout ou rien.
- mcq multiple strict : points si sélection = ensemble exact. — mcq **score partiel** : `arrondi(bonnes cochées / total bonnes × points)`, **0 si une mauvaise est cochée** ; « correct » = totalité.
- fill_blank : proportionnel par trou (sensibilité à la casse par trou). — matching : proportionnel par paire. — ordering : proportionnel **position par position**. — open_text : points si non vide.
- Score % = arrondi(points/total × 100, 2) ; réussi si ≥ seuil.

### 7.5 Écran de résultat
🎉/😕, « Quiz réussi ! »/« Quiz terminé », **score % géant** (vert/rouge), « points / total · seuil de réussite x % », « ⚡ +n XP » ; **delta** « ▲ +x pts / ▼ −x pts vs votre meilleur score (y %) / = égal » ; **barres d'historique** (7 dernières tentatives + courante en bleu) ; si `show_correct_answers` : accordéon « Voir le détail des réponses » (✓/✗ + points par question) ; boutons : **« Partager »** (si réussi — Web Share, repli copie presse-papiers + toast), « Détails », « Retour à l'entraînement ». Sons réussite/échec. En arrière-plan : action `quiz_attempt` en outbox, statut/tentative/XP optimistes, brouillon supprimé, **erreurs enregistrées** (question ratée → table locale ; réussie → retirée).

### 7-bis. Rejouer mes erreurs — `/quizzes/erreurs/play`
Quiz synthétique local à partir des questions ratées (mélangées). Bannière violette : « 🔁 Entraînement libre sur vos erreurs — sans XP ni enregistrement. » **Aucun envoi serveur, aucun XP, aucun statut modifié** ; les questions réussies sortent de la liste. Résultat : « Entraînement terminé » + « Recommencer ». État vide : 🎯 « Aucune erreur à rejouer — les questions ratées lors de vos quiz apparaîtront ici. »

---

## 8. Révision flashcards (session) — `/reviser/{deckId}`

**Objectif** : répétition espacée SM-2, entièrement hors-ligne.

**File** : cartes dues (jamais revues ou `next_review` ≤ maintenant) ; si aucune due → tout le deck (« révision en avance »). `/reviser` sans deck redirige vers `/entrainement?tab=cartes`.

**Composition** : « ✕ Arrêter » ; barre + « x/y » ; **carte retournable (flip 3D)** — face Question (accent violet, « Touchez pour retourner »), face Réponse (accent émeraude), contenu HTML riche ; accessible clavier (Espace/Entrée). Après le flip : 4 boutons — **À revoir (0, rouge) / Difficile (3, ambre) / Moyen (4, bleu) / Facile (5, émeraude)**.

**À chaque évaluation** : calcul **SM-2 local** — `q<3` → répétitions 0, intervalle 1 j ; sinon 1 j / 6 j / `arrondi(intervalle×EF)` ; `EF' = EF + (0,1 − (5−q)(0,08 + 0,02(5−q)))`, plancher 1,3 ; intervalle borné par le deck ; statut new→learning→review→mastered / relearning — mise à jour locale immédiate, action `card_review` (le serveur recalcule, même formule), **+5 XP**, son correct/faux. **Carte notée 0 re-présentée en fin de session.**

**Fin de session** : 🧠 « Session terminée ! », tuiles (révisées / maîtrisées / +XP), boutons « Recommencer » / « Mes decks » ; action `review_session` (durée, nouvelles/revues, notes).

---

## 9. Examens — `/examens`

**Objectif** : espace solennel des épreuves officielles, distinct de l'entraînement.

**Composition** : bandeau d'avertissement ambre (« Les examens sont des épreuves officielles surveillées : connexion requise, tentatives limitées, et toute infraction aux règles (capture d'écran, sortie de l'épreuve) peut entraîner l'annulation. ») ; sections **« À PASSER »** et **« HISTORIQUE & À VENIR »**.

**Carte examen** : liseré supérieur dégradé ambre→orange ; icône 🎓 ; titre ; « durée min · note /max · n tentative(s) » ; « 📅 Ferme le {date heure} » ; statut pill — Disponible (vert) / En cours (ambre) / Terminé (bleu) / Verrouillé / Expiré (gris) ; **chips sécurité** : 🛡 Surveillé, ⛶ Plein écran, 📷 Anti-capture, 🚫 Navigation bloquée (selon flags) ; dernière note « n/max » (vert ≥ seuil, rouge sinon) ; CTA « **Commencer/Reprendre l'examen** » si jouable.

État vide : 🎓 « Aucun examen programmé. »

---

## 10. Passage d'examen — `/exams/{id}/play` (online-only)

**Objectif** : épreuve chronométrée surveillée, notation /20, classement.

1. **Hors-ligne** → 📡 « Connexion requise — les examens surveillés ne peuvent être passés qu'en ligne, pour garantir l'intégrité de l'épreuve. »
2. **Consignes** : règles actives selon flags (plein écran automatique ; « toute capture d'écran **annule immédiatement** l'examen » ; « 3 changements d'onglet **annulent** l'examen » ; durée + soumission automatique) → « J'ai compris, commencer ». Erreurs serveur affichées (fenêtre fermée, tentatives épuisées…).
3. **Épreuve** : bandeau « 🛡 Surveillé », chrono (rouge ≤ 2 min, soumission auto à 0), questions aux mêmes UIs que le quiz mais **réponses correctes jamais transmises** (retirées côté serveur ; associations/ordre mélangés serveur) ; **autosave** des réponses (debounce 1,5 s).
   **Anti-fraude** (déclaratif client, décision serveur) : sortie d'onglet/fenêtre → violation `navigation` (toast « ⚠️ Sortie détectée (n/3) ») ; Impr. écran / Ctrl+P → violation `screenshot` + **voile blanc 2 s** ; clic droit bloqué ; plein écran forcé si flag ; Wake Lock (écran maintenu allumé). `cancelled` → dialogue « Examen annulé » + retour à la liste, listeners démontés.
4. **Résultat** : 🎓/📋, « Temps écoulé ! » si time-up, **note n/max géante** (vert/rouge), « points bruts/total · x % » (points négatifs appliqués par question fausse, plancher 0), « ⚡ +50 XP » si réussi, **classement** « 🏆 Xᵉ sur N participant(s) » si visible. Échec de soumission → message + retour.

---

## 11. Ma progression — `/progression`

**Objectif** : rétrospective personnelle + émulation de groupe.

**Composition**
1. Retour « ← Tableau de bord ».
2. **Résumé** : 4 tuiles — XP total / Niveau / 🔥 Série (avec « max n ») / % réussite quiz — + barre de progression du niveau.
3. **« Activité des 12 dernières semaines »** : heatmap 7 lignes × 12 colonnes (un carré = un jour ; intensités 0 / 1 / 2 / 3+ tentatives, du gris au bleu soutenu ; infobulle date + compte) + « n quiz complété(s) · m/k cartes maîtrisées ». Calculée localement → disponible hors-ligne.
4. **« Mes meilleurs scores »** : par quiz, barre horizontale du meilleur % (émeraude si 100 %).
5. **« 🏆 Classement de mes groupes »** : par groupe — « Vous êtes Xᵉ / N » ; lignes classées : médailles 🥇🥈🥉 (top 3), avatar initiales, nom (+ « (vous) » sur ligne surlignée bleue), 🔥 série, XP. En ligne : rechargé + mis en cache ; hors-ligne : cache avec mention « Classement issu de la dernière synchronisation » ou message d'indisponibilité. Squelettes pendant le chargement.

---

## 12. Profil — `/profil`

**Objectif** : identité, préférences, sécurité, contrôle de la synchronisation.

**Sections (cartes empilées)**
1. **Identité** : avatar (photo si locale, sinon **initiales sur dégradé déterministe** — jamais de service externe), nom complet, email, « Matricule : X ».
2. **Statistiques** : XP total / Niveau / 🔥 Série (max) + barre de niveau.
3. **Badges** : grille 2 colonnes — débloqués (fond ambre) vs verrouillés (grisés, opacité réduite) ; icône, nom, description.
4. **Préférences** (persistées localement + action `preferences_update`) :
   - Thème : segments « ☀️ Clair / 🌙 Sombre » (application immédiate) ;
   - Taille du texte : « A- / A / A+ » (échelle typographique globale) ;
   - Sons : interrupteur.
5. **Application** (si installable) : « Installez Learn&Quiz sur votre écran d'accueil… » + bouton « ⬇ Installer l'application » (prompt natif ou guide iOS).
6. **Sécurité** : « Le changement de mot de passe nécessite une connexion internet. » + bouton « 🔑 Changer mon mot de passe » → dialogue natif 3 champs (actuel / nouveau ≥ 8 / confirmation), **erreurs de validation affichées sous chaque champ** (« Le mot de passe actuel est incorrect. », « La confirmation ne correspond pas. »…), toast « Mot de passe mis à jour. » ; hors-ligne → toast d'avertissement, dialogue non ouvert.
7. **Synchronisation** : « Toutes vos données sont synchronisées. » ou « n action(s) en attente de synchronisation. », « Dernière sync : hh:mm:ss », bouton « 🔄 Synchroniser maintenant » (toast « Synchronisation terminée. »).
8. **Se déconnecter** (rouge) : dialogue de confirmation — si actions en attente : « n action(s) non synchronisée(s) seront perdues. Synchronisez d'abord si possible. » ; sinon : « Vos données locales seront effacées de cet appareil. » → tentative de sync, logout serveur, **purge complète locale** (IndexedDB + session), retour `/connexion`.

---

## 13. Matrice des états par écran

| Écran | Chargement | Vide | Hors-ligne | Erreur |
|---|---|---|---|---|
| Connexion | Bouton « Connexion… » | — | Message réseau inline | 401/403/429 inline |
| Accueil | Squelette global | Badges : message d'encouragement | Complet (données locales) | — |
| Articles / liste | instantané (local) | 2 messages (aucun / filtre) | Complet | — |
| Article | instantané | « Article introuvable. » | Complet (médias cachés) | — |
| Entraînement | instantané | messages par segment | Complet | — |
| Quiz play | dialogue reprise éventuel | « Quiz introuvable. » / max tentatives | Complet | — |
| Rejouer erreurs | instantané | 🎯 message + retour | Complet | — |
| Révision | instantané | (liste : deck vide) | Complet | « Deck introuvable. » |
| Examens / liste | instantané | 🎓 message | Complet (liste seule) | — |
| Examen play | démarrage réseau | — | 📡 « Connexion requise » | 403 serveur affichés, échec soumission |
| Progression | squelettes classement | sections conditionnelles | Stats oui, classement = cache | mention cache |
| Profil | instantané | — | Complet sauf mot de passe | 422 par champ |

---

*Document généré depuis l'implémentation réelle (branche `refactor/learner-pwa`) — 27/08/2026.*
