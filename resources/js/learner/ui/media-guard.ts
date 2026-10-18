/**
 * Protection des médias pédagogiques (images, audio, vidéo) du volet
 * apprenant : on peut les voir et les écouter — en ligne comme hors-ligne —
 * mais pas les récupérer.
 *
 * 1. Gestes de sauvegarde neutralisés : clic droit / appui long, glisser-
 *    déposer, Ctrl+S, boutons « Télécharger » / PiP / Cast des lecteurs.
 * 2. Filigrane nominatif (nom · matricule · date) sur chaque image et
 *    vidéo : une capture d'écran qui fuite désigne son auteur.
 * 3. Visionneuse plein écran des images (zoom, galerie, swipe), fermée par
 *    le bouton Retour sans démonter la vue courante.
 *
 * Limite assumée : aucune protection web n'empêche une capture d'écran ou
 * les outils de développement d'un ordinateur — d'où le filigrane.
 *
 * Ne s'applique qu'au contenu pédagogique (`.rich-content`) : logo,
 * avatars et icônes de l'interface ne sont pas concernés.
 */

import { escapeHtml } from '../core/html';
import { mountBrandVideos } from './brand-video';
import { resolveVideoRefs } from './video-ref';

const MEDIA_SELECTOR = '.rich-content img, .rich-content video';
const PLAYER_SELECTOR = '.rich-content video, .rich-content audio';
/** Vidéos référencées, embeds de marque et anciennes iframes d'embed. */
const VIDEO_SELECTOR = '.rich-content iframe, .rich-content [data-embed]';
const PROTECTED_SELECTOR =
  '.rich-content img, .rich-content video, .rich-content audio, .media-frame, .media-lightbox, .brand-video';

/** Texte du filigrane courant (repris par le lecteur de marque). */
let currentWatermark: string | null = null;

// ------------------------------------------------------------- Filigrane

function escapeXml(value: string): string {
  return value.replace(/[<>&'"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' })[c] ?? c);
}

/**
 * Motif SVG répété en fond (via la variable CSS --wm-tile) : texte clair
 * légèrement cerné, lisible sur une image claire comme sombre.
 */
export function setWatermark(text: string | null): void {
  currentWatermark = text;
  const rootStyle = document.documentElement.style;
  if (!text) {
    rootStyle.removeProperty('--wm-tile');
    return;
  }

  const svg =
    `<svg xmlns='http://www.w3.org/2000/svg' width='240' height='130'>` +
    `<text x='120' y='65' text-anchor='middle' dominant-baseline='middle' transform='rotate(-24 120 65)' ` +
    `font-family='system-ui,sans-serif' font-size='12' font-weight='600' fill='white' fill-opacity='0.30' ` +
    `stroke='black' stroke-opacity='0.16' stroke-width='0.5'>${escapeXml(text)}</text></svg>`;

  rootStyle.setProperty('--wm-tile', `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`);
}

// ------------------------------------------------- Durcissement du DOM

function hardenPlayer(player: HTMLMediaElement): void {
  if (player.dataset.guarded) return;
  player.dataset.guarded = '1';
  player.setAttribute('controlsList', 'nodownload noremoteplayback noplaybackrate');
  player.setAttribute('disableRemotePlayback', '');
  if (player instanceof HTMLVideoElement) {
    player.disablePictureInPicture = true;
    player.setAttribute('playsinline', '');
  }
  if (!player.getAttribute('preload')) player.setAttribute('preload', 'metadata');
}

/** Enveloppe image/vidéo dans un cadre portant le filigrane. */
function frameMedia(media: HTMLElement): void {
  if (media.closest('.media-frame')) return;

  const frame = document.createElement('span');
  frame.className = media instanceof HTMLVideoElement ? 'media-frame media-frame--block' : 'media-frame';
  media.replaceWith(frame);
  frame.append(media);

  if (media instanceof HTMLImageElement) {
    media.draggable = false;
    const zoom = document.createElement('button');
    zoom.type = 'button';
    zoom.className = 'media-zoom';
    zoom.setAttribute('aria-label', "Agrandir l'image");
    zoom.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
    frame.append(zoom);
  }
}

/** Contenus pédagogiques concernés par un sous-arbre (ancêtre ou descendants). */
function richScopes(root: ParentNode): ParentNode[] {
  const host = root instanceof Element ? root.closest('.rich-content') : null;
  return host ? [host] : [...root.querySelectorAll('.rich-content')];
}

