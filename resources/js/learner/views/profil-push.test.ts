// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const pushMock = vi.hoisted(() => ({
  pushSupport: vi.fn(),
  pushState: vi.fn(),
  enablePush: vi.fn(),
  disablePush: vi.fn(),
}));
vi.mock('../core/push', () => pushMock);

const consultationMock = vi.hoisted(() => ({ value: false }));
vi.mock('../consultation/consultation', async (importOriginal) => {
  const original = await importOriginal<typeof import('../consultation/consultation')>();
  return { ...original, isConsultation: () => consultationMock.value };
});

import { sessionStore } from '../stores';
import type { UserProfile } from '../domain/types';
import { mount } from './profil';

const flush = () => new Promise((r) => setTimeout(r, 0));

function user(over: Partial<UserProfile> = {}): UserProfile {
  return {
    id: 1, name: 'A', last_name: 'B', full_name: 'A B', email: 'a@b.c', avatar_url: '', matricule: null,
    xp: { total_xp: 0, current_level: 0, current_streak: 0, longest_streak: 0, last_activity_date: null },
    push: { public_key: 'KEY' },
    ...over,
  };
}

async function render(): Promise<HTMLElement> {
  const el = document.createElement('div');
  mount(el);
  await flush();
  return el;
}

beforeEach(() => {
  vi.clearAllMocks();
  consultationMock.value = false;
  pushMock.pushSupport.mockReturnValue('supported');
  pushMock.pushState.mockResolvedValue('off');
  pushMock.enablePush.mockResolvedValue('on');
  sessionStore.set(user());
});

describe('profil : carte Notifications', () => {
  it('renders the card with the switch and asks no permission on load', async () => {
    const el = await render();
    expect(el.textContent).toContain('Notifications');
    expect(el.textContent).toContain('Recevoir les notifications');
    expect(el.querySelector('[data-push-switch]')).not.toBeNull();
    expect(pushMock.enablePush).not.toHaveBeenCalled();
  });

  it('hides the card without a public key', async () => {
    sessionStore.set(user({ push: { public_key: null } }));
    expect((await render()).querySelector('[data-push-card]')).toBeNull();
  });

  it('hides the card in guest mode', async () => {
    sessionStore.set(user({ is_guest: true }));
    expect((await render()).querySelector('[data-push-card]')).toBeNull();
  });

  it('hides the card in consultation', async () => {
    consultationMock.value = true;
    expect((await render()).querySelector('[data-push-card]')).toBeNull();
  });

  it('shows the iOS hint when install is needed, without a switch', async () => {
    pushMock.pushSupport.mockReturnValue('ios-install-needed');
    const el = await render();
    expect(el.textContent).toContain("Sur iPhone, ajoutez d'abord l'application à l'écran d'accueil (Partager → Sur l'écran d'accueil).");
    expect(el.querySelector('[data-push-switch]')).toBeNull();
  });

  it('shows the denied message', async () => {
    pushMock.pushState.mockResolvedValue('denied');
    const el = await render();
    expect(el.textContent).toContain('Notifications bloquées dans les réglages du navigateur.');
  });

  it('enables on click with the public key', async () => {
    const el = await render();
    el.querySelector<HTMLButtonElement>('[data-push-switch]')!.click();
    await flush();
    expect(pushMock.enablePush).toHaveBeenCalledWith('KEY');
  });

  it('ignores a second click while enabling (single request)', async () => {
    let done!: (v: string) => void;
    pushMock.enablePush.mockReturnValue(new Promise((r) => { done = r; }));
    const el = await render();
    const sw = el.querySelector<HTMLButtonElement>('[data-push-switch]')!;
    expect(sw.getAttribute('aria-label')).toBe('Recevoir les notifications');
    sw.click();
    sw.click();
    expect(sw.getAttribute('aria-busy')).toBe('true');
    expect(pushMock.enablePush).toHaveBeenCalledTimes(1);
    done('on');
    await flush();
  });
});
