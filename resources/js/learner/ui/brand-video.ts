/**
 * Lecteur de marque des vidéos YouTube / Vimeo du contenu pédagogique.
 *
 * Le contenu ne stocke que `<div class="brand-video" data-embed="youtube|vimeo"
 * data-id="…" [data-hash="…"]>` ; les anciennes iframes brutes (ancien bouton
 * de l'éditeur) sont converties à l'affichage et ne sont jamais montrées.
 *
 * - Lecteur du fournisseur sans ses commandes (YouTube nocookie, Vimeo dnt),
 *   piloté par son API (IFrame API / player.js, chargées une seule fois, à la
 *   demande).
 * - Calque transparent plein cadre au-dessus de l'iframe : il bloque clics,
 *   appui long et menu contextuel (donc les liens « Regarder sur YouTube »,
 *   titre, logo…) et porte le filigrane nominatif (--wm-tile).
 * - Barre de commandes maison : lecture/pause, progression, temps, muet,
 *   volume, plein écran de NOTRE conteneur (jamais celui du fournisseur).
 * - En ligne uniquement : hors ligne, « Vidéo disponible en ligne uniquement. » ;
 *   embeds désactivés ou fournisseur non autorisé, « Vidéo indisponible ».
 *
 * Limite assumée : une capture d'écran reste possible — d'où le filigrane.
 */

import type { VideoEmbedPolicy } from '../domain/types';

type Provider = 'youtube' | 'vimeo';

interface EmbedRef {
  provider: Provider;
  id: string;
  hash?: string;
}

const YT_ID = /^[A-Za-z0-9_-]{11}$/;
const VIMEO_ID = /^\d+$/;
const VIMEO_HASH = /^[a-f0-9]+$/i;

const YT_API = 'https://www.youtube.com/iframe_api';
const YT_HOST = 'https://www.youtube-nocookie.com';
const VIMEO_API = 'https://player.vimeo.com/api/player.js';

const LEGACY_IFRAME =
  'iframe[src*="youtube.com/embed/"], iframe[src*="youtube-nocookie.com/embed/"], iframe[src*="player.vimeo.com/video/"]';
const EMBED_SELECTOR = '[data-embed]';

const MSG_OFFLINE = 'Vidéo disponible en ligne uniquement.';
const MSG_UNAVAILABLE = 'Vidéo indisponible';

// --------------------------------------------------------------- Réglages

let policyResolver: () => VideoEmbedPolicy | null | undefined = () => null;
let lastWatermark: string | null = null;

/**
 * Source du réglage admin `video.embeds` (profil apprenant ou vitrine).
 * Inconnu (null) → autorisé, comme la valeur par défaut côté serveur.
 */
export function setVideoEmbedPolicy(resolver: () => VideoEmbedPolicy | null | undefined): void {
  policyResolver = resolver;
}

function providerAllowed(provider: Provider): boolean {
  const policy = policyResolver();
  if (!policy) return true;
  return policy.enabled && Array.isArray(policy.providers) && policy.providers.includes(provider);
}

// ------------------------------------------------------------- Références

function readRef(box: HTMLElement): EmbedRef | null {
  const provider = box.dataset.embed;
  const id = box.dataset.id ?? '';
  if (provider === 'youtube' && YT_ID.test(id)) return { provider, id };
  if (provider === 'vimeo' && VIMEO_ID.test(id)) {
    const hash = box.dataset.hash;
    return hash && VIMEO_HASH.test(hash) ? { provider, id, hash } : { provider, id };
  }
  return null;
}

/** Ancienne iframe d'embed → référence (fournisseur, identifiant, hash Vimeo). */
function parseLegacySrc(src: string | null): EmbedRef | null {
  if (!src) return null;
  let url: URL;
  try {
    url = new URL(src, 'https://invalid.local');
  } catch {
    return null;
  }
  const host = url.hostname.toLowerCase();
  const parts = url.pathname.split('/').filter(Boolean);

  if (/(^|\.)youtube(-nocookie)?\.com$/.test(host) && parts[0] === 'embed') {
    const id = parts[1] ?? '';
    return YT_ID.test(id) ? { provider: 'youtube', id } : null;
  }
  if (host === 'player.vimeo.com' && parts[0] === 'video') {
    const id = parts[1] ?? '';
    if (!VIMEO_ID.test(id)) return null;
    const hash = url.searchParams.get('h');
    return hash && VIMEO_HASH.test(hash) ? { provider: 'vimeo', id, hash } : { provider: 'vimeo', id };
  }
  return null;
}

