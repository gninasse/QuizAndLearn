# Analyse des Écarts - Nouveau Cahier des Charges

**Date:** 21 juin 2026, 21:13 UTC  
**Version:** 1.0  

---

## 🔍 Comparaison avec le PRD existant

### ✅ Points communs (déjà couverts)

1. **Architecture séparée** admin/apprenant ✓
2. **PWA offline-first** ✓
3. **Quiz avec modes multiples** ✓
4. **Articles pédagogiques** ✓
5. **Tracking de progression** ✓
6. **Système de notifications** ✓
7. **Responsive mobile-first** ✓
8. **Accessibilité WCAG 2.1 AA** ✓

---

## 🆕 Nouvelles fonctionnalités (non présentes dans le PRD)

### 1. **Système de Flashcards avec Répétition Espacée (SM-2)**
**Impact:** MAJEUR ❗

**Nouveau:**
- Algorithme SM-2 adapté
- Auto-évaluation ("Facile", "Moyen", "Difficile", "À revoir")
- Planning de révisions visible (aujourd'hui, demain, 7 jours)
- Intervalles: 1j → 3j → 7j → 14j → 30j → 60j
- Source: questions échouées + marquées manuellement

**BDD requise:**
```sql
-- Nouvelle table
CREATE TABLE flashcards (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER REFERENCES learners(id),
    question_id INTEGER REFERENCES questions(id),
    difficulty_factor DECIMAL(3,2) DEFAULT 2.5, -- EF (SM-2)
    interval_days INTEGER DEFAULT 1,
    repetitions INTEGER DEFAULT 0,
    next_review_date DATE NOT NULL,
    last_reviewed_at TIMESTAMP,
    ease_rating VARCHAR(20), -- 'easy', 'medium', 'hard', 'again'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(learner_id, question_id)
);
```

**Services requis:**
- `FlashcardService->calculateNextReview($flashcard_id, $ease_rating)`
- `FlashcardService->getDueCards($learner_id, $date)`
- `FlashcardService->createFromFailedQuestions($attempt_id)`

---

### 2. **Gamification Complète**
**Impact:** MAJEUR ❗

**Nouveau:**
- **Système de badges** (10 badges définis)
- **Niveaux et XP:**
  - Quiz terminé: 10 XP × % réussite
  - Article lu: 5 XP
  - Flashcard: 2 XP
  - Badge: 50 XP
  - Formule niveau: 100 × N^1.5 XP
- **Série de jours (Streak):**
  - Compteur visible "🔥 12 jours"
  - Bonus XP: +5% à +50%
  - Rappel à 20h si inactif
- **Comparaison anonymisée:**
  - "Top 20% de votre groupe"
  - Pas de classement nominatif

**BDD requise:**
```sql
CREATE TABLE badges (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100),
    description TEXT,
    icon VARCHAR(100),
    condition_type VARCHAR(50),
    condition_value JSONB
);

CREATE TABLE learner_badges (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER REFERENCES learners(id),
    badge_id INTEGER REFERENCES badges(id),
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(learner_id, badge_id)
);

CREATE TABLE learner_xp (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER REFERENCES learners(id) UNIQUE,
    total_xp INTEGER DEFAULT 0,
    current_level INTEGER DEFAULT 1,
    current_streak INTEGER DEFAULT 0,
    longest_streak INTEGER DEFAULT 0,
    last_activity_date DATE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 3. **Mode Lecture Zen**
**Impact:** MOYEN

**Nouveau:**
- Masquage éléments d'interface
- Focus sur contenu
- Barre progression lecture
- Marquage auto à 80% scroll

**Frontend:**
- Bouton toggle "Mode Zen"
- CSS: masquer header/sidebar
- JS: tracking scroll position

---

### 4. **Signalement d'Erreurs**
**Impact:** MOYEN

**Nouveau:**
- Formulaire court (type + commentaire)
- Types: contenu, orthographe, technique
- Transmis au créateur
- Accessible quiz ET articles

**BDD requise:**
```sql
CREATE TABLE error_reports (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER REFERENCES learners(id),
    content_type VARCHAR(50), -- 'quiz' ou 'article'
    content_id INTEGER NOT NULL,
    error_type VARCHAR(50), -- 'content', 'spelling', 'technical'
    comment TEXT,
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'reviewed', 'fixed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 5. **Évaluation par Étoiles (Articles)**
**Impact:** FAIBLE

**Nouveau:**
- Note 1-5 étoiles après lecture
- Moyenne visible par formateur
- Optionnel

**BDD:**
```sql
ALTER TABLE learner_progress ADD COLUMN rating INTEGER CHECK (rating BETWEEN 1 AND 5);
```

---

### 6. **Anti-Capture d'Écran**
**Impact:** MAJEUR (Sécurité) ❗

**Nouveau:**
- Détection capture écran pendant quiz
- Écran blanc 5 secondes
- Enregistrement tentative (date, heure, quiz)
- Notification formateur
- Option: annulation tentative examen

**Frontend:**
```javascript
// Détection capture écran (API limitée, pas 100% fiable)
document.addEventListener('visibilitychange', () => {
    if (document.hidden && isQuizActive) {
        // Probable screenshot
        logScreenshotAttempt();
        showWhiteScreen(5000);
    }
});

// Détection via keyboard (Print Screen)
document.addEventListener('keydown', (e) => {
    if (e.key === 'PrintScreen') {
        e.preventDefault();
        logScreenshotAttempt();
        showWhiteScreen(5000);
    }
});
```

**BDD:**
```sql
CREATE TABLE screenshot_attempts (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER REFERENCES learners(id),
    attempt_id INTEGER REFERENCES quiz_attempts(id),
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address INET,
    user_agent TEXT
);
```

**⚠️ Limitation:** Impossible de bloquer 100% les captures (outils système, caméra externe)

---

### 7. **Chronomètre avec Alertes**
**Impact:** MOYEN

**Nouveau:**
- Alerte visuelle à 50% et 80% du temps
- (Déjà prévu mais précision supplémentaire)

---

### 8. **QCM avec Score Partiel**
**Impact:** MOYEN

**Nouveau:**
- QCM multiple avec points partiels
- Ex: 2/3 bonnes = 2/3 des points
- Option activable par formateur

**Logique:**
```php
// Dans ScoreCalculationService
if ($question->type === 'qcm_multiple' && $quiz->allow_partial_score) {
    $correct_count = count($question->correct_answers);
    $learner_correct = count(array_intersect($learner_answers, $question->correct_answers));
    $score = ($learner_correct / $correct_count) * $question->points;
}
```

---

### 9. **Paramètres Utilisateur Enrichis**
**Impact:** FAIBLE

**Nouveau:**
- Langue interface
- Thème clair/sombre
- Taille police (3 niveaux)
- Son activé/désactivé
- Mode Ne Pas Déranger (22h-8h)
- Heure rappel streak (défaut 20h)

**BDD:**
```sql
CREATE TABLE learner_preferences (
    learner_id INTEGER PRIMARY KEY REFERENCES learners(id),
    locale VARCHAR(10) DEFAULT 'fr',
    theme VARCHAR(20) DEFAULT 'light', -- 'light', 'dark', 'auto'
    font_size VARCHAR(20) DEFAULT 'medium', -- 'small', 'medium', 'large'
    sound_enabled BOOLEAN DEFAULT true,
    notifications_enabled JSONB DEFAULT '{"new_quiz": true, "new_article": true, "streak_reminder": true, "flashcard_due": true, "exam_deadline": true}',
    dnd_start TIME DEFAULT '22:00:00',
    dnd_end TIME DEFAULT '08:00:00',
    streak_reminder_time TIME DEFAULT '20:00:00'
);
```

---

### 10. **Sommaire Auto Article**
**Impact:** FAIBLE

**Nouveau:**
- Génération auto depuis titres H2/H3
- Ancres cliquables
- Navigation rapide

**Frontend:**
```javascript
// Générer sommaire
const headings = document.querySelectorAll('article h2, article h3');
const toc = headings.map(h => ({
    level: h.tagName,
    text: h.textContent,
    id: h.id || generateId(h.textContent)
}));
```

---

## ❌ Fonctionnalités à RETIRER du PRD existant

### 1. **Deadlines avec Blocage**
- Le nouveau CDC ne mentionne PAS de deadlines strictes
- **Action:** Retirer la table `group_deadlines` et tout le système de deadlines

### 2. **Génération de Certificats**
- Le nouveau CDC ne mentionne PAS de certificats
- **Action:** Retirer la table `certificates` et le système de génération PDF

### 3. **Ressources Externes (Vidéos, PDF)**
- Le nouveau CDC ne mentionne PAS de ressources externes séparées
- Les médias sont intégrés dans les articles/quiz
- **Action:** Retirer la table `external_resources`

### 4. **Système de Commentaires/Forum**
- Le CDC précise: "Pas de forum, pas de discussion"
- **Action:** Confirmer aucun système de commentaires

---

## 📊 Tables BDD - Comparaison

### À AJOUTER:
1. ✅ `flashcards` (répétition espacée)
2. ✅ `badges` (gamification)
3. ✅ `learner_badges` (attribution badges)
4. ✅ `learner_xp` (niveaux, XP, streak)
5. ✅ `error_reports` (signalements)
6. ✅ `screenshot_attempts` (anti-triche)
7. ✅ `learner_preferences` (paramètres utilisateur)

### À CONSERVER (déjà prévues):
1. ✅ `quiz_attempts`
2. ✅ `quiz_answers`
3. ✅ `learner_progress`
4. ✅ `notifications`

### À RETIRER:
1. ❌ `certificates`
2. ❌ `external_resources`
3. ❌ `group_resource`
4. ❌ `group_deadlines`

---

## 🎯 Fonctionnalités par Priorité

### P0 - Critiques (MVP)
1. ✅ Authentification
2. ✅ Tableau de bord
3. ✅ Quiz (3 modes: examen, entraînement, flashcard) **NOUVEAU**
4. ✅ Articles (lecture, favoris)
5. ✅ Tracking progression
6. ✅ Mode hors ligne
7. ✅ Profil utilisateur

### P1 - Importantes
1. 🆕 **Flashcards avec SM-2** (révision intelligente)
2. 🆕 **Gamification** (badges, XP, niveaux, streak)
3. 🆕 **Anti-capture écran** (sécurité)
4. ✅ Historique et stats
5. ✅ Notifications push
6. 🆕 Signalement erreurs
7. 🆕 Mode lecture zen

### P2 - Bonus
1. 🆕 Évaluation par étoiles
2. 🆕 Comparaison anonymisée groupe
3. ✅ Thème sombre
4. 🆕 Paramètres utilisateur avancés
5. 🆕 Sommaire auto articles

---

## 📅 Nouveau Planning Estimé

### Phase 1: Setup & Auth (2 sem)
- Authentification
- Profil utilisateur
- Tableau de bord basique

### Phase 2: Quiz Complet (3 sem) ⬆️ +1 sem
- Mode examen
- Mode entraînement
- **Mode flashcard (nouveau)**
- Correction et feedback
- Historique

### Phase 3: Articles (2 sem)
- Lecture responsive
- Favoris
- **Mode lecture zen (nouveau)**
- **Sommaire auto (nouveau)**
- **Signalement erreurs (nouveau)**

### Phase 4: Flashcards & Révision (2 sem) 🆕
- **Algorithme SM-2**
- **Planning révisions**
- **Auto-évaluation**
- **Dashboard révisions**

### Phase 5: Gamification (2 sem) 🆕
- **Système badges**
- **XP et niveaux**
- **Streak tracker**
- **Comparaison groupe**

### Phase 6: Sécurité & Offline (2 sem)
- **Anti-capture écran (nouveau)**
- Service Worker
- Cache stratégies
- Sync silencieuse

### Phase 7: Accessibilité & Paramètres (1 sem)
- WCAG 2.1 AA
- Thème sombre
- **Paramètres enrichis (nouveau)**
- Taille police

### Phase 8: Tests & Polish (2 sem)
- Tests fonctionnels
- Tests performance
- Tests accessibilité
- Documentation

**TOTAL: 16 semaines** (+4 semaines vs. PRD initial)

---

## 🔧 Ajustements Techniques

### Frontend
**À AJOUTER:**
- Module Flashcards (SM-2 algorithm)
- Module Gamification (badges, XP, levels)
- Anti-screenshot detection
- Mode lecture zen (CSS)
- Sommaire auto (parsing headings)
- Système signalement erreurs
- Paramètres utilisateur avancés

### Backend
**À AJOUTER:**
- `FlashcardService` (SM-2 algorithm)
- `GamificationService` (badges, XP calculation)
- `BadgeService` (attribution automatique)
- `ScreenshotDetectionService` (logging)
- `ErrorReportService` (signalements)
- Jobs: `CheckBadgeEligibility`, `CalculateStreak`, `SendStreakReminder`

### API Routes
**À AJOUTER:**
```php
// Flashcards
GET    /api/learner/flashcards/due
POST   /api/learner/flashcards/{id}/review
GET    /api/learner/flashcards/schedule

// Gamification
GET    /api/learner/badges
GET    /api/learner/xp
GET    /api/learner/streak
GET    /api/learner/leaderboard/anonymous

// Signalements
POST   /api/learner/reports
GET    /api/learner/reports

// Préférences
GET    /api/learner/preferences
PUT    /api/learner/preferences
```

---

## ⚠️ Points d'Attention

### 1. Algorithme SM-2
- Implémentation complexe
- Nécessite tests extensifs
- Formules mathématiques précises
- Planning dynamique

### 2. Anti-Capture Écran
- **Limitation technique:** Impossible de bloquer 100%
- API navigateur limitée
- Fonctionne surtout en détection (pas prévention)
- Message clair à l'apprenant au début du quiz

### 3. Gamification
- Calculs XP complexes
- Attribution badges automatique (jobs)
- Streak: vérification quotidienne
- Comparaison anonymisée: requêtes agrégées

### 4. Mode Hors Ligne
- Flashcards: nécessite pre-cache
- Gamification: synchronisation différée
- XP calculé côté serveur

---

## 📝 Documents à Mettre à Jour

1. **PRD_VOLET_APPRENANT.md**
   - Ajouter: Flashcards SM-2 (F15)
   - Ajouter: Gamification complète (F16)
   - Ajouter: Anti-screenshot (F17)
   - Ajouter: Signalement erreurs (F18)
   - Retirer: Certificats
   - Retirer: Deadlines strictes
   - Retirer: Ressources externes

2. **DATABASE_SCHEMA_APPRENANT.md**
   - Ajouter: 7 nouvelles tables
   - Retirer: 4 tables
   - Mettre à jour relations

3. **GUIDE_DEVELOPPEMENT.md**
   - Ajouter Phase 4 (Flashcards)
   - Ajouter Phase 5 (Gamification)
   - Mettre à jour Phase 6 (Sécurité)
   - Ajuster timeline: 16 semaines

4. **EXEMPLES_MIGRATIONS.md**
   - Ajouter migrations flashcards
   - Ajouter migrations gamification
   - Retirer migrations certificats/deadlines

---

## ✅ Conclusion

**Écarts majeurs identifiés:**
1. 🆕 Flashcards avec SM-2 (fonctionnalité critique absente)
2. 🆕 Gamification complète (badges, XP, streak)
3. 🆕 Anti-capture écran (sécurité)
4. ❌ Certificats (à retirer)
5. ❌ Deadlines (à retirer)
6. ❌ Ressources externes (à retirer)

**Impact planning:** +4 semaines (12 → 16 semaines)

**Prochaines actions:**
1. Valider ces écarts avec le client
2. Mettre à jour les 4 documents principaux
3. Créer nouvelles migrations
4. Ajuster le planning

---

**Créé le:** 21 juin 2026, 21:15 UTC  
**Statut:** ⚠️ Analyse à valider
