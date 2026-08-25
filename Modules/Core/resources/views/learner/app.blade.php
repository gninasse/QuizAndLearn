<!DOCTYPE html>
<html lang="fr" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0284c7">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>Learn&Quiz — Espace Apprenant</title>

    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    {{-- Anti-flash de thème : appliqué avant le premier rendu. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('learner-theme');
            var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (dark) document.documentElement.classList.add('dark');
        })();
    </script>

    @vite(['resources/css/learner.css', 'resources/js/learner/main.ts'])
</head>
<body class="bg-zinc-50 dark:bg-zinc-950">
    <div id="app">
        {{-- Squelette de chargement remplacé au boot par la SPA. --}}
        <div class="min-h-dvh bg-zinc-50 dark:bg-zinc-950 flex flex-col">
            <div class="h-14 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 flex items-center px-4 gap-3">
                <span class="text-xl">🎓</span>
                <div class="skeleton h-4 w-32 rounded-md"></div>
                <div class="ml-auto skeleton h-8 w-8 rounded-full"></div>
            </div>
            <div class="flex-1 max-w-5xl w-full mx-auto px-4 py-5 flex flex-col gap-4">
                <div class="skeleton h-40 rounded-3xl"></div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="skeleton h-28 rounded-2xl"></div>
                    <div class="skeleton h-28 rounded-2xl"></div>
                    <div class="skeleton h-28 rounded-2xl hidden sm:block"></div>
                    <div class="skeleton h-28 rounded-2xl hidden sm:block"></div>
                </div>
                <div class="skeleton h-4 w-24 rounded-md mt-2"></div>
                <div class="skeleton h-20 rounded-2xl"></div>
                <div class="skeleton h-20 rounded-2xl"></div>
            </div>
        </div>
    </div>
</body>
</html>
