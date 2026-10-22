// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { Resource } from '../domain/types';

const mocked = vi.hoisted(() => ({
  resourceView: vi.fn(),
  dispatch: vi.fn(async () => undefined),
  markResourceConsulted: vi.fn(async () => undefined),
  consultation: false,
}));

vi.mock('../consultation/consultation', async (importOriginal) => {
  const original = await importOriginal<typeof import('../consultation/consultation')>();
  return { ...original, isConsultation: () => mocked.consultation };
});

vi.mock('../api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../api/client')>();
  return { ...original, api: { ...original.api, resourceView: mocked.resourceView } };
});

vi.mock('../sync/engine', () => ({
  dispatch: mocked.dispatch,
  markResourceConsulted: mocked.markResourceConsulted,
}));

import { ApiError } from '../api/client';
import { resourcesStore } from '../stores';
import { mount } from './ressource';

const flush = async (): Promise<void> => {
  for (let i = 0; i < 5; i++) await new Promise((resolve) => setTimeout(resolve, 0));
};

function resource(patch: Partial<Resource> = {}): Resource {
  return {
    id: 4, kind: 'document', title: 'Cours Word', description: null, mime: null, size: null,
    allow_download: false, video_ref: null, url: null, conversion_status: 'pending', consulted: false,
    updated_at: '2026-10-20T10:00:00+00:00', ...patch,
  };
}

