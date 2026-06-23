# Schéma Base de Données - Volet Apprenant (Learn&Quiz)

**Date:** 23 juin 2026  
**Version:** 2.0 (Mis à jour selon le nouveau Cahier des Charges)

---

## Tables créées pour le volet apprenant

### 1. quiz_attempts
**Description:** Enregistre chaque tentative de quiz par un apprenant.

```sql
CREATE TABLE quiz_attempts (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    score DECIMAL(5,2) NULL, -- Score en pourcentage (0-100)
    points_earned INTEGER NULL, -- Points totaux obtenus
    points_total INTEGER NULL, -- Points totaux possibles
    passed BOOLEAN NULL, -- TRUE si score >= passing_score
    time_spent INTEGER NULL, -- Temps en secondes
    answers JSONB NULL, -- Réponses données par question (facilite le hors ligne et l'historique rapide)
    attempt_number INTEGER NOT NULL DEFAULT 1, -- Numéro de la tentative
    status VARCHAR(20) DEFAULT 'in_progress', -- 'in_progress', 'completed', 'abandoned'
    ip_address INET NULL, -- Adresse IP pour tracking/sécurité
    user_agent TEXT NULL, -- User agent
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_learner_quiz ON quiz_attempts(learner_id, quiz_id);
CREATE INDEX idx_status ON quiz_attempts(status);
CREATE INDEX idx_completed ON quiz_attempts(completed_at);
CREATE INDEX idx_attempts_composite ON quiz_attempts(learner_id, quiz_id, completed_at);
```

---

### 2. quiz_answers
**Description:** Détail des réponses individuelles par question pour chaque tentative (complémentaire au JSONB de `quiz_attempts` pour le reporting détaillé et les flashcards).

```sql
CREATE TABLE quiz_answers (
    id SERIAL PRIMARY KEY,
    attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
    answer_given TEXT NULL, -- Réponse donnée par l'apprenant
    correct_answer TEXT NULL, -- Bonne réponse au moment de la tentative
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    points_earned INTEGER NOT NULL DEFAULT 0,
    answered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE (attempt_id, question_id)
);

CREATE INDEX idx_attempt ON quiz_answers(attempt_id);
CREATE INDEX idx_question ON quiz_answers(question_id);
CREATE INDEX idx_is_correct ON quiz_answers(is_correct);
```

---

### 3. learner_progress
**Description:** Suivi de progression dans les contenus pédagogiques (articles, quiz). Gère également les favoris et les évaluations par étoiles des articles.

```sql
CREATE TABLE learner_progress (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    content_type VARCHAR(50) NOT NULL, -- 'article', 'quiz'
    content_id INTEGER NOT NULL, -- ID de l'article ou du quiz
    status VARCHAR(20) NOT NULL DEFAULT 'not_started', -- 'not_started', 'in_progress', 'completed'
    progress_percentage INTEGER DEFAULT 0, -- 0-100
    time_spent INTEGER DEFAULT 0, -- Temps en secondes
    rating INTEGER NULL, -- Note de 1 à 5 étoiles (uniquement pour les articles, optionnel)
    is_favorite BOOLEAN DEFAULT FALSE, -- Statut favori
    last_accessed_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    metadata JSONB NULL, -- Données additionnelles (scroll position, bookmarks, etc.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE (learner_id, content_type, content_id),
    CONSTRAINT chk_progress CHECK (progress_percentage >= 0 AND progress_percentage <= 100),
    CONSTRAINT chk_rating CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5))
);

CREATE INDEX idx_learner ON learner_progress(learner_id);
CREATE INDEX idx_content ON learner_progress(content_type, content_id);
CREATE INDEX idx_completed_progress ON learner_progress(completed_at);
CREATE INDEX idx_learner_status ON learner_progress(learner_id, status);
```

---

### 4. notifications
**Description:** Notifications système in-app pour l'apprenant.

```sql
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'new_quiz', 'new_article', 'streak_reminder', 'flashcard_due', 'exam_deadline'
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url TEXT NULL, -- Lien interne ou ancre
    icon VARCHAR(100) NULL, -- Icône (ex: 'fa-bell', 'fa-check-circle')
    priority VARCHAR(20) DEFAULT 'normal', -- 'low', 'normal', 'high', 'urgent'
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    metadata JSONB NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_user ON notifications(user_id);
CREATE INDEX idx_type ON notifications(type);
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read, created_at DESC);
```

---

### 5. flashcards
**Description:** Système de révision espacée intelligente basé sur l'algorithme SuperMemo-2 (SM-2).

```sql
CREATE TABLE flashcards (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
    difficulty_factor DECIMAL(3,2) DEFAULT 2.50, -- EF (SuperMemo-2 Easiness Factor)
    interval_days INTEGER DEFAULT 1, -- Prochain intervalle de révision en jours
    repetitions INTEGER DEFAULT 0, -- Nombre de répétitions consécutives réussies
    next_review_date DATE NOT NULL, -- Date de la prochaine révision planifiée
    last_reviewed_at TIMESTAMP NULL,
    ease_rating VARCHAR(20) NULL, -- 'easy', 'medium', 'hard', 'again'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (learner_id, question_id)
);

CREATE INDEX idx_flashcard_learner ON flashcards(learner_id);
CREATE INDEX idx_flashcard_review ON flashcards(next_review_date);
```

