/**
 * Lecteur PDF protégé (pdf.js) : chaque page est dessinée sur un canvas.
 *
 * - Ni barre d'outils pdf.js, ni calque de texte : pas de lien de
 *   téléchargement, pas d'impression, pas de copie du texte.
 * - Filigrane nominatif (--wm-tile, posé par media-guard) sur chaque page.
 * - `.pdf-viewer` fait partie du PROTECTED_SELECTOR de media-guard : clic
 *   droit et glisser-déposer y sont neutralisés.
 * - Pages dessinées à l'approche de l'écran (IntersectionObserver) : un long
 *   document ne remplit pas la mémoire d'un téléphone.
 *
 * Le document arrive en mémoire (ArrayBuffer) : l'URL signée n'est jamais
 * donnée à pdf.js, et un fichier gardé hors ligne s'affiche de la même façon.
 */

import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { currentWatermarkText } from './media-guard';

/** Sous-ensemble de l'API pdf.js utilisé ici. */
interface PdfViewport {
  width: number;
  height: number;
}

interface PdfPage {
  getViewport(options: { scale: number }): PdfViewport;
  render(options: { canvasContext: CanvasRenderingContext2D; viewport: PdfViewport }): { promise: Promise<unknown> };
  cleanup?(): void;
}

interface PdfDocument {
  numPages: number;
  getPage(pageNumber: number): Promise<PdfPage>;
  destroy(): Promise<void>;
}

interface PdfJs {
  GlobalWorkerOptions: { workerSrc: string };
  getDocument(source: { data: Uint8Array; isEvalSupported?: boolean }): { promise: Promise<PdfDocument> };
}

export interface PdfViewerHandle {
  pages: number;
  destroy(): void;
}

/** Densité de rendu plafonnée : au-delà, le gain est invisible et la mémoire explose. */
const MAX_PIXEL_RATIO = 2;
const DEFAULT_WIDTH = 800;

async function loadPdfJs(): Promise<PdfJs> {
  const pdfjs = (await import('pdfjs-dist')) as unknown as PdfJs;
  pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
  return pdfjs;
}

function pageShell(pageNumber: number, ratio: number, watermark: string): HTMLElement {
  const page = document.createElement('div');
  page.className = 'pdf-viewer__page';
  page.dataset.page = String(pageNumber);
  page.style.aspectRatio = String(ratio);

  const canvas = document.createElement('canvas');
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', `Page ${pageNumber}`);

  const mark = document.createElement('div');
  mark.className = 'pdf-viewer__wm';
  mark.setAttribute('aria-hidden', 'true');
  mark.dataset.watermark = watermark;

  page.append(canvas, mark);
  return page;
}

async function drawPage(doc: PdfDocument, shell: HTMLElement, width: number): Promise<void> {
  const pageNumber = Number(shell.dataset.page);
  const page = await doc.getPage(pageNumber);
  const base = page.getViewport({ scale: 1 });
  const ratio = Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO);
  const viewport = page.getViewport({ scale: (width / base.width) * ratio });

  shell.style.aspectRatio = `${base.width} / ${base.height}`;
  const canvas = shell.querySelector('canvas')!;
  canvas.width = Math.floor(viewport.width);
  canvas.height = Math.floor(viewport.height);
  const context = canvas.getContext('2d');
  if (!context) return;

  await page.render({ canvasContext: context, viewport }).promise;
  page.cleanup?.();
}

/** Monte le lecteur dans `host` (contenu remplacé) et dessine le document. */
export async function renderPdf(host: HTMLElement, data: ArrayBuffer): Promise<PdfViewerHandle> {
  const pdfjs = await loadPdfJs();
  const doc = await pdfjs.getDocument({ data: new Uint8Array(data), isEvalSupported: false }).promise;

  const viewer = document.createElement('div');
  viewer.className = 'pdf-viewer';
  viewer.setAttribute('role', 'document');
  viewer.setAttribute('aria-label', 'Document PDF');

  const first = (await doc.getPage(1)).getViewport({ scale: 1 });
  const watermark = currentWatermarkText() ?? '';
  const shells: HTMLElement[] = [];
  for (let n = 1; n <= doc.numPages; n++) {
    const shell = pageShell(n, first.width / first.height, watermark);
    shells.push(shell);
    viewer.append(shell);
  }
  host.replaceChildren(viewer);

  const width = host.clientWidth || DEFAULT_WIDTH;
  let observer: IntersectionObserver | null = null;

  if (typeof IntersectionObserver === 'undefined') {
    for (const shell of shells) await drawPage(doc, shell, width);
  } else {
    observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          observer?.unobserve(entry.target);
          void drawPage(doc, entry.target as HTMLElement, width).catch(() => undefined);
        }
      },
      { rootMargin: '800px 0px' },
    );
    shells.forEach((shell) => observer!.observe(shell));
  }

  return {
    pages: doc.numPages,
    destroy(): void {
      observer?.disconnect();
      void doc.destroy();
    },
  };
}
