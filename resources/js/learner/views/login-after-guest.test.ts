// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocked = vi.hoisted(() => ({
  login: vi.fn(),
  bootstrap: vi.fn(),
  showcaseContent: vi.fn(),
  showcaseSettings: vi.fn(),
}));

vi.mock('../api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../api/client')>();
  return { ...original, api: mocked };
});

import { db } from '../db/schema';
import { META_SHOWCASE_SETTINGS, enterGuestMode } from '../guest/guest-session';
import { sessionStore } from '../stores';
import { clearLocalData } from '../sync/engine';
import { mount } from './login';

const xp = { total_xp: 120, current_level: 2, current_streak: 1, longest_streak: 3, last_activity_date: null };
const realUser = (mustChange: boolean) => ({
  id: 7,
  name: 'Awa',
  last_name: 'Koné',
  full_name: 'Awa Koné',
  email: 'awa@exemple.fr',
  avatar_url: '',
  matricule: 'MAT-7',
  must_change_password: mustChange,
  xp,
});

const showcaseArticle = {
  id: 99, title: 'Vitrine', category: null, content: null, estimated_reading_time: null,
  is_favorite: false, rating: 0, status: 'unread', progress_percentage: 0,
  created_at: '2026-10-01T00:00:00Z', updated_at: '2026-10-01T00:00:00Z',
};
const showcaseQuiz = {
  id: 98, title: 'Quiz vitrine', description: null, duration: null, passing_score: 50, max_attempts: 0,
  shuffle_questions: false, show_correct_answers: true, status: 'unread', max_attempts_reached: false,
  updated_at: '2026-10-01T00:00:00Z', attempts: [], questions: [],
};
const showcaseDeck = {
  id: 97, titre: 'Deck vitrine', description: null, matiere: null, algorithme: 'sm2', easiness_default: 2.5,
  interval_min: 1, interval_max: 365, updated_at: '2026-10-01T00:00:00Z', cards: [],
};

async function guestWithLocalTraces(): Promise<void> {
  mocked.showcaseContent.mockResolvedValue({
    success: true, guest_token: 't', expires_at: '2026-10-19T00:00:00Z',
    articles: [showcaseArticle], quizzes: [showcaseQuiz], decks: [showcaseDeck],
  });
  mocked.showcaseSettings.mockResolvedValue({ success: true, enabled: true });
  await enterGuestMode();
  await db.mistakes.put({ question_id: 1, quiz_id: 98, last_wrong_at: '2026-10-18T00:00:00Z' });
  await db.drafts.put({
    quiz_id: 98, answers: {}, question_order: [1], index: 0, started_at: '2026-10-18T00:00:00Z',
    remaining_seconds: null, updated_at: '2026-10-18T00:00:00Z',
  });
}

async function submitLogin(): Promise<void> {
  const el = document.createElement('div');
  document.body.append(el);
  mount(el);
  el.querySelector<HTMLInputElement>('input[name="login"]')!.value = 'awa@exemple.fr';
  el.querySelector<HTMLInputElement>('input[name="password"]')!.value = 'secret123';
  const done = new Promise<void>((resolve) =>
    window.addEventListener('learner:authenticated', () => resolve(), { once: true }),
  );
  el.querySelector<HTMLFormElement>('#login-form')!.requestSubmit();
  await done;
}

describe('connexion réelle après une session invité', () => {
  beforeEach(async () => {
    vi.clearAllMocks();
    await clearLocalData();
  });

  it('purge les traces invité avant d’écrire le vrai profil', async () => {
    await guestWithLocalTraces();
    mocked.login.mockResolvedValue({ success: true, user: realUser(false) });
    mocked.bootstrap.mockResolvedValue({
      success: true, cursor: 'c1', user: realUser(false), groups: [], articles: [], quizzes: [], decks: [],
      exams: [], badges: [],
      preferences: { locale: 'fr', theme: 'light', font_size: 'medium', sound_enabled: true, notifications_enabled: null },
    });

    await submitLogin();

    expect(await db.mistakes.count()).toBe(0);
    expect(await db.drafts.count()).toBe(0);
    expect(await db.meta.get(META_SHOWCASE_SETTINGS)).toBeUndefined();
    const profile = await db.profile.get('me');
    expect(profile?.id).toBe(7);
    expect(profile?.is_guest).toBeUndefined();
    expect(sessionStore.get()?.full_name).toBe('Awa Koné');
  });

  it('mot de passe provisoire : aucun contenu vitrine ne reste sous le vrai profil', async () => {
    await guestWithLocalTraces();
    mocked.login.mockResolvedValue({ success: true, user: realUser(true) });

    await submitLogin();

    expect(mocked.bootstrap).not.toHaveBeenCalled();
    expect(await db.articles.count()).toBe(0);
    expect(await db.quizzes.count()).toBe(0);
    expect(await db.decks.count()).toBe(0);
    expect(await db.mistakes.count()).toBe(0);
    expect(await db.drafts.count()).toBe(0);
    expect(await db.meta.get(META_SHOWCASE_SETTINGS)).toBeUndefined();
    expect((await db.profile.get('me'))?.id).toBe(7);
  });
});
