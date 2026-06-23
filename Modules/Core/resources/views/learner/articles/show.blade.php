@extends('core::layouts.learner')

@section('title', $article->title . ' | Learn&Quiz')

@section('content')
<div class="flex flex-col lg:flex-row gap-8 max-w-5xl mx-auto" id="article-container">
    
    <!-- Left Navigation Widget (Bookmarks, Zen mode, Rating) -->
    <div class="w-full lg:w-64 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 p-6 rounded-3xl shadow-sm shrink-0 h-fit space-y-6 lg:sticky lg:top-6" id="article-tools">
        <!-- Back button -->
        <a href="{{ route('learner.articles.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Retour aux articles
        </a>

        <!-- Reading Controls -->
        <div class="space-y-3 pt-4 border-t border-slate-50 dark:border-zinc-800/80">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Options de lecture</p>
            
            <!-- Zen Mode Toggle -->
            <button id="btn-zen" class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 hover:bg-slate-150 dark:bg-zinc-850 dark:hover:bg-zinc-800 rounded-2xl transition-all text-xs font-semibold">
                <span class="flex items-center gap-2">👁️ Mode Zen</span>
                <span id="zen-status" class="text-[10px] uppercase font-bold text-slate-400">Désactivé</span>
            </button>

            <!-- Bookmarks / Favoris -->
            <button id="btn-favorite" class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 hover:bg-slate-150 dark:bg-zinc-850 dark:hover:bg-zinc-800 rounded-2xl transition-all text-xs font-semibold">
                <span class="flex items-center gap-2">⭐ Favori</span>
                <span id="favorite-status" class="text-xs text-sky-500 font-bold">
                    {{ $progress->is_favorite ? 'Oui' : 'Non' }}
                </span>
            </button>
        </div>

        <!-- Rating Widget -->
        <div class="space-y-2 pt-4 border-t border-slate-50 dark:border-zinc-800/80">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Évaluer la ressource</p>
            <div class="flex items-center justify-center gap-1 py-2" id="star-rating-box">
                @for($i = 1; $i <= 5; $i++)
                    <button class="star-btn text-2xl transition-all {{ ($progress->rating >= $i) ? 'text-amber-400 scale-110' : 'text-slate-200 dark:text-zinc-700' }}" data-rating="{{ $i }}">★</button>
                @endfor
            </div>
            <p id="rating-info" class="text-center text-[10px] text-slate-400 dark:text-zinc-500 font-medium">Votre note aide à améliorer le cours.</p>
        </div>

        <!-- Table of Contents -->
        <div class="space-y-2 pt-4 border-t border-slate-50 dark:border-zinc-800/80 hidden lg:block" id="toc-container">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Sommaire</p>
            <ul class="space-y-2 text-xs font-medium text-slate-500 dark:text-zinc-450" id="toc-list">
                <!-- Dynamically generated using Vanilla JS -->
            </ul>
        </div>

        <div class="pt-4 border-t border-slate-50 dark:border-zinc-800/80">
            <button id="btn-report-error" class="w-full flex items-center justify-center gap-2 py-3 bg-slate-50 hover:bg-slate-100 dark:bg-zinc-850 dark:hover:bg-zinc-800 text-slate-500 dark:text-zinc-450 font-semibold text-[10px] uppercase rounded-2xl transition-all tracking-wider">
                ⚠️ Signaler une anomalie
            </button>
        </div>
    </div>

    <!-- Article Content Box -->
    <div class="flex-1 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 p-8 rounded-3xl shadow-sm space-y-6 flex flex-col justify-between" id="article-body-wrapper">
        <article class="space-y-6" id="article-area">
            <!-- Article Title Header -->
            <header class="space-y-3 pb-6 border-b border-slate-50 dark:border-zinc-800/80">
                <div class="flex items-center gap-2">
                    @if($article->category)
                        <span class="px-2.5 py-0.5 bg-sky-500/10 text-sky-600 dark:text-sky-400 rounded-full text-[9px] font-bold uppercase tracking-wider">{{ $article->category }}</span>
                    @endif
                    @if($article->estimated_reading_time)
                        <span class="text-xs text-slate-400">⏱️ {{ $article->estimated_reading_time }} min de lecture</span>
                    @endif
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-zinc-50 leading-tight">{{ $article->title }}</h1>
            </header>

            <!-- Reading Progress Float Bar -->
            <div id="read-indicator" class="w-full h-1 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                <div id="read-progress" class="h-full bg-sky-500 rounded-full transition-all duration-100" style="width: 0%;"></div>
            </div>

            <!-- Content Area -->
            <div class="prose prose-slate dark:prose-invert max-w-none text-slate-800 dark:text-zinc-200 leading-relaxed font-medium" id="article-content">
                {!! $article->content !!}
            </div>
        </article>

        <!-- Completion banner -->
        <div id="read-complete-banner" class="hidden mt-8 p-4 bg-emerald-500/10 border border-emerald-200 dark:border-emerald-950/40 rounded-2xl flex items-center justify-between text-emerald-600 dark:text-emerald-400">
            <span class="text-xs font-bold flex items-center gap-2">✅ Article lu et validé (+15 XP !)</span>
        </div>
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
                    <option value="technical">Problème d'affichage de média</option>
                    <option value="content">Informations obsolètes / incorrectes</option>
                    <option value="other">Autre problème</option>
                </select>
            </div>
            <div>
                <label for="comment" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Détails</label>
                <textarea name="comment" id="comment" placeholder="Décrivez l'anomalie de manière précise..." rows="4" class="w-full p-4 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 leading-relaxed" required></textarea>
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
        const articleId = "{{ $article->id }}";
        const contentArea = document.getElementById('article-content');
        const tocList = document.getElementById('toc-list');
        const tocContainer = document.getElementById('toc-container');
        
        // 1. Generate dynamic Table of Contents
        const headings = contentArea.querySelectorAll('h2, h3');
        if (headings.length > 0) {
            headings.forEach((heading, index) => {
                const id = `heading-${index}`;
                heading.setAttribute('id', id);
                
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = `#${id}`;
                a.textContent = heading.textContent;
                a.className = "hover:text-sky-500 transition-colors block py-0.5 truncate";
                if (heading.tagName === 'H3') {
                    a.classList.add('pl-3', 'text-[11px]');
                }
                
                // Add soft scroll click handler
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    heading.scrollIntoView({ behavior: 'smooth' });
                });
                
                li.appendChild(a);
                tocList.appendChild(li);
            });
        } else {
            tocContainer.classList.add('hidden');
        }

        // 2. Zen Mode handling
        const btnZen = document.getElementById('btn-zen');
        const zenStatus = document.getElementById('zen-status');
        const tools = document.getElementById('article-tools');
        const wrapper = document.getElementById('article-body-wrapper');
        const mainContainer = document.getElementById('article-container');
        const appSidebar = document.querySelector('aside');
        const appHeader = document.querySelector('header');
        const bottomNav = document.querySelector('nav.md\\:hidden');
        
        let zenActive = false;
        
        btnZen.addEventListener('click', () => {
            zenActive = !zenActive;
            if (zenActive) {
                // Activate Zen
                zenStatus.textContent = "Activé";
                zenStatus.className = "text-[10px] uppercase font-bold text-sky-500 animate-pulse";
                btnZen.classList.add('bg-sky-500/10', 'text-sky-600', 'dark:text-sky-400');
                
                // Hide peripheral layouts
                if (appSidebar) appSidebar.style.display = 'none';
                if (appHeader) appHeader.style.display = 'none';
                if (bottomNav) bottomNav.style.display = 'none';
                
                // Center and style text content box
                wrapper.classList.remove('p-8');
                wrapper.classList.add('p-10', 'md:p-16', 'mx-auto', 'max-w-3xl');
                mainContainer.classList.remove('max-w-5xl');
                mainContainer.classList.add('max-w-4xl');
            } else {
                // Deactivate Zen
                zenStatus.textContent = "Désactivé";
                zenStatus.className = "text-[10px] uppercase font-bold text-slate-400";
                btnZen.classList.remove('bg-sky-500/10', 'text-sky-600', 'dark:text-sky-400');
                
                if (appSidebar) appSidebar.style.display = '';
                if (appHeader) appHeader.style.display = '';
                if (bottomNav) bottomNav.style.display = '';
                
                wrapper.classList.add('p-8');
                wrapper.classList.remove('p-10', 'md:p-16', 'mx-auto', 'max-w-3xl');
                mainContainer.classList.add('max-w-5xl');
                mainContainer.classList.remove('max-w-4xl');
            }
        });

        // 3. Favorite Bookmark Ajax call
        const btnFavorite = document.getElementById('btn-favorite');
        const favoriteStatus = document.getElementById('favorite-status');
        
        btnFavorite.addEventListener('click', () => {
            fetch(`/learner/articles/${articleId}/favorite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    favoriteStatus.textContent = data.is_favorite ? 'Oui' : 'Non';
                }
            });
        });

        // 4. Rating widget hover & click handler
        const stars = document.querySelectorAll('.star-btn');
        stars.forEach(star => {
            star.addEventListener('click', () => {
                const rating = parseInt(star.getAttribute('data-rating'));
                
                fetch(`/learner/articles/${articleId}/rate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ rating: rating })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Repaint stars
                        stars.forEach((s, idx) => {
                            if (idx < rating) {
                                s.className = "star-btn text-2xl text-amber-400 scale-110 transition-all";
                            } else {
                                s.className = "star-btn text-2xl text-slate-200 dark:text-zinc-700 transition-all";
                            }
                        });
                        document.getElementById('rating-info').textContent = "Note enregistrée. Merci !";
                    }
                });
            });
        });

        // 5. Reading Scroll tracker & dynamic progress submission
        const readProgressBar = document.getElementById('read-progress');
        const readCompleteBanner = document.getElementById('read-complete-banner');
        let completed = "{{ $progress->status }}" === 'completed';
        
        if (completed) {
            readCompleteBanner.classList.remove('hidden');
            readProgressBar.style.width = "100%";
        }

        window.addEventListener('scroll', () => {
            if (completed) return;
            
            // Calculate scroll depth
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            
            if (scrollHeight <= 0) return;
            
            const percentage = Math.min(100, Math.round((scrollTop / scrollHeight) * 100));
            readProgressBar.style.width = `${percentage}%`;
            
            if (percentage >= 80) {
                completed = true;
                
                // Submit progress
                fetch(`/learner/articles/${articleId}/progress`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ progress_percentage: 100 })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        readCompleteBanner.classList.remove('hidden');
                        readProgressBar.style.width = "100%";
                    }
                });
            }
        });

        // 6. Bug report modal
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
            
            fetch(`/learner/articles/${articleId}/error-report`, {
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
    });
</script>
@endpush
@endsection
