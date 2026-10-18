/**
 * Point d'entrée du volet apprenant (PWA).
 */

import './components/app-shell';
import type { AppShell } from './components/app-shell';
import { endConsultation, isConsultation, isImpersonationEnded } from './consultation/consultation';
import { db } from './db/schema';
import { loadGuestSettings } from './guest/guest-session';
import { Router, type RouteDefinition } from './router';
import { preferencesStore, sessionStore, syncStore } from './stores';
import {
  clearLocalData,
  fullBootstrap,
  hydrateFromDb,
  installSyncTriggers,
  onSyncEvent,
  sync,
} from './sync/engine';
import { applyPreferences, applyTheme, initialTheme, toggleTheme } from './theme';
import { celebrateDialog } from './ui/app-dialog';
import { toast } from './ui/app-toast';
import { captureInstallPrompt, maybeShowInstallBanner } from './ui/install';
import { dismissLightbox, installMediaGuard, setWatermark } from './ui/media-guard';
import { playBadge } from './ui/sound';

// ------------------------------------------------------------------ Thème

applyTheme(initialTheme());
captureInstallPrompt();
installMediaGuard();

// ---------------------------------------------------------------- Routage

const CONSULTATION_POLL_MS = 60_000;

const routes: RouteDefinition[] = [
  { path: '/connexion', title: 'Connexion', public: true, view: () => import('./views/login') },
  { path: '/', title: 'Tableau de bord', view: () => import('./views/dashboard') },
  { path: '/articles', title: 'Articles', view: () => import('./views/articles') },
  { path: '/articles/:id', title: 'Lecture', view: () => import('./views/article-detail') },
  { path: '/entrainement', title: 'Entraînement', view: () => import('./views/entrainement') },
  { path: '/examens', title: 'Examens', view: () => import('./views/examens') },
  { path: '/quizzes/:id', title: 'Quiz', view: () => import('./views/quiz-detail') },
  { path: '/quizzes/:id/play', title: 'Quiz en cours', view: () => import('./views/quiz-play') },
  { path: '/exams/:id/play', title: 'Examen', view: () => import('./views/exam-play') },
  { path: '/reviser', title: 'Révision', view: () => import('./views/reviser') },
  { path: '/reviser/:deckId', title: 'Révision', view: () => import('./views/reviser') },
  { path: '/demande-acces', title: 'Demander un accès', public: true, view: () => import('./views/demande-acces') },
  { path: '/mot-de-passe', title: 'Nouveau mot de passe', view: () => import('./views/mot-de-passe') },
  { path: '/profil', title: 'Profil', view: () => import('./views/profil') },
  { path: '/progression', title: 'Ma progression', view: () => import('./views/progression') },
];

