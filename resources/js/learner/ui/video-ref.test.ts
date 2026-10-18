import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const hlsState = vi.hoisted(() => ({ instances: [] as Array<Record<string, any>> }));

vi.mock('hls.js', () => {
  class FakeHls {
    static isSupported = (): boolean => true;
    static Events = { ERROR: 'hlsError' };
    static ErrorTypes = { NETWORK_ERROR: 'networkError', MEDIA_ERROR: 'mediaError' };
    handlers: Record<string, (event: string, data: unknown) => void> = {};
    loadSource = vi.fn();
    attachMedia = vi.fn();
    destroy = vi.fn();
    recoverMediaError = vi.fn();
    on(event: string, handler: (event: string, data: unknown) => void): void {
      this.handlers[event] = handler;
    }
    constructor() {
      hlsState.instances.push(this as unknown as Record<string, any>);
    }
  }
  return { default: FakeHls };
});

import { sessionStore } from '../stores';
import { resolveVideoRefs } from './video-ref';

const REF = '0b6f4c1e-8d2a-4f7b-9c3d-2e1a5b6c7d8e';

type Reply = { status: number; body: unknown };
let replies: Reply[] = [];
let fetchMock: ReturnType<typeof vi.fn>;

/** Laisse s'écouler les promesses (fetch simulé, import dynamique). */
async function settle(): Promise<void> {
  for (let i = 0; i < 20; i++) await new Promise<void>((resolve) => queueMicrotask(resolve));
}

function mountVideo(): HTMLVideoElement {
  document.body.innerHTML = `<div class="rich-content"><video data-video-ref="${REF}" controls playsinline preload="metadata"></video></div>`;
  return document.querySelector('video')!;
}

function placeholderText(): string | null {
  return document.querySelector('.video-placeholder')?.textContent?.trim() ?? null;
}

function setOnline(value: boolean): void {
  Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => value });
}

beforeEach(() => {
  replies = [];
  hlsState.instances.length = 0;
  setOnline(true);
  fetchMock = vi.fn(async () => {
    const reply = replies.shift() ?? { status: 404, body: { message: 'Not found' } };
    return {
      ok: reply.status >= 200 && reply.status < 300,
      status: reply.status,
      json: async () => reply.body,
    };
  });
  vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  sessionStore.set(null);
  document.body.innerHTML = '';
});

