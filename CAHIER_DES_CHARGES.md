# Cahier des Charges — QuizAndLearn

> Document de cadrage du projet, dérivé des spécifications fonctionnelles
> (`SPECIFICATIONS_FONCTIONNELLES.md`). Définit le contexte, les objectifs, le périmètre,
> les exigences (fonctionnelles et non fonctionnelles), les acteurs, les livrables et la
> feuille de route.

---

## 1. Présentation du projet

### 1.1 Contexte
QuizAndLearn est une plateforme web d'apprentissage et d'évaluation destinée à des organisations
(formation, enseignement, certification). Elle permet de **créer du contenu pédagogique**
(quiz, examens, decks de flashcards, articles), de l'**assigner à des groupes**, et de le
**diffuser à des apprenants** via une application mobile-first hors-ligne (PWA).

### 1.2 Objectif
Fournir un outil unique couvrant tout le cycle de l'apprentissage :
1. **Authoring** côté formateurs/admins (back-office).
2. **Diffusion** côté apprenants (PWA, y compris hors-ligne).
3. **Motivation** via la gamification (XP, niveaux, séries, badges, classement).
4. **Intégrité** via la notation et l'anti-fraude (examens proctored).

### 1.3 Périmètre
- **Inclus** : back-office (AdminLTE), volet apprenant PWA, gamification, SM-2, RBAC modulaire, audit.
- **Hors périmètre (actuel)** : applications mobiles natives, paiement/monétisation, LMS tiers,
  multi-tenant, éditeur de contenu WYSIWYG avancé (rich-text existant mais non spécifié ici).

---

## 2. Parties prenantes & acteurs

| Acteur | Rôle | Accès |
| --- | --- | --- |
| **Super Admin** | Administration globale, modules, permissions | Back-office (`cores.*`), toutes permissions |
| **Admin** | Gestion utilisateurs/contenus | Back-office, permissions déléguées |
| **Formateur (Trainer)** | Crée contenu, supervise groupes/examens | Back-office (périmètre = ses groupes) |
| **Apprenant (Learner)** | Consomme contenu, passe quiz/examens, révise | PWA (`/`), middleware `learner` |
| **Système** | Jobs, sync, expiration d'activités | Interne |

---

## 3. Exigences fonctionnelles

> Identifiants `EF-xxx` (Exigence Fonctionnelle) pour traçabilité vers les specs.

### 3.1 Authentification & accès (EF-AUTH)
- **EF-AUTH-01** : Connexion staff par email ou `user_name`, compte `is_active` requis.
- **EF-AUTH-02** : Connexion apprenant par email/`user_name`, **+ profil learner obligatoire**,
  sinon déconnexion forcée.
- **EF-AUTH-03** : Déconnexion et journalisation (login/logout avec IP + user-agent).
- **EF-AUTH-04** : Middleware `learner` protégeant toutes les routes du volet apprenant.

### 3.2 Gestion des identités (EF-ID)
- **EF-ID-01** : CRUD utilisateurs (users, admins, trainers, learners) en modales + datatables.
- **EF-ID-02** : Profils `learner` (matricule) et `trainer` (spécialité/biographie) liés au `User`.
- **EF-ID-03** : Gestion des groupes (dates, statut) + assignation formateurs/apprenants.
- **EF-ID-04** : Réinitialisation de mot de passe vers valeur par défaut ; bascule statut actif/inactif.
- **EF-ID-05** : Protection auto-suppression / auto-desactivation ; admin ne peut modifier son propre rôle.

### 3.3 Rôles, permissions & modules (EF-RBAC)
- **EF-RBAC-01** : Rôles Spatie (`super-admin, admin, trainer, learner`), matrice de permissions groupées par module.
- **EF-RBAC-02** : Attribution/révocation de rôles et permissions par utilisateur.
- **EF-RBAC-03** : Installation / activation / désactivation / désinstallation de modules à chaud.
- **EF-RBAC-04** : Synchronisation automatique des permissions depuis `config/permissions.php`.
- **EF-RBAC-05** : `super-admin` non supprimable/non modifiable.

