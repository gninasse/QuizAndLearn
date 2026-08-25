import { api } from '../api/client';
import { escapeHtml, html, raw } from '../core/html';
import { getMeta, setMeta } from '../db/schema';
import type { LeaderboardGroup } from '../domain/types';
import { decksStore, quizzesStore, sessionStore, syncStore } from '../stores';
import { avatarHtml } from '../ui/avatar';
import { levelProgress } from './helpers';

/**
 * Ma progression : statistiques personnelles (calculées localement, donc
 * disponibles hors-ligne) + classement XP par groupe (en ligne, mis en cache).
 */

const LEADERBOARD_CACHE_KEY = 'leaderboard_cache';

export function mount(el: HTMLElement): void {
  const user = sessionStore.get();
  if (!user) return;

  const quizzes = quizzesStore.get();
  const decks = decksStore.get();
  const progress = levelProgress(user.xp);

  const attempts = quizzes
    .flatMap((quiz) => quiz.attempts.map((a) => ({ ...a, quizTitle: quiz.title })))
    .filter((a) => a.status === 'completed' && a.completed_at);

  const passedCount = attempts.filter((a) => a.passed).length;
  const successRate = attempts.length ? Math.round((passedCount / attempts.length) * 100) : null;

  const masteredCards = decks.flatMap((d) => d.cards).filter((c) => c.review?.status === 'mastered').length;
  const totalCards = decks.reduce((sum, d) => sum + d.cards.length, 0);

  // ------------------------------------------------ Heatmap (12 semaines)

  const dayCounts = new Map<string, number>();
  attempts.forEach((a) => {
    const day = (a.completed_at as string).slice(0, 10);
    dayCounts.set(day, (dayCounts.get(day) ?? 0) + 1);
  });

  const today = new Date();
  const days: Array<{ date: string; count: number }> = [];
  for (let i = 83; i >= 0; i--) {
    const d = new Date(today);
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    days.push({ date: key, count: dayCounts.get(key) ?? 0 });
  }

  const heatClass = (count: number): string => {
    if (count === 0) return 'bg-zinc-100 dark:bg-zinc-800';
    if (count === 1) return 'bg-sky-300 dark:bg-sky-700';
    if (count === 2) return 'bg-sky-500';
    return 'bg-sky-600 dark:bg-sky-400';
  };

  // ------------------------------------------ Meilleurs scores par quiz

  const perQuiz = quizzes
    .map((quiz) => {
      const scores = quiz.attempts
        .filter((a) => a.status === 'completed')
        .map((a) => a.score ?? 0);
      return scores.length
        ? { title: quiz.title, best: Math.max(...scores), tries: scores.length }
        : null;
    })
    .filter((row): row is { title: string; best: number; tries: number } => row !== null)
    .sort((a, b) => b.best - a.best);

  el.innerHTML = html`
    <div class="max-w-2xl mx-auto flex flex-col gap-5">
      <a data-link href="/" class="text-sm font-semibold text-sky-600 dark:text-sky-400">
        <i class="bi bi-arrow-left"></i> Tableau de bord
      </a>

      <!-- Résumé -->
      <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-4 text-center gap-4">
          <div><p class="text-2xl font-extrabold tabular-nums">${user.xp.total_xp}</p><p class="text-xs text-zinc-500">XP total</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums">${user.xp.current_level}</p><p class="text-xs text-zinc-500">Niveau</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums">🔥 ${user.xp.current_streak}</p><p class="text-xs text-zinc-500">Série (max ${user.xp.longest_streak})</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums">${successRate !== null ? `${successRate} %` : '—'}</p><p class="text-xs text-zinc-500">Réussite quiz</p></div>
        </div>
        <div class="mt-4">
          <div class="flex justify-between text-xs text-zinc-500 mb-1">
            <span>Progression niveau ${user.xp.current_level}</span>
            <span>${progress.intoLevel}/${progress.perLevel} XP</span>
          </div>
          <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
            <div class="h-full bg-sky-500 rounded-full" style="width:${progress.percent}%"></div>
          </div>
        </div>
      </section>

      <!-- Activité -->
      <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
        <h3 class="font-bold text-sm mb-1">Activité des 12 dernières semaines</h3>
        <p class="text-xs text-zinc-500 mb-3">${attempts.length} quiz complété(s) · ${masteredCards}/${totalCards} cartes maîtrisées</p>
        <div class="grid grid-flow-col grid-rows-7 gap-1 justify-start overflow-x-auto pb-1" role="img"
             aria-label="Activité quotidienne des 12 dernières semaines">
          ${raw(
            days
              .map(
                (day) =>
                  `<span class="w-3.5 h-3.5 rounded-[3px] ${heatClass(day.count)}" title="${day.date} : ${day.count} activité(s)"></span>`,
              )
              .join(''),
          )}
        </div>
      </section>

      <!-- Meilleurs scores -->
      ${perQuiz.length
        ? raw(html`
            <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
              <h3 class="font-bold text-sm mb-3">Mes meilleurs scores</h3>
              <div class="flex flex-col gap-2.5">
                ${raw(
                  perQuiz
                    .map(
                      (row) => `
                        <div>
                          <div class="flex justify-between text-xs mb-1">
                            <span class="font-semibold truncate pr-3">${escapeHtml(row.title)}</span>
                            <span class="font-bold tabular-nums ${row.best >= 100 ? 'text-emerald-500' : 'text-zinc-500'}">${row.best} %</span>
                          </div>
                          <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                            <div class="h-full rounded-full ${row.best >= 100 ? 'bg-emerald-500' : 'bg-sky-500'}" style="width:${row.best}%"></div>
                          </div>
                        </div>`,
                    )
                    .join(''),
                )}
              </div>
            </section>
          `)
        : ''}

      <!-- Classement -->
      <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
        <h3 class="font-bold text-sm mb-3"><i class="bi bi-trophy text-amber-500"></i> Classement de mes groupes</h3>
        <div id="leaderboard-zone" class="text-sm text-zinc-500">
          <div class="flex flex-col gap-2">
            <div class="skeleton h-10 rounded-xl"></div>
            <div class="skeleton h-10 rounded-xl"></div>
            <div class="skeleton h-10 rounded-xl"></div>
          </div>
        </div>
      </section>
    </div>
  `;

  void renderLeaderboard(el.querySelector('#leaderboard-zone')!);
}

