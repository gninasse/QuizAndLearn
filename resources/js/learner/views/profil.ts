import { api } from '../api/client';
import { escapeHtml, html, raw } from '../core/html';
import { db } from '../db/schema';
import type { Preferences } from '../domain/types';
import { badgesStore, preferencesStore, sessionStore, syncStore } from '../stores';
import { clearLocalData, dispatch, sync } from '../sync/engine';
import { applyPreferences } from '../theme';
import { avatarHtml } from '../ui/avatar';
import { confirmDialog } from '../ui/app-dialog';
import { toast } from '../ui/app-toast';
import { levelProgress } from './helpers';

export function mount(el: HTMLElement): void {
  const user = sessionStore.get();
  if (!user) return;

  const preferences = preferencesStore.get();
  const badges = badgesStore.get();
  const progress = levelProgress(user.xp);
  const syncState = syncStore.get();

  el.innerHTML = html`
    <div class="max-w-xl mx-auto flex flex-col gap-5">
      <!-- Identité -->
      <section class="flex items-center gap-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
        ${raw(avatarHtml(user, 'w-16 h-16 text-xl'))}
        <div class="min-w-0 flex-1">
          <h2 class="font-extrabold text-lg truncate">${user.full_name}</h2>
          <p class="text-sm text-zinc-500 truncate">${user.email}</p>
          ${user.matricule ? raw(`<p class="text-xs text-zinc-400 mt-0.5">Matricule : ${escapeHtml(user.matricule)}</p>`) : ''}
        </div>
      </section>

      <!-- Statistiques -->
      <section class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-4">
        <div class="grid grid-cols-3 text-center gap-3">
          <div><p class="text-2xl font-extrabold tabular-nums">${user.xp.total_xp}</p><p class="text-xs text-zinc-500">XP total</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums">${user.xp.current_level}</p><p class="text-xs text-zinc-500">Niveau</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums">🔥 ${user.xp.current_streak}</p><p class="text-xs text-zinc-500">Série (max ${user.xp.longest_streak})</p></div>
        </div>
        <div>
          <div class="flex justify-between text-xs text-zinc-500 mb-1">
            <span>Progression niveau ${user.xp.current_level}</span>
            <span>${progress.intoLevel}/${progress.perLevel} XP</span>
          </div>
          <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
            <div class="h-full bg-sky-500 rounded-full" style="width:${progress.percent}%"></div>
          </div>
        </div>
      </section>

      <!-- Badges -->
      <section class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
        <h3 class="font-bold text-sm mb-3">Badges</h3>
        <div class="grid grid-cols-2 gap-2">
          ${raw(
            badges
              .map(
                (b) => `
                  <div class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 ${
                    b.unlocked
                      ? 'border-amber-300 dark:border-amber-500/40 bg-amber-50 dark:bg-amber-500/10'
                      : 'border-zinc-200 dark:border-zinc-800 opacity-50'
                  }">
                    <span class="text-xl">${escapeHtml(b.icon)}</span>
                    <div class="min-w-0">
                      <p class="text-xs font-bold truncate">${escapeHtml(b.name)}</p>
                      <p class="text-[10px] text-zinc-500 truncate">${escapeHtml(b.description)}</p>
                    </div>
                  </div>`,
              )
              .join(''),
          )}
        </div>
      </section>

      <!-- Préférences -->
      <section class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-4">
        <h3 class="font-bold text-sm">Préférences</h3>

        <div class="flex items-center justify-between">
          <span class="text-sm font-medium">Thème</span>
          <div class="flex rounded-xl bg-zinc-100 dark:bg-zinc-800 p-1">
            ${raw(
              (['light', 'dark'] as const)
                .map(
                  (theme) => `
                    <button data-pref-theme="${theme}" class="px-4 py-1.5 rounded-lg text-sm font-bold ${
                      preferences.theme === theme ? 'bg-white dark:bg-zinc-900 shadow-sm' : 'text-zinc-500'
                    }">${theme === 'light' ? '☀️ Clair' : '🌙 Sombre'}</button>`,
                )
                .join(''),
            )}
          </div>
        </div>

        <div class="flex items-center justify-between">
          <span class="text-sm font-medium">Taille du texte</span>
          <div class="flex rounded-xl bg-zinc-100 dark:bg-zinc-800 p-1">
            ${raw(
              (
                [
                  ['small', 'A-'],
                  ['medium', 'A'],
                  ['large', 'A+'],
                ] as const
              )
                .map(
                  ([size, label]) => `
                    <button data-pref-font="${size}" class="px-3.5 py-1.5 rounded-lg text-sm font-bold ${
                      preferences.font_size === size ? 'bg-white dark:bg-zinc-900 shadow-sm' : 'text-zinc-500'
                    }">${label}</button>`,
                )
                .join(''),
            )}
          </div>
        </div>

        <div class="flex items-center justify-between">
          <span class="text-sm font-medium">Sons</span>
          <button data-pref-sound role="switch" aria-checked="${preferences.sound_enabled}"
                  class="relative w-12 h-7 rounded-full transition-colors ${preferences.sound_enabled ? 'bg-sky-600' : 'bg-zinc-300 dark:bg-zinc-700'}">
            <span class="absolute top-1 ${preferences.sound_enabled ? 'left-6' : 'left-1'} w-5 h-5 rounded-full bg-white transition-all"></span>
          </button>
        </div>
      </section>

      <!-- Synchronisation -->
      <section class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-3">
        <h3 class="font-bold text-sm">Synchronisation</h3>
        <p class="text-xs text-zinc-500">
          ${syncState.pendingActions > 0
            ? `${syncState.pendingActions} action(s) en attente de synchronisation.`
            : 'Toutes vos données sont synchronisées.'}
          ${syncState.lastSyncAt ? ` Dernière sync : ${new Date(syncState.lastSyncAt).toLocaleTimeString('fr-FR')}` : ''}
        </p>
        <button id="btn-sync" class="rounded-xl border border-sky-300 dark:border-sky-500/40 text-sky-600 dark:text-sky-400 font-bold text-sm py-2.5 hover:bg-sky-50 dark:hover:bg-sky-500/10">
          <i class="bi bi-arrow-repeat"></i> Synchroniser maintenant
        </button>
      </section>

      <button id="btn-logout" class="rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold py-3 text-sm">
        <i class="bi bi-box-arrow-right"></i> Se déconnecter
      </button>
    </div>
  `;

  // ------------------------------------------------------------- Handlers

  const savePrefs = (patch: Partial<Preferences>): void => {
    const next = { ...preferencesStore.get(), ...patch };
    preferencesStore.set(next);
    applyPreferences(next);
    void db.preferences.put({ ...next, _key: 'me' });
    void dispatch('preferences_update', patch as Record<string, unknown>);
    mount(el); // re-rendu avec l'état à jour
  };

  el.querySelectorAll<HTMLButtonElement>('[data-pref-theme]').forEach((button) => {
    button.addEventListener('click', () => savePrefs({ theme: button.dataset.prefTheme as Preferences['theme'] }));
  });
  el.querySelectorAll<HTMLButtonElement>('[data-pref-font]').forEach((button) => {
    button.addEventListener('click', () => savePrefs({ font_size: button.dataset.prefFont as Preferences['font_size'] }));
  });
  el.querySelector('[data-pref-sound]')?.addEventListener('click', () => {
    savePrefs({ sound_enabled: !preferencesStore.get().sound_enabled });
  });

  el.querySelector('#btn-sync')?.addEventListener('click', () => {
    void sync().then(() => toast('Synchronisation terminée.', 'success'));
  });

  el.querySelector('#btn-logout')?.addEventListener('click', () => {
    void (async () => {
      const pendingCount = syncStore.get().pendingActions;
      const ok = await confirmDialog(
        'Se déconnecter ?',
        pendingCount > 0
          ? `${pendingCount} action(s) non synchronisée(s) seront perdues. Synchronisez d'abord si possible.`
          : 'Vos données locales seront effacées de cet appareil.',
        'Déconnexion',
      );
      if (!ok) return;

      try {
        await sync();
        await api.logout();
      } catch {
        /* hors-ligne : la session serveur expirera d'elle-même */
      }
      await clearLocalData();
      window.dispatchEvent(new CustomEvent('learner:logged-out'));
    })();
  });
}
