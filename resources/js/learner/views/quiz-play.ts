import { escapeHtml, html, raw } from '../core/html';
import { db } from '../db/schema';
import { xpForQuizCompletion } from '../domain/gamification';
import { scoreQuiz, type AnswerValue } from '../domain/scoring';
import type { QuizDraft, QuizItem, QuizQuestion } from '../domain/types';
import { quizzesStore, sessionStore } from '../stores';
import { dispatch } from '../sync/engine';
import { confirmDialog, dangerDialog } from '../ui/app-dialog';
import { makeListDraggable } from '../ui/drag-list';
import { playSuccess, playWrong } from '../ui/sound';
import { toast } from '../ui/app-toast';
import { formatDuration, shuffleArray } from './helpers';

interface PlayState {
  quiz: QuizItem;
  questions: QuizQuestion[];
  index: number;
  answers: Record<number, AnswerValue>;
  startedAt: Date;
  remainingSeconds: number | null;
  timerId: number | null;
  /** Ordres mélangés mémorisés par question (matching/ordering). */
  shuffles: Record<number, string[]>;
  /** Mode entraînement libre (rejouer ses erreurs) : rien n'est envoyé au serveur. */
  practice: boolean;
}

export function mount(el: HTMLElement, params: Record<string, string>): void {
  void mountAsync(el, params);
}

