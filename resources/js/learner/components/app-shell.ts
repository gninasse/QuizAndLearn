import { BaseComponent, define } from '../core/base-component';
import { html, raw } from '../core/html';
import { isDue } from '../domain/sm2';
import { avatarHtml } from '../ui/avatar';
import { logoMark, logoWordmark } from '../ui/logo';
import { articlesStore, decksStore, quizzesStore, sessionStore, syncStore } from '../stores';

/**
 * Chrome de l'application : header + sidebar desktop + tab bar mobile +
 * bannière de connexion. L'outlet du router est le <main> interne.
 */

interface NavItem {
  path: string;
  label: string;
  icon: string;
  match: (path: string) => boolean;
}

const NAV: NavItem[] = [
  { path: '/', label: 'Accueil', icon: 'bi-house-door', match: (p) => p === '/' },
  { path: '/articles', label: 'Articles', icon: 'bi-journal-text', match: (p) => p.startsWith('/articles') },
  {
    path: '/entrainement',
    label: 'Entraînement',
    icon: 'bi-lightning-charge',
    match: (p) => p.startsWith('/entrainement') || p.startsWith('/quizzes') || p.startsWith('/reviser'),
  },
  {
    path: '/examens',
    label: 'Examens',
    icon: 'bi-mortarboard',
    match: (p) => p.startsWith('/examens') || p.startsWith('/exams'),
  },
  { path: '/profil', label: 'Profil', icon: 'bi-person-circle', match: (p) => p.startsWith('/profil') },
];

export class AppShell extends BaseComponent {
  private currentTitle = '';

  setTitle(title: string): void {
    this.currentTitle = title;
    const el = this.querySelector('[data-role="title"]');
    if (el) el.textContent = title;
    this.refreshNav();
  }

  get outlet(): HTMLElement {
    return this.$('#outlet');
  }

  protected override onConnected(): void {
    // Pas de re-rendu complet sur ces stores : un rebuild du shell détruirait
    // la vue routée dans l'outlet. On patche les zones concernées en place ;
    // le flip connecté/déconnecté (rerender complet) est piloté par main.ts.
    let hadUser = sessionStore.get() !== null;

    this.own(
      syncStore.subscribe((state) => this.patchSync(state)),
    );
    this.own(
      sessionStore.subscribe((user) => {
        const hasUser = user !== null;
        if (hasUser !== hadUser) {
          hadUser = hasUser;
          this.rerender();
        } else if (user) {
          this.patchUser(user);
        }
      }),
    );

    // Compteurs « à faire » sur la navigation, patchés en place.
    const patchBadges = (): void => this.patchNavBadges();
    this.own(articlesStore.subscribe(patchBadges));
    this.own(quizzesStore.subscribe(patchBadges));
    this.own(decksStore.subscribe(patchBadges));
  }

  /** Nombre d'éléments « à faire » par entrée de navigation. */
  private navCounts(): Record<string, number> {
    const unreadArticles = articlesStore.get().filter((a) => a.status !== 'completed').length;
    const pendingQuizzes = quizzesStore
      .get()
      .filter((q) => q.status !== 'completed' && !q.max_attempts_reached).length;
    const dueCards = decksStore
      .get()
      .flatMap((d) => d.cards)
      .filter((c) => isDue(c.review?.next_review)).length;

    return { '/articles': unreadArticles, '/entrainement': pendingQuizzes + dueCards };
  }

  private patchNavBadges(): void {
    const counts = this.navCounts();
    this.$$<HTMLElement>('[data-nav-badge]').forEach((badge) => {
      const count = counts[badge.dataset.navBadge ?? ''] ?? 0;
      badge.textContent = count > 9 ? '9+' : String(count);
      badge.style.display = count === 0 ? 'none' : '';
    });
  }

