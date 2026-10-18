@extends('core::layouts.master')

@section('title', 'Membres — '.$group->name)
@section('header', 'Membres du groupe')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.groups.index') }}">Groupes</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $group->name }}</li>
@endsection

@section('content')
<div id="group-members-app"
     data-url-data="{{ route('cores.groups.members.data', $group->id) }}"
     data-url-candidates="{{ route('cores.groups.members.candidates', $group->id) }}"
     data-url-learners="{{ route('cores.groups.members.learners.add', $group->id) }}"
     data-url-trainers="{{ route('cores.groups.members.trainers.add', $group->id) }}"
     data-can-manage-trainers="{{ $canManageTrainers ? '1' : '0' }}">

    {{-- En-tête du groupe --}}
    <div class="card gm-hero mb-3">
        <div class="card-body d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <a href="{{ route('cores.groups.index') }}" class="btn btn-sm btn-light" title="Retour à la liste des groupes">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h4 class="mb-0 fw-bold" id="gm-name">{{ $group->name }}</h4>
                    <span id="gm-status"></span>
                </div>
                <p class="text-muted mb-1 small" id="gm-description">{{ $group->description }}</p>
                <p class="text-muted mb-0 small"><i class="bi bi-calendar-range me-1"></i><span id="gm-dates">—</span></p>
            </div>
            <div class="gm-kpis d-grid gap-2">
                <div class="gm-kpi"><i class="bi bi-mortarboard"></i><strong id="kpi-learners">0</strong><span>Apprenants</span></div>
                <div class="gm-kpi"><i class="bi bi-person-badge"></i><strong id="kpi-trainers">0</strong><span>Formateurs</span></div>
                <div class="gm-kpi gm-kpi-warn"><i class="bi bi-pause-circle"></i><strong id="kpi-suspended">0</strong><span>Suspendus</span></div>
                <div class="gm-kpi"><i class="bi bi-journal-check"></i><strong id="kpi-content">0</strong><span>Contenus</span></div>
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-learners" type="button" role="tab">
                <i class="bi bi-mortarboard me-1"></i> Apprenants <span class="badge text-bg-secondary ms-1" id="tab-learners-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-trainers" type="button" role="tab">
                <i class="bi bi-person-badge me-1"></i> Formateurs <span class="badge text-bg-secondary ms-1" id="tab-trainers-count">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content card border-top-0 rounded-top-0">
        {{-- Apprenants --}}
        <div class="tab-pane fade show active card-body" id="tab-learners" role="tabpanel">
            <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                <div class="position-relative flex-grow-1">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    <input type="search" id="learner-search" class="form-control ps-5" placeholder="Rechercher par nom, e-mail, identifiant, matricule ou téléphone…" aria-label="Rechercher un apprenant">
                </div>
                <select id="learner-filter" class="form-select w-auto" aria-label="Filtrer par statut">
                    <option value="all">Tous les statuts</option>
                    <option value="active">Actifs</option>
                    <option value="suspended">Suspendus</option>
                    <option value="disabled">Comptes désactivés</option>
                    <option value="dormant">Sans activité depuis 7 jours</option>
                </select>
                <button type="button" class="btn btn-primary" id="btn-add-learners">
                    <i class="bi bi-person-plus me-1"></i> Ajouter des apprenants
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle gm-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Apprenant</th>
                            <th>Matricule</th>
                            <th class="text-center">Niveau</th>
                            <th class="text-center">Série</th>
                            <th class="text-center">Quiz</th>
                            <th class="text-center">Examens</th>
                            <th>Dernière activité</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="learners-body">
                        <tr><td colspan="9" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Chargement…</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0" id="learners-footer"></p>
        </div>

        {{-- Formateurs --}}
        <div class="tab-pane fade card-body" id="tab-trainers" role="tabpanel">
            @if ($canManageTrainers)
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-primary" id="btn-add-trainers">
                        <i class="bi bi-person-plus me-1"></i> Ajouter un formateur
                    </button>
                </div>
            @endif
            <div class="row g-3" id="trainers-list"></div>
        </div>
    </div>
</div>

{{-- Fiche détaillée d'un apprenant --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="learner-details" aria-labelledby="learner-details-title" style="width: min(440px, 100vw);">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="learner-details-title">Fiche apprenant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body" id="learner-details-body"></div>
</div>

{{-- Ajout de membres --}}
<div class="modal fade" id="add-members-modal" tabindex="-1" aria-labelledby="add-members-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="add-members-title">Ajouter des apprenants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="position-relative mb-3">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    <input type="search" id="candidate-search" class="form-control ps-5" placeholder="Nom, e-mail, matricule…" aria-label="Rechercher">
                </div>
                <div id="candidate-list" class="list-group list-group-flush"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted" id="candidate-selected">0 sélectionné</span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="btn-confirm-add" disabled>
                        <i class="bi bi-check2 me-1"></i> Ajouter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Suspension --}}
<div class="modal fade" id="suspend-modal" tabindex="-1" aria-labelledby="suspend-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="suspend-form">
            <div class="modal-header">
                <h5 class="modal-title" id="suspend-title"><i class="bi bi-pause-circle text-warning me-1"></i> Suspendre <span id="suspend-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    L'apprenant ne pourra plus se connecter au volet apprenant pendant la suspension.
                    Il <strong>reste membre de ses groupes</strong> et conserve son historique.
                </div>
                <label class="form-label fw-semibold">Durée</label>
                <div class="d-flex flex-wrap gap-2 mb-3" id="suspend-presets">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-days="1">1 jour</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-days="3">3 jours</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary active" data-days="7">7 jours</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-days="30">30 jours</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-days="0">Jusqu'à levée manuelle</button>
                </div>
                <div class="mb-3">
                    <label for="suspend-until" class="form-label fw-semibold">Fin de la suspension</label>
                    <input type="datetime-local" class="form-control" id="suspend-until">
                    <div class="form-text">Laissez vide pour une suspension sans date de fin.</div>
                </div>
                <div>
                    <label for="suspend-reason" class="form-label fw-semibold">Motif <span class="text-muted fw-normal">(visible par l'apprenant)</span></label>
                    <textarea class="form-control" id="suspend-reason" rows="2" maxlength="500" placeholder="Ex. absences répétées, comportement…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-pause-circle me-1"></i> Suspendre</button>
            </div>
        </form>
    </div>
</div>
@stop

@push('css')
<style>
    .gm-hero { border: 0; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .gm-kpis { grid-template-columns: repeat(4, minmax(5.5rem, 1fr)); }
    @media (max-width: 575.98px) { .gm-kpis { grid-template-columns: repeat(2, 1fr); } }
    .gm-kpi { display: flex; flex-direction: column; align-items: center; padding: .6rem .75rem; border-radius: .75rem; background: var(--bs-tertiary-bg); }
    .gm-kpi i { font-size: 1.1rem; color: var(--bs-primary); }
    .gm-kpi strong { font-size: 1.35rem; line-height: 1.2; }
    .gm-kpi span { font-size: .75rem; color: var(--bs-secondary-color); }
    .gm-kpi-warn i { color: var(--bs-warning); }
    .gm-table td { white-space: nowrap; }
    .gm-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .gm-row-suspended { background: rgba(var(--bs-warning-rgb), .06); }
    .gm-detail dt { font-weight: 600; color: var(--bs-secondary-color); font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
    .gm-detail dd { margin-bottom: .75rem; }
    #candidate-list .list-group-item { cursor: pointer; }
</style>
@endpush

@push('js')
<script type="module" src="{{ asset('js/modules/core/groups/members.js') }}"></script>
@endpush
