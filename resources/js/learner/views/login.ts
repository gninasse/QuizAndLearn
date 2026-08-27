import { api, ApiError, NetworkError } from '../api/client';
import { html, raw } from '../core/html';
import { db } from '../db/schema';
import { fullBootstrap, hydrateFromDb } from '../sync/engine';
import { sessionStore } from '../stores';
import { toast } from '../ui/app-toast';
import { isStandalone } from '../ui/install';
import { logoMark } from '../ui/logo';

export function mount(el: HTMLElement): void {
  el.innerHTML = html`
    <div class="min-h-dvh flex items-center justify-center bg-gradient-to-br from-sky-950 via-zinc-950 to-zinc-900 px-4">
      <div class="w-full max-w-sm">
        <div class="text-center mb-8">
          <div class="mb-4 flex justify-center">${raw(logoMark('w-20 h-20', 'rounded-3xl'))}</div>
          <h1 class="text-2xl font-extrabold text-white tracking-tight">
            Learn<span class="text-sky-400">&</span>Quiz
          </h1>
          <p class="text-sm text-zinc-400 mt-1">Espace apprenant</p>
        </div>

        <form id="login-form" class="bg-white/5 border border-white/10 rounded-2xl p-6 flex flex-col gap-4 backdrop-blur">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-zinc-300 uppercase tracking-wide">Email ou identifiant</span>
            <input name="login" type="text" required autocomplete="username" autocapitalize="none"
                   class="rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-sky-500"
                   placeholder="prenom.nom@exemple.fr" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-zinc-300 uppercase tracking-wide">Mot de passe</span>
            <input name="password" type="password" required autocomplete="current-password"
                   class="rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-sky-500"
                   placeholder="••••••••" />
          </label>
          <p id="login-error" class="hidden text-sm text-red-400 font-medium"></p>
          <button type="submit" id="login-submit"
                  class="mt-1 rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-60 text-white font-bold py-3 transition-colors">
            Se connecter
          </button>
        </form>

        ${isStandalone()
          ? ''
          : raw('<p class="text-center text-xs text-zinc-500 mt-6">Application installable — fonctionne aussi hors-ligne après la première connexion.</p>')}
      </div>
    </div>
  `;

  const form = el.querySelector<HTMLFormElement>('#login-form')!;
  const errorEl = el.querySelector<HTMLParagraphElement>('#login-error')!;
  const submitBtn = el.querySelector<HTMLButtonElement>('#login-submit')!;

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    void (async () => {
      errorEl.classList.add('hidden');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Connexion…';

      const data = new FormData(form);

      try {
        const response = await api.login(String(data.get('login')), String(data.get('password')));
        await db.profile.put({ ...response.user, _key: 'me' });
        sessionStore.set(response.user);

        // Demande de stockage persistant (évite l'éviction du cache offline).
        void navigator.storage?.persist?.();

        await fullBootstrap();
        await hydrateFromDb();
        toast(`Bonjour ${response.user.name} !`, 'success');
        window.dispatchEvent(new CustomEvent('learner:authenticated'));
      } catch (e) {
        if (e instanceof NetworkError) {
          errorEl.textContent = 'Impossible de joindre le serveur. Vérifiez votre connexion.';
        } else if (e instanceof ApiError) {
          errorEl.textContent = e.message;
        } else {
          errorEl.textContent = 'Une erreur inattendue est survenue.';
        }
        errorEl.classList.remove('hidden');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Se connecter';
      }
    })();
  });
}
