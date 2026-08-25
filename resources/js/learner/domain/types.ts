/**
 * Types partagés du volet apprenant — reflètent les payloads de l'API v1
 * (LearnerContentService / ActionController / ExamAttemptController).
 */

export interface XpSnapshot {
  total_xp: number;
  current_level: number;
  current_streak: number;
  longest_streak: number;
  last_activity_date: string | null;
}

export interface UserProfile {
  id: number;
  name: string;
  last_name: string;
  full_name: string;
  email: string;
  avatar_url: string;
  matricule?: string | null;
  xp: XpSnapshot;
}

export interface ArticleItem {
  id: number;
  title: string;
  category: string | null;
  content: string | null;
  estimated_reading_time: number | null;
  is_favorite: boolean;
  rating: number;
  status: string;
  progress_percentage: number;
  created_at: string;
  updated_at: string;
}

export interface QuizQuestion {
  id: number;
  question_text: string;
  type: string;
  points: number;
  order: number;
  options: Record<string, unknown>;
}

export interface QuizAttemptSummary {
  id: number;
  attempt_number: number;
  status: string;
  score: number | null;
  points_earned: number | null;
  points_total: number | null;
  passed: boolean;
  completed_at: string | null;
}

export interface QuizItem {
  id: number;
  title: string;
  description: string | null;
  duration: number | null;
  passing_score: number;
  max_attempts: number;
  shuffle_questions: boolean;
  show_correct_answers: boolean;
  status: 'unread' | 'in_progress' | 'completed';
  max_attempts_reached: boolean;
  updated_at: string;
  attempts: QuizAttemptSummary[];
  questions: QuizQuestion[];
}

export interface CardReviewState {
  easiness_factor: number;
  interval_days: number;
  repetitions: number;
  last_reviewed: string | null;
  next_review: string | null;
  status: string;
}

export interface DeckCard {
  id: number;
  recto: string;
  verso: string;
  recto_media: unknown;
  verso_media: unknown;
  tags: string | null;
  ordre: number;
  review: CardReviewState | null;
}

export interface DeckItem {
  id: number;
  titre: string;
  description: string | null;
  matiere: string | null;
  algorithme: 'sm2' | 'leitner';
  easiness_default: number;
  interval_min: number;
  interval_max: number;
  updated_at: string;
  cards: DeckCard[];
}

export interface ExamAttemptSummary {
  id: number;
  date_debut: string | null;
  date_fin: string | null;
  score_brut: number | null;
  score_total: number | null;
  note_sur_vingt: number | null;
  pourcentage: number | null;
  status: string;
  capture_attempts: number;
  navigation_violations: number;
}

export interface ExamItem {
  id: number;
  title: string;
  description: string | null;
  duration: number;
  passing_score: number;
  note_max: number;
  max_attempts: number;
  available_from: string | null;
  available_until: string | null;
  plein_ecran_force: boolean;
  anti_capture_strict: boolean;
  navigation_interdite: boolean;
  publication_resultats: string;
  classement_visible: boolean;
  classement_anonyme: boolean;
  status: 'locked' | 'available' | 'expired' | 'in_progress' | 'completed';
  max_attempts_reached: boolean;
  updated_at: string;
  attempts: ExamAttemptSummary[];
}

export interface BadgeItem {
  id: number;
  name: string;
  description: string;
  icon: string;
  unlocked: boolean;
}

export interface Preferences {
  locale: 'fr' | 'en';
  theme: 'light' | 'dark';
  font_size: 'small' | 'medium' | 'large';
  sound_enabled: boolean;
  notifications_enabled: Record<string, boolean> | null;
}

export interface BootstrapPayload {
  cursor: string;
  user: UserProfile;
  articles: ArticleItem[];
  quizzes: QuizItem[];
  decks: DeckItem[];
  exams: ExamItem[];
  badges: BadgeItem[];
  preferences: Preferences;
}

export interface CollectionDelta<T> {
  updated: T[];
  authorized_ids: number[];
}

export interface ChangesPayload {
  cursor: string;
  articles: CollectionDelta<ArticleItem>;
  quizzes: CollectionDelta<QuizItem>;
  decks: CollectionDelta<DeckItem>;
  exams: CollectionDelta<ExamItem>;
  badges: BadgeItem[];
  xp: XpSnapshot;
}

// ---------------------------------------------------------------- Actions

export type ActionType =
  | 'article_progress'
  | 'article_favorite'
  | 'article_rate'
  | 'article_error_report'
  | 'quiz_attempt'
  | 'quiz_error_report'
  | 'card_review'
  | 'review_session'
  | 'preferences_update';

export interface OutboxAction {
  /** auto-incrément Dexie */
  seq?: number;
  id: string; // client_action_id (uuid)
  type: ActionType;
  data: Record<string, unknown>;
  created_at: string;
  /** null = en attente ; sinon dernier échec renvoyé par le serveur */
  error: string | null;
}

export interface ActionResult {
  id: string;
  status: 'applied' | 'duplicate' | 'rejected';
  result?: Record<string, unknown> | null;
  message?: string;
}

export interface ActionsResponse {
  success: boolean;
  results: ActionResult[];
  xp: XpSnapshot;
  badges_unlocked: string[];
}

// ------------------------------------------------------------ Progression

export interface LeaderboardRow {
  rank: number;
  name: string;
  total_xp: number;
  current_level: number;
  current_streak: number;
  is_me: boolean;
}

export interface LeaderboardGroup {
  group_id: number;
  group_name: string;
  total_participants: number;
  my_rank: number | null;
  rows: LeaderboardRow[];
}

/** Brouillon local d'un quiz en cours (reprise après fermeture). */
export interface QuizDraft {
  quiz_id: number;
  answers: Record<number, unknown>;
  question_order: number[];
  index: number;
  started_at: string;
  remaining_seconds: number | null;
  updated_at: string;
}

/** Question ratée récemment — alimente « Rejouer mes erreurs ». */
export interface MistakeEntry {
  question_id: number;
  quiz_id: number;
  last_wrong_at: string;
}

// ------------------------------------------------------------------ Exams

export interface ExamStartResponse {
  success: boolean;
  attempt_id: number;
  questions: QuizQuestion[];
  elapsed_seconds: number;
  remaining_seconds: number;
  plein_ecran_force: boolean;
  anti_capture_strict: boolean;
  navigation_interdite: boolean;
}

export interface ExamResultResponse {
  success: boolean;
  time_up?: boolean;
  score_brut: number;
  score_total: number;
  pourcentage: number;
  note_sur_vingt: number;
  passed: boolean;
  status: string;
  rank: number | null;
  total_participants: number;
}
