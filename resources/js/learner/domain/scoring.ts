/**
 * Notation de quiz côté client — miroir de QuizScoringService.php.
 *
 * Sert uniquement à l'affichage immédiat du résultat (y compris hors-ligne) ;
 * le serveur re-note la tentative à la synchronisation et reste l'autorité.
 */

import type { QuizItem, QuizQuestion } from './types';

export type AnswerValue = string | string[] | Record<string, string[]> | null;

export interface QuestionScore {
  earned: number;
  isCorrect: boolean;
}

export interface QuizScore {
  totalPoints: number;
  scoredPoints: number;
  scorePercent: number;
  passed: boolean;
  perQuestion: Record<number, QuestionScore>;
}

function asBool(value: unknown): boolean {
  return value === true || value === 'true' || value === '1' || value === 1;
}

export function scoreQuestion(question: QuizQuestion, userAns: AnswerValue): QuestionScore {
  const options = question.options as Record<string, any>;
  const points = question.points;

  if (userAns === null || userAns === undefined) {
    return { earned: 0, isCorrect: false };
  }

  switch (question.type) {
    case 'true_false': {
      const correct = (options.correct_answer ?? 'true') === 'true';
      const userBool = asBool(userAns);
      return correct === userBool
        ? { earned: points, isCorrect: true }
        : { earned: 0, isCorrect: false };
    }

    case 'mcq':
    case 'single_choice':
    case 'multiple_choice': {
      const isMultiple = asBool(options.multiple) || question.type === 'multiple_choice';
      const answersList: Array<{ text: string; is_correct?: unknown }> = options.answers ?? [];
      const correctAnswers = answersList.filter((a) => asBool(a.is_correct)).map((a) => a.text);

      if (isMultiple) {
        const userArray = Array.isArray(userAns) ? userAns : [userAns as string];
        const matches = userArray.filter((a) => correctAnswers.includes(a)).length;
        const incorrect = userArray.filter((a) => !correctAnswers.includes(a)).length;
        const partial = asBool(options.partial_score);

        if (partial) {
          let earned = 0;
          if (correctAnswers.length > 0 && incorrect === 0) {
            earned = Math.round((matches / correctAnswers.length) * points);
          }
          return { earned, isCorrect: earned === points };
        }

        const exact =
          matches === correctAnswers.length &&
          incorrect === 0 &&
          userArray.length === correctAnswers.length;
        return exact ? { earned: points, isCorrect: true } : { earned: 0, isCorrect: false };
      }

      const userStr = Array.isArray(userAns) ? userAns[0] : (userAns as string);
      return correctAnswers.includes(userStr ?? '')
        ? { earned: points, isCorrect: true }
        : { earned: 0, isCorrect: false };
    }

    case 'fill_blank': {
      const blanks: Array<{ answers?: string[]; case_sensitive?: unknown }> = options.blanks ?? [];
      const userArray = Array.isArray(userAns) ? userAns : [userAns as string];
      let correctCount = 0;

      blanks.forEach((blank, idx) => {
        const uText = String(userArray[idx] ?? '').trim();
        const caseSensitive = asBool(blank.case_sensitive);
        const matched = (blank.answers ?? []).some((ans) =>
          caseSensitive ? ans === uText : ans.toLowerCase() === uText.toLowerCase(),
        );
        if (matched) correctCount++;
      });

      if (blanks.length === 0) return { earned: 0, isCorrect: false };
      const earned = Math.round((correctCount / blanks.length) * points);
      return { earned, isCorrect: correctCount === blanks.length };
    }

    case 'matching': {
      const pairs: Array<{ term?: string; definition?: string }> = options.pairs ?? [];
      const dict = (userAns ?? {}) as Record<string, string[]>;
      const terms = dict.terms ?? [];
      const definitions = dict.definitions ?? [];
      let matchCount = 0;

      terms.forEach((term, idx) => {
        const userDef = definitions[idx] ?? '';
        const pair = pairs.find((p) => (p.term ?? '') === term);
        if (pair && (pair.definition ?? '') === userDef) matchCount++;
      });

      if (pairs.length === 0) return { earned: 0, isCorrect: false };
      const earned = Math.round((matchCount / pairs.length) * points);
      return { earned, isCorrect: matchCount === pairs.length };
    }

    case 'ordering': {
      const items: string[] = options.items ?? [];
      const userArray = Array.isArray(userAns) ? (userAns as string[]) : [];
      let correctCount = 0;

      userArray.forEach((item, idx) => {
        if (items[idx] === item) correctCount++;
      });

      if (items.length === 0) return { earned: 0, isCorrect: false };
      const earned = Math.round((correctCount / items.length) * points);
      return { earned, isCorrect: correctCount === items.length };
    }

    case 'open_text': {
      const text = typeof userAns === 'string' ? userAns.trim() : '';
      return text.length > 0 ? { earned: points, isCorrect: true } : { earned: 0, isCorrect: false };
    }

    default:
      return { earned: 0, isCorrect: false };
  }
}

export function scoreQuiz(quiz: QuizItem, answers: Record<number, AnswerValue>): QuizScore {
  let totalPoints = 0;
  let scoredPoints = 0;
  const perQuestion: Record<number, QuestionScore> = {};

  for (const question of quiz.questions) {
    totalPoints += question.points;
    const result = scoreQuestion(question, answers[question.id] ?? null);
    perQuestion[question.id] = result;
    scoredPoints += result.earned;
  }

  const scorePercent = totalPoints > 0 ? Math.round((scoredPoints / totalPoints) * 10000) / 100 : 100;
  const passed = scorePercent >= (quiz.passing_score ?? 60);

  return { totalPoints, scoredPoints, scorePercent, passed, perQuestion };
}
