/**
 * Session invité du mode découverte : aucun compte, aucune synchronisation.
 *
 * Le contenu vitrine (`/api/showcase/v1/content`) est rangé dans les tables
 * habituelles pour que les vues fonctionnent sans changement ; la progression
 * et l'XP restent strictement locales (l'outbox n'est jamais alimentée).
 */

import { api } from '../api/client';
import { pendingCount } from '../db/outbox';
import { db, getMeta, setMeta, META_TERMS } from '../db/schema';
import type {
  ArticleItem,
  DeckItem,
  QuizItem,
  ShowcaseContent,
  ShowcaseSettings,
  UserProfile,
} from '../domain/types';
import { sessionStore } from '../stores';
import { clearLocalData, hydrateFromDb } from '../sync/engine';

export const META_SHOWCASE_SETTINGS = 'showcase-settings';

/**
 * Refus d'ouvrir le mode découverte : des actions d'un apprenant attendent
 * encore d'être envoyées (ex. après une session expirée). Les purger les
 * perdrait définitivement.
 */
export class PendingOutboxError extends Error {
  constructor() {
    super('Vous avez des activités non synchronisées : reconnectez-vous d’abord pour les envoyer.');
    this.name = 'PendingOutboxError';
  }
}

const GUEST_PROFILE: UserProfile = {
  id: 0,
  name: 'Invité',
  last_name: '',
  full_name: 'Invité',
  email: '',
  avatar_url: '',
  matricule: null,
  is_guest: true,
  xp: {
    total_xp: 0,
    current_level: 0,
    current_streak: 0,
    longest_streak: 0,
    last_activity_date: null,
  },
};

let settings: ShowcaseSettings | null = null;

export function isGuest(): boolean {
  return sessionStore.get()?.is_guest === true;
}

export function guestSettings(): ShowcaseSettings | null {
  return isGuest() ? settings : null;
}

/** Recharge les réglages vitrine depuis IndexedDB (démarrage hors-ligne). */
export async function loadGuestSettings(): Promise<ShowcaseSettings | null> {
  settings = (await getMeta<ShowcaseSettings>(META_SHOWCASE_SETTINGS)) ?? null;
  return settings;
}

function stripSuccess<T extends { success?: boolean }>(payload: T): Omit<T, 'success'> {
  const { success: _success, ...rest } = payload;
  return rest;
}

/** Ouvre une session invité : vitrine + réglages, profil « Invité ». */
export async function enterGuestMode(): Promise<void> {
  if ((await pendingCount()) > 0) throw new PendingOutboxError();

  const [content, rawSettings] = await Promise.all([api.showcaseContent(), api.showcaseSettings()]);
  const showcaseSettings = stripSuccess(rawSettings) as ShowcaseSettings;

  // Aucune donnée d'une session précédente ne doit survivre.
  await clearLocalData();

  await db.transaction('rw', [db.profile, db.articles, db.quizzes, db.decks, db.meta], async () => {
    await db.profile.put({ ...GUEST_PROFILE, _key: 'me' });
    await db.articles.bulkPut(content.articles);
    await db.quizzes.bulkPut(content.quizzes);
    await db.decks.bulkPut(content.decks);
    await setMeta(META_SHOWCASE_SETTINGS, showcaseSettings);
    if (showcaseSettings.terms) await setMeta(META_TERMS, showcaseSettings.terms);
  });

  settings = showcaseSettings;
  await hydrateFromDb();
}

// ------------------------------------------------ Fusion de la progression

function mergeArticle(fresh: ArticleItem, local: ArticleItem | undefined): ArticleItem {
  if (!local) return fresh;
  return {
    ...fresh,
    is_favorite: local.is_favorite,
    rating: local.rating,
    status: local.status,
    progress_percentage: local.progress_percentage,
  };
}

function mergeQuiz(fresh: QuizItem, local: QuizItem | undefined): QuizItem {
  if (!local) return fresh;
  return {
    ...fresh,
    status: local.status,
    attempts: local.attempts,
    max_attempts_reached: local.max_attempts_reached,
  };
}

function mergeDeck(fresh: DeckItem, local: DeckItem | undefined): DeckItem {
  if (!local) return fresh;
  const reviews = new Map(local.cards.map((c) => [c.id, c.review]));
  return {
    ...fresh,
    cards: fresh.cards.map((c) => ({ ...c, review: reviews.get(c.id) ?? c.review })),
  };
}

function mergeById<T extends { id: number }>(
  fresh: T[],
  local: T[],
  merge: (f: T, l: T | undefined) => T,
): T[] {
  const byId = new Map(local.map((item) => [item.id, item]));
  return fresh.map((item) => merge(item, byId.get(item.id)));
}

/**
 * Remplace articles, quiz et decks par la vitrine du serveur (renouvelle
 * aussi le jeton invité) ; conserve l'XP et la progression locales.
 */
export async function refreshGuestContent(): Promise<void> {
  const content: ShowcaseContent = await api.showcaseContent();

  await db.transaction('rw', [db.profile, db.articles, db.quizzes, db.decks], async () => {
    const [articles, quizzes, decks] = await Promise.all([
      db.articles.toArray(),
      db.quizzes.toArray(),
      db.decks.toArray(),
    ]);

    await db.articles.clear();
    await db.articles.bulkPut(mergeById(content.articles, articles, mergeArticle));
    await db.quizzes.clear();
    await db.quizzes.bulkPut(mergeById(content.quizzes, quizzes, mergeQuiz));
    await db.decks.clear();
    await db.decks.bulkPut(mergeById(content.decks, decks, mergeDeck));

    // L'XP gagné localement (store) prime : il n'existe nulle part ailleurs.
    const current = sessionStore.get();
    if (current?.is_guest) await db.profile.put({ ...current, _key: 'me' });
  });

  await hydrateFromDb();
}

/** Ferme la session invité : purge locale complète puis retour à la connexion. */
export async function leaveGuestMode(): Promise<void> {
  await clearLocalData();
  settings = null;
  window.dispatchEvent(new CustomEvent('learner:logged-out'));
}
