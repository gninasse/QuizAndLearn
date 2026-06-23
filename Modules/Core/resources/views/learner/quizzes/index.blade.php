@extends('core::layouts.learner')

@section('title', 'Quiz | Learn&Quiz')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Vos Quiz</h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400">Évaluez vos compétences théoriques</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 overflow-x-auto pb-2 shrink-0">
        <button class="filter-btn px-4 py-2 bg-sky-500 text-white text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="all">Tous</button>
        <button class="filter-btn px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="new">Nouveaux</button>
        <button class="filter-btn px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="in_progress">En cours</button>
        <button class="filter-btn px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="completed">Terminés</button>
    </div>

    <!-- Quiz list grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="quiz-grid">
        @forelse($quizzes as $quiz)
            @php
                $attempts = $quizAttempts->get($quiz->id);
                $status = 'new';
                $statusLabel = 'Nouveau';
                $statusClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
                
                if ($attempts) {
                    if ($attempts->contains('status', 'in_progress')) {
                        $status = 'in_progress';
                        $statusLabel = 'En cours';
                        $statusClass = 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400';
                    } elseif ($attempts->contains('status', 'completed')) {
                        $status = 'completed';
                        $statusLabel = 'Terminé';
                        $statusClass = 'bg-slate-500/10 text-slate-600 dark:text-slate-400';
                    }
                }
                
                $completedAttemptsCount = $attempts ? $attempts->where('status', 'completed')->count() : 0;
                $remainingAttempts = $quiz->max_attempts ? max(0, $quiz->max_attempts - $completedAttemptsCount) : null;
            @endphp
            <div class="quiz-card p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm flex flex-col justify-between transition-all duration-300" data-status="{{ $status }}">
                <div>
                    <div class="flex justify-between items-start mb-3">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                        @if($quiz->duration)
                            <span class="text-xs text-slate-400">⏱️ {{ $quiz->duration }} min</span>
                        @endif
                    </div>
                    <h3 class="font-bold text-lg mb-2 text-slate-900 dark:text-zinc-50">{{ $quiz->title }}</h3>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 line-clamp-2">{{ $quiz->description }}</p>
                </div>
                <div class="mt-6 flex items-center justify-between border-t border-slate-50 dark:border-zinc-800/80 pt-4">
                    <span class="text-xs text-slate-400">
                        {{ $quiz->questions_count }} {{ $quiz->questions_count > 1 ? 'questions' : 'question' }}
                        @if($remainingAttempts !== null)
                            • {{ $remainingAttempts }} {{ $remainingAttempts > 1 ? 'tentatives' : 'tentative' }} rest.
                        @endif
                    </span>
                    
                    @if($status === 'completed' && $remainingAttempts === 0)
                        <span class="px-4 py-2 bg-slate-100 text-slate-400 dark:bg-zinc-800 dark:text-zinc-600 text-xs font-semibold rounded-xl">Fini</span>
                    @else
                        <a href="{{ route('learner.quizzes.show', $quiz->id) }}" class="px-4 py-2 bg-sky-500 text-white text-xs font-semibold rounded-xl hover:opacity-90 shadow-md shadow-sky-500/10 transition-all">
                            {{ $status === 'in_progress' ? 'Reprendre' : 'Démarrer' }}
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center text-slate-400 dark:text-zinc-500 bg-white dark:bg-zinc-900 border border-dashed border-slate-200 dark:border-zinc-800 rounded-3xl">
                📥 Aucun quiz disponible pour le moment.
            </div>
        @endforelse
    </div>
</div>

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const buttons = document.querySelectorAll('.filter-btn');
        const cards = document.querySelectorAll('.quiz-card');

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const filter = btn.getAttribute('data-filter');
                
                // Style des boutons
                buttons.forEach(b => {
                    b.classList.remove('bg-sky-500', 'text-white');
                    b.classList.add('bg-white', 'dark:bg-zinc-900', 'border', 'border-slate-200', 'dark:border-zinc-800', 'text-slate-600', 'dark:text-zinc-400');
                });
                
                btn.classList.add('bg-sky-500', 'text-white');
                btn.classList.remove('bg-white', 'dark:bg-zinc-900', 'border', 'border-slate-200', 'dark:border-zinc-800', 'text-slate-600', 'dark:text-zinc-400');

                // Filtrage des cartes
                cards.forEach(card => {
                    const status = card.getAttribute('data-status');
                    if (filter === 'all' || status === filter) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    });
</script>
@endpush
@endsection
