import { beforeEach, describe, expect, it } from 'vitest';
import { DEFAULT_TERMS, fe, resetTerms, setTerms, t, tc, termsStore } from './terms';

const CLASSE = {
  group: 'classe',
  groups: 'classes',
  Group: 'Classe',
  Groups: 'Classes',
  a_group: 'une classe',
  the_group: 'la classe',
  of_group: 'de la classe',
  this_group: 'cette classe',
  no_group: 'aucune classe',
};

describe('terms', () => {
  beforeEach(() => resetTerms());

  it('defaults to masculine « groupe »', () => {
    expect(t('group')).toBe('groupe');
    expect(t('the_group')).toBe('le groupe');
    expect(tc('no_group')).toBe('Aucun groupe');
    expect(fe()).toBe('');
    expect(termsStore.get()).toEqual(DEFAULT_TERMS);
  });

  it('setTerms replaces labels and notifies subscribers', () => {
    const seen: string[] = [];
    termsStore.subscribe((v) => seen.push(v.group as string));
    setTerms(CLASSE);
    expect(t('groups')).toBe('classes');
    expect(tc('the_group')).toBe('La classe');
    expect(fe()).toBe('e');
    expect(seen).toEqual(['groupe', 'classe']);
  });

  it('ignores invalid payloads and falls back to defaults', () => {
    setTerms(null);
    setTerms({ group: '', groups: 42 });
    expect(t('group')).toBe('groupe');
    expect(t('unknown_key')).toBe('unknown_key');
  });
});
