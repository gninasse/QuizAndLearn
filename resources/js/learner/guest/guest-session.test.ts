// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ArticleItem, DeckItem, QuizItem, ShowcaseSettings } from '../domain/types';

const mocked = vi.hoisted(() => ({
  showcaseContent: vi.fn(),
  showcaseSettings: vi.fn(),
  bootstrap: vi.fn(),
  changes: vi.fn(),
  actions: vi.fn(),
}));

vi.mock('../api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../api/client')>();
  return { ...original, api: mocked };
});

import { db } from '../db/schema';
import { enqueue } from '../db/outbox';
import { articlesStore, sessionStore } from '../stores';
import { clearLocalData, dispatch, sync } from '../sync/engine';
import { enterGuestMode, guestSettings, isGuest, leaveGuestMode, PendingOutboxError, refreshGuestSettings } from './guest-session';

function article(id: number, title: string): ArticleItem {
  return {
    id,
    title,
    category: null,
    content: '<p>Contenu</p>',
    estimated_reading_time: 3,
    is_favorite: false,
    rating: 0,
    status: 'unread',
    progress_percentage: 0,
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-01T00:00:00Z',
  };
}

function quiz(id: number): QuizItem {
  return {
    id,
    title: `Quiz ${id}`,
    description: null,
    duration: null,
    passing_score: 50,
    max_attempts: 0,
    shuffle_questions: false,
    show_correct_answers: true,
    status: 'unread',
    max_attempts_reached: false,
    updated_at: '2026-10-01T00:00:00Z',
    attempts: [],
    questions: [],
  };
}

function deck(id: number): DeckItem {
  return {
    id,
    titre: `Deck ${id}`,
    description: null,
    matiere: null,
    algorithme: 'sm2',
    easiness_default: 2.5,
    interval_min: 1,
    interval_max: 365,
    updated_at: '2026-10-01T00:00:00Z',
    cards: [
      { id: 10, recto: 'R', verso: 'V', recto_media: null, verso_media: null, tags: null, ordre: 1, review: null },
    ],
  };
}

const settings: ShowcaseSettings = {
  enabled: true,
  brand: { name: 'Learn&Quiz', tagline: 'Apprendre partout', logo: null },
  registration: { fee: 5000, currency: 'XOF', accounts: [], reference: null, instructions: null },
  default_country_code: '225',
  privacy_url: '/confidentialite',
  groups: [],
};

function content(articles: ArticleItem[]) {
  return {
    success: true,
    guest_token: 'tok',
    expires_at: '2026-10-19T00:00:00Z',
    articles,
    quizzes: [quiz(1)],
    decks: [deck(1)],
  };
}

