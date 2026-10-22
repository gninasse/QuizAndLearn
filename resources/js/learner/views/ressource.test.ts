// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { Resource } from '../domain/types';

const mocked = vi.hoisted(() => ({
  resourceView: vi.fn(),
  dispatch: vi.fn(async () => undefined),
  markResourceConsulted: vi.fn(async () => undefined),
}));

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
    allow_download: false, video_ref: null, url: null, conversion_status: 'pending',
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

  it("enregistre la consultation à l'ouverture", async () => {
    resourcesStore.set([resource({ id: 6, kind: 'pdf', conversion_status: null })]);
    mocked.resourceView.mockResolvedValue({ status: 'pending' });
    mount(document.createElement('div'), { id: '6' });
    await flush();

    expect(mocked.markResourceConsulted).toHaveBeenCalledWith(6);
    expect(mocked.dispatch).toHaveBeenCalledWith('resource_view', { resource_id: 6 });
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
