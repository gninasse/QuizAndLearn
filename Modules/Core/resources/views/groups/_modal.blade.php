<!-- Group Edit/Create Modal -->
<div class="modal fade" id="groupModal" tabindex="-1" role="dialog" aria-labelledby="groupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Ajouter un groupe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="groupForm">
                @csrf
                <input type="hidden" id="group_id" name="group_id">
                
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="name">Nom du groupe <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Date de début</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">Date de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Actif</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-save">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Group Members Modal -->
<div class="modal fade" id="groupMembersModal" tabindex="-1" role="dialog" data-bs-backdrop="static" aria-labelledby="groupMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-bottom-0 bg-white pt-4 px-4 pb-2 d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="modal-title fw-bold text-dark mb-1">Gérer les membres : <strong id="membersGroupName"></strong></h4>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge bg-light text-secondary border py-1.5 px-2.5 fs-7 rounded-pill" style="font-weight: 500;">
                                <i class="fas fa-users me-1 text-muted"></i> <span id="totalMembersCount">0</span> membres au total
                            </span>
                            <span id="group-status-badge"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            
            <form id="groupMembersForm">
                @csrf
                <input type="hidden" id="members_group_id" name="group_id">
                
                <div class="modal-body px-4 py-3" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                    
                    <!-- Section Paramètres de Formation (Dates & Statut) -->
                    <div class="bg-light p-3 rounded mb-4" style="border: 1px solid var(--border);">
                        <h6 class="fw-bold mb-3 text-secondary" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Dates & Statut du Groupe</h6>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="members_start_date" class="form-label fw-semibold small mb-1">Date de début</label>
                                <input type="date" class="form-control form-control-sm" id="members_start_date" name="start_date" style="border-radius: 6px;">
                            </div>
                            <div class="col-md-5">
                                <label for="members_end_date" class="form-label fw-semibold small mb-1">Date de fin</label>
                                <input type="date" class="form-control form-control-sm" id="members_end_date" name="end_date" style="border-radius: 6px;">
                            </div>
                            <div class="col-md-2 text-end">
                                <div class="form-check form-switch d-inline-block text-start mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="members_is_active" name="is_active" value="1">
                                    <label class="form-check-label fw-semibold small" for="members_is_active">Actif</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Formateurs -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-dark mb-0 fs-6">Formateurs <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1" id="trainersCount">3 max</span></h6>
                        </div>
                        
                        <div class="position-relative">
                            <div id="trainer-tags-input-container" class="form-control d-flex flex-wrap align-items-center gap-2 p-2 cursor-pointer" style="border-radius: 8px; min-height: 48px; border-color: #cbd5e1; background-color: #fff;">
                                <!-- Rendered tags go here -->
                                <input type="text" id="trainer-search-input" placeholder="Rechercher un formateur..." class="border-0 p-0 flex-grow-1" style="outline: none; min-width: 150px; font-size: 0.9rem; background: transparent;">
                            </div>
                            
                            <!-- Autocomplete Dropdown Menu -->
                            <div id="trainer-autocomplete-dropdown" class="dropdown-menu shadow border-0 mt-1 w-100 py-1" style="display: none; max-height: 200px; overflow-y: auto; border-radius: 8px; z-index: 1050;">
                                <!-- Dynamically populated -->
                            </div>
                        </div>
                        <small class="text-muted fs-8 mt-1 d-block">Sélectionnez jusqu'à 3 formateurs pour ce groupe.</small>
                    </div>
                    
                    <hr class="text-muted opacity-25 mb-4">
                    
                    <!-- Section Apprenants (Dual List Box) -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <h6 class="fw-bold text-dark mb-0 fs-6">Apprenants <span class="badge bg-primary-subtle text-primary rounded-pill ms-1" id="learnersCount">0 inscrits</span></h6>
                        </div>
                        
                        <div class="row g-0 align-items-stretch">
                            <!-- Panel Gauche : Disponibles -->
                            <div class="col-md-5 flex-column d-flex">
                                <div class="card border shadow-none flex-grow-1 m-0" style="border-radius: 8px; overflow: hidden; min-height: 320px;">
                                    <div class="card-header bg-white border-bottom-0 pt-3 px-3 pb-2">
                                        <div class="position-relative">
                                            <span class="position-absolute top-50 translate-middle-y start-0 ps-3 text-muted" style="font-size: 0.85rem;">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <input type="text" class="form-control form-control-sm ps-5" id="search-available-learners" placeholder="Rechercher..." style="border-radius: 6px;">
                                        </div>
                                    </div>
                                    <div class="card-body p-0 overflow-auto" style="height: 240px;" id="available-learners-container">
                                        <!-- Dynamic list of available learners -->
                                    </div>
                                    <div class="card-footer bg-white text-muted py-2 px-3 border-top-0" style="font-size: 0.8rem; font-weight: 500;">
                                        <span id="available-count">0 disponibles</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Panel Milieu : Boutons de transfert -->
                            <div class="col-md-2 d-flex flex-column justify-content-center align-items-center gap-2 py-3 px-2">
                                <button type="button" class="btn-transfer-btn" id="btn-transfer-right" title="Assigner la sélection">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <button type="button" class="btn-transfer-btn" id="btn-transfer-left" title="Retirer la sélection">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                            </div>
                            
                            <!-- Panel Droite : Affectés -->
                            <div class="col-md-5 flex-column d-flex">
                                <div class="card border shadow-none flex-grow-1 m-0" style="border-radius: 8px; overflow: hidden; min-height: 320px;">
                                    <div class="card-header bg-white border-bottom-0 pt-3 px-3 pb-2 d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark fs-7">Affectés</span>
                                        <span class="badge bg-success rounded-pill px-2.5 py-1 fs-8" id="assigned-badge-count" style="background-color: #115e59 !important;">0</span>
                                    </div>
                                    <div class="card-body p-0 overflow-auto" style="height: 240px;" id="assigned-learners-container">
                                        <!-- Dynamic list of assigned learners -->
                                    </div>
                                    <div class="card-footer bg-white py-2 px-3 border-top-0 text-center">
                                        <a href="#" id="btn-remove-all" class="text-teal text-decoration-none fs-8 fw-bold" style="color: #0d9488;">Tout retirer</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="text-muted opacity-25 mb-4">

                    <!-- Section Quiz assignés -->
                    <div>
                        <h6 class="fw-bold text-dark mb-2 fs-6">Quiz assignés à ce groupe <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill ms-1" id="assignedQuizzesCount">0 quiz</span></h6>
                        <div id="assigned-quizzes-wrapper" class="row g-2 mt-1" style="max-height: 160px; overflow-y: auto;">
                            <!-- Dynamic quiz cards go here -->
                        </div>
                    </div>

                </div>
                
                <div class="modal-footer bg-light border-top-0 px-4 py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-secondary fs-6" id="totalAssignedCount">0 apprenants assignés au total</span>
                    <div>
                        <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal" style="border-radius: 6px; font-weight: 500; font-size: 0.9rem;">Annuler</button>
                        <button type="submit" class="btn btn-success px-4" id="btn-save-members" style="background-color: #1a6d54; border-color: #1a6d54; border-radius: 6px; font-weight: 500; font-size: 0.9rem;">
                            <i class="fas fa-save me-1.5"></i> Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .trainer-tag {
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 3px 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        font-weight: 500;
        color: #334155;
        user-select: none;
    }
    .trainer-tag img {
        width: 18px;
        height: 18px;
        object-fit: cover;
        border-radius: 50%;
    }
    .trainer-tag .remove-tag-btn {
        border: none;
        background: none;
        padding: 0;
        cursor: pointer;
        color: #94a3b8;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.15s;
    }
    .trainer-tag .remove-tag-btn:hover {
        color: #ef4444;
    }
    .trainer-dropdown-item {
        padding: 8px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        transition: background-color 0.15s;
    }
    .trainer-dropdown-item:hover {
        background-color: #f1f5f9;
    }
    .dual-list-item {
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background-color 0.15s;
        user-select: none;
    }
    .dual-list-item:hover {
        background-color: #f8fafc;
    }
    .dual-list-item.selected {
        background-color: #eff6ff;
    }
    .btn-transfer-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        font-size: 0.95rem;
        transition: all 0.2s;
        cursor: pointer;
    }
    .btn-transfer-btn:hover {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: #fff;
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
    }
    .avatar-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #fff;
        font-size: 0.85rem;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    .fs-7 {
        font-size: 0.8rem !important;
    }
    .fs-8 {
        font-size: 0.725rem !important;
    }
</style>
