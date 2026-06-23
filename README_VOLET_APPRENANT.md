# QuizAndLearn - Documentation Volet Apprenant

**Date de création:** 21 juin 2026  
**Statut:** Analyse complète et PRD validés  

---

## 📚 Fichiers de documentation créés

| Fichier | Description | Utilité |
|---------|-------------|---------|
| **ANALYSE_PROJET_ADMINISTRATION.md** | Analyse complète du volet admin existant | Comprendre l'existant, architecture, données |
| **PRD_VOLET_APPRENANT.md** | Product Requirements Document complet | Spécifications fonctionnelles détaillées |
| **DATABASE_SCHEMA_APPRENANT.md** | Schéma base de données avec SQL | Créer les tables nécessaires |
| **GUIDE_DEVELOPPEMENT.md** | Roadmap technique et checklist | Guide pas-à-pas pour développer |
| **README_VOLET_APPRENANT.md** | Ce fichier (résumé exécutif) | Vue d'ensemble rapide |

---

## 🎯 Résumé du projet

### Vision
Développer une **application web PWA offline-first** pour les apprenants en formation professionnelle, permettant:
- Consultation de contenus pédagogiques (articles, quiz, ressources externes)
- Passage de quiz avec notation automatique et deadlines
- Suivi de progression en temps réel
- Génération automatique de certificats
- Fonctionnement hors ligne (cache-first)

### Contraintes validées
✅ **Public:** Grand public en formation professionnelle  
✅ **Inscription:** Gérée uniquement par admin/formateurs  
✅ **Notation:** Système automatique avec scores et seuils de passage  
✅ **Certificats:** Génération automatique après complétion  
✅ **Quiz:** Linéaires (pas de branches conditionnelles)  
✅ **Ressources:** Support des vidéos, PDF, liens externes  
✅ **Deadlines:** Obligatoires sur quiz/articles  
✅ **Tracking:** Complet (KPIs, temps, progression, tentatives)  
✅ **Offline:** PWA avec cache-first, sync en retour online  

---

## 📊 Analyse de l'existant (Volet Admin)

### ✅ Ce qui est déjà prêt

#### Infrastructure
- Laravel 12 + PostgreSQL 15
- Architecture modulaire (nwidart/laravel-modules)
- Système de permissions (Spatie)
- Audit trail (Spatie ActivityLog)
- Authentification fonctionnelle