function processTree(root: ParentNode): void {
  root.querySelectorAll<HTMLElement>(MEDIA_SELECTOR).forEach(frameMedia);
  root.querySelectorAll<HTMLMediaElement>(PLAYER_SELECTOR).forEach(hardenPlayer);
  // Vidéos : lecteur de marque YouTube / Vimeo (et conversion des anciennes
  // iframes) puis sources des vidéos référencées — idempotents.
  for (const scope of richScopes(root)) {
    mountBrandVideos(scope, currentWatermark);
    resolveVideoRefs(scope);
  }
}

// ------------------------------------------------------------ Visionneuse

let lightbox: HTMLElement | null = null;
let closeLightbox: ((fromHistory: boolean) => void) | null = null;

export function isLightboxOpen(): boolean {
  return lightbox !== null;
}

export function openLightbox(images: HTMLImageElement[], startIndex: number): void {
  if (lightbox || !images.length) return;

  let index = Math.max(0, Math.min(startIndex, images.length - 1));
  let zoomed = false;
  const previousFocus = document.activeElement as HTMLElement | null;

  const box = document.createElement('div');
  box.className = 'media-lightbox';
  box.setAttribute('role', 'dialog');
  box.setAttribute('aria-modal', 'true');
  box.setAttribute('aria-label', "Aperçu de l'image");
  box.innerHTML = `
    <div class="media-lightbox__bar">
      <span class="media-lightbox__count" aria-live="polite"></span>
      <div class="flex items-center gap-1">
        <button type="button" class="media-lightbox__btn" data-lb="zoom" aria-label="Zoomer"><i class="bi bi-zoom-in"></i></button>
        <button type="button" class="media-lightbox__btn" data-lb="close" aria-label="Fermer l'aperçu"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="media-lightbox__stage">
      <span class="media-frame"><img alt="" draggable="false"></span>
    </div>
    ${images.length > 1
      ? `<button type="button" class="media-lightbox__nav media-lightbox__nav--prev" data-lb="prev" aria-label="Image précédente"><i class="bi bi-chevron-left"></i></button>
         <button type="button" class="media-lightbox__nav media-lightbox__nav--next" data-lb="next" aria-label="Image suivante"><i class="bi bi-chevron-right"></i></button>`
      : ''}
    <p class="media-lightbox__caption"></p>`;

  const stage = box.querySelector<HTMLElement>('.media-lightbox__stage')!;
  const img = box.querySelector<HTMLImageElement>('.media-lightbox__stage img')!;
  const count = box.querySelector<HTMLElement>('.media-lightbox__count')!;
  const caption = box.querySelector<HTMLElement>('.media-lightbox__caption')!;
  const zoomBtn = box.querySelector<HTMLButtonElement>('[data-lb="zoom"]')!;

  const setZoom = (value: boolean): void => {
    zoomed = value;
    box.classList.toggle('is-zoomed', zoomed);
    zoomBtn.innerHTML = zoomed ? '<i class="bi bi-zoom-out"></i>' : '<i class="bi bi-zoom-in"></i>';
    zoomBtn.setAttribute('aria-label', zoomed ? 'Dézoomer' : 'Zoomer');
  };

  const show = (next: number): void => {
    index = (next + images.length) % images.length;
    const source = images[index]!;
    img.src = source.currentSrc || source.src;
    // L'éditeur met souvent le nom du fichier en alt : on ne l'affiche pas.
    const alt = source.alt && !/^image$/i.test(source.alt) && !/\.\w{2,4}$/.test(source.alt) ? source.alt : '';
    img.alt = alt;
    caption.innerHTML = alt ? escapeHtml(alt) : '';
    count.textContent = images.length > 1 ? `${index + 1} / ${images.length}` : '';
    setZoom(false);
    stage.scrollTo(0, 0);
  };

  const onKey = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close(false);
    } else if (event.key === 'ArrowRight' && images.length > 1) {
      show(index + 1);
    } else if (event.key === 'ArrowLeft' && images.length > 1) {
      show(index - 1);
    }
  };

  const close = (fromHistory: boolean): void => {
    if (!lightbox) return;
    document.removeEventListener('keydown', onKey, true);
    box.classList.remove('is-open');
    window.setTimeout(() => box.remove(), 180);
    lightbox = null;
    closeLightbox = null;
    document.documentElement.classList.remove('lightbox-open');
    previousFocus?.focus?.({ preventScroll: true });
    // Fermeture par le bouton ✕ / Échap : on consomme l'entrée d'historique.
    if (!fromHistory && (history.state as { lightbox?: boolean } | null)?.lightbox) history.back();
  };

  box.addEventListener('click', (event) => {
    const action = (event.target as HTMLElement).closest<HTMLElement>('[data-lb]')?.dataset.lb;
    if (action === 'close') close(false);
    else if (action === 'prev') show(index - 1);
    else if (action === 'next') show(index + 1);
    else if (action === 'zoom') setZoom(!zoomed);
    else if (event.target === img) setZoom(!zoomed);
    else if (event.target === stage && !zoomed) close(false);
  });

  // Swipe horizontal (galerie) / vertical vers le bas (fermer), hors zoom.
  let startX = 0;
  let startY = 0;
  stage.addEventListener('pointerdown', (event) => {
    startX = event.clientX;
    startY = event.clientY;
  });
  stage.addEventListener('pointerup', (event) => {
    if (zoomed) return;
    const dx = event.clientX - startX;
    const dy = event.clientY - startY;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) && images.length > 1) {
      show(dx < 0 ? index + 1 : index - 1);
    } else if (dy > 90 && Math.abs(dy) > Math.abs(dx)) {
      close(false);
    }
  });

  document.body.append(box);
  document.addEventListener('keydown', onKey, true);
  document.documentElement.classList.add('lightbox-open');
  lightbox = box;
  closeLightbox = close;

  // Entrée d'historique dédiée : le bouton Retour (Android, navigateur)
  // ferme l'aperçu au lieu de quitter l'écran.
  history.pushState({ ...(history.state as object | null), lightbox: true }, '');

  show(index);
  requestAnimationFrame(() => box.classList.add('is-open'));
  box.querySelector<HTMLButtonElement>('[data-lb="close"]')?.focus({ preventScroll: true });
}

