// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const pdf = vi.hoisted(() => {
  const render = vi.fn(() => ({ promise: Promise.resolve() }));
  const page = {
    getViewport: ({ scale }: { scale: number }) => ({ width: 600 * scale, height: 800 * scale }),
    render,
    cleanup: vi.fn(),
  };
  const doc = { numPages: 3, getPage: vi.fn(async () => page), destroy: vi.fn(async () => undefined) };
  return { render, doc, getDocument: vi.fn(() => ({ promise: Promise.resolve(doc) })), GlobalWorkerOptions: { workerSrc: '' } };
});

vi.mock('pdfjs-dist', () => ({ getDocument: pdf.getDocument, GlobalWorkerOptions: pdf.GlobalWorkerOptions }));
vi.mock('pdfjs-dist/build/pdf.worker.min.mjs?url', () => ({ default: '/build/pdf.worker.min.mjs' }));

import { setWatermark } from './media-guard';
import { renderPdf } from './pdf-viewer';

describe('pdf-viewer', () => {
  beforeEach(() => {
    // Rendu immédiat de toutes les pages (pas de rendu paresseux sous test).
    vi.stubGlobal('IntersectionObserver', undefined);
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({} as never);
    setWatermark('Awa Diop · MAT-1 · 22/10/2026');
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    setWatermark(null);
    document.body.innerHTML = '';
  });

  it('rend chaque page sur un canvas avec le filigrane nominatif', async () => {
    const host = document.createElement('div');
    document.body.append(host);
    await renderPdf(host, new ArrayBuffer(8));

    const viewer = host.querySelector('.pdf-viewer');
    expect(viewer).not.toBeNull();
    expect(viewer!.querySelectorAll('.pdf-viewer__page canvas')).toHaveLength(3);
    const marks = viewer!.querySelectorAll<HTMLElement>('.pdf-viewer__page .pdf-viewer__wm');
    expect(marks).toHaveLength(3);
    expect(marks[0]!.dataset.watermark).toBe('Awa Diop · MAT-1 · 22/10/2026');
    expect(pdf.render).toHaveBeenCalledTimes(3);
    expect(pdf.GlobalWorkerOptions.workerSrc).toBe('/build/pdf.worker.min.mjs');
  });

  it("n'expose aucun lien de téléchargement", async () => {
    const host = document.createElement('div');
    await renderPdf(host, new ArrayBuffer(8));
    expect(host.querySelector('a[download]')).toBeNull();
    expect(host.querySelector('a[href]')).toBeNull();
  });

  it('le menu contextuel est bloqué sur le lecteur (PROTECTED_SELECTOR)', async () => {
    const { installMediaGuard } = await import('./media-guard');
    installMediaGuard();
    const host = document.createElement('div');
    document.body.append(host);
    await renderPdf(host, new ArrayBuffer(8));

    const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    host.querySelector('canvas')!.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
  });
});