describe('resolveVideoRefs', () => {
  it('resolves_mp4_ref', async () => {
    replies.push({ status: 200, body: { type: 'mp4', url: '/storage/videos/a.mp4', expires_at: null } });
    const video = mountVideo();

    resolveVideoRefs(document);
    await settle();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(String(fetchMock.mock.calls[0]![0])).toBe(`/api/learner/v1/videos/${REF}/playback`);
    expect(video.getAttribute('src')).toBe('/storage/videos/a.mp4');
    expect(video.hidden).toBe(false);
    expect(placeholderText()).toBeNull();

    // Idempotent : un second passage ne relance pas la requête.
    resolveVideoRefs(document);
    await settle();
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('uses the showcase endpoint in guest mode', async () => {
    sessionStore.set({ id: 0, name: 'Invité', last_name: '', full_name: 'Invité', email: '', avatar_url: '', is_guest: true, xp: { total_xp: 0, current_level: 0, current_streak: 0, longest_streak: 0, last_activity_date: null } });
    replies.push({ status: 200, body: { type: 'mp4', url: '/storage/videos/a.mp4', expires_at: null } });
    mountVideo();

    resolveVideoRefs(document);
    await settle();

    expect(String(fetchMock.mock.calls[0]![0])).toBe(`/api/showcase/v1/videos/${REF}/playback`);
  });

  it('shows_processing_then_retries', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    replies.push({ status: 202, body: { status: 'processing' } });
    replies.push({ status: 200, body: { type: 'mp4', url: '/storage/videos/b.mp4', expires_at: null } });
    const video = mountVideo();

    resolveVideoRefs(document);
    await settle();
    expect(placeholderText()).toBe('Vidéo en cours de préparation');
    expect(video.hidden).toBe(true);

    await vi.advanceTimersByTimeAsync(30_000);
    await settle();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(video.getAttribute('src')).toBe('/storage/videos/b.mp4');
    expect(video.hidden).toBe(false);
    expect(placeholderText()).toBeNull();
  });

  it('stops retrying after three attempts', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    for (let i = 0; i < 6; i++) replies.push({ status: 202, body: { status: 'processing' } });
    mountVideo();

    resolveVideoRefs(document);
    await settle();
    for (let i = 0; i < 5; i++) {
      await vi.advanceTimersByTimeAsync(30_000);
      await settle();
    }

    expect(fetchMock).toHaveBeenCalledTimes(4);
    expect(placeholderText()).toBe('Vidéo en cours de préparation');
  });

  it('shows_unavailable_on_404', async () => {
    replies.push({ status: 404, body: { message: 'Not Found' } });
    const video = mountVideo();

    resolveVideoRefs(document);
    await settle();

    expect(placeholderText()).toBe('Vidéo indisponible');
    expect(video.hidden).toBe(true);
    expect(video.hasAttribute('src')).toBe(false);
  });

  it('shows unavailable on 410 (conversion failed)', async () => {
    replies.push({ status: 410, body: { status: 'failed' } });
    mountVideo();

    resolveVideoRefs(document);
    await settle();

    expect(placeholderText()).toBe('Vidéo indisponible');
  });

  it('shows the online-only message when offline, then loads once back online', async () => {
    setOnline(false);
    const video = mountVideo();

    resolveVideoRefs(document);
    await settle();
    expect(fetchMock).not.toHaveBeenCalled();
    expect(placeholderText()).toBe('Vidéo disponible en ligne uniquement.');

    setOnline(true);
    replies.push({ status: 200, body: { type: 'mp4', url: '/storage/videos/a.mp4', expires_at: null } });
    window.dispatchEvent(new Event('online'));
    await settle();

    expect(video.getAttribute('src')).toBe('/storage/videos/a.mp4');
    expect(placeholderText()).toBeNull();
  });

  it('renews_expired_source_and_keeps_position', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    const now = Date.now();
    replies.push({ status: 200, body: { type: 'mp4', url: 'https://cdn.example/a.mp4?token=1', expires_at: new Date(now + 15 * 60_000).toISOString() } });
    replies.push({ status: 200, body: { type: 'mp4', url: 'https://cdn.example/a.mp4?token=2', expires_at: new Date(now + 30 * 60_000).toISOString() } });
    const video = mountVideo();
    const play = vi.spyOn(video, 'play').mockResolvedValue(undefined);

    resolveVideoRefs(document);
    await settle();
    expect(video.getAttribute('src')).toBe('https://cdn.example/a.mp4?token=1');

    // L'apprenant a mis en pause à 42 s ; 14 min passent (renouvellement à expiration − 60 s).
    video.currentTime = 42;
    await vi.advanceTimersByTimeAsync(14 * 60_000);
    await settle();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(video.getAttribute('src')).toBe('https://cdn.example/a.mp4?token=2');

    video.currentTime = 0; // le changement de source remet la lecture au début…
    video.dispatchEvent(new Event('loadedmetadata'));
    expect(video.currentTime).toBe(42); // …le lecteur reprend à la même position
    expect(play).not.toHaveBeenCalled(); // il était en pause : il le reste
    expect(placeholderText()).toBeNull();
  });

  it('renews the source after a media error (403) and resumes playback', async () => {
    replies.push({ status: 200, body: { type: 'mp4', url: 'https://cdn.example/a.mp4?token=1', expires_at: new Date(Date.now() + 60 * 60_000).toISOString() } });
    replies.push({ status: 200, body: { type: 'mp4', url: 'https://cdn.example/a.mp4?token=2', expires_at: new Date(Date.now() + 75 * 60_000).toISOString() } });
    const video = mountVideo();
    const play = vi.spyOn(video, 'play').mockResolvedValue(undefined);

    resolveVideoRefs(document);
    await settle();

    video.currentTime = 90;
    Object.defineProperty(video, 'paused', { configurable: true, get: () => false });
    video.dispatchEvent(new Event('error'));
    await settle();

    expect(video.getAttribute('src')).toBe('https://cdn.example/a.mp4?token=2');
    video.currentTime = 0;
    video.dispatchEvent(new Event('loadedmetadata'));
    expect(video.currentTime).toBe(90);
    expect(play).toHaveBeenCalled();
    expect(placeholderText()).toBeNull();
  });

  it('plays hls through hls.js and reloads the playlist on a fatal network error', async () => {
    replies.push({ status: 200, body: { type: 'hls', url: '/videos/r/hls/index.m3u8?signature=1', expires_at: new Date(Date.now() + 15 * 60_000).toISOString() } });
    replies.push({ status: 200, body: { type: 'hls', url: '/videos/r/hls/index.m3u8?signature=2', expires_at: new Date(Date.now() + 30 * 60_000).toISOString() } });
    const video = mountVideo();
    vi.spyOn(video, 'canPlayType').mockReturnValue('');

    resolveVideoRefs(document);
    await settle();

    expect(hlsState.instances).toHaveLength(1);
    const hls = hlsState.instances[0]!;
    expect(hls.loadSource).toHaveBeenCalledWith('/videos/r/hls/index.m3u8?signature=1');
    expect(hls.attachMedia).toHaveBeenCalledWith(video);

    video.currentTime = 300;
    hls.handlers.hlsError!('hlsError', { fatal: true, type: 'networkError', response: { code: 403 } });
    await settle();

    expect(hls.loadSource).toHaveBeenLastCalledWith('/videos/r/hls/index.m3u8?signature=2');
    video.currentTime = 0;
    video.dispatchEvent(new Event('loadedmetadata'));
    expect(video.currentTime).toBe(300);
  });

  it('bounds hls media error recovery, then shows unavailable', async () => {
    replies.push({ status: 200, body: { type: 'hls', url: '/videos/r/hls/index.m3u8?signature=1', expires_at: new Date(Date.now() + 15 * 60_000).toISOString() } });
    const video = mountVideo();
    vi.spyOn(video, 'canPlayType').mockReturnValue('');

    resolveVideoRefs(document);
    await settle();

    const hls = hlsState.instances[0]!;
    const mediaError = { fatal: true, type: 'mediaError' };
    hls.handlers.hlsError!('hlsError', mediaError);
    hls.handlers.hlsError!('hlsError', mediaError);
    expect(hls.recoverMediaError).toHaveBeenCalledTimes(2);
    expect(placeholderText()).toBeNull();

    hls.handlers.hlsError!('hlsError', mediaError);
    expect(hls.recoverMediaError).toHaveBeenCalledTimes(2);
    expect(hls.destroy).toHaveBeenCalled();
    expect(placeholderText()).toBe('Vidéo indisponible');
  });

  it('uses native hls when the browser supports it', async () => {
    replies.push({ status: 200, body: { type: 'hls', url: '/videos/r/hls/index.m3u8?signature=1', expires_at: null } });
    const video = mountVideo();
    vi.spyOn(video, 'canPlayType').mockImplementation((type) => (type === 'application/vnd.apple.mpegurl' ? 'maybe' : ''));

    resolveVideoRefs(document);
    await settle();

    expect(hlsState.instances).toHaveLength(0);
    expect(video.getAttribute('src')).toBe('/videos/r/hls/index.m3u8?signature=1');
  });
});
