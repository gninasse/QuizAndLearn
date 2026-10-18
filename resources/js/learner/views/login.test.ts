// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest';

const { showcaseSettings, enterGuestMode } = vi.hoisted(() => ({
  showcaseSettings: vi.fn(),
  enterGuestMode: vi.fn(),
}));

vi.mock('../api/client', () => ({
  api: { showcaseSettings },
  ApiError: class extends Error {},
  NetworkError: class extends Error {},
}));
vi.mock('../guest/guest-session', () => ({ enterGuestMode }));
vi.mock('../sync/engine', () => ({ fullBootstrap: vi.fn(), hydrateFromDb: vi.fn() }));
vi.mock('../db/schema', () => ({ db: { profile: { put: vi.fn() } } }));

import { mount } from './login';

async function setup(): Promise<HTMLButtonElement> {
  const el = document.createElement('div');
  document.body.append(el);
  mount(el);
  await new Promise((r) => setTimeout(r, 0));
  return el.querySelector<HTMLButtonElement>('#guest-enter')!;
}

describe('bouton « Découvrir sans compte »', () => {
  it('visible quand le mode découverte est activé', async () => {
    showcaseSettings.mockResolvedValue({ success: true, enabled: true });
    const btn = await setup();
    expect(btn.hidden).toBe(false);
    expect(btn.textContent).toContain('Découvrir sans compte');
  });

  it('masqué quand le mode est désactivé ou en cas d’erreur', async () => {
    showcaseSettings.mockResolvedValue({ success: true, enabled: false });
    expect((await setup()).hidden).toBe(true);
    showcaseSettings.mockRejectedValue(new Error('offline'));
    expect((await setup()).hidden).toBe(true);
  });

  it('ouvre la session invité puis signale l’authentification', async () => {
    showcaseSettings.mockResolvedValue({ success: true, enabled: true });
    enterGuestMode.mockResolvedValue(undefined);
    const authenticated = vi.fn();
    window.addEventListener('learner:authenticated', authenticated);

    const btn = await setup();
    btn.click();
    await new Promise((r) => setTimeout(r, 0));

    window.removeEventListener('learner:authenticated', authenticated);
    expect(enterGuestMode).toHaveBeenCalledOnce();
    expect(authenticated).toHaveBeenCalledOnce();
  });
});
