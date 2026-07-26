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
        {{-- Écran de chargement remplacé au boot par la SPA. --}}
        <div class="min-h-dvh flex flex-col items-center justify-center gap-4 text-zinc-400">
            <span class="text-5xl">🎓</span>
            <div class="w-8 h-8 border-3 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>
</body>
</html>
