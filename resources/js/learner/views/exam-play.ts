import { api, ApiError, NetworkError } from '../api/client';
import { escapeHtml, html, raw } from '../core/html';
import type { ExamStartResponse, QuizQuestion } from '../domain/types';
import { examsStore, syncStore } from '../stores';
import { sync } from '../sync/engine';
import { alertDialog, dangerDialog } from '../ui/app-dialog';
import { makeListDraggable } from '../ui/drag-list';
import { toast } from '../ui/app-toast';
import { formatDuration } from './helpers';

/**
 * Passage d'examen sécurisé — online-only par conception.
 *
 * Anti-fraude (déclaratif client, décision serveur) :
 * - visibilitychange / blur      → violation `navigation`
 * - PrintScreen / Ctrl+P         → violation `screenshot` + voile blanc
 * - clic droit                   → bloqué (client uniquement)
 * - plein_ecran_force            → Fullscreen API à l'entrée
 * - Wake Lock                    → l'écran ne se verrouille pas pendant l'épreuve
 */

type ExamAnswers = Record<number, unknown>;

export function mount(el: HTMLElement, params: Record<string, string>): void {
  const examId = Number(params.id);
  const exam = examsStore.get().find((e) => e.id === examId);

  if (!exam) {
    el.innerHTML = '<p class="text-zinc-500 py-10 text-center">Examen introuvable.</p>';
    return;
  }

  if (!syncStore.get().online) {
    el.innerHTML = html`
      <div class="max-w-md mx-auto text-center py-16 flex flex-col gap-4">
        <div class="text-5xl">📡</div>
        <h2 class="text-xl font-extrabold">Connexion requise</h2>
        <p class="text-sm text-zinc-500">
          Les examens surveillés ne peuvent être passés qu'en ligne, pour garantir l'intégrité de l'épreuve.
        </p>
        <a data-link href="/evaluations?tab=exams" class="rounded-xl bg-sky-600 text-white font-bold px-5 py-2.5 text-sm mx-auto">Retour</a>
      </div>
    `;
    return;
  }

  // Écran de consignes avant démarrage.
  el.innerHTML = html`
    <div class="max-w-lg mx-auto flex flex-col gap-5 py-6">
      <h2 class="text-xl font-extrabold text-center">${exam.title}</h2>
      <div class="rounded-2xl border-2 border-amber-300 dark:border-amber-500/40 bg-amber-50 dark:bg-amber-500/10 p-5 flex flex-col gap-3 text-sm">
        <p class="font-bold text-amber-700 dark:text-amber-400"><i class="bi bi-shield-exclamation"></i> Examen surveillé — règles anti-triche</p>
        <ul class="flex flex-col gap-2 text-amber-800 dark:text-amber-200">
          ${exam.plein_ecran_force ? raw('<li><i class="bi bi-fullscreen mr-1"></i> Le plein écran sera activé automatiquement.</li>') : ''}
          ${exam.anti_capture_strict
            ? raw("<li><i class='bi bi-camera-video-off mr-1'></i> Toute capture d'écran <b>annule immédiatement</b> l'examen.</li>")
            : ''}
          ${exam.navigation_interdite
            ? raw("<li><i class='bi bi-window-x mr-1'></i> 3 changements d'onglet ou sorties de fenêtre <b>annulent</b> l'examen.</li>")
            : ''}
          <li><i class="bi bi-clock mr-1"></i> Durée : <b>${exam.duration} minutes</b> — soumission automatique à la fin du temps.</li>
        </ul>
      </div>
      <button id="btn-start" class="rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold py-3">
        J'ai compris, commencer
      </button>
      <a data-link href="/evaluations?tab=exams" class="text-center text-sm text-zinc-500">Annuler</a>
    </div>
  `;

  el.querySelector('#btn-start')?.addEventListener('click', () => {
    void begin();
  });

  async function begin(): Promise<void> {
    let session: ExamStartResponse;
    try {
      session = await api.startExam(examId);
    } catch (e) {
      if (e instanceof NetworkError) {
        toast('Impossible de joindre le serveur.', 'error');
      } else if (e instanceof ApiError) {
        void alertDialog('Examen indisponible', e.message);
      }
      return;
    }

    runExam(session);
  }

  function runExam(session: ExamStartResponse): void {
    const answers: ExamAnswers = {};
    let index = 0;
    let remaining = session.remaining_seconds;
    let finished = false;
    let timerId: number | null = null;
    let wakeLock: WakeLockSentinel | null = null;
    let saveTimer: number | null = null;

    // ------------------------------------------------- Anti-fraude client

    const reportViolation = async (type: 'screenshot' | 'navigation'): Promise<void> => {
      if (finished) return;
      try {
        const result = await api.reportExamViolation(examId, session.attempt_id, type);
        if (result.cancelled) {
          finished = true;
          teardown();
          await alertDialog(
            'Examen annulé',
            result.message ?? "L'examen a été annulé pour violation des règles de sécurité.",
          );
          void sync();
          window.location.href = '/evaluations?tab=exams';
        } else if (type === 'navigation') {
          toast(`⚠️ Sortie détectée (${result.violations_count}/3)`, 'warning');
        }
      } catch {
        /* réseau : la violation sera re-signalée à la prochaine occurrence */
      }
    };

    const onVisibility = (): void => {
      if (document.hidden || !document.hasFocus()) void reportViolation('navigation');
    };

    const onKeydown = (event: KeyboardEvent): void => {
      const isPrintScreen = event.key === 'PrintScreen' || event.keyCode === 44;
      const isPrint = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'p';
      if (isPrintScreen || isPrint) {
        event.preventDefault();
        flashWhiteOverlay();
        void reportViolation('screenshot');
      }
    };

    const onContextMenu = (event: Event): void => {
      event.preventDefault();
      toast("Clic droit désactivé pendant l'examen.", 'warning');
    };

    const flashWhiteOverlay = (): void => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 bg-white z-[999]';
      document.body.append(overlay);
      window.setTimeout(() => overlay.remove(), 2000);
    };

    const teardown = (): void => {
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('blur', onVisibility);
      document.removeEventListener('keydown', onKeydown, true);
      document.removeEventListener('contextmenu', onContextMenu, true);
      if (timerId) window.clearInterval(timerId);
      if (saveTimer) window.clearTimeout(saveTimer);
      void wakeLock?.release().catch(() => undefined);
      if (document.fullscreenElement) void document.exitFullscreen().catch(() => undefined);
    };

    if (session.navigation_interdite) {
      document.addEventListener('visibilitychange', onVisibility);
      window.addEventListener('blur', onVisibility);
    }
    document.addEventListener('keydown', onKeydown, true);
    document.addEventListener('contextmenu', onContextMenu, true);

    if (session.plein_ecran_force) {
      void document.documentElement.requestFullscreen?.().catch(() => undefined);
    }
    void navigator.wakeLock?.request('screen').then(
      (lock) => {
        wakeLock = lock;
      },
      () => undefined,
    );

    // Sécurité : démontage si l'utilisateur navigue ailleurs.
    const observer = new MutationObserver(() => {
      if (!document.body.contains(el.firstElementChild as Node)) {
        teardown();
        observer.disconnect();
      }
    });
    observer.observe(el, { childList: true });

    // ------------------------------------------------------------ Timer

    timerId = window.setInterval(() => {
      remaining--;
      const timerEl = el.querySelector('#exam-timer');
      if (timerEl) {
        timerEl.textContent = formatDuration(Math.max(0, remaining));
        timerEl.classList.toggle('text-red-500', remaining <= 120);
      }
      if (remaining <= 0 && !finished) {
        void complete(true);
      }
    }, 1000);

    // ----------------------------------------------------------- Autosave

    const scheduleSave = (): void => {
      if (saveTimer) window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(() => {
        void api
          .saveExamAnswers(examId, session.attempt_id, answers as Record<string, unknown>)
          .catch(() => undefined);
      }, 1500);
    };

    // -------------------------------------------------------------- Rendu

    function questionBody(question: QuizQuestion): string {
      const options = question.options as Record<string, any>;
      const saved = answers[question.id];

      switch (question.type) {
        case 'true_false':
          return html`
            <div class="grid grid-cols-2 gap-3">
              ${raw(
                (['true', 'false'] as const)
                  .map(
                    (value) => `
                      <button data-tf="${value}" class="rounded-2xl border-2 py-5 font-bold ${
                        saved === value
                          ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/10'
                          : 'border-zinc-200 dark:border-zinc-700'
                      }">${value === 'true' ? 'Vrai' : 'Faux'}</button>`,
                  )
                  .join(''),
              )}
            </div>
          `;

        case 'mcq': {
          const choices: Array<{ text: string }> = options.choices ?? [];
          const selected = Array.isArray(saved) ? (saved as string[]) : [];
          return html`
            <div class="flex flex-col gap-2">
              ${raw(
                choices
                  .map(
                    (c) => `
                      <button data-mcq="${escapeHtml(c.text)}" class="text-left rounded-xl border-2 px-4 py-3 text-sm font-semibold flex items-center gap-3 ${
                        selected.includes(c.text)
                          ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/10'
                          : 'border-zinc-200 dark:border-zinc-700'
                      }">
                        <i class="bi ${selected.includes(c.text) ? 'bi-check-square-fill text-amber-600' : 'bi-square'}"></i>
                        <span>${escapeHtml(c.text)}</span>
                      </button>`,
                  )
                  .join(''),
              )}
            </div>
          `;
        }

        case 'fill_blank':
          return html`
            <input data-fill type="text" value="${typeof saved === 'string' ? saved : ''}"
                   class="w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-sm"
                   placeholder="Votre réponse…" />
          `;

        case 'matching': {
          const lefts: string[] = options.lefts ?? [];
          const rights: string[] = options.rights ?? [];
          const savedDict = (saved ?? {}) as Record<string, string>;
          return html`
            <div class="flex flex-col gap-3">
              ${raw(
                lefts
                  .map(
                    (left) => `
                      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <span class="sm:w-1/2 text-sm font-bold rounded-xl bg-zinc-100 dark:bg-zinc-800 px-4 py-3">${escapeHtml(left)}</span>
                        <select data-match-left="${escapeHtml(left)}" class="sm:w-1/2 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-3 text-sm">
                          <option value="">— Choisir —</option>
                          ${rights.map((r) => `<option value="${escapeHtml(r)}" ${savedDict[left] === r ? 'selected' : ''}>${escapeHtml(r)}</option>`).join('')}
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
          const current = Array.isArray(saved) && saved.length ? (saved as string[]) : items;
          return html`
            <p class="text-xs text-zinc-500 mb-2"><i class="bi bi-info-circle"></i> Glissez les éléments (ou utilisez les flèches)</p>
            <ol data-ordering-list class="flex flex-col gap-2">
              ${raw(
                current
                  .map(
                    (item, idx) => `
                      <li data-key="${escapeHtml(item)}" class="flex items-center gap-2 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 py-2.5">
                        <span class="drag-handle w-11 h-11 shrink-0 rounded-lg flex items-center justify-center text-zinc-400" aria-hidden="true"><i class="bi bi-grip-vertical text-lg"></i></span>
                        <span class="w-6 h-6 shrink-0 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-600 text-xs font-bold flex items-center justify-center">${idx + 1}</span>
                        <span class="flex-1 text-sm font-semibold">${escapeHtml(item)}</span>
                        <button data-ord="up" data-idx="${idx}" ${idx === 0 ? 'disabled' : ''} class="w-11 h-11 shrink-0 rounded-lg flex items-center justify-center disabled:opacity-30" aria-label="Monter"><i class="bi bi-chevron-up text-lg"></i></button>
                        <button data-ord="down" data-idx="${idx}" ${idx === current.length - 1 ? 'disabled' : ''} class="w-11 h-11 shrink-0 rounded-lg flex items-center justify-center disabled:opacity-30" aria-label="Descendre"><i class="bi bi-chevron-down text-lg"></i></button>
                      </li>`,
                  )
                  .join(''),
              )}
            </ol>
          `;
        }

        case 'open_text':
          return html`
            <textarea data-open rows="5" class="w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-sm"
                      placeholder="Rédigez votre réponse…">${typeof saved === 'string' ? saved : ''}</textarea>
          `;

        default:
          return '<p class="text-sm text-zinc-500">Type non pris en charge.</p>';
      }
    }

    function render(): void {
      const question = session.questions[index];
      if (!question) return;
      const total = session.questions.length;

      el.innerHTML = html`
        <div class="max-w-2xl mx-auto flex flex-col gap-4" style="user-select:none">
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400">
              <i class="bi bi-shield-lock-fill"></i> Surveillé
            </span>
            <div class="flex-1 h-2 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
              <div class="h-full bg-amber-500 rounded-full transition-all" style="width:${Math.round(((index + 1) / total) * 100)}%"></div>
            </div>
            <span id="exam-timer" class="text-sm font-bold tabular-nums ${remaining <= 120 ? 'text-red-500' : ''}">${formatDuration(remaining)}</span>
          </div>

          <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-4">
            <div class="flex items-center justify-between text-xs font-bold text-zinc-500">
              <span>Question ${index + 1} / ${total}</span>
              <span>${question.points} pt${question.points > 1 ? 's' : ''}</span>
            </div>
            <div class="rich-content font-semibold text-[15px]">${raw(question.question_text)}</div>
            <div>${raw(questionBody(question))}</div>
          </div>

          <div class="flex items-center gap-3">
            <button id="btn-prev" ${index === 0 ? 'disabled' : ''}
                    class="rounded-xl border border-zinc-300 dark:border-zinc-700 px-5 py-2.5 text-sm font-bold disabled:opacity-40">
              <i class="bi bi-arrow-left"></i>
            </button>
            <span class="flex-1 text-center text-xs text-zinc-500">${Object.keys(answers).length}/${total} répondues</span>
            ${index === total - 1
              ? raw('<button id="btn-submit" class="rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2.5 text-sm font-bold">Soumettre</button>')
              : raw('<button id="btn-next" class="rounded-xl bg-amber-600 hover:bg-amber-500 text-white px-5 py-2.5 text-sm font-bold"><i class="bi bi-arrow-right"></i></button>')}
          </div>
        </div>
      `;

      bind(question);
    }

    function collect(question: QuizQuestion): void {
      switch (question.type) {
        case 'fill_blank': {
          const input = el.querySelector<HTMLInputElement>('[data-fill]');
          if (input && input.value.trim()) answers[question.id] = input.value;
          break;
        }
        case 'matching': {
          const dict: Record<string, string> = {};
          el.querySelectorAll<HTMLSelectElement>('[data-match-left]').forEach((select) => {
            if (select.value) dict[select.dataset.matchLeft ?? ''] = select.value;
          });
          if (Object.keys(dict).length) answers[question.id] = dict;
          break;
        }
        case 'open_text': {
          const textarea = el.querySelector<HTMLTextAreaElement>('[data-open]');
          if (textarea && textarea.value.trim()) answers[question.id] = textarea.value;
          break;
        }
      }
      scheduleSave();
    }

    function bind(question: QuizQuestion): void {
      el.querySelectorAll<HTMLButtonElement>('[data-tf]').forEach((button) => {
        button.addEventListener('click', () => {
          answers[question.id] = button.dataset.tf;
          scheduleSave();
          render();
        });
      });

      el.querySelectorAll<HTMLButtonElement>('[data-mcq]').forEach((button) => {
        button.addEventListener('click', () => {
          const choice = button.dataset.mcq ?? '';
          const current = Array.isArray(answers[question.id]) ? [...(answers[question.id] as string[])] : [];
          answers[question.id] = current.includes(choice)
            ? current.filter((c) => c !== choice)
            : [...current, choice];
          scheduleSave();
          render();
        });
      });

      const orderingList = el.querySelector<HTMLElement>('[data-ordering-list]');
      if (orderingList) {
        makeListDraggable(orderingList, {
          handle: '.drag-handle',
          onCommit: (orderedKeys) => {
            answers[question.id] = orderedKeys;
            scheduleSave();
            render();
          },
        });
      }

      el.querySelectorAll<HTMLButtonElement>('[data-ord]').forEach((button) => {
        button.addEventListener('click', () => {
          const options = question.options as Record<string, any>;
          const idx = Number(button.dataset.idx);
          const current: string[] = Array.isArray(answers[question.id])
            ? [...(answers[question.id] as string[])]
            : [...(options.items ?? [])];
          const target = button.dataset.ord === 'up' ? idx - 1 : idx + 1;
          if (target < 0 || target >= current.length) return;
          [current[idx], current[target]] = [current[target] as string, current[idx] as string];
          answers[question.id] = current;
          scheduleSave();
          render();
        });
      });

      el.querySelector('#btn-prev')?.addEventListener('click', () => {
        collect(question);
        index--;
        render();
      });
      el.querySelector('#btn-next')?.addEventListener('click', () => {
        collect(question);
        index++;
        render();
      });
      el.querySelector('#btn-submit')?.addEventListener('click', () => {
        collect(question);
        void (async () => {
          const unanswered = session.questions.length - Object.keys(answers).length;
          const ok = await dangerDialog(
            "Soumettre l'examen ?",
            unanswered > 0
              ? `${unanswered} question(s) sans réponse. Cette action est définitive.`
              : 'Cette action est définitive.',
            'Soumettre',
          );
          if (ok) void complete(false);
        })();
      });
    }

    // ------------------------------------------------------------ Fin

    async function complete(timeUp: boolean): Promise<void> {
      if (finished) return;
      finished = true;
      teardown();

      try {
        const result = await api.completeExam(examId, session.attempt_id, answers as Record<string, unknown>);
        void sync();

        const showRank = exam!.classement_visible && result.rank !== null;

        el.innerHTML = html`
          <div class="max-w-xl mx-auto flex flex-col gap-5 text-center py-6">
            <div class="text-6xl">${result.passed ? '🎓' : '📋'}</div>
            <h2 class="text-2xl font-extrabold">
              ${timeUp ? 'Temps écoulé !' : result.passed ? 'Examen réussi !' : 'Examen terminé'}
            </h2>
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-3">
              <p class="text-5xl font-extrabold tabular-nums ${result.passed ? 'text-emerald-500' : 'text-red-500'}">
                ${result.note_sur_vingt}<span class="text-xl text-zinc-400">/${exam!.note_max}</span>
              </p>
              <p class="text-sm text-zinc-500">${result.score_brut}/${result.score_total} points · ${result.pourcentage} %</p>
              ${result.passed
                ? raw('<p class="text-sm font-bold text-amber-600 dark:text-amber-400"><i class="bi bi-lightning-charge-fill"></i> +50 XP</p>')
                : ''}
              ${showRank
                ? raw(
                    `<p class="text-sm font-semibold text-sky-600 dark:text-sky-400"><i class="bi bi-trophy"></i> ${result.rank}ᵉ sur ${result.total_participants} participant(s)</p>`,
                  )
                : ''}
            </div>
            <a data-link href="/evaluations?tab=exams" class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white px-5 py-3 text-sm font-bold mx-auto">
              Retour aux évaluations
            </a>
          </div>
        `;
      } catch (e) {
        el.innerHTML = html`
          <div class="max-w-md mx-auto text-center py-16 flex flex-col gap-4">
            <div class="text-5xl">⚠️</div>
            <p class="font-bold">La soumission a échoué.</p>
            <p class="text-sm text-zinc-500">${e instanceof ApiError ? e.message : 'Vérifiez votre connexion puis réessayez.'}</p>
            <a data-link href="/evaluations?tab=exams" class="rounded-xl bg-sky-600 text-white font-bold px-5 py-2.5 text-sm mx-auto">Retour</a>
          </div>
        `;
      }
    }

    render();
  }
}
