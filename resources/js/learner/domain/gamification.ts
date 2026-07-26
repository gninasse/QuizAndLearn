/**
 * Constantes de gamification — miroir de GamificationService.php.
 * Utilisées pour l'affichage immédiat hors-ligne ; le serveur reste l'autorité.
 */
export const GAMIFICATION = {
  XP_QUIZ_BASE: 20,
  XP_QUIZ_PASS_BONUS: 30,
  XP_PER_QUIZ_POINT: 5,
  XP_ARTICLE_COMPLETED: 15,
  XP_CARD_REVIEW: 5,
  XP_EXAM_PASSED: 50,
  XP_PER_LEVEL: 100,
} as const;

export function xpForQuizCompletion(passed: boolean, scoredPoints: number): number {
  return (
    GAMIFICATION.XP_QUIZ_BASE +
    (passed ? GAMIFICATION.XP_QUIZ_PASS_BONUS : 0) +
    scoredPoints * GAMIFICATION.XP_PER_QUIZ_POINT
  );
}

export function levelFor(totalXp: number): number {
  return Math.floor(totalXp / GAMIFICATION.XP_PER_LEVEL) + 1;
}
