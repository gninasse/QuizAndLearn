@extends('core::layouts.master')

@section('title', 'Détails du Module - ' . $module->name)

@section('header', 'Détails du Module')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cores.modules.index') }}">Modules</a></li>
    <li class="breadcrumb-item active">{{ $module->name }}</li>
@endsection

@section('content')
<div class="container-fluid">
    <div id="ajax-alert"></div>
    @coreAlert

    <!-- Header / Title Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="bg-white p-3 rounded shadow-sm me-3">
                @if($module->icon)
                    <i class="{{ $module->icon }} fa-2x text-primary"></i>
                @else
                    <i class="fas fa-shield-alt fa-2x text-primary"></i>
                @endif
            </div>
            <div>
                <h1 class="h3 mb-0">
                    {{ $module->name }} 
                    <small class="text-muted fs-6 ms-2">v{{ $module->version }}</small>
                    @if($module->is_active)
                        <span class="badge bg-success ms-2">Actif</span>
                    @else
                        <span class="badge bg-secondary ms-2">Inactif</span>
                    @endif
                </h1>
                <p class="text-muted mb-0">{{ $module->description }}</p>
            </div>
        </div>
        <a href="{{ route('cores.modules.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour à la liste
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Permissions Count</div>
                            <div class="h3 mb-0">{{ $module->permissions()->count() }}</div>
                        </div>
                        <div class="text-primary bg-light rounded p-2">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Linked Users</div>
                            <div class="h3 mb-0">{{ $module->users_count ?? 0 }}</div>
                        </div>
                        <div class="text-info bg-light rounded p-2">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Installation Date</div>
                            <div class="h4 mb-0">{{ $module->installed_at ? $module->installed_at->format('d/m/Y') : '-' }}</div>
                            <small class="text-muted">Manuel install</small>
                        </div>
                        <div class="text-warning bg-light rounded p-2">
                            <i class="far fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Last Update</div>
                            <div class="h4 mb-0">{{ $module->updated_at->format('d/m/Y') }}</div>
                            <small class="text-success">System Up-to-date</small>
                        </div>
                        <div class="text-success bg-light rounded p-2">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content (Tabs) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview" role="tab">Overview</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="permissions-tab" data-bs-toggle="tab" href="#permissions" role="tab">Permissions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="configuration-tab" data-bs-toggle="tab" href="#configuration" role="tab">Configuration</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-tab" data-bs-toggle="tab" href="#activity" role="tab">Activity Log</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="moduleTabContent">
                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                            <h5 class="card-title fw-bold mb-3">Description</h5>
                            <p class="text-secondary">
                                {{ $module->description }}
                                <br>
                                Le module <strong>{{ $module->name }}</strong> est un composant de l'infrastructure Keystone.
                            </p>

                            <h5 class="card-title fw-bold mt-4 mb-3">Key Features</h5>
                             <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center bg-light p-3 rounded">
                                        <i class="fas fa-check-circle text-primary me-3 fa-lg"></i>
                                        <div>
                                            <div class="fw-bold">Fonctionnalité Principale</div>
                                            <small class="text-muted">Support de base du système</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center bg-light p-3 rounded">
                                        <i class="fas fa-shield-alt text-primary me-3 fa-lg"></i>
                                        <div>
                                            <div class="fw-bold">Sécurité Intégrée</div>
                                            <small class="text-muted">Protection des données</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="card-title fw-bold mt-4 mb-3">Dependencies</h5>
                            @if($module->dependencies && count($module->dependencies) > 0)
                                <div class="list-group">
                                    @foreach($module->dependencies as $dep)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                 <i class="fas fa-cube text-secondary me-3"></i>
                                                 <span class="fw-bold">{{ $dep }}</span>
                                            </div>
                                            <span class="badge bg-success rounded-pill">Installé</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted">Aucune dépendance requise.</p>
                            @endif
                        </div>

                        <!-- Permissions Tab -->
                        <div class="tab-pane fade" id="permissions" role="tabpanel">
                            <h5 class="mb-3">Permissions du Module</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Guard</th>
                                            <th>Label</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($module->permissions as $perm)
                                            <tr>
                                                <td><code>{{ $perm->name }}</code></td>
                                                <td>{{ $perm->guard_name }}</td>
                                                <td>{{ $perm->label ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Aucune permission définie pour ce module.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Configuration Tab -->
                        <div class="tab-pane fade" id="configuration" role="tabpanel">
                            <div class="text-center py-5">
                                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                <h5>Configuration</h5>
                                <p class="text-muted">Les options de configuration seront disponibles ici.</p>
                            </div>
                        </div>

                         <!-- Activity Tab -->
                         <div class="tab-pane fade" id="activity" role="tabpanel">
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5>Activity Log</h5>
                                <p class="text-muted">L'historique des activités sera disponible ici.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Actions -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3">QUICK ACTIONS</div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Statut du Module</span>
                            <div class="form-check form-switch action-switch">
                                @if($module->is_active)
                                    @if(!$module->is_required)
                                         <form action="{{ route('cores.modules.disable', $module->slug) }}" method="POST" class="d-inline ajax-form">
                                            @csrf
                                            <input class="form-check-input" type="checkbox" role="switch" checked onchange="$(this).closest('form').submit()" @cannot('cores.modules.disable') disabled @endcannot>
                                        </form>
                                    @else
                                        <input class="form-check-input" type="checkbox" role="switch" checked disabled title="Module requis">
                                    @endif
                                @else
                                    <form action="{{ route('cores.modules.enable', $module->slug) }}" method="POST" class="d-inline ajax-form">
                                        @csrf
                                        <input class="form-check-input" type="checkbox" role="switch" onchange="$(this).closest('form').submit()" @cannot('cores.modules.enable') disabled @endcannot>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <small class="text-muted">
                            {{ $module->is_active ? 'Actuellement actif' : 'Actuellement inactif' }}
                        </small>
                    </div>

                    <div class="d-grid gap-2">
                         @can('cores.permissions.sync')
                            <form action="{{ route('cores.permissions.sync') }}" method="POST" class="w-100 ajax-form">
                                @csrf
                                <button type="submit" class="btn btn-outline-dark w-100">
                                    <i class="fas fa-sync me-2"></i>Synchronize Permissions
                                </button>
                            </form>
                        @endcan
                        
                        @if(!$module->is_required && !$module->is_active)
                            @can('cores.modules.uninstall')
                                <form action="{{ route('cores.modules.uninstall', $module->slug) }}" 
                                      method="POST" 
                                      class="w-100 ajax-form"
                                      data-confirm-message="Êtes-vous sûr de vouloir désinstaller ce module ?"
                                      data-confirm-title="Attention"
                                      data-confirm-icon="warning"
                                      data-confirm-button-text="Oui, désinstaller"
                                      data-confirm-button-color="#dc3545">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger w-100 bg-opacity-10 text-danger border-0">
                                        <i class="fas fa-trash me-2"></i>Uninstall Module
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    RECENT ACTIVITY
                    <a href="#" class="text-decoration-none small">View All</a>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <div class="avatar bg-success-subtle text-success rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <div>
                                <h6 class="mb-0 small fw-bold">Installation initiale</h6>
                                <small class="text-muted">{{ $module->installed_at ? $module->installed_at->diffForHumans() : '-' }}</small>
                            </div>
                        </div>
                        @if($module->activated_at)
                             <div class="d-flex mb-3">
                                <div class="me-3">
                                    <div class="avatar bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-power-off"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0 small fw-bold">Module activé</h6>
                                    <small class="text-muted">{{ $module->activated_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4 bg-primary text-white">
                <div class="card-body">
                    <h5>Besoin d'aide ?</h5>
                    <p class="small mb-3">Consultez la documentation technique pour configurer ce module.</p>
                    <a href="#" class="btn btn-light btn-sm w-100 text-primary fw-bold">DOCUMENTATION <i class="fas fa-external-link-alt ms-1"></i></a>
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
            
            // Only show spinner if it's a button click, not a switch toggle
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
                            (response.success || 'Opération réussie') +
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
                    
                    if(button.length > 0 && !form.find('input[type="checkbox"][role="switch"]').length) {
                        button.prop('disabled', false).html(initialText);
                    } else {
                        // Revert switch if error
                        var switchInput = form.find('input[type="checkbox"][role="switch"]');
                        if (switchInput.length > 0) {
                             switchInput.prop('checked', !switchInput.prop('checked'));
                        }
                    }
                }
            });
        }
    });
</script>
@endpush
