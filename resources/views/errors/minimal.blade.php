{{--
    Gabarit des pages d'erreur (remplace celui du framework) : aux couleurs
    de la marque, en français, sans aucune référence technique.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    <link rel="icon" href="{{ config('branding.logo') }}">
    <style>
        :root { --bg: #f4f6fb; --card: #ffffff; --text: #18181b; --muted: #6b7280; --accent: #0284c7; --border: #e4e4e7; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #09090b; --card: #18181b; --text: #f4f4f5; --muted: #a1a1aa; --accent: #38bdf8; --border: #27272a; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 1.5rem;
               background: var(--bg); color: var(--text);
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: min(100%, 28rem); background: var(--card); border: 1px solid var(--border);
                border-radius: 1.25rem; padding: 2.25rem 2rem; text-align: center;
                box-shadow: 0 10px 30px rgb(0 0 0 / 0.06); }
        .brand { display: inline-flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--text);
                 text-decoration: none; margin-bottom: 1.75rem; }
        .brand img { width: 2.25rem; height: 2.25rem; border-radius: .6rem; }
        .code { font-size: 3.5rem; font-weight: 800; line-height: 1; color: var(--accent); margin: 0; letter-spacing: -.03em; }
        h1 { font-size: 1.25rem; margin: .75rem 0 .5rem; }
        p { color: var(--muted); margin: 0 0 1.75rem; line-height: 1.55; }
        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 2.75rem;
               padding: 0 1.25rem; border-radius: .75rem; font-weight: 600; text-decoration: none;
               border: 1px solid var(--border); color: var(--text); background: transparent; cursor: pointer; font: inherit; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    </style>
</head>
<body>
    <main class="card">
        <a class="brand" href="/">
            <img src="{{ config('branding.logo') }}" alt="">
            <span>{{ config('branding.name') }}</span>
        </a>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <button type="button" class="btn" onclick="history.length > 1 ? history.back() : location.assign('/')">Retour</button>
            <a class="btn btn-primary" href="/">Accueil</a>
        </div>
    </main>
</body>
</html>
