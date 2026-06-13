@extends('core::layouts.master')

@section('header', 'Gestion des utilisateurs')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Gestion des utilisateurs</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des utilisateurs</h3>
    </div>
    <div class="card-body">
        <div id="toolbar">
            @can('cores.users.store')
            <button id="btn-add-user" class="btn btn-primary" data-bs-toggle="tooltip" title="Ajouter un utilisateur">
                <i class="fas fa-plus"></i>
            </button>
            @endcan
            @can('cores.users.update')
            <button id="btn-edit-user" class="btn btn-info" disabled data-bs-toggle="tooltip" title="Modifier">
                <i class="fas fa-edit"></i>
            </button>
            @endcan
            @can('cores.users.destroy')
            <button id="btn-delete-user" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
            @endcan
            @can('cores.users.reset-password')
            <button id="btn-reset-password" class="btn btn-warning" disabled data-bs-toggle="tooltip" title="Réinitialiser MDP">
                <i class="fas fa-key"></i>
            </button>
            @endcan
            @can('cores.users.toggle-status')
            <button id="btn-enable-user" class="btn btn-success" disabled data-bs-toggle="tooltip" title="Activer">
                <i class="fas fa-check"></i>
            </button>
            @endcan
            @can('cores.users.toggle-status')
            <button id="btn-disable-user" class="btn btn-secondary" disabled data-bs-toggle="tooltip" title="Désactiver">
                <i class="fas fa-ban"></i>
            </button>
            @endcan
        </div>
        <table id="users-table"
               data-toggle="table"
               data-url="{{ route('cores.users.data') }}"
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
                    <th data-field="service" data-sortable="true">Service</th>
                    <th data-field="is_active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                    <th data-field="created_at" data-sortable="true" data-formatter="dateFormatter">Date création</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::users._modal')

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>
<script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>

<script type="module" src="{{ asset('js/modules/core/users/index.js') }}"></script>
@endpush                     