import { html, raw } from '../core/html';
import { db } from '../db/schema';
import { GAMIFICATION } from '../domain/gamification';
import type { ArticleItem } from '../domain/types';
import { articlesStore, sessionStore } from '../stores';
import { dispatch } from '../sync/engine';
import { confirmDialog } from '../ui/app-dialog';
import { toast } from '../ui/app-toast';

/** Persiste localement la mutation d'un article puis rafraîchit le store. */
async function patchArticle(id: number, patch: Partial<ArticleItem>): Promise<void> {
  const articles = articlesStore.get().map((a) => (a.id === id ? { ...a, ...patch } : a));
  articlesStore.set(articles);
  const row = articles.find((a) => a.id === id);
  if (row) await db.articles.put(row);
}

export function mount(el: HTMLElement, params: Record<string, string>): void {
  const article = articlesStore.get().find((a) => a.id === Number(params.id));

  if (!article) {
    el.innerHTML = '<p class="text-zinc-500 py-10 text-center">Article introuvable.</p>';
    return;
  }

  let maxProgress = article.progress_percentage;
  let completed = article.status === 'completed';
  let progressTimer: number | null = null;

  el.innerHTML = html`
    <article class="max-w-2xl mx-auto flex flex-col gap-4">
      <a data-link href="/articles" class="text-sm font-semibold text-sky-600 dark:text-sky-400">
        <i class="bi bi-arrow-left"></i> Articles
      </a>

      <header class="flex flex-col gap-2">
        <h2 class="text-2xl font-extrabold leading-tight">${article.title}</h2>
        <div class="flex items-center gap-3 text-sm text-zinc-500">
          <span><i class="bi bi-clock"></i> ${article.estimated_reading_time ?? '?'} min de lecture</span>
          <button id="btn-favorite" class="ml-auto w-9 h-9 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800"
                  aria-label="Favori">
            <i class="bi ${article.is_favorite ? 'bi-heart-fill text-rose-500' : 'bi-heart'}"></i>
          </button>
          <button id="btn-report" class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800"
                  aria-label="Signaler une erreur">
            <i class="bi bi-flag"></i>
          </button>
        </div>
        <div class="sticky top-16 z-30 -mx-1 px-1 py-1 bg-zinc-50/90 dark:bg-zinc-950/90 backdrop-blur">
          <div class="h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
            <div id="read-progress" class="h-full bg-sky-500 rounded-full transition-all" style="width:${maxProgress}%"></div>
          </div>
        </div>
      </header>

      <!-- Contenu riche venant du serveur : injection volontaire (raw). -->
      <div id="article-body" class="rich-content text-[15px] leading-relaxed text-zinc-800 dark:text-zinc-200">
        ${raw(article.content ?? '<p>Contenu indisponible.</p>')}
      </div>

      <footer class="border-t border-zinc-200 dark:border-zinc-800 pt-5 mt-4 flex flex-col gap-4">
        <div id="complete-zone">
          ${completed
            ? raw('<p class="text-emerald-600 dark:text-emerald-400 font-semibold text-sm"><i class="bi bi-check-circle-fill"></i> Article lu</p>')
            : raw(
                `<button id="btn-complete" class="w-full sm:w-auto rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 py-3 text-sm">Marquer comme lu (+${GAMIFICATION.XP_ARTICLE_COMPLETED} XP)</button>`,
              )}
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm text-zinc-500 mr-1">Votre note :</span>
          <div id="rating-stars" class="flex gap-1 text-xl">
            ${raw(
              [1, 2, 3, 4, 5]
                .map(
                  (i) =>
                    `<button data-star="${i}" aria-label="${i} étoiles" class="bi ${i <= article.rating ? 'bi-star-fill text-amber-400' : 'bi-star text-zinc-300 dark:text-zinc-600'}"></button>`,
                )
                .join(''),
            )}
          </div>
        </div>
      </footer>
    </article>
  `;

  // ------------------------------------------------ Progression de lecture

  const progressBar = el.querySelector<HTMLElement>('#read-progress')!;

  const computeProgress = (): number => {
    const doc = document.documentElement;
    const scrollable = doc.scrollHeight - window.innerHeight;
    if (scrollable <= 0) return 100;
    return Math.min(100, Math.round((window.scrollY / scrollable) * 100));
  };

  const sendProgress = (percent: number, status: string): void => {
    void dispatch('article_progress', {
      article_id: article.id,
      progress_percentage: percent,
      status,
    });
    void patchArticle(article.id, { progress_percentage: percent, status });
  };

  const onScroll = (): void => {
    const percent = computeProgress();
    if (percent > maxProgress) {
      maxProgress = percent;
      progressBar.style.width = `${maxProgress}%`;

      // Progression envoyée par paliers (throttle 3 s) pour ménager l'outbox.
      if (progressTimer === null && !completed) {
        progressTimer = window.setTimeout(() => {
          progressTimer = null;
          if (!completed) sendProgress(maxProgress, 'reading');
        }, 3000);
      }
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });

  // Nettoyage à la navigation (l'outlet est vidé par le router).
  const observer = new MutationObserver(() => {
    if (!document.body.contains(el.firstElementChild as Node)) {
      window.removeEventListener('scroll', onScroll);
      if (progressTimer) window.clearTimeout(progressTimer);
      observer.disconnect();
    }
  });
  observer.observe(el, { childList: true });

  // ------------------------------------------------------------ Marquer lu

  el.querySelector('#btn-complete')?.addEventListener('click', () => {
    completed = true;
    maxProgress = 100;
    progressBar.style.width = '100%';
    sendProgress(100, 'completed');
    void patchArticle(article.id, { progress_percentage: 100, status: 'completed' });

    const user = sessionStore.get();
    if (user) {
      sessionStore.set({
        ...user,
        xp: { ...user.xp, total_xp: user.xp.total_xp + GAMIFICATION.XP_ARTICLE_COMPLETED },
      });
    }

    el.querySelector('#complete-zone')!.innerHTML =
      '<p class="text-emerald-600 dark:text-emerald-400 font-semibold text-sm"><i class="bi bi-check-circle-fill"></i> Article lu (+15 XP)</p>';
    toast('+15 XP — article terminé !', 'success');
  });

  // --------------------------------------------------------------- Favori

  el.querySelector('#btn-favorite')?.addEventListener('click', (event) => {
    const icon = (event.currentTarget as HTMLElement).querySelector('i')!;
    const nowFav = icon.classList.contains('bi-heart');
    icon.className = `bi ${nowFav ? 'bi-heart-fill text-rose-500' : 'bi-heart'}`;
    void dispatch('article_favorite', { article_id: article.id, is_favorite: nowFav });
    void patchArticle(article.id, { is_favorite: nowFav });
  });

  // ----------------------------------------------------------------- Note

  el.querySelector('#rating-stars')?.addEventListener('click', (event) => {
    const button = (event.target as HTMLElement).closest<HTMLElement>('[data-star]');
    if (!button) return;
    const rating = Number(button.dataset.star);

    el.querySelectorAll<HTMLElement>('[data-star]').forEach((star) => {
      const value = Number(star.dataset.star);
      star.className = `bi ${value <= rating ? 'bi-star-fill text-amber-400' : 'bi-star text-zinc-300 dark:text-zinc-600'}`;
    });

    void dispatch('article_rate', { article_id: article.id, rating });
    void patchArticle(article.id, { rating });
    toast('Merci pour votre note !', 'success');
  });

  // ----------------------------------------------------------- Signalement

  el.querySelector('#btn-report')?.addEventListener('click', () => {
    void (async () => {
      const ok = await confirmDialog(
        'Signaler une erreur',
        'Signaler un problème de contenu sur cet article aux formateurs ?',
        'Signaler',
      );
      if (ok) {
        void dispatch('article_error_report', {
          content_id: article.id,
          error_type: 'content',
          comment: `Signalement depuis l'article « ${article.title} »`,
        });
        toast('Signalement envoyé.', 'success');
      }
    })();
  });
}
