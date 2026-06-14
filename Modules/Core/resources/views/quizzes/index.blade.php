@extends('core::layouts.master')

@section('title', 'Gestion des Quiz')
@section('header', 'Gestion des Quiz')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Quiz</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des quiz</h3>
    </div>
    <div class="card-body">
        <div id="toolbar" class="d-flex flex-wrap gap-2">
            <button id="btn-add-quiz" class="btn btn-success" data-bs-toggle="tooltip" title="Créer un quiz">
                <i class="fas fa-plus me-1"></i> Nouveau
            </button>
            <button id="btn-edit-quiz" class="btn btn-info text-white" disabled data-bs-toggle="tooltip" title="Modifier le quiz">
                <i class="fas fa-edit me-1"></i> Modifier
            </button>
            <button id="btn-builder-quiz" class="btn btn-warning text-dark" disabled data-bs-toggle="tooltip" title="Concevoir les questions">
                <i class="fas fa-cubes me-1"></i> Questions
            </button>
            <button id="btn-preview-quiz" class="btn btn-primary" disabled data-bs-toggle="tooltip" title="Prévisualiser le quiz">
                <i class="fas fa-eye me-1"></i> Aperçu
            </button>
            <button id="btn-enable-quiz" class="btn btn-outline-success" disabled data-bs-toggle="tooltip" title="Activer le quiz">
                <i class="fas fa-check me-1"></i> Activer
            </button>
            <button id="btn-disable-quiz" class="btn btn-outline-secondary" disabled data-bs-toggle="tooltip" title="Désactiver le quiz">
                <i class="fas fa-ban me-1"></i> Désactiver
            </button>
            <button id="btn-delete-quiz" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer le quiz">
                <i class="fas fa-trash me-1"></i> Supprimer
            </button>
        </div>
        <table id="quizzes-table"
               data-toggle="table"
               data-url="{{ route('cores.quizzes.data') }}"
               data-pagination="true"
               data-side-pagination="server"
               data-search="true"
               data-show-refresh="true"
               data-show-columns="true"
               data-toolbar="#toolbar"
               data-click-to-select="true"
               data-single-select="true"
               data-id-field="id"
               data-page-list="[10, 25, 50, 100]">
            <thead>
                <tr>
                    <th data-field="state" data-radio="true"></th>
                    <th data-field="id" data-sortable="true">ID</th>
                    <th data-field="title" data-sortable="true">Titre</th>
                    <th data-field="description" data-sortable="true">Description</th>
                    <th data-field="duration" data-sortable="true" data-formatter="durationFormatter">Durée</th>
                    <th data-field="passing_score" data-sortable="true" data-formatter="scoreFormatter">Score de réussite</th>
                    <th data-field="questions_count" data-formatter="questionsCountFormatter">Questions</th>
                    <th data-field="groups_list" data-formatter="groupsFormatter">Groupes</th>
                    <th data-field="creator_name" data-sortable="true">Créateur</th>
                    <th data-field="is_active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::quizzes._modal')

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
<style>
    .m-t-small { margin-top: 15px; }
</style>
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>

<script>
    function statusFormatter(value, row, index) {
        return value 
            ? '<span class="badge bg-success">Actif</span>' 
            : '<span class="badge bg-danger">Inactif</span>';
    }

    function durationFormatter(value, row, index) {
        return value 
            ? `<span class="badge bg-secondary"><i class="far fa-clock"></i> ${value} min</span>`
            : '<span class="text-muted">Illimitée</span>';
    }

    function scoreFormatter(value, row, index) {
        return `<span class="badge bg-warning text-dark">${value}%</span>`;
    }

    function questionsCountFormatter(value, row, index) {
        return `<span class="badge bg-primary"><i class="fas fa-question-circle"></i> ${value}</span>`;
    }

    function groupsFormatter(value, row, index) {
        if (!value || value.length === 0) {
            return '<span class="text-muted">Aucun groupe</span>';
        }
        return value.map(g => `<span class="badge bg-info">${g}</span>`).join(' ');
    }
</script>
<script type="module" src="{{ asset('js/modules/core/quizzes/index.js') }}"></script>
@endpush
