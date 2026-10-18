import { afterEach, beforeAll, describe, expect, it } from 'vitest';
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
