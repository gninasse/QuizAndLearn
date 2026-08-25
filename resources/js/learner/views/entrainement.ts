import { escapeHtml, html, raw } from '../core/html';
import { isDue } from '../domain/sm2';
import type { DeckItem, QuizItem } from '../domain/types';
import { decksStore, quizzesStore } from '../stores';
import { pluralize } from './helpers';

/**
 * Espace Entraînement : quiz et flashcards réunis (segments), sans enjeu —
 * les épreuves notées vivent dans l'espace Examens séparé.
 */

function quizCard(quiz: QuizItem): string {
  const badge =
    quiz.status === 'completed'
      ? '<span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> Terminé</span>'
      : quiz.status === 'in_progress'
        ? '<span class="text-[11px] font-bold text-amber-600 dark:text-amber-400"><i class="bi bi-play-circle-fill"></i> En cours</span>'
        : '<span class="text-[11px] font-bold text-sky-600 dark:text-sky-400">Nouveau</span>';

  const best = quiz.attempts
    .filter((a) => a.status === 'completed')
    .sort((a, b) => (b.score ?? 0) - (a.score ?? 0))[0];

  return html`
    <a data-link href="/quizzes/${quiz.id}"
       class="group flex items-center gap-3.5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-sm hover:shadow-md hover:border-sky-200 dark:hover:border-sky-500/30 transition-all">
      <span class="w-11 h-11 shrink-0 rounded-xl bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg group-hover:scale-105 transition-transform">
        <i class="bi bi-patch-question"></i>
      </span>
      <div class="min-w-0 flex-1">
        <p class="font-bold text-sm leading-snug">${quiz.title}</p>
        <p class="text-xs text-zinc-500 mt-0.5">
          ${quiz.questions.length} questions${quiz.duration ? ` · ${quiz.duration} min` : ''} · réussite ≥ ${quiz.passing_score} %
        </p>
        ${best ? raw(`<p class="text-xs mt-0.5 font-semibold ${best.passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500'}">Meilleur score : ${best.score} %</p>`) : ''}
      </div>
      <div class="text-right shrink-0">${raw(badge)}</div>
    </a>
  `;
}

function deckCard(deck: DeckItem): string {
  const dueCount = deck.cards.filter((c) => isDue(c.review?.next_review)).length;
  const mastered = deck.cards.filter((c) => c.review?.status === 'mastered').length;
  const progress = deck.cards.length ? Math.round((mastered / deck.cards.length) * 100) : 0;

  return html`
    <div class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-sm flex flex-col gap-3">
      <div class="flex items-start gap-3.5">
        <span class="w-11 h-11 shrink-0 rounded-xl bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center text-lg">
          <i class="bi bi-stack"></i>
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-bold text-sm">${deck.titre}</p>
          ${deck.matiere ? raw(`<p class="text-xs text-zinc-500">${escapeHtml(deck.matiere)}</p>`) : ''}
          <p class="text-xs text-zinc-500 mt-1">${deck.cards.length} ${pluralize(deck.cards.length, 'carte')} · ${mastered} maîtrisée(s)</p>
        </div>
        ${dueCount > 0
          ? raw(`<span class="shrink-0 inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-violet-600 text-white text-xs font-bold">${dueCount}</span>`)
          : raw('<span class="shrink-0 text-emerald-500 text-lg"><i class="bi bi-check-circle-fill"></i></span>')}
      </div>
      <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
        <div class="h-full bg-violet-500 rounded-full transition-all" style="width:${progress}%"></div>
      </div>
      <a data-link href="/reviser/${deck.id}"
         class="rounded-xl ${dueCount > 0 ? 'bg-violet-600 hover:bg-violet-500' : 'bg-zinc-200 dark:bg-zinc-800 hover:bg-zinc-300 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200'} ${dueCount > 0 ? 'text-white' : ''} text-center text-sm font-bold py-2.5 transition-colors">
        ${dueCount > 0 ? `Réviser ${dueCount} ${pluralize(dueCount, 'carte')}` : 'Réviser en avance'}
      </a>
    </div>
  `;
}

export function mount(el: HTMLElement): void {
  const tab = new URLSearchParams(window.location.search).get('tab') === 'cartes' ? 'cartes' : 'quiz';
  const quizzes = quizzesStore.get();
  const decks = decksStore.get();
  const totalDue = decks.flatMap((d) => d.cards).filter((c) => isDue(c.review?.next_review)).length;

  const emptyState = (emoji: string, title: string, hint: string): string =>
    `<div class="text-center py-16 text-zinc-500"><div class="text-4xl mb-3">${emoji}</div><p class="font-semibold">${title}</p><p class="text-sm mt-1">${hint}</p></div>`;

  const renderContent = (active: string): string => {
    if (active === 'cartes') {
      return decks.length
        ? `<div class="grid sm:grid-cols-2 gap-3">${decks.map(deckCard).join('')}</div>`
        : emptyState('🃏', 'Aucun deck de révision', 'Les decks assignés à vos groupes apparaîtront ici.');
    }
    return quizzes.length
      ? `<div class="flex flex-col gap-3">${quizzes.map(quizCard).join('')}</div>`
      : emptyState('❓', 'Aucun quiz assigné', 'Les quiz assignés à vos groupes apparaîtront ici.');
  };

  el.innerHTML = html`
    <div class="flex flex-col gap-4">
      <p class="text-sm text-zinc-500 -mb-1">
        Entraînez-vous sans pression : les quiz sont rejouables et les cartes suivent votre mémoire.
      </p>
      <div class="flex rounded-xl bg-zinc-100 dark:bg-zinc-800/80 p-1 max-w-sm" role="tablist">
        <button data-tab="quiz" role="tab"
                class="flex-1 rounded-lg py-2 text-sm font-bold transition-colors flex items-center justify-center gap-1.5">
          <i class="bi bi-patch-question"></i> Quiz (${quizzes.length})
        </button>
        <button data-tab="cartes" role="tab"
                class="flex-1 rounded-lg py-2 text-sm font-bold transition-colors flex items-center justify-center gap-1.5">
          <i class="bi bi-stack"></i> Flashcards${totalDue > 0 ? raw(`<span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-violet-600 text-white text-[10px] font-bold">${totalDue}</span>`) : ''}
        </button>
      </div>
      <div id="training-content">${raw(renderContent(tab))}</div>
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
      const active = button.dataset.tab ?? 'quiz';
      history.replaceState(null, '', active === 'cartes' ? '/entrainement?tab=cartes' : '/entrainement');
      styleTabs(active);
      el.querySelector('#training-content')!.innerHTML = renderContent(active);
    });
  });
}
