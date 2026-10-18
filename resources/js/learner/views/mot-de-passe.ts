import { api, ApiError, NetworkError } from '../api/client';
import { html, raw } from '../core/html';
import { db } from '../db/schema';
import { sessionStore } from '../stores';
import { fullBootstrap } from '../sync/engine';
import { toast } from '../ui/app-toast';

const INPUT =
  'rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none';

/** Écran imposé : choisir un nouveau mot de passe après une connexion avec le mot de passe temporaire. */
export function mount(el: HTMLElement, _params?: Record<string, string>): void {
  const field = (name: string, label: string, autocomplete: string): string => `
    <label class="flex flex-col gap-1.5">
      <span class="text-xs font-bold text-zinc-500 uppercase tracking-wide">${label}</span>
      <input name="${name}" type="password" required autocomplete="${autocomplete}" class="${INPUT}" />
      <span data-error="${name}" class="hidden text-xs font-medium text-red-500"></span>
    </label>`;

  el.innerHTML = html`
    <div class="max-w-md mx-auto py-8 px-4">
      <form id="pwd-form" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 flex flex-col gap-4" novalidate>
        <h1 class="text-lg font-bold">Choisissez votre mot de passe</h1>
        <p class="text-sm text-zinc-500">Pour sécuriser votre compte, remplacez le mot de passe provisoire par un mot de passe que vous seul connaissez.</p>
        ${raw(field('current_password', 'Mot de passe actuel (reçu par votre formateur)', 'current-password'))}
        ${raw(field('password', 'Nouveau mot de passe (8 caractères min.)', 'new-password'))}
        ${raw(field('password_confirmation', 'Confirmer le nouveau mot de passe', 'new-password'))}
        <button type="submit" id="pwd-submit" disabled
                class="rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-60 text-white font-bold py-3 transition-colors">
          Enregistrer mon mot de passe
        </button>
      </form>
    </div>
  `;

  const form = el.querySelector<HTMLFormElement>('#pwd-form')!;
  const submit = el.querySelector<HTMLButtonElement>('#pwd-submit')!;
  const value = (name: string): string =>
    el.querySelector<HTMLInputElement>(`input[name="${name}"]`)!.value;

  const refresh = (): void => {
    const next = value('password');
    submit.disabled =
      value('current_password') === '' || next.length < 8 || next !== value('password_confirmation');
  };
  form.addEventListener('input', refresh);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    void (async () => {
      el.querySelectorAll('[data-error]').forEach((e) => e.classList.add('hidden'));
      submit.disabled = true;
      try {
        await api.updatePassword(value('current_password'), value('password'), value('password_confirmation'));
        const user = sessionStore.get();
        if (user) {
          const updated = { ...user, must_change_password: false };
          sessionStore.set(updated);
          await db.profile.put({ ...updated, _key: 'me' });
        }
        toast('Mot de passe enregistré', 'success');
        // Le mot de passe est changé : un échec du bootstrap ne doit plus rouvrir le formulaire.
        try {
          await fullBootstrap();
        } catch {
          /* la prochaine synchronisation rattrapera les données */
        }
        // main.ts réagit à cet événement : retour à l'accueil.
        window.dispatchEvent(new CustomEvent('learner:authenticated'));
      } catch (e) {
        const errors = (e instanceof ApiError
          ? (e.payload as { errors?: Record<string, string[]> } | null)?.errors
          : undefined) ?? {};
        let shown = false;
        for (const [name, messages] of Object.entries(errors)) {
          const target = el.querySelector(`[data-error="${name}"]`);
          if (target && messages[0]) {
            target.textContent = messages[0];
            target.classList.remove('hidden');
            shown = true;
          }
        }
        if (!shown) {
          toast(
            e instanceof NetworkError
              ? 'Impossible de joindre le serveur. Vérifiez votre connexion.'
              : e instanceof Error ? e.message : 'Le changement de mot de passe a échoué.',
            'error',
          );
        }
        refresh();
      }
    })();
  });
}