async function renderLeaderboard(zone: HTMLElement): Promise<void> {
  let groups: LeaderboardGroup[] | undefined;
  let fromCache = false;

  if (syncStore.get().online) {
    try {
      const response = await api.leaderboard();
      groups = response.groups;
      await setMeta(LEADERBOARD_CACHE_KEY, groups);
    } catch {
      /* repli sur le cache */
    }
  }
  if (!groups) {
    groups = await getMeta<LeaderboardGroup[]>(LEADERBOARD_CACHE_KEY);
    fromCache = true;
  }

  if (!groups || !groups.length) {
    zone.innerHTML =
      '<p class="text-sm text-zinc-500">Classement indisponible hors-ligne — il s\'affichera à la prochaine connexion.</p>';
    return;
  }

  zone.innerHTML = groups
    .map(
      (group) => `
        <div class="mb-4 last:mb-0">
          <div class="flex items-baseline justify-between mb-2">
            <p class="text-xs font-bold uppercase tracking-wide text-zinc-500">${escapeHtml(group.group_name)}</p>
            <p class="text-[11px] text-zinc-400">${group.my_rank ? `Vous êtes ${group.my_rank}ᵉ` : ''} / ${group.total_participants}</p>
          </div>
          <div class="flex flex-col gap-1.5">
            ${group.rows
              .map(
                (row) => `
                  <div class="flex items-center gap-3 rounded-xl px-3 py-2 ${
                    row.is_me
                      ? 'bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/30'
                      : 'bg-zinc-50 dark:bg-zinc-800/60'
                  }">
                    <span class="w-7 text-center font-extrabold tabular-nums text-sm ${
                      row.rank === 1 ? 'text-amber-500' : row.rank === 2 ? 'text-zinc-400' : row.rank === 3 ? 'text-orange-400' : 'text-zinc-500'
                    }">${row.rank <= 3 ? ['🥇', '🥈', '🥉'][row.rank - 1] : row.rank}</span>
                    ${avatarHtml({ full_name: row.name, avatar_url: '' }, 'w-7 h-7 text-[10px]')}
                    <span class="flex-1 min-w-0 truncate text-sm font-semibold">${escapeHtml(row.name)}${row.is_me ? ' <span class="text-[10px] text-sky-500 font-bold">(vous)</span>' : ''}</span>
                    ${row.current_streak > 0 ? `<span class="text-[11px] text-zinc-400">🔥${row.current_streak}</span>` : ''}
                    <span class="font-bold tabular-nums text-sm">${row.total_xp} <span class="text-[10px] text-zinc-400 font-semibold">XP</span></span>
                  </div>`,
              )
              .join('')}
          </div>
        </div>`,
    )
    .join('')
    .concat(
      fromCache
        ? '<p class="text-[11px] text-zinc-400 mt-2"><i class="bi bi-clock-history"></i> Classement issu de la dernière synchronisation.</p>'
        : '',
    );
}
