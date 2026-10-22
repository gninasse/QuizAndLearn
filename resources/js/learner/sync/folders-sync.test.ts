// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type {
  ArticleItem,
  BootstrapPayload,
  ChangesPayload,
  DeckItem,
  ExamItem,
  Folder,
  Placement,
  QuizItem,
  Resource,
} from '../domain/types';

const mocked = vi.hoisted(() => ({
  bootstrap: vi.fn(),
  changes: vi.fn(),
  actions: vi.fn(),
}));

vi.mock('../api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../api/client')>();
  return { ...original, api: mocked };
});

import { db } from '../db/schema';
import {
  articlesStore,
  consultedResourcesStore,
  decksStore,
  examsStore,
  foldersStore,
  isConsulted,
  placementsStore,
  quizzesStore,
  resourcesStore,
} from '../stores';
import { clearLocalData, fullBootstrap, hydrateFromDb, markResourceConsulted, sync } from './engine';

function resource(id: number, updated_at = '2026-10-20T10:00:00+00:00'): Resource {
  return {
    id,
    kind: 'link',
    title: `Ressource ${id}`,
    description: null,
    mime: null,
    size: null,
    allow_download: false,
    video_ref: null,
    url: 'https://example.org/doc',
    conversion_status: null,
    updated_at,
  };
}

const folders: Folder[] = [
  { id: 1, group_id: 1, parent_id: null, name: 'Semaine 1', position: 0 },
  { id: 2, group_id: 1, parent_id: 1, name: 'Exercices', position: 1 },
];

const placements: Placement[] = [
  { type: 'resource', id: 1, group_id: 1, folder_id: 1, position: 0 },
  { type: 'resource', id: 2, group_id: 1, folder_id: 2, position: 0 },
];

const xp = { total_xp: 0, current_level: 1, current_streak: 0, longest_streak: 0, last_activity_date: null };

function bootstrapPayload(patch: Partial<BootstrapPayload> = {}): BootstrapPayload {
  return {
    cursor: '2026-10-20T10:00:00+00:00',
    user: {
      id: 1,
      name: 'Dossier',
      last_name: 'Learner',
      full_name: 'Dossier Learner',
      email: 'folder@learnandquiz.fr',
      avatar_url: '',
      xp,
    },
    groups: [{ id: 1, name: 'Promo Dossiers', status: 'active', start_date: null, end_date: null }],
    articles: [],
    quizzes: [],
    decks: [],
    exams: [],
    badges: [],
    resources: [resource(1), resource(2)],
    folders,
    placements,
    preferences: { locale: 'fr', theme: 'light', font_size: 'medium', sound_enabled: true, notifications_enabled: null },
    ...patch,
  };
}

function changesPayload(patch: Partial<ChangesPayload> = {}): ChangesPayload {
  const empty = { updated: [], authorized_ids: [] };
  return {
    cursor: '2026-10-20T10:01:00+00:00',
    groups: [{ id: 1, name: 'Promo Dossiers', status: 'active', start_date: null, end_date: null }],
    articles: empty,
    quizzes: empty,
    decks: empty,
    exams: empty,
    badges: [],
    xp,
    resources: { updated: [], authorized_ids: [1, 2] },
    folders,
    placements,
    ...patch,
  };
}

