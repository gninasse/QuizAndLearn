/**
 * Puce de chemin sous le titre d'une carte (« SVT › Ch.3 ») : elle situe le
 * contenu dans l'onglet Cours et y mène d'un clic.
 *
 * Les cartes sont souvent elles-mêmes des liens : la puce n'est donc pas un
 * `<a>` (liens imbriqués interdits) mais un `span[role=link]` ; un écouteur
 * global, en capture, la fait naviguer sans suivre le lien de la carte.
 */

import { escapeHtml } from '../core/html';
import { pathLabel } from '../domain/folders';
import type { ContentType } from '../domain/types';
import { foldersStore, groupsStore, placementsStore } from '../stores';
import { navigateTo } from '../views/helpers';

/** Adresse de l'onglet Cours pour un groupe et un dossier (racine si null). */
export function coursPath(groupId: number, folderId: number | null): string {
  return folderId === null ? `/cours/${groupId}` : `/cours/${groupId}/${folderId}`;
}

/** HTML de la puce, ou chaîne vide si le contenu n'est rangé dans aucun groupe connu. */
export function pathChipHtml(type: ContentType, id: number): string {
  const path = pathLabel(type, id, placementsStore.get(), foldersStore.get(), groupsStore.get());
  if (!path) return '';
  const href = coursPath(path.groupId, path.folderId);
  return (
    `<span role="link" tabindex="0" data-path-chip="${escapeHtml(href)}" title="Voir dans Cours" ` +
    `class="path-chip inline-flex items-center gap-1 max-w-full self-start rounded-full bg-zinc-100 dark:bg-zinc-800 ` +
    `hover:bg-sky-50 dark:hover:bg-sky-500/10 text-zinc-600 dark:text-zinc-300 text-[11px] font-semibold px-2 py-0.5 cursor-pointer">` +
    `<i class="bi bi-folder2" aria-hidden="true"></i><span class="truncate">${escapeHtml(path.label)}</span></span>`
  );
}

let installed = false;

function chipFrom(event: Event): HTMLElement | null {
  return (event.target as Element | null)?.closest?.<HTMLElement>('[data-path-chip]') ?? null;
}

/** Écouteurs globaux (clic, Entrée) : idempotent. */
export function installPathChips(): void {
  if (installed) return;
  installed = true;

  const follow = (event: Event, chip: HTMLElement): void => {
    event.preventDefault();
    event.stopPropagation();
    const href = chip.dataset.pathChip;
    if (href) navigateTo(href);
  };

  document.addEventListener(
    'click',
    (event) => {
      const chip = chipFrom(event);
      if (chip) follow(event, chip);
    },
    true,
  );
  document.addEventListener(
    'keydown',
    (event) => {
      const chip = chipFrom(event);
      if (chip && (event.key === 'Enter' || event.key === ' ')) follow(event, chip);
    },
    true,
  );
}
