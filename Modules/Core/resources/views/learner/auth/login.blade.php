<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion Apprenant | Learn&Quiz</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/learner.css', 'resources/js/learner.js'])

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center bg-slate-50 text-slate-900 dark:bg-zinc-950 dark:text-zinc-50 transition-colors duration-200 antialiased p-4">

    <!-- Card Container -->
    <div class="w-full max-w-md bg-white dark:bg-zinc-900 rounded-3xl shadow-xl border border-slate-100 dark:border-zinc-800 p-8 transition-transform duration-300">
        
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-sky-500/10 mb-4">
                <!-- SVG Icon (Book & Checkmark matching app theme) -->
                <svg class="w-10 h-10 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold bg-gradient-to-r from-sky-500 to-indigo-500 bg-clip-text text-transparent">Learn&Quiz</h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Espace Apprenant immersif</p>
        </div>

        <!-- Form -->
        <form action="{{ route('learner.login.post') }}" method="post" class="space-y-6">
            @csrf
            
            <!-- Login field -->
            <div>
                <label for="login" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-2">Identifiant</label>
                <div class="relative">
                    <input type="text" name="login" id="login" class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all" placeholder="Nom d'utilisateur ou Email" value="{{ old('login') }}" required autofocus>
                </div>
            </div>

            <!-- Password field -->
            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-2">Mot de passe</label>
                <div class="relative">
                    <input type="password" name="password" id="password" class="w-full px-4 py-3 pr-12 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all" placeholder="••••••••" required>
                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-zinc-300">
                        <!-- Eye Icon -->
                        <svg id="eyeIcon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Error Alerts -->
            @error('login_error')
                <div class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 rounded-2xl text-xs font-medium text-red-600 dark:text-red-400 text-center">
                    {{ $message }}
                </div>
            @enderror

            <!-- Submit Button -->
            <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-indigo-500 text-white font-semibold text-sm rounded-2xl hover:opacity-95 shadow-lg shadow-sky-500/10 hover:shadow-sky-500/20 transition-all active:scale-[0.98]">
                Se connecter
            </button>
        </form>
        
        <!-- Bottom Info -->
        <p class="text-center text-xs text-slate-400 dark:text-zinc-500 mt-8">
            Pour toute demande d'accès, contactez votre administrateur de formation.
        </p>
    </div>

    <!-- Toggle Password Visibility Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            togglePasswordBtn.addEventListener('click', () => {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                // Toggle SVG path of the eye icon (with line / without line)
                if (isPassword) {
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    `;
                } else {
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    `;
                }
            });
        });
    </script>
</body>
</html>
