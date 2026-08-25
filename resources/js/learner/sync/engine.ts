/**
 * Moteur de synchronisation : bootstrap complet, deltas incrémentaux,
 * rejeu de l'outbox, Badging API et rafraîchissement des stores.
 *
 * Priorité des déclencheurs : Background Sync (via le SW) quand disponible,
 * sinon événements `online` / `visibilitychange` + après chaque enqueue.
 */

import { api, ApiError, NetworkError } from '../api/client';
import { db, getMeta, setMeta, META_CURSOR } from '../db/schema';
import * as outbox from '../db/outbox';
import type { ActionType, BootstrapPayload, ChangesPayload, CollectionDelta } from '../domain/types';
import {
  articlesStore,
  badgesStore,
  decksStore,
  examsStore,
  preferencesStore,
  quizzesStore,
  sessionStore,
  syncStore,
} from '../stores';

export type SyncEvent =
  | { kind: 'badges-unlocked'; names: string[] }
  | { kind: 'action-rejected'; type: ActionType; message: string }
  | { kind: 'session-expired' }
  /** Les stores viennent d'être réhydratés après une sync (stale-while-revalidate). */
  | { kind: 'data-refreshed' };

type SyncListener = (event: SyncEvent) => void;

const listeners = new Set<SyncListener>();

