import { describe, expect, it } from 'vitest';
import type { ContentType, Folder, GroupInfo, Placement } from './types';
import { breadcrumb, childrenOf, folderProgress, pathLabel } from './folders';

function folder(id: number, group_id: number, parent_id: number | null, name: string, position: number): Folder {
  return { id, group_id, parent_id, name, position };
}

function place(type: ContentType, id: number, group_id: number, folder_id: number | null, position: number): Placement {
  return { type, id, group_id, folder_id, position };
}

function group(id: number, name: string): GroupInfo {
  return { id, name, status: 'active', start_date: null, end_date: null };
}

// Groupe 1 : racine → « Semaine 1 » (1) → « Exercices » (2) ; « Annexes » (3) à la racine.
const folders: Folder[] = [
  folder(1, 1, null, 'Semaine 1', 1),
  folder(2, 1, 1, 'Exercices', 0),
  folder(3, 1, null, 'Annexes', 0),
  folder(9, 2, null, 'Autre groupe', 0),
];

const placements: Placement[] = [
  place('article', 1, 1, null, 2),
  place('exam', 1, 1, null, 0),
  place('quiz', 1, 1, 2, 0),
  place('deck', 1, 1, 1, 1),
  place('resource', 1, 1, 1, 0),
  place('resource', 2, 1, 2, 1),
  place('article', 5, 2, null, 0),
];

describe('childrenOf', () => {
  it('renvoie les dossiers puis les éléments du niveau, triés par position', () => {
    const root = childrenOf(1, null, folders, placements);
    expect(root.folders.map((f) => f.id)).toEqual([3, 1]);
    expect(root.items.map((p) => `${p.type}:${p.id}`)).toEqual(['exam:1', 'article:1']);

    const week = childrenOf(1, 1, folders, placements);
    expect(week.folders.map((f) => f.id)).toEqual([2]);
    expect(week.items.map((p) => `${p.type}:${p.id}`)).toEqual(['resource:1', 'deck:1']);
  });

  it('ignore les dossiers et placements des autres groupes', () => {
    const root = childrenOf(2, null, folders, placements);
    expect(root.folders.map((f) => f.id)).toEqual([9]);
    expect(root.items.map((p) => `${p.type}:${p.id}`)).toEqual(['article:5']);
  });
});

describe('breadcrumb', () => {
  it('renvoie le chemin de la racine au dossier', () => {
    expect(breadcrumb(2, folders).map((f) => f.name)).toEqual(['Semaine 1', 'Exercices']);
    expect(breadcrumb(1, folders).map((f) => f.id)).toEqual([1]);
  });

  it('renvoie un chemin vide pour la racine ou un dossier inconnu', () => {
    expect(breadcrumb(null, folders)).toEqual([]);
    expect(breadcrumb(404, folders)).toEqual([]);
  });

  it('ne boucle pas sur un cycle de parents corrompu', () => {
    const cyclic = [folder(1, 1, 2, 'A', 0), folder(2, 1, 1, 'B', 0)];
    expect(breadcrumb(1, cyclic).length).toBeLessThanOrEqual(2);
  });
});

describe('pathLabel', () => {
  const groups = [group(1, 'SVT'), group(2, 'Anglais')];

  it('choisit le premier groupe dans l’ordre alphabétique', () => {
    const multi = [
      place('quiz', 7, 1, 2, 0),
      place('quiz', 7, 2, 9, 0),
    ];
    expect(pathLabel('quiz', 7, multi, folders, groups)).toEqual({
      groupId: 2,
      folderId: 9,
      label: 'Anglais › Autre groupe',
    });
  });

  it('affiche le groupe puis le dossier le plus profond', () => {
    expect(pathLabel('quiz', 1, placements, folders, groups)).toEqual({
      groupId: 1,
      folderId: 2,
      label: 'SVT › Exercices',
    });
  });

  it('affiche le groupe seul pour un contenu à la racine', () => {
    expect(pathLabel('article', 1, placements, folders, groups)?.label).toBe('SVT');
  });

  it('tronque le libellé à 24 caractères', () => {
    const long = [folder(50, 1, null, 'Chapitre trois : la cellule végétale', 0)];
    const result = pathLabel('resource', 3, [place('resource', 3, 1, 50, 0)], long, groups);
    expect(result?.label).toHaveLength(24);
    expect(result?.label.startsWith('SVT › Chapitre')).toBe(true);
    expect(result?.label.endsWith('…')).toBe(true);
  });

  it('renvoie null sans placement dans un groupe connu', () => {
    expect(pathLabel('deck', 99, placements, folders, groups)).toBeNull();
    expect(pathLabel('article', 5, placements, folders, [group(1, 'SVT')])).toBeNull();
  });
});

describe('folderProgress', () => {
  const consulted = (type: ContentType, id: number): boolean =>
    (type === 'quiz' && id === 1) || (type === 'resource' && id === 1);

  it('compte récursivement les contenus du sous-arbre', () => {
    // Semaine 1 : deck 1, resource 1 + Exercices : quiz 1, resource 2.
    expect(folderProgress(1, 1, folders, placements, consulted)).toEqual({ done: 2, total: 4 });
    expect(folderProgress(1, 2, folders, placements, consulted)).toEqual({ done: 1, total: 2 });
    expect(folderProgress(1, 3, folders, placements, consulted)).toEqual({ done: 0, total: 0 });
  });

  it('couvre tout le groupe depuis la racine', () => {
    expect(folderProgress(1, null, folders, placements, consulted)).toEqual({ done: 2, total: 6 });
  });
});
