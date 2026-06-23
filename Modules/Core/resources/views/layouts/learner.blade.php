<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#0284c7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
    <title>@yield('title', 'Learn&Quiz')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    @vite(['resources/css/learner.css', 'resources/js/learner.js'])
    @stack('css')

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 dark:bg-zinc-950 dark:text-zinc-50 transition-colors duration-200 antialiased">

    <!-- Connectivité Hors Ligne Banner -->
    <div id="offline-banner" class="hidden fixed top-0 left-0 right-0 bg-red-600 text-white text-center py-2 text-sm font-medium z-50 animate-bounce">
        <span>⚠️ Mode hors ligne - Les données sont chargées depuis le cache local</span>
    </div>

    <!-- Main Outer Container (Centering on Desktop) -->
    <div class="min-h-full mx-auto max-w-[1024px] bg-white dark:bg-zinc-900 shadow-xl min-h-screen flex flex-col md:flex-row pb-16 md:pb-0 relative border-x border-slate-100 dark:border-zinc-800">
        
        <!-- Sidebar Navigation (Desktop & Tablet >= 768px) -->
        <aside class="hidden md:flex flex-col w-64 bg-slate-900 text-white dark:bg-zinc-950 border-r border-slate-800 dark:border-zinc-800 shrink-0">
            <div class="p-6">
                <span class="text-xl font-bold tracking-wider bg-gradient-to-r from-sky-400 to-indigo-400 bg-clip-text text-transparent">Learn&Quiz</span>
            </div>
            
            <nav class="flex-1 px-4 space-y-1">
                <a href="{{ route('learner.dashboard') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl hover:bg-slate-800 transition-colors {{ request()->routeIs('learner.dashboard') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Accueil
                </a>
                <a href="{{ route('learner.quizzes.index') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl hover:bg-slate-800 transition-colors {{ request()->routeIs('learner.quizzes.*') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Quiz
                </a>
                <a href="{{ route('learner.articles.index') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl hover:bg-slate-800 transition-colors {{ request()->routeIs('learner.articles.*') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Articles
                </a>
                <a href="{{ route('learner.reviser') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl hover:bg-slate-800 transition-colors {{ request()->routeIs('learner.reviser') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Réviser
                </a>
                <a href="{{ route('learner.profil') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl hover:bg-slate-800 transition-colors {{ request()->routeIs('learner.profil') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profil
                </a>
            </nav>

            <!-- User Info and Logout in Sidebar footer -->
            @auth
            <div class="p-4 border-t border-slate-800 dark:border-zinc-800">
                <div class="flex items-center gap-3 mb-4">
                    <img src="{{ auth()->user()->avatar_url }}" alt="Avatar" class="w-10 h-10 rounded-full object-cover">
                    <div class="truncate">
                        <p class="text-sm font-semibold truncate">{{ auth()->user()->full_name }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <form action="{{ route('learner.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold bg-red-950 text-red-400 rounded-xl hover:bg-red-900 hover:text-red-300 transition-colors">
                        Déconnexion
                    </button>
                </form>
            </div>
            @endauth
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Header bar for Mobile (Home / Navigation title) -->
            <header class="md:hidden flex items-center justify-between px-6 py-4 bg-white dark:bg-zinc-900 border-b border-slate-100 dark:border-zinc-800 sticky top-0 z-30">
                <span class="text-xl font-bold bg-gradient-to-r from-sky-500 to-indigo-500 bg-clip-text text-transparent">Learn&Quiz</span>
                @auth
                    <a href="{{ route('learner.profil') }}">
                        <img src="{{ auth()->user()->avatar_url }}" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-sky-500">
                    </a>
                @endauth
            </header>

            <!-- Main Content Slot -->
            <main class="flex-1 p-6 overflow-y-auto">
                @yield('content')
            </main>
        </div>

        <!-- Bottom Tab Navigation (Mobile < 768px) -->
        <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-zinc-900 border-t border-slate-200 dark:border-zinc-800 flex justify-around items-center h-16 px-4 z-40 max-w-[1024px] mx-auto shadow-lg">
            <a href="{{ route('learner.dashboard') }}" class="flex flex-col items-center justify-center flex-1 py-1 transition-colors {{ request()->routeIs('learner.dashboard') ? 'text-sky-600' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="text-[10px] mt-0.5 font-medium">Accueil</span>
            </a>
            <a href="{{ route('learner.quizzes.index') }}" class="flex flex-col items-center justify-center flex-1 py-1 transition-colors {{ request()->routeIs('learner.quizzes.*') ? 'text-sky-600' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span class="text-[10px] mt-0.5 font-medium">Quiz</span>
            </a>
            <a href="{{ route('learner.articles.index') }}" class="flex flex-col items-center justify-center flex-1 py-1 transition-colors {{ request()->routeIs('learner.articles.*') ? 'text-sky-600' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span class="text-[10px] mt-0.5 font-medium">Articles</span>
            </a>
            <a href="{{ route('learner.reviser') }}" class="flex flex-col items-center justify-center flex-1 py-1 transition-colors {{ request()->routeIs('learner.reviser') ? 'text-sky-600' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <span class="text-[10px] mt-0.5 font-medium">Réviser</span>
            </a>
            <a href="{{ route('learner.profil') }}" class="flex flex-col items-center justify-center flex-1 py-1 transition-colors {{ request()->routeIs('learner.profil') ? 'text-sky-600' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="text-[10px] mt-0.5 font-medium">Profil</span>
            </a>
        </nav>

    </div>

    <!-- Script for offline tracking -->
    <script>
        const offlineBanner = document.getElementById('offline-banner');

        function updateOnlineStatus() {
            if (navigator.onLine) {
                offlineBanner.classList.add('hidden');
            } else {
                offlineBanner.classList.remove('hidden');
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus(); // Check immediately on load
    </script>
    @stack('js')
</body>
</html>
