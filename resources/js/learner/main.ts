/**
 * Point d'entrée du volet apprenant (PWA).
 */

import './components/app-shell';
import type { AppShell } from './components/app-shell';
import { Router, type RouteDefinition } from './router';
import { preferencesStore, sessionStore, syncStore } from './stores';
import { hydrateFromDb, installSyncTriggers, onSyncEvent, sync } from './sync/engine';
import { applyPreferences, applyTheme, initialTheme, toggleTheme } from './theme';
import { celebrateDialog } from './ui/app-dialog';
import { toast } from './ui/app-toast';

// ------------------------------------------------------------------ Thème

applyTheme(initialTheme());

// ---------------------------------------------------------------- Routage

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
  { path: '/profil', title: 'Profil', view: () => import('./views/profil') },
];

async function boot(): Promise<void> {
  const root = document.getElementById('app');
  if (!root) return;

  const shell = document.createElement('app-shell') as AppShell;
  root.replaceChildren(shell);

  // Session locale (fonctionne hors-ligne) puis préférences.
  const hasLocalSession = await hydrateFromDb();
  if (hasLocalSession) {
    applyPreferences(preferencesStore.get());
  }

  const router = new Router({
    routes,
    outlet: () => shell.outlet,
    isAuthenticated: () => sessionStore.get() !== null,
    loginPath: '/connexion',
    defaultPath: '/',
    onNavigate: (route) => shell.setTitle(route.title),
  });

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
    void router.go('/', true);
  });

  window.addEventListener('learner:logged-out', () => {
    void router.go('/connexion', true);
  });

  // Événements de synchronisation → retours visuels.
  onSyncEvent((event) => {
    if (event.kind === 'badges-unlocked') {
      void celebrateDialog(
        'Nouveau badge débloqué !',
        event.names.join(', '),
        '🏅',
      );
    } else if (event.kind === 'action-rejected') {
      toast(`Une action n'a pas pu être synchronisée : ${event.message}`, 'warning', 5000);
    } else if (event.kind === 'session-expired') {
      toast('Session expirée — reconnectez-vous.', 'warning');
      sessionStore.set(null);
      void router.go('/connexion', true);
    }
  });

  installSyncTriggers();

  // Démarrage : route initiale puis sync en arrière-plan.
  await router.resolve();
  if (hasLocalSession && navigator.onLine) {
    void sync();
  }

  // Compteur d'actions en attente affiché dans le header (déjà via stores).
  syncStore.subscribe(() => {
    /* le shell observe déjà syncStore ; hook conservé pour extensions */
  });
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
