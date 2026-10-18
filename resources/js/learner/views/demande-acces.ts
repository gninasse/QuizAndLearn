import { api, ApiError, NetworkError } from '../api/client';
import { html, raw } from '../core/html';
import type { AccessRequestResponse, ShowcaseSettings } from '../domain/types';
import { guestSettings } from '../guest/guest-session';
import { toast } from '../ui/app-toast';

const DRAFT_KEY = 'learner:access-draft';

const INPUT =
  'w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none';
const CARD =
  'bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 flex flex-col gap-4';

const OPERATORS: Record<string, string> = {
  orange_money: 'Orange Money',
  mtn_momo: 'MTN MoMo',
  moov_money: 'Moov Money',
  wave: 'Wave',
  other: 'Autre',
};

type Draft = Record<string, string | boolean>;

function readDraft(): Draft {
  try {
    return JSON.parse(sessionStorage.getItem(DRAFT_KEY) ?? '{}') as Draft;
  } catch {
    return {};
  }
}

function writeDraft(draft: Draft | null): void {
  try {
    if (draft === null) {
      sessionStorage.removeItem(DRAFT_KEY);
    } else {
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    }
  } catch {
    /* stockage indisponible : le brouillon est facultatif */
  }
}

async function loadSettings(): Promise<ShowcaseSettings | null> {
  const cached = guestSettings();
  if (cached) {
    return cached;
  }
  try {
    return await api.showcaseSettings();
  } catch {
    return null;
  }
}

function formatFee(registration: ShowcaseSettings['registration']): string {
  const amount = registration.fee === null ? '' : new Intl.NumberFormat('fr-FR').format(registration.fee);
  return `${amount} ${registration.currency}`.trim();
}