describe('lecteur de ressource', () => {
  beforeEach(() => {
    mocked.resourceView.mockReset();
    mocked.dispatch.mockClear();
    mocked.markResourceConsulted.mockClear();
  });

  afterEach(() => {
    resourcesStore.set([]);
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('202 → « Document en préparation… »', async () => {
    resourcesStore.set([resource()]);
    mocked.resourceView.mockResolvedValue({ status: 'pending' });
    const el = document.createElement('div');
    mount(el, { id: '4' });
    await flush();

    expect(mocked.resourceView).toHaveBeenCalledWith(4);
    expect(el.querySelector('[data-role="resource-body"]')?.textContent).toContain('Document en préparation…');
  });

  it('409 → « Document indisponible »', async () => {
    resourcesStore.set([resource({ id: 5, conversion_status: 'failed' })]);
    mocked.resourceView.mockRejectedValue(new ApiError(409, 'Erreur 409', { status: 'failed' }));
    const el = document.createElement('div');
    mount(el, { id: '5' });
    await flush();

    expect(el.querySelector('[data-role="resource-body"]')?.textContent).toContain('Document indisponible');
  });

  it('202, 409 et 403 ne comptent pas comme consultés', async () => {
    resourcesStore.set([resource({ id: 6, kind: 'document' })]);
    for (const outcome of [
      () => mocked.resourceView.mockResolvedValue({ status: 'pending' }),
      () => mocked.resourceView.mockRejectedValue(new ApiError(409, 'Erreur 409', { status: 'failed' })),
      () => mocked.resourceView.mockRejectedValue(new ApiError(403, 'Ressource indisponible.')),
    ]) {
      outcome();
      mount(document.createElement('div'), { id: '6' });
      await flush();
    }

    expect(mocked.markResourceConsulted).not.toHaveBeenCalled();
    expect(mocked.dispatch).not.toHaveBeenCalled();
  });

  it('un affichage réussi (200) enregistre la consultation', async () => {
    resourcesStore.set([resource({ id: 11, kind: 'link', url: 'https://example.org/cours', conversion_status: null })]);
    mocked.resourceView.mockResolvedValue({ type: 'link', url: 'https://example.org/cours' });
    mount(document.createElement('div'), { id: '11' });
    await flush();

    expect(mocked.markResourceConsulted).toHaveBeenCalledWith(11);
    expect(mocked.dispatch).toHaveBeenCalledWith('resource_view', { resource_id: 11 });
  });

  it('copie hors ligne affichée : comptée ; hors ligne sans copie : non', async () => {
    resourcesStore.set([resource({ id: 13, kind: 'image', conversion_status: null })]);
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:x');
    let entry: Response | undefined;
    vi.stubGlobal('caches', { open: async () => ({ match: async () => entry?.clone() }) });

    mount(document.createElement('div'), { id: '13' });
    await flush();
    expect(mocked.markResourceConsulted).not.toHaveBeenCalled();
    expect(mocked.dispatch).not.toHaveBeenCalled();

    entry = new Response('img', { headers: { 'Content-Type': 'image/png', 'X-Resource-Type': 'image' } });
    const el = document.createElement('div');
    mount(el, { id: '13' });
    await flush();
    expect(el.querySelector('.rich-content img')).not.toBeNull();
    expect(mocked.markResourceConsulted).toHaveBeenCalledWith(13);
    expect(mocked.dispatch).toHaveBeenCalledWith('resource_view', { resource_id: 13 });
  });

  it('consultation (« Voir en tant que ») : ni marqueur local ni action', async () => {
    mocked.consultation = true;
    try {
      resourcesStore.set([resource({ id: 12, kind: 'link', url: 'https://example.org/cours', conversion_status: null })]);
      mocked.resourceView.mockResolvedValue({ type: 'link', url: 'https://example.org/cours' });
      mount(document.createElement('div'), { id: '12' });
      await flush();
    } finally {
      mocked.consultation = false;
    }

    expect(mocked.markResourceConsulted).not.toHaveBeenCalled();
    expect(mocked.dispatch).not.toHaveBeenCalled();
  });

  it('« Télécharger » seulement si allow_download', async () => {
    mocked.resourceView.mockResolvedValue({ status: 'pending' });

    resourcesStore.set([resource({ id: 7, kind: 'pdf', allow_download: false })]);
    const without = document.createElement('div');
    mount(without, { id: '7' });
    expect(without.querySelector('[data-role="download"]')).toBeNull();

    resourcesStore.set([resource({ id: 8, kind: 'pdf', allow_download: true })]);
    const withDl = document.createElement('div');
    mount(withDl, { id: '8' });
    expect(withDl.querySelector('[data-role="download"]')).not.toBeNull();
    expect(withDl.querySelector('a[download]')).toBeNull();
    await flush();
  });

  it('vidéo : jamais de « Télécharger », même avec allow_download (le serveur répond 404)', async () => {
    mocked.resourceView.mockResolvedValue({ status: 'pending' });
    resourcesStore.set([resource({ id: 14, kind: 'video', allow_download: true, video_ref: 'vid-abc', conversion_status: null })]);
    const el = document.createElement('div');
    mount(el, { id: '14' });
    expect(el.querySelector('[data-role="download"]')).toBeNull();
    await flush();
  });

  it('lien YouTube → lecteur de marque ; autre lien → window.open', async () => {
    resourcesStore.set([
      resource({ id: 9, kind: 'link', url: 'https://youtu.be/dQw4w9WgXcQ', conversion_status: null }),
      resource({ id: 10, kind: 'link', url: 'https://example.org/cours', conversion_status: null }),
    ]);

    mocked.resourceView.mockResolvedValue({ type: 'link', url: 'https://youtu.be/dQw4w9WgXcQ' });
    const yt = document.createElement('div');
    mount(yt, { id: '9' });
    await flush();
    const box = yt.querySelector<HTMLElement>('.rich-content .brand-video');
    expect(box?.dataset.embed).toBe('youtube');
    expect(box?.dataset.id).toBe('dQw4w9WgXcQ');

    const open = vi.fn();
    vi.stubGlobal('open', open);
    mocked.resourceView.mockResolvedValue({ type: 'link', url: 'https://example.org/cours' });
    const other = document.createElement('div');
    mount(other, { id: '10' });
    await flush();
    other.querySelector<HTMLButtonElement>('[data-role="open-link"]')!.click();
    expect(open).toHaveBeenCalledWith('https://example.org/cours', '_blank', 'noopener');
  });
});
