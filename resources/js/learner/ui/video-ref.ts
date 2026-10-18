/**
 * Vidéos référencées : `<video data-video-ref="uuid">` (sans `src`).
 *
 * Le contenu ne stocke qu'une référence ; la source de lecture est demandée
 * au serveur (`GET …/videos/{ref}/playback`) au moment de l'affichage :
 *
 * - `mp4` → `src` direct (pilote local : URL permanente ; pilote externe :
 *   URL signée 15 min) ;
 * - `hls` → lecture native (Safari / iOS) ou hls.js, importé à la demande ;
 * - 202 → « Vidéo en cours de préparation », nouvel essai toutes les 30 s
 *   (3 fois au plus) ; 404 / 410 → « Vidéo indisponible » ;
 * - hors ligne → « Vidéo disponible en ligne uniquement. », chargement au
 *   retour du réseau (les vidéos ne sont jamais mises en cache).
 *
 * Renouvellement : une URL signée expire. 60 s avant `expires_at`, ou sur
 * erreur réseau / 403 du média, une nouvelle source est demandée et la
 * lecture reprend à la même position, dans le même état (lecture / pause),
 * sans message d'erreur. Pour HLS, les segments signés expirent avec la
 * playlist : c'est la playlist entière qui est rechargée (`loadSource`).
 *
 * Le durcissement « visible mais non récupérable » (`hardenPlayer`, filigrane)
 * reste celui de media-guard.ts.
 */

import { ApiError, api } from '../api/client';
import type { VideoPlaybackSource } from '../domain/types';
import { sessionStore } from '../stores';

const SELECTOR = 'video[data-video-ref]';
const HLS_MIME = 'application/vnd.apple.mpegurl';
const PROCESSING_RETRY_MS = 30_000;
const MAX_PROCESSING_RETRIES = 3;
const RENEW_BEFORE_MS = 60_000;
const MIN_RENEW_DELAY_MS = 5_000;
/** Renouvellements consécutifs sur erreur avant d'abandonner (évite une boucle). */
const MAX_ERROR_RENEWALS = 3;
/** Récupérations hls.js sur erreur média fatale avant d'abandonner. */
const MAX_MEDIA_RECOVERIES = 2;

type PlaceholderKind = 'processing' | 'unavailable' | 'offline';

const MESSAGES: Record<PlaceholderKind, { icon: string; text: string }> = {
  processing: { icon: 'bi-hourglass-split', text: 'Vidéo en cours de préparation' },
  unavailable: { icon: 'bi-camera-video-off', text: 'Vidéo indisponible' },
  offline: { icon: 'bi-wifi-off', text: 'Vidéo disponible en ligne uniquement.' },
};

/** Sous-ensemble de l'API hls.js utilisé ici. */
interface HlsInstance {
  loadSource(url: string): void;
  attachMedia(media: HTMLMediaElement): void;
  destroy(): void;
  recoverMediaError(): void;
  on(event: string, handler: (event: string, data: HlsErrorData) => void): void;
}

interface HlsErrorData {
  fatal?: boolean;
  type?: string;
}

interface HlsModule {
  isSupported(): boolean;
  new (): HlsInstance;
  Events: { ERROR: string };
  ErrorTypes: { NETWORK_ERROR: string; MEDIA_ERROR: string };
}

interface Resume {
  time: number;
  playing: boolean;
}

interface Controller {
  video: HTMLVideoElement;
  ref: string;
  hls: HlsInstance | null;
  placeholder: HTMLElement | null;
  hasSource: boolean;
  expiresAt: number | null;
  renewTimer: number | null;
  processingRetries: number;
  errorRenewals: number;
  mediaRecoveries: number;
  renewing: boolean;
  pendingResume: (() => void) | null;
  stopped: boolean;
}

const controllers = new WeakMap<HTMLVideoElement, Controller>();
/** Vidéos en attente du réseau (rechargées sur l'événement `online`). */
const waitingForNetwork = new Set<Controller>();
/** Lecteurs hls.js vivants : détruits quand leur vidéo quitte le DOM. */
const liveHls = new Set<Controller>();

let globalsInstalled = false;

