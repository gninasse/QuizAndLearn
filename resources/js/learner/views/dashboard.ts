import { escapeHtml, html, raw } from '../core/html';
import { isDue } from '../domain/sm2';
import {
  articlesStore,
  badgesStore,
  decksStore,
  examsStore,
  quizzesStore,
  sessionStore,
} from '../stores';
import { levelProgress, pluralize } from './helpers';

export function mount(el: HTMLElement): void {
  const user = sessionStore.get();
  if (!user) return;

  const quizzes = quizzesStore.get();
  const articles = articlesStore.get();
  const decks = decksStore.get();
  const exams = examsStore.get();
  const badges = badgesStore.get().filter((b) => b.unlocked);

  const pendingQuizzes = quizzes.filter((q) => q.status !== 'completed' && !q.max_attempts_reached);
  const unreadArticles = articles.filter((a) => a.status !== 'completed');
  const dueCards = decks.flatMap((d) => d.cards).filter((c) => isDue(c.review?.next_review));
  const openExams = exams.filter((e) => e.status === 'available' || e.status === 'in_progress');
  const progress = levelProgress(user.xp);

  const statCard = (
    href: string,
    icon: string,
    tint: string,
    value: number,
    label: string,
  ): string => html`
    <a data-link href="${href}"
       class="flex flex-col gap-2 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 hover:shadow-md transition-shadow">
      <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg ${tint}">
        <i class="bi ${icon}"></i>
      </span>
      <span class="text-2xl font-extrabold tabular-nums">${value}</span>
      <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">${label}</span>
    </a>
  `;

  el.innerHTML = html`
    <div class="flex flex-col gap-5">
      <!-- Carte XP / niveau / série -->
      <section class="rounded-2xl bg-gradient-to-br from-sky-600 to-indigo-700 text-white p-5 shadow-lg">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sky-200 text-sm">Bonjour,</p>
            <h2 class="text-xl font-extrabold">${user.name} 👋</h2>
          </div>
          <div class="text-right">
            <p class="text-3xl font-extrabold tabular-nums">${user.xp.total_xp}<span class="text-base font-semibold text-sky-200"> XP</span></p>
            <p class="text-xs text-sky-200">Niveau ${user.xp.current_level}</p>
          </div>
        </div>
        <div class="mt-4">
          <div class="flex justify-between text-xs text-sky-100 mb-1">
            <span>Niveau ${user.xp.current_level}</span>
            <span>${progress.intoLevel} / ${progress.perLevel} XP</span>
          </div>
          <div class="h-2.5 rounded-full bg-white/20 overflow-hidden">
            <div class="h-full rounded-full bg-white/90 transition-all" style="width:${progress.percent}%"></div>
          </div>
        </div>
        <div class="mt-4 flex items-center gap-4 text-sm">
          <span class="inline-flex items-center gap-1.5 bg-white/15 rounded-full px-3 py-1">
            🔥 <b class="tabular-nums">${user.xp.current_streak}</b> ${pluralize(user.xp.current_streak, 'jour')}
          </span>
          <span class="inline-flex items-center gap-1.5 bg-white/15 rounded-full px-3 py-1">
            🏅 <b class="tabular-nums">${badges.length}</b> ${pluralize(badges.length, 'badge')}
          </span>
        </div>
      </section>

      <!-- Raccourcis -->
      <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        ${raw(
          [
            statCard('/evaluations', 'bi-patch-question', 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400', pendingQuizzes.length, `Quiz à faire`),
            statCard('/articles', 'bi-journal-text', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', unreadArticles.length, `Articles à lire`),
            statCard('/reviser', 'bi-stack', 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400', dueCards.length, `Cartes à réviser`),
            statCard('/evaluations?tab=exams', 'bi-mortarboard', 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', openExams.length, `Examens ouverts`),
          ].join(''),
        )}
      </section>

      <!-- Reprendre -->
      ${pendingQuizzes.length
        ? raw(html`
            <section>
              <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">Continuer</h3>
              <div class="flex flex-col gap-2">
                ${raw(
                  pendingQuizzes
                    .slice(0, 3)
                    .map((quiz) =>
                      html`
                        <a data-link href="/quizzes/${quiz.id}"
                           class="flex items-center gap-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 hover:shadow-md transition-shadow">
                          <span class="w-10 h-10 shrink-0 rounded-xl bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                            <i class="bi ${quiz.status === 'in_progress' ? 'bi-play-circle' : 'bi-patch-question'}"></i>
                          </span>
                          <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate">${quiz.title}</p>
                            <p class="text-xs text-zinc-500">${quiz.questions.length} questions${quiz.duration ? ` · ${quiz.duration} min` : ''}</p>
                          </div>
                          <span class="text-xs font-bold ${quiz.status === 'in_progress' ? 'text-amber-600' : 'text-sky-600'}">
                            ${quiz.status === 'in_progress' ? 'En cours' : 'Nouveau'}
                          </span>
                        </a>
                      `,
                    )
                    .join(''),
                )}
              </div>
            </section>
          `)
        : ''}

      <!-- Badges -->
      <section>
        <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">Mes badges</h3>
        ${badges.length
          ? raw(
              `<div class="flex flex-wrap gap-2">${badges
                .map(
                  (b) =>
                    `<span class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 px-3 py-1.5 text-sm font-semibold" title="${escapeHtml(b.description)}">${escapeHtml(b.icon)} ${escapeHtml(b.name)}</span>`,
                )
                .join('')}</div>`,
            )
          : raw(
              '<p class="text-sm text-zinc-500">Aucun badge pour l\'instant — complétez un quiz ou lisez un article pour débloquer le premier !</p>',
            )}
      </section>
    </div>
  `;
}
