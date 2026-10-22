import Dexie, { type Table } from 'dexie';
import { consultationDbName } from '../consultation/consultation';
import type {
  ArticleItem,
  GroupInfo,
  BadgeItem,
  DeckItem,
  ExamItem,
  Folder,
  MistakeEntry,
  OutboxAction,
  Placement,
  Preferences,
  QuizDraft,
  QuizItem,
  Resource,
  UserProfile,
} from '../domain/types';

/**
 * Base locale du volet apprenant — source de vérité hors-ligne.
 *
 * Nouvelle base (`LearnQuizDB-v2026`), indépendante de l'ancienne
 * `LearnQuizDB` du SPA legacy pour permettre une bascule sans migration.
 *
 * En consultation « Voir en tant que », le nom est choisi à l'import
 * (`LearnQuizDB-v2026-consultation`) : la base d'un vrai apprenant sur ce
 * navigateur n'est jamais ouverte.
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

  groups!: Table<GroupInfo, number>;

  outbox!: Table<OutboxAction, number>;

  meta!: Table<MetaRow, string>;

  /** Brouillons de quiz en cours (reprise après fermeture). */
  drafts!: Table<QuizDraft, number>;

  /** Questions ratées récemment (mode « rejouer mes erreurs »). */
  mistakes!: Table<MistakeEntry, number>;

  /** Dossiers visibles des groupes de l'apprenant (remplacés à chaque sync). */
  folders!: Table<Folder, number>;

  /** Ressources (vidéos, documents, liens). */
  resources!: Table<Resource, number>;

  /** Place de chaque contenu dans chaque groupe (remplacées à chaque sync). */
  placements!: Table<Placement, [string, number, number]>;

  constructor() {
    super(consultationDbName());

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

    this.version(3).stores({
      groups: 'id',
    });

    this.version(4).stores({
      folders: 'id, group_id, parent_id',
      resources: 'id, updated_at',
      placements: '[type+id+group_id], group_id, folder_id',
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
/** Libellés du terme « groupe » (voir core/terms.ts). */
export const META_TERMS = 'terms';
/** Ids des ressources déjà ouvertes sur cet appareil (progression locale). */
export const META_CONSULTED_RESOURCES = 'consulted_resources';
