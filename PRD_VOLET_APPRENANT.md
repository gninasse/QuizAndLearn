# PRD - Learn&Quiz Volet Apprenant

**Date:** 23 juin 2026  
**Version:** 2.0 (Aligné sur le Cahier des Charges final)  
**Statut:** ✅ Validé et compatible backend

---

## 1. Vision et Objectifs
Le **volet apprenant** de l'application Learn&Quiz est un espace d'apprentissage mobile-first, immersif et dénué de toute distraction administrative. Il est strictement séparé du volet d'administration et permet aux apprenants de :
*   Consommer du contenu pédagogique (articles et quiz).
*   Réviser via un algorithme de répétition espacée intelligent (flashcards SM-2).
*   Suivre leur progression via des mécaniques de gamification (XP, niveaux, streaks, badges).
*   Travailler de manière autonome en mode hors ligne (PWA).
*   Bénéficier d'une interface accessible (WCAG 2.1 AA / RGAA).

---

## 2. Acteurs et Authentification
*   **🧑🎓 Apprenant :** Unique acteur du volet apprenant. Rattaché à un ou plusieurs groupes de formation.
*   **Authentification :** Identifiant unique + mot de passe sécurisé. Session HttpOnly. Déconnexion automatique après 15 min d'inactivité. Compteur et historique de connexions affichés dans le profil.

---

## 3. Fonctionnalités Détaillées (PRD)

### F1 : Tableau de bord personnalisé
*   **Priorité :** P0
*   **Description :** Page d'accueil après connexion avec sections repliables et réorganisation adaptative selon l'urgence.
*   **Widgets :**
    1.  **📅 Révisions du jour :** Flashcards arrivant à échéance (prioritaire si non vide).
    2.  **📝 Quiz en attente :** Quiz assignés non commencés ou tentatives restantes.
    3.  **📄 Articles récents :** Dernières publications avec indicateur "non lu".
    4.  **📈 Progression globale :** Taux de réussite global, graphique de tendance (sur 7 et 30 jours).
    5.  **🏆 Gamification :** Niveau, XP, série de jours (streak 🔥) et badges récents.
    6.  **⭐ Favoris et raccourcis :** Accès rapide aux favoris et quiz fréquents.

### F2 : Bibliothèque de quiz
*   **Priorité :** P0
*   **Description :** Liste des quiz assignés filtrable par matière, niveau, mode (examen, entraînement, flashcard) et état (nouveau, en cours, terminé).
*   **Modes de jeu :**
    *   **Examen :** Tentatives limitées, chronométré, pas de correction en cours de jeu. Une tentative commencée et abandonnée est consommée. Score officiel sauvegardé.
    *   **Entraînement :** Pratique libre, tentatives illimitées, correction et explications pédagogiques immédiates. Score personnel non transmis au formateur.
    *   **Flashcard :** Révision intelligente (SuperMemo-2).
*   **Notation :** Score brut (points obtenus / points totaux) et pourcentage. Prise en compte du score partiel pour les QCM multiples si activé.

### F3 : Révision par Flashcards (SM-2)
*   **Priorité :** P1
*   **Description :** Système de révision espacée automatique basé sur les questions échouées ou marquées manuellement.
*   **Logique de révision :**
    *   Auto-évaluation de l'apprenant après réponse ("Facile", "Moyen", "Difficile", "À revoir").
    *   Formule SM-2 calculant le prochain intervalle (1j → 3j → 7j → 14j → 30j → 60j).
    *   Visualisation du planning à venir (cartes aujourd'hui, demain, et les 7 prochains jours).

### F4 : Bibliothèque d'articles
*   **Priorité :** P0
*   **Description :** Accès aux articles pédagogiques assignés aux groupes de l'apprenant.
*   **Lecture zen :** Mode sans distraction (masquage header/sidebar), barre de progression de lecture, et marquage automatique "lu" à 80% du défilement.
*   **Sommaire Auto :** Sommaire dynamique généré à partir des titres cliquables (ancres).
*   **Étoiles :** Évaluation optionnelle de 1 à 5 étoiles à la fin de la lecture.

### F5 : Signalement d'erreurs
*   **Priorité :** P1
*   **Description :** Formulaire court disponible en bas d'article ou fin de quiz pour signaler une erreur (contenu, orthographe, technique) au formateur.

### F6 : Anti-Capture d'Écran
*   **Priorité :** P1 (Sécurité)
*   **Description :** Détection de capture d'écran (PrintScreen ou visibilité navigateur) en cours de quiz.
*   **Sanction :** Écran blanc instantané pendant 5 secondes, enregistrement de la tentative dans `screenshot_attempts`, notification du formateur, et annulation de l'examen si paramétré.

### F7 : Gamification & Motivation
*   **Priorité :** P1
*   **Description :** Attribution automatique de badges (10 badges définis), gain d'XP (quiz : 10 XP x % ; article : 5 XP ; flashcard : 2 XP ; badge : 50 XP) et calcul des niveaux ($Niveau = 100 \times N^{1.5}$).
*   **Streaks (Série) :** Suivi quotidien de l'activité. Rappel à 20h si aucune activité effectuée.
*   **Comparaison anonymisée :** Positionnement par pourcentage (ex: "Top 20% du groupe") pour motiver sans instaurer de compétition directe nominative.

### F8 : Mode Hors Ligne
*   **Priorité :** P0
*   **Description :** PWA installable. Mise en cache automatique (IndexedDB/Service Worker) des quiz/articles consultés et des flashcards. Synchronisation automatique des tentatives et favoris dès le retour au réseau.

### F9 : Accessibilité et Thèmes
*   **Priorité :** P1
*   **Description :** Thème clair/sombre automatique, contrastes minimums de 4.5:1, support lecteur d'écran (ARIA labels), navigation clavier intégrale, taille de police ajustable (3 niveaux).

---

## 4. Architecture BDD (11 Tables Actives)
1.  **`quiz_attempts` :** Suivi des tentatives (points, score, état, ip, user agent, JSON answers).
2.  **`quiz_answers` :** Historique détaillé question par question.
3.  **`learner_progress` :** Statut de complétion, favoris et notes par étoiles.
4.  **`notifications` :** Centre de notifications in-app pour l'apprenant.
5.  **`flashcards` :** Données SM-2 de révision (EF, intervalle, répétitions, prochaine date).
6.  **`badges` :** Règles et métadonnées de badges.
7.  **`learner_badges` :** Attribution des badges.
8.  **`learner_xp` :** Total XP, niveau, streak actuel et max, dernière date d'activité.
9.  **`error_reports` :** Signalements d'erreurs d'apprenants.
10. **`screenshot_attempts` :** Historique des triches/captures d'écran détectées.
11. **`learner_preferences` :** Thème, langue, DND et configuration notifications.
