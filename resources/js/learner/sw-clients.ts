/**
 * Choix de la fenêtre à réutiliser au clic sur une notification (service worker).
 * Module sans dépendance au contexte SW : testable sous vitest.
 */

/** Pages hors PWA apprenant (back-office, authentification serveur) : jamais détournées. */
const NON_LEARNER_PATH = /^\/(cores|admin|login|logout)(\/|$)/;

interface WindowLike {
  readonly url: string;
  readonly focused?: boolean;
}

/** Onglet apprenant de même origine, l'onglet au premier plan en priorité. */
export function pickLearnerClient<T extends WindowLike>(clients: readonly T[], origin: string): T | undefined {
  const learner = clients.filter((c) => {
    try {
      const url = new URL(c.url);
      return url.origin === origin && !NON_LEARNER_PATH.test(url.pathname);
    } catch {
      return false;
    }
  });
  return learner.find((c) => c.focused === true) ?? learner[0];
}
