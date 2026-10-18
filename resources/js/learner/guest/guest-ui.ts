import { html, raw } from '../core/html';
import { accessEntryHref, isInternalHref, opensNewTab, resolveAccessConfig } from './access-channels';
import { guestSettings } from './guest-session';

/**
 * Éléments d'interface du mode découverte : bandeau, cartes verrouillées et
 * appels à l'action vers la demande d'accès.
 */

export type LockedFeature = 'exams' | 'badges' | 'leaderboard' | 'progression';

const FEATURE: Record<LockedFeature, { icon: string; title: string; copy: string }> = {
  exams: { icon: 'bi-mortarboard-fill', title: 'Examens', copy: 'Examens surveillés, notés sur 20, avec classement.' },
  badges: { icon: 'bi-award-fill', title: 'Badges', copy: 'Gagnez des badges en progressant.' },
  leaderboard: {
    icon: 'bi-trophy-fill',
    title: 'Classement',
    copy: 'Comparez-vous aux autres apprenants de votre formation.',
  },
  progression: {
    icon: 'bi-graph-up-arrow',
    title: 'Progression',
    copy: 'Suivez votre progression semaine après semaine.',
  },
};

/** Lien d'accès configuré (libellé et destination) ; null si les demandes sont fermées. */
function accessLink(classes: string): string | null {
  const cfg = resolveAccessConfig(guestSettings());
  const href = accessEntryHref(cfg);
  if (href === null) return null;
  if (isInternalHref(href)) {
    return html`<a data-link href="${href}" class="${classes}">${cfg.button_label}</a>`;
  }
  if (opensNewTab(href)) {
    return html`<a href="${href}" target="_blank" rel="noopener" class="${classes}">${cfg.button_label}</a>`;
  }
  return html`<a href="${href}" class="${classes}">${cfg.button_label}</a>`;
}

/**
 * Bandeau fin sous le header, visible uniquement en mode découverte ;
 * demandes fermées : le texte reste, sans tiret ni lien.
 */
export function guestBannerHtml(): string {
  const link = accessLink('underline font-bold');
  return html`
    <div data-role="guest-banner"
         class="bg-sky-50 dark:bg-sky-500/10 border-b border-sky-200/70 dark:border-sky-500/25 text-sky-800 dark:text-sky-200 text-center text-xs font-semibold py-1.5 px-4">
      ${link === null ? 'Mode découverte' : raw(html`Mode découverte — ${raw(link)}`)}
    </div>
  `;
}

/** Remplace le bandeau déjà affiché (après un rafraîchissement des réglages). */
export function patchGuestBanner(): void {
  const current = document.querySelector('[data-role="guest-banner"]');
  if (!current) return;
  const tpl = document.createElement('template');
  tpl.innerHTML = guestBannerHtml().trim();
  const next = tpl.content.firstElementChild;
  if (next) current.replaceWith(next);
}

/** Carte « Disponible avec un compte » pour une fonctionnalité réservée. */
export function lockedCardHtml(feature: LockedFeature): string {
  const f = FEATURE[feature];
  const link = accessLink('rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-sm px-5 py-2.5');
  return html`
    <div data-locked="${feature}"
         class="rounded-2xl border border-dashed border-zinc-300 dark:border-zinc-700 panel-glass backdrop-blur-xl p-5 flex flex-col items-center text-center gap-3">
      <span class="w-11 h-11 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-lg">
        <i class="bi ${f.icon}"></i>
      </span>
      <div>
        <p class="font-bold text-sm">${f.title}</p>
        <p class="text-xs text-zinc-500 mt-1">${f.copy}</p>
      </div>
      <span class="inline-flex items-center gap-1 text-[11px] font-bold rounded-full px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-500">
        <i class="bi bi-lock-fill"></i> Disponible avec un compte
      </span>
      ${raw(link)}
    </div>
  `;
}

/** Appel à l'action de fin de quiz / de révision et du profil invité. */
export function accessCtaHtml(context: 'quiz' | 'review' | 'profile'): string {
  const link = accessLink('rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-sm px-5 py-2.5 glow-sky');
  if (link === null) return '';
  return html`
    <div data-guest-cta="${context}"
         class="rounded-2xl border border-sky-200/70 dark:border-sky-500/25 bg-sky-50/70 dark:bg-sky-500/10 p-5 flex flex-col items-center gap-3">
      <p class="font-bold text-sm text-sky-800 dark:text-sky-200">Vous avez aimé ? Obtenez votre accès complet.</p>
      ${raw(link)}
    </div>
  `;
}