async function mountAsync(el: HTMLElement, params: Record<string, string>): Promise<void> {
  const practice = params.id === 'erreurs';

  let quiz: QuizItem | undefined;
  if (practice) {
    quiz = await buildMistakesQuiz();
    if (!quiz) {
      el.innerHTML = html`
        <div class="text-center py-16 text-zinc-500">
          <div class="text-4xl mb-3">🎯</div>
          <p class="font-semibold">Aucune erreur à rejouer</p>
          <p class="text-sm mt-1">Les questions ratées lors de vos quiz apparaîtront ici.</p>
          <a data-link href="/entrainement" class="inline-block mt-4 rounded-xl bg-sky-600 text-white font-bold px-5 py-2.5 text-sm">Retour</a>
        </div>
      `;
      return;
    }
  } else {
    quiz = quizzesStore.get().find((q) => q.id === Number(params.id));
  }

  if (!quiz) {
    el.innerHTML = '<p class="text-zinc-500 py-10 text-center">Quiz introuvable.</p>';
    return;
  }
  if (!practice && quiz.max_attempts_reached) {
    el.innerHTML =
      '<p class="text-zinc-500 py-10 text-center">Nombre maximum de tentatives atteint pour ce quiz.</p>';
    return;
  }

  const state: PlayState = {
    quiz,
    questions: quiz.shuffle_questions ? shuffleArray(quiz.questions) : [...quiz.questions],
    index: 0,
    answers: {},
    startedAt: new Date(),
    remainingSeconds: quiz.duration ? quiz.duration * 60 : null,
    timerId: null,
    shuffles: {},
    practice,
  };

  // ----------------------------------------------- Reprise de brouillon

  if (!practice) {
    const draft = await db.drafts.get(quiz.id);
    if (draft) {
      const resume = await confirmDialog(
        'Reprendre votre session ?',
        'Une session interrompue de ce quiz a été retrouvée sur cet appareil.',
        'Reprendre',
      );
      if (resume) {
        state.answers = draft.answers as Record<number, AnswerValue>;
        state.index = Math.min(draft.index, state.questions.length - 1);
        state.startedAt = new Date(draft.started_at);
        state.remainingSeconds = draft.remaining_seconds;
        const byId = new Map(state.questions.map((q) => [q.id, q]));
        const restored = draft.question_order
          .map((id) => byId.get(id))
          .filter((q): q is QuizQuestion => q !== undefined);
        if (restored.length === state.questions.length) state.questions = restored;
      } else {
        await db.drafts.delete(quiz.id);
      }
    }
  }

  const saveDraft = (): void => {
    if (state.practice) return;
    const draft: QuizDraft = {
      quiz_id: state.quiz.id,
      answers: state.answers,
      question_order: state.questions.map((q) => q.id),
      index: state.index,
      started_at: state.startedAt.toISOString(),
      remaining_seconds: state.remainingSeconds,
      updated_at: new Date().toISOString(),
    };
    void db.drafts.put(draft);
  };

  const cleanup = (): void => {
    if (state.timerId) window.clearInterval(state.timerId);
    state.timerId = null;
  };

  const observer = new MutationObserver(() => {
    if (!document.body.contains(el.firstElementChild as Node)) {
      cleanup();
      observer.disconnect();
    }
  });
  observer.observe(el, { childList: true });

  if (state.remainingSeconds !== null) {
    state.timerId = window.setInterval(() => {
      if (state.remainingSeconds === null) return;
      state.remainingSeconds--;
      const timerEl = el.querySelector('#quiz-timer');
      if (timerEl) {
        timerEl.textContent = formatDuration(Math.max(0, state.remainingSeconds));
        timerEl.classList.toggle('text-red-500', state.remainingSeconds <= 60);
      }
      if (state.remainingSeconds % 10 === 0) saveDraft();
      if (state.remainingSeconds <= 0) {
        cleanup();
        void finish();
      }
    }, 1000);
  }

  // ------------------------------------------------------------- Rendu

  function questionBody(question: QuizQuestion): string {
    const options = question.options as Record<string, any>;
    const saved = state.answers[question.id];

    switch (question.type) {
      case 'true_false':
        return html`
          <div class="grid grid-cols-2 gap-3">
            ${raw(
              (['true', 'false'] as const)
                .map(
                  (value) => `
                    <button data-answer="${value}"
                            class="answer-tf rounded-2xl border-2 py-6 text-lg font-bold transition-colors ${
                              saved === value
                                ? 'border-sky-500 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400'
                                : 'border-zinc-200 dark:border-zinc-700 hover:border-sky-300'
                            }">
                      ${value === 'true' ? '<i class="bi bi-check-circle mr-2"></i>Vrai' : '<i class="bi bi-x-circle mr-2"></i>Faux'}
                    </button>`,
                )
                .join(''),
            )}
          </div>
        `;

      case 'mcq':
      case 'single_choice':
      case 'multiple_choice': {
        const isMultiple =
          options.multiple === true || options.multiple === 'true' || question.type === 'multiple_choice';
        const answersList: Array<{ text: string }> = options.answers ?? [];
        const savedArray = Array.isArray(saved) ? saved : saved ? [saved as string] : [];

        return html`
          ${isMultiple
            ? raw('<p class="text-xs text-zinc-500 mb-2"><i class="bi bi-info-circle"></i> Plusieurs réponses possibles</p>')
            : ''}
          <div class="flex flex-col gap-2">
            ${raw(
              answersList
                .map((a, choiceIdx) => {
                  const selected = savedArray.includes(a.text);
                  const letter = String.fromCharCode(65 + choiceIdx);
                  return `
                    <button data-choice="${escapeHtml(a.text)}" data-multiple="${isMultiple}"
                            aria-pressed="${selected}"
                            class="answer-choice text-left rounded-xl border-2 px-3.5 py-3 text-sm font-semibold transition-all flex items-center gap-3 ${
                              selected
                                ? 'border-sky-500 bg-sky-50 dark:bg-sky-500/10 shadow-sm'
                                : 'border-zinc-200 dark:border-zinc-700 hover:border-sky-300'
                            }">
                      <span class="w-7 h-7 shrink-0 rounded-lg flex items-center justify-center text-xs font-extrabold transition-colors ${
                        selected ? 'bg-sky-600 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500'
                      }">${selected && isMultiple ? '<i class="bi bi-check-lg"></i>' : letter}</span>
                      <span class="flex-1">${escapeHtml(a.text)}</span>
                      ${selected && !isMultiple ? '<i class="bi bi-check-circle-fill text-sky-600"></i>' : ''}
                    </button>`;
                })
                .join(''),
            )}
          </div>
        `;
      }

      case 'fill_blank': {
        const blanks: Array<{ answers?: string[] }> = options.blanks ?? [];
        const savedArray = Array.isArray(saved) ? (saved as string[]) : [];
        return html`
          <div class="flex flex-col gap-3">
            ${raw(
              blanks
                .map(
                  (_, idx) => `
                    <label class="flex flex-col gap-1">
                      <span class="text-xs font-bold text-zinc-500">Trou ${idx + 1}</span>
                      <input data-blank="${idx}" type="text" value="${escapeHtml(savedArray[idx] ?? '')}"
                             class="answer-blank rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none"
                             placeholder="Votre réponse…" />
                    </label>`,
                )
                .join(''),
            )}
          </div>
        `;
      }

      case 'matching': {
        const pairs: Array<{ term: string; definition: string }> = options.pairs ?? [];
        if (!state.shuffles[question.id]) {
          state.shuffles[question.id] = shuffleArray(pairs.map((p) => p.definition));
        }
        const definitions = state.shuffles[question.id] ?? [];
        const savedDict = (saved ?? {}) as Record<string, string[]>;
        const savedDefs = savedDict.definitions ?? [];

        return html`
          <div class="flex flex-col gap-3">
            ${raw(
              pairs
                .map(
                  (pair, idx) => `
                    <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2 rounded-xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-800/40 p-2.5">
                      <span class="sm:w-1/2 text-sm font-bold rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 px-4 py-2.5 flex items-center gap-2">
                        <i class="bi bi-arrow-return-right text-sky-500 sm:hidden" aria-hidden="true"></i>${escapeHtml(pair.term)}
                      </span>
                      <i class="bi bi-arrow-right text-sky-500 hidden sm:block shrink-0" aria-hidden="true"></i>
                      <select data-match="${idx}" class="answer-match sm:flex-1 rounded-lg border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm">
                        <option value="">— Choisir —</option>
                        ${definitions
                          .map(
                            (def) =>
                              `<option value="${escapeHtml(def)}" ${savedDefs[idx] === def ? 'selected' : ''}>${escapeHtml(def)}</option>`,
                          )
                          .join('')}
                      </select>
                    </div>`,
                )
                .join(''),
            )}
          </div>
        `;
      }

      case 'ordering': {
        const items: string[] = options.items ?? [];
        if (!state.shuffles[question.id]) {
          state.shuffles[question.id] = Array.isArray(saved) && saved.length
            ? [...(saved as string[])]
            : shuffleArray(items);
        }
        const current = state.shuffles[question.id] ?? [];

        return html`
          <p class="text-xs text-zinc-500 mb-2"><i class="bi bi-info-circle"></i> Glissez les éléments (ou utilisez les flèches) pour les remettre dans le bon ordre</p>
          <ol data-ordering-list class="flex flex-col gap-2">
            ${raw(
              current
                .map(
                  (item, idx) => `
                    <li data-key="${escapeHtml(item)}" class="flex items-center gap-2 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 py-2.5">
                      <span class="drag-handle w-11 h-11 shrink-0 rounded-lg flex items-center justify-center text-zinc-400" aria-hidden="true"><i class="bi bi-grip-vertical text-lg"></i></span>
                      <span class="w-6 h-6 shrink-0 rounded-full bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 text-xs font-bold flex items-center justify-center">${idx + 1}</span>
                      <span class="flex-1 text-sm font-semibold">${escapeHtml(item)}</span>
                      <button data-move="up" data-idx="${idx}" ${idx === 0 ? 'disabled' : ''} class="w-11 h-11 shrink-0 rounded-lg flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-30" aria-label="Monter"><i class="bi bi-chevron-up text-lg"></i></button>
                      <button data-move="down" data-idx="${idx}" ${idx === current.length - 1 ? 'disabled' : ''} class="w-11 h-11 shrink-0 rounded-lg flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-30" aria-label="Descendre"><i class="bi bi-chevron-down text-lg"></i></button>
                    </li>`,
                )
                .join(''),
            )}
          </ol>
        `;
      }

      case 'open_text':
        return html`
          <textarea data-open-text rows="5" placeholder="Rédigez votre réponse…"
                    class="w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none">${typeof saved === 'string' ? saved : ''}</textarea>
        `;

      default:
        return '<p class="text-sm text-zinc-500">Type de question non pris en charge.</p>';
    }
  }

  function render(): void {
    const question = state.questions[state.index];
    if (!question) return;
    const total = state.questions.length;
    const answered = Object.keys(state.answers).length;

    el.innerHTML = html`
      <div class="max-w-2xl mx-auto flex flex-col gap-4">
        ${state.practice
          ? raw(
              '<p class="rounded-xl bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 text-xs font-semibold px-4 py-2.5"><i class="bi bi-arrow-repeat"></i> Entraînement libre sur vos erreurs — sans XP ni enregistrement.</p>',
            )
          : ''}
        <div class="flex items-center gap-3">
          <button id="btn-quit" class="text-sm font-semibold text-zinc-500 hover:text-red-500">
            <i class="bi bi-x-lg"></i> Quitter
          </button>
          <div class="flex-1 h-2 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
            <div class="h-full bg-sky-500 rounded-full transition-all" style="width:${Math.round(((state.index + 1) / total) * 100)}%"></div>
          </div>
          ${state.remainingSeconds !== null
            ? raw(
                `<span id="quiz-timer" class="text-sm font-bold tabular-nums ${state.remainingSeconds <= 60 ? 'text-red-500' : ''}">${formatDuration(state.remainingSeconds)}</span>`,
              )
            : ''}
        </div>

        <div class="question-card rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-5 flex flex-col gap-4 shadow-sm">
          <div class="flex items-center justify-between text-xs font-bold text-zinc-500">
            <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>Question ${state.index + 1} / ${total}</span>
            <span>${question.points} pt${question.points > 1 ? 's' : ''}</span>
          </div>
          <!-- Énoncé riche (éditeur admin) : injection volontaire. -->
          <div class="rich-content font-semibold text-[15px]">${raw(question.question_text)}</div>
          <div id="question-body">${raw(questionBody(question))}</div>
        </div>

        <div class="flex items-center gap-3">
          <button id="btn-prev" ${state.index === 0 ? 'disabled' : ''}
                  class="rounded-xl border border-zinc-300 dark:border-zinc-700 px-5 py-2.5 text-sm font-bold disabled:opacity-40">
            <i class="bi bi-arrow-left"></i> Précédent
          </button>
          <span class="flex-1 text-center text-xs text-zinc-500">${answered}/${total} répondues</span>
          ${state.index === total - 1
            ? raw('<button id="btn-finish" class="rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2.5 text-sm font-bold glow-emerald">Terminer <i class="bi bi-check-lg"></i></button>')
            : raw('<button id="btn-next" class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white px-5 py-2.5 text-sm font-bold glow-sky">Suivant <i class="bi bi-arrow-right"></i></button>')}
        </div>
      </div>
    `;

    bind(question);
  }

  function collectCurrent(question: QuizQuestion): void {
    switch (question.type) {
      case 'fill_blank': {
        const values = Array.from(el.querySelectorAll<HTMLInputElement>('.answer-blank')).map((i) => i.value);
        if (values.some((v) => v.trim() !== '')) state.answers[question.id] = values;
        break;
      }
      case 'matching': {
        const options = question.options as Record<string, any>;
        const pairs: Array<{ term: string }> = options.pairs ?? [];
        const selects = Array.from(el.querySelectorAll<HTMLSelectElement>('.answer-match'));
        if (selects.some((s) => s.value !== '')) {
          state.answers[question.id] = {
            terms: pairs.map((p) => p.term),
            definitions: selects.map((s) => s.value),
          };
        }
        break;
      }
      case 'ordering':
        state.answers[question.id] = [...(state.shuffles[question.id] ?? [])];
        break;
      case 'open_text': {
        const textarea = el.querySelector<HTMLTextAreaElement>('[data-open-text]');
        if (textarea && textarea.value.trim() !== '') state.answers[question.id] = textarea.value;
        break;
      }
    }
    saveDraft();
  }

  function bind(question: QuizQuestion): void {
    el.querySelectorAll<HTMLButtonElement>('.answer-tf').forEach((button) => {
      button.addEventListener('click', () => {
        state.answers[question.id] = button.dataset.answer ?? null;
        saveDraft();
        render();
      });
    });

    el.querySelectorAll<HTMLButtonElement>('.answer-choice').forEach((button) => {
      button.addEventListener('click', () => {
        const choice = button.dataset.choice ?? '';
        const isMultiple = button.dataset.multiple === 'true';
        if (isMultiple) {
          const current = Array.isArray(state.answers[question.id])
            ? [...(state.answers[question.id] as string[])]
            : [];
          state.answers[question.id] = current.includes(choice)
            ? current.filter((c) => c !== choice)
            : [...current, choice];
        } else {
          state.answers[question.id] = choice;
        }
        saveDraft();
        render();
      });
    });

    el.querySelectorAll<HTMLButtonElement>('[data-move]').forEach((button) => {
      button.addEventListener('click', () => {
        const idx = Number(button.dataset.idx);
        const list = state.shuffles[question.id] ?? [];
        const target = button.dataset.move === 'up' ? idx - 1 : idx + 1;
        if (target < 0 || target >= list.length) return;
        [list[idx], list[target]] = [list[target] as string, list[idx] as string];
        state.answers[question.id] = [...list];
        saveDraft();
        render();
      });
    });

    const orderingList = el.querySelector<HTMLElement>('[data-ordering-list]');
    if (orderingList) {
      makeListDraggable(orderingList, {
        handle: '.drag-handle',
        onCommit: (orderedKeys) => {
          state.shuffles[question.id] = orderedKeys;
          state.answers[question.id] = [...orderedKeys];
          saveDraft();
          render(); // rafraîchit numéros + état des flèches
        },
      });
    }

    el.querySelector('#btn-prev')?.addEventListener('click', () => {
      collectCurrent(question);
      state.index--;
      render();
    });

    el.querySelector('#btn-next')?.addEventListener('click', () => {
      collectCurrent(question);
      state.index++;
      render();
    });

    el.querySelector('#btn-finish')?.addEventListener('click', () => {
      collectCurrent(question);
      void finish();
    });

    el.querySelector('#btn-quit')?.addEventListener('click', () => {
      void (async () => {
        const ok = await dangerDialog(
          'Quitter le quiz ?',
          state.practice
            ? 'Cette session d\'entraînement sera abandonnée.'
            : 'Votre progression est enregistrée sur cet appareil : vous pourrez reprendre plus tard.',
          'Quitter',
        );
        if (ok) {
          cleanup();
          history.back();
        }
      })();
    });
  }

  // ----------------------------------------------------------- Résultat

  async function finish(): Promise<void> {
    cleanup();

    const result = scoreQuiz(state.quiz, state.answers);
    const xpEarned = state.practice ? 0 : xpForQuizCompletion(result.passed, result.scoredPoints);

    // Mémorise / résout les erreurs pour le mode « Rejouer mes erreurs ».
    for (const question of state.questions) {
      const score = result.perQuestion[question.id];
      if (score && !score.isCorrect) {
        await db.mistakes.put({
          question_id: question.id,
          quiz_id: state.practice ? (findQuizIdOf(question.id) ?? state.quiz.id) : state.quiz.id,
          last_wrong_at: new Date().toISOString(),
        });
      } else {
        await db.mistakes.delete(question.id);
      }
    }

    if (result.passed) playSuccess();
    else playWrong();

    if (!state.practice) {
      await db.drafts.delete(state.quiz.id);

      // Journalise la tentative (rejouée vers le serveur, qui re-note).
      void dispatch('quiz_attempt', {
        quiz_id: state.quiz.id,
        answers: state.answers,
        started_at: state.startedAt.toISOString(),
        completed_at: new Date().toISOString(),
      });

      // Mise à jour optimiste locale (statut, tentative, XP).
      const attemptNumber = state.quiz.attempts.length + 1;
      const updatedQuiz: QuizItem = {
        ...state.quiz,
        status: 'completed',
        max_attempts_reached:
          !!state.quiz.max_attempts &&
          state.quiz.attempts.filter((a) => a.status === 'completed').length + 1 >= state.quiz.max_attempts,
        attempts: [
          ...state.quiz.attempts,
          {
            id: -attemptNumber, // id local provisoire, remplacé au prochain delta
            attempt_number: attemptNumber,
            status: 'completed',
            score: result.scorePercent,
            points_earned: result.scoredPoints,
            points_total: result.totalPoints,
            passed: result.passed,
            completed_at: new Date().toISOString(),
          },
        ],
      };
      quizzesStore.set(quizzesStore.get().map((q) => (q.id === updatedQuiz.id ? updatedQuiz : q)));
      void db.quizzes.put(updatedQuiz);

      const user = sessionStore.get();
      if (user) {
        sessionStore.set({ ...user, xp: { ...user.xp, total_xp: user.xp.total_xp + xpEarned } });
      }
    }

    renderResult(result, xpEarned);
  }

  function renderResult(result: ReturnType<typeof scoreQuiz>, xpEarned: number): void {
    const showCorrections = state.quiz.show_correct_answers;

    // Historique (avant cette tentative) pour la comparaison.
    const previous = state.quiz.attempts
      .filter((a) => a.status === 'completed' && a.id > 0)
      .map((a) => a.score ?? 0);
    const bestPrevious = previous.length ? Math.max(...previous) : null;
    const delta = bestPrevious !== null ? Math.round((result.scorePercent - bestPrevious) * 100) / 100 : null;

    const historyBars = [...previous.slice(-7), result.scorePercent];

    el.innerHTML = html`
      <div class="max-w-xl mx-auto flex flex-col gap-5 text-center py-6">
        <div class="text-6xl">${result.passed ? '🎉' : '😕'}</div>
        <h2 class="text-2xl font-extrabold">
          ${state.practice
            ? 'Entraînement terminé'
            : result.passed
              ? 'Quiz réussi !'
              : 'Quiz terminé'}
        </h2>

        <div class="rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-6 flex flex-col gap-4">
          <p class="text-5xl font-extrabold tabular-nums ${result.passed ? 'text-emerald-500' : 'text-red-500'}">
            ${result.scorePercent} %
          </p>
          <p class="text-sm text-zinc-500">${result.scoredPoints} / ${result.totalPoints} points · seuil de réussite ${state.quiz.passing_score} %</p>
          ${!state.practice
            ? raw(
                `<p class="inline-flex items-center justify-center gap-2 text-sm font-bold text-amber-600 dark:text-amber-400"><i class="bi bi-lightning-charge-fill"></i> +${xpEarned} XP</p>`,
              )
            : ''}
          ${delta !== null
            ? raw(
                `<p class="text-xs font-semibold ${delta > 0 ? 'text-emerald-600 dark:text-emerald-400' : delta < 0 ? 'text-red-500' : 'text-zinc-500'}">
                   ${delta > 0 ? `▲ +${delta} pts vs votre meilleur score` : delta < 0 ? `▼ ${delta} pts vs votre meilleur score (${bestPrevious} %)` : '= égal à votre meilleur score'}
                 </p>`,
              )
            : ''}
          ${historyBars.length > 1
            ? raw(html`
                <div class="pt-1">
                  <p class="text-[10px] font-bold uppercase tracking-wide text-zinc-400 mb-1.5">Vos tentatives</p>
                  <div class="flex items-end justify-center gap-1.5 h-14" role="img" aria-label="Historique des scores">
                    ${raw(
                      historyBars
                        .map(
                          (score, i) =>
                            `<div class="w-6 rounded-t-md ${i === historyBars.length - 1 ? 'bg-sky-500' : 'bg-zinc-200 dark:bg-zinc-700'}"
                                  style="height:${Math.max(8, score)}%" title="${score} %"></div>`,
                        )
                        .join(''),
                    )}
                  </div>
                </div>
              `)
            : ''}
        </div>

        ${showCorrections
          ? raw(html`
              <details class="text-left rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-4">
                <summary class="font-bold text-sm cursor-pointer">Voir le détail des réponses</summary>
                <div class="mt-3 flex flex-col gap-2">
                  ${raw(
                    state.questions
                      .map((question, idx) => {
                        const score = result.perQuestion[question.id];
                        return `<div class="flex items-start gap-2 text-sm py-2 border-t border-zinc-100 dark:border-zinc-800">
                                  <i class="bi ${score?.isCorrect ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-red-500'} mt-0.5"></i>
                                  <div class="min-w-0">
                                    <div class="rich-content font-medium">${idx + 1}. ${question.question_text}</div>
                                    <p class="text-xs text-zinc-500 mt-0.5">${score?.earned ?? 0}/${question.points} pts</p>
                                  </div>
                                </div>`;
                      })
                      .join(''),
                  )}
                </div>
              </details>
            `)
          : ''}

        <div class="flex gap-3 justify-center flex-wrap">
          ${!state.practice && result.passed
            ? raw('<button id="btn-share" class="rounded-xl border border-sky-300 dark:border-sky-500/40 text-sky-600 dark:text-sky-400 px-5 py-2.5 text-sm font-bold"><i class="bi bi-share"></i> Partager</button>')
            : ''}
          ${state.practice
            ? raw('<a data-link href="/quizzes/erreurs/play" class="rounded-xl border border-zinc-300 dark:border-zinc-700 px-5 py-2.5 text-sm font-bold">Recommencer</a>')
            : raw(`<a data-link href="/quizzes/${state.quiz.id}" class="rounded-xl border border-zinc-300 dark:border-zinc-700 px-5 py-2.5 text-sm font-bold">Détails</a>`)}
          <a data-link href="/entrainement" class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white px-5 py-2.5 text-sm font-bold glow-sky">Retour à l'entraînement</a>
        </div>
      </div>
    `;

    el.querySelector('#btn-share')?.addEventListener('click', () => {
      void shareResult(state.quiz.title, result.scorePercent, xpEarned);
    });
  }

  render();
}