function convertLegacyIframe(iframe: HTMLIFrameElement): void {
  const ref = parseLegacySrc(iframe.getAttribute('src'));
  const box = document.createElement('div');
  box.className = 'brand-video';
  box.dataset.embed = ref?.provider ?? 'unknown';
  if (ref) {
    box.dataset.id = ref.id;
    if (ref.hash) box.dataset.hash = ref.hash;
  }
  iframe.replaceWith(box);
}

// --------------------------------------------------- Scripts fournisseurs

const scripts = new Map<string, Promise<void>>();

function loadScript(src: string): Promise<void> {
  const cached = scripts.get(src);
  if (cached) return cached;
  const promise = new Promise<void>((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => {
      scripts.delete(src);
      script.remove();
      reject(new Error(`Chargement impossible : ${src}`));
    };
    document.head.append(script);
  });
  scripts.set(src, promise);
  return promise;
}

interface YTPlayer {
  playVideo(): void;
  pauseVideo(): void;
  seekTo(seconds: number, allowSeekAhead: boolean): void;
  mute(): void;
  unMute(): void;
  setVolume(volume: number): void;
  getCurrentTime(): number;
  getDuration(): number;
  destroy(): void;
}

interface YTNamespace {
  Player: new (
    target: HTMLElement,
    options: {
      host: string;
      events: { onReady: () => void; onStateChange: (event: { data: number }) => void };
    },
  ) => YTPlayer;
}

interface VimeoPlayer {
  play(): Promise<unknown>;
  pause(): Promise<unknown>;
  setCurrentTime(seconds: number): Promise<unknown>;
  setVolume(volume: number): Promise<unknown>;
  setMuted?(muted: boolean): Promise<unknown>;
  getDuration(): Promise<number>;
  ready(): Promise<unknown>;
  on(event: string, handler: (data: { seconds?: number; duration?: number }) => void): void;
  destroy(): Promise<unknown>;
}

interface VimeoNamespace {
  Player: new (target: HTMLIFrameElement) => VimeoPlayer;
}

type ProviderWindow = Window & {
  YT?: YTNamespace;
  Vimeo?: VimeoNamespace;
  onYouTubeIframeAPIReady?: () => void;
};

const providerWindow = window as ProviderWindow;
let ytPromise: Promise<YTNamespace> | null = null;

function loadYouTubeApi(): Promise<YTNamespace> {
  if (providerWindow.YT?.Player) return Promise.resolve(providerWindow.YT);
  if (ytPromise) return ytPromise;
  ytPromise = new Promise<YTNamespace>((resolve, reject) => {
    const previous = providerWindow.onYouTubeIframeAPIReady;
    providerWindow.onYouTubeIframeAPIReady = () => {
      previous?.();
      ytPromise = null;
      if (providerWindow.YT?.Player) resolve(providerWindow.YT);
      else reject(new Error('API YouTube indisponible'));
    };
    loadScript(YT_API).catch((e: unknown) => {
      ytPromise = null;
      reject(e);
    });
  });
  return ytPromise;
}

async function loadVimeoApi(): Promise<VimeoNamespace> {
  if (!providerWindow.Vimeo?.Player) await loadScript(VIMEO_API);
  if (!providerWindow.Vimeo?.Player) throw new Error('API Vimeo indisponible');
  return providerWindow.Vimeo;
}

// --------------------------------------------------------------- Adaptateurs

interface PlayerEvents {
  ready(duration: number): void;
  state(playing: boolean): void;
  time(current: number, duration: number): void;
}

interface Adapter {
  play(): void;
  pause(): void;
  seek(seconds: number): void;
  setVolume(percent: number): void;
  setMuted(muted: boolean): void;
  destroy(): void;
}

