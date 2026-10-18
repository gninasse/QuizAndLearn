import { describe, expect, it } from 'vitest';
import { isVideoStreamRequest } from './sw-video';

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
