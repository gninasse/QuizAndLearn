import { describe, expect, it } from 'vitest';
import { isVideoStreamRequest, RESOURCE_CACHE, resourceCacheKey, resourceFileId } from './sw-video';

const at = (href: string): boolean => isVideoStreamRequest(new URL(href, 'https://app.example'));

describe('isVideoStreamRequest', () => {
  it.each([
    '/api/learner/v1/videos/0b6f4c1e-8d2a-4f7b-9c3d-2e1a5b6c7d8e/playback',
    '/api/showcase/v1/videos/0b6f4c1e-8d2a-4f7b-9c3d-2e1a5b6c7d8e/playback',
    '/api/learner/v1/videos/0b6f4c1e-8d2a-4f7b-9c3d-2e1a5b6c7d8e/hls/index.m3u8?expires=1&signature=abc',
    'https://bucket.s3.example/hls/ref/seg_001.ts?X-Amz-Signature=1',
    'https://www.youtube.com/iframe_api',
    'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    'https://rr3---sn-abc.googlevideo.com/videoplayback',
    'https://player.vimeo.com/api/player.js',
    'https://i.vimeocdn.com/video/1.jpg',
  ])('jamais en cache : %s', (href) => {
    expect(at(href)).toBe(true);
  });

  it.each(['/storage/images/a.png', '/icons/icon-192x192.png', '/api/learner/v1/bootstrap', '/plugins/x.js'])(
    'non concerné : %s',
    (href) => {
      expect(at(href)).toBe(false);
    },
  );
});

describe('ressources hors ligne (clé resource:{id})', () => {
  it("extrait l'id d'une URL de fichier signée, quelle que soit la signature", () => {
    const signed = new URL('https://app.test/api/learner/v1/resources/12/file?expires=1&signature=abc');
    const renewed = new URL('https://app.test/api/learner/v1/resources/12/file?expires=2&signature=def');
    expect(resourceFileId(signed)).toBe(12);
    expect(resourceFileId(renewed)).toBe(12);
    expect(resourceFileId(new URL('https://app.test/api/learner/v1/resources/12/view'))).toBeNull();
    expect(resourceFileId(new URL('https://app.test/storage/12/file'))).toBeNull();
  });

  it('la clé de cache ne dépend que de l’id', () => {
    expect(resourceCacheKey(12)).toBe('/offline/resource:12');
    expect(RESOURCE_CACHE).toBe('learner-resources-v1');
  });

  it("une URL de fichier signée n'est jamais mise en cache telle quelle", () => {
    expect(isVideoStreamRequest(new URL('https://app.test/api/learner/v1/resources/12/file?expires=1&signature=a'))).toBe(false);
  });
});
