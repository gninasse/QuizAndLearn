import type { Store, Unsubscribe } from './store';

/**
 * Socle des Web Components du volet apprenant.
 *
 * - `render()` est appelé à la connexion ; `rerender()` à la demande.
 * - `watch(store)` re-rend automatiquement quand le store change et se
 *   désabonne tout seul à la déconnexion.
 * - Pas de Shadow DOM : Tailwind global doit s'appliquer.
 */
export abstract class BaseComponent extends HTMLElement {
  private subscriptions: Unsubscribe[] = [];

  private connected = false;

  connectedCallback(): void {
    this.connected = true;
    this.rerender();
    this.onConnected();
  }

  disconnectedCallback(): void {
    this.connected = false;
    this.subscriptions.forEach((unsub) => unsub());
    this.subscriptions = [];
    this.onDisconnected();
  }

  /** Hook post-connexion (listeners globaux, timers…). */
  protected onConnected(): void {}

  /** Hook de nettoyage complémentaire. */
  protected onDisconnected(): void {}

  /** HTML de la vue — utiliser le helper `html` (échappement par défaut). */
  protected abstract render(): string;

  /** Brancher les listeners après chaque rendu. */
  protected bind(): void {}

  rerender(): void {
    if (!this.connected) return;
    this.innerHTML = this.render();
    this.bind();
  }

  /** Re-rend le composant à chaque changement du store (auto-désabonné). */
  protected watch<T>(store: Store<T>): void {
    let first = true;
    this.subscriptions.push(
      store.subscribe(() => {
        // subscribe() émet immédiatement : on saute ce premier appel,
        // connectedCallback fait déjà le rendu initial.
        if (first) {
          first = false;
          return;
        }
        this.rerender();
      }),
    );
  }

  /** Abonnement arbitraire nettoyé à la déconnexion. */
  protected own(unsub: Unsubscribe): void {
    this.subscriptions.push(unsub);
  }

  /** Query typée, lève si absent — les templates sont sous notre contrôle. */
  protected $<T extends Element>(selector: string): T {
    const el = this.querySelector<T>(selector);
    if (!el) throw new Error(`Élément introuvable : ${selector}`);
    return el;
  }

  protected $$<T extends Element>(selector: string): T[] {
    return Array.from(this.querySelectorAll<T>(selector));
  }
}

/** Enregistre un custom element une seule fois (HMR-safe). */
export function define(tag: string, ctor: CustomElementConstructor): void {
  if (!customElements.get(tag)) {
    customElements.define(tag, ctor);
  }
}
