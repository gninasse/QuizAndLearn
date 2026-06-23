@extends('core::layouts.learner')

@section('title', 'Quiz en cours | ' . $quiz->title)

@section('content')
<!-- Fullscreen Cheat Overlay -->
<div id="cheat-overlay" class="hidden fixed inset-0 bg-white dark:bg-zinc-950 flex flex-col items-center justify-center z-[999] p-8 text-center">
    <div class="w-20 h-20 rounded-full bg-red-100 dark:bg-red-950/30 flex items-center justify-center text-red-600 mb-6">
        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </div>
    <h2 class="text-2xl font-bold text-red-600 mb-2">Activité suspecte détectée</h2>
    <p class="text-sm text-slate-500 dark:text-zinc-400 max-w-md">La capture d'écran ou le changement d'onglet est interdit pendant l'examen. Cet incident a été consigné et envoyé à vos formateurs.</p>
</div>

<div class="flex flex-col lg:flex-row gap-8 max-w-5xl mx-auto h-full" id="quiz-container">
    
    <!-- Left Navigation Sidebar / State -->
    <div class="w-full lg:w-72 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 p-6 rounded-3xl shadow-sm flex flex-col justify-between shrink-0 h-fit space-y-6">
        <div>
            <!-- Quiz Meta -->
            <div class="pb-4 border-b border-slate-50 dark:border-zinc-800/80">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Quiz en cours</span>
                <h2 class="font-bold text-base text-slate-900 dark:text-zinc-50 truncate">{{ $quiz->title }}</h2>
            </div>

            <!-- Chronomètre -->
            @if($quiz->duration)
                <div class="py-4 border-b border-slate-50 dark:border-zinc-800/80">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Temps restant</p>
                    <div id="timer-box" class="flex items-center gap-3 px-4 py-3 bg-sky-500/10 text-sky-600 dark:text-sky-400 rounded-2xl transition-all duration-300">
                        <svg class="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span id="chronometer" class="text-lg font-bold">--:--</span>
                    </div>
                </div>
            @endif

            <!-- Question Grid Nav -->
            <div class="py-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-3">Questions</p>
                <div class="grid grid-cols-5 gap-2" id="nav-grid">
                    @foreach($quiz->questions as $index => $q)
                        <button class="nav-dot w-full aspect-square text-xs font-bold rounded-xl border border-slate-200 dark:border-zinc-800 flex items-center justify-center transition-all" data-q-index="{{ $index }}" id="nav-dot-{{ $q->id }}">
                            {{ $index + 1 }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-50 dark:border-zinc-800/80 flex flex-col gap-2">
            <button id="btn-report-error" class="w-full flex items-center justify-center gap-2 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 font-semibold text-xs rounded-2xl transition-all">
                ⚠️ Signaler une anomalie
            </button>
            <button id="btn-submit-exam" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-indigo-500 hover:opacity-95 text-white font-bold text-xs rounded-2xl shadow-md shadow-sky-500/10 transition-all">
                Terminer le quiz
            </button>
        </div>
    </div>

    <!-- Active Question card -->
    <div class="flex-1 flex flex-col space-y-6">
        @foreach($quiz->questions as $index => $q)
            @php
                $savedAns = $answers->get($q->id)?->answer_given;
                if ($savedAns !== null) {
                    $decoded = json_decode($savedAns, true);
                    if ($decoded !== null) {
                        $savedAns = $decoded;
                    }
                }
                $options = $q->options ?: [];
            @endphp
            <div class="question-page hidden bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 p-8 rounded-3xl shadow-sm space-y-6 flex-1 flex flex-col justify-between" data-q-index="{{ $index }}" data-q-id="{{ $q->id }}" data-q-type="{{ $q->type }}">
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <span class="px-3 py-1 bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 rounded-full text-xs font-semibold">Question {{ $index + 1 }} sur {{ $quiz->questions_count }}</span>
                        <span class="text-xs font-bold text-sky-500">{{ $q->points }} {{ $q->points > 1 ? 'points' : 'point' }}</span>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-50 leading-relaxed">{{ $q->question_text }}</h3>

                    <!-- Render Question options depending on type -->
                    <div class="answer-container pt-4">
                        @if($q->type === 'true_false')
                            <div class="grid grid-cols-2 gap-4">
                                <label class="flex flex-col items-center justify-center p-6 border-2 border-slate-100 dark:border-zinc-800 rounded-3xl cursor-pointer hover:bg-slate-50 dark:hover:bg-zinc-800/40 transition-all option-label" data-value="true">
                                    <input type="radio" name="ans-{{ $q->id }}" value="true" class="hidden" {{ $savedAns === 'true' || $savedAns === true ? 'checked' : '' }}>
                                    <span class="text-3xl mb-2">👍</span>
                                    <span class="font-bold text-sm">Vrai</span>
                                </label>
                                <label class="flex flex-col items-center justify-center p-6 border-2 border-slate-100 dark:border-zinc-800 rounded-3xl cursor-pointer hover:bg-slate-50 dark:hover:bg-zinc-800/40 transition-all option-label" data-value="false">
                                    <input type="radio" name="ans-{{ $q->id }}" value="false" class="hidden" {{ $savedAns === 'false' || $savedAns === false ? 'checked' : '' }}>
                                    <span class="text-3xl mb-2">👎</span>
                                    <span class="font-bold text-sm">Faux</span>
                                </label>
                            </div>

                        @elseif($q->type === 'mcq' || $q->type === 'single_choice' || $q->type === 'multiple_choice')
                            @php
                                $isMultiple = ($options['multiple'] ?? false) || ($q->type === 'multiple_choice');
                                $answersList = $options['answers'] ?? [];
                            @endphp
                            <div class="space-y-3">
                                @foreach($answersList as $ansIndex => $ans)
                                    @php
                                        $isChecked = false;
                                        if (is_array($savedAns)) {
                                            $isChecked = in_array($ans['text'], $savedAns);
                                        } else {
                                            $isChecked = ($savedAns === $ans['text']);
                                        }
                                    @endphp
                                    <label class="flex items-center gap-4 p-4 border border-slate-150 dark:border-zinc-800 rounded-2xl cursor-pointer hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition-all option-label" data-value="{{ $ans['text'] }}">
                                        <input type="{{ $isMultiple ? 'checkbox' : 'radio' }}" name="ans-{{ $q->id }}[]" value="{{ $ans['text'] }}" class="w-5 h-5 accent-sky-500 rounded" {{ $isChecked ? 'checked' : '' }}>
                                        <span class="text-sm text-slate-800 dark:text-zinc-100">{{ $ans['text'] }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif($q->type === 'fill_blank')
                            @php
                                $blanks = $options['blanks'] ?? [];
                            @endphp
                            <div class="space-y-4">
                                @foreach($blanks as $bIdx => $blank)
                                    @php
                                        $bVal = is_array($savedAns) ? ($savedAns[$bIdx] ?? '') : '';
                                    @endphp
                                    <div class="flex flex-col gap-2">
                                        <label class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Champ #{{ $bIdx + 1 }}</label>
                                        <input type="text" name="ans-{{ $q->id }}[]" value="{{ $bVal }}" placeholder="Écrivez votre réponse..." class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all font-semibold">
                                    </div>
                                @endforeach
                            </div>

                        @elseif($q->type === 'matching')
                            @php
                                $pairs = $options['pairs'] ?? [];
                                $allDefs = collect($pairs)->pluck('definition')->shuffle();
                                $savedTerms = is_array($savedAns) && isset($savedAns['terms']) ? $savedAns['terms'] : [];
                                $savedDefs = is_array($savedAns) && isset($savedAns['definitions']) ? $savedAns['definitions'] : [];
                            @endphp
                            <div class="space-y-4">
                                <p class="text-xs text-slate-400 mb-2">Associez chaque terme avec sa définition :</p>
                                @foreach($pairs as $pIdx => $pair)
                                    @php
                                        $term = $pair['term'];
                                        $savedDef = '';
                                        $tIdx = array_search($term, $savedTerms);
                                        if ($tIdx !== false) {
                                            $savedDef = $savedDefs[$tIdx] ?? '';
                                        }
                                    @endphp
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 bg-slate-50 dark:bg-zinc-850 border border-slate-100 dark:border-zinc-800/80 rounded-2xl">
                                        <span class="font-bold text-sm text-slate-800 dark:text-zinc-200 sm:w-1/3">{{ $term }}</span>
                                        <input type="hidden" name="ans-{{ $q->id }}-terms[]" value="{{ $term }}">
                                        <select name="ans-{{ $q->id }}-definitions[]" class="flex-1 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                                            <option value="">Sélectionner l'association...</option>
                                            @foreach($allDefs as $def)
                                                <option value="{{ $def }}" {{ $savedDef === $def ? 'selected' : '' }}>{{ $def }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>

                        @elseif($q->type === 'ordering')
                            @php
                                $items = $options['items'] ?? [];
                                $shuffledItems = collect($items)->shuffle()->toArray();
                                if (is_array($savedAns) && count($savedAns) === count($items)) {
                                    $shuffledItems = $savedAns;
                                }
                            @endphp
                            <div class="space-y-3 ordering-list" data-q-id="{{ $q->id }}">
                                <p class="text-xs text-slate-400 mb-2">Ordonnez les éléments suivants (du premier au dernier) :</p>
                                @foreach($shuffledItems as $iIdx => $item)
                                    <div class="drag-item flex items-center justify-between p-4 bg-slate-50 dark:bg-zinc-850 border border-slate-150 dark:border-zinc-800 rounded-2xl cursor-grab active:cursor-grabbing hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors" data-value="{{ $item }}">
                                        <div class="flex items-center gap-3">
                                            <span class="text-slate-400 font-bold text-xs">☰</span>
                                            <span class="text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ $item }}</span>
                                        </div>
                                        <input type="hidden" name="ans-{{ $q->id }}[]" value="{{ $item }}" class="order-input">
                                        <div class="flex gap-1 shrink-0">
                                            <button type="button" class="btn-move-up p-1 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded hover:bg-slate-100 text-xs">▲</button>
                                            <button type="button" class="btn-move-down p-1 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded hover:bg-slate-100 text-xs">▼</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        @elseif($q->type === 'open_text')
                            <textarea name="ans-{{ $q->id }}" placeholder="Écrivez votre réponse ici..." rows="5" class="w-full p-4 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all leading-relaxed font-medium">{{ $savedAns }}</textarea>
                        @endif
                    </div>
                </div>

                <!-- Next/Prev Buttons inside card -->
                <div class="flex justify-between items-center pt-6 border-t border-slate-50 dark:border-zinc-800/80 mt-8 shrink-0">
                    <button class="btn-prev px-6 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-250 font-bold text-xs rounded-2xl disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                        Précédent
                    </button>
                    <button class="btn-next px-6 py-3 bg-sky-500 text-white font-bold text-xs rounded-2xl hover:opacity-95 shadow-md shadow-sky-500/10 transition-all">
                        Suivant
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal Signalement Anomalie -->
<div id="modal-error" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-xl w-full max-w-md p-6 space-y-4 transform scale-95 transition-all">
        <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-50">Signaler une anomalie</h3>
        <form id="form-error-report" class="space-y-4">
            <div>
                <label for="error_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Type d'anomalie</label>
                <select name="error_type" id="error_type" class="w-full bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 font-semibold" required>
                    <option value="spelling">Faute d'orthographe / Syntaxe</option>
                    <option value="wrong_answer">Erreur dans les choix proposés</option>
                    <option value="technical">Problème technique de rendu</option>
                    <option value="other">Autre problème</option>
                </select>
            </div>
            <div>
                <label for="comment" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Détails</label>
                <textarea name="comment" id="comment" placeholder="Décrivez le problème rencontré de manière précise..." rows="4" class="w-full p-4 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 leading-relaxed" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" id="btn-close-modal" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs rounded-2xl">
                    Annuler
                </button>
                <button type="submit" class="flex-1 py-3 bg-sky-500 text-white font-bold text-xs rounded-2xl hover:opacity-95 shadow-md shadow-sky-500/10">
                    Envoyer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Gamification Success Result -->
<div id="modal-result" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-xl w-full max-w-md p-6 space-y-6 text-center transform scale-95 transition-all">
        <div id="result-icon-box" class="w-20 h-20 rounded-full flex items-center justify-center mx-auto text-4xl">
            <!-- Icon sets dynamically -->
        </div>
        <div class="space-y-2">
            <h3 id="result-title" class="font-bold text-xl text-slate-900 dark:text-zinc-50">Score : --%</h3>
            <p id="result-subtitle" class="text-sm text-slate-500 dark:text-zinc-400">Bravo pour vos efforts !</p>
        </div>

        <!-- Awarded Section -->
        <div class="grid grid-cols-2 gap-4 py-4 bg-slate-50 dark:bg-zinc-850 rounded-2xl">
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">XP Gagnés</p>
                <p id="result-xp" class="text-lg font-bold text-sky-500">+-- XP</p>
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Niveau</p>
                <p id="result-level" class="text-lg font-bold text-indigo-500">Niveau --</p>
            </div>
        </div>

        <div id="result-badges-box" class="hidden p-4 border border-dashed border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/20 rounded-2xl space-y-2">
            <p class="text-[10px] uppercase font-bold text-indigo-500 tracking-wider">🏅 Badge débloqué !</p>
            <p id="result-badge-name" class="text-sm font-bold text-indigo-600 dark:text-indigo-400"></p>
        </div>

        <button id="btn-close-result" class="w-full py-3.5 bg-slate-900 dark:bg-zinc-800 text-white font-bold text-xs rounded-2xl hover:opacity-95 shadow-md transition-all">
            Retourner aux quiz
        </button>
    </div>
</div>

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const quizId = "{{ $quiz->id }}";
        const attemptId = "{{ $activeAttempt->id }}";
        const questionsCount = parseInt("{{ $quiz->questions_count }}");
        let activeIdx = 0;
        
        const pages = document.querySelectorAll('.question-page');
        const dots = document.querySelectorAll('.nav-dot');
        
        // Navigation function
        function showQuestion(idx) {
            pages.forEach((p, i) => {
                if (i === idx) {
                    p.classList.remove('hidden');
                } else {
                    p.classList.add('hidden');
                }
            });

            dots.forEach((dot, i) => {
                dot.classList.remove('bg-sky-500', 'text-white', 'border-sky-500', 'bg-emerald-500/10', 'text-emerald-600');
                if (i === idx) {
                    dot.classList.add('bg-sky-500', 'text-white', 'border-sky-500');
                } else {
                    // Check if question has been answered (or selected/input filled)
                    const qId = dot.getAttribute('data-q-index');
                    if (isQuestionAnswered(i)) {
                        dot.classList.add('bg-emerald-500/10', 'text-emerald-600', 'dark:bg-emerald-950/20', 'dark:text-emerald-400');
                    }
                }
            });

            activeIdx = idx;
            
            // Handle prev/next button states
            const page = pages[idx];
            const btnPrev = page.querySelector('.btn-prev');
            const btnNext = page.querySelector('.btn-next');
            
            btnPrev.disabled = (idx === 0);
            if (idx === questionsCount - 1) {
                btnNext.textContent = "Dépôt final";
                btnNext.classList.add('bg-indigo-500');
                btnNext.classList.remove('bg-sky-500');
            } else {
                btnNext.textContent = "Suivant";
                btnNext.classList.add('bg-sky-500');
                btnNext.classList.remove('bg-indigo-500');
            }
        }

        function isQuestionAnswered(idx) {
            const page = pages[idx];
            if (!page) return false;
            
            const qType = page.getAttribute('data-q-type');
            if (qType === 'true_false' || qType === 'mcq' || qType === 'single_choice' || qType === 'multiple_choice') {
                const inputs = page.querySelectorAll('input:checked');
                return inputs.length > 0;
            } else if (qType === 'fill_blank') {
                const inputs = page.querySelectorAll('input[type="text"]');
                return Array.from(inputs).every(i => i.value.trim() !== '');
            } else if (qType === 'open_text') {
                const text = page.querySelector('textarea');
                return text && text.value.trim() !== '';
            } else if (qType === 'matching') {
                const selects = page.querySelectorAll('select');
                return Array.from(selects).every(s => s.value !== '');
            }
            return false;
        }

        // Dot navigation click
        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const idx = parseInt(dot.getAttribute('data-q-index'));
                saveCurrentAnswer();
                showQuestion(idx);
            });
        });

        // Prev/Next handlers
        pages.forEach((page, idx) => {
            const btnPrev = page.querySelector('.btn-prev');
            const btnNext = page.querySelector('.btn-next');
            
            btnPrev.addEventListener('click', () => {
                saveCurrentAnswer();
                showQuestion(idx - 1);
            });
            
            btnNext.addEventListener('click', () => {
                saveCurrentAnswer();
                if (idx === questionsCount - 1) {
                    submitWholeExam();
                } else {
                    showQuestion(idx + 1);
                }
            });
        });

        // Dynamic styling for checked options (true_false and mcq)
        function styleAnswers() {
            pages.forEach(page => {
                const labels = page.querySelectorAll('.option-label');
                labels.forEach(label => {
                    const input = label.querySelector('input');
                    if (input && input.checked) {
                        label.classList.add('border-sky-500', 'bg-sky-500/5', 'dark:bg-sky-950/20');
                    } else {
                        label.classList.remove('border-sky-500', 'bg-sky-500/5', 'dark:bg-sky-950/20');
                    }
                });
            });
        }
        document.addEventListener('change', styleAnswers);
        styleAnswers();

        // Save current question's answer dynamically
        function saveCurrentAnswer() {
            const page = pages[activeIdx];
            if (!page) return;
            
            const qId = page.getAttribute('data-q-id');
            const qType = page.getAttribute('data-q-type');
            let answerVal = null;
            
            if (qType === 'true_false') {
                const selected = page.querySelector('input:checked');
                if (selected) answerVal = selected.value;
            } 
            else if (qType === 'mcq' || qType === 'single_choice' || qType === 'multiple_choice') {
                const checked = page.querySelectorAll('input:checked');
                const multiple = page.querySelector('input[type="checkbox"]') !== null;
                if (multiple) {
                    answerVal = Array.from(checked).map(c => c.value);
                } else {
                    if (checked.length > 0) answerVal = checked[0].value;
                }
            } 
            else if (qType === 'fill_blank') {
                const inputs = page.querySelectorAll('input[type="text"]');
                answerVal = Array.from(inputs).map(i => i.value);
            } 
            else if (qType === 'open_text') {
                const text = page.querySelector('textarea');
                if (text) answerVal = text.value;
            }
            else if (qType === 'matching') {
                const terms = Array.from(page.querySelectorAll('input[name^="ans-"]')).map(i => i.value);
                const defs = Array.from(page.querySelectorAll('select')).map(s => s.value);
                answerVal = { terms: terms, definitions: defs };
            }
            else if (qType === 'ordering') {
                const items = Array.from(page.querySelectorAll('.drag-item')).map(i => i.getAttribute('data-value'));
                answerVal = items;
            }

            if (answerVal !== null && answerVal !== '') {
                fetch(`/learner/quizzes/${quizId}/attempts/${attemptId}/answers`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        question_id: qId,
                        answer_given: answerVal
                    })
                });
            }
        }

        // Helper: Up/Down for ordering questions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-move-up')) {
                const row = e.target.closest('.drag-item');
                const prev = row.previousElementSibling;
                if (prev && prev.classList.contains('drag-item')) {
                    row.parentNode.insertBefore(row, prev);
                    syncOrderInputs(row.parentNode);
                }
            }
            if (e.target.classList.contains('btn-move-down')) {
                const row = e.target.closest('.drag-item');
                const next = row.nextElementSibling;
                if (next && next.classList.contains('drag-item')) {
                    row.parentNode.insertBefore(next, row);
                    syncOrderInputs(row.parentNode);
                }
            }
        });

        function syncOrderInputs(parent) {
            const inputs = parent.querySelectorAll('.order-input');
            const items = parent.querySelectorAll('.drag-item');
            items.forEach((item, idx) => {
                const val = item.getAttribute('data-value');
                inputs[idx].value = val;
            });
            saveCurrentAnswer();
        }

        // Chronometer Logic
        const duration = parseInt("{{ $quiz->duration }}");
        if (duration) {
            const timerBox = document.getElementById('timer-box');
            const chronometer = document.getElementById('chronometer');
            
            // Calculate time left based on started_at and current timestamp
            const startedAt = new Date("{{ $activeAttempt->started_at }}").getTime();
            const totalDurationMs = duration * 60 * 1000;
            
            const timerInterval = setInterval(() => {
                const now = new Date().getTime();
                const elapsed = now - startedAt;
                const timeLeft = totalDurationMs - elapsed;
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    chronometer.textContent = "00:00";
                    alert("Le temps est écoulé ! Soumission du quiz automatique.");
                    submitWholeExam(true);
                    return;
                }
                
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                
                chronometer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Color alerts: 50% left, 20% left
                const percentLeft = (timeLeft / totalDurationMs) * 100;
                if (percentLeft <= 20) {
                    timerBox.className = "flex items-center gap-3 px-4 py-3 bg-red-500/10 text-red-600 dark:text-red-400 rounded-2xl transition-all duration-300";
                } else if (percentLeft <= 50) {
                    timerBox.className = "flex items-center gap-3 px-4 py-3 bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-2xl transition-all duration-300";
                }
            }, 1000);
        }

        // Submit quiz endpoint call
        function submitWholeExam(auto = false) {
            saveCurrentAnswer();
            
            if (!auto && !confirm("Êtes-vous sûr de vouloir soumettre et finaliser votre quiz ?")) {
                return;
            }

            fetch(`/learner/quizzes/${quizId}/attempts/${attemptId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Populate gamification modal
                    const modal = document.getElementById('modal-result');
                    const iconBox = document.getElementById('result-icon-box');
                    const title = document.getElementById('result-title');
                    const subtitle = document.getElementById('result-subtitle');
                    const xpText = document.getElementById('result-xp');
                    const levelText = document.getElementById('result-level');
                    const badgesBox = document.getElementById('result-badges-box');
                    const badgeName = document.getElementById('result-badge-name');
                    
                    const score = data.attempt.score;
                    const passed = data.attempt.passed;
                    
                    title.textContent = `Score : ${score}%`;
                    xpText.textContent = `+${data.xp_earned} XP`;
                    levelText.textContent = `Niveau ${data.new_level}`;
                    
                    if (passed) {
                        iconBox.className = "w-20 h-20 rounded-full flex items-center justify-center mx-auto text-4xl bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600";
                        iconBox.textContent = "🏆";
                        subtitle.textContent = "Félicitations ! Vous avez réussi le quiz.";
                    } else {
                        iconBox.className = "w-20 h-20 rounded-full flex items-center justify-center mx-auto text-4xl bg-red-100 dark:bg-red-950/20 text-red-600";
                        iconBox.textContent = "💪";
                        subtitle.textContent = "Seuil de réussite non atteint, retentez votre chance !";
                    }

                    if (data.badges_unlocked && data.badges_unlocked.length > 0) {
                        badgesBox.classList.remove('hidden');
                        badgeName.textContent = data.badges_unlocked.join(', ');
                    } else {
                        badgesBox.classList.add('hidden');
                    }

                    modal.classList.remove('hidden');
                }
            });
        }

        document.getElementById('btn-submit-exam').addEventListener('click', () => submitWholeExam(false));
        document.getElementById('btn-close-result').addEventListener('click', () => {
            window.location.href = "{{ route('learner.quizzes.show', $quiz->id) }}";
        });

        // Error Report Modal Handling
        const modalError = document.getElementById('modal-error');
        document.getElementById('btn-report-error').addEventListener('click', () => {
            modalError.classList.remove('hidden');
        });
        document.getElementById('btn-close-modal').addEventListener('click', () => {
            modalError.classList.add('hidden');
        });

        document.getElementById('form-error-report').addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            fetch(`/learner/quizzes/${quizId}/error-report`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    error_type: formData.get('error_type'),
                    comment: formData.get('comment')
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Signalement envoyé. Merci pour votre aide !");
                    modalError.classList.add('hidden');
                    e.target.reset();
                }
            });
        });

        // Security / Triche Log (Anti-Screenshot / Tab Blur detection)
        const overlay = document.getElementById('cheat-overlay');
        const container = document.getElementById('quiz-container');
        
        function triggerCheatAlert() {
            overlay.classList.remove('hidden');
            container.classList.add('blur-md');
            
            // Post screenshot incident
            fetch('/learner/security/screenshot-log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    attempt_id: attemptId
                })
            });

            setTimeout(() => {
                overlay.classList.add('hidden');
                container.classList.remove('blur-md');
            }, 5000);
        }

        // 1. Detect switch tabs
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                triggerCheatAlert();
            }
        });

        // 2. Detect print screen key
        window.addEventListener('keyup', (e) => {
            if (e.key === 'PrintScreen') {
                triggerCheatAlert();
            }
        });

        // Initial launch
        showQuestion(0);
    });
</script>
@endpush
@endsection
