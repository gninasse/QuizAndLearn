/**
 * Algorithme SuperMemo-2 — miroir exact de Modules/Core/app/Services/Sm2Service.php.
 *
 * Les vecteurs de test de sm2.test.ts sont les mêmes que ceux de
 * tests/Unit/Sm2ServiceTest.php : toute divergence client/serveur casse les tests.
 */

export const MIN_EASINESS = 1.3;
export const DEFAULT_EASINESS = 2.5;

export interface Sm2State {
  easinessFactor: number;
  repetitions: number;
  intervalDays: number;
}

export interface Sm2Result extends Sm2State {
  nextReview: Date;
}

export type CardStatus = 'new' | 'learning' | 'review' | 'relearning' | 'mastered';

export function review(
  state: Sm2State,
  quality: number,
  intervalMin = 1,
  intervalMax: number | null = null,
  now: Date = new Date(),
): Sm2Result {
  const q = Math.max(0, Math.min(5, Math.trunc(quality)));
  let { easinessFactor, repetitions, intervalDays } = state;
  let interval: number;

  if (q < 3) {
    repetitions = 0;
    interval = 1;
  } else {
    if (repetitions === 0) {
      interval = 1;
    } else if (repetitions === 1) {
      interval = 6;
    } else {
      interval = Math.round(intervalDays * easinessFactor);
    }
    repetitions++;
  }

  // EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02)), plancher 1.3
  easinessFactor = easinessFactor + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02));
  if (easinessFactor < MIN_EASINESS) {
    easinessFactor = MIN_EASINESS;
  }
  easinessFactor = Math.round(easinessFactor * 100) / 100;

  interval = Math.max(intervalMin, interval);
  if (intervalMax !== null) {
    interval = Math.min(intervalMax, interval);
  }

  const nextReview = new Date(now);
  nextReview.setDate(nextReview.getDate() + interval);

  return { easinessFactor, repetitions, intervalDays: interval, nextReview };
}

export function statusFor(repetitions: number, quality: number): CardStatus {
  if (quality < 3) return 'relearning';
  if (repetitions >= 5) return 'mastered';
  if (repetitions >= 3) return 'review';
  return 'learning';
}

/** Une carte est due si elle n'a jamais été révisée ou si next_review est passé. */
export function isDue(nextReview: string | null | undefined, now: Date = new Date()): boolean {
  if (!nextReview) return true;
  return new Date(nextReview).getTime() <= now.getTime();
}
