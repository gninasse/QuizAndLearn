import { alertDialog } from './app-dialog';
import { raw } from '../core/html';
import { toast } from './app-toast';
import { logoMark } from './logo';

/**
 * Prompt d'installation maîtrisé :
 * - Chrome/Edge : capture `beforeinstallprompt`, bannière au moment choisi
 *   (après connexion, utilisateur engagé) + bouton permanent dans Profil.
 * - iOS Safari : pas d'API — guide « Partager → Sur l'écran d'accueil ».
 */

interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let deferredPrompt: BeforeInstallPromptEvent | null = null;

const DISMISSED_KEY = 'learner-install-dismissed';

export function captureInstallPrompt(): void {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event as BeforeInstallPromptEvent;
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    localStorage.removeItem(DISMISSED_KEY);
    toast('Application installée ✨', 'success');
  });
}

/**
 * L'app tourne-t-elle installée (hors navigateur) ?
 *
 * Le manifeste demande `fullscreen` en priorité : il faut donc tester tous
 * les modes installés, sinon `(display-mode: standalone)` seul renvoie faux
 * en immersif et l'app se croirait encore dans un onglet.
 * `navigator.standalone` couvre iOS, qui ignore `fullscreen`.
 */
export function isStandalone(): boolean {
  const installedModes = ['fullscreen', 'standalone', 'minimal-ui'];

  return (
    installedModes.some((mode) => window.matchMedia(`(display-mode: ${mode})`).matches) ||
    (navigator as { standalone?: boolean }).standalone === true
  );
}

function isIos(): boolean {
  return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

/** L'installation peut être proposée dès que l'app ne tourne pas en standalone. */
export function canOfferInstall(): boolean {
  return !isStandalone();
}

export async function triggerInstall(): Promise<void> {
  if (deferredPrompt) {
    const prompt = deferredPrompt;
    deferredPrompt = null;
    await prompt.prompt();
    const choice = await prompt.userChoice;
    if (choice.outcome !== 'accepted') {
      localStorage.setItem(DISMISSED_KEY, new Date().toISOString());
    }
    return;
  }

  if (isIos()) {
    await alertDialog(
      "Installer l'application",
      raw(
        `<ol class="text-sm text-zinc-600 dark:text-zinc-300 flex flex-col gap-2 list-decimal pl-5">
           <li>Touchez le bouton <b>Partager</b> <i class="bi bi-box-arrow-up"></i> de Safari</li>
           <li>Choisissez <b>« Sur l'écran d'accueil »</b> <i class="bi bi-plus-square"></i></li>
           <li>Validez : Learn&amp;Quiz s'ouvrira comme une application</li>
         </ol>`,
      ),
    );
    return;
  }

  // Navigateur sans beforeinstallprompt (ou pas encore déclenché) :
  // guider vers le menu du navigateur.
  await alertDialog(
    "Installer l'application",
    raw(
      `<p class="text-sm text-zinc-600 dark:text-zinc-300">
         Ouvrez le menu de votre navigateur (<i class="bi bi-three-dots-vertical"></i> en haut à droite)
         puis choisissez <b>« Installer l'application »</b> ou
         <b>« Ajouter à l'écran d'accueil »</b>.
       </p>`,
    ),
  );
}

/**
 * Bannière discrète post-connexion (une seule fois, mémorise le refus).
 */
export function maybeShowInstallBanner(): void {
  if (!canOfferInstall() || localStorage.getItem(DISMISSED_KEY)) return;

  window.setTimeout(() => {
    if (!canOfferInstall() || document.getElementById('install-banner')) return;

    const banner = document.createElement('div');
    banner.id = 'install-banner';
    banner.className =
      'fixed bottom-20 lg:bottom-6 inset-x-3 sm:inset-x-auto sm:right-6 sm:w-96 z-[80] ' +
      'rounded-2xl border border-sky-200 dark:border-sky-500/30 bg-white dark:bg-zinc-900 shadow-2xl p-4 flex items-start gap-3';
    banner.innerHTML = `
      <span aria-hidden="true">${logoMark('w-11 h-11')}</span>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-bold">Installer Learn&Quiz</p>
        <p class="text-xs text-zinc-500 mt-0.5">Accès depuis l'écran d'accueil, plein écran, et tout fonctionne hors-ligne.</p>
        <div class="flex gap-2 mt-2.5">
          <button data-install class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-3.5 py-2">Installer</button>
          <button data-later class="rounded-lg text-xs font-semibold text-zinc-500 px-3 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">Plus tard</button>
        </div>
      </div>
      <button data-close aria-label="Fermer" class="text-zinc-400 hover:text-zinc-600 p-1"><i class="bi bi-x-lg"></i></button>`;

    const dismiss = (): void => {
      localStorage.setItem(DISMISSED_KEY, new Date().toISOString());
      banner.remove();
    };
    banner.querySelector('[data-install]')?.addEventListener('click', () => {
      banner.remove();
      void triggerInstall();
    });
    banner.querySelector('[data-later]')?.addEventListener('click', dismiss);
    banner.querySelector('[data-close]')?.addEventListener('click', dismiss);

    document.body.append(banner);
  }, 4000);
}
