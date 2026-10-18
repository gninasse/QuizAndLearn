/**
 * Mode consultation « Voir en tant que » : un super admin parcourt l'espace
 * d'un apprenant en lecture seule, dans un onglet dédié.
 *
 * Le jeton arrive dans le fragment (`/#consultation=<jeton>`, jamais envoyé
 * au serveur), puis vit en `sessionStorage`, propre à l'onglet. Chaque appel
 * à l'API apprenant le porte en en-tête `X-Impersonation-Token`.
 *
 * Ce module est importé par `db/schema.ts` pour choisir le nom de la base AVANT
 * son ouverture : il ne doit importer statiquement ni la base, ni le client
 * API, ni le moteur de sync (cycles). Ces dépendances sont chargées à la
 * demande dans `endConsultation()`.
 */

import { escapeHtml } from '../core/html';
import { toast } from '../ui/app-toast';

const STORAGE_KEY = 'learner:consultation';
const FRAGMENT_PREFIX = '#consultation=';

export const LEARNER_DB_NAME = 'LearnQuizDB-v2026';
export const CONSULTATION_DB_NAME = 'LearnQuizDB-v2026-consultation';

export const CONSULTATION_BLOCKED_MESSAGE = 'Action indisponible en consultation.';

/** La consultation a pris fin dans cet onglet : il reste en lecture seule, inerte. */
let ended = false;
let ending: Promise<void> | null = null;

function storageGet(): string | null {
  try {
    return sessionStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

function storageSet(token: string): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, token);
  } catch {
    /* stockage indisponible : le jeton reste dans l'URL de ce chargement seulement */
  }
}

function storageClear(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    /* stockage indisponible */
  }
}

/**
 * Jeton de consultation de l'onglet : lu dans le fragment (puis retiré de
 * l'URL et mémorisé en `sessionStorage`), sinon dans le `sessionStorage`.
 */
export function readConsultationToken(): string | null {
  const hash = window.location.hash;
  if (hash.startsWith(FRAGMENT_PREFIX)) {
    let token = '';
    try {
      token = decodeURIComponent(hash.slice(FRAGMENT_PREFIX.length)).trim();
    } catch {
      token = '';
    }
    // Le jeton ne doit pas rester visible (historique, partage d'écran).
    history.replaceState(history.state, '', window.location.pathname + window.location.search);
    if (token) {
      ended = false;
      storageSet(token);
      return token;
    }
  }

  return storageGet();
}

/** Onglet en consultation (y compris après sa fin : il reste en lecture seule). */
export function isConsultation(): boolean {
  return ended || readConsultationToken() !== null;
}

/** La consultation est terminée : plus aucun appel serveur. */
export function isConsultationEnded(): boolean {
  return ended;
}

/** Base IndexedDB à ouvrir : séparée en consultation (jamais celle d'un apprenant). */
export function consultationDbName(): string {
  return readConsultationToken() !== null ? CONSULTATION_DB_NAME : LEARNER_DB_NAME;
}

/** Retour visuel d'une action d'écriture neutralisée. */
export function blockedInConsultation(): void {
  toast(CONSULTATION_BLOCKED_MESSAGE, 'warning');
}

/** Code d'erreur métier d'une réponse API (sans dépendre de la classe ApiError). */
function errorCode(error: unknown): string | null {
  const e = error as { status?: unknown; payload?: { code?: unknown } | null } | null;
  if (!e || e.status !== 403) return null;
  return typeof e.payload?.code === 'string' ? e.payload.code : null;
}

/** Refus serveur : la consultation n'est plus valide (jeton échu, admin déconnecté…). */
export function isImpersonationEnded(error: unknown): boolean {
  if (errorCode(error) === 'impersonation_ended') return true;
  // Session du super admin expirée : même effet en consultation.
  const status = (error as { status?: unknown } | null)?.status;
  return isConsultation() && (status === 401 || status === 419);
}

/** Refus serveur d'une écriture en consultation. */
export function isReadOnlyRefusal(error: unknown): boolean {
  return errorCode(error) === 'read_only';
}

function renderEndedScreen(): void {
  const screen = document.createElement('main');
  screen.dataset.role = 'consultation-ended';
  screen.className = 'min-h-dvh flex items-center justify-center p-6 text-zinc-900 dark:text-zinc-100';
  screen.innerHTML = `
    <div class="max-w-sm w-full text-center rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-8 flex flex-col items-center gap-3">
      <span class="w-12 h-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-xl">
        <i class="bi bi-eye-slash"></i>
      </span>
      <h1 class="font-extrabold text-lg">${escapeHtml('Consultation terminée')}</h1>
      <p class="text-sm text-zinc-500">${escapeHtml('Vous pouvez fermer cet onglet.')}</p>
    </div>`;

  const root = document.getElementById('app');
  if (root) root.replaceChildren(screen);
  else document.body.replaceChildren(screen);
}

/**
 * Termine la consultation : fin côté serveur si l'admin quitte, purge de la
 * base de consultation et du `sessionStorage`, puis écran de fin.
 */
export function endConsultation(reason: 'quit' | 'ended'): Promise<void> {
  if (ending) return ending;

  ending = (async () => {
    if (reason === 'quit' && !ended) {
      try {
        const { api } = await import('../api/client');
        await api.endConsultation();
      } catch {
        /* déjà terminée ou hors-ligne : l'expiration serveur fera foi */
      }
    }

    ended = true;
    storageClear();
    renderEndedScreen();

    try {
      const { db } = await import('../db/schema');
      if (db.name === CONSULTATION_DB_NAME) {
        await Promise.all(db.tables.map((table) => table.clear()));
      }
    } catch {
      /* base indisponible : rien à purger */
    }
  })();

  return ending;
}
