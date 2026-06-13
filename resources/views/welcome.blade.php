<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Parc Informatique - CHU-YO Fondation</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/all.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.min.css') }}"> 
    <link rel="stylesheet" href="{{ asset('plugins/tools/tools.css') }}"> 
    
    <style>
        :root {
            --navy: #001f3f;
            --dark-blue: #001a35;
            --dark-gray: #212529;
            --medium-gray: #343a40;
            --light-gray-text: #adb5bd;
            --white-text: #f8f9fa;
            --emerald: #01D28E;
        }
        html { scroll-behavior: smooth; }
        body { background-color: var(--dark-gray); }

        .content-wrapper { background-color: var(--dark-gray); color: var(--white-text); }
        .logo-icon { margin-right: 8px; color: var(--emerald); }
        .btn-success { background-color: var(--emerald); border-color: var(--emerald); color: var(--navy); font-weight: bold; }
        .btn-success:hover { background-color: #01b87a; border-color: #01b87a; }
        
        .section-title { font-weight: 700; color: var(--white-text); margin-bottom: 30px; }
        .text-emerald { color: var(--emerald); }
        .text-muted { color: var(--light-gray-text) !important; }

        /* Navigation */
        .navbar-dark .navbar-nav .nav-link { color: rgba(255,255,255,.8); transition: color 0.2s; }
        .navbar-dark .navbar-nav .nav-link:hover { color: #fff; }

        /* Hero Section */
        .hero-section { background-color: var(--dark-blue); padding: 80px 20px; text-align: center; border-bottom: 1px solid var(--medium-gray); }
        .hero-section h1 { font-size: 3.2rem; font-weight: 700; color: #fff; }
        .hero-section p.lead { font-size: 1.3rem; max-width: 800px; margin: 20px auto; color: rgba(255,255,255,.9); }
        
        /* Sections */
        .section { padding: 70px 20px; border-bottom: 1px solid var(--medium-gray); }
        .section.bg-darker { background-color: var(--dark-blue); }

        /* Detailed Features Section */
        .feature-item { margin-bottom: 50px; }
        .feature-item .feature-icon-lg { font-size: 8rem; color: var(--emerald); opacity: 0.3; }
        .feature-item h3 { font-weight: 600; color: var(--white-text); }
        
        /* Benefits Section */
        .benefit-card { background-color: var(--medium-gray); border: 1px solid #495057; height: 100%; }
        .benefit-card .card-icon { font-size: 2.5rem; color: var(--emerald); }

        /* How it works Section */
        .step-icon {
            width: 80px; height: 80px;
            background-color: var(--emerald);
            color: var(--dark-blue);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; margin: 0 auto 15px auto;
            border: 4px solid var(--medium-gray);
        }
        .step-connector {
            position: absolute; top: 40px; left: 100%;
            width: 100%; height: 2px;
            background-color: var(--medium-gray);
            z-index: -1;
        }

        /* CTA Section */
        .cta-section { background: var(--emerald); color: var(--dark-blue); padding: 60px 20px; }
        .cta-section h2, .cta-section .lead { color: var(--dark-blue); }
        .cta-section .btn { background-color: var(--dark-blue); color: white; }

        /* Footer */
        .main-footer { background-color: var(--dark-blue); color: var(--light-gray-text); border-top: 1px solid var(--medium-gray); }
        .main-footer a { color: var(--emerald); }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

    <!-- Barre de navigation -->
    <nav class="main-header navbar navbar-expand-md navbar-dark sticky-top" style="background-color: var(--dark-blue);">
        <div class="container">
            <a href="#" class="navbar-brand">
                <i class="fas fa-cogs logo-icon"></i>
                <span class="brand-text font-weight-bold">CHU-YO | <span class="font-weight-light">Gestion de Parc</span></span>
            </a>
            <button class="navbar-toggler order-1" type="button" data-toggle="collapse" data-target="#navbarCollapse"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse order-3" id="navbarCollapse">
                <ul class="navbar-nav"> 
                    <li class="nav-item"><a href="#how-it-works" class="nav-link">Comment ça marche ?</a></li>
                </ul>
            </div>
            <ul class="order-1 order-md-3 navbar-nav navbar-no-expand ml-auto">
                <li class="nav-item">
                    @auth
                        <a href="{{ route('cores.dashboard') }}" class="btn btn-outline-light">Tableau de Bord</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-light">Connexion</a>
                    @endauth
                </li>
            </ul>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="hero-section">
            <div class="container">
                <h1>Visibilité totale et maîtrise complète de votre parc informatique</h1>
                <p class="lead">Le module de Gestion de Parc du CHU-YO transforme la manière dont vous gérez vos actifs informatiques, de l'acquisition au retrait, en passant par la maintenance et la conformité.</p>
                @auth
                    <a href="{{ route('cores.dashboard') }}" class="btn btn-lg btn-success">Accéder au tableau de bord</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-lg btn-success">Connectez-vous</a>
                @endauth
            </div>
        </div>

        <section id="features" class="section">
            <div class="container">
                <div class="text-center"><h2 class="section-title">Une solution conçue pour les environnements exigeants</h2></div>
                <div class="row align-items-center feature-item">
                    <div class="col-md-7">
                        <h3><i class="fas fa-clipboard-list text-emerald mr-2"></i>Inventaire et Découverte Automatisés</h3>
                        <p class="text-muted">Notre outil scanne en permanence votre réseau pour découvrir et identifier chaque actif. Postes de travail, serveurs, périphériques, logiciels... Rien n'est oublié.</p>
                    </div>
                    <div class="col-md-5 text-center"><i class="fas fa-sitemap feature-icon-lg"></i></div>
                </div>
                <div class="row align-items-center feature-item">
                    <div class="col-md-5 text-center"><i class="fas fa-sync-alt feature-icon-lg"></i></div>
                    <div class="col-md-7">
                        <h3><i class="fas fa-recycle text-emerald mr-2"></i>Gestion du Cycle de Vie complet</h3>
                        <p class="text-muted">Suivez chaque actif de son achat à sa mise au rebut. Gérez les garanties, planifiez les renouvellements et prenez des décisions éclairées sur la base de l'âge, de l'état et du coût total de possession de votre matériel.</p>
                    </div>
                </div>
            </div>
        </section>
        <section id="benefits" class="section bg-darker">
            <div class="container">
                <div class="text-center"><h2 class="section-title">Les avantages concrets pour votre établissement</h2></div>
                <div class="row">
                    <div class="col-md-6 col-lg-3 d-flex"><div class="card card-body text-center benefit-card"><div class="card-icon mx-auto"><i class="fas fa-coins"></i></div><h5 class="mt-3">Réduction des Coûts</h5><p class="text-muted small">Optimisez les achats, éliminez les licences inutilisées et prolongez la vie de vos équipements.</p></div></div>
                    <div class="col-md-6 col-lg-3 d-flex"><div class="card card-body text-center benefit-card"><div class="card-icon mx-auto"><i class="fas fa-shield-alt"></i></div><h5 class="mt-3">Sécurité Accrue</h5><p class="text-muted small">Détectez les appareils non autorisés et assurez-vous que tous les logiciels sont à jour et patchés.</p></div></div>
                    <div class="col-md-6 col-lg-3 d-flex"><div class="card card-body text-center benefit-card"><div class="card-icon mx-auto"><i class="fas fa-chart-pie"></i></div><h5 class="mt-3">Aide à la Décision</h5><p class="text-muted small">Utilisez des rapports clairs pour planifier vos budgets et vos investissements technologiques.</p></div></div>
                    <div class="col-md-6 col-lg-3 d-flex"><div class="card card-body text-center benefit-card"><div class="card-icon mx-auto"><i class="fas fa-clock"></i></div><h5 class="mt-3">Gain de Temps</h5><p class="text-muted small">Automatisez les tâches répétitives et libérez vos équipes IT pour des projets à plus forte valeur ajoutée.</p></div></div>
                </div>
            </div>
        </section> 
        
        <section id="cta" class="cta-section text-center">
            <div class="container">
                <h2 class="mb-3">Transformons ensemble la gestion de notre parc informatique</h2>
                <p class="lead mb-4">Vous avez des accès a cette plateforme?</p>
                @auth
                    <a href="{{ route('cores.dashboard') }}" class="btn btn-lg btn-light"><i class="fas fa-tachometer-alt mr-2"></i>Tableau de bord</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-lg btn-light"><i class="fas fa-user mr-2"></i>Connectez-vous</a>
                @endauth
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <div class="container">
            <div class="float-right d-none d-sm-inline">Fiabilité, Sécurité et Performance.</div>
            <strong>Copyright &copy; 2025 <a href="#">CHU-YO</a>.</strong>
        </div>
    </footer>
</div>

<script src="{{ asset('plugins/jquery/jquery-3.7.1.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.js') }}"></script>
<script src="{{ asset('adminlte/js/adminlte.js') }}"></script>
</body>
</html>
