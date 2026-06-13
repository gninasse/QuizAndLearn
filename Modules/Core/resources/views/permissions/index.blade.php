@extends('core::layouts.master')

@section('header', 'Matrice des permissions')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Matrice des permissions</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title">Gestion des privilèges par rôle</h3>
        <div class="card-tools d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-permissions-config">
                <i class="fas fa-sliders-h me-1"></i> Configurer l'affichage
            </button>
            <div class="input-group input-group-sm" style="width: 250px;">
                <input type="text" id="permission-search" class="form-control" placeholder="Rechercher une permission">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>
        </div>
    </div>

    {{-- Modal de configuration de l'affichage --}}
    <div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="configModalTitle">
                        <i class="fas fa-layer-group me-2 text-primary"></i>Paramètres d'affichage de la matrice
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form id="configForm">
                        <div class="mb-4">
                            <h6 class="text-uppercase small fw-bold text-muted mb-3 border-bottom pb-2">MODULES</h6>
                            <div class="row g-2">
                                @foreach($allModules as $module)
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="modules[]" 
                                                       value="{{ $module }}" id="mod_{{ Str::slug($module) }}"
                                                       {{ in_array($module, $selectedModules) ? 'checked' : '' }}>
                                                <label class="form-check-label ms-2 small fw-medium" for="mod_{{ Str::slug($module) }}">
                                                    {{ $module }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h6 class="text-uppercase small fw-bold text-muted mb-3 border-bottom pb-2">RÔLES</h6>
                            <div class="row g-2">
                                @foreach($allRoles as $role)
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="roles[]" 
                                                       value="{{ $role->id }}" id="role_{{ $role->id }}"
                                                       {{ in_array($role->id, $selectedRolesIds) ? 'checked' : '' }}>
                                                <label class="form-check-label ms-2 small fw-medium" for="role_{{ $role->id }}">
                                                    {{ ucfirst($role->name) }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary px-4 shadow-sm" id="btn-apply-filters">
                        Appliquer le filtre
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="permissions-table">
                <thead>
                    <tr>
                        <th style="width: 300px;">Permission</th>
                        @foreach($roles as $role)
                            <th class="text-center">{{ ucfirst($role->name) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissions as $permission)
                        <tr class="permission-row">
                            <td class="permission-name">
                                <strong>{{ $permission->label ?? $permission->name }}</strong>
                                @if($permission->label)
                                    <br><small class="text-muted">{{ $permission->name }}</small>
                                @endif
                            </td>
                            @foreach($roles as $role)
                                <td class="text-center">
                                    <input type="checkbox" 
                                           class="permission-toggle" 
                                           data-role-id="{{ $role->id }}" 
                                           data-permission-id="{{ $permission->id }}"
                                           data-toggle="toggle"
                                           data-on="Oui"
                                           data-off="Non"
                                           data-onstyle="success"
                                           data-offstyle="danger"
                                           data-size="small"
                                           {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                           @cannot('cores.permissions.toggle') disabled @endcannot>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-toggle/css/bootstrap-toggle.css') }}">
<style>
    /* Fix for bootstrap toggle alignment in table */
    .toggle.btn { min-width: 60px; min-height: 30px; }
</style>
@endpush

@push('js')
<script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-toggle/js/bootstrap-toggle.js') }}"></script>
<script type="module" src="{{ asset('js/modules/core/permissions/index.js') }}"></script>
@endpush
