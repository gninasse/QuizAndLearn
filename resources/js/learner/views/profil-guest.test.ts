// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const dispatchMock = vi.hoisted(() => vi.fn());
vi.mock('../sync/engine', async (importOriginal) => {
  const original = await importOriginal<typeof import('../sync/engine')>();
  return { ...original, dispatch: dispatchMock };
});

import { preferencesStore, sessionStore } from '../stores';
import { mount } from './profil';

describe('profil invité', () => {
  beforeEach(() => {
    sessionStore.set({
      id: 0, name: 'Invité', last_name: '', full_name: 'Invité', email: '', avatar_url: '',
      matricule: null, is_guest: true,
      xp: { total_xp: 0, current_level: 0, current_streak: 0, longest_streak: 0, last_activity_date: null },
    });
  });

  it('shows only preferences and leave button, saving locally without dispatch', () => {
    const el = document.createElement('div');
    mount(el);
    expect(el.textContent).toContain('Préférences');
    expect(el.textContent).toContain('Quitter le mode découverte');
    expect(el.textContent).not.toContain('Sécurité');
    expect(el.textContent).not.toContain('Se déconnecter');

    el.querySelector<HTMLButtonElement>('[data-pref-font="large"]')!.click();
    expect(preferencesStore.get().font_size).toBe('large');
    expect(dispatchMock).not.toHaveBeenCalled();
  });
});
