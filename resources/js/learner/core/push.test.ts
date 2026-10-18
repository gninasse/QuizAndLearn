// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const apiMock = vi.hoisted(() => ({
  subscribePush: vi.fn(),
  unsubscribePush: vi.fn(),
}));
vi.mock('../api/client', () => ({ api: apiMock }));

import type { UserProfile } from '../domain/types';
import { disablePush, enablePush, pushState, pushSupport, resyncPushSubscription, urlBase64ToUint8Array } from './push';

type Anything = Record<string, unknown>;

function setUa(ua: string, standalone = false): void {
  Object.defineProperty(navigator, 'userAgent', { value: ua, configurable: true });
  Object.defineProperty(navigator, 'standalone', { value: standalone, configurable: true });
}

function fakeSubscription(endpoint = 'https://push.example/abc') {
  return {
    endpoint,
    toJSON: () => ({ endpoint, keys: { p256dh: 'P', auth: 'A' } }),
    unsubscribe: vi.fn().mockResolvedValue(true),
  };
}

function installPush(opts: { permission?: string; request?: string; existing?: ReturnType<typeof fakeSubscription> | null } = {}) {
  const sub = fakeSubscription();
  const pushManager = {
    getSubscription: vi.fn().mockResolvedValue(opts.existing ?? null),
    subscribe: vi.fn().mockResolvedValue(sub),
  };
  Object.defineProperty(navigator, 'serviceWorker', {
    value: { ready: Promise.resolve({ pushManager }) },
    configurable: true,
  });
  (window as unknown as Anything).PushManager = class {};
  (window as unknown as Anything).Notification = {
    permission: opts.permission ?? 'default',
    requestPermission: vi.fn().mockResolvedValue(opts.request ?? 'granted'),
  };
  return { pushManager, sub };
}

function removePush(): void {
  delete (navigator as unknown as Anything).serviceWorker;
  delete (window as unknown as Anything).PushManager;
  delete (window as unknown as Anything).Notification;
}

beforeEach(() => {
  vi.clearAllMocks();
  apiMock.subscribePush.mockResolvedValue({});
  apiMock.unsubscribePush.mockResolvedValue({});
  window.matchMedia = ((q: string) => ({ matches: false, media: q })) as unknown as typeof window.matchMedia;
  setUa('Mozilla/5.0 (X11; Linux x86_64) Chrome/120');
});

afterEach(removePush);

describe('urlBase64ToUint8Array', () => {
  it('decodes url-safe base64 without padding', () => {
    expect(Array.from(urlBase64ToUint8Array('AQID'))).toEqual([1, 2, 3]);
    expect(Array.from(urlBase64ToUint8Array('-_8'))).toEqual([251, 255]);
  });
});

describe('pushSupport', () => {
  it('is supported with serviceWorker, PushManager and Notification', () => {
    installPush();
    expect(pushSupport()).toBe('supported');
  });

  it('is unsupported without PushManager on a desktop browser', () => {
    expect(pushSupport()).toBe('unsupported');
  });

  it('asks to install on iOS outside the home screen', () => {
    setUa('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari');
    expect(pushSupport()).toBe('ios-install-needed');
  });

  it('is unsupported on iOS when installed but without PushManager', () => {
    setUa('Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)', true);
    expect(pushSupport()).toBe('unsupported');
  });
});

describe('pushState', () => {
  it('is denied when permission is denied', async () => {
    installPush({ permission: 'denied' });
    expect(await pushState()).toBe('denied');
  });

  it('is off without a subscription and on with one', async () => {
    installPush({ permission: 'granted' });
    expect(await pushState()).toBe('off');
    installPush({ permission: 'granted', existing: fakeSubscription() });
    expect(await pushState()).toBe('on');
  });
});

