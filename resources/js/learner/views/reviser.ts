import { html, raw } from '../core/html';
import { db } from '../db/schema';
import { GAMIFICATION } from '../domain/gamification';
import { isDue, review, statusFor, type Sm2State } from '../domain/sm2';
import type { DeckCard, DeckItem } from '../domain/types';
import { decksStore, sessionStore } from '../stores';
import { dispatch } from '../sync/engine';
import { toast } from '../ui/app-toast';
import { playCorrect, playWrong } from '../ui/sound';

/**
 * Révision par deck (système FlashcardDeck/FlashcardItem) :
 * SM-2 calculé côté client → révision 100 % hors-ligne, serveur = autorité.
 */

// Notes qualité : mêmes valeurs que le legacy (0 à revoir, 3/4/5).
const RATINGS = [
  { q: 0, label: 'À revoir', classes: 'bg-red-600 hover:bg-red-500' },
  { q: 3, label: 'Difficile', classes: 'bg-amber-600 hover:bg-amber-500' },
  { q: 4, label: 'Moyen', classes: 'bg-sky-600 hover:bg-sky-500' },
  { q: 5, label: 'Facile', classes: 'bg-emerald-600 hover:bg-emerald-500' },
] as const;

export function mount(el: HTMLElement, params: Record<string, string>): void {
  const deckId = params.deckId ? Number(params.deckId) : null;

  // La liste des decks vit désormais dans l'espace Entraînement.
  if (deckId === null) {
    history.replaceState(null, '', '/entrainement?tab=cartes');
    window.dispatchEvent(new PopStateEvent('popstate'));
    return;
  }

  const deck = decksStore.get().find((d) => d.id === deckId);
  if (!deck) {
    el.innerHTML = '<p class="text-zinc-500 py-10 text-center">Deck introuvable.</p>';
    return;
  }
  startSession(el, deck);
}

// ------------------------------------------------------- Session de deck

interface SessionStats {
  studied: number;
  newCards: number;
  reviewed: number;
  mastered: number;
  grades: number[];
  startedAt: Date;
}

