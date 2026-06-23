@extends('core::layouts.learner')

@section('title', 'Profil | Learn&Quiz')

@section('content')
<div class="space-y-8 max-w-2xl mx-auto">
    
    <!-- User Top Card -->
    <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm flex flex-col sm:flex-row items-center gap-6">
        <img src="{{ $user->avatar_url }}" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-2 border-sky-500 shadow-md">
        
        <div class="text-center sm:text-left space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-50">{{ $user->full_name }}</h1>
            <p class="text-xs text-slate-400 dark:text-zinc-500">Matricule : <strong class="font-semibold">{{ $learner->matricule }}</strong></p>
            <p class="text-xs font-semibold text-sky-600 dark:text-sky-400">
                @if($learner->groups->count() > 0)
                    Groupes : {{ $learner->groups->pluck('name')->join(', ') }}
                @else
                    Aucun groupe assigné
                @endif
            </p>
        </div>
    </div>

    <!-- Gamification Streaks & Stats -->
    <div class="grid grid-cols-2 gap-4">
        <div class="p-5 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm text-center">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Streak Actuel</p>
            <p class="text-3xl font-extrabold text-amber-500 mt-2">🔥 {{ $xp->current_streak }} {{ $xp->current_streak > 1 ? 'jours' : 'jour' }}</p>
        </div>
        <div class="p-5 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm text-center">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Record de Streak</p>
            <p class="text-3xl font-extrabold text-sky-500 mt-2">🏆 {{ $xp->longest_streak }} {{ $xp->longest_streak > 1 ? 'jours' : 'jour' }}</p>
        </div>
    </div>

    <!-- Badges Collection -->
    <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-4">
        <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-50">Vos Badges</h3>
        <p class="text-xs text-slate-400 dark:text-zinc-500">Débloquez de nouveaux titres en complétant vos activités.</p>
        
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-4 pt-2">
            @php
                $allBadges = \Modules\Core\Models\Badge::all();
                $earnedBadgeIds = $badges->pluck('id')->toArray();
            @endphp
            @forelse($allBadges as $badge)
                @php
                    $isEarned = in_array($badge->id, $earnedBadgeIds);
                    $earnedPivot = $isEarned ? $badges->firstWhere('id', $badge->id)->pivot : null;
                @endphp
                <div class="flex flex-col items-center p-3 border border-slate-100 dark:border-zinc-800 rounded-2xl text-center space-y-1 select-none {{ $isEarned ? 'bg-indigo-50/10 border-indigo-200/40' : 'opacity-40 grayscale' }}" title="{{ $badge->description }}">
                    <span class="text-3xl">{{ $badge->icon ?: '🏅' }}</span>
                    <span class="text-[10px] font-extrabold text-slate-700 dark:text-zinc-200 truncate w-full">{{ $badge->name }}</span>
                    @if($isEarned && $earnedPivot)
                        <span class="text-[8px] text-slate-400 font-semibold">{{ \Carbon\Carbon::parse($earnedPivot->earned_at)->format('d/m/y') }}</span>
                    @else
                        <span class="text-[8px] text-slate-400 font-semibold">Verrouillé</span>
                    @endif
                </div>
            @empty
                <p class="col-span-full text-center text-xs text-slate-400 dark:text-zinc-500">Aucun badge configuré dans le système.</p>
            @endforelse
        </div>
    </div>

    <!-- Preferences form toggles -->
    <div class="p-6 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-3xl shadow-sm space-y-6">
        <h3 class="font-bold text-lg text-slate-900 dark:text-zinc-50">Préférences de l'application</h3>
        
        <div class="space-y-4">
            
            <!-- Dark / Light Theme toggle -->
            <div class="flex justify-between items-center py-2">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-zinc-200">Thème Sombre</p>
                    <p class="text-[11px] text-slate-400 dark:text-zinc-500">Activer l'affichage sombre</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer select-none">
                    <input type="checkbox" id="theme-toggle" class="sr-only peer" {{ $preferences->theme === 'dark' ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-slate-200 dark:bg-zinc-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-sky-500/20 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                </label>
            </div>

            <!-- Font size scale -->
            <div class="flex justify-between items-center py-2 border-t border-slate-50 dark:border-zinc-800/80">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-zinc-200">Taille du texte</p>
                    <p class="text-[11px] text-slate-400 dark:text-zinc-500">Ajuster la lisibilité</p>
                </div>
                <select id="font-size-select" class="bg-slate-50 dark:bg-zinc-800 border border-slate-250 dark:border-zinc-700 rounded-xl px-3 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                    <option value="small" {{ $preferences->font_size === 'small' ? 'selected' : '' }}>Petit</option>
                    <option value="medium" {{ $preferences->font_size === 'medium' ? 'selected' : '' }}>Moyen</option>
                    <option value="large" {{ $preferences->font_size === 'large' ? 'selected' : '' }}>Grand</option>
                </select>
            </div>

            <!-- Sound effects toggle -->
            <div class="flex justify-between items-center py-2 border-t border-slate-50 dark:border-zinc-800/80">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-zinc-200">Effets Sonores</p>
                    <p class="text-[11px] text-slate-400 dark:text-zinc-500">Sons de validation d'activités</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer select-none">
                    <input type="checkbox" id="sound-toggle" class="sr-only peer" {{ $preferences->sound_enabled ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-slate-200 dark:bg-zinc-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-sky-500/20 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                </label>
            </div>

            <!-- Language selector -->
            <div class="flex justify-between items-center py-2 border-t border-slate-50 dark:border-zinc-800/80">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-zinc-200">Langue</p>
                    <p class="text-[11px] text-slate-400 dark:text-zinc-500">Langue d'interface</p>
                </div>
                <select id="locale-select" class="bg-slate-50 dark:bg-zinc-800 border border-slate-250 dark:border-zinc-700 rounded-xl px-3 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                    <option value="fr" {{ $preferences->locale === 'fr' ? 'selected' : '' }}>Français</option>
                    <option value="en" {{ $preferences->locale === 'en' ? 'selected' : '' }}>English</option>
                </select>
            </div>

        </div>
    </div>
</div>

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('theme-toggle');
        const soundToggle = document.getElementById('sound-toggle');
        const fontSizeSelect = document.getElementById('font-size-select');
        const localeSelect = document.getElementById('locale-select');

        // Toggle Theme immediately in DOM & Post to Server
        themeToggle.addEventListener('change', () => {
            const isDark = themeToggle.checked;
            const themeVal = isDark ? 'dark' : 'light';
            
            // DOM switch
            const html = document.documentElement;
            if (isDark) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }

            savePreferences({ theme: themeVal });
        });

        soundToggle.addEventListener('change', () => {
            savePreferences({ sound_enabled: soundToggle.checked ? 1 : 0 });
        });

        fontSizeSelect.addEventListener('change', () => {
            savePreferences({ font_size: fontSizeSelect.value });
        });

        localeSelect.addEventListener('change', () => {
            savePreferences({ locale: localeSelect.value });
        });

        function savePreferences(data) {
            fetch('/learner/profil/preferences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            });
        }
    });
</script>
@endpush
@endsection