describe('enablePush', () => {
  it('makes no API call when permission is refused', async () => {
    const { pushManager } = installPush({ request: 'denied' });
    expect(await enablePush('AQID')).toBe('denied');
    expect(pushManager.subscribe).not.toHaveBeenCalled();
    expect(apiMock.subscribePush).not.toHaveBeenCalled();
  });

  it('does nothing when push is unsupported', async () => {
    expect(await enablePush('AQID')).toBe('error');
    expect(apiMock.subscribePush).not.toHaveBeenCalled();
  });

  it('subscribes and POSTs the subscription JSON', async () => {
    const { pushManager } = installPush();
    expect(await enablePush('AQID')).toBe('on');
    expect(pushManager.subscribe).toHaveBeenCalledWith(
      expect.objectContaining({ userVisibleOnly: true }),
    );
    expect(apiMock.subscribePush).toHaveBeenCalledWith({
      endpoint: 'https://push.example/abc',
      keys: { p256dh: 'P', auth: 'A' },
    });
  });

  it('unsubscribes locally and reports error when the server refuses', async () => {
    const { sub } = installPush();
    apiMock.subscribePush.mockRejectedValue(new Error('boom'));
    expect(await enablePush('AQID')).toBe('error');
    expect(sub.unsubscribe).toHaveBeenCalled();
  });
});

describe('disablePush', () => {
  it('unsubscribes and DELETEs the endpoint', async () => {
    const existing = fakeSubscription();
    installPush({ existing });
    await disablePush();
    expect(existing.unsubscribe).toHaveBeenCalled();
    expect(apiMock.unsubscribePush).toHaveBeenCalledWith('https://push.example/abc');
  });

  it('never throws, even when everything fails', async () => {
    const existing = fakeSubscription();
    existing.unsubscribe.mockRejectedValue(new Error('x'));
    installPush({ existing });
    apiMock.unsubscribePush.mockRejectedValue(new Error('y'));
    await expect(disablePush()).resolves.toBeUndefined();
  });

  it('is a no-op without push support', async () => {
    await expect(disablePush()).resolves.toBeUndefined();
    expect(apiMock.unsubscribePush).not.toHaveBeenCalled();
  });
});

describe('resyncPushSubscription', () => {
  const learner = { id: 1, name: 'Awa', is_guest: false } as unknown as UserProfile;

  it('re-POSTs the existing browser subscription for the signed-in learner', async () => {
    installPush({ permission: 'granted', existing: fakeSubscription('https://push.example/shared') });
    await resyncPushSubscription(learner, false);
    expect(apiMock.subscribePush).toHaveBeenCalledWith({
      endpoint: 'https://push.example/shared',
      keys: { p256dh: 'P', auth: 'A' },
    });
  });

  it('does nothing without a browser subscription', async () => {
    installPush({ permission: 'granted' });
    await resyncPushSubscription(learner, false);
    expect(apiMock.subscribePush).not.toHaveBeenCalled();
  });

  it('never runs in guest mode, consultation, with a provisional password or without a session', async () => {
    const { pushManager } = installPush({ permission: 'granted', existing: fakeSubscription() });
    await resyncPushSubscription({ ...learner, is_guest: true }, false);
    await resyncPushSubscription(learner, true);
    await resyncPushSubscription({ ...learner, must_change_password: true }, false);
    await resyncPushSubscription(null, false);
    expect(pushManager.getSubscription).not.toHaveBeenCalled();
    expect(apiMock.subscribePush).not.toHaveBeenCalled();
  });

  it('does not touch the push manager when permission is not granted', async () => {
    const { pushManager } = installPush({ permission: 'default', existing: fakeSubscription() });
    await resyncPushSubscription(learner, false);
    expect(pushManager.getSubscription).not.toHaveBeenCalled();
  });

  it('never throws when the server refuses, and keeps the browser subscription', async () => {
    const existing = fakeSubscription();
    installPush({ permission: 'granted', existing });
    apiMock.subscribePush.mockRejectedValue(new Error('offline'));
    await expect(resyncPushSubscription(learner, false)).resolves.toBeUndefined();
    expect(existing.unsubscribe).not.toHaveBeenCalled();
  });

  it('gives up when the service worker never becomes ready', async () => {
    vi.useFakeTimers();
    try {
      installPush({ permission: 'granted' });
      Object.defineProperty(navigator, 'serviceWorker', { value: { ready: new Promise(() => undefined) }, configurable: true });
      const done = resyncPushSubscription(learner, false);
      await vi.advanceTimersByTimeAsync(5000);
      await expect(done).resolves.toBeUndefined();
      expect(apiMock.subscribePush).not.toHaveBeenCalled();
    } finally {
      vi.useRealTimers();
    }
  });

  it('is a no-op without push support', async () => {
    await expect(resyncPushSubscription(learner, false)).resolves.toBeUndefined();
    expect(apiMock.subscribePush).not.toHaveBeenCalled();
  });
});