function installGlobals(): void {
  if (globalsInstalled) return;
  globalsInstalled = true;

  window.addEventListener('online', () => {
    const pending = [...waitingForNetwork];
    waitingForNetwork.clear();
    pending.forEach((c) => {
      if (c.video.isConnected && !c.stopped) void load(c, null);
    });
  });

  if (typeof MutationObserver !== 'undefined' && document.body) {
    new MutationObserver((mutations) => {
      if (!liveHls.size || !mutations.some((m) => m.removedNodes.length)) return;
      liveHls.forEach((c) => {
        if (!c.video.isConnected) stop(c);
      });
    }).observe(document.body, { childList: true, subtree: true });
  }
}

function isGuestSession(): boolean {
  return sessionStore.get()?.is_guest === true;
}

// ---------------------------------------------------------- Placeholder

function showPlaceholder(c: Controller, kind: PlaceholderKind): void {
  let el = c.placeholder;
  if (!el) {
    el = document.createElement('div');
    el.className = 'video-placeholder';
    el.setAttribute('role', 'status');
    c.video.before(el);
    c.placeholder = el;
  }
  const { icon, text } = MESSAGES[kind];
  el.dataset.state = kind;
  el.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i> <span></span>`;
  el.querySelector('span')!.textContent = text;
  c.video.hidden = true;
}

function clearPlaceholder(c: Controller): void {
  c.placeholder?.remove();
  c.placeholder = null;
  c.video.hidden = false;
}

function giveUp(c: Controller): void {
  showPlaceholder(c, 'unavailable');
  stop(c);
}

// ------------------------------------------------------------ Lifecycle

function stop(c: Controller): void {
  c.stopped = true;
  if (c.renewTimer !== null) window.clearTimeout(c.renewTimer);
  c.renewTimer = null;
  c.hls?.destroy();
  c.hls = null;
  liveHls.delete(c);
  waitingForNetwork.delete(c);
}

function scheduleRenewal(c: Controller): void {
  if (c.renewTimer !== null) window.clearTimeout(c.renewTimer);
  c.renewTimer = null;
  if (c.expiresAt === null) return;

  const delay = Math.max(MIN_RENEW_DELAY_MS, c.expiresAt - RENEW_BEFORE_MS - Date.now());
  c.renewTimer = window.setTimeout(() => {
    c.renewTimer = null;
    if (!c.video.isConnected) {
      stop(c);
      return;
    }
    void renew(c);
  }, delay);
}

function isNearExpiry(c: Controller): boolean {
  return c.expiresAt !== null && Date.now() >= c.expiresAt - RENEW_BEFORE_MS;
}

/** Après changement de source : reprise à la position et dans l'état d'avant. */
function armResume(c: Controller, resume: Resume): void {
  if (c.pendingResume) c.video.removeEventListener('loadedmetadata', c.pendingResume);
  const handler = (): void => {
    c.pendingResume = null;
    if (resume.time > 0) {
      try {
        c.video.currentTime = resume.time;
      } catch {
        /* média pas encore prêt : la lecture reprend au début */
      }
    }
    if (resume.playing) void c.video.play()?.catch(() => undefined);
  };
  c.pendingResume = handler;
  c.video.addEventListener('loadedmetadata', handler, { once: true });
}

async function renew(c: Controller): Promise<void> {
  if (c.renewing || c.stopped) return;
  c.renewing = true;
  const resume: Resume = {
    time: c.video.currentTime || 0,
    playing: !c.video.paused && !c.video.ended,
  };
  try {
    await load(c, resume);
  } finally {
    c.renewing = false;
  }
}

// --------------------------------------------------------------- Source

async function load(c: Controller, resume: Resume | null): Promise<void> {
  if (c.stopped) return;

  if (!navigator.onLine) {
    // Renouvellement hors ligne : la source courante continue tant qu'elle peut.
    if (!resume) showPlaceholder(c, 'offline');
    waitingForNetwork.add(c);
    return;
  }

  let response;
  try {
    response = await api.videoPlayback(c.ref, isGuestSession());
  } catch (e) {
    if (e instanceof ApiError) {
      giveUp(c);
    } else if (resume) {
      // Réseau instable pendant un renouvellement : nouvel essai plus tard.
      c.expiresAt = Date.now() + RENEW_BEFORE_MS + PROCESSING_RETRY_MS;
      scheduleRenewal(c);
    } else {
      showPlaceholder(c, 'offline');
      waitingForNetwork.add(c);
    }
    return;
  }

  if (c.stopped) return;

  if ('status' in response) {
    showPlaceholder(c, 'processing');
    if (c.processingRetries < MAX_PROCESSING_RETRIES) {
      c.processingRetries++;
      window.setTimeout(() => {
        if (c.video.isConnected) void load(c, null);
      }, PROCESSING_RETRY_MS);
    }
    return;
  }

  await apply(c, response, resume);
}

async function apply(c: Controller, source: VideoPlaybackSource, resume: Resume | null): Promise<void> {
  const parsed = source.expires_at ? Date.parse(source.expires_at) : NaN;
  c.expiresAt = Number.isFinite(parsed) ? parsed : null;
  if (resume && c.hasSource) armResume(c, resume);

  const nativeHls = source.type === 'hls' && c.video.canPlayType(HLS_MIME) !== '';

  if (source.type === 'hls' && !nativeHls) {
    if (c.hls) {
      c.hls.loadSource(source.url);
    } else {
      const Hls = ((await import('hls.js')) as unknown as { default: HlsModule }).default;
      if (c.stopped) return;
      if (!Hls.isSupported()) {
        giveUp(c);
        return;
      }
      const hls = new Hls();
      hls.on(Hls.Events.ERROR, (_event, data) => onHlsError(c, Hls, hls, data));
      hls.loadSource(source.url);
      hls.attachMedia(c.video);
      c.hls = hls;
      liveHls.add(c);
    }
  } else {
    if (c.hls) {
      c.hls.destroy();
      c.hls = null;
      liveHls.delete(c);
    }
    c.video.src = source.url;
  }

  c.hasSource = true;
  clearPlaceholder(c);
  scheduleRenewal(c);
}

function onHlsError(c: Controller, Hls: HlsModule, hls: HlsInstance, data: HlsErrorData): void {
  if (!data.fatal) return;
  if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
    // Segment signé expiré (403) ou coupure : on recharge la playlist.
    renewAfterError(c);
  } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR && c.mediaRecoveries < MAX_MEDIA_RECOVERIES) {
    // Borné : un flux indécodable ne doit pas boucler indéfiniment.
    c.mediaRecoveries++;
    hls.recoverMediaError();
  } else {
    giveUp(c);
  }
}

function renewAfterError(c: Controller): void {
  if (c.errorRenewals >= MAX_ERROR_RENEWALS) {
    giveUp(c);
    return;
  }
  c.errorRenewals++;
  void renew(c);
}

function attach(c: Controller): void {
  const { video } = c;
  // Lecteur natif (mp4 ou HLS Safari) : 403 / URL expirée → événement `error`.
  video.addEventListener('error', () => {
    if (c.hasSource && !c.hls && !c.stopped) renewAfterError(c);
  });
  video.addEventListener('playing', () => {
    c.errorRenewals = 0;
  });
  // Onglet en arrière-plan : le minuteur a pu être retardé.
  video.addEventListener('play', () => {
    if (c.hasSource && isNearExpiry(c)) void renew(c);
  });
}

/** Résout chaque `video[data-video-ref]` sans `src` de la portée (idempotent). */
export function resolveVideoRefs(scope: ParentNode): void {
  installGlobals();
  scope.querySelectorAll<HTMLVideoElement>(SELECTOR).forEach((video) => {
    if (controllers.has(video) || video.hasAttribute('src')) return;
    const ref = video.dataset.videoRef ?? '';
    if (!ref) return;

    const c: Controller = {
      video,
      ref,
      hls: null,
      placeholder: null,
      hasSource: false,
      expiresAt: null,
      renewTimer: null,
      processingRetries: 0,
      errorRenewals: 0,
      mediaRecoveries: 0,
      renewing: false,
      pendingResume: null,
      stopped: false,
    };
    controllers.set(video, c);
    attach(c);
    void load(c, null);
  });
}