function successView(result: AccessRequestResponse): string {
  const reg = result.registration;
  const payment =
    reg && reg.accounts.length > 0
      ? html`
        <section class="rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-900 p-4 flex flex-col gap-2">
          <h2 class="font-bold">Frais d'inscription</h2>
          ${reg.fee !== null ? raw(html`<p data-fee class="text-2xl font-extrabold">${formatFee(reg)}</p>`) : ''}
          <ul class="flex flex-col gap-1 text-sm">
            ${raw(
              reg.accounts
                .map((a) => html`<li><strong>${OPERATORS[a.operator] ?? a.operator}</strong> → ${a.number} → ${a.holder}</li>`)
                .join(''),
            )}
          </ul>
          ${reg.reference ? raw(html`<p class="text-sm">Référence : <strong>${reg.reference}</strong></p>`) : ''}
          ${reg.instructions ? raw(html`<p class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line">${reg.instructions}</p>`) : ''}
          <p class="text-sm font-bold text-amber-600 dark:text-amber-400">Attendez la confirmation de notre équipe avant tout transfert.</p>
        </section>`
      : '';
  return html`
    <div class="${CARD}">
      <h1 class="text-lg font-bold">Demande envoyée.</h1>
      <p class="text-sm text-zinc-500">Nous vous contacterons au ${result.phone}.</p>
      ${raw(payment)}
      <a data-link href="/" class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 text-center transition-colors">Retour à la découverte</a>
    </div>`;
}

/** Formulaire public de demande d'accès (mode découverte). */
export async function mount(el: HTMLElement, _params?: Record<string, string>): Promise<void> {
  const settings = await loadSettings();
  const startedAt = Date.now();
  const prefix = settings?.default_country_code ? `+${settings.default_country_code}` : '';
  const groups = settings?.groups ?? [];
  const draft = readDraft();
  const dv = (name: string): string => (typeof draft[name] === 'string' ? (draft[name] as string) : '');
  const whatsappSame = draft.whatsapp_same !== false;

  const field = (name: string, label: string, opts: { type?: string; required?: boolean; auto?: string } = {}): string => `
    <label class="flex flex-col gap-1.5">
      <span class="text-xs font-bold text-zinc-500 uppercase tracking-wide">${html`${label}`}${opts.required ? ' *' : ''}</span>
      <input name="${name}" type="${opts.type ?? 'text'}" autocomplete="${opts.auto ?? 'off'}" value="${html`${dv(name)}`}" class="${INPUT}" />
      <span data-error="${name}" class="hidden text-xs font-medium text-red-500"></span>
    </label>`;

  el.innerHTML = html`
    <div class="max-w-md mx-auto py-8 px-4">
      <form id="access-form" class="${CARD}" novalidate>
        <h1 class="text-lg font-bold">Demander un accès</h1>
        <p class="text-sm text-zinc-500">Laissez-nous vos coordonnées : notre équipe vous recontacte pour ouvrir votre compte.</p>
        ${raw(field('first_name', 'Prénom', { required: true, auto: 'given-name' }))}
        ${raw(field('last_name', 'Nom', { required: true, auto: 'family-name' }))}
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-zinc-500 uppercase tracking-wide">Téléphone *</span>
          <div class="flex items-center gap-2">
            ${prefix ? raw(html`<span data-prefix class="text-sm font-bold text-zinc-500">${prefix}</span>`) : ''}
            <input name="phone" type="tel" autocomplete="tel-national" value="${dv('phone')}" class="${INPUT}" />
          </div>
          <span data-error="phone" class="hidden text-xs font-medium text-red-500"></span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input name="whatsapp_same" type="checkbox" ${whatsappSame ? 'checked' : ''} />
          Même numéro sur WhatsApp
        </label>
        <div id="whatsapp-box" class="${whatsappSame ? 'hidden' : ''}">
          ${raw(field('whatsapp', 'Numéro WhatsApp', { type: 'tel', auto: 'tel-national' }))}
        </div>
        ${raw(field('email', 'E-mail', { type: 'email', auto: 'email' }))}
        ${raw(field('organization', 'Organisation', { auto: 'organization' }))}
        ${
          groups.length > 0
            ? raw(html`<label class="flex flex-col gap-1.5">
                <span class="text-xs font-bold text-zinc-500 uppercase tracking-wide">Formation souhaitée</span>
                <select name="group_id" class="${INPUT}">
                  <option value="">Je ne sais pas encore</option>
                  ${raw(
                    groups
                      .map((g) => html`<option value="${g.id}" ${dv('group_id') === String(g.id) ? 'selected' : ''}>${g.name}</option>`)
                      .join(''),
                  )}
                </select>
                <span data-error="group_id" class="hidden text-xs font-medium text-red-500"></span>
              </label>`)
            : ''
        }
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-zinc-500 uppercase tracking-wide">Message</span>
          <textarea name="message" rows="3" maxlength="2000" class="${INPUT}">${dv('message')}</textarea>
          <span data-error="message" class="hidden text-xs font-medium text-red-500"></span>
        </label>
        <div class="flex flex-col gap-1">
          <label class="flex items-start gap-2 text-sm">
            <input name="consent" type="checkbox" class="mt-1" ${draft.consent === true ? 'checked' : ''} />
            <span>J'accepte que mes données soient utilisées pour traiter ma demande.
              <a href="${settings?.privacy_url ?? '/confidentialite'}" target="_blank" rel="noopener" class="underline">Politique de confidentialité</a>
            </span>
          </label>
          <span data-error="consent" class="hidden text-xs font-medium text-red-500"></span>
        </div>
        <div class="absolute -left-[9999px]" aria-hidden="true">
          <input name="website" type="text" tabindex="-1" autocomplete="off" />
        </div>
        <p id="offline-note" class="hidden text-sm font-medium text-amber-600 dark:text-amber-400">Vous êtes hors-ligne — votre demande sera envoyable dès le retour du réseau.</p>
        <p data-error="form" class="hidden text-sm font-medium text-red-500"></p>
        <button type="submit" disabled
                class="rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-60 text-white font-bold py-3 transition-colors">
          Envoyer
        </button>
      </form>
    </div>
  `;

  const form = el.querySelector<HTMLFormElement>('#access-form')!;
  const submit = el.querySelector<HTMLButtonElement>('button[type="submit"]')!;
  const offlineNote = el.querySelector<HTMLElement>('#offline-note')!;
  const whatsappBox = el.querySelector<HTMLElement>('#whatsapp-box')!;
  const control = (name: string): HTMLInputElement =>
    form.querySelector<HTMLInputElement>(`[name="${name}"]`)!;
  const text = (name: string): string => (form.querySelector(`[name="${name}"]`) as HTMLInputElement | null)?.value.trim() ?? '';
  const checked = (name: string): boolean => control(name).checked;
  let sending = false;

  const collectDraft = (): Draft => ({
    first_name: text('first_name'),
    last_name: text('last_name'),
    phone: text('phone'),
    whatsapp_same: checked('whatsapp_same'),
    whatsapp: text('whatsapp'),
    email: text('email'),
    organization: text('organization'),
    group_id: text('group_id'),
    message: text('message'),
    consent: checked('consent'),
  });

  const refresh = (): void => {
    const online = navigator.onLine;
    offlineNote.classList.toggle('hidden', online);
    const complete =
      text('first_name') !== '' && text('last_name') !== '' && text('phone') !== '' && checked('consent');
    submit.disabled = !complete || !online || sending;
  };

  const clearErrors = (): void => {
    el.querySelectorAll('[data-error]').forEach((e) => {
      e.textContent = '';
      e.classList.add('hidden');
    });
  };

  const showError = (name: string, message: string): boolean => {
    const target = el.querySelector(`[data-error="${name}"]`);
    if (!target) {
      return false;
    }
    target.textContent = message;
    target.classList.remove('hidden');
    return true;
  };

  const onInput = (): void => {
    whatsappBox.classList.toggle('hidden', checked('whatsapp_same'));
    writeDraft(collectDraft());
    refresh();
  };
  form.addEventListener('input', onInput);
  form.addEventListener('change', onInput);
  const onOnline = (): void => {
    if (!el.isConnected) {
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
      return;
    }
    refresh();
  };
  const onOffline = (): void => {
    if (!el.isConnected) {
      onOnline();
      return;
    }
    writeDraft(collectDraft());
    refresh();
  };
  window.addEventListener('online', onOnline);
  window.addEventListener('offline', onOffline);
  refresh();

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (submit.disabled) {
      return;
    }
    void (async () => {
      clearErrors();
      sending = true;
      refresh();
      const same = checked('whatsapp_same');
      try {
        const result = await api.submitAccessRequest({
          first_name: text('first_name'),
          last_name: text('last_name'),
          phone: text('phone'),
          whatsapp_same: same,
          whatsapp: same ? null : text('whatsapp') || null,
          email: text('email') || null,
          organization: text('organization') || null,
          group_id: text('group_id') ? Number(text('group_id')) : null,
          message: text('message') || null,
          consent: checked('consent'),
          website: text('website'),
          started_at: startedAt,
        });
        writeDraft(null);
        el.innerHTML = html`<div class="max-w-md mx-auto py-8 px-4">${raw(successView(result))}</div>`;
      } catch (e) {
        sending = false;
        const payload = e instanceof ApiError ? (e.payload as { errors?: Record<string, string[]> } | null) : null;
        let shown = false;
        for (const [name, messages] of Object.entries(payload?.errors ?? {})) {
          if (messages[0] && showError(name, messages[0])) {
            shown = true;
          }
        }
        if (!shown) {
          const message =
            e instanceof NetworkError
              ? 'Impossible de joindre le serveur. Vérifiez votre connexion.'
              : e instanceof ApiError && e.status === 429
                ? 'Trop de tentatives. Réessayez dans quelques minutes.'
                : e instanceof Error ? e.message : "L'envoi a échoué.";
          showError('form', message);
          toast(message, 'error');
        }
        refresh();
      }
    })();
  });
}
