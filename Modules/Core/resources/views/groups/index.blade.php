@extends('core::layouts.master')

@section('title', 'Gestion des Groupes')
@section('header', 'Gestion des Groupes')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Groupes</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des groupes de formation</h3>
    </div>
    <div class="card-body">
        <div id="toolbar">
            <button id="btn-add-group" class="btn btn-primary" data-bs-toggle="tooltip" title="Ajouter un groupe">
                <i class="fas fa-plus"></i>
            </button>
            <button id="btn-edit-group" class="btn btn-info" disabled data-bs-toggle="tooltip" title="Modifier">
                <i class="fas fa-edit"></i>
            </button>
            <button id="btn-delete-group" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
            <button id="btn-members-group" class="btn btn-warning" disabled data-bs-toggle="tooltip" title="Gérer les membres">
                <i class="fas fa-users-cog"></i> Membres
            </button>
            <button id="btn-enable-group" class="btn btn-success" disabled data-bs-toggle="tooltip" title="Activer">
                <i class="fas fa-check"></i>
            </button>
            <button id="btn-disable-group" class="btn btn-secondary" disabled data-bs-toggle="tooltip" title="Désactiver">
                <i class="fas fa-ban"></i>
            </button>
        </div>
        <table id="groups-table"
               data-toggle="table"
               data-url="{{ route('cores.groups.data') }}"
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
                    <th data-field="name" data-sortable="true">Nom</th>
                    <th data-field="description" data-sortable="true">Description</th>
                    <th data-field="start_date_formatted" data-sortable="true">Date début</th>
                    <th data-field="end_date_formatted" data-sortable="true">Date fin</th>
                    <th data-field="trainers_count" data-formatter="trainersCountFormatter">Formateurs</th>
                    <th data-field="learners_count" data-formatter="learnersCountFormatter">Apprenants</th>
                    <th data-field="is_active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::groups._modal')

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

    function trainersCountFormatter(value, row, index) {
        return `<span class="badge bg-info"><i class="fas fa-chalkboard-teacher"></i> ${value}</span>`;
    }

    function learnersCountFormatter(value, row, index) {
        return `<span class="badge bg-black text-white"><i class="fas fa-user-graduate"></i> ${value}</span>`;
    }
</script>
<script type="module" src="{{ asset('js/modules/core/groups/index.js') }}"></script>
@endpush
