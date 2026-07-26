/**
 * Réordonnancement vertical par glisser-déposer — Pointer Events.
 *
 * Fonctionne souris + tactile (pas d'API HTML5 drag & drop, morte sur
 * mobile). La poignée porte `touch-action: none` pour ne pas entrer en
 * conflit avec le scroll de la page ; le reste de la ligne scrolle
 * normalement. Les flèches ↑↓ restent le fallback accessible.
 *
 * Chaque élément direct de la liste doit porter `data-key` (identifiant
 * stable) ; `onCommit` reçoit l'ordre final des keys au relâchement.
 */

interface DragListOptions {
  /** Sélecteur de la poignée à l'intérieur de chaque item. */
  handle: string;
  onCommit: (orderedKeys: string[]) => void;
}

/** Index cible d'un centre `y` parmi des centres de lignes triés. Exporté pour les tests. */
export function indexForCenter(centers: number[], y: number): number {
  let index = 0;
  for (const center of centers) {
    if (y > center) index++;
  }
  return Math.min(index, Math.max(0, centers.length - 1));
}

export function makeListDraggable(list: HTMLElement, options: DragListOptions): void {
  list.querySelectorAll<HTMLElement>(options.handle).forEach((handle) => {
    handle.addEventListener('pointerdown', (event) => start(event, handle));
  });

  function start(event: PointerEvent, handle: HTMLElement): void {
    // Bouton principal / premier doigt uniquement.
    if (event.button !== 0 && event.pointerType === 'mouse') return;

    const item = handle.closest<HTMLElement>('[data-key]');
    if (!item || item.parentElement !== list) return;

    event.preventDefault();
    handle.setPointerCapture(event.pointerId);

    let startPointerY = event.clientY;
    let moved = false;

    const baseStyle = item.getAttribute('style') ?? '';

    const applyDrag = (): void => {
      item.style.zIndex = '30';
      item.style.position = 'relative';
      item.style.boxShadow = '0 8px 24px rgb(0 0 0 / 0.18)';
      item.style.opacity = '0.95';
      item.classList.add('drag-active');
    };

    const onMove = (moveEvent: PointerEvent): void => {
      const dy = moveEvent.clientY - startPointerY;
      if (!moved && Math.abs(dy) < 4) return; // seuil anti-tap
      if (!moved) {
        moved = true;
        applyDrag();
      }

      item.style.transform = `translateY(${dy}px)`;

      const itemRect = item.getBoundingClientRect();
      const itemCenter = itemRect.top + itemRect.height / 2;

      const previous = item.previousElementSibling as HTMLElement | null;
      const next = item.nextElementSibling as HTMLElement | null;

      const reinsert = (position: () => void): void => {
        const before = item.offsetTop;
        position();
        const shift = item.offsetTop - before;
        // L'élément a changé de place dans le flux : on compense pour que
        // la ligne reste sous le doigt sans saut visuel.
        startPointerY += shift;
        item.style.transform = `translateY(${moveEvent.clientY - startPointerY}px)`;
      };

      if (previous) {
        const rect = previous.getBoundingClientRect();
        if (itemCenter < rect.top + rect.height / 2) {
          reinsert(() => list.insertBefore(item, previous));
          return;
        }
      }
      if (next) {
        const rect = next.getBoundingClientRect();
        if (itemCenter > rect.top + rect.height / 2) {
          reinsert(() => list.insertBefore(item, next.nextSibling));
        }
      }
    };

    const finish = (): void => {
      handle.removeEventListener('pointermove', onMove);
      handle.removeEventListener('pointerup', finish);
      handle.removeEventListener('pointercancel', finish);

      item.setAttribute('style', baseStyle);
      item.classList.remove('drag-active');

      if (moved) {
        const orderedKeys = [...list.children]
          .map((child) => (child as HTMLElement).dataset.key)
          .filter((key): key is string => key !== undefined);
        options.onCommit(orderedKeys);
      }
    };

    handle.addEventListener('pointermove', onMove);
    handle.addEventListener('pointerup', finish);
    handle.addEventListener('pointercancel', finish);
  }
}
