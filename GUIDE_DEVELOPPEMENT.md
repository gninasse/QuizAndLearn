# Guide de Développement - Volet Apprenant (Learn&Quiz)

**Date:** 23 juin 2026  
**Version:** 2.0 (Aligné sur le nouveau Cahier des Charges)

---

## 🎯 Vue d'ensemble technique
Le volet apprenant est construit sur l'écosystème Laravel 12 existant avec une séparation logique stricte du volet d'administration.
*   **Architecture :** Intégration complète au module `Core` pour partager les entités (Users, Learners, Quizzes, Questions, Articles) tout en créant des contrôleurs, services et routes distincts.
*   **Sécurité :** JWT ou cookies HttpOnly pour l'authentification. Session expirée après 15 min d'inactivité.
*   **PWA & Hors-Ligne :** Utilisation de Service Workers (Workbox) pour le cache-first (quiz d'entraînement et articles consultés) et IndexedDB pour stocker les tentatives de quiz hors ligne avant synchronisation.

---

## 🗂️ Structure et Services Recommandés

### 1. Services Backend (`Modules/Core/app/Services`)
*   `FlashcardService` :
    *   `calculateNextReview(Flashcard $flashcard, string $easeRating): void` : Calcule les nouveaux paramètres SM-2 ($EF$, intervalle de répétition) et fixe la prochaine date de révision.
    *   `getDueCards(Learner $learner): Collection` : Retourne la liste des flashcards arrivant à échéance.
*   `GamificationService` :
    *   `awardXp(Learner $learner, int $amount, string $reason): void` : Incrémente l'XP, recalcule le niveau de l'apprenant selon la formule exponentielle ($Niveau = 100 \times Niveau^{1.5}$) et déclenche la notification si passage de niveau.
    *   `updateStreak(Learner $learner): void` : Valide l'activité quotidienne, met à jour le streak actuel et record, et applique le bonus d'XP (+5% à +50%).
*   `BadgeService` :
    *   `checkEligibility(Learner $learner, string $triggerType): void` : Vérifie automatiquement l'éligibilité aux 10 badges et attribue le badge si les conditions (JSON) sont remplies.
*   `ScreenshotDetectionService` :
    *   `logAttempt(Learner $learner, QuizAttempt $attempt, string $ip, string $userAgent): void` : Enregistre l'incident, incrémente les signalements et notifie le formateur responsable.

### 2. Jobs de fond (`Modules/Core/app/Jobs`)
*   `CheckBadgeEligibility` : Job en file d'attente pour analyser l'attribution d'un badge après une action.
*   `SendStreakReminder` : Exécuté à 20h00 chaque jour pour envoyer des notifications push douces aux apprenants inactifs.
*   `SyncOfflineResults` : Traite les résultats de quiz stockés temporairement sur IndexedDB et synchronise le statut de progression.

---

## 📅 Roadmap Estimée (16 Semaines)

### Phase 1 : Setup & Auth (Semaines 1-2)
*   Routes de connexion distinctes (`/learner/login`).
*   Création du profil apprenant (avatar dynamique, paramètres de langue/thème/son).
*   Mise en place du middleware de déconnexion automatique (15 min).

### Phase 2 : Module Quiz (Semaines 3-5)
*   Mode examen (temps limité, tentatives contrôlées, pas de correction en cours de jeu).
*   Mode entraînement (correction immédiate, feedback et explications pédagogiques).
*   Système de notation en points et pourcentage (QCM multiple à notation partielle).
*   Historique des tentatives.

### Phase 3 : Articles & Zen (Semaines 6-7)
*   Lecture d'articles responsive.
*   Génération automatique du sommaire depuis les balises HTML.
*   Mode lecture zen (CSS dynamique) et tracking du scroll à 80% pour marquage automatique comme lu.
*   Évaluation par étoiles (1 à 5).
*   Favoris.

### Phase 4 : Flashcards & Algorithme SM-2 (Semaines 8-9)
*   Création automatique de flashcards à partir des quiz échoués.
*   Interface d'auto-évaluation de révision ("Facile", "Moyen", "Difficile", "À revoir").
*   Planificateur visuel sur 7 jours.

### Phase 5 : Gamification (Semaines 10-11)
*   Système de calcul XP dynamique avec multiplicateurs.
*   Gestion quotidienne des streaks.
*   Moteur de règles pour les 10 badges.
*   Widget de comparaison anonymisée (ex: "Top 20%").

### Phase 6 : Sécurité & Hors ligne (Semaines 12-13)
*   Détection JavaScript des captures d'écran et écran blanc instantané de 5 secondes.
*   Mise en cache IndexedDB et synchronisation silencieuse après coupure réseau.

### Phase 7 : Accessibilité & Thème Sombre (Semaine 14)
*   Audit WCAG 2.1 AA (navigation clavier, ARIA labels, contrastes).
*   Bascule automatique/manuelle du thème sombre.

### Phase 8 : Tests, Perf & Polish (Semaines 15-16)
*   Tests fonctionnels unitaires et feature.
*   Optimisations de chargement Lighthouse (> 90).

---

## 🧪 Stratégie de Test & Commandes
Tous les tests doivent être configurés pour s'exécuter en base de données SQLite en mémoire pour garantir la rapidité.
*   **Lancement de la suite complète :** `php artisan test`
*   **Lancement d'un test spécifique :** `php artisan test tests/Feature/LearnerBackendCompatibilityTest.php`
*   **Vérification du format de code (Pint) :** `vendor/bin/pint`
