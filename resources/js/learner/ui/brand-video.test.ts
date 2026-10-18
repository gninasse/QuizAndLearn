import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mountBrandVideos, PLAY_FALLBACK_MS, setVideoEmbedPolicy } from './brand-video';

const YT_ID = 'dQw4w9WgXcQ';

/** Faux lecteur YouTube (API IFrame) : enveloppe l'iframe fournie. */
class FakeYTPlayer {
  static instances: FakeYTPlayer[] = [];
  playVideo = vi.fn();
  pauseVideo = vi.fn();
  seekTo = vi.fn();
  mute = vi.fn();
  unMute = vi.fn();
  setVolume = vi.fn();
  isMuted = vi.fn(() => false);
  getCurrentTime = vi.fn(() => 0);
  getDuration = vi.fn(() => 120);
  getPlayerState = vi.fn(() => -1);
  destroy = vi.fn();
  constructor(
    readonly target: HTMLElement,
    readonly options: { host?: string; events?: { onReady?: () => void; onStateChange?: (e: { data: number }) => void } },
  ) {
    FakeYTPlayer.instances.push(this);
  }
}

class FakeVimeoPlayer {
  static instances: FakeVimeoPlayer[] = [];
  play = vi.fn(async () => undefined);
  pause = vi.fn(async () => undefined);
  setCurrentTime = vi.fn(async () => 0);
  setVolume = vi.fn(async () => 1);
  setMuted = vi.fn(async () => false);
  getDuration = vi.fn(async () => 60);
  ready = vi.fn(async () => undefined);
  on = vi.fn();
  destroy = vi.fn(async () => undefined);
  constructor(readonly target: HTMLElement) {
    FakeVimeoPlayer.instances.push(this);
  }
}

async function settle(): Promise<void> {
  for (let i = 0; i < 20; i++) await new Promise<void>((resolve) => queueMicrotask(resolve));
}

function setOnline(value: boolean): void {
  Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => value });
}

const win = window as unknown as Record<string, unknown>;

beforeEach(() => {
  FakeYTPlayer.instances = [];
  FakeVimeoPlayer.instances = [];
  win.YT = { Player: FakeYTPlayer };
  win.Vimeo = { Player: FakeVimeoPlayer };
  setOnline(true);
  setVideoEmbedPolicy(() => ({ enabled: true, providers: ['youtube', 'vimeo'] }));
});

afterEach(() => {
  delete win.YT;
  delete win.Vimeo;
  delete win.onYouTubeIframeAPIReady;
  document.head.querySelectorAll('script').forEach((s) => s.remove());
  document.body.innerHTML = '';
});

