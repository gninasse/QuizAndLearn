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
                <svg viewBox="0 0 64 64" style="width:32px;height:32px;border-radius:8px" aria-hidden="true"><defs><linearGradient id="lq-boot" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0ea5e9"/><stop offset="55%" stop-color="#2563eb"/><stop offset="100%" stop-color="#4f46e5"/></linearGradient></defs><rect width="64" height="64" rx="14" fill="url(#lq-boot)"/><path d="M32 16 L54 26 L32 36 L10 26 Z" fill="#fff"/><path d="M20 31.5 V40 q0 5 12 5 t12 -5 V31.5 L32 37 Z" fill="#fff" opacity="0.92"/><path d="M53 27 V38" stroke="#fbbf24" stroke-width="2.6" stroke-linecap="round"/><circle cx="53" cy="40.5" r="3.2" fill="#fbbf24"/></svg>
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