// ----------------------------------------------------------- Utilitaires

/** Quiz synthétique construit à partir des questions ratées récemment. */
async function buildMistakesQuiz(): Promise<QuizItem | undefined> {
  const mistakes = await db.mistakes.orderBy('question_id').toArray();
  if (!mistakes.length) return undefined;

  const allQuestions = new Map(
    quizzesStore.get().flatMap((quiz) => quiz.questions.map((q) => [q.id, q] as const)),
  );
  const questions = mistakes
    .map((m) => allQuestions.get(m.question_id))
    .filter((q): q is QuizQuestion => q !== undefined);

  if (!questions.length) return undefined;

  return {
    id: -1,
    title: 'Rejouer mes erreurs',
    description: null,
    duration: null,
    passing_score: 60,
    max_attempts: 0,
    shuffle_questions: true,
    show_correct_answers: true,
    status: 'unread',
    max_attempts_reached: false,
    updated_at: new Date().toISOString(),
    attempts: [],
    questions,
  };
}

function findQuizIdOf(questionId: number): number | undefined {
  return quizzesStore.get().find((quiz) => quiz.questions.some((q) => q.id === questionId))?.id;
}

async function shareResult(title: string, score: number, xp: number): Promise<void> {
  const text = `J'ai obtenu ${score} % au quiz « ${title} » sur Learn&Quiz ! 🎓 (+${xp} XP)`;

  if (navigator.share) {
    try {
      await navigator.share({ title: 'Learn&Quiz', text });
      return;
    } catch {
      /* partage annulé */
    }
  }

  try {
    await navigator.clipboard.writeText(text);
    toast('Résultat copié dans le presse-papiers !', 'success');
  } catch {
    toast(text, 'info', 6000);
  }
}
