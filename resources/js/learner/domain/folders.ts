/**
 * Arborescence des dossiers d'un groupe — fonctions pures, sans accès aux stores.
 *
 * Les dossiers et placements reçus sont déjà filtrés par le serveur (visibilité
 * calculée par groupe) : ici on ne fait que naviguer dans l'arbre.
 */

import type { ContentType, Folder, GroupInfo, Placement } from './types';

/** Longueur maximale du libellé de chemin (« SVT › Ch.3 »). */
const PATH_LABEL_MAX = 24;
const PATH_SEPARATOR = ' › ';

const byPosition = <T extends { position: number }>(a: T, b: T): number => a.position - b.position;

/** Contenu direct d'un dossier (ou de la racine si `folderId` est null) : dossiers d'abord. */
export function childrenOf(
  groupId: number,
  folderId: number | null,
  folders: Folder[],
  placements: Placement[],
): { folders: Folder[]; items: Placement[] } {
  return {
    folders: folders.filter((f) => f.group_id === groupId && f.parent_id === folderId).sort(byPosition),
    items: placements.filter((p) => p.group_id === groupId && p.folder_id === folderId).sort(byPosition),
  };
}

/** Chemin de la racine jusqu'au dossier inclus ; vide pour la racine ou un dossier inconnu. */
export function breadcrumb(folderId: number | null, folders: Folder[]): Folder[] {
  const byId = new Map(folders.map((f) => [f.id, f]));
  const path: Folder[] = [];
  const seen = new Set<number>();

  let current = folderId === null ? undefined : byId.get(folderId);
  // Garde anti-cycle : une donnée corrompue ne doit pas bloquer l'interface.
  while (current && !seen.has(current.id)) {
    seen.add(current.id);
    path.unshift(current);
    current = current.parent_id === null ? undefined : byId.get(current.parent_id);
  }

  return path;
}

function truncate(label: string, max: number): string {
  return label.length > max ? `${label.slice(0, max - 1)}…` : label;
}

/**
 * Emplacement d'un contenu pour l'afficher sous son titre : premier groupe
 * (ordre alphabétique) où il est placé, puis son dossier, ex. « SVT › Ch.3 ».
 */
export function pathLabel(
  type: ContentType,
  id: number,
  placements: Placement[],
  folders: Folder[],
  groups: GroupInfo[],
): { groupId: number; folderId: number | null; label: string } | null {
  const placed = new Map(
    placements.filter((p) => p.type === type && p.id === id).map((p) => [p.group_id, p]),
  );

  const group = groups
    .filter((g) => placed.has(g.id))
    .sort((a, b) => a.name.localeCompare(b.name, 'fr', { sensitivity: 'base' }))[0];
  if (!group) return null;

  const placement = placed.get(group.id)!;
  const folder = placement.folder_id === null ? undefined : folders.find((f) => f.id === placement.folder_id);
  const label = folder ? `${group.name}${PATH_SEPARATOR}${folder.name}` : group.name;

  return { groupId: group.id, folderId: placement.folder_id, label: truncate(label, PATH_LABEL_MAX) };
}

/** Avancement « n/m consultés » d'un dossier, sous-dossiers compris (racine si null). */
export function folderProgress(
  groupId: number,
  folderId: number | null,
  folders: Folder[],
  placements: Placement[],
  consulted: (type: ContentType, id: number) => boolean,
): { done: number; total: number } {
  let done = 0;
  let total = 0;
  const seen = new Set<number | null>();
  const pending: (number | null)[] = [folderId];

  while (pending.length) {
    const current = pending.pop()!;
    if (seen.has(current)) continue;
    seen.add(current);

    const children = childrenOf(groupId, current, folders, placements);
    for (const item of children.items) {
      total++;
      if (consulted(item.type, item.id)) done++;
    }
    pending.push(...children.folders.map((f) => f.id));
  }

  return { done, total };
}