function providerIframe(src: string): HTMLIFrameElement {
  const iframe = document.createElement('iframe');
  iframe.src = src;
  iframe.title = 'Lecteur vidéo';
  iframe.tabIndex = -1;
  iframe.setAttribute('allow', 'autoplay; encrypted-media');
  iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
  iframe.setAttribute('aria-hidden', 'true');
  return iframe;
}

async function createYouTube(slot: HTMLElement, ref: EmbedRef, events: PlayerEvents): Promise<Adapter> {
  const params = new URLSearchParams({
    enablejsapi: '1',
    controls: '0',
    rel: '0',
    modestbranding: '1',
    disablekb: '1',
    fs: '0',
    playsinline: '1',
    iv_load_policy: '3',
  });
  if (/^https?:\/\//.test(window.location.origin)) params.set('origin', window.location.origin);
  const iframe = providerIframe(`${YT_HOST}/embed/${ref.id}?${params.toString()}`);
  slot.append(iframe);

  const YT = await loadYouTubeApi();
  let poll: number | null = null;
  const stopPoll = (): void => {
    if (poll !== null) window.clearInterval(poll);
    poll = null;
  };

  const player: YTPlayer = new YT.Player(iframe, {
    host: YT_HOST,
    events: {
      onReady: () => events.ready(player.getDuration()),
      onStateChange: ({ data }) => {
        const playing = data === 1;
        events.state(playing);
        stopPoll();
        if (playing) {
          poll = window.setInterval(() => {
            if (!iframe.isConnected) {
              stopPoll();
              player.destroy();
              return;
            }
            events.time(player.getCurrentTime(), player.getDuration());
          }, 250);
        } else {
          events.time(player.getCurrentTime(), player.getDuration());
        }
      },
    },
  });

  return {
    play: () => player.playVideo(),
    pause: () => player.pauseVideo(),
    seek: (seconds) => player.seekTo(seconds, true),
    setVolume: (percent) => player.setVolume(percent),
    setMuted: (muted) => (muted ? player.mute() : player.unMute()),
    destroy: () => {
      stopPoll();
      player.destroy();
    },
  };
}

async function createVimeo(slot: HTMLElement, ref: EmbedRef, events: PlayerEvents): Promise<Adapter> {
  const params = new URLSearchParams();
  if (ref.hash) params.set('h', ref.hash);
  params.set('title', '0');
  params.set('byline', '0');
  params.set('portrait', '0');
  params.set('controls', '0');
  params.set('dnt', '1');
  const iframe = providerIframe(`https://player.vimeo.com/video/${ref.id}?${params.toString()}`);
  slot.append(iframe);

  const Vimeo = await loadVimeoApi();
  const player = new Vimeo.Player(iframe);
  let volume = 1;
  const ignore = (): undefined => undefined;

  player.on('play', () => events.state(true));
  player.on('pause', () => events.state(false));
  player.on('ended', () => events.state(false));
  player.on('timeupdate', (data) => events.time(data.seconds ?? 0, data.duration ?? 0));
  player
    .ready()
    .then(() => player.getDuration())
    .then((duration) => events.ready(duration))
    .catch(ignore);

  return {
    play: () => void player.play().catch(ignore),
    pause: () => void player.pause().catch(ignore),
    seek: (seconds) => void player.setCurrentTime(seconds).catch(ignore),
    setVolume: (percent) => {
      volume = percent / 100;
      void player.setVolume(volume).catch(ignore);
    },
    setMuted: (muted) => {
      if (player.setMuted) void player.setMuted(muted).catch(ignore);
      else void player.setVolume(muted ? 0 : volume).catch(ignore);
    },
    destroy: () => void player.destroy().catch(ignore),
  };
}

// ----------------------------------------------------------------- Lecteur

function formatTime(seconds: number): string {
  const total = Math.max(0, Math.floor(Number.isFinite(seconds) ? seconds : 0));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = String(total % 60).padStart(2, '0');
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

function showPlaceholder(box: HTMLElement, text: string, state: 'offline' | 'unavailable'): void {
  box.classList.add('brand-video--placeholder');
  box.dataset.bvState = state;
  const message = document.createElement('p');
  message.className = 'brand-video__message';
  message.setAttribute('role', 'status');
  message.textContent = text;
  box.replaceChildren(message);
}

const CONTROLS_HTML = `
  <div class="brand-video__frame">
    <div class="brand-video__player"></div>
    <div class="brand-video__overlay" aria-hidden="true"></div>
  </div>
  <div class="brand-video__controls" role="group" aria-label="Commandes de la vidéo">
    <button type="button" class="brand-video__btn" data-bv="play" aria-label="Lire la vidéo"><i class="bi bi-play-fill" aria-hidden="true"></i></button>
    <input type="range" class="brand-video__seek" data-bv="seek" min="0" max="0" step="0.1" value="0" aria-label="Position dans la vidéo">
    <span class="brand-video__time" data-bv="time">0:00 / 0:00</span>
    <button type="button" class="brand-video__btn" data-bv="mute" aria-label="Couper le son"><i class="bi bi-volume-up-fill" aria-hidden="true"></i></button>
    <input type="range" class="brand-video__volume" data-bv="volume" min="0" max="100" step="1" value="100" aria-label="Volume">
    <button type="button" class="brand-video__btn" data-bv="fullscreen" aria-label="Plein écran"><i class="bi bi-fullscreen" aria-hidden="true"></i></button>
  </div>`;

function mountPlayer(box: HTMLElement, ref: EmbedRef, watermark: string | null): void {
  box.dataset.bvState = 'ready';
  box.classList.remove('brand-video--placeholder');
  box.innerHTML = CONTROLS_HTML;

  const q = <T extends HTMLElement>(name: string): T => box.querySelector<T>(`[data-bv="${name}"]`)!;
  const slot = box.querySelector<HTMLElement>('.brand-video__player')!;
  const overlay = box.querySelector<HTMLElement>('.brand-video__overlay')!;
  const playBtn = q<HTMLButtonElement>('play');
  const seek = q<HTMLInputElement>('seek');
  const time = q<HTMLElement>('time');
  const muteBtn = q<HTMLButtonElement>('mute');
  const volume = q<HTMLInputElement>('volume');
  const fsBtn = q<HTMLButtonElement>('fullscreen');

  if (watermark) overlay.classList.add('brand-video__overlay--wm');

  let adapter: Adapter | null = null;
  let playing = false;
  let muted = false;
  let duration = 0;
  let seeking = false;

  const renderPlay = (): void => {
    playBtn.setAttribute('aria-label', playing ? 'Mettre en pause' : 'Lire la vidéo');
    playBtn.innerHTML = `<i class="bi ${playing ? 'bi-pause-fill' : 'bi-play-fill'}" aria-hidden="true"></i>`;
    box.classList.toggle('is-playing', playing);
  };
  const renderTime = (current: number): void => {
    time.textContent = `${formatTime(current)} / ${formatTime(duration)}`;
    if (!seeking) seek.value = String(current);
  };
  const renderMute = (): void => {
    muteBtn.setAttribute('aria-label', muted ? 'Rétablir le son' : 'Couper le son');
    muteBtn.innerHTML = `<i class="bi ${muted ? 'bi-volume-mute-fill' : 'bi-volume-up-fill'}" aria-hidden="true"></i>`;
  };

  const events: PlayerEvents = {
    ready: (d) => {
      duration = d || 0;
      seek.max = String(duration);
      renderTime(0);
    },
    state: (value) => {
      playing = value;
      renderPlay();
    },
    time: (current, d) => {
      if (d) {
        duration = d;
        seek.max = String(d);
      }
      renderTime(current);
    },
  };

  const togglePlay = (): void => {
    if (!adapter) return;
    if (playing) adapter.pause();
    else adapter.play();
  };

  // Le calque reçoit tous les gestes destinés à l'iframe du fournisseur.
  overlay.addEventListener('click', togglePlay);
  box.addEventListener('contextmenu', (event) => event.preventDefault());
  overlay.addEventListener('dblclick', (event) => event.preventDefault());

  playBtn.addEventListener('click', togglePlay);
  seek.addEventListener('input', () => {
    seeking = true;
    time.textContent = `${formatTime(Number(seek.value))} / ${formatTime(duration)}`;
  });
  seek.addEventListener('change', () => {
    seeking = false;
    adapter?.seek(Number(seek.value));
  });
  muteBtn.addEventListener('click', () => {
    muted = !muted;
    adapter?.setMuted(muted);
    renderMute();
  });
  volume.addEventListener('input', () => {
    const value = Number(volume.value);
    adapter?.setVolume(value);
    if (muted && value > 0) {
      muted = false;
      adapter?.setMuted(false);
      renderMute();
    }
  });

  fsBtn.addEventListener('click', () => {
    const doc = document as Document & { webkitFullscreenElement?: Element; webkitExitFullscreen?: () => void };
    const el = box as HTMLElement & { webkitRequestFullscreen?: () => void };
    if (doc.fullscreenElement === box || doc.webkitFullscreenElement === box) {
      if (doc.exitFullscreen) void doc.exitFullscreen().catch(() => undefined);
      else doc.webkitExitFullscreen?.();
    } else if (box.classList.contains('brand-video--pseudo-fs')) {
      box.classList.remove('brand-video--pseudo-fs');
    } else if (typeof el.requestFullscreen === 'function') {
      void Promise.resolve(el.requestFullscreen()).catch(() => box.classList.add('brand-video--pseudo-fs'));
    } else if (typeof el.webkitRequestFullscreen === 'function') {
      el.webkitRequestFullscreen();
    } else {
      // iOS (pas de plein écran d'élément) : plein écran simulé en CSS.
      box.classList.add('brand-video--pseudo-fs');
    }
  });
  const onFullscreenChange = (): void => {
    const active = document.fullscreenElement === box || box.classList.contains('brand-video--pseudo-fs');
    fsBtn.setAttribute('aria-label', active ? 'Quitter le plein écran' : 'Plein écran');
    fsBtn.innerHTML = `<i class="bi ${active ? 'bi-fullscreen-exit' : 'bi-fullscreen'}" aria-hidden="true"></i>`;
  };
  box.addEventListener('fullscreenchange', onFullscreenChange);
  fsBtn.addEventListener('click', () => window.setTimeout(onFullscreenChange, 0));

  const create = ref.provider === 'youtube' ? createYouTube : createVimeo;
  create(slot, ref, events)
    .then((created) => {
      adapter = created;
    })
    .catch(() => {
      showPlaceholder(box, navigator.onLine ? MSG_UNAVAILABLE : MSG_OFFLINE, navigator.onLine ? 'unavailable' : 'offline');
    });
}

function mountOne(box: HTMLElement, watermark: string | null): void {
  const state = box.dataset.bvState;
  if (state === 'ready' || state === 'unavailable') return;
  if (state === 'offline' && !navigator.onLine) return;

  box.classList.add('brand-video');
  box.removeAttribute('contenteditable');

  const ref = readRef(box);
  if (!ref || !providerAllowed(ref.provider)) {
    showPlaceholder(box, MSG_UNAVAILABLE, 'unavailable');
    return;
  }
  if (!navigator.onLine) {
    showPlaceholder(box, MSG_OFFLINE, 'offline');
    return;
  }
  mountPlayer(box, ref, watermark);
}

// ------------------------------------------------------------ Installation

let onlineListener = false;

function installOnlineListener(): void {
  if (onlineListener) return;
  onlineListener = true;
  window.addEventListener('online', () => {
    document.querySelectorAll<HTMLElement>('[data-embed][data-bv-state="offline"]').forEach((box) => {
      mountOne(box, lastWatermark);
    });
  });
}

/**
 * Monte le lecteur de marque sur chaque `[data-embed]` de la portée et
 * convertit les anciennes iframes YouTube / Vimeo (idempotent).
 */
export function mountBrandVideos(scope: ParentNode, watermark: string | null): void {
  lastWatermark = watermark;
  installOnlineListener();

  scope.querySelectorAll<HTMLIFrameElement>(LEGACY_IFRAME).forEach((iframe) => {
    // Nos propres iframes (lecteur déjà monté) ne sont pas reconverties.
    if (!iframe.closest('.brand-video')) convertLegacyIframe(iframe);
  });

  scope.querySelectorAll<HTMLElement>(EMBED_SELECTOR).forEach((box) => mountOne(box, watermark));
}
