/**
 * Terminologie configurable du terme « groupe » (Classe, Promotion…).
 * Alimentée par `terms` (bootstrap, changes, réglages vitrine), persistée
 * dans Dexie `meta` ('terms') et restaurée au démarrage.
 */

import { createStore } from './store';

export type Terms = Record<string, string>;

export const DEFAULT_TERMS: Readonly<Terms> = Object.freeze({
  group: 'groupe',
  groups: 'groupes',
  Group: 'Groupe',
  Groups: 'Groupes',
  a_group: 'un groupe',
  the_group: 'le groupe',
  of_group: 'du groupe',
  this_group: 'ce groupe',
  no_group: 'aucun groupe',
});

export const termsStore = createStore<Terms>({ ...DEFAULT_TERMS });

/** Libellé d'un terme (clé inconnue → la clé elle-même). */
export function t(key: string): string {
  return termsStore.get()[key] ?? DEFAULT_TERMS[key] ?? key;
}

/** Première lettre en majuscule (début de phrase). */
export function tc(key: string): string {
  const v = t(key);
  return v.charAt(0).toUpperCase() + v.slice(1);
}

/** Accord féminin : « e » si le terme est féminin (suspendu → suspendue). */
export function fe(): string {
  return t('a_group').startsWith('une ') ? 'e' : '';
}

/** Remplace les termes ; ignore toute valeur invalide, complète avec les défauts. */
export function setTerms(next: unknown): void {
  if (!next || typeof next !== 'object') return;
  const clean: Terms = { ...DEFAULT_TERMS };
  for (const [k, v] of Object.entries(next as Record<string, unknown>)) {
    if (typeof v === 'string' && v.trim() !== '') clean[k] = v;
  }
  termsStore.set(clean);
}

export function resetTerms(): void {
  termsStore.set({ ...DEFAULT_TERMS });
}
