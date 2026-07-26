import { describe, expect, it } from 'vitest';
import { isDue, review, statusFor } from './sm2';

/**
 * Mêmes vecteurs que tests/Unit/Sm2ServiceTest.php — garantie de parité
 * client/serveur. Toute modification doit être répercutée des deux côtés.
 */
describe('sm2.review', () => {
  it('première révision réussie : intervalle 1 jour', () => {
    const r = review({ easinessFactor: 2.5, repetitions: 0, intervalDays: 0 }, 5);
    expect(r.intervalDays).toBe(1);
    expect(r.repetitions).toBe(1);
    expect(r.easinessFactor).toBe(2.6);
  });

  it('deuxième révision réussie : intervalle 6 jours', () => {
    const r = review({ easinessFactor: 2.6, repetitions: 1, intervalDays: 1 }, 5);
    expect(r.intervalDays).toBe(6);
    expect(r.repetitions).toBe(2);
  });

  it('troisième révision : intervalle × EF', () => {
    const r = review({ easinessFactor: 2.6, repetitions: 2, intervalDays: 6 }, 4);
    expect(r.intervalDays).toBe(16); // round(6 × 2.6)
    expect(r.repetitions).toBe(3);
    expect(r.easinessFactor).toBe(2.6); // q=4 : EF inchangé
  });

  it('échec : répétitions et intervalle réinitialisés', () => {
    const r = review({ easinessFactor: 2.6, repetitions: 4, intervalDays: 30 }, 1);
    expect(r.intervalDays).toBe(1);
    expect(r.repetitions).toBe(0);
    expect(r.easinessFactor).toBe(2.06); // EF - 0.54
  });

  it("l'EF ne descend jamais sous 1.3", () => {
    const r = review({ easinessFactor: 1.3, repetitions: 0, intervalDays: 0 }, 0);
    expect(r.easinessFactor).toBe(1.3);
  });

  it('q=3 diminue EF de 0.14', () => {
    const r = review({ easinessFactor: 2.5, repetitions: 0, intervalDays: 0 }, 3);
    expect(r.easinessFactor).toBe(2.36);
    expect(r.repetitions).toBe(1);
  });

  it("l'intervalle est borné par le max du deck", () => {
    expect(review({ easinessFactor: 2.5, repetitions: 5, intervalDays: 200 }, 5, 1, 365).intervalDays).toBe(365);
    expect(review({ easinessFactor: 2.5, repetitions: 5, intervalDays: 200 }, 5, 1, 90).intervalDays).toBe(90);
  });

  it("l'intervalle respecte le min du deck", () => {
    expect(review({ easinessFactor: 2.5, repetitions: 0, intervalDays: 0 }, 5, 3).intervalDays).toBe(3);
  });

  it('next_review = maintenant + intervalle', () => {
    const now = new Date('2026-07-26T10:00:00Z');
    const r = review({ easinessFactor: 2.6, repetitions: 1, intervalDays: 1 }, 5, 1, null, now);
    expect(r.nextReview.toISOString().slice(0, 10)).toBe('2026-08-01');
  });
});

describe('sm2.statusFor', () => {
  it('mappe repetitions/quality vers le statut', () => {
    expect(statusFor(0, 1)).toBe('relearning');
    expect(statusFor(1, 5)).toBe('learning');
    expect(statusFor(2, 4)).toBe('learning');
    expect(statusFor(3, 4)).toBe('review');
    expect(statusFor(5, 5)).toBe('mastered');
  });
});

describe('sm2.isDue', () => {
  it('jamais révisée → due', () => {
    expect(isDue(null)).toBe(true);
    expect(isDue(undefined)).toBe(true);
  });

  it('date passée → due, date future → pas due', () => {
    const now = new Date('2026-07-26T10:00:00Z');
    expect(isDue('2026-07-25T10:00:00Z', now)).toBe(true);
    expect(isDue('2026-07-27T10:00:00Z', now)).toBe(false);
  });
});
