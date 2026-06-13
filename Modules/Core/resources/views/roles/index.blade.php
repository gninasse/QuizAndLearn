@extends('core::layouts.master')

@section('header', 'Gestion des rôles')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Gestion des rôles</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des rôles</h3>
    </div>
    <div class="card-body">
        <div id="toolbar">
            @can('cores.roles.store')
            <button id="btn-add-role" class="btn btn-primary" data-bs-toggle="tooltip" title="Ajouter un rôle">
                <i class="fas fa-plus"></i>
            </button>
            @endcan
            @can('cores.roles.update')
            <button id="btn-edit-role" class="btn btn-info" disabled data-bs-toggle="tooltip" title="Modifier">
                <i class="fas fa-edit"></i>
            </button>
            @endcan
            @can('cores.roles.destroy')
            <button id="btn-delete-role" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
            @endcan
            @can('cores.permissions.index')
            <button id="btn-manage-permissions" class="btn btn-secondary" disabled data-bs-toggle="tooltip" title="Gérer les permissions">
                <i class="fas fa-shield-alt"></i>
            </button>
            @endcan
        </div>
        <table id="roles-table"
               data-toggle="table"
               data-url="{{ route('cores.roles.data') }}"
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
                    <th data-field="guard_name" data-sortable="true">Guard</th>
                    <th data-field="created_at" data-sortable="true" data-formatter="dateFormatter">Date création</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::roles._modal')

{{-- Modal pour gérer les permissions --}}
<div id="permissionModalContainer"></div>

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>
<script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>

<script type="module" src="{{ asset('js/modules/core/roles/index.js') }}"></script>
@endpush
