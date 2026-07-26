import { escapeHtml } from '../core/html';

/**
 * Toasts empilés (remplace SweetAlert2-toast) : window natif, aria-live,
 * disparition automatique.
 */

type ToastKind = 'success' | 'error' | 'warning' | 'info';

const ICONS: Record<ToastKind, string> = {
  success: 'bi-check-circle-fill',
  error: 'bi-x-circle-fill',
  warning: 'bi-exclamation-triangle-fill',
  info: 'bi-info-circle-fill',
};

const COLORS: Record<ToastKind, string> = {
  success: 'border-emerald-500/40 text-emerald-600 dark:text-emerald-400',
  error: 'border-red-500/40 text-red-600 dark:text-red-400',
  warning: 'border-amber-500/40 text-amber-600 dark:text-amber-400',
  info: 'border-sky-500/40 text-sky-600 dark:text-sky-400',
};

function container(): HTMLElement {
  let el = document.getElementById('toast-region');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast-region';
    el.setAttribute('aria-live', 'polite');
    el.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 z-[90] flex flex-col gap-2 w-[92%] max-w-sm sm:bottom-6';
    document.body.append(el);
  }
  return el;
}

export function toast(message: string, kind: ToastKind = 'info', durationMs = 3200): void {
  const el = document.createElement('div');
  el.className =
    `flex items-center gap-2.5 rounded-xl border bg-white/95 dark:bg-zinc-900/95 backdrop-blur ` +
    `px-4 py-3 text-sm font-medium shadow-lg transition-all duration-300 opacity-0 translate-y-2 ${COLORS[kind]}`;
  el.innerHTML = `<i class="bi ${ICONS[kind]} shrink-0"></i><span class="text-zinc-800 dark:text-zinc-100">${escapeHtml(message)}</span>`;

  container().append(el);
  requestAnimationFrame(() => el.classList.remove('opacity-0', 'translate-y-2'));

  window.setTimeout(() => {
    el.classList.add('opacity-0', 'translate-y-2');
    window.setTimeout(() => el.remove(), 300);
  }, durationMs);
}
