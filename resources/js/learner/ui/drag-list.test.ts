import { describe, expect, it } from 'vitest';
import { indexForCenter, makeListDraggable } from './drag-list';

describe('indexForCenter', () => {
  it('place le centre avant/entre/après les lignes', () => {
    const centers = [50, 150, 250];
    expect(indexForCenter(centers, 10)).toBe(0);
    expect(indexForCenter(centers, 100)).toBe(1);
    expect(indexForCenter(centers, 200)).toBe(2);
    expect(indexForCenter(centers, 999)).toBe(2); // borné à la fin
  });

  it('liste vide → 0', () => {
    expect(indexForCenter([], 100)).toBe(0);
  });
});

describe('makeListDraggable', () => {
  function buildList(keys: string[]): HTMLElement {
    const list = document.createElement('ol');
    list.innerHTML = keys
      .map((k) => `<li data-key="${k}"><span class="drag-handle">≡</span>${k}</li>`)
      .join('');
    document.body.append(list);
    return list;
  }

  it("commit l'ordre DOM courant au relâchement après un vrai déplacement", () => {
    const list = buildList(['a', 'b', 'c']);
    let committed: string[] | null = null;
    makeListDraggable(list, { handle: '.drag-handle', onCommit: (keys) => (committed = keys) });

    const handle = list.querySelector<HTMLElement>('li[data-key="a"] .drag-handle')!;
    handle.setPointerCapture = () => undefined; // non implémenté par happy-dom

    handle.dispatchEvent(new PointerEvent('pointerdown', { clientY: 10, bubbles: true }));
    // Déplacement > seuil de 4px (happy-dom n'a pas de layout : pas de réinsertion,
    // mais le commit doit refléter l'ordre DOM et se déclencher).
    handle.dispatchEvent(new PointerEvent('pointermove', { clientY: 60, bubbles: true }));
    handle.dispatchEvent(new PointerEvent('pointerup', { clientY: 60, bubbles: true }));

    expect(committed).toEqual(['a', 'b', 'c']);
  });

  it('un simple tap (< 4px) ne déclenche pas de commit', () => {
    const list = buildList(['a', 'b']);
    let committed: string[] | null = null;
    makeListDraggable(list, { handle: '.drag-handle', onCommit: (keys) => (committed = keys) });

    const handle = list.querySelector<HTMLElement>('.drag-handle')!;
    handle.setPointerCapture = () => undefined;

    handle.dispatchEvent(new PointerEvent('pointerdown', { clientY: 10, bubbles: true }));
    handle.dispatchEvent(new PointerEvent('pointermove', { clientY: 12, bubbles: true }));
    handle.dispatchEvent(new PointerEvent('pointerup', { clientY: 12, bubbles: true }));

    expect(committed).toBeNull();
  });
});