  private patchSync(state: ReturnType<typeof syncStore.get>): void {
    const banner = this.querySelector('[data-role="conn-banner"]');
    banner?.classList.toggle('hidden', state.online);

    const syncEl = this.querySelector('[data-role="sync"]');
    if (syncEl) {
      syncEl.className = `flex items-center gap-1.5 text-xs font-medium ${state.online ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`;
      syncEl.innerHTML = html`
        <span class="relative flex h-2 w-2">
          ${state.syncing ? raw('<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>') : ''}
          <span class="relative inline-flex rounded-full h-2 w-2 ${state.online ? 'bg-emerald-500' : 'bg-amber-500'}"></span>
        </span>
        <span class="hidden sm:inline">${state.syncing ? 'Synchronisation…' : state.online ? 'Synchronisé' : 'Hors-ligne'}</span>
        ${state.pendingActions > 0
          ? raw(`<span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-amber-500 text-white text-[10px] font-bold">${state.pendingActions}</span>`)
          : ''}
      `;
    }
  }

  private patchUser(user: NonNullable<ReturnType<typeof sessionStore.get>>): void {
    const label = this.querySelector('[data-role="user-meta"]');
    if (label) {
      label.textContent = `Niveau ${user.xp.current_level} · ${user.xp.total_xp} XP`;
    }
  }

  private refreshNav(): void {
    const path = window.location.pathname;
    this.$$<HTMLAnchorElement>('a[data-nav]').forEach((a) => {
      const item = NAV.find((n) => n.path === a.dataset.nav);
      const active = item ? item.match(path) : false;
      a.classList.toggle('text-sky-600', active);
      a.classList.toggle('dark:text-sky-400', active);
      a.classList.toggle('bg-sky-50', active);
      a.classList.toggle('dark:bg-sky-500/10', active);
    });
  }

  protected render(): string {
    const user = sessionStore.get();
    const sync = syncStore.get();

    if (!user) {
      // Non connecté : pas de chrome, l'outlet occupe tout (vue login).
      return html`<main id="outlet" class="min-h-dvh"></main>`;
    }

    const counts = this.navCounts();
    const badgeChip = (path: string, classes: string): string => {
      const count = counts[path] ?? 0;
      return `<span data-nav-badge="${path}" style="${count === 0 ? 'display:none' : ''}" class="${classes}">${count > 9 ? '9+' : count}</span>`;
    };

    const navLinks = NAV.map((item) =>
      html`
        <a data-link data-nav="${item.path}" href="${item.path}"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
          <i class="bi ${item.icon} text-lg"></i><span>${item.label}</span>
          ${raw(badgeChip(item.path, 'ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-sky-600 text-white text-[10px] font-bold'))}
        </a>
      `,
    );

    const tabLinks = NAV.map((item) =>
      html`
        <a data-link data-nav="${item.path}" href="${item.path}"
           class="relative flex flex-col items-center gap-0.5 py-2 flex-1 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
          <span class="relative">
            <i class="bi ${item.icon} text-xl leading-none"></i>
            ${raw(badgeChip(item.path, 'absolute -top-1.5 -right-3 inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full bg-sky-600 text-white text-[9px] font-bold'))}
          </span>
          <span>${item.label}</span>
        </a>
      `,
    );

    return html`
      <div class="min-h-dvh text-zinc-900 dark:text-zinc-100">
        <!-- Bannière hors-ligne -->
        <div data-role="conn-banner"
             class="${sync.online ? 'hidden' : ''} bg-amber-500 text-amber-950 text-center text-xs font-bold py-1.5 px-4 sticky top-0 z-50">
          <i class="bi bi-wifi-off mr-1"></i> Mode hors-ligne — vos actions seront synchronisées au retour du réseau
        </div>

        <div class="flex">
          <!-- Sidebar desktop -->
          <aside class="hidden lg:flex flex-col w-64 shrink-0 h-dvh sticky top-0 border-r border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-4 gap-1">
            <div class="px-2 py-3 mb-2">${raw(logoWordmark('w-9 h-9', 'text-lg'))}</div>
            ${raw(navLinks.join(''))}
            <div class="mt-auto px-2 py-3 flex items-center gap-3 border-t border-zinc-200 dark:border-zinc-800">
              ${raw(avatarHtml(user, 'w-9 h-9 text-xs'))}
              <div class="min-w-0">
                <p class="text-sm font-semibold truncate">${user.full_name}</p>
                <p data-role="user-meta" class="text-xs text-zinc-500 truncate">Niveau ${user.xp.current_level} · ${user.xp.total_xp} XP</p>
              </div>
            </div>
          </aside>

          <div class="flex-1 min-w-0 flex flex-col min-h-dvh">
            <!-- Header -->
            <header class="sticky top-0 z-40 panel-glass backdrop-blur-xl border-b border-zinc-200/70 dark:border-zinc-800/70">
              <div class="flex items-center gap-3 px-4 py-3 max-w-5xl mx-auto w-full">
                <span class="lg:hidden inline-flex" aria-hidden="true">${raw(logoMark('w-8 h-8', 'rounded-lg'))}</span>
                <h1 data-role="title" class="font-bold text-lg truncate flex-1">${this.currentTitle}</h1>
                <div data-role="sync" class="flex items-center gap-1.5 text-xs font-medium ${sync.online ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}">
                  <span class="relative flex h-2 w-2">
                    ${sync.syncing ? raw('<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>') : ''}
                    <span class="relative inline-flex rounded-full h-2 w-2 ${sync.online ? 'bg-emerald-500' : 'bg-amber-500'}"></span>
                  </span>
                  <span class="hidden sm:inline">${sync.syncing ? 'Synchronisation…' : sync.online ? 'Synchronisé' : 'Hors-ligne'}</span>
                  ${sync.pendingActions > 0
                    ? raw(
                        `<span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-amber-500 text-white text-[10px] font-bold">${sync.pendingActions}</span>`,
                      )
                    : ''}
                </div>
                <button data-role="theme-toggle" aria-label="Basculer le thème"
                        class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800">
                  <i class="bi bi-moon-stars dark:hidden"></i>
                  <i class="bi bi-sun hidden dark:inline"></i>
                </button>
                <a data-link href="/profil" class="lg:hidden shrink-0" aria-label="Mon profil">
                  ${raw(avatarHtml(user, 'w-8 h-8 text-[11px]', 'ring-2 ring-zinc-200 dark:ring-zinc-700'))}
                </a>
              </div>
            </header>

            <main id="outlet" class="flex-1 w-full max-w-5xl mx-auto px-4 py-5 pb-24 lg:pb-8"></main>
          </div>
        </div>

        <!-- Tab bar mobile -->
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 panel-glass backdrop-blur-xl border-t border-zinc-200/70 dark:border-zinc-800/70 flex pb-[env(safe-area-inset-bottom)]">
          ${raw(tabLinks.join(''))}
        </nav>
      </div>
    `;
  }

  protected override bind(): void {
    this.querySelector('[data-role="theme-toggle"]')?.addEventListener('click', () => {
      this.dispatchEvent(new CustomEvent('theme-toggle', { bubbles: true }));
    });
    this.refreshNav();
  }
}

define('app-shell', AppShell);
