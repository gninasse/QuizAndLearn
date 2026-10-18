@extends('core::layouts.master')

@section('title', "Demandes d'accès")
@section('header', "Demandes d'accès")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Demandes d'accès</li>
@endsection

@section('content')
<div id="access-requests-app"
     data-url-data="{{ route('cores.access-requests.data') }}"
     data-url-base="{{ url('cores/demandes-acces') }}"
     data-currency="{{ $currency }}"
     data-groups='@json($groups)'>

    <div class="card ar-hero mb-3">
        <div class="card-body">
            <div class="ar-kpis d-grid gap-2">
                <div class="ar-kpi"><i class="bi bi-inbox"></i><strong id="kpi-new">0</strong><span>Nouvelles</span></div>
                <div class="ar-kpi"><i class="bi bi-hourglass-split"></i><strong id="kpi-open">0</strong><span>En cours</span></div>
                <div class="ar-kpi"><i class="bi bi-cash-coin"></i><strong id="kpi-paid">0</strong><span>Payées</span></div>
                <div class="ar-kpi"><i class="bi bi-person-check"></i><strong id="kpi-created">0</strong><span>Comptes créés</span></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills mb-3 flex-wrap gap-1" id="status-tabs">
                <li class="nav-item"><button type="button" class="nav-link active" data-status="">Toutes <span class="badge text-bg-secondary ms-1" data-count="all">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="new">Nouvelles <span class="badge text-bg-secondary ms-1" data-count="new">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="contacted">Contactées <span class="badge text-bg-secondary ms-1" data-count="contacted">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="payment_pending">Paiement en attente <span class="badge text-bg-secondary ms-1" data-count="payment_pending">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="paid">Payées <span class="badge text-bg-secondary ms-1" data-count="paid">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="account_created">Compte créé <span class="badge text-bg-secondary ms-1" data-count="account_created">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="rejected">Refusées <span class="badge text-bg-secondary ms-1" data-count="rejected">0</span></button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-status="abandoned">Abandonnées <span class="badge text-bg-secondary ms-1" data-count="abandoned">0</span></button></li>
            </ul>

            <div class="position-relative mb-3">
                <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                <input type="search" id="request-search" class="form-control ps-5" placeholder="Rechercher par nom, téléphone ou e-mail…" aria-label="Rechercher une demande">
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle ar-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Demandeur</th>
                            <th>Téléphone</th>
                            <th>{{ term('Group') }}</th>
                            <th>Statut</th>
                            <th class="text-end">Payé</th>
                            <th>Reçue le</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requests-body">
                        <tr><td colspan="7" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Chargement…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Fiche d'une demande --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="request-details" aria-labelledby="request-details-title" style="width: min(520px, 100vw);">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="request-details-title">Demande d'accès</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body" id="request-details-body"></div>
</div>
@stop

@push('css')
<style>
    .ar-hero { border: 0; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .ar-kpis { grid-template-columns: repeat(4, minmax(5.5rem, 1fr)); }
    @media (max-width: 575.98px) { .ar-kpis { grid-template-columns: repeat(2, 1fr); } }
    .ar-kpi { display: flex; flex-direction: column; align-items: center; padding: .6rem .75rem; border-radius: .75rem; background: var(--bs-tertiary-bg); }
    .ar-kpi i { font-size: 1.1rem; color: var(--bs-primary); }
    .ar-kpi strong { font-size: 1.35rem; line-height: 1.2; }
    .ar-kpi span { font-size: .75rem; color: var(--bs-secondary-color); }
    .ar-table td { white-space: nowrap; }
    .ar-table tbody tr { cursor: pointer; }
    .ar-detail h6 { font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); margin: 1.25rem 0 .5rem; }
    .ar-timeline { list-style: none; padding-left: 0; margin: 0; }
    .ar-timeline li { padding: .4rem 0; border-bottom: 1px solid var(--bs-border-color-translucent); font-size: .875rem; }
</style>
@endpush

@push('js')
<script type="module" src="{{ asset('js/modules/core/access-requests/index.js') }}"></script>
@endpush
