@extends('core::layouts.master')

@section('header', 'Mon Profil')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Mon Profil</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <!-- Profile Image -->
        <div class="card card-primary card-outline">
            <div class="card-body box-profile text-center">
                <div class="position-relative d-inline-block mb-3">
                    <img id="profile-avatar-preview" 
                         src="{{ $user->avatar_url }}" 
                         class="profile-user-img img-fluid img-circle shadow" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         alt="User profile picture">
                    
                    <label for="profile_avatar" class="position-absolute bottom-0 end-0 bg-primary text-white p-2 rounded-circle shadow-sm" style="cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile_avatar" name="avatar" class="d-none" accept="image/*">
                </div>

                <h3 class="profile-username text-center">{{ $user->full_name }}</h3>
                <p class="text-muted text-center">{{ $user->service ?? 'Aucun service' }}</p>

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Rôles</b> <a class="float-end">
                            @foreach($user->roles as $role)
                                <span class="badge bg-info">{{ $role->name }}</span>
                            @endforeach
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b>Membre depuis</b> <a class="float-end">{{ $user->created_at->format('d/m/Y') }}</a>
                    </li>
                </ul>

                <button type="button" class="btn btn-warning btn-block w-100" data-bs-toggle="modal" data-bs-target="#passwordModal">
                    <i class="fas fa-key me-1"></i> Modifier le mot de passe
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header p-2">
                <ul class="nav nav-pills">
                    <li class="nav-item"><a class="nav-link active" href="#settings" data-bs-toggle="tab">Informations Personnelles</a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="active tab-pane" id="settings">
                        <form id="profileForm" class="form-horizontal">
                            @csrf
                            <div class="row mb-3">
                                <label for="last_name" class="col-sm-2 col-form-label">Nom</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control bg-light" id="last_name" name="last_name" value="{{ $user->last_name }}" readonly>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="name" class="col-sm-2 col-form-label">Prénom</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control bg-light" id="name" name="name" value="{{ $user->name }}" readonly>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="user_name" class="col-sm-2 col-form-label">Nom d'utilisateur</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control bg-light" id="user_name" name="user_name" value="{{ $user->user_name }}" readonly>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="email" class="col-sm-2 col-form-label">Email</label>
                                <div class="col-sm-10">
                                    <input type="email" class="form-control bg-light" id="email" name="email" value="{{ $user->email }}" readonly>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="service" class="col-sm-2 col-form-label">Service</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control bg-light" id="service" name="service" value="{{ $user->service }}" readonly>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mot de passe -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalLabel">Changer le mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="passwordForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i> Après modification, vous serez automatiquement déconnecté.
                    </div>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Min 8 caractères, majuscule, minuscule, chiffre, symbole.</small>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password_confirmation">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="btn-update-password">Modifier</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script type="module" src="{{ asset('js/modules/core/profile/index.js') }}"></script>
@endpush