async function boot(): Promise<void> {
  const root = document.getElementById('app');
  if (!root) return;

  // Consultation « Voir en tant que » : jamais d'écran de connexion. La base
  // dédiée est vidée puis remplie par un bootstrap avec le jeton.
  const consultation = isConsultation();
  if (consultation) {
    const started = await startConsultation(root);
    if (!started) return;
  }

  const shell = document.createElement('app-shell') as AppShell;
  root.replaceChildren(shell);

  // Session locale (fonctionne hors-ligne) puis préférences.
  const hasLocalSession = await hydrateFromDb();
  if (hasLocalSession && !consultation) {
    applyPreferences(preferencesStore.get());
    if (sessionStore.get()?.is_guest) await loadGuestSettings();
  }

  const redirectIfPasswordChangeRequired = (): void => {
    // Consultation : le mot de passe provisoire est seulement signalé (bandeau).
    if (consultation) {
      if (window.location.pathname === '/mot-de-passe') void router.go('/', true);
      return;
    }
    if (sessionStore.get()?.must_change_password && window.location.pathname !== '/mot-de-passe') {
      void router.go('/mot-de-passe', true);
    }
  };

  const router: Router = new Router({
    routes,
    outlet: () => shell.outlet,
    isAuthenticated: () => sessionStore.get() !== null,
    loginPath: '/connexion',
    defaultPath: '/',
    onNavigate: (route) => {
      redirectIfPasswordChangeRequired();
      dismissLightbox();
      shell.setTitle(route.title);
    },
  });

  // Filigrane nominatif des médias : suit la session courante.
  const updateWatermark = (): void => {
    const user = sessionStore.get();
    if (consultation) {
      setWatermark(user ? `Consultation · ${user.full_name}` : 'Consultation');
      return;
    }
    if (user?.is_guest) {
      setWatermark('Mode découverte');
      return;
    }
    const today = new Date().toLocaleDateString('fr-FR');
    setWatermark(user ? [user.full_name, user.matricule, today].filter(Boolean).join(' · ') : null);
  };
  updateWatermark();
  sessionStore.subscribe(updateWatermark);

  // Le shell se reconstruit uniquement sur bascule connecté/déconnecté ;
  // on re-résout alors la route pour remonter la vue dans le nouvel outlet.
  // (Un simple gain d'XP ne doit PAS remonter la vue — ex. en plein quiz.)
  let hadUser = sessionStore.get() !== null;
  sessionStore.subscribe((user) => {
    const hasUser = user !== null;
    if (hasUser !== hadUser) {
      hadUser = hasUser;
      void router.resolve();
    }
  });

  shell.addEventListener('theme-toggle', () => {
    const dark = toggleTheme();
    const preferences = { ...preferencesStore.get(), theme: dark ? ('dark' as const) : ('light' as const) };
    preferencesStore.set(preferences);
  });

  window.addEventListener('learner:authenticated', () => {
    applyPreferences(preferencesStore.get());
    if (sessionStore.get()?.must_change_password) {
      void router.go('/mot-de-passe', true);
      return;
    }
    void router.go('/', true);
    maybeShowInstallBanner();
  });

  window.addEventListener('learner:logged-out', () => {
    void router.go('/connexion', true);
  });

  // Après une sync, les vues « liste » se rafraîchissent avec les données
  // fraîches (stale-while-revalidate). Jamais pendant une activité en cours
  // (quiz, examen, session de révision) : on ne casse pas l'état local.
  const REFRESHABLE_PATHS = new Set(['/', '/articles', '/entrainement', '/examens', '/profil']);

  // Événements de synchronisation → retours visuels.
  onSyncEvent((event) => {
    if (event.kind === 'data-refreshed') {
      if (REFRESHABLE_PATHS.has(window.location.pathname)) {
        void router.resolve();
      }
      return;
    }
    if (event.kind === 'badges-unlocked') {
      playBadge();
      void celebrateDialog(
        'Nouveau badge débloqué !',
        event.names.join(', '),
        '🏅',
      );
    } else if (event.kind === 'action-rejected') {
      toast(`Une action n'a pas pu être synchronisée : ${event.message}`, 'warning', 5000);
    } else if (event.kind === 'account-suspended') {
      try {
        sessionStorage.setItem('learner:notice', event.message);
      } catch {
        /* stockage indisponible */
      }
      void clearLocalData().then(() => router.go('/connexion', true));
    } else if (event.kind === 'password-change-required') {
      const user = sessionStore.get();
      if (user && !user.must_change_password) sessionStore.set({ ...user, must_change_password: true });
      redirectIfPasswordChangeRequired();
    } else if (event.kind === 'session-expired' && consultation) {
      void endConsultation('ended');
    } else if (event.kind === 'session-expired') {
      toast('Session expirée — reconnectez-vous.', 'warning');
      sessionStore.set(null);
      void router.go('/connexion', true);
    }
  });

  installSyncTriggers();

  // Démarrage : route initiale puis sync en arrière-plan.
  await router.resolve();
  if (consultation) {
    // Bootstrap déjà fait. Delta périodique : détecte aussi la fin serveur
    // (expiration à 30 min, admin déconnecté) pour afficher l'écran de fin.
    window.setInterval(() => void sync(), CONSULTATION_POLL_MS);
    return;
  }
  if (hasLocalSession && navigator.onLine) {
    void sync();
    maybeShowInstallBanner();
  }

  // Compteur d'actions en attente affiché dans le header (déjà via stores).
  syncStore.subscribe(() => {
    /* le shell observe déjà syncStore ; hook conservé pour extensions */
  });
}

/**
 * Démarre la consultation : purge de la base dédiée puis bootstrap avec le
 * jeton. Renvoie false si l'onglet ne peut pas afficher l'espace (fin ou erreur).
 */
async function startConsultation(root: HTMLElement): Promise<boolean> {
  root.innerHTML =
    '<main class="min-h-dvh flex items-center justify-center text-sm text-zinc-500">Ouverture de la consultation…</main>';

  try {
    await Promise.all(db.tables.map((table) => table.clear()));
    await fullBootstrap();
    return true;
  } catch (e) {
    if (isImpersonationEnded(e)) {
      await endConsultation('ended');
      return false;
    }
    root.innerHTML = `
      <main class="min-h-dvh flex flex-col items-center justify-center gap-3 p-6 text-center">
        <p class="font-bold">Impossible d'ouvrir la consultation.</p>
        <p class="text-sm text-zinc-500">Vérifiez la connexion puis réessayez.</p>
        <button type="button" data-role="consultation-retry"
                class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-sm px-5 py-2.5">Réessayer</button>
      </main>`;
    root.querySelector('[data-role="consultation-retry"]')?.addEventListener('click', () => window.location.reload());
    return false;
  }
}

// --------------------------------------------------------- Service worker

function registerServiceWorker(): void {
  if (!('serviceWorker' in navigator) || import.meta.env.DEV) return;

  window.addEventListener('load', () => {
    void navigator.serviceWorker.register('/sw.js').then((registration) => {
      // Nouvelle version déployée → rechargement doux à l'activation.
      let hadController = Boolean(navigator.serviceWorker.controller);
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (hadController) {
          toast('Application mise à jour ✨', 'info');
        }
        hadController = true;
      });

      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        worker?.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) {
            // skipWaiting est déjà actif côté SW : l'activation est immédiate.
            worker.postMessage('skip-waiting');
          }
        });
      });
    });
  });
}

registerServiceWorker();
void boot();
