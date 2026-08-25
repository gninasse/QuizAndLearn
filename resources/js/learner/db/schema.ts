import Dexie, { type Table } from 'dexie';
import type {
  ArticleItem,
  BadgeItem,
  DeckItem,
  ExamItem,
  MistakeEntry,
  OutboxAction,
  Preferences,
  QuizDraft,
  QuizItem,
  UserProfile,
} from '../domain/types';

/**
 * Base locale du volet apprenant — source de vérité hors-ligne.
 *
 * Nouvelle base (`LearnQuizDB-v2026`), indépendante de l'ancienne
 * `LearnQuizDB` du SPA legacy pour permettre une bascule sans migration.
 */

interface MetaRow {
  key: string;
  value: unknown;
}

export class LearnerDb extends Dexie {
  profile!: Table<UserProfile & { _key: string }, string>;

  preferences!: Table<Preferences & { _key: string }, string>;

  articles!: Table<ArticleItem, number>;

  quizzes!: Table<QuizItem, number>;

  decks!: Table<DeckItem, number>;

  exams!: Table<ExamItem, number>;

  badges!: Table<BadgeItem, number>;

  outbox!: Table<OutboxAction, number>;

  meta!: Table<MetaRow, string>;

  /** Brouillons de quiz en cours (reprise après fermeture). */
  drafts!: Table<QuizDraft, number>;

  /** Questions ratées récemment (mode « rejouer mes erreurs »). */
  mistakes!: Table<MistakeEntry, number>;

  constructor() {
    super('LearnQuizDB-v2026');

    this.version(1).stores({
      profile: '_key',
      preferences: '_key',
      articles: 'id, updated_at',
      quizzes: 'id, updated_at',
      decks: 'id, updated_at',
      exams: 'id, updated_at',
      badges: 'id',
      outbox: '++seq, id, type',
      meta: 'key',
    });

    this.version(2).stores({
      drafts: 'quiz_id',
      mistakes: 'question_id, quiz_id',
    });
  }
}

export const db = new LearnerDb();

// ------------------------------------------------------------------ Meta

export async function getMeta<T>(key: string): Promise<T | undefined> {
  const row = await db.meta.get(key);
  return row?.value as T | undefined;
}

export async function setMeta(key: string, value: unknown): Promise<void> {
  await db.meta.put({ key, value });
}

export const META_CURSOR = 'sync_cursor';
