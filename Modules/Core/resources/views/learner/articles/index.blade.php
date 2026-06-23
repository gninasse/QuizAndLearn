@extends('core::layouts.learner')

@section('title', 'Articles | Learn&Quiz')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Vos Articles</h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400">Ressources théoriques et lectures recommandées</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 overflow-x-auto pb-2 shrink-0">
        <button class="filter-btn px-4 py-2 bg-sky-500 text-white text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="all">Tous</button>
        <button class="filter-btn px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="new">Nouveaux</button>
        <button class="filter-btn px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="in_progress">En cours</button>
        <button class="filter-btn px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold rounded-xl shrink-0 transition-colors" data-filter="completed">Lus</button>
    </div>

    <!-- Articles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="article-grid">
        @forelse($articles as $article)
            @php
                $progress = $articlesProgress->get($article->id);
                $status = 'new';
                $statusLabel = 'Nouveau';
                $statusClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
                $percent = 0;
                
                if ($progress) {
                    $percent = $progress->progress_percentage;
                    if ($progress->status === 'completed') {
                        $status = 'completed';
                        $statusLabel = 'Lu';
                        $statusClass = 'bg-slate-500/10 text-slate-600 dark:text-slate-400';
                    } else {
                        $status = 'in_progress';
                        $statusLabel = 'En cours';
                        $statusClass = 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
                    }
                }
            @endphp
            <div class="article-card p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm flex flex-col justify-between transition-all duration-300" data-status="{{ $status }}">
                <div class="space-y-3">
                    <div class="flex justify-between items-start">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                        @if($article->estimated_reading_time)
                            <span class="text-xs text-slate-400">📖 {{ $article->estimated_reading_time }} min</span>
                        @endif
                    </div>
                    <div>
                        <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-50 leading-snug line-clamp-2">{{ $article->title }}</h3>
                        @if($article->category)
                            <span class="inline-block mt-1 text-[10px] bg-slate-100 dark:bg-zinc-850 px-2 py-0.5 rounded-full text-slate-550 dark:text-zinc-400 font-semibold">{{ $article->category }}</span>
                        @endif
                    </div>
                </div>

                <div class="mt-6 space-y-4 border-t border-slate-50 dark:border-zinc-800/80 pt-4">
                    <!-- Progress micro indicator -->
                    @if($percent > 0)
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full bg-sky-500 rounded-full" style="width: {{ $percent }}%;"></div>
                            </div>
                            <span class="text-[10px] font-bold text-slate-400">{{ $percent }}%</span>
                        </div>
                    @endif

                    <div class="flex justify-between items-center">
                        <span class="text-xs text-slate-400">
                            Publié le {{ $article->created_at->format('d/m/Y') }}
                        </span>
                        <a href="{{ route('learner.articles.show', $article->id) }}" class="px-4 py-2 bg-slate-800 dark:bg-zinc-700 text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-md shadow-slate-500/10">
                            {{ $status === 'in_progress' ? 'Continuer' : 'Lire' }}
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center text-slate-400 dark:text-zinc-500 bg-white dark:bg-zinc-900 border border-dashed border-slate-200 dark:border-zinc-800 rounded-3xl">
                📥 Aucun article disponible pour le moment.
            </div>
        @endforelse
    </div>
</div>

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const buttons = document.querySelectorAll('.filter-btn');
        const cards = document.querySelectorAll('.article-card');

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