#### Entités de base
- **Users** (Admin, Trainer, Learner) avec rôles
- **Groups** (cohortes d'apprenants)
- **Articles** avec médias, SEO, assignation groupes
- **Quizzes** avec questions (QCM, Vrai/Faux), shuffle, scores
- **Questions** avec choix multiples, points, ordre

#### Relations établies
- Group ↔ Learner (many-to-many)
- Group ↔ Trainer (many-to-many)
- Group ↔ Quiz (many-to-many)
- Group ↔ Article (many-to-many)
- Quiz → Questions (one-to-many)

### ❌ Ce qui manque pour le volet apprenant

#### Tables BDD
- `quiz_attempts` - Tentatives de quiz
- `quiz_answers` - Réponses détaillées
- `learner_progress` - Tracking progression
- `certificates` - Certificats générés
- `external_resources` - Ressources externes (vidéos, PDF)
- `group_deadlines` - Dates limites par contenu
- `notifications` - Système de notifications

#### Backend
- API REST pour l'interface apprenant
- Services métier (calcul scores, génération certificats, tracking)
- Jobs asynchrones (notifications, certificats)
- Middleware de vérification (deadlines, tentatives)

#### Frontend
- Application PWA dédiée apprenants
- Service Workers (cache offline)
- Interface passage de quiz (timer, sauvegarde auto)
- Lecteur d'articles avec tracking
- Dashboard apprenant

---

## 🏗️ Architecture technique

### Stack recommandée
- **Backend:** Laravel 12 (existant) + API REST
- **Frontend:** Blade + Alpine.js + Service Workers (simple à intégrer)
- **PWA:** Workbox + Manifest (offline-first)
- **Cache:** Redis (sessions, résultats, stats)
- **Queue:** Laravel Queue (notifications, certificats)
- **Storage:** IndexedDB (cache offline côté client)

### Alternative (si besoin SPA full)
- Inertia.js + Vue 3/React (plus complexe mais meilleure UX)

---

## 📋 Fonctionnalités principales

### 1. Authentification & Profil
- Login email/password (réutilise système Laravel)
- Consultation/modification profil (avatar, infos perso)
- Pas d'auto-inscription

### 2. Dashboard apprenant
- Contenus en cours, à faire, complétés
- Stats personnelles (score moyen, taux réussite, temps)
- Prochaines deadlines (7 jours)
- Notifications récentes (5 dernières)

### 3. Articles
- Liste articles assignés au groupe
- Lecteur avec médias (images, vidéos, fichiers)
- Tracking automatique (progression, temps)
- Disponible hors ligne après 1er chargement

### 4. Quiz
- Liste quiz avec statut (non commencé, en cours, terminé)
- Interface de passage avec:
  - Timer décompte (géré serveur)
  - Sauvegarde auto réponses (toutes les 2s)
  - Navigation questions
  - Soumission avec confirmation
- Résultats immédiats (score, réussite/échec, détails)
- Historique des tentatives
- Limite de tentatives configurable

### 5. Ressources externes
- Vidéos (YouTube/Vimeo embed)
- PDF (viewer inline)
- Liens externes
- Tracking de visualisation

### 6. Progression
- Suivi automatique par contenu
- Stats détaillées (temps, complétion, scores)
- Graphiques d'évolution
- Export PDF du parcours

### 7. Certificats
- Génération automatique après complétion:
  - Tous quiz réussis (score >= minimum)
  - Tous articles lus
  - Toutes ressources vues
- Téléchargement PDF
- Code unique de validation
- Badge sur dashboard

### 8. Deadlines & Notifications
- Dates limites par contenu
- Badges visuels (couleurs selon urgence)
- Blocage accès si deadline dépassée (configurable)
- Notifications:
  - Nouveaux contenus
  - Deadlines approchantes (J-7, J-2, J-1)
  - Résultats quiz
  - Certificats disponibles

---

## 🗄️ Schéma Base de Données

### Nouvelles tables à créer

```sql
quiz_attempts (id, learner_id, quiz_id, started_at, completed_at, score, passed, time_spent, answers, ...)
quiz_answers (id, attempt_id, question_id, answer_given, correct_answer, is_correct, points_earned, ...)
learner_progress (id, learner_id, content_type, content_id, status, progress_percentage, time_spent, ...)
certificates (id, learner_id, group_id, certificate_code, title, pdf_path, issued_at, metadata, ...)
external_resources (id, title, description, resource_type, url, thumbnail, duration, ...)
group_resource (group_id, resource_id) -- Table pivot
group_deadlines (id, group_id, content_type, content_id, deadline, is_mandatory, ...)
notifications (id, user_id, type, title, message, action_url, is_read, ...)
```

Voir **DATABASE_SCHEMA_APPRENANT.md** pour SQL complet.

---

## 🚀 Roadmap de développement

### Phase 1: Setup & Infra (2 semaines)
- Créer migrations BDD
- Créer models Eloquent avec relations
- Configurer PWA (manifest, service worker)
- Setup routes API (stubs)

### Phase 2: Articles & Ressources (2 semaines)
- Backend: API articles, tracking progression
- Frontend: Liste, lecteur avec tracking scroll/temps
- Ressources externes (vidéos, PDF, liens)
- Cache offline (Service Worker + IndexedDB)

### Phase 3: Quiz (2 semaines)
- Backend: Services passage quiz, calcul scores
- Frontend: Interface quiz avec timer
- Sauvegarde auto réponses
- Gestion offline (queue soumissions)
- Affichage résultats

### Phase 4: Dashboard & Profil (1 semaine)
- Dashboard avec widgets (stats, deadlines, notifications)
- Profil utilisateur (consultation, édition)
- Stats personnelles détaillées

### Phase 5: Certificats & Notifications (1 semaine)
- Service génération certificats (PDF)
- Système notifications complet
- Gestion deadlines (blocages, alertes)

### Phase 6: PWA & Optimisation (1 semaine)
- Finalisation Service Worker
- Cache stratégies (cache-first, network-first)
- Background sync
- Tests offline extensifs

### Phase 7: Tests & Polish (1 semaine)
- Tests backend (unit, feature)
- Tests E2E (Playwright/Cypress)
- Load testing (k6)
- Optimisations performance
- Corrections bugs

**Total: 10 semaines**

---

## 🔒 Sécurité

### Authentification
- Laravel Sanctum (API tokens)
- Middleware `auth:sanctum` sur toutes routes API
- Middleware `role:learner` pour restriction

### Autorisation
- Vérification appartenance au groupe pour accès contenus
- Permissions via Spatie (réutilise existant)

### Prévention triche (Quiz)
- Timer géré côté serveur (pas JavaScript)
- Réponses correctes jamais exposées côté client
- Validation temps écoulé à soumission
- Tentatives limitées (configuré par quiz)
- Log IP et User-Agent
- Détection activités suspectes (optionnel)

---

## 📈 Métriques à tracker

### Business
- DAU, WAU, MAU (utilisateurs actifs)
- Taux de complétion des contenus
- Taux de réussite aux quiz
- Temps moyen par contenu
- Certificats émis
- Deadlines manquées

### Technique
- Temps réponse API (< 200ms objectif)
- Erreurs serveur (via Sentry)
- Performance frontend (Lighthouse > 90)
- Uptime (99.9% objectif)
- Cache hit rate Redis

---

## 📝 Prochaines étapes

### Immédiat
1. ✅ **Analyse terminée** (ce document)
2. ⬜ Validation PRD par Product Owner
3. ⬜ Estimation fine par équipe dev
4. ⬜ Planification sprints
5. ⬜ Démarrage Phase 1 (migrations BDD)

### Cette semaine
- Créer toutes les migrations BDD
- Créer models Eloquent avec relations
- Configurer manifest PWA basique
- Setup structure routes API

### Semaine prochaine
- Développer API articles (CRUD)
- Développer service tracking progression
- Créer layout frontend apprenant (Blade)
- Implémenter premier service worker

---

## 📖 Ressources

### Documentation technique
- [Laravel 12 Docs](https://laravel.com/docs/12.x)
- [Spatie Permission](https://spatie.be/docs/laravel-permission)
- [Spatie ActivityLog](https://spatie.be/docs/laravel-activitylog)
- [Workbox (PWA)](https://developers.google.com/web/tools/workbox)
- [Service Workers MDN](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)

### Outils
- **API Testing:** Postman, Insomnia
- **E2E Testing:** Playwright, Cypress
- **Load Testing:** k6, Artillery
- **Monitoring:** Sentry, New Relic, Laravel Telescope

---

## 👥 Support

### Questions techniques
- Consulter **GUIDE_DEVELOPPEMENT.md** (checklist détaillée)
- Consulter **DATABASE_SCHEMA_APPRENANT.md** (SQL complet)
- Consulter **PRD_VOLET_APPRENANT.md** (specs fonctionnelles)

### Questions fonctionnelles
- Consulter **PRD_VOLET_APPRENANT.md**
- Contacter Product Owner

### Architecture
- Consulter **ANALYSE_PROJET_ADMINISTRATION.md**
- Contacter Tech Lead

---

## ✅ Validation

**Analyse approuvée par:**
- [ ] Product Owner
- [ ] Tech Lead
- [ ] Équipe Développement

**Date de démarrage prévue:** [À définir]

---

**Bon développement ! 🚀**