/** Galerie = toutes les images pédagogiques de l'écran (une seule face de carte). */
function galleryFor(image: HTMLImageElement): { images: HTMLImageElement[]; index: number } {
  const scope = image.closest('#outlet') ?? document;
  const face = image.closest('.flip-face');
  const images = [...scope.querySelectorAll<HTMLImageElement>('.rich-content img')].filter(
    (candidate) => !face || candidate.closest('.flip-face') === face,
  );
  return { images, index: Math.max(0, images.indexOf(image)) };
}

/** Ferme l'aperçu sans toucher à l'historique (changement d'écran programmatique). */
export function dismissLightbox(): void {
  closeLightbox?.(true);
}

// ------------------------------------------------------------- Installation

let installed = false;

export function installMediaGuard(): void {
  if (installed) return;
  installed = true;

  // Gestes de sauvegarde (souris, appui long tactile, glisser-déposer).
  document.addEventListener(
    'contextmenu',
    (event) => {
      if ((event.target as Element | null)?.closest?.(PROTECTED_SELECTOR)) event.preventDefault();
    },
    true,
  );
  document.addEventListener(
    'dragstart',
    (event) => {
      if ((event.target as Element | null)?.closest?.(PROTECTED_SELECTOR)) event.preventDefault();
    },
    true,
  );
  // « Enregistrer la page » embarquerait tous les médias.
  document.addEventListener(
    'keydown',
    (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') event.preventDefault();
    },
    true,
  );

  // Ouverture de la visionneuse. Sur une flashcard, toucher l'image
  // retourne la carte : seul le bouton « agrandir » ouvre l'aperçu.
  document.addEventListener(
    'click',
    (event) => {
      const target = event.target as HTMLElement;
      const zoomButton = target.closest('.media-zoom');
      const image = zoomButton
        ? zoomButton.parentElement?.querySelector('img')
        : target.closest<HTMLImageElement>('.rich-content img');
      if (!image || target.closest('.media-lightbox')) return;
      if (!zoomButton && image.closest('.flip-face')) return;
      // Image cliquable dans un lien : on laisse le lien vivre.
      if (!zoomButton && image.closest('a[href]')) return;

      event.preventDefault();
      event.stopPropagation();
      const { images, index } = galleryFor(image);
      openLightbox(images, index);
    },
    true,
  );

  window.addEventListener('popstate', () => {
    if (closeLightbox) closeLightbox(true);
  });

  processTree(document);
  new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof HTMLElement) {
          if (node.matches(MEDIA_SELECTOR) || node.matches(PLAYER_SELECTOR) || node.matches(VIDEO_SELECTOR)) {
            processTree(node.parentElement ?? document);
          } else if (node.querySelector('img, video, audio, iframe, [data-embed]')) {
            processTree(node);
          }
        }
      });
    }
  }).observe(document.body, { childList: true, subtree: true });
}
