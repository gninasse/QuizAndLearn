@extends('core::layouts.learner')

@section('title', 'Tableau de Bord | Learn&Quiz')

@section('content')
<div class="space-y-8">
    
    <!-- Greeting & Streak Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-6 bg-gradient-to-r from-sky-500 to-indigo-500 rounded-3xl text-white shadow-lg shadow-sky-500/10">
        <div>
            <h1 class="text-2xl font-bold">Bonjour, {{ auth()->user()->name }} ! 👋</h1>
            <p class="text-sm text-sky-100 mt-1">Prêt pour vos révisions du jour ?</p>
        </div>
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-md rounded-2xl shrink-0 self-start sm:self-auto border border-white/10">
            <span class="text-lg">🔥</span>
            <span class="font-bold text-sm">{{ $xp->current_streak }} {{ $xp->current_streak > 1 ? 'jours' : 'jour' }} {{ $xp->current_streak > 1 ? 'consécutifs' : 'consécutif' }}</span>
        </div>
    </div>

    <!-- XP & Level Card -->
    <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm">
        <div class="flex justify-between items-center mb-3">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Progression Globale</span>
                <h3 class="text-lg font-bold">Niveau {{ $xp->current_level ?: 1 }}</h3>
            </div>
            <span class="text-xs font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 px-3 py-1 rounded-full">{{ $xp->total_xp }} / {{ $xpForNextLevel }} XP</span>
        </div>
        <!-- Progress bar -->
        <div class="w-full h-3 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-sky-500 to-indigo-500 rounded-full" style="width: {{ $xpProgressPercentage }}%;"></div>
        </div>
    </div>

    <!-- Dashboard Sections Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- Flashcard reviews widget -->
        <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-4">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <span>🧠</span> Révisions du jour
            </h3>
            <p class="text-xs text-slate-400 dark:text-zinc-500">
                Mémorisez à long terme grâce à l'algorithme de répétition espacée.
            </p>
            <div class="p-4 bg-sky-50 dark:bg-sky-950/20 border border-sky-100 dark:border-sky-900/30 rounded-2xl flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold">{{ $dueCardsCount }} {{ $dueCardsCount > 1 ? 'cartes dues' : 'carte due' }}</p>
                    <p class="text-[11px] text-slate-500 dark:text-zinc-400">À réviser aujourd'hui</p>
                </div>
                <a href="{{ route('learner.reviser') }}" class="px-4 py-2 bg-sky-500 text-white text-xs font-semibold rounded-xl hover:opacity-90 shadow-md shadow-sky-500/15 transition-all">
                    Commencer
                </a>
            </div>
        </div>

        <!-- Quiz in progress widget -->
        <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-4">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <span>📝</span> Quiz en attente
            </h3>
            <p class="text-xs text-slate-400 dark:text-zinc-500">
                Quiz assignés par vos formateurs.
            </p>
            <div class="space-y-3">
                @forelse($pendingQuizzes as $quiz)
                    @php
                        $attempts = $quizAttempts->get($quiz->id);
                        $hasInProgress = $attempts ? $attempts->contains('status', 'in_progress') : false;
                    @endphp
                    <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800/80 rounded-2xl flex items-center justify-between">
                        <div class="truncate pr-4">
                            <p class="text-sm font-semibold truncate">{{ $quiz->title }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">
                                @if($quiz->duration)
                                    ⏱️ {{ $quiz->duration }} min • 
                                @endif
                                {{ $quiz->questions_count }} {{ $quiz->questions_count > 1 ? 'questions' : 'question' }}
                            </p>
                        </div>
                        <a href="{{ route('learner.quizzes.show', $quiz->id) }}" class="px-4 py-2 {{ $hasInProgress ? 'bg-indigo-600 shadow-indigo-500/15' : 'bg-slate-800 dark:bg-zinc-700 shadow-slate-500/15' }} text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-md shrink-0">
                            {{ $hasInProgress ? 'Reprendre' : 'Lancer' }}
                        </a>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400 dark:text-zinc-500 bg-slate-50 dark:bg-zinc-800/20 border border-dashed border-slate-200 dark:border-zinc-800 rounded-2xl">
                        🎉 Aucun quiz en attente ! Vous êtes à jour.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent articles widget -->
        <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-4 md:col-span-2">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <span>📄</span> Articles récents
            </h3>
            <p class="text-xs text-slate-400 dark:text-zinc-500">
                Dernières ressources théoriques publiées pour votre groupe.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($assignedArticles as $article)
                    @php
                        $progress = $articlesProgress->get($article->id);
                        $isCompleted = $progress && $progress->status === 'completed';
                    @endphp
                    <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800/80 rounded-2xl flex items-center justify-between">
                        <div class="truncate pr-4">
                            <div class="flex items-center gap-2 mb-1">
                                @if(!$progress)
                                    <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded text-[9px] font-bold uppercase tracking-wider">Nouveau</span>
                                @elseif($isCompleted)
                                    <span class="px-2 py-0.5 bg-slate-500/10 text-slate-600 dark:text-slate-400 rounded text-[9px] font-bold uppercase tracking-wider">Lu</span>
                                @else
                                    <span class="px-2 py-0.5 bg-sky-500/10 text-sky-600 dark:text-sky-400 rounded text-[9px] font-bold uppercase tracking-wider">En cours</span>
                                @endif
                                @if($article->estimated_reading_time)
                                    <span class="text-[10px] text-slate-400">Lecture : {{ $article->estimated_reading_time }} min</span>
                                @endif
                            </div>
                            <p class="text-sm font-semibold truncate">{{ $article->title }}</p>
                        </div>
                        <a href="{{ route('learner.articles.show', $article->id) }}" class="px-4 py-2 bg-slate-800 text-white dark:bg-zinc-700 text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-md shrink-0">
                            Lire
                        </a>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400 dark:text-zinc-500 bg-slate-50 dark:bg-zinc-800/20 border border-dashed border-slate-200 dark:border-zinc-800 rounded-2xl md:col-span-2">
                        Aucun article disponible pour le moment.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection

