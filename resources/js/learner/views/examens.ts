import { html, raw } from '../core/html';
import type { ExamItem } from '../domain/types';
import { examsStore } from '../stores';
import { formatDateTime } from './helpers';

/**
 * Espace Examens — séparé de l'entraînement : épreuves officielles,
 * surveillées, à tentatives limitées et notées sur note_max.
 */

function examCard(exam: ExamItem): string {
  const config: Record<ExamItem['status'], { label: string; classes: string }> = {
    locked: { label: 'Verrouillé', classes: 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500' },
    available: { label: 'Disponible', classes: 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' },
    in_progress: { label: 'En cours', classes: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400' },
    completed: { label: 'Terminé', classes: 'bg-sky-100 dark:bg-sky-500/15 text-sky-700 dark:text-sky-400' },
    expired: { label: 'Expiré', classes: 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400' },
  };
  const status = config[exam.status];
  const playable = exam.status === 'available' || exam.status === 'in_progress';
  const lastAttempt = exam.attempts[exam.attempts.length - 1];

  return html`
    <div class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden flex flex-col">
      <div class="h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
      <div class="p-4 flex flex-col gap-3 flex-1">
        <div class="flex items-start gap-3.5">
          <span class="w-11 h-11 shrink-0 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
            <i class="bi bi-mortarboard-fill"></i>
          </span>
          <div class="min-w-0 flex-1">
            <p class="font-bold text-sm leading-snug">${exam.title}</p>
            <p class="text-xs text-zinc-500 mt-0.5">${exam.duration} min · note /${exam.note_max} · ${exam.max_attempts} tentative(s)</p>
            ${exam.available_until
              ? raw(`<p class="text-xs text-zinc-500"><i class="bi bi-calendar-x"></i> Ferme le ${formatDateTime(exam.available_until)}</p>`)
              : ''}
          </div>
          <span class="text-[11px] font-bold shrink-0 rounded-full px-2.5 py-1 ${status.classes}">${status.label}</span>
        </div>

        <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 flex-wrap">
          <span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5">
            <i class="bi bi-shield-lock"></i> Surveillé
          </span>
          ${exam.plein_ecran_force ? raw('<span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5"><i class="bi bi-fullscreen"></i> Plein écran</span>') : ''}
          ${exam.anti_capture_strict ? raw('<span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5"><i class="bi bi-camera-video-off"></i> Anti-capture</span>') : ''}
          ${exam.navigation_interdite ? raw('<span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5"><i class="bi bi-window-x"></i> Navigation bloquée</span>') : ''}
        </div>

        ${lastAttempt && lastAttempt.note_sur_vingt !== null
          ? raw(
              `<p class="text-sm font-extrabold tabular-nums ${Number(lastAttempt.pourcentage) >= exam.passing_score ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'}">
                 ${lastAttempt.note_sur_vingt}<span class="text-xs font-semibold text-zinc-400">/${exam.note_max}</span>
               </p>`,
            )
          : ''}

        ${playable
          ? raw(
              `<a data-link href="/exams/${exam.id}/play" class="mt-auto rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-center font-bold text-sm py-2.5 transition-colors">${exam.status === 'in_progress' ? "Reprendre l'examen" : "Commencer l'examen"}</a>`,
            )
          : ''}
      </div>
    </div>
  `;
}

export function mount(el: HTMLElement): void {
  const exams = examsStore.get();
  const open = exams.filter((e) => e.status === 'available' || e.status === 'in_progress');
  const others = exams.filter((e) => !open.includes(e));

  if (!exams.length) {
    el.innerHTML = html`
      <div class="text-center py-16 text-zinc-500">
        <div class="text-4xl mb-3">🎓</div>
        <p class="font-semibold">Aucun examen programmé</p>
        <p class="text-sm mt-1">Les examens de vos groupes apparaîtront ici.</p>
      </div>
    `;
    return;
  }

  el.innerHTML = html`
    <div class="flex flex-col gap-5">
      <div class="rounded-2xl border border-amber-200/70 dark:border-amber-500/25 bg-amber-50/70 dark:bg-amber-500/10 px-4 py-3 flex items-start gap-3">
        <i class="bi bi-shield-exclamation text-amber-600 dark:text-amber-400 text-lg mt-0.5"></i>
        <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200">
          Les examens sont des épreuves officielles surveillées : connexion requise, tentatives limitées,
          et toute infraction aux règles (capture d'écran, sortie de l'épreuve) peut entraîner l'annulation.
        </p>
      </div>

      ${open.length
        ? raw(html`
            <section>
              <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">À passer</h3>
              <div class="grid sm:grid-cols-2 gap-3">${raw(open.map(examCard).join(''))}</div>
            </section>
          `)
        : ''}
      ${others.length
        ? raw(html`
            <section>
              <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">Historique & à venir</h3>
              <div class="grid sm:grid-cols-2 gap-3">${raw(others.map(examCard).join(''))}</div>
            </section>
          `)
        : ''}
    </div>
  `;
}
