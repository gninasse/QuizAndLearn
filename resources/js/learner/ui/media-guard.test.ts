import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { dismissLightbox, installMediaGuard, isLightboxOpen, openLightbox, setWatermark } from './media-guard';

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

beforeAll(() => {
  installMediaGuard();
});

afterEach(() => {
  dismissLightbox();
  document.body.innerHTML = '';
});

describe('installMediaGuard', () => {
  it('encadre les images et vidéos du contenu pédagogique, pas celles de l’interface', async () => {
    document.body.innerHTML = `
      <img id="logo" src="/icons/logo.png">
      <div class="rich-content"><p>Texte</p><img id="pedago" src="/storage/a.png"><video id="vid" src="/storage/v.mp4"></video></div>`;
    await flush();

    expect(document.getElementById('logo')!.parentElement!.classList.contains('media-frame')).toBe(false);
    expect(document.getElementById('pedago')!.parentElement!.classList.contains('media-frame')).toBe(true);
    expect(document.querySelector('#pedago + .media-zoom')).not.toBeNull();
    expect(document.getElementById('vid')!.parentElement!.classList.contains('media-frame--block')).toBe(true);
  });

  it('retire les commandes de téléchargement des lecteurs audio/vidéo', async () => {
    document.body.innerHTML = '<div class="rich-content"><audio id="a" controls src="/storage/a.mp3"></audio></div>';
    await flush();

    const audio = document.getElementById('a')!;
    expect(audio.getAttribute('controlsList')).toContain('nodownload');
    expect(audio.hasAttribute('disableRemotePlayback')).toBe(true);
  });

  it('bloque le menu contextuel sur un média protégé uniquement', async () => {
    document.body.innerHTML = '<div class="rich-content"><img id="i" src="/storage/a.png"></div><p id="t">texte</p>';
    await flush();

    const onImage = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    document.getElementById('i')!.dispatchEvent(onImage);
    expect(onImage.defaultPrevented).toBe(true);

    const onText = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    document.getElementById('t')!.dispatchEvent(onText);
    expect(onText.defaultPrevented).toBe(false);
  });

  it("ouvre la visionneuse au clic sur l'image, sauf sur une flashcard", async () => {
    document.body.innerHTML = `
      <div class="rich-content"><img id="free" src="/storage/a.png"></div>
      <div class="flip-face"><div class="rich-content"><img id="card" src="/storage/b.png"></div></div>`;
    await flush();

    document.getElementById('card')!.click();
    expect(isLightboxOpen()).toBe(false);

    document.getElementById('free')!.click();
    expect(isLightboxOpen()).toBe(true);
  });
});

describe('openLightbox', () => {
  it('navigue dans la galerie et se ferme au retour arrière', async () => {
    document.body.innerHTML = '<div class="rich-content"><img src="/storage/1.png"><img src="/storage/2.png"></div>';
    await flush();
    const images = [...document.querySelectorAll<HTMLImageElement>('.rich-content img')];

    openLightbox(images, 0);
    const box = document.querySelector('.media-lightbox')!;
    expect(box.querySelector('.media-lightbox__count')!.textContent).toBe('1 / 2');

    box.querySelector<HTMLButtonElement>('[data-lb="next"]')!.click();
    expect(box.querySelector('.media-lightbox__count')!.textContent).toBe('2 / 2');
    expect(box.querySelector<HTMLImageElement>('.media-lightbox__stage img')!.src).toContain('/storage/2.png');

    window.dispatchEvent(new PopStateEvent('popstate'));
    expect(isLightboxOpen()).toBe(false);
  });
});

describe('setWatermark', () => {
  it('pose puis retire le motif de filigrane, texte échappé', () => {
    setWatermark('Awa <Test> · M-001');
    const tile = document.documentElement.style.getPropertyValue('--wm-tile');
    expect(tile).toContain('data:image/svg+xml');
    expect(decodeURIComponent(tile)).toContain('Awa &lt;Test&gt; · M-001');

    setWatermark(null);
    expect(document.documentElement.style.getPropertyValue('--wm-tile')).toBe('');
  });
});

describe('vidéos du contenu pédagogique', () => {
  it('convertit une ancienne iframe YouTube et résout une vidéo référencée au montage', async () => {
    const win = window as unknown as Record<string, unknown>;
    const players: unknown[] = [];
    win.YT = { Player: class { constructor() { players.push(this); } } };
    const fetchMock = vi.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => ({ type: 'mp4', url: '/storage/videos/a.mp4', expires_at: null }),
    }));
    vi.stubGlobal('fetch', fetchMock);
    setWatermark('Awa · M-001');

    document.body.innerHTML = `<div class="rich-content">
      <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>
      <video data-video-ref="0b6f4c1e-8d2a-4f7b-9c3d-2e1a5b6c7d8e" controls></video></div>`;
    await vi.waitFor(() => expect(document.querySelector('video')!.getAttribute('src')).toBe('/storage/videos/a.mp4'));

    expect(document.querySelector('iframe[src*="youtube.com/embed/"]')).toBeNull();
    expect(document.querySelector('.brand-video[data-embed="youtube"] .brand-video__overlay--wm')).not.toBeNull();
    expect(players).toHaveLength(1);
    // Durcissement inchangé sur la vidéo référencée.
    expect(document.querySelector('video')!.getAttribute('controlsList')).toContain('nodownload');

    const menu = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    document.querySelector('.brand-video')!.dispatchEvent(menu);
    expect(menu.defaultPrevented).toBe(true);

    setWatermark(null);
    vi.unstubAllGlobals();
    delete win.YT;
  });
});