---

### 6. badges
**Description:** Définition des badges disponibles pour la gamification.

```sql
CREATE TABLE badges (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL, -- Identifiant unique (ex: 'first_step', 'perfect_score')
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(100) NOT NULL, -- Icône ou emoji (ex: '🚀', '🎯')
    condition_type VARCHAR(50) NOT NULL, -- Type de règle (ex: 'quiz_completed', 'articles_read')
    condition_value JSONB NULL, -- Valeurs associées à la condition (ex: {"count": 10})
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 7. learner_badges
**Description:** Table de liaison des badges obtenus par chaque apprenant.

```sql
CREATE TABLE learner_badges (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    badge_id INTEGER NOT NULL REFERENCES badges(id) ON DELETE CASCADE,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (learner_id, badge_id)
);
```

---

### 8. learner_xp
**Description:** Suivi d'expérience (XP), de niveau, et de série de jours consécutifs (streak) de l'apprenant.

```sql
CREATE TABLE learner_xp (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL UNIQUE REFERENCES learners(id) ON DELETE CASCADE,
    total_xp INTEGER DEFAULT 0, -- Somme de tous les XP
    current_level INTEGER DEFAULT 1, -- Niveau calculé par la formule : Niveau N = 100 * N^1.5 XP
    current_streak INTEGER DEFAULT 0, -- Série actuelle de jours actifs consécutifs
    longest_streak INTEGER DEFAULT 0, -- Record historique de jours actifs
    last_activity_date DATE NULL, -- Dernière date d'activité validée pour le streak
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 9. error_reports
**Description:** Signalements d'erreurs rédigés par les apprenants sur les quiz ou les articles.

```sql
CREATE TABLE error_reports (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    content_type VARCHAR(50) NOT NULL, -- 'quiz' ou 'article' (polymorphique)
    content_id INTEGER NOT NULL,
    error_type VARCHAR(50) NOT NULL, -- 'content', 'spelling', 'technical'
    comment TEXT NULL, -- Commentaire optionnel
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'resolved', 'ignored'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_report_content ON error_reports(content_type, content_id);
CREATE INDEX idx_report_status ON error_reports(status);
```

---

### 10. screenshot_attempts
**Description:** Log de sécurité enregistrant les tentatives de capture d'écran détectées pendant les quiz (examen/entraînement).

```sql
CREATE TABLE screenshot_attempts (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address INET NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 11. learner_preferences
**Description:** Paramètres personnels de l'apprenant (thème, langue, son, notifications).

```sql
CREATE TABLE learner_preferences (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL UNIQUE REFERENCES learners(id) ON DELETE CASCADE,
    locale VARCHAR(10) DEFAULT 'fr',
    theme VARCHAR(20) DEFAULT 'light', -- 'light', 'dark', 'auto'
    font_size VARCHAR(20) DEFAULT 'medium', -- 'small', 'medium', 'large'
    sound_enabled BOOLEAN DEFAULT TRUE,
    notifications_enabled JSONB NULL, -- Configuration fine (ex: {"new_quiz": true, "streak_reminder": false})
    streak_reminder_time TIME DEFAULT '20:00:00',
    dnd_start TIME DEFAULT '22:00:00',
    dnd_end TIME DEFAULT '08:00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Modifications apportées aux tables existantes

### Quizzes
Les champs suivants ont été ajoutés pour s'adapter au volet apprenant :
*   `max_attempts` (unsigned smallint, default 3) : Nombre max de tentatives.
*   `show_correct_answers` (boolean, default true) : Afficher les réponses correctes à la fin du quiz.
*   `allow_partial_score` (boolean, default false) : Activer le barème partiel pour les QCM multiples.
*   `available_from` (timestamp, nullable) : Date de début d'accès.
*   `available_until` (timestamp, nullable) : Échéance d'accès.

### Articles
Les champs suivants ont été ajoutés :
*   `estimated_reading_time` (unsigned smallint, nullable) : Temps de lecture estimé en minutes.
*   `available_from` (timestamp, nullable) : Date de publication d'accès.
*   `available_until` (timestamp, nullable) : Date de fin de visibilité.

---

## Intégrité & Audit
1. **Polymorphisme :** Les tables `learner_progress` et `error_reports` utilisent le Morph Map Laravel enregistré globalement :
   *   `'article'` => `Modules\Core\Models\Article`
   *   `'quiz'` => `Modules\Core\Models\Quiz`
2. **Contrainte Active :** Pour garantir la cohérence des tentatives de quiz en cours, une contrainte d'unicité partielle est recommandée dans l'application ou l'index PostgreSQL pour interdire plus d'une tentative au statut `'in_progress'` par quiz et par apprenant simultanément.