export function onSyncEvent(listener: SyncListener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function emit(event: SyncEvent): void {
  listeners.forEach((l) => l(event));
}

// ------------------------------------------------------------ Hydratation

/** Charge les stores depuis IndexedDB (démarrage, y compris hors-ligne). */
export async function hydrateFromDb(): Promise<boolean> {
  const profile = await db.profile.get('me');
  if (!profile) return false;

  sessionStore.set(profile);
  preferencesStore.set((await db.preferences.get('me')) ?? preferencesStore.get());
  articlesStore.set(await db.articles.toArray());
  quizzesStore.set(await db.quizzes.orderBy('id').toArray());
  decksStore.set(await db.decks.toArray());
  examsStore.set(await db.exams.toArray());
  badgesStore.set(await db.badges.toArray());
  await refreshPendingCount();

  return true;
}

async function persistBootstrap(payload: BootstrapPayload): Promise<void> {
  await db.transaction(
    'rw',
    [db.profile, db.preferences, db.articles, db.quizzes, db.decks, db.exams, db.badges, db.meta],
    async () => {
      await db.profile.put({ ...payload.user, _key: 'me' });
      await db.preferences.put({ ...payload.preferences, _key: 'me' });

      await db.articles.clear();
      await db.articles.bulkPut(payload.articles);
      await db.quizzes.clear();
      await db.quizzes.bulkPut(payload.quizzes);
      await db.decks.clear();
      await db.decks.bulkPut(payload.decks);
      await db.exams.clear();
      await db.exams.bulkPut(payload.exams);
      await db.badges.clear();
      await db.badges.bulkPut(payload.badges);
      await setMeta(META_CURSOR, payload.cursor);
    },
  );
}

async function applyChanges(delta: ChangesPayload): Promise<void> {
  await db.transaction(
    'rw',
    [db.articles, db.quizzes, db.decks, db.exams, db.badges, db.profile, db.meta],
    async () => {
      const apply = async <T>(
        table: { bulkPut(items: T[]): Promise<unknown>; where(index: string): { noneOf(keys: number[]): { delete(): Promise<number> } } },
        d: CollectionDelta<T>,
      ): Promise<void> => {
        if (d.updated.length) {
          await table.bulkPut(d.updated);
        }
        // Supprime tout ce qui n'est plus autorisé (désassignation, désactivation).
        await table.where('id').noneOf(d.authorized_ids).delete();
      };

      await apply(db.articles, delta.articles);
      await apply(db.quizzes, delta.quizzes);
      await apply(db.decks, delta.decks);
      await apply(db.exams, delta.exams);

      await db.badges.clear();
      await db.badges.bulkPut(delta.badges);

      const profile = await db.profile.get('me');
      if (profile) {
        profile.xp = delta.xp;
        await db.profile.put(profile);
      }

      await setMeta(META_CURSOR, delta.cursor);
    },
  );
}

// ------------------------------------------------------ Warm cache médias

/** Chemins locaux considérés comme médias pédagogiques cacheables. */
const MEDIA_PATH_PREFIXES = ['/storage/', '/uploads/'];

export function extractMediaUrls(htmlFragments: (string | null | undefined)[]): string[] {
  const urls = new Set<string>();
  const attrRegex = /(?:src|href)\s*=\s*["']([^"']+)["']/gi;

  for (const fragment of htmlFragments) {
    if (!fragment) continue;
    for (const match of fragment.matchAll(attrRegex)) {
      const rawUrl = match[1] ?? '';
      try {
        const url = new URL(rawUrl, window.location.origin);
        if (
          url.origin === window.location.origin &&
          MEDIA_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
        ) {
          urls.add(url.pathname);
        }
      } catch {
        /* URL invalide : ignorée */
      }
    }
  }

  return [...urls];
}

const warmedUrls = new Set<string>();

/**
 * Pré-télécharge les médias référencés par le contenu pédagogique afin que
 * le service worker (CacheFirst sur /storage/) les garde pour le hors-ligne —
 * un article jamais ouvert s'affiche ainsi complet sans réseau.
 */
async function warmMediaCache(): Promise<void> {
  if (!navigator.onLine || !('serviceWorker' in navigator)) return;

  const fragments: (string | null | undefined)[] = [
    ...articlesStore.get().map((a) => a.content),
    ...quizzesStore.get().flatMap((q) => q.questions.map((question) => question.question_text)),
    ...decksStore.get().flatMap((d) => d.cards.flatMap((c) => [c.recto, c.verso])),
  ];

  const pending = extractMediaUrls(fragments).filter((u) => !warmedUrls.has(u));
  if (!pending.length) return;

  // Fetch séquentiel par petits lots : le SW met en cache au passage.
  const BATCH = 4;
  for (let i = 0; i < pending.length; i += BATCH) {
    await Promise.allSettled(
      pending.slice(i, i + BATCH).map(async (path) => {
        const response = await fetch(path, { credentials: 'same-origin' });
        if (response.ok) warmedUrls.add(path);
      }),
    );
  }
}

// ---------------------------------------------------------------- Badging

async function updateAppBadge(count: number): Promise<void> {
  try {
    if (count > 0) {
      await navigator.setAppBadge?.(count);
    } else {
      await navigator.clearAppBadge?.();
    }
  } catch {
    /* Badging non supporté : silencieux */
  }
}

async function refreshPendingCount(): Promise<number> {
  const count = await outbox.pendingCount();
  syncStore.update((s) => ({ ...s, pendingActions: count }));
  await updateAppBadge(count);
  return count;
}

// -------------------------------------------------------------- Sync flow

let syncInFlight: Promise<void> | null = null;

/** Rejoue l'outbox puis récupère le delta serveur. Idempotent et réentrant. */
export function sync(): Promise<void> {
  if (syncInFlight) return syncInFlight;

  syncInFlight = doSync().finally(() => {
    syncInFlight = null;
  });

  return syncInFlight;
}

async function doSync(): Promise<void> {
  if (!navigator.onLine || !sessionStore.get()) return;

  syncStore.update((s) => ({ ...s, syncing: true }));

  try {
    // 1. Rejeu de l'outbox (actions en attente, y compris en échec réseau).
    const actions = await outbox.pending();
    if (actions.length) {
      const response = await api.actions(actions);

      const done: string[] = [];
      for (const result of response.results) {
        if (result.status === 'applied' || result.status === 'duplicate') {
          done.push(result.id);
        } else {
          // Rejet métier définitif : on retire l'action et on prévient l'UI —
          // la rejouer produirait le même refus à l'infini.
          done.push(result.id);
          const original = actions.find((a) => a.id === result.id);
          emit({
            kind: 'action-rejected',
            type: original?.type ?? 'article_progress',
            message: result.message ?? 'Action refusée par le serveur.',
          });
        }
      }
      await outbox.remove(done);

      if (response.badges_unlocked.length) {
        emit({ kind: 'badges-unlocked', names: response.badges_unlocked });
      }

      // XP à jour renvoyé par le serveur.
      const profile = sessionStore.get();
      if (profile) {
        const updated = { ...profile, xp: { ...profile.xp, ...response.xp } };
        sessionStore.set(updated);
        await db.profile.put({ ...updated, _key: 'me' });
      }
    }

    // 2. Delta serveur depuis le dernier cursor (ou bootstrap complet).
    const cursor = await getMeta<string>(META_CURSOR);
    if (cursor) {
      const delta = await api.changes(cursor);
      await applyChanges(delta);
    } else {
      const payload = await api.bootstrap();
      await persistBootstrap(payload);
    }

    await hydrateFromDb();
    syncStore.update((s) => ({ ...s, lastSyncAt: new Date().toISOString() }));
    emit({ kind: 'data-refreshed' });
    void warmMediaCache();
  } catch (e) {
    if (e instanceof ApiError && (e.status === 401 || e.status === 419)) {
      emit({ kind: 'session-expired' });
    } else if (!(e instanceof NetworkError)) {
      console.error('[sync]', e);
    }
    // NetworkError : silencieux, on retentera au prochain déclencheur.
  } finally {
    syncStore.update((s) => ({ ...s, syncing: false }));
    await refreshPendingCount();
  }
}

/** Premier chargement en ligne : bootstrap complet. */
export async function fullBootstrap(): Promise<void> {
  const payload = await api.bootstrap();
  await persistBootstrap(payload);
  await hydrateFromDb();
  void warmMediaCache();
}

// -------------------------------------------------------- File d'actions

/**
 * Journalise une action et tente une sync immédiate.
 * Enregistre aussi une Background Sync pour rejouer app fermée.
 */
export async function dispatch(type: ActionType, data: Record<string, unknown>): Promise<void> {
  await outbox.enqueue(type, data);
  await refreshPendingCount();
  void requestBackgroundSync();
  void sync();
}

const BG_SYNC_TAG = 'learner-outbox';

async function requestBackgroundSync(): Promise<void> {
  try {
    const registration = await navigator.serviceWorker?.ready;
    // @ts-expect-error SyncManager absent des libs TS standard
    await registration?.sync?.register(BG_SYNC_TAG);
  } catch {
    /* Background Sync non supporté : les événements online/visible suffisent */
  }
}

// ------------------------------------------------------------ Écouteurs

export function installSyncTriggers(): void {
  window.addEventListener('online', () => {
    syncStore.update((s) => ({ ...s, online: true }));
    void sync();
  });

  window.addEventListener('offline', () => {
    syncStore.update((s) => ({ ...s, online: false }));
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) void sync();
  });

  // Le SW notifie quand la Background Sync s'est déclenchée app ouverte.
  navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data === 'outbox-sync') void sync();
  });
}

/** Vide toutes les données locales (déconnexion). */
export async function clearLocalData(): Promise<void> {
  await Promise.all([
    db.profile.clear(),
    db.preferences.clear(),
    db.articles.clear(),
    db.quizzes.clear(),
    db.decks.clear(),
    db.exams.clear(),
    db.badges.clear(),
    db.outbox.clear(),
    db.meta.clear(),
  ]);
  sessionStore.set(null);
  await updateAppBadge(0);
}