### 3.4 Éditeur de Quiz (EF-QUIZ)
- **EF-QUIZ-01** : CRUD quiz + publish/draft + autosave AJAX.
- **EF-QUIZ-02** : Questions multi-types (`true_false, mcq, fill_blank, matching, ordering, open_text`).
- **EF-QUIZ-03** : Réordonnancement, assignation de groupes, mélange des questions (`shuffle`).
- **EF-QUIZ-04** : Aperçu (device), impression, paramètres (`duration, passing_score, max_attempts,
  show_correct_answers, allow_partial_score, disponibilité`).

### 3.5 Éditeur d'Examens (EF-EXAM)
- **EF-EXAM-01** : CRUD examen + questions (avec `points_negatifs`).
- **EF-EXAM-02** : Flags de sécurité (`plein_ecran_force, anti_capture_strict, navigation_interdite`).
- **EF-EXAM-03** : Publication des résultats (`immediate/apres_fermeture/manuelle`), classement (visible/anonyme).
- **EF-EXAM-04** : Supervision temps réel (captures, navigations, scores).
- **EF-EXAM-05** : Notation/20 via `note_max`, annulation automatique en cas de fraude stricte.

### 3.6 Decks de Flashcards (EF-FC)
- **EF-FC-01** : CRUD decks + cartes (`recto/verso`, médias, tags).
- **EF-FC-02** : Génération automatique depuis quiz / examen / article.
- **EF-FC-03** : Algorithmes `sm2` / `leitner`, assignation de groupes, aperçu/impression.

### 3.7 Articles (EF-ART)
- **EF-ART-01** : CRUD article (contenu riche, SEO, catégorie, disponibilité).
- **EF-ART-02** : Upload médias (image ≤5 Mo, audio ≤10 Mo).
- **EF-ART-03** : Embed de quiz, assignation de groupes, publish/draft + autosave.
- **EF-ART-04** : Export HTML autonome sécurisé (base64 images, médias anti-téléchargement).

### 3.8 Volet Apprenant PWA (EF-APP)
- **EF-APP-01** : Shell SPA hors-ligne (Service Worker, IndexedDB `LearnQuizDB`, sync queue).
- **EF-APP-02** : `/api/init` (données complètes) + `/api/sync` (rejeu d'actions hors-ligne).
- **EF-APP-03** : Passage de quiz (notation client puis sync serveur), affiche réponses si autorisé.
- **EF-APP-04** : Lecture d'articles (progression, favori, note, signalement d'erreur).
- **EF-APP-05** : Révision flashcards (SM-2), examens sécurisés (masquage réponses, log violations).
- **EF-APP-06** : Profil + préférences (thème clair/sombre, taille police, langue, son, notifications).
- **EF-APP-07** : Bannière online/offline + badge de synchronisation.

### 3.9 Gamification (EF-GAME)
- **EF-GAME-01** : XP par événement (quiz +`20+30`si réussi+`5×pts`, article +15, flashcard +5, examen +50).
- **EF-GAME-02** : Niveaux + progression ; séries (streaks) hier→+1, sinon reset à 1.
- **EF-GAME-03** : Badges `quiz_completed` / `article_read` avec seuil ; déblocage automatique.
- **EF-GAME-04** : Classement par examen (note/20 desc, durée asc).

### 3.10 Audit & sécurité (EF-SEC)
- **EF-SEC-01** : Journal d'activité filtrable (module, user, rôle, action, IP, dates).
- **EF-SEC-02** : Log captures d'écran (quiz) et signalements d'erreur.
- **EF-SEC-03** : Annulation examen sur capture stricte / 3 navigations interdites.
- **EF-SEC-04** : Protections des articles exportés (no-download, no-PiP, blocage clavier).

---

## 4. Exigences non fonctionnelles

| Réf | Catégorie | Exigence |
| --- | --- | --- |
| **ENF-PERF** | Performance | Datatables serveur-side (pas de rendu de toutes les lignes côté client) ; pagination/recherche par endpoint `…/data`. |
| **ENF-DISP** | Disponibilité hors-ligne | Le volet apprenant doit fonctionner sans réseau (IndexedDB + sync différée). |
| **ENF-SEC** | Sécurité | `is_active` obligatoire ; middleware `learner` ; masquage des réponses en examen ; journalisation IP/UA. |
| **ENF-RBAC** | Autorisation | Contrôle par permission Spatie, vérifié par requête ; périmètre formateur sur ses groupes. |
| **ENF-AUDIT** | Traçabilité | Toute mutation sensible journalisée (module, IP, rôles causeur). |
| **ENF-TECH** | Stack | Laravel 12, PHP 8.3, PostgreSQL, Tailwind v4, AdminLTE, PWA vanilla JS. |
| **ENF-EXT** | Extensibilité | Architecture multi-modules (`nwidart`) ; installation/sync de modules à chaud. |
| **ENF-ERG** | Ergonomie | Back-office AdminLTE (modales, Select2, SweetAlert2) ; PWA mobile-first (sidebar + tab nav). |
| **ENF-I18N** | Internationalisation | Préférence `locale` fr/en ; contenu majoritairement en français. |
| **ENF-ACCESS** | Accessibilité | Support dark mode sur le volet apprenant (thème configurable). |

