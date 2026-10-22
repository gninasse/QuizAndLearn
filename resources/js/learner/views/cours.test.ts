// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import type { Resource } from '../domain/types';
import {
  articlesStore,
  consultedResourcesStore,
  foldersStore,
  groupsStore,
  placementsStore,
  resourcesStore,
} from '../stores';
import { mount } from './cours';

function resource(id: number, kind: Resource['kind'], title: string): Resource {
  return {
    id, kind, title, description: null, mime: null, size: null, allow_download: false,
    video_ref: null, url: null, conversion_status: null, consulted: false, updated_at: '2026-10-20T10:00:00+00:00',
  };
}

describe('vue Cours', () => {
  beforeEach(() => {
    history.replaceState(null, '', '/cours');
  });

  afterEach(() => {
    groupsStore.set([]);
    foldersStore.set([]);
    placementsStore.set([]);
    resourcesStore.set([]);
    articlesStore.set([]);
    consultedResourcesStore.set([]);
  });

  it('redirige vers le groupe quand il n’y en a qu’un', () => {
    groupsStore.set([{ id: 4, name: 'Promo A', status: 'active', start_date: null, end_date: null }]);
    const popped: string[] = [];
    const onPop = (): void => {
      popped.push(window.location.pathname);
    };
    window.addEventListener('popstate', onPop);
    mount(document.createElement('div'), {});
    window.removeEventListener('popstate', onPop);

    expect(window.location.pathname).toBe('/cours/4');
    expect(popped).toEqual(['/cours/4']);
  });

  it('liste les groupes quand il y en a plusieurs', () => {
    groupsStore.set([
      { id: 4, name: 'Promo A', status: 'active', start_date: null, end_date: null },
      { id: 5, name: 'Promo B', status: 'active', start_date: null, end_date: null },
    ]);
    const el = document.createElement('div');
    mount(el, {});
    expect(window.location.pathname).toBe('/cours');
    expect(el.querySelector('a[href="/cours/4"]')).not.toBeNull();
    expect(el.querySelector('a[href="/cours/5"]')).not.toBeNull();
  });

  it('affiche l’état vide d’un dossier sans contenu', () => {
    groupsStore.set([{ id: 4, name: 'Promo A', status: 'active', start_date: null, end_date: null }]);
    foldersStore.set([{ id: 9, group_id: 4, parent_id: null, name: 'Semaine 1', position: 0 }]);
    const el = document.createElement('div');
    mount(el, { groupId: '4', folderId: '9' });
    expect(el.textContent).toContain('Aucun contenu dans ce dossier pour le moment.');
  });

  it('affiche fil d’Ariane, dossiers, éléments par type et progression', () => {
    groupsStore.set([{ id: 4, name: 'Promo A', status: 'active', start_date: null, end_date: null }]);
    foldersStore.set([
      { id: 9, group_id: 4, parent_id: null, name: 'Semaine 1', position: 0 },
      { id: 10, group_id: 4, parent_id: 9, name: 'Exercices', position: 0 },
    ]);
    resourcesStore.set([resource(1, 'pdf', 'Support PDF'), resource(2, 'link', 'Site')]);
    placementsStore.set([
      { type: 'resource', id: 1, group_id: 4, folder_id: 9, position: 0 },
      { type: 'resource', id: 2, group_id: 4, folder_id: 9, position: 1 },
      { type: 'article', id: 5, group_id: 4, folder_id: 10, position: 0 },
    ]);
    articlesStore.set([{ id: 5, title: 'Lecture', status: 'unread', progress_percentage: 0 } as never]);
    consultedResourcesStore.set([1]);

    const el = document.createElement('div');
    mount(el, { groupId: '4', folderId: '9' });

    const crumbs = [...el.querySelectorAll('[data-role="breadcrumb"] a, [data-role="breadcrumb"] [aria-current]')].map((n) => n.textContent?.trim());
    expect(crumbs).toEqual(['Cours', 'Promo A', 'Semaine 1']);
    expect(el.querySelector('a[href="/cours/4/10"]')?.textContent).toContain('Exercices');
    const pdf = el.querySelector('a[href="/ressources/1"]');
    expect(pdf?.querySelector('.bi-file-earmark-pdf')).not.toBeNull();
    expect(el.querySelector('a[href="/ressources/2"] .bi-link-45deg')).not.toBeNull();
    expect(el.querySelector('[data-role="folder-progress"]')?.textContent).toContain('1/3 consultés');
  });
});
