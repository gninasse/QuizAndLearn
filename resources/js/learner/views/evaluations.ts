import { html, raw } from '../core/html';
import type { ExamItem, QuizItem } from '../domain/types';
import { examsStore, quizzesStore } from '../stores';
import { formatDateTime } from './helpers';

/** Liste combinée Quiz / Examens avec segment switch (?tab=exams). */

function quizCard(quiz: QuizItem): string {
  const badge =
    quiz.status === 'completed'
      ? '<span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> Terminé</span>'
      : quiz.status === 'in_progress'
        ? '<span class="text-[11px] font-bold text-amber-600 dark:text-amber-400"><i class="bi bi-play-circle-fill"></i> En cours</span>'
        : '<span class="text-[11px] font-bold text-sky-600 dark:text-sky-400">Nouveau</span>';

  const best = quiz.attempts.filter((a) => a.status === 'completed').sort((a, b) => (b.score ?? 0) - (a.score ?? 0))[0];

  return html`
    <a data-link href="/quizzes/${quiz.id}"
       class="flex items-center gap-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 hover:shadow-md transition-shadow">
      <span class="w-11 h-11 shrink-0 rounded-xl bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg">
        <i class="bi bi-patch-question"></i>
      </span>
      <div class="min-w-0 flex-1">
        <p class="font-bold text-sm leading-snug">${quiz.title}</p>
        <p class="text-xs text-zinc-500 mt-0.5">
          ${quiz.questions.length} questions${quiz.duration ? ` · ${quiz.duration} min` : ''} · réussite ≥ ${quiz.passing_score} %
        </p>
        ${best ? raw(`<p class="text-xs mt-0.5 font-semibold ${best.passed ? 'text-emerald-600' : 'text-zinc-500'}">Meilleur score : ${best.score} %</p>`) : ''}
      </div>
      <div class="text-right shrink-0">${raw(badge)}</div>
    </a>
  `;
}

function examCard(exam: ExamItem): string {
  const config: Record<ExamItem['status'], { label: string; classes: string }> = {
    locked: { label: 'Verrouillé', classes: 'text-zinc-500' },
    available: { label: 'Disponible', classes: 'text-emerald-600 dark:text-emerald-400' },
    in_progress: { label: 'En cours', classes: 'text-amber-600 dark:text-amber-400' },
    completed: { label: 'Terminé', classes: 'text-sky-600 dark:text-sky-400' },
    expired: { label: 'Expiré', classes: 'text-zinc-400' },
  };
  const status = config[exam.status];
  const playable = exam.status === 'available' || exam.status === 'in_progress';
  const lastAttempt = exam.attempts[exam.attempts.length - 1];

  return html`
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 flex flex-col gap-3">
      <div class="flex items-start gap-3">
        <span class="w-11 h-11 shrink-0 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
          <i class="bi bi-mortarboard"></i>
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-bold text-sm leading-snug">${exam.title}</p>
          <p class="text-xs text-zinc-500 mt-0.5">${exam.duration} min · note /${exam.note_max} · ${exam.max_attempts} tentative(s) max</p>
          ${exam.available_until
            ? raw(`<p class="text-xs text-zinc-500">Ferme le ${formatDateTime(exam.available_until)}</p>`)
            : ''}
        </div>
        <span class="text-[11px] font-bold shrink-0 ${status.classes}">${status.label}</span>
      </div>

      <div class="flex items-center gap-2 text-[11px] text-zinc-500 flex-wrap">
        <span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5">
          <i class="bi bi-shield-lock"></i> Surveillé
        </span>
        ${exam.plein_ecran_force ? raw('<span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5"><i class="bi bi-fullscreen"></i> Plein écran</span>') : ''}
        ${exam.anti_capture_strict ? raw('<span class="inline-flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2 py-0.5"><i class="bi bi-camera-video-off"></i> Anti-capture</span>') : ''}
      </div>

      ${lastAttempt && lastAttempt.note_sur_vingt !== null
        ? raw(
            `<p class="text-sm font-bold ${Number(lastAttempt.pourcentage) >= exam.passing_score ? 'text-emerald-600' : 'text-red-500'}">Dernière note : ${lastAttempt.note_sur_vingt}/${exam.note_max}</p>`,
          )
        : ''}

      ${playable
        ? raw(
            `<a data-link href="/exams/${exam.id}/play" class="rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-center font-bold text-sm py-2.5">${exam.status === 'in_progress' ? "Reprendre l'examen" : "Commencer l'examen"}</a>`,
          )
        : ''}
    </div>
  `;
}

export function mount(el: HTMLElement): void {
  const tab = new URLSearchParams(window.location.search).get('tab') === 'exams' ? 'exams' : 'quizzes';
  const quizzes = quizzesStore.get();
  const exams = examsStore.get();

  const renderContent = (active: string): string => {
    if (active === 'exams') {
      return exams.length
        ? `<div class="grid sm:grid-cols-2 gap-3">${exams.map(examCard).join('')}</div>`
        : '<div class="text-center py-16 text-zinc-500"><div class="text-4xl mb-3">🎓</div><p class="font-semibold">Aucun examen programmé</p></div>';
    }
    return quizzes.length
      ? `<div class="flex flex-col gap-3">${quizzes.map(quizCard).join('')}</div>`
      : '<div class="text-center py-16 text-zinc-500"><div class="text-4xl mb-3">❓</div><p class="font-semibold">Aucun quiz assigné</p></div>';
  };

  el.innerHTML = html`
    <div class="flex flex-col gap-4">
      <div class="flex rounded-xl bg-zinc-100 dark:bg-zinc-800 p-1 max-w-xs" role="tablist">
        <button data-tab="quizzes" role="tab"
                class="flex-1 rounded-lg py-2 text-sm font-bold transition-colors">Quiz (${quizzes.length})</button>
        <button data-tab="exams" role="tab"
                class="flex-1 rounded-lg py-2 text-sm font-bold transition-colors">Examens (${exams.length})</button>
      </div>
      <div id="eval-content">${raw(renderContent(tab))}</div>
    </div>
  `;

  const styleTabs = (active: string): void => {
    el.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((button) => {
      const isActive = button.dataset.tab === active;
      button.classList.toggle('bg-white', isActive);
      button.classList.toggle('dark:bg-zinc-900', isActive);
      button.classList.toggle('shadow-sm', isActive);
      button.classList.toggle('text-zinc-500', !isActive);
      button.setAttribute('aria-selected', String(isActive));
    });
  };
  styleTabs(tab);

  el.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((button) => {
    button.addEventListener('click', () => {
      const active = button.dataset.tab ?? 'quizzes';
      history.replaceState(null, '', active === 'exams' ? '/evaluations?tab=exams' : '/evaluations');
      styleTabs(active);
      el.querySelector('#eval-content')!.innerHTML = renderContent(active);
    });
  });
}
