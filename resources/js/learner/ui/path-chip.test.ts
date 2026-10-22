// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { foldersStore, groupsStore, placementsStore } from '../stores';
import { installPathChips, pathChipHtml } from './path-chip';

describe('path-chip', () => {
  beforeEach(() => {
    history.replaceState(null, '', '/articles');
    groupsStore.set([{ id: 3, name: 'SVT', status: 'active', start_date: null, end_date: null }]);
    foldersStore.set([{ id: 7, group_id: 3, parent_id: null, name: 'Ch.3', position: 0 }]);
    placementsStore.set([{ type: 'article', id: 12, group_id: 3, folder_id: 7, position: 0 }]);
    installPathChips();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    groupsStore.set([]);
    foldersStore.set([]);
    placementsStore.set([]);
  });

  it('affiche le chemin « Groupe › Dossier » du contenu', () => {
    document.body.innerHTML = pathChipHtml('article', 12);
    expect(document.querySelector('[data-path-chip]')?.textContent?.trim()).toBe('SVT › Ch.3');
  });

  it("ne rend rien pour un contenu placé nulle part", () => {
    expect(pathChipHtml('quiz', 99)).toBe('');
  });

  it('un clic navigue vers /cours/:g/:f sans suivre le lien de la carte', () => {
    document.body.innerHTML = `<a data-link href="/articles/12" id="card">${pathChipHtml('article', 12)}</a>`;
    const popped: string[] = [];
    const onPop = (): void => {
      popped.push(window.location.pathname);
    };
    window.addEventListener('popstate', onPop);

    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.querySelector('[data-path-chip]')!.dispatchEvent(click);
    window.removeEventListener('popstate', onPop);

    expect(click.defaultPrevented).toBe(true);
    expect(window.location.pathname).toBe('/cours/3/7');
    expect(popped).toEqual(['/cours/3/7']);
  });
});