describe('mountBrandVideos', () => {
  it('legacy_youtube_iframe_replaced_by_brand_player', async () => {
    document.body.innerHTML = `<div class="rich-content"><p>Intro</p><iframe width="560" height="315" src="https://www.youtube.com/embed/${YT_ID}?rel=0&amp;showinfo=0" frameborder="0" allowfullscreen></iframe></div>`;

    mountBrandVideos(document, null);
    await settle();

    expect(document.querySelector('iframe[src*="youtube.com/embed/"]')).toBeNull();
    const box = document.querySelector<HTMLElement>('.brand-video')!;
    expect(box).not.toBeNull();
    expect(box.dataset.embed).toBe('youtube');
    expect(box.dataset.id).toBe(YT_ID);
    const frames = document.querySelectorAll('iframe');
    expect(frames).toHaveLength(1);
    expect(frames[0]!.getAttribute('src')).toContain(`https://www.youtube-nocookie.com/embed/${YT_ID}?`);
    expect(FakeYTPlayer.instances).toHaveLength(1);

    // Un second passage ne reconvertit pas notre propre iframe (nocookie).
    mountBrandVideos(document, null);
    await settle();
    expect(document.querySelectorAll('.brand-video')).toHaveLength(1);
    expect(FakeYTPlayer.instances).toHaveLength(1);
  });

  it('legacy vimeo iframe keeps its privacy hash', async () => {
    document.body.innerHTML = '<div class="rich-content"><iframe src="https://player.vimeo.com/video/76979871?h=8272103f6e&amp;badge=0"></iframe></div>';

    mountBrandVideos(document, null);
    await settle();

    const box = document.querySelector<HTMLElement>('.brand-video')!;
    expect(box.dataset.embed).toBe('vimeo');
    expect(box.dataset.id).toBe('76979871');
    expect(box.dataset.hash).toBe('8272103f6e');
  });

  it('unrecognised legacy iframe is removed, never displayed', async () => {
    document.body.innerHTML = '<div class="rich-content"><iframe src="https://www.youtube.com/embed/oops"></iframe></div>';

    mountBrandVideos(document, null);
    await settle();

    expect(document.querySelector('iframe')).toBeNull();
    expect(document.querySelector('.brand-video')!.textContent).toContain('Vidéo indisponible');
  });

  it('brand_player_has_overlay_and_no_provider_links', async () => {
    document.body.innerHTML = `<div class="rich-content"><div class="brand-video" data-embed="youtube" data-id="${YT_ID}" contenteditable="false"><span>Vidéo YouTube</span></div></div>`;

    mountBrandVideos(document, 'Jean Dupont · M001');
    await settle();

    const box = document.querySelector<HTMLElement>('.brand-video')!;
    expect(box.hasAttribute('contenteditable')).toBe(false);
    expect(box.querySelectorAll('a')).toHaveLength(0);
    expect(document.querySelector('a[href*="youtube"], a[href*="youtu.be"], a[href*="vimeo"]')).toBeNull();

    const iframe = box.querySelector('iframe')!;
    const src = new URL(iframe.getAttribute('src')!);
    expect(src.host).toBe('www.youtube-nocookie.com');
    expect(src.pathname).toBe(`/embed/${YT_ID}`);
    for (const [key, value] of Object.entries({ controls: '0', rel: '0', modestbranding: '1', disablekb: '1', fs: '0', playsinline: '1', iv_load_policy: '3', enablejsapi: '1' })) {
      expect(src.searchParams.get(key)).toBe(value);
    }
    expect(FakeYTPlayer.instances[0]!.options.host).toBe('https://www.youtube-nocookie.com');

    // Calque plein cadre au-dessus de l'iframe : filigrane, clic droit bloqué.
    const overlay = box.querySelector<HTMLElement>('.brand-video__overlay')!;
    expect(overlay).not.toBeNull();
    expect(overlay.classList.contains('brand-video__overlay--wm')).toBe(true);
    const menu = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    overlay.dispatchEvent(menu);
    expect(menu.defaultPrevented).toBe(true);

    // Contrôles maison, tous libellés en français.
    const controls = [...box.querySelectorAll<HTMLElement>('button, input')];
    expect(controls.length).toBeGreaterThanOrEqual(5);
    controls.forEach((control) => expect(control.getAttribute('aria-label')).toBeTruthy());
    expect(box.querySelector('[data-bv="play"]')!.getAttribute('aria-label')).toBe('Lire la vidéo');

    // Lecture pilotée par nos boutons via l'API du fournisseur.
    const player = FakeYTPlayer.instances[0]!;
    player.options.events!.onReady!();
    box.querySelector<HTMLButtonElement>('[data-bv="play"]')!.click();
    expect(player.playVideo).toHaveBeenCalled();
    player.options.events!.onStateChange!({ data: 1 });
    expect(box.querySelector('[data-bv="play"]')!.getAttribute('aria-label')).toBe('Mettre en pause');
    overlay.click();
    expect(player.pauseVideo).toHaveBeenCalled();
  });

  it('fullscreen targets our container, not the provider iframe', async () => {
    document.body.innerHTML = `<div class="rich-content"><div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div></div>`;
    mountBrandVideos(document, null);
    await settle();

    const box = document.querySelector<HTMLElement>('.brand-video')!;
    const request = vi.fn(async () => undefined);
    (box as unknown as { requestFullscreen: typeof request }).requestFullscreen = request;
    FakeYTPlayer.instances[0]!.options.events!.onReady!();
    box.querySelector<HTMLButtonElement>('[data-bv="fullscreen"]')!.click();

    expect(request).toHaveBeenCalled();
  });

  it('offline_shows_online_only_message', async () => {
    setOnline(false);
    document.body.innerHTML = `<div class="rich-content"><div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div><iframe src="https://www.youtube.com/embed/${YT_ID}"></iframe></div>`;

    mountBrandVideos(document, null);
    await settle();

    expect(document.querySelector('iframe')).toBeNull();
    const boxes = document.querySelectorAll('.brand-video');
    expect(boxes).toHaveLength(2);
    boxes.forEach((box) => expect(box.textContent!.trim()).toBe('Vidéo disponible en ligne uniquement.'));
    expect(FakeYTPlayer.instances).toHaveLength(0);

    // Retour du réseau : le lecteur se monte.
    setOnline(true);
    window.dispatchEvent(new Event('online'));
    await settle();
    expect(FakeYTPlayer.instances).toHaveLength(2);
  });

  it('shows unavailable when embeds are disabled or the provider is not allowed', async () => {
    setVideoEmbedPolicy(() => ({ enabled: true, providers: ['vimeo'] }));
    document.body.innerHTML = `<div class="rich-content"><div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div></div>`;
    mountBrandVideos(document, null);
    await settle();
    expect(document.querySelector('.brand-video')!.textContent!.trim()).toBe('Vidéo indisponible');
    expect(document.querySelector('iframe')).toBeNull();

    setVideoEmbedPolicy(() => ({ enabled: false, providers: [] }));
    document.body.innerHTML = '<div class="rich-content"><div class="brand-video" data-embed="vimeo" data-id="123"></div></div>';
    mountBrandVideos(document, null);
    await settle();
    expect(document.querySelector('.brand-video')!.textContent!.trim()).toBe('Vidéo indisponible');
  });

  it('vimeo_hash_passed_as_h_param', async () => {
    document.body.innerHTML = '<div class="rich-content"><div class="brand-video" data-embed="vimeo" data-id="123456" data-hash="abcdef0123"></div></div>';

    mountBrandVideos(document, null);
    await settle();

    const src = new URL(document.querySelector('iframe')!.getAttribute('src')!);
    expect(src.host).toBe('player.vimeo.com');
    expect(src.pathname).toBe('/video/123456');
    expect(src.searchParams.get('h')).toBe('abcdef0123');
    for (const [key, value] of Object.entries({ title: '0', byline: '0', portrait: '0', controls: '0', dnt: '1' })) {
      expect(src.searchParams.get(key)).toBe(value);
    }
    expect(FakeVimeoPlayer.instances).toHaveLength(1);
    expect(FakeVimeoPlayer.instances[0]!.target).toBe(document.querySelector('iframe'));
  });

  it('loads the provider script once, on demand', async () => {
    delete win.YT;
    document.body.innerHTML = `<div class="rich-content">
      <div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div>
      <div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div></div>`;

    mountBrandVideos(document, null);
    await settle();

    const scripts = document.querySelectorAll('script[src="https://www.youtube.com/iframe_api"]');
    expect(scripts).toHaveLength(1);
    expect(document.querySelectorAll('script[src*="vimeo"]')).toHaveLength(0);

    win.YT = { Player: FakeYTPlayer };
    (win.onYouTubeIframeAPIReady as () => void)();
    await settle();
    expect(FakeYTPlayer.instances).toHaveLength(2);
  });

  describe('mobile tap-to-play fallback', () => {
    afterEach(() => {
      vi.useRealTimers();
    });

    const tapThrough = (box: HTMLElement): boolean =>
      box.querySelector('.brand-video__overlay')!.classList.contains('brand-video__overlay--tap-through');
    const hintText = (box: HTMLElement): string | null => {
      const hint = box.querySelector<HTMLElement>('.brand-video__hint')!;
      return hint.hidden ? null : hint.textContent;
    };

    it('youtube: lets the next tap reach the iframe when playback does not start, then restores the overlay', async () => {
      vi.useFakeTimers();
      document.body.innerHTML = `<div class="rich-content"><div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div></div>`;
      mountBrandVideos(document, null);
      await settle();

      const box = document.querySelector<HTMLElement>('.brand-video')!;
      const player = FakeYTPlayer.instances[0]!;
      player.options.events!.onReady!();
      box.querySelector<HTMLButtonElement>('[data-bv="play"]')!.click();
      expect(player.playVideo).toHaveBeenCalled();

      vi.advanceTimersByTime(PLAY_FALLBACK_MS - 100);
      expect(tapThrough(box)).toBe(false);
      expect(hintText(box)).toBeNull();

      vi.advanceTimersByTime(200);
      expect(tapThrough(box)).toBe(true);
      expect(hintText(box)).toBe('Touchez la vidéo pour lancer la lecture');

      // Le toucher a atteint l'iframe : lecture → calque bloquant rétabli.
      player.options.events!.onStateChange!({ data: 1 });
      expect(tapThrough(box)).toBe(false);
      expect(hintText(box)).toBeNull();
    });

    it('youtube: no fallback when playback starts in time', async () => {
      vi.useFakeTimers();
      document.body.innerHTML = `<div class="rich-content"><div class="brand-video" data-embed="youtube" data-id="${YT_ID}"></div></div>`;
      mountBrandVideos(document, null);
      await settle();

      const box = document.querySelector<HTMLElement>('.brand-video')!;
      const player = FakeYTPlayer.instances[0]!;
      player.options.events!.onReady!();
      box.querySelector<HTMLButtonElement>('[data-bv="play"]')!.click();
      player.options.events!.onStateChange!({ data: 1 });
      vi.advanceTimersByTime(PLAY_FALLBACK_MS * 2);

      expect(tapThrough(box)).toBe(false);
      expect(hintText(box)).toBeNull();
    });

    it('vimeo: a rejected play() enables tap-through immediately (never swallowed)', async () => {
      document.body.innerHTML = '<div class="rich-content"><div class="brand-video" data-embed="vimeo" data-id="123456"></div></div>';
      mountBrandVideos(document, null);
      await settle();

      const box = document.querySelector<HTMLElement>('.brand-video')!;
      const player = FakeVimeoPlayer.instances[0]!;
      player.play.mockRejectedValueOnce(new Error('NotAllowedError'));
      box.querySelector<HTMLButtonElement>('[data-bv="play"]')!.click();
      await settle();

      expect(player.play).toHaveBeenCalled();
      expect(tapThrough(box)).toBe(true);
      expect(hintText(box)).toBe('Touchez la vidéo pour lancer la lecture');

      // Événement « play » du fournisseur : calque rétabli.
      const onPlay = player.on.mock.calls.find(([name]) => name === 'play')![1] as () => void;
      onPlay();
      expect(tapThrough(box)).toBe(false);
      expect(hintText(box)).toBeNull();
    });
  });
});
