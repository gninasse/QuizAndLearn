// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest';

vi.mock('../sync/engine', () => ({ fullBootstrap: vi.fn() }));

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