describe('synchronisation des dossiers, ressources et placements', () => {
  beforeEach(async () => {
    vi.clearAllMocks();
    await clearLocalData();
  });

  it('le bootstrap persiste et hydrate les trois collections', async () => {
    mocked.bootstrap.mockResolvedValue({ success: true, ...bootstrapPayload() });
    await fullBootstrap();

    expect(await db.folders.count()).toBe(2);
    expect(await db.resources.count()).toBe(2);
    expect(await db.placements.count()).toBe(2);
    expect(foldersStore.get().map((f) => f.id).sort()).toEqual([1, 2]);
    expect(resourcesStore.get().map((r) => r.id).sort()).toEqual([1, 2]);
    expect(placementsStore.get()).toHaveLength(2);
  });

  it('les changes remplacent dossiers et placements, et appliquent le delta des ressources', async () => {
    mocked.bootstrap.mockResolvedValue({ success: true, ...bootstrapPayload() });
    await fullBootstrap();

    mocked.changes.mockResolvedValue({
      success: true,
      ...changesPayload({
        resources: { updated: [{ ...resource(1), title: 'Renommée' }], authorized_ids: [1] },
        folders: [folders[0]!],
        placements: [{ type: 'resource', id: 1, group_id: 1, folder_id: null, position: 3 }],
      }),
    });
    await sync();

    expect(mocked.bootstrap).toHaveBeenCalledTimes(1);
    expect(foldersStore.get().map((f) => f.id)).toEqual([1]);
    expect(placementsStore.get()).toEqual([{ type: 'resource', id: 1, group_id: 1, folder_id: null, position: 3 }]);
    expect(resourcesStore.get().map((r) => [r.id, r.title])).toEqual([[1, 'Renommée']]);
  });

  it('une ressource réautorisée absente en local déclenche un re-bootstrap', async () => {
    mocked.bootstrap.mockResolvedValue({ success: true, ...bootstrapPayload({ resources: [resource(1)] }) });
    await fullBootstrap();

    // La ressource 2 revient (dossier réaffiché) avec un ancien updated_at : absente de `updated`.
    mocked.changes.mockResolvedValue({
      success: true,
      ...changesPayload({ resources: { updated: [], authorized_ids: [1, 2] } }),
    });
    mocked.bootstrap.mockResolvedValue({ success: true, ...bootstrapPayload() });
    await sync();

    expect(mocked.bootstrap).toHaveBeenCalledTimes(2);
    expect(resourcesStore.get().map((r) => r.id).sort()).toEqual([1, 2]);
  });

  it('clearLocalData vide les nouvelles tables', async () => {
    mocked.bootstrap.mockResolvedValue({ success: true, ...bootstrapPayload() });
    await fullBootstrap();
    await markResourceConsulted(1);

    await clearLocalData();

    expect(await db.folders.count()).toBe(0);
    expect(await db.resources.count()).toBe(0);
    expect(await db.placements.count()).toBe(0);
    expect(foldersStore.get()).toEqual([]);
    expect(resourcesStore.get()).toEqual([]);
    expect(placementsStore.get()).toEqual([]);
    expect(consultedResourcesStore.get()).toEqual([]);
  });
});

describe('isConsulted', () => {
  beforeEach(async () => {
    await clearLocalData();
    mocked.bootstrap.mockResolvedValue({ success: true, ...bootstrapPayload() });
    await fullBootstrap();
  });

  it('ressource : marquée consultée localement, conservée après réhydratation', async () => {
    expect(isConsulted('resource', 1)).toBe(false);
    await markResourceConsulted(1);
    expect(isConsulted('resource', 1)).toBe(true);
    expect(isConsulted('resource', 2)).toBe(false);

    consultedResourcesStore.set([]);
    await hydrateFromDb();
    expect(isConsulted('resource', 1)).toBe(true);
  });

  it('examen : consulté dès une tentative', () => {
    const exam = { id: 4, attempts: [] } as unknown as ExamItem;
    examsStore.set([exam, { ...exam, id: 5, attempts: [{ id: 1 }] } as unknown as ExamItem]);
    expect(isConsulted('exam', 4)).toBe(false);
    expect(isConsulted('exam', 5)).toBe(true);
  });

  it('deck : consulté dès une carte révisée', () => {
    const card = { id: 1, review: null };
    decksStore.set([
      { id: 6, cards: [card] } as unknown as DeckItem,
      { id: 7, cards: [card, { id: 2, review: { repetitions: 1 } }] } as unknown as DeckItem,
    ]);
    expect(isConsulted('deck', 6)).toBe(false);
    expect(isConsulted('deck', 7)).toBe(true);
  });

  it('article et quiz : progression locale', () => {
    articlesStore.set([
      { id: 1, status: 'unread', progress_percentage: 0 } as unknown as ArticleItem,
      { id: 2, status: 'in_progress', progress_percentage: 40 } as unknown as ArticleItem,
    ]);
    quizzesStore.set([
      { id: 1, status: 'unread', attempts: [] } as unknown as QuizItem,
      { id: 2, status: 'completed', attempts: [{ id: 3 }] } as unknown as QuizItem,
    ]);
    expect(isConsulted('article', 1)).toBe(false);
    expect(isConsulted('article', 2)).toBe(true);
    expect(isConsulted('quiz', 1)).toBe(false);
    expect(isConsulted('quiz', 2)).toBe(true);
  });

  it('contenu inconnu : non consulté', () => {
    expect(isConsulted('article', 999)).toBe(false);
    expect(isConsulted('quiz', 999)).toBe(false);
  });
});
