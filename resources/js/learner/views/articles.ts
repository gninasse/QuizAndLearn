import { escapeHtml, html, raw } from '../core/html';
import { articlesStore } from '../stores';
import type { ArticleItem } from '../domain/types';
import { formatDate } from './helpers';

function starRating(rating: number): string {
  return [1, 2, 3, 4, 5]
    .map((i) => `<i class="bi ${i <= rating ? 'bi-star-fill text-amber-400' : 'bi-star text-zinc-300 dark:text-zinc-600'}"></i>`)
    .join('');
}

function articleCard(article: ArticleItem): string {
  const statusBadge =
    article.status === 'completed'
      ? '<span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> Lu</span>'
      : article.progress_percentage > 0
        ? `<span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">${article.progress_percentage} %</span>`
        : '<span class="text-[11px] font-bold text-sky-600 dark:text-sky-400">Nouveau</span>';

  return html`
    <a data-link href="/articles/${article.id}"
       class="flex flex-col gap-2 rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-4 hover:shadow-md transition-shadow">
      <div class="flex items-center gap-2">
        ${article.category
          ? raw(`<span class="text-[11px] font-bold uppercase tracking-wide text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 rounded-full px-2.5 py-0.5">${escapeHtml(article.category)}</span>`)
          : ''}
        <span class="ml-auto">${raw(statusBadge)}</span>
        ${article.is_favorite ? raw('<i class="bi bi-heart-fill text-rose-500 text-sm"></i>') : ''}
      </div>
      <h3 class="font-bold leading-snug">${article.title}</h3>
      <div class="flex items-center gap-3 text-xs text-zinc-500">
        <span><i class="bi bi-clock"></i> ${article.estimated_reading_time ?? '?'} min</span>
        <span>${formatDate(article.created_at)}</span>
        ${article.rating > 0 ? raw(`<span class="flex gap-0.5 text-xs">${starRating(article.rating)}</span>`) : ''}
      </div>
      ${article.progress_percentage > 0 && article.status !== 'completed'
        ? raw(
            `<div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden"><div class="h-full bg-sky-500 rounded-full" style="width:${article.progress_percentage}%"></div></div>`,
          )
        : ''}
    </a>
  `;
}

type ArticleFilter = 'tous' | 'a-lire' | 'lus' | 'favoris';

const FILTERS: Array<{ key: ArticleFilter; label: string }> = [
  { key: 'tous', label: 'Tous' },
  { key: 'a-lire', label: 'À lire' },
  { key: 'lus', label: 'Lus' },
  { key: 'favoris', label: 'Favoris ♥' },
];

export function mount(el: HTMLElement): void {
  const articles = articlesStore.get();

  if (!articles.length) {
    el.innerHTML = html`
      <div class="text-center py-16 text-zinc-500">
        <div class="text-4xl mb-3">📚</div>
        <p class="font-semibold">Aucun article disponible</p>
        <p class="text-sm mt-1">Les articles assignés à vos groupes apparaîtront ici.</p>
      </div>
    `;
    return;
  }

  let query = '';
  let filter: ArticleFilter = 'tous';

  const filtered = (): ArticleItem[] => {
    const q = query.trim().toLowerCase();
    return articles.filter((article) => {
      if (q && !`${article.title} ${article.category ?? ''}`.toLowerCase().includes(q)) return false;
      switch (filter) {
        case 'a-lire':
          return article.status !== 'completed';
        case 'lus':
          return article.status === 'completed';
        case 'favoris':
          return article.is_favorite;
        default:
          return true;
      }
    });
  };

  el.innerHTML = html`
    <div class="flex flex-col gap-4">
      <div class="relative">
        <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" aria-hidden="true"></i>
        <input id="article-search" type="search" placeholder="Rechercher un article…"
               aria-label="Rechercher un article"
               class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 pl-11 pr-4 py-3 text-sm focus:border-sky-500 focus:outline-none" />
      </div>
      <div class="flex gap-2 flex-wrap" role="group" aria-label="Filtres">
        ${raw(
          FILTERS.map(
            (f) =>
              `<button data-filter="${f.key}" aria-pressed="${f.key === 'tous'}"
                       class="filter-chip rounded-full px-3.5 py-1.5 text-xs font-bold border transition-colors">${f.label}</button>`,
          ).join(''),
        )}
      </div>
      <div id="articles-grid" class="grid sm:grid-cols-2 gap-3"></div>
    </div>
  `;

  const grid = el.querySelector<HTMLElement>('#articles-grid')!;

  const renderGrid = (): void => {
    const rows = filtered();
    grid.innerHTML = rows.length
      ? rows.map(articleCard).join('')
      : '<p class="col-span-full text-center py-10 text-sm text-zinc-500">Aucun article ne correspond.</p>';
  };

  const styleChips = (): void => {
    el.querySelectorAll<HTMLButtonElement>('.filter-chip').forEach((chip) => {
      const active = chip.dataset.filter === filter;
      chip.setAttribute('aria-pressed', String(active));
      chip.className = `filter-chip rounded-full px-3.5 py-1.5 text-xs font-bold border transition-colors ${
        active
          ? 'bg-sky-600 border-sky-600 text-white'
          : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-300 hover:border-sky-300'
      }`;
    });
  };

  el.querySelector('#article-search')?.addEventListener('input', (event) => {
    query = (event.target as HTMLInputElement).value;
    renderGrid();
  });
  el.querySelectorAll<HTMLButtonElement>('.filter-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
      filter = (chip.dataset.filter as ArticleFilter) ?? 'tous';
      styleChips();
      renderGrid();
    });
  });

  styleChips();
  renderGrid();
}
