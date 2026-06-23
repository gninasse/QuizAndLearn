@extends('core::layouts.learner')

@section('title', 'Révisions Spacées | Learn&Quiz')

@section('content')
<style>
    .perspective-1000 {
        perspective: 1000px;
    }
    .backface-hidden {
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
    }
    .rotate-y-180 {
        transform: rotateY(180deg);
    }
    .transform-style-3d {
        transform-style: preserve-3d;
    }
    .flipped {
        transform: rotateY(180deg);
    }
</style>

<div class="max-w-xl mx-auto space-y-6 flex flex-col justify-center h-full min-h-[75vh]" id="reviser-container">
    
    <!-- Congratulations Screen (when done) -->
    <div id="congrats-screen" class="{{ count($dueCards) > 0 ? 'hidden' : '' }} text-center p-8 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-6">
        <div class="w-20 h-20 bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600 rounded-full flex items-center justify-center text-4xl mx-auto">
            🎉
        </div>
        <div class="space-y-2">
            <h2 class="text-xl font-bold text-slate-900 dark:text-zinc-50">Mémoire à jour !</h2>
            <p class="text-sm text-slate-500 dark:text-zinc-400">Aucune carte à réviser aujourd'hui. Revenez demain pour ancrer vos compétences !</p>
        </div>
        <a href="{{ route('learner.dashboard') }}" class="inline-block px-6 py-3 bg-slate-900 dark:bg-zinc-800 hover:opacity-90 text-white font-bold text-xs rounded-2xl shadow-md transition-all">
            Retour au tableau de bord
        </a>
    </div>

    <!-- Active Deck Workspace -->
    @if(count($dueCards) > 0)
    <div id="workspace-deck" class="space-y-6 flex-1 flex flex-col justify-between">
        
        <!-- Header status -->
        <div class="flex justify-between items-center text-slate-400 dark:text-zinc-500">
            <span class="text-xs font-bold uppercase tracking-wider">Révisions Actives</span>
            <span id="deck-progress-text" class="text-xs font-bold">1 / {{ count($dueCards) }}</span>
        </div>

        <!-- Progress Bar -->
        <div class="w-full h-2 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden shrink-0">
            <div id="deck-progress-bar" class="h-full bg-sky-500 rounded-full transition-all duration-300" style="width: {{ 100 / count($dueCards) }}%;"></div>
        </div>

        <!-- 3D Card Stack Container -->
        <div class="perspective-1000 w-full min-h-[320px] relative flex-1 flex">
            @foreach($dueCards as $idx => $card)
                @php
                    $q = $card->question;
                    $options = $q->options ?: [];
                @endphp
                <div class="flashcard-box w-full h-full absolute inset-0 {{ $idx === 0 ? '' : 'pointer-events-none opacity-0 scale-95 translate-y-4' }} transition-all duration-300 flex" data-idx="{{ $idx }}" data-card-id="{{ $card->id }}" data-q-id="{{ $q->id }}">
                    <div class="flashcard-inner w-full h-full bg-white dark:bg-zinc-900 border border-slate-150 dark:border-zinc-800/80 rounded-3xl shadow-md cursor-pointer transform-style-3d transition-transform duration-500 flex flex-col justify-between p-8">
                        
                        <!-- FRONT SIDE (Question) -->
                        <div class="backface-hidden flex flex-col justify-between h-full absolute inset-0 p-8">
                            <div class="space-y-4">
                                <span class="px-3 py-1 bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 rounded-full text-[10px] font-bold uppercase tracking-wider">Question</span>
                                <h3 class="text-lg md:text-xl font-bold text-slate-900 dark:text-zinc-50 leading-relaxed">{{ $q->question_text }}</h3>
                            </div>
                            <p class="text-center text-[10px] text-sky-500 dark:text-sky-400 font-bold uppercase tracking-wider animate-bounce mt-4">👉 Cliquer pour révéler la réponse</p>
                        </div>

                        <!-- BACK SIDE (Answer Details) -->
                        <div class="backface-hidden rotate-y-180 flex flex-col justify-between h-full absolute inset-0 p-8">
                            <div class="space-y-4 overflow-y-auto pr-1">
                                <span class="px-3 py-1 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-full text-[10px] font-bold uppercase tracking-wider">Réponse correcte</span>
                                
                                <div class="space-y-3 pt-2">
                                    @if($q->type === 'true_false')
                                        <p class="text-base font-bold text-slate-800 dark:text-zinc-200">
                                            Réponse attendue : <span class="text-sky-500">{{ ($options['correct_answer'] ?? 'true') === 'true' ? 'Vrai' : 'Faux' }}</span>
                                        </p>

                                    @elseif($q->type === 'mcq' || $q->type === 'single_choice' || $q->type === 'multiple_choice')
                                        @php
                                            $answersList = $options['answers'] ?? [];
                                            $corrects = collect($answersList)->filter(fn($a) => filter_var($a['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->pluck('text')->toArray();
                                        @endphp
                                        <ul class="space-y-2">
                                            @foreach($answersList as $ans)
                                                @php
                                                    $isCorrect = in_array($ans['text'], $corrects);
                                                @endphp
                                                <li class="flex items-center gap-3 p-3 border rounded-xl {{ $isCorrect ? 'border-emerald-250 bg-emerald-500/5 dark:bg-emerald-950/10 text-emerald-600 dark:text-emerald-450 font-bold' : 'border-slate-100 text-slate-500 dark:border-zinc-800 dark:text-zinc-550' }}">
                                                    <span>{{ $isCorrect ? '✓' : '✗' }}</span>
                                                    <span class="text-xs">{{ $ans['text'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>

                                    @elseif($q->type === 'fill_blank')
                                        @php
                                            $blanks = $options['blanks'] ?? [];
                                        @endphp
                                        <div class="space-y-2">
                                            @foreach($blanks as $bIdx => $blank)
                                                <p class="text-xs text-slate-650 dark:text-zinc-300">
                                                    Champ #{{ $bIdx + 1 }} : <strong class="text-emerald-600 dark:text-emerald-400 font-bold">{{ implode(' ou ', $blank['answers'] ?? []) }}</strong>
                                                </p>
                                            @endforeach
                                        </div>

                                    @elseif($q->type === 'matching')
                                        @php
                                            $pairs = $options['pairs'] ?? [];
                                        @endphp
                                        <div class="space-y-2">
                                            @foreach($pairs as $pair)
                                                <p class="text-xs text-slate-600 dark:text-zinc-350">
                                                    <strong>{{ $pair['term'] }}</strong> ➔ <span class="text-emerald-650 dark:text-emerald-400 font-bold">{{ $pair['definition'] }}</span>
                                                </p>
                                            @endforeach
                                        </div>

                                    @elseif($q->type === 'ordering')
                                        @php
                                            $items = $options['items'] ?? [];
                                        @endphp
                                        <ol class="list-decimal pl-4 space-y-1 text-xs text-slate-650 dark:text-zinc-350">
                                            @foreach($items as $item)
                                                <li><strong class="text-emerald-650 dark:text-emerald-400 font-bold">{{ $item }}</strong></li>
                                            @endforeach
                                        </ol>

                                    @elseif($q->type === 'open_text')
                                        <p class="text-xs text-slate-500 dark:text-zinc-450 italic">Cette question ouverte demande une réflexion libre.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        <!-- SM-2 Evaluation Box -->
        <div id="evaluation-box" class="hidden space-y-3 animate-fade-in shrink-0">
            <p class="text-center text-xs text-slate-400 font-bold uppercase tracking-wider">Comment avez-vous trouvé la réponse ?</p>
            <div class="grid grid-cols-4 gap-2">
                <button class="btn-eval py-3.5 bg-red-650 hover:bg-red-700 text-white font-bold text-xs rounded-2xl shadow-sm transition-all" data-rating="0">
                    Oublié
                </button>
                <button class="btn-eval py-3.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-2xl shadow-sm transition-all" data-rating="3">
                    Dur
                </button>
                <button class="btn-eval py-3.5 bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs rounded-2xl shadow-sm transition-all" data-rating="4">
                    Moyen
                </button>
                <button class="btn-eval py-3.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs rounded-2xl shadow-sm transition-all" data-rating="5">
                    Facile
                </button>
            </div>
            <button id="btn-report-question" class="w-full py-2 text-center text-[10px] font-bold text-slate-450 dark:text-zinc-500 hover:text-slate-600 uppercase tracking-wider">
                ⚠️ Signaler une erreur dans la question
            </button>
        </div>

    </div>
    @endif
</div>

<!-- Modal Signalement Question -->
<div id="modal-error" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-xl w-full max-w-md p-6 space-y-4 transform scale-95 transition-all">
        <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-50">Signaler une erreur</h3>
        <form id="form-error-report" class="space-y-4">
            <div>
                <label for="error_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Type d'erreur</label>
                <select name="error_type" id="error_type" class="w-full bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 font-semibold" required>
                    <option value="spelling">Faute d'orthographe / Syntaxe</option>
                    <option value="wrong_answer">Erreur d'explication / Réponse</option>
                    <option value="other">Autre problème</option>
                </select>
            </div>
            <div>
                <label for="comment" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Détails</label>
                <textarea name="comment" id="comment" placeholder="Précisez l'erreur observée..." rows="4" class="w-full p-4 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 leading-relaxed" required></textarea>
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

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cardsCount = parseInt("{{ count($dueCards) }}");
        if (cardsCount === 0) return;
        
        let currentIdx = 0;
        const boxes = document.querySelectorAll('.flashcard-box');
        const evalBox = document.getElementById('evaluation-box');
        
        // Progress elements
        const progressText = document.getElementById('deck-progress-text');
        const progressBar = document.getElementById('deck-progress-bar');
        const congratsScreen = document.getElementById('congrats-screen');
        const workspaceDeck = document.getElementById('workspace-deck');

        function updateProgressUI() {
            progressText.textContent = `${currentIdx + 1} / ${cardsCount}`;
            progressBar.style.width = `${((currentIdx + 1) / cardsCount) * 100}%`;
        }

        // Add flip logic to cards
        boxes.forEach((box, index) => {
            const inner = box.querySelector('.flashcard-inner');
            inner.addEventListener('click', () => {
                if (index !== currentIdx) return;
                
                inner.classList.toggle('flipped');
                
                // Show evaluation box once flipped to back
                if (inner.classList.contains('flipped')) {
                    evalBox.classList.remove('hidden');
                } else {
                    evalBox.classList.add('hidden');
                }
            });
        });

        // Evaluation button logic
        const evalBtns = document.querySelectorAll('.btn-eval');
        evalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const rating = parseInt(btn.getAttribute('data-rating'));
                const activeBox = boxes[currentIdx];
                const cardId = activeBox.getAttribute('data-card-id');
                
                // Post evaluation to server
                fetch('/learner/reviser/evaluate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        flashcard_id: cardId,
                        rating: rating
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Slide card away
                        const inner = activeBox.querySelector('.flashcard-inner');
                        activeBox.classList.add('pointer-events-none', '-translate-x-full', 'opacity-0', 'scale-90');
                        
                        setTimeout(() => {
                            activeBox.classList.add('hidden');
                            
                            // Move to next card
                            currentIdx++;
                            evalBox.classList.add('hidden');
                            
                            if (currentIdx < cardsCount) {
                                const nextBox = boxes[currentIdx];
                                nextBox.classList.remove('pointer-events-none', 'opacity-0', 'scale-95', 'translate-y-4');
                                updateProgressUI();
                            } else {
                                // Done all reviews!
                                workspaceDeck.classList.add('hidden');
                                congratsScreen.classList.remove('hidden');
                            }
                        }, 300);
                    }
                });
            });
        });

        // Bug modal inside flashcards
        const modalError = document.getElementById('modal-error');
        document.getElementById('btn-report-question').addEventListener('click', () => {
            modalError.classList.remove('hidden');
        });
        document.getElementById('btn-close-modal').addEventListener('click', () => {
            modalError.classList.add('hidden');
        });

        document.getElementById('form-error-report').addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const activeBox = boxes[currentIdx];
            const qId = activeBox.getAttribute('data-q-id');
            
            fetch(`/learner/quizzes/${qId}/error-report`, {
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
                    alert("Signalement envoyé. Merci !");
                    modalError.classList.add('hidden');
                    e.target.reset();
                }
            });
        });
    });
</script>
@endpush
@endsection
