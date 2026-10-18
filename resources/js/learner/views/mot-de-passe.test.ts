// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest';

const { bootstrap, updatePassword } = vi.hoisted(() => ({ bootstrap: vi.fn(), updatePassword: vi.fn() }));

vi.mock('../sync/engine', () => ({ fullBootstrap: bootstrap }));
vi.mock('../api/client', () => ({
  api: { updatePassword },
  ApiError: class extends Error {},
  NetworkError: class extends Error {},
}));
vi.mock('../db/schema', () => ({ db: { profile: { put: vi.fn() } } }));

import { sessionStore } from '../stores';
import { mount } from './mot-de-passe';

function setup(): { el: HTMLElement; btn: HTMLButtonElement; set: (n: string, v: string) => void } {
  const el = document.createElement('div');
  mount(el, {});
  const set = (name: string, value: string): void => {
    const input = el.querySelector<HTMLInputElement>(`input[name="${name}"]`)!;
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };
  return { el, btn: el.querySelector<HTMLButtonElement>('button[type="submit"]')!, set };
}

describe('vue mot de passe imposé', () => {
  it('affiche trois champs dont le mot de passe reçu du formateur', () => {
    const { el } = setup();
    expect(el.querySelectorAll('input[type="password"]').length).toBe(3);
    expect(el.textContent).toContain('reçu par votre formateur');
  });

  it('désactive le bouton tant que le nouveau mot de passe est invalide', () => {
    const { btn, set } = setup();
    expect(btn.disabled).toBe(true);
    set('current_password', 'temp1234');
    set('password', 'court');
    set('password_confirmation', 'court');
    expect(btn.disabled).toBe(true);
    set('password', 'longenough1');
    expect(btn.disabled).toBe(true);
    set('password_confirmation', 'longenough1');
    expect(btn.disabled).toBe(false);
  });
});

async function submitValid(): Promise<{ authenticated: ReturnType<typeof vi.fn>; btn: HTMLButtonElement }> {
  sessionStore.set({ id: 1, must_change_password: true } as never);
  const { el, btn, set } = setup();
  set('current_password', 'temp1234');
  set('password', 'longenough1');
  set('password_confirmation', 'longenough1');
  const authenticated = vi.fn();
  window.addEventListener('learner:authenticated', authenticated, { once: true });
  el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
  await vi.waitFor(() => expect(authenticated).toHaveBeenCalled());
  return { authenticated, btn };
}

describe('soumission', () => {
  it('efface le drapeau et signale la connexion', async () => {
    updatePassword.mockResolvedValue({});
    bootstrap.mockResolvedValue(undefined);
    await submitValid();
    expect(sessionStore.get()?.must_change_password).toBe(false);
  });

  it('continue même si le bootstrap échoue, sans rouvrir le formulaire', async () => {
    updatePassword.mockResolvedValue({});
    bootstrap.mockRejectedValue(new Error('offline'));
    const { btn } = await submitValid();
    expect(sessionStore.get()?.must_change_password).toBe(false);
    expect(btn.disabled).toBe(true);
  });
});
