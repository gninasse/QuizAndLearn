@extends('core::layouts.learner')

@section('title', $quiz->title . ' | Learn&Quiz')

@section('content')
<div class="space-y-6 max-w-2xl mx-auto">
    <!-- Back button -->
    <a href="{{ route('learner.quizzes.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour aux quiz
    </a>

    <!-- Header Card -->
    <div class="p-8 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-50">{{ $quiz->title }}</h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400 leading-relaxed">{{ $quiz->description }}</p>
        </div>

        <!-- Meta Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-50 dark:border-zinc-800/80">
            @if($quiz->duration)
                <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl">
                    <p class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 dark:text-zinc-500">Durée</p>
                    <p class="text-base font-bold mt-0.5 text-slate-800 dark:text-zinc-100">{{ $quiz->duration }} minutes</p>
                </div>
            @endif
            <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl">
                <p class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 dark:text-zinc-500">Seuil de réussite</p>
                <p class="text-base font-bold mt-0.5 text-slate-800 dark:text-zinc-100">{{ $quiz->passing_score }}%</p>
            </div>
            <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl col-span-2 sm:col-span-1">
                <p class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 dark:text-zinc-500">Tentatives max</p>
                <p class="text-base font-bold mt-0.5 text-slate-800 dark:text-zinc-100">
                    {{ $quiz->max_attempts ?: 'Illimitées' }}
                </p>
            </div>
        </div>

        <!-- Action Button -->
        <div class="pt-4">
            @if($maxAttemptsReached)
                <div class="p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/30 rounded-2xl text-xs font-semibold text-amber-600 dark:text-amber-400 text-center">
                    ⚠️ Vous avez atteint la limite de tentatives pour ce quiz.
                </div>
            @else
                <form action="{{ route('learner.quizzes.attempts.start', $quiz->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-sky-500 to-indigo-500 hover:opacity-95 text-white font-bold text-sm rounded-2xl shadow-lg shadow-sky-500/10 hover:shadow-sky-500/25 transition-all">
                        Démarrer le quiz
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Attempt History -->
    <div class="space-y-4">
        <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-100">Vos tentatives passées</h3>
        <div class="space-y-3">
            @forelse($attempts as $attempt)
                <div class="p-5 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-slate-800 dark:text-zinc-100">Tentative #{{ $attempt->attempt_number }}</span>
                            <span class="text-xs text-slate-400">{{ $attempt->completed_at ? $attempt->completed_at->format('d/m/Y H:i') : 'En cours' }}</span>
                        </div>
                        <p class="text-xs text-slate-400 dark:text-zinc-500 mt-1">
                            Score : <strong class="text-slate-700 dark:text-zinc-300">{{ $attempt->score ?? 0 }}%</strong> ({{ $attempt->points_earned }} / {{ $attempt->points_total }} pts)
                        </p>
                    </div>
                    <div>
                        @if($attempt->status === 'completed')
                            @if($attempt->passed)
                                <span class="px-3 py-1.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-full text-xs font-bold uppercase tracking-wider">Réussi</span>
                            @else
                                <span class="px-3 py-1.5 bg-red-500/10 text-red-600 dark:text-red-400 rounded-full text-xs font-bold uppercase tracking-wider">Échoué</span>
                            @endif
                        @else
                            <span class="px-3 py-1.5 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-full text-xs font-bold uppercase tracking-wider">En cours</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-xs text-slate-400 dark:text-zinc-500 bg-white dark:bg-zinc-900 border border-dashed border-slate-200 dark:border-zinc-800 rounded-3xl">
                    Vous n'avez pas encore tenté ce quiz.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