describe('session invité (mode découverte)', () => {
  beforeEach(async () => {
    vi.clearAllMocks();
    await clearLocalData();
    mocked.showcaseContent.mockResolvedValue(content([article(1, 'Vitrine A')]));
    mocked.showcaseSettings.mockResolvedValue({ success: true, ...settings });
  });

  it('enterGuestMode stores showcase content and a guest profile', async () => {
    await enterGuestMode();

    const profile = await db.profile.get('me');
    expect(profile).toMatchObject({
      id: 0,
      name: 'Invité',
      full_name: 'Invité',
      email: '',
      matricule: null,
      is_guest: true,
    });
    expect(profile?.xp.total_xp).toBe(0);
    expect(await db.articles.count()).toBe(1);
    expect(await db.quizzes.count()).toBe(1);
    expect(await db.decks.count()).toBe(1);
    expect((await db.meta.get('showcase-settings'))?.value).toMatchObject({ enabled: true, default_country_code: '225' });

    // Stores réhydratés.
    expect(sessionStore.get()?.is_guest).toBe(true);
    expect(articlesStore.get().map((a) => a.title)).toEqual(['Vitrine A']);
    expect(isGuest()).toBe(true);
    expect(guestSettings()?.registration.currency).toBe('XOF');
  });

  it('refreshGuestSettings replaces cached settings (memory and IndexedDB)', async () => {
    await enterGuestMode();
    const closed = { ...settings, access_request: { enabled: false } };
    mocked.showcaseSettings.mockResolvedValue({ success: true, ...closed });

    await refreshGuestSettings();

    expect(guestSettings()?.access_request?.enabled).toBe(false);
    expect((await db.meta.get('showcase-settings'))?.value).toMatchObject({ access_request: { enabled: false } });
    expect((await db.meta.get('showcase-settings'))?.value).not.toHaveProperty('success');
  });

  it('refuses to enter guest mode while a learner outbox is pending (no purge)', async () => {
    await db.articles.put(article(7, 'Article apprenant'));
    await db.profile.put({ id: 5, name: 'Awa', _key: 'me' } as never);
    await enqueue('article_progress', { article_id: 7, progress_percentage: 50 });

    await expect(enterGuestMode()).rejects.toBeInstanceOf(PendingOutboxError);

    expect(await db.outbox.count()).toBe(1);
    expect(await db.articles.count()).toBe(1);
    expect((await db.profile.get('me'))?.id).toBe(5);
    expect(mocked.showcaseContent).not.toHaveBeenCalled();
  });

  it('dispatch is a no-op in guest mode (outbox stays empty)', async () => {
    await enterGuestMode();

    await dispatch('article_progress', { article_id: 1, progress_percentage: 50 });

    expect(await db.outbox.count()).toBe(0);
    expect(mocked.actions).not.toHaveBeenCalled();
  });

  it('sync in guest mode refreshes showcase instead of calling bootstrap/changes', async () => {
    await enterGuestMode();

    // Progression locale (écrite par les vues) et XP local.
    await db.articles.put({ ...article(1, 'Vitrine A'), progress_percentage: 80, status: 'in_progress', is_favorite: true });
    const profile = (await db.profile.get('me'))!;
    await db.profile.put({ ...profile, xp: { ...profile.xp, total_xp: 42 } });
    sessionStore.set({ ...profile, xp: { ...profile.xp, total_xp: 42 } });

    mocked.showcaseContent.mockResolvedValue(content([article(1, 'Vitrine A (maj)'), article(2, 'Vitrine B')]));

    await sync();

    expect(mocked.bootstrap).not.toHaveBeenCalled();
    expect(mocked.changes).not.toHaveBeenCalled();
    expect(mocked.actions).not.toHaveBeenCalled();
    expect(mocked.showcaseContent).toHaveBeenCalledTimes(2);

    const rows = await db.articles.orderBy('id').toArray();
    expect(rows.map((a) => a.title)).toEqual(['Vitrine A (maj)', 'Vitrine B']);
    expect(rows[0]).toMatchObject({ progress_percentage: 80, status: 'in_progress', is_favorite: true });
    expect(rows[1]?.progress_percentage).toBe(0);
    expect((await db.profile.get('me'))?.xp.total_xp).toBe(42);
    expect(sessionStore.get()?.xp.total_xp).toBe(42);
  });

  it('leaving guest mode clears every local table', async () => {
    await enterGuestMode();
    await enqueue('article_progress', { article_id: 1 });

    const loggedOut = vi.fn();
    window.addEventListener('learner:logged-out', loggedOut);

    await leaveGuestMode();

    window.removeEventListener('learner:logged-out', loggedOut);
    expect(await db.outbox.count()).toBe(0);
    expect(await db.articles.count()).toBe(0);
    expect(await db.quizzes.count()).toBe(0);
    expect(await db.decks.count()).toBe(0);
    expect(await db.profile.count()).toBe(0);
    expect(await db.meta.count()).toBe(0);
    expect(sessionStore.get()).toBeNull();
    expect(isGuest()).toBe(false);
    expect(guestSettings()).toBeNull();
    expect(loggedOut).toHaveBeenCalledOnce();
  });
});
