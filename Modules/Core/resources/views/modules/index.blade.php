@extends('core::layouts.master')

@section('title', 'Gestion des Modules')

@section('header', 'Gestion des Modules')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Modules</li>
@endsection

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-2">Gestion des Modules</h1>
            <p class="text-muted">{{ $modules->count() }} module(s) installé(s)</p>
        </div>
        <div class="col-md-6 text-end">
            @can('cores.permissions.sync')
                <form action="{{ route('cores.permissions.sync') }}" method="POST" class="d-inline ajax-form"
                      data-confirm-message="Synchroniser les permissions de tous les modules ?"
                      data-confirm-title="Confirmation"
                      data-confirm-icon="warning"
                      data-confirm-button-text="Oui, synchroniser">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="fas fa-sync me-2"></i>Sync Permissions
                    </button>
                </form>
            @endcan
        </div>
    </div>

    @coreAlert
    <div id="ajax-alert"></div>

    <!-- Modules détectés non installés -->
    @if(!empty($detectedModules))
        <div class="alert alert-info" role="alert">
            <h5 class="alert-heading">
                <i class="fas fa-info-circle me-2"></i>Nouveaux modules détectés
            </h5>
            <p class="mb-2">Les modules suivants sont présents mais non installés :</p>
            <div class="d-flex flex-wrap gap-2 mt-3">
                @foreach($detectedModules as $detected)
                    <div class="badge bg-light text-dark border p-2">
                        <strong>{{ $detected['name'] }}</strong>
                        @can('cores.modules.install')
                            <form action="{{ route('cores.modules.install') }}" method="POST" class="d-inline ms-2 ajax-form">
                                @csrf
                                <input type="hidden" name="module_slug" value="{{ $detected['slug'] }}">
                                <button type="submit" class="btn btn-sm btn-primary py-0">
                                    Installer
                                </button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Modules installés -->
    <div class="row g-4">
        @forelse($modules as $module)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 {{ !$module->is_active ? 'opacity-75' : '' }}">
                    <div class="card-body">
                        <!-- Header du module -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                @if($module->icon)
                                    <i class="{{ $module->icon }} fa-2x text-primary me-3"></i>
                                @else
                                    <i class="fas fa-cube fa-2x text-primary me-3"></i>
                                @endif
                                <div>
                                    <h5 class="mb-0">{{ $module->name }}</h5>
                                    <small class="text-muted">v{{ $module->version }}</small>
                                </div>
                            </div>
                            @if($module->is_active)
                                <span class="badge bg-success">Actif</span>
                            @else
                                <span class="badge bg-secondary">Inactif</span>
                            @endif
                        </div>

                        <!-- Description -->
                        <p class="text-muted small mb-3">
                            {{ $module->description ?? 'Aucune description disponible' }}
                        </p>

                        <!-- Badges -->
                        <div class="mb-3">
                            @if($module->is_required)
                                <span class="badge bg-danger me-1">
                                    <i class="fas fa-exclamation-triangle"></i> Requis
                                </span>
                            @endif
                            @if($module->dependencies && count($module->dependencies) > 0)
                                <span class="badge bg-info me-1" 
                                      title="Dépendances: {{ implode(', ', $module->dependencies) }}">
                                    <i class="fas fa-link"></i> {{ count($module->dependencies) }} dépendance(s)
                                </span>
                            @endif
                        </div>

                        <!-- Statistiques -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="fw-bold text-primary">{{ $module->permissions()->count() }}</div>
                                    <small class="text-muted">Permissions</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="fw-bold text-primary">{{ $module->users_count ?? 0 }}</div>
                                    <small class="text-muted">Utilisateurs</small>
                                </div>
                            </div>
                        </div>

                        <!-- Informations -->
                        <div class="small text-muted mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Installé le:</span>
                                <span>{{ $module->installed_at ? $module->installed_at->format('d/m/Y') : 'N/A' }}</span>
                            </div>
                            @if($module->is_active && $module->activated_at)
                                <div class="d-flex justify-content-between">
                                    <span>Activé le:</span>
                                    <span>{{ $module->activated_at->format('d/m/Y') }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Actions -->
                        <div class="btn-group w-100" role="group">
                            <a href="{{ route('cores.modules.show', $module->slug) }}" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>Voir
                            </a>

                            @if($module->is_active)
                                @can('cores.modules.enable')
                                    @if(!$module->is_required)
                                        <form action="{{ route('cores.modules.disable', $module->slug) }}" 
                                              method="POST" 
                                              class="flex-fill ajax-form"
                                              data-confirm-message="Voulez-vous vraiment désactiver ce module ?"
                                              data-confirm-title="Désactiver le module"
                                              data-confirm-icon="warning"
                                              data-confirm-button-text="Oui, désactiver"
                                              data-confirm-button-color="#ffc107">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-outline-warning btn-sm w-100">
                                                <i class="fas fa-pause me-1"></i>Désactiver
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            @else
                                @can('cores.modules.enable')
                                    <form action="{{ route('cores.modules.enable', $module->slug) }}" 
                                          method="POST" 
                                          class="flex-fill ajax-form">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                            <i class="fas fa-play me-1"></i>Activer
                                        </button>
                                    </form>
                                @endcan
                            @endif

                            @can('cores.modules.configure')
                                <a href="{{ route('cores.modules.configure', $module->slug) }}" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-cog"></i>
                                </a>
                            @endcan
                        </div>

                        <!-- Bouton de suppression -->
                        @if(!$module->is_required && !$module->is_active)
                            @can('cores.modules.uninstall')
                                <form action="{{ route('cores.modules.uninstall', $module->slug) }}" 
                                      method="POST" 
                                      class="mt-2 ajax-form"
                                      data-confirm-message="Attention : Désinstaller ce module supprimera toutes ses données et permissions. Cette action est irréversible."
                                      data-confirm-title="Êtes-vous sûr ?"
                                      data-confirm-icon="error"
                                      data-confirm-button-text="Oui, désinstaller"
                                      data-confirm-button-color="#dc3545">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="btn btn-outline-danger btn-sm w-100">
                                        <i class="fas fa-trash me-1"></i>Désinstaller
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-cubes fa-4x text-muted mb-3"></i>
                        <h4>Aucun module installé</h4>
                        <p class="text-muted">Installez votre premier module pour commencer</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Légende -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6 class="mb-3">Légende</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <span class="badge bg-success me-2">Actif</span>
                    Module activé et fonctionnel
                </div>
                <div class="col-md-3">
                    <span class="badge bg-secondary me-2">Inactif</span>
                    Module installé mais désactivé
                </div>
                <div class="col-md-3">
                    <span class="badge bg-danger me-2">Requis</span>
                    Ne peut pas être désactivé
                </div>
                <div class="col-md-3">
                    <span class="badge bg-info me-2">Dépendances</span>
                    Nécessite d'autres modules
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    $(document).ready(function() {
        $('.ajax-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            
            // Check for confirmation requirement
            var confirmMessage = form.data('confirm-message');
            
            if (confirmMessage) {
                var confirmTitle = form.data('confirm-title') || 'Êtes-vous sûr ?';
                var confirmIcon = form.data('confirm-icon') || 'warning';
                var confirmButtonText = form.data('confirm-button-text') || 'Oui, continuer';
                var confirmButtonColor = form.data('confirm-button-color') || '#3085d6';

                Swal.fire({
                    title: confirmTitle,
                    text: confirmMessage,
                    icon: confirmIcon,
                    showCancelButton: true,
                    confirmButtonColor: confirmButtonColor,
                    cancelButtonColor: '#d33',
                    confirmButtonText: confirmButtonText,
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitForm(form);
                    } else {
                        // If canceled, specific handling for switches if needed
                        var switchInput = form.find('input[type="checkbox"][role="switch"]');
                        if (switchInput.length > 0) {
                            switchInput.prop('checked', !switchInput.prop('checked'));
                        }
                    }
                });
            } else {
                submitForm(form);
            }
        });

        function submitForm(form) {
            var url = form.attr('action');
            var method = form.find('input[name="_method"]').val() || form.attr('method');
            var data = form.serialize();
            var button = form.find('button[type="submit"]');
            var initialText = button.html();
            
            // Disable button and show spinner if it's a button submission
            if(button.length > 0 && !form.find('input[type="checkbox"][role="switch"]').length) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Traitement...');
            }
            
            $.ajax({
                url: url,
                type: method,
                data: data,
                success: function(response) {
                    $('#ajax-alert').html(
                        '<div class="alert alert-success alert-dismissible fade show" role="alert">' + 
                            (response.message || 'Opération réussie') +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>'
                    );
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                },
                error: function(xhr) {
                    var errorMsg = 'Une erreur est survenue';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                    
                    $('#ajax-alert').html(
                        '<div class="alert alert-danger alert-dismissible fade show" role="alert">' + 
                            errorMsg +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>'
                    );
                    
                    if(button.length > 0) {
                        button.prop('disabled', false).html(initialText);
                    }
                    
                    // Revert switch if error
                    var switchInput = form.find('input[type="checkbox"][role="switch"]');
                    if (switchInput.length > 0) {
                         switchInput.prop('checked', !switchInput.prop('checked'));
                    }
                }
            });
        }
    });
</script>
@endpush
