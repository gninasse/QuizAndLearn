@extends('core::layouts.master')

@section('header', $user->full_name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.users.index') }}">Utilisateurs</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $user->full_name }}</li>
@endsection

@section('content')
<div class="container-fluid">
    {{-- Header Section --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="position-relative">
                        <img src="{{ $user->avatar_url }}" 
                             alt="{{ $user->full_name }}" 
                             class="rounded-circle"
                             style="width: 80px; height: 80px; object-fit: cover;">
                        @if($user->is_active)
                            <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" 
                                  style="width: 20px; height: 20px;"></span>
                        @endif
                    </div>
                </div>
                <div class="col">
                    <h3 class="mb-1">{{ $user->full_name }}</h3>
                    <div class="text-muted">
                        @if($user->roles->first())
                            <span class="badge bg-primary me-2">{{ $user->roles->first()->name }}</span>
                        @endif
                        <i class="fas fa-envelope me-1"></i> {{ $user->email }}
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar me-1"></i> Inscrit le {{ $user->created_at->format('d/m/Y') }}
                        <span class="mx-2">•</span>
                        <span id="user-status-badge" class="badge {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
                            {{ $user->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </div>
                </div>
                <div class="col-auto">
                    @if(auth()->id() !== $user->id)
                        @if($user->is_active)
                            <button type="button" class="btn btn-outline-danger me-2" id="btn-toggle-status" data-action="désactiver">
                                <i class="fas fa-user-slash"></i> Désactiver
                            </button>
                        @else
                            <button type="button" class="btn btn-outline-success me-2" id="btn-toggle-status" data-action="activer">
                                <i class="fas fa-user-check"></i> Activer
                            </button>
                        @endif
                    @endif
                    <button type="button" class="btn btn-outline-secondary me-2" id="btn-reset-password">
                        <i class="fas fa-key"></i> Réinitialiser le mot de passe
                    </button>
                    <button type="button" class="btn btn-primary" id="btn-edit-mode">
                        <i class="fas fa-edit"></i> Modifier le profil
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Rôles assignés</h6>
                            <h3 class="mb-0">{{ $user->roles->count() }}</h3>
                        </div>
                        @if($user->roles->where('created_at', '>=', now()->subDays(7))->count() > 0)
                            <span class="badge bg-success">+{{ $user->roles->where('created_at', '>=', now()->subDays(7))->count() }} nouveaux</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Permissions directes</h6>
                    <h3 class="mb-0">{{ $user->getDirectPermissions()->count() }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total effectif</h6>
                    <h3 class="mb-0">
                        {{ $user->getAllPermissions()->count() }}
                        <small class="text-muted">Calculé</small>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Dernière connexion</h6>
                    <h3 class="mb-0">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Jamais' }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Section --}}
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#general" role="tab">
                        Général
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#roles" role="tab">
                        Rôles <span class="badge bg-light text-dark ms-1 shadow-sm">{{ $user->roles->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#direct-permissions" role="tab">
                        Permissions directes <span class="badge bg-light text-dark ms-1 shadow-sm">{{ $user->getDirectPermissions()->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#effective-access" role="tab">
                        Accès effectif
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                {{-- General Tab --}}
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <form id="profile-form">
                        @csrf
                        <div class="row">
                            <div class="col-md-3 text-center mb-4">
                                <div class="position-relative d-inline-block">
                                    <img id="profile-avatar-preview" 
                                         src="{{ $user->avatar_url }}" 
                                         class="rounded-circle img-thumbnail" 
                                         style="width: 150px; height: 150px; object-fit: cover;" 
                                         alt="Avatar">
                                </div>
                                <div class="mt-3">
                                    <label for="profile-avatar" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-camera"></i> Modifier la photo
                                    </label>
                                    <input type="file" id="profile-avatar" class="d-none" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">
                                            Nom <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="last_name" 
                                               name="last_name" 
                                               value="{{ $user->last_name }}" 
                                               required
                                               disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">
                                            Prénom <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name" 
                                               name="name" 
                                               value="{{ $user->name }}" 
                                               required
                                               disabled>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="user_name" class="form-label">
                                            Nom d'utilisateur <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="user_name" 
                                               name="user_name" 
                                               value="{{ $user->user_name }}" 
                                               required
                                               disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">
                                            Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="{{ $user->email }}" 
                                               required
                                               disabled>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="service" class="form-label">Service</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="service" 
                                           name="service" 
                                           value="{{ $user->service }}"
                                           disabled>
                                </div>

                                {{-- Password Section (Disabled) --}}
                                <hr class="my-4">
                                <h6 class="text-muted mb-3">Mot de passe</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Mot de passe</label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password" 
                                                   placeholder="••••••••" 
                                                   disabled>
                                            <button class="btn btn-outline-secondary" type="button" disabled>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Min 8 carats, majuscule, minuscule, chiffre, symbole
                                        </small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password_confirmation" class="form-label">
                                            Confirmer le mot de passe
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password_confirmation" 
                                                   placeholder="••••••••" 
                                                   disabled>
                                            <button class="btn btn-outline-secondary" type="button" disabled>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="row mt-4 d-none" id="form-actions">
                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-secondary" id="btn-cancel">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary" id="btn-save-profile">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Roles Tab --}}
                <div class="tab-pane fade" id="roles" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="input-group" style="max-width: 400px;">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="search-roles" 
                                   placeholder="Rechercher des rôles assignés...">
                        </div>
                        <button type="button" class="btn btn-primary" id="btn-assign-role">
                            <i class="fas fa-plus"></i> ASSIGNER UN NOUVEAU RÔLE
                        </button>
                    </div>

                    @if($user->roles->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover" id="roles-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>NOM DU RÔLE</th>
                                        <th>DESCRIPTION</th>
                                        <th>DATE D'ASSIGNATION</th>
                                        <th style="width: 100px;">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->roles as $role)
                                        <tr data-role-id="{{ $role->id }}">
                                            <td>
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user-shield"></i>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>{{ $role->name }}</strong>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    {{ Str::limit($role->description ?? 'Pas de description', 60) }}
                                                </span>
                                            </td>
                                            <td>
                                                {{ $role->pivot->created_at ? $role->pivot->created_at->format('d/m/Y') : '-' }}
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger btn-remove-role" 
                                                        data-role-id="{{ $role->id }}"
                                                        data-role-name="{{ $role->name }}">
                                                    <i class="fas fa-trash"></i> Retirer
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucun rôle assigné
                        </div>
                    @endif
                </div>

                {{-- Direct Permissions Tab --}}
                <div class="tab-pane fade" id="direct-permissions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="input-group" style="max-width: 400px;">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="search-direct-permissions" 
                                   placeholder="Rechercher des permissions directes...">
                        </div>
                        <button type="button" class="btn btn-primary" id="btn-assign-permissions">
                            <i class="fas fa-plus"></i> ASSIGNER DES PERMISSIONS DIRECTES
                        </button>
                    </div>

                    @if($user->getDirectPermissions()->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover" id="direct-permissions-table">
                                <thead>
                                    <tr>
                                        <th>NOM DE LA PERMISSION</th>
                                        <th>LIBELLÉ</th>
                                        <th>MODULE</th>
                                        <th style="width: 100px;">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->getDirectPermissions() as $permission)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 24px; height: 24px; font-size: 0.7rem;">
                                                        <i class="fas fa-key"></i>
                                                    </div>
                                                    <code>{{ $permission->name }}</code>
                                                </div>
                                            </td>
                                            <td>{{ $permission->label ?? '-' }}</td>
                                            <td>
                                                @if($permission->module)
                                                    <span class="badge bg-light text-dark border">{{ $permission->module }}</span>
                                                @else
                                                    <span class="text-muted small">Système</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger btn-remove-permission" 
                                                        data-permission-id="{{ $permission->id }}"
                                                        data-permission-name="{{ $permission->name }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucune permission directe assignée
                        </div>
                    @endif
                </div>

                {{-- Effective Access Tab --}}
                <div class="tab-pane fade" id="effective-access" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="input-group" style="max-width: 400px;">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="search-effective-permissions" 
                                   placeholder="Rechercher toutes les permissions effectives...">
                        </div>
                    </div>

                    @if($user->getAllPermissions()->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover" id="effective-permissions-table">
                                <thead>
                                    <tr>
                                        <th>NOM DE LA PERMISSION</th>
                                        <th>LIBELLÉ</th>
                                        <th>SOURCE</th>
                                        <th>MODULE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->getAllPermissions() as $permission)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 24px; height: 24px; font-size: 0.7rem;">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                    <code>{{ $permission->name }}</code>
                                                </div>
                                            </td>
                                            <td>{{ $permission->label ?? '-' }}</td>
                                            <td>
                                                @if($user->getDirectPermissions()->contains($permission))
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Directe</span>
                                                @else
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Via rôle</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($permission->module)
                                                    <span class="badge bg-light text-dark border">{{ $permission->module }}</span>
                                                @else
                                                    <span class="text-muted small">Système</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucune permission effective
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Assign Role Modal --}}
<div class="modal fade" id="assignRoleModal" tabindex="-1" aria-labelledby="assignRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignRoleModalLabel">
                    Assigner un nouveau rôle à {{ $user->full_name }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assign-role-form">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="role-search" class="form-label">Sélectionner un rôle</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="role-search" 
                                   placeholder="Rechercher et sélectionner un rôle...">
                        </div>
                        
                        <div id="roles-list" class="border rounded" style="max-height: 250px; overflow-y: auto;">
                            {{-- Roles will be loaded here --}}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="assignment-note" class="form-label">Note d'assignation (Optionnel)</label>
                        <textarea class="form-control" 
                                  id="assignment-note" 
                                  rows="3" 
                                  placeholder="Ajouter une raison ou un contexte pour cette assignation..."></textarea>
                    </div>

                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Les permissions du rôle sélectionné seront héritées immédiatement.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        ANNULER
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-confirm-assign">
                        <i class="fas fa-plus"></i> ASSIGNER LE RÔLE
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Assign Permissions Modal --}}
<div class="modal fade" id="assignPermissionsModal" tabindex="-1" aria-labelledby="assignPermissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignPermissionsModalLabel">
                    Assigner des permissions directes à {{ $user->full_name }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assign-permissions-form">
                @csrf
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="permission-search" class="form-label">Rechercher des permissions</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="permission-search" 
                                       placeholder="ex: create_user, delete_record...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="module-filter" class="form-label">Filtrer par module</label>
                            <select class="form-select" id="module-filter">
                                <option value="all">Tous les modules</option>
                                {{-- Modules will be loaded here --}}
                            </select>
                        </div>
                    </div>
                    
                    <div id="permissions-list" class="border rounded" style="max-height: 400px; overflow-y: auto;">
                        {{-- Permissions will be loaded here --}}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        ANNULER
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-confirm-assign-permissions">
                         ASSIGNER PLUSIEURS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    window.userId = {{ $user->id }};
    window.userAvatarUrl = "{{ $user->avatar_url }}";
</script>
<script type="module" src="{{ asset('js/modules/core/users/show.js') }}"></script>
@endpush
