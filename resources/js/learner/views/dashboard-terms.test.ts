// @vitest-environment happy-dom
import { afterEach, describe, expect, it } from 'vitest';

import { resetTerms, setTerms } from '../core/terms';
import { groupsStore, sessionStore } from '../stores';
import { mount } from './dashboard';

describe('dashboard : terminologie configurable', () => {
  afterEach(() => resetTerms());

  const render = (): string => {
    sessionStore.set({
      id: 1, name: 'Awa', last_name: 'Diop', full_name: 'Awa Diop', email: 'a@b.c', avatar_url: '',
      matricule: null,
      xp: { total_xp: 0, current_level: 0, current_streak: 0, longest_streak: 0, last_activity_date: null },
    } as never);
    groupsStore.set([{ id: 1, name: 'Promo A', status: 'suspended', start_date: null, end_date: null }]);
    const el = document.createElement('div');
    mount(el);
    return el.textContent ?? '';
  };

  it('uses « Le groupe » by default', () => {
    expect(render()).toContain('Le groupe « Promo A » est momentanément suspendu —');
  });

  it('uses the configured feminine term with agreement', () => {
    setTerms({ group: 'classe', groups: 'classes', Group: 'Classe', Groups: 'Classes', a_group: 'une classe', the_group: 'la classe' });
    expect(render()).toContain('La classe « Promo A » est momentanément suspendue —');
  });
});
