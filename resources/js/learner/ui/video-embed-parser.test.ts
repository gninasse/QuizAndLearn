import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Le fichier public est un script classique (UMD minuscule) : on l'évalue avec un `module` factice.
const source = readFileSync(resolve(process.cwd(), 'public/js/admin/video-embed.js'), 'utf8');
const mod: { exports: any } = { exports: {} };
new Function('module', source)(mod);
const { parseVideoEmbed, buildVideoEmbedHtml } = mod.exports;

const ID = 'dQw4w9WgXcQ';

describe('parseVideoEmbed', () => {
    it.each([
        `https://www.youtube.com/watch?v=${ID}`,
        `https://youtube.com/watch?v=${ID}&t=42s`,
        `https://m.youtube.com/watch?v=${ID}`,
        `https://youtu.be/${ID}`,
        `https://youtu.be/${ID}?si=abc123`,
        `https://www.youtube.com/shorts/${ID}`,
        `https://www.youtube.com/embed/${ID}`,
        `https://www.youtube-nocookie.com/embed/${ID}?start=3`,
    ])('YouTube %s', (url) => {
        expect(parseVideoEmbed(url)).toEqual({ provider: 'youtube', id: ID });
    });

    it('Vimeo simple, non répertoriée et lecteur', () => {
        expect(parseVideoEmbed('https://vimeo.com/123456')).toEqual({ provider: 'vimeo', id: '123456' });
        expect(parseVideoEmbed('https://vimeo.com/123456/abcdef0123')).toEqual({ provider: 'vimeo', id: '123456', hash: 'abcdef0123' });
        expect(parseVideoEmbed('https://player.vimeo.com/video/123456?h=abcdef0123')).toEqual({ provider: 'vimeo', id: '123456', hash: 'abcdef0123' });
        expect(parseVideoEmbed('https://player.vimeo.com/video/123456')).toEqual({ provider: 'vimeo', id: '123456' });
    });

    it.each([
        'https://evil.com/watch?v=abc',
        `https://evil.com/watch?v=${ID}`,
        `https://youtube.com.evil.com/watch?v=${ID}`,
        'https://www.youtube.com/watch?v=short',
        'https://vimeo.com/notdigits',
        'javascript:alert(1)',
        '',
        'pas une url',
    ])('refuse %s', (url) => {
        expect(parseVideoEmbed(url)).toBeNull();
    });

    it('construit le div de marque sans URL complète', () => {
        const html = buildVideoEmbedHtml({ provider: 'vimeo', id: '1', hash: 'ab' });
        expect(html).toBe('<div class="brand-video" data-embed="vimeo" data-id="1" data-hash="ab" contenteditable="false"><span>Vidéo Vimeo</span></div><p><br></p>');
    });
});
