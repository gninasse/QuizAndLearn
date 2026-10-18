@extends('core::layouts.master')

@section('title', 'Tableau de bord')

@section('header', 'Tableau de bord')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tableau de bord</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('plugins/apexcharts/apexcharts.css') }}" />
@endpush

@section('content')
    <div id="kpi-app"
         data-url-kpis="{{ route('cores.dashboard.kpis') }}"
         data-url-export="{{ route('cores.dashboard.export', ['table' => '__TABLE__']) }}"
         data-url-members="{{ route('cores.groups.members.page', ['id' => '__ID__']) }}">

        {{-- Filtres --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="kpi-period" class="form-label mb-1">Période</label>
                        <select id="kpi-period" class="form-select">
                            <option value="7">7 jours</option>
                            <option value="30" selected>30 jours</option>
                            <option value="90">90 jours</option>
                            <option value="custom">Personnalisée</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 kpi-custom d-none">
                        <label for="kpi-from" class="form-label mb-1">Du</label>
                        <input type="date" id="kpi-from" class="form-control">
                    </div>
                    <div class="col-6 col-md-2 kpi-custom d-none">
                        <label for="kpi-to" class="form-label mb-1">Au</label>
                        <input type="date" id="kpi-to" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="kpi-group" class="form-label mb-1">{{ term('Group') }}</label>
                        <select id="kpi-group" class="form-select">
                            <option value="">{{ term('fem_e') === 'e' ? 'Toutes les ' : 'Tous les ' }}{{ term('groups') }}</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                        <button type="button" id="kpi-apply" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-1"></i>Actualiser
                        </button>
                        <span id="kpi-loading" class="spinner-border spinner-border-sm text-primary d-none" role="status" aria-label="Chargement"></span>
                    </div>
                </div>
            </div>
        </div>

        <div id="kpi-error" class="alert alert-danger d-none" role="alert"></div>

        {{-- Tuiles --}}
        <div class="row">
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small-box text-bg-primary">
                    <div class="inner">
                        <h3><span id="kpi-active-7d">—</span> <i id="kpi-active-7d-trend" class="bi bi-dash fs-5"></i></h3>
                        <p>Actifs 7 j</p>
                    </div>
                    <i class="small-box-icon bi bi-people"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small-box text-bg-info">
                    <div class="inner"><h3 id="kpi-active-30d">—</h3><p>Actifs 30 j</p></div>
                    <i class="small-box-icon bi bi-person-check"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small-box text-bg-success">
                    <div class="inner"><h3 id="kpi-activation">—</h3><p>Activation</p></div>
                    <i class="small-box-icon bi bi-rocket-takeoff"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small-box text-bg-warning">
                    <div class="inner"><h3 id="kpi-retention">—</h3><p>Rétention 30 j</p></div>
                    <i class="small-box-icon bi bi-arrow-repeat"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small-box text-bg-secondary">
                    <div class="inner"><h3 id="kpi-quiz-score">—</h3><p>Score moyen quiz</p></div>
                    <i class="small-box-icon bi bi-patch-question"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small-box text-bg-danger">
                    <div class="inner"><h3 id="kpi-exam-note">—</h3><p>Moyenne examens</p></div>
                    <i class="small-box-icon bi bi-journal-check"></i>
                </div>
            </div>
        </div>

        {{-- Graphiques --}}
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-graph-up me-2"></i>Apprenants actifs par semaine</h3></div>
                    <div class="card-body"><div id="kpi-weekly-chart"></div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-bar-chart-steps me-2"></i>Complétion des contenus</h3></div>
                    <div class="card-body">
                        <div id="kpi-completion-chart"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 kpi-export" data-table="completion">
                            <i class="bi bi-filetype-csv me-1"></i>Exporter CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tableaux --}}
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><i class="bi bi-x-octagon me-2"></i>Questions les plus ratées</h3>
                        <button type="button" class="btn btn-outline-secondary btn-sm kpi-export" data-table="failed_questions">
                            <i class="bi bi-filetype-csv me-1"></i>Exporter CSV
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="kpi-failed-table" class="table table-striped align-middle mb-0">
                                <thead><tr><th>Question</th><th>Quiz</th><th class="text-end">Réponses</th><th class="text-end">Échec</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><i class="bi bi-bell me-2"></i>Apprenants à relancer</h3>
                        <button type="button" class="btn btn-outline-secondary btn-sm kpi-export" data-table="inactive">
                            <i class="bi bi-filetype-csv me-1"></i>Exporter CSV
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="kpi-inactive-table" class="table table-striped align-middle mb-0">
                                <thead><tr><th>Apprenant</th><th>Matricule</th><th>Dernière activité</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Incidents / Mode découverte --}}
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-shield-exclamation me-2"></i>Incidents d'examen</h3></div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">Captures d'écran détectées <strong id="kpi-inc-screenshots">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between">Tentatives de capture en examen <strong id="kpi-inc-captures">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between">Sorties de navigation <strong id="kpi-inc-navigation">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between">Examens annulés <strong id="kpi-inc-cancelled">—</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none" id="kpi-showcase-col">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-stars me-2"></i>Mode découverte</h3></div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">Demandes d'accès <strong id="kpi-sc-requests">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between">Comptes créés <strong id="kpi-sc-converted">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between">Taux de conversion <strong id="kpi-sc-rate">—</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script src="{{ asset('plugins/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('js/modules/core/dashboard/kpis.js') }}"></script>
@endpush