function startSession(el: HTMLElement, deck: DeckItem): void {
  // Cartes dues d'abord ; s'il n'y en a pas, tout le deck (révision en avance).
  const due = deck.cards.filter((c) => isDue(c.review?.next_review));
  const queue = (due.length ? due : [...deck.cards]).slice();

  const stats: SessionStats = {
    studied: 0,
    newCards: 0,
    reviewed: 0,
    mastered: 0,
    grades: [],
    startedAt: new Date(),
  };

  let flipped = false;

  function renderCard(): void {
    const card = queue[0];
    if (!card) {
      renderDone();
      return;
    }
    flipped = false;
    const position = stats.studied + 1;
    const total = stats.studied + queue.length;

    el.innerHTML = html`
      <div class="max-w-xl mx-auto flex flex-col gap-4">
        <div class="flex items-center gap-3">
          <a data-link href="/entrainement?tab=cartes" class="text-sm font-semibold text-zinc-500 hover:text-red-500">
            <i class="bi bi-x-lg"></i> Arrêter
          </a>
          <div class="flex-1 h-2 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
            <div class="h-full bg-violet-500 rounded-full transition-all" style="width:${Math.round((stats.studied / total) * 100)}%"></div>
          </div>
          <span class="text-xs font-bold text-zinc-500 tabular-nums">${position}/${total}</span>
        </div>

        <div id="flip" class="flip-card cursor-pointer select-none" role="button" tabindex="0" aria-label="Retourner la carte">
          <div class="flip-card-inner relative min-h-72">
            <div class="flip-face absolute inset-0 rounded-2xl border-2 border-violet-200 dark:border-violet-500/30 bg-white dark:bg-zinc-900 p-6 flex flex-col">
              <span class="text-[11px] font-bold uppercase tracking-wide text-violet-500 mb-3">Question</span>
              <div class="rich-content flex-1 flex items-center justify-center text-center font-semibold">${raw(card.recto)}</div>
              <p class="text-xs text-zinc-400 text-center mt-3"><i class="bi bi-arrow-repeat"></i> Touchez pour retourner</p>
            </div>
            <div class="flip-face flip-back absolute inset-0 rounded-2xl border-2 border-emerald-200 dark:border-emerald-500/30 bg-white dark:bg-zinc-900 p-6 flex flex-col">
              <span class="text-[11px] font-bold uppercase tracking-wide text-emerald-500 mb-3">Réponse</span>
              <div class="rich-content flex-1 flex items-center justify-center text-center">${raw(card.verso)}</div>
            </div>
          </div>
        </div>

        <div id="rating-zone" class="grid grid-cols-4 gap-2 opacity-0 pointer-events-none transition-opacity">
          ${raw(
            RATINGS.map(
              (r) =>
                `<button data-quality="${r.q}" class="rounded-xl ${r.classes} text-white text-xs sm:text-sm font-bold py-3">${r.label}</button>`,
            ).join(''),
          )}
        </div>
      </div>
    `;

    const flip = el.querySelector<HTMLElement>('#flip')!;
    const ratingZone = el.querySelector<HTMLElement>('#rating-zone')!;

    const doFlip = (): void => {
      flipped = !flipped;
      flip.classList.toggle('flipped', flipped);
      if (flipped) {
        ratingZone.classList.remove('opacity-0', 'pointer-events-none');
      }
    };
    flip.addEventListener('click', doFlip);
    flip.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        doFlip();
      }
    });

    ratingZone.querySelectorAll<HTMLButtonElement>('[data-quality]').forEach((button) => {
      button.addEventListener('click', () => {
        void evaluate(card, Number(button.dataset.quality));
      });
    });
  }

  async function evaluate(card: DeckCard, quality: number): Promise<void> {
    const prior: Sm2State = card.review
      ? {
          easinessFactor: card.review.easiness_factor,
          repetitions: card.review.repetitions,
          intervalDays: card.review.interval_days,
        }
      : { easinessFactor: deck.easiness_default, repetitions: 0, intervalDays: 0 };

    const isNew = !card.review;
    if (quality >= 3) playCorrect();
    else playWrong();
    const next = review(prior, quality, deck.interval_min, deck.interval_max);
    const status = statusFor(next.repetitions, quality);

    // État local mis à jour immédiatement (offline-first).
    card.review = {
      easiness_factor: next.easinessFactor,
      interval_days: next.intervalDays,
      repetitions: next.repetitions,
      last_reviewed: new Date().toISOString(),
      next_review: next.nextReview.toISOString(),
      status,
    };

    const decks = decksStore.get().map((d) =>
      d.id === deck.id ? { ...d, cards: d.cards.map((c) => (c.id === card.id ? card : c)) } : d,
    );
    decksStore.set(decks);
    const updated = decks.find((d) => d.id === deck.id);
    if (updated) await db.decks.put(updated);

    // Action serveur (le serveur recalcule le SM-2 — même formule).
    void dispatch('card_review', { card_id: card.id, quality });

    // XP optimiste.
    const user = sessionStore.get();
    if (user) {
      sessionStore.set({
        ...user,
        xp: { ...user.xp, total_xp: user.xp.total_xp + GAMIFICATION.XP_CARD_REVIEW },
      });
    }

    stats.studied++;
    stats.grades.push(quality);
    if (isNew) stats.newCards++;
    else stats.reviewed++;
    if (status === 'mastered') stats.mastered++;

    queue.shift();
    if (quality < 3) {
      // Carte ratée : re-présentée en fin de session.
      queue.push(card);
    }

    renderCard();
  }

  function renderDone(): void {
    const seconds = Math.round((Date.now() - stats.startedAt.getTime()) / 1000);

    void dispatch('review_session', {
      deck_id: deck.id,
      date_debut: stats.startedAt.toISOString(),
      date_fin: new Date().toISOString(),
      duree_seconds: seconds,
      cartes_etudiees: stats.studied,
      cartes_nouvelles: stats.newCards,
      cartes_revues: stats.reviewed,
      cartes_maitrisees: stats.mastered,
      grades: stats.grades,
    });

    toast(`+${stats.studied * GAMIFICATION.XP_CARD_REVIEW} XP — session terminée !`, 'success');

    el.innerHTML = html`
      <div class="max-w-md mx-auto text-center py-10 flex flex-col gap-5">
        <div class="text-6xl">🧠</div>
        <h2 class="text-2xl font-extrabold">Session terminée !</h2>
        <div class="rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-6 grid grid-cols-3 gap-4">
          <div><p class="text-2xl font-extrabold tabular-nums">${stats.studied}</p><p class="text-xs text-zinc-500">révisées</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums text-emerald-500">${stats.mastered}</p><p class="text-xs text-zinc-500">maîtrisées</p></div>
          <div><p class="text-2xl font-extrabold tabular-nums text-amber-500">+${stats.studied * GAMIFICATION.XP_CARD_REVIEW}</p><p class="text-xs text-zinc-500">XP</p></div>
        </div>
        <div class="flex gap-3 justify-center">
          <a data-link href="/reviser/${deck.id}" class="rounded-xl border border-zinc-300 dark:border-zinc-700 px-5 py-2.5 text-sm font-bold">Recommencer</a>
          <a data-link href="/entrainement?tab=cartes" class="rounded-xl glow-violet bg-violet-600 hover:bg-violet-500 text-white px-5 py-2.5 text-sm font-bold">Mes decks</a>
        </div>
      </div>
    `;
  }

  renderCard();
}
