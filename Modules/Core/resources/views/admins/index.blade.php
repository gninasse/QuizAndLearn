@extends('core::layouts.master')

@section('title', 'Gestion des Administrateurs')
@section('header', 'Gestion des Administrateurs')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Administrateurs</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des administrateurs</h3>
    </div>
    <div class="card-body">
        <div id="toolbar">
            <button id="btn-add-admin" class="btn btn-primary" data-bs-toggle="tooltip" title="Ajouter un administrateur">
                <i class="fas fa-plus"></i>
            </button>
            <button id="btn-edit-admin" class="btn btn-info" disabled data-bs-toggle="tooltip" title="Modifier">
                <i class="fas fa-edit"></i>
            </button>
            <button id="btn-delete-admin" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
            <button id="btn-reset-password" class="btn btn-warning" disabled data-bs-toggle="tooltip" title="Réinitialiser MDP">
                <i class="fas fa-key"></i>
            </button>
            <button id="btn-enable-admin" class="btn btn-success" disabled data-bs-toggle="tooltip" title="Activer">
                <i class="fas fa-check"></i>
            </button>
            <button id="btn-disable-admin" class="btn btn-secondary" disabled data-bs-toggle="tooltip" title="Désactiver">
                <i class="fas fa-ban"></i>
            </button>
        </div>
        <table id="admins-table"
               data-toggle="table"
               data-url="{{ route('cores.admins.data') }}"
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
                    <th data-field="last_name" data-sortable="true">Nom</th>
                    <th data-field="name" data-sortable="true">Prénom</th>
                    <th data-field="user_name" data-sortable="true">Nom d'utilisateur</th>
                    <th data-field="email" data-sortable="true">Email</th>
                    <th data-field="phone" data-sortable="true">Téléphone</th>
                    <th data-field="roles_list" data-formatter="rolesFormatter">Rôle</th>
                    <th data-field="is_active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::admins._modal')

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
    window.emptyAvatar = "{{ asset('media/user_avatar.svg') }}";

    function statusFormatter(value, row, index) {
        return value 
            ? '<span class="badge bg-success">Actif</span>' 
            : '<span class="badge bg-danger">Bloqué</span>';
    }

    function rolesFormatter(value, row, index) {
        if (!value || value.length === 0) return '-';
        return value.map(role => {
            let badgeClass = role === 'super-admin' ? 'bg-danger' : 'bg-primary';
            return `<span class="badge ${badgeClass}">${role}</span>`;
        }).join(' ');
    }
</script>
<script type="module" src="{{ asset('js/modules/core/admins/index.js') }}"></script>
@endpush
