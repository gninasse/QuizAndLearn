import { html, raw } from '../core/html';
import { quizzesStore } from '../stores';
import { formatDateTime, pluralize } from './helpers';

const TYPE_LABELS: Record<string, { label: string; icon: string }> = {
  true_false: { label: 'Vrai / Faux', icon: 'bi-toggle-on' },
  mcq: { label: 'Choix multiples', icon: 'bi-ui-checks' },
  single_choice: { label: 'Choix unique', icon: 'bi-ui-radios' },
  multiple_choice: { label: 'Choix multiples', icon: 'bi-ui-checks' },
  fill_blank: { label: 'Texte à trous', icon: 'bi-input-cursor-text' },
  matching: { label: 'Associations', icon: 'bi-arrow-left-right' },
  ordering: { label: 'Remise en ordre', icon: 'bi-sort-numeric-down' },
  open_text: { label: 'Réponse libre', icon: 'bi-chat-left-text' },
};

export function mount(el: HTMLElement, params: Record<string, string>): void {
  const quiz = quizzesStore.get().find((q) => q.id === Number(params.id));

  if (!quiz) {
    el.innerHTML = '<p class="text-zinc-500 py-10 text-center">Quiz introuvable.</p>';
    return;
  }

  const totalPoints = quiz.questions.reduce((sum, q) => sum + q.points, 0);
  const byType = new Map<string, number>();
  quiz.questions.forEach((q) => byType.set(q.type, (byType.get(q.type) ?? 0) + 1));

  const completedAttempts = quiz.attempts.filter((a) => a.status === 'completed');
  const remaining = quiz.max_attempts ? quiz.max_attempts - completedAttempts.length : null;

  el.innerHTML = html`
    <div class="max-w-3xl mx-auto flex flex-col gap-5">
      <a data-link href="/evaluations" class="text-sm font-semibold text-sky-600 dark:text-sky-400">
        <i class="bi bi-arrow-left"></i> Évaluations
      </a>

      <header class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-4">
        <h2 class="text-xl font-extrabold leading-tight">${quiz.title}</h2>
        ${quiz.description ? raw(`<p class="text-sm text-zinc-600 dark:text-zinc-300">${html`${quiz.description}`}</p>`) : ''}

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-3">
            <p class="text-lg font-extrabold tabular-nums">${quiz.questions.length}</p>
            <p class="text-[11px] text-zinc-500 font-medium">${pluralize(quiz.questions.length, 'question')}</p>
          </div>
          <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-3">
            <p class="text-lg font-extrabold tabular-nums">${totalPoints}</p>
            <p class="text-[11px] text-zinc-500 font-medium">points</p>
          </div>
          <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-3">
            <p class="text-lg font-extrabold tabular-nums">${quiz.duration ?? '∞'}</p>
            <p class="text-[11px] text-zinc-500 font-medium">${quiz.duration ? 'minutes' : 'sans limite'}</p>
          </div>
          <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-3">
            <p class="text-lg font-extrabold tabular-nums">${quiz.passing_score} %</p>
            <p class="text-[11px] text-zinc-500 font-medium">pour réussir</p>
          </div>
        </div>

        ${quiz.max_attempts_reached
          ? raw(
              '<p class="rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-sm font-semibold px-4 py-3"><i class="bi bi-slash-circle"></i> Nombre maximum de tentatives atteint.</p>',
            )
          : raw(
              `<a data-link href="/quizzes/${quiz.id}/play" class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-center font-bold py-3">
                 ${quiz.status === 'in_progress' ? 'Reprendre le quiz' : 'Commencer le quiz'}
               </a>
               ${remaining !== null ? `<p class="text-xs text-center text-zinc-500">${remaining} ${pluralize(remaining, 'tentative restante', 'tentatives restantes')}</p>` : ''}`,
            )}
      </header>

      <!-- Structure du quiz -->
      <section>
        <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">Structure du quiz</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          ${raw(
            [...byType.entries()]
              .map(([type, count]) => {
                const meta = TYPE_LABELS[type] ?? { label: type, icon: 'bi-question-circle' };
                return `<div class="flex items-center gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-3 py-2.5">
                          <i class="bi ${meta.icon} text-sky-600 dark:text-sky-400"></i>
                          <span class="text-sm font-semibold flex-1">${meta.label}</span>
                          <span class="text-sm font-bold tabular-nums text-zinc-500">${count}</span>
                        </div>`;
              })
              .join(''),
          )}
        </div>
      </section>

      <!-- Historique -->
      ${completedAttempts.length
        ? raw(html`
            <section>
              <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">Mes tentatives</h3>
              <div class="flex flex-col gap-2">
                ${raw(
                  completedAttempts
                    .slice()
                    .reverse()
                    .map(
                      (att) => `
                        <div class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 py-3">
                          <span class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold ${att.passed ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-red-100 text-red-500 dark:bg-red-500/15 dark:text-red-400'}">
                            ${att.passed ? '<i class="bi bi-check-lg"></i>' : '<i class="bi bi-x-lg"></i>'}
                          </span>
                          <div class="flex-1">
                            <p class="text-sm font-bold">Tentative ${att.attempt_number} — ${att.score} %</p>
                            <p class="text-xs text-zinc-500">${att.points_earned}/${att.points_total} points · ${formatDateTime(att.completed_at)}</p>
                          </div>
                          <span class="text-xs font-bold ${att.passed ? 'text-emerald-600' : 'text-red-500'}">${att.passed ? 'Réussi' : 'Échoué'}</span>
                        </div>`,
                    )
                    .join(''),
                )}
              </div>
            </section>
          `)
        : ''}
    </div>
  `;
}