---

## 5. Cas d'usage prioritaires (résumé)

1. **Formateur** crée un quiz → l'assigne à un groupe → un apprenant le passe hors-ligne → gagne de l'XP/un badge.
2. **Admin** crée un examen sécurisé → supervise en temps réel → un apprenant triche (capture) → examen annulé automatiquement.
3. **Apprenant** lit un article → le marque lu (+15 XP) → le met en favori → le note.
4. **Apprenant** révise ses flashcards (SM-2) → progression d'intervalle recalculée → +5 XP.
5. **Super Admin** installe un module → synchronise ses permissions → l'active pour les rôles concernés.

---

## 6. Livrables attendus

- Application back-office fonctionnelle (CRUD + RBAC + audit).
- Application PWA apprenant (online + offline).
- Moteur SM-2 et gamification opérationnels.
- Éditeurs de contenu (quiz/examen/flashcard/article) avec aperçu/impression/export.
- Documentation (spécifications fonctionnelles, cahier des charges, README d'installation).
- Jeux de tests (PHPUnit) couvrant les happy/failure/weird paths.

---

## 7. Contraintes & dépendances

- **Environnement** : PHP 8.3+, PostgreSQL 15+, Node 18+, Composer.
- **Dépendances clés** : `nwidart/laravel-modules`, `spatie/laravel-permission`, `spatie/laravel-activitylog`.
- **Données initiales** : seeders de rôles, commandes `cores:*` (sync, create-user, make-superadmin).

---

## 8. Feuille de route (jalons)

| Jalon | Contenu | Statut |
| --- | --- | --- |
| **M1 — Socle** | Auth, users, rôles/permissions, modules, audit | ✅ Réalisé |
| **M2 — Contenu** | Quiz, Examens, Flashcards, Articles + éditeurs | ✅ Réalisé |
| **M3 — Apprenant** | PWA, quiz/articles/révision/examens, hors-ligne | ✅ Réalisé |
| **M4 — Gamification** | XP, niveaux, séries, badges, classement examen | ✅ Réalisé |
| **M5 — Durcissement (recommandé)** | Résolution des incohérences (§10 specs) : unifier SM-2 sur `FlashcardItemReview`, normaliser types de questions, aligner formule de niveau, étendre badges (streak/perfect), classement XP global, activer graphiques dashboard | ⏳ À planifier |

---

## 9. Critères d'acceptation (extraits)

- Un apprenant hors-ligne peut lire un article et passer un quiz ; les actions sont rejouées à la reconnexion.
- Une capture d'écran en examen `anti_capture_strict` annule immédiatement la tentative.
- La matrice de permissions reflète exactement les accès effectifs (pas de dérive).
- Le journal d'audit permet de retracer toute suppression/modification sensible (qui, quand, IP).
- L'XP et les badges se mettent à jour cohérents après chaque activité.

---

## 10. Risques & dettes techniques

| Risque | Impact | Recommandation |
| --- | --- | --- |
| Double système de flashcards (legacy + deck) | Maintenance, comportement divergent | Unifier sur `FlashcardItemReview` |
| Formule de niveau divergente (dashboard vs quiz) | Apprenant perdu sur sa progression | Aligner sur une formule unique |
| Badges partiels (streak/perfect absent) | Gamification sous-exploitée | Implémenter les conditions manquantes |
| Pas de classement XP global | Manque de motivation transversale | Ajouter un leaderboard global |
| Rollback migration désactivé à la désinstallation | Pollution DB possible | Réactiver ou documenter |

---

*Cahier des charges dérivé de `SPECIFICATIONS_FONCTIONNELLES.md` — QuizAndLearn, 12 juillet 2026.*
