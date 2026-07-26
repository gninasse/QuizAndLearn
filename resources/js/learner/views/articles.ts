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
       class="flex flex-col gap-2 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 hover:shadow-md transition-shadow">
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

  const favorites = articles.filter((a) => a.is_favorite);

  el.innerHTML = html`
    <div class="flex flex-col gap-5">
      ${favorites.length
        ? raw(html`
            <section>
              <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 mb-2">Favoris</h3>
              <div class="grid sm:grid-cols-2 gap-3">${raw(favorites.map(articleCard).join(''))}</div>
            </section>
            <h3 class="font-bold text-sm uppercase tracking-wide text-zinc-500 -mb-3">Tous les articles</h3>
          `)
        : ''}
      <div class="grid sm:grid-cols-2 gap-3">
        ${raw(articles.map(articleCard).join(''))}
      </div>
    </div>
  `;
}
