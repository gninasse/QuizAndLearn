import { html, raw, escapeHtml, RawHtml } from '../core/html';

/**
 * Dialogues sur <dialog> natif (remplace SweetAlert2) :
 * confirm(), alert() et celebrate() (badges/niveaux).
 */

interface DialogOptions {
  title: string;
  /** Corps : texte simple (échappé) ou raw() pour du HTML de confiance. */
  body?: string | RawHtml;
  confirmLabel?: string;
  cancelLabel?: string | null;
  tone?: 'default' | 'danger' | 'celebrate';
  /** Emoji/icône affiché en tête (celebrate). */
  emblem?: string;
}

function buildDialog(options: DialogOptions): HTMLDialogElement {
  const dialog = document.createElement('dialog');
  dialog.className =
    'rounded-2xl p-0 w-[92%] max-w-md backdrop:bg-black/50 backdrop:backdrop-blur-sm ' +
    'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-2xl border border-zinc-200 dark:border-zinc-700 ' +
    'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 m-0';

  const confirmClass =
    options.tone === 'danger'
      ? 'bg-red-600 hover:bg-red-500 text-white'
      : 'bg-sky-600 hover:bg-sky-500 text-white';

  const bodyHtml =
    options.body instanceof RawHtml
      ? options.body.value
      : options.body
        ? `<p class="text-sm text-zinc-600 dark:text-zinc-300">${escapeHtml(options.body)}</p>`
        : '';

  dialog.innerHTML = html`
    <div class="p-6 flex flex-col gap-4 ${options.tone === 'celebrate' ? 'items-center text-center' : ''}">
      ${options.emblem ? raw(`<div class="text-5xl leading-none">${escapeHtml(options.emblem)}</div>`) : ''}
      <h2 class="text-lg font-bold">${options.title}</h2>
      ${raw(bodyHtml)}
      <div class="flex gap-3 justify-end mt-2 ${options.tone === 'celebrate' ? 'justify-center' : ''}">
        ${options.cancelLabel !== null
          ? raw(
              `<button data-action="cancel" class="px-4 py-2 rounded-xl text-sm font-semibold bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700">${escapeHtml(options.cancelLabel ?? 'Annuler')}</button>`,
            )
          : ''}
        <button data-action="confirm" class="px-4 py-2 rounded-xl text-sm font-semibold ${confirmClass}">
          ${options.confirmLabel ?? 'OK'}
        </button>
      </div>
    </div>
  `;

  document.body.append(dialog);
  return dialog;
}

function run(options: DialogOptions): Promise<boolean> {
  return new Promise((resolve) => {
    const dialog = buildDialog(options);

    const close = (result: boolean): void => {
      dialog.close();
      dialog.remove();
      resolve(result);
    };

    dialog.querySelector('[data-action="confirm"]')?.addEventListener('click', () => close(true));
    dialog.querySelector('[data-action="cancel"]')?.addEventListener('click', () => close(false));
    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      close(false);
    });

    dialog.showModal();
  });
}

export function confirmDialog(title: string, body?: string, confirmLabel = 'Confirmer'): Promise<boolean> {
  return run({ title, body, confirmLabel });
}

export function dangerDialog(title: string, body?: string, confirmLabel = 'Continuer'): Promise<boolean> {
  return run({ title, body, confirmLabel, tone: 'danger' });
}

export function alertDialog(title: string, body?: string | RawHtml): Promise<boolean> {
  return run({ title, body, cancelLabel: null });
}

export function celebrateDialog(title: string, body: string, emblem = '🎉'): Promise<boolean> {
  return run({ title, body, emblem, tone: 'celebrate', cancelLabel: null, confirmLabel: 'Super !' });
}
