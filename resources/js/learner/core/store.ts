/**
 * Mini-store typé (pub/sub) — zéro dépendance.
 *
 * const count = createStore(0);
 * const unsub = count.subscribe((v) => render(v));
 * count.set(1); count.update((v) => v + 1);
 */

export type Unsubscribe = () => void;

export interface Store<T> {
  get(): T;
  set(value: T): void;
  update(fn: (current: T) => T): void;
  subscribe(listener: (value: T) => void): Unsubscribe;
}

export function createStore<T>(initial: T): Store<T> {
  let value = initial;
  const listeners = new Set<(value: T) => void>();

  return {
    get: () => value,
    set(next: T) {
      if (Object.is(next, value)) return;
      value = next;
      listeners.forEach((listener) => listener(value));
    },
    update(fn: (current: T) => T) {
      this.set(fn(value));
    },
    subscribe(listener: (value: T) => void): Unsubscribe {
      listeners.add(listener);
      listener(value);
      return () => listeners.delete(listener);
    },
  };
}
