import { describe, expect, it } from 'vitest';
import { scoreQuestion, scoreQuiz } from './scoring';
import type { QuizItem, QuizQuestion } from './types';

/**
 * Vecteurs alignés sur QuizScoringService.php (mêmes règles, mêmes arrondis).
 */

function q(type: string, points: number, options: Record<string, unknown>): QuizQuestion {
  return { id: 1, question_text: 'Q', type, points, order: 1, options };
}

describe('scoreQuestion', () => {
  it('true_false', () => {
    const question = q('true_false', 4, { correct_answer: 'true' });
    expect(scoreQuestion(question, 'true')).toEqual({ earned: 4, isCorrect: true });
    expect(scoreQuestion(question, 'false')).toEqual({ earned: 0, isCorrect: false });
  });

  it('mcq simple', () => {
    const question = q('mcq', 3, {
      answers: [
        { text: 'A', is_correct: true },
        { text: 'B', is_correct: false },
      ],
    });
    expect(scoreQuestion(question, 'A').earned).toBe(3);
    expect(scoreQuestion(question, 'B').earned).toBe(0);
  });

  it('mcq multiple strict : tout ou rien', () => {
    const question = q('mcq', 4, {
      multiple: true,
      answers: [
        { text: 'A', is_correct: true },
        { text: 'B', is_correct: true },
        { text: 'C', is_correct: false },
      ],
    });
    expect(scoreQuestion(question, ['A', 'B']).earned).toBe(4);
    expect(scoreQuestion(question, ['A']).earned).toBe(0);
    expect(scoreQuestion(question, ['A', 'B', 'C']).earned).toBe(0);
  });

  it('mcq multiple partiel : proportionnel sans erreur, zéro si une erreur', () => {
    const question = q('mcq', 4, {
      multiple: true,
      partial_score: true,
      answers: [
        { text: 'A', is_correct: true },
        { text: 'B', is_correct: true },
        { text: 'C', is_correct: false },
      ],
    });
    expect(scoreQuestion(question, ['A'])).toEqual({ earned: 2, isCorrect: false });
    expect(scoreQuestion(question, ['A', 'C']).earned).toBe(0);
    expect(scoreQuestion(question, ['A', 'B'])).toEqual({ earned: 4, isCorrect: true });
  });

  it('fill_blank : par trou, sensibilité à la casse', () => {
    const question = q('fill_blank', 4, {
      blanks: [
        { answers: ['Paris'], case_sensitive: false },
        { answers: ['Laravel'], case_sensitive: true },
      ],
    });
    expect(scoreQuestion(question, ['paris', 'Laravel'])).toEqual({ earned: 4, isCorrect: true });
    expect(scoreQuestion(question, ['paris', 'laravel'])).toEqual({ earned: 2, isCorrect: false });
  });

  it('matching : proportionnel par paire', () => {
    const question = q('matching', 4, {
      pairs: [
        { term: 'HTTP', definition: 'Protocole' },
        { term: 'SQL', definition: 'Requêtes' },
      ],
    });
    expect(
      scoreQuestion(question, { terms: ['HTTP', 'SQL'], definitions: ['Protocole', 'Requêtes'] }),
    ).toEqual({ earned: 4, isCorrect: true });
    expect(
      scoreQuestion(question, { terms: ['HTTP', 'SQL'], definitions: ['Requêtes', 'Protocole'] }),
    ).toEqual({ earned: 0, isCorrect: false });
  });

  it('ordering : position par position', () => {
    const question = q('ordering', 3, { items: ['un', 'deux', 'trois'] });
    expect(scoreQuestion(question, ['un', 'deux', 'trois'])).toEqual({ earned: 3, isCorrect: true });
    expect(scoreQuestion(question, ['un', 'trois', 'deux'])).toEqual({ earned: 1, isCorrect: false });
  });

  it('open_text : crédit complet si non vide', () => {
    const question = q('open_text', 2, {});
    expect(scoreQuestion(question, 'ma réponse').earned).toBe(2);
    expect(scoreQuestion(question, '   ').earned).toBe(0);
  });
});

describe('scoreQuiz', () => {
  it('agrège points, pourcentage et réussite', () => {
    const quiz = {
      passing_score: 60,
      questions: [
        { id: 1, question_text: '', type: 'true_false', points: 5, order: 1, options: { correct_answer: 'true' } },
        {
          id: 2,
          question_text: '',
          type: 'mcq',
          points: 5,
          order: 2,
          options: { answers: [{ text: 'A', is_correct: true }] },
        },
      ],
    } as unknown as QuizItem;

    const result = scoreQuiz(quiz, { 1: 'true', 2: 'B' });
    expect(result.totalPoints).toBe(10);
    expect(result.scoredPoints).toBe(5);
    expect(result.scorePercent).toBe(50);
    expect(result.passed).toBe(false);
  });
});
