{{-- Politique de confidentialité du mode découverte : page publique autonome. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Politique de confidentialité · {{ config('branding.name') }}</title>
    <link rel="icon" href="{{ config('branding.logo') }}">
    <style>
        :root { --bg: #f4f6fb; --card: #ffffff; --text: #18181b; --muted: #52525b; --accent: #0284c7; --border: #e4e4e7; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #09090b; --card: #18181b; --text: #f4f4f5; --muted: #a1a1aa; --accent: #38bdf8; --border: #27272a; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; padding: 1.5rem; background: var(--bg); color: var(--text);
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: min(100%, 44rem); margin: 0 auto; background: var(--card); border: 1px solid var(--border);
                border-radius: 1.25rem; padding: 2rem; box-shadow: 0 10px 30px rgb(0 0 0 / 0.06); }
        .brand { display: inline-flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--text);
                 text-decoration: none; margin-bottom: 1.5rem; }
        .brand img { width: 2.25rem; height: 2.25rem; border-radius: .6rem; }
        h1 { font-size: 1.5rem; margin: 0 0 1rem; }
        h2 { font-size: 1.05rem; margin: 1.5rem 0 .4rem; color: var(--accent); }
        p, li { color: var(--muted); line-height: 1.6; }
        .text { white-space: pre-line; color: var(--muted); line-height: 1.6; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <main class="card">
        <a class="brand" href="/">
            <img src="{{ config('branding.logo') }}" alt="">
            <span>{{ config('branding.name') }}</span>
        </a>
        <h1>Politique de confidentialité</h1>

        @if ($text)
            <div class="text">{{ $text }}</div>
        @else
            <p>Cette page explique comment {{ config('branding.name') }} traite vos informations lorsque vous utilisez le mode découverte ou demandez un accès.</p>

            <h2>Données collectées</h2>
            <p>Lorsque vous faites une demande d'accès, nous recueillons uniquement les informations que vous saisissez : votre nom, votre numéro de téléphone, éventuellement votre adresse e-mail, et la formation qui vous intéresse.</p>

            <h2>Utilisation</h2>
            <p>Ces informations servent uniquement à vous recontacter, à confirmer votre inscription et à créer votre compte. Elles ne sont ni vendues ni cédées à des tiers.</p>

            <h2>Paiement</h2>
            <p>Le paiement est effectué par vos soins auprès de l'organisme. Nous ne vous demandons jamais votre code secret ni vos identifiants Mobile Money.</p>

            <h2>Conservation</h2>
            <p>Les demandes refusées ou abandonnées sont supprimées automatiquement après une durée limitée. Vous pouvez demander la suppression de vos données à tout moment.</p>

            <h2>Contact</h2>
            <p>
                @if (config('branding.support_email'))
                    Pour toute question, écrivez-nous à <a href="mailto:{{ config('branding.support_email') }}">{{ config('branding.support_email') }}</a>.
                @else
                    Pour toute question, contactez l'équipe de {{ config('branding.name') }}.
                @endif
            </p>
        @endif
    </main>
</body>
</html>
