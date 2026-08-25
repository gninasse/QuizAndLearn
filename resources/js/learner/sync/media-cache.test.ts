import { describe, expect, it } from 'vitest';
import { extractMediaUrls } from './engine';

describe('extractMediaUrls', () => {
  it('extrait les médias same-origin sous /storage/ et /uploads/', () => {
    const html = `
      <p>Texte</p>
      <img src="/storage/uploads/media/photo.png" />
      <audio src="${window.location.origin}/storage/articles/media/son.mp3"></audio>
      <a href="/storage/doc.pdf">doc</a>
    `;
    const urls = extractMediaUrls([html]);
    expect(urls).toContain('/storage/uploads/media/photo.png');
    expect(urls).toContain('/storage/articles/media/son.mp3');
    expect(urls).toContain('/storage/doc.pdf');
  });

  it('ignore les URLs externes et les chemins hors médias', () => {
    const html = `
      <img src="https://cdn.exemple.com/pub.png" />
      <a href="/quizzes/3">lien interne</a>
      <img src="/build/assets/app.js" />
    `;
    expect(extractMediaUrls([html])).toEqual([]);
  });

  it('déduplique et tolère null/undefined', () => {
    const html = '<img src="/storage/a.png" /><img src="/storage/a.png" />';
    expect(extractMediaUrls([html, null, undefined])).toEqual(['/storage/a.png']);
  });
});
