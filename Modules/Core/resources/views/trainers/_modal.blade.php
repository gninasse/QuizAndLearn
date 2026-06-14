<!-- Modal Ajouter/Modifier un Formateur -->
<div class="modal fade" id="trainerModal" tabindex="-1" role="dialog" data-bs-backdrop="static" aria-labelledby="trainerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-light py-3">
                <h5 class="modal-title fw-bold" id="modalTitle" style="color: var(--green-dark);">Ajouter un formateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="trainerForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="trainer_id" name="trainer_id">
                
                <div class="modal-body p-4">
                    <!-- Photo de profil & Avatar circular upload -->
                    <div class="text-center mb-4">
                        <div class="avatar-upload-container d-inline-block">
                            <img id="avatar-preview" src="{{ asset('media/user_avatar.svg') }}" 
                                 class="avatar-upload-preview" alt="Avatar Preview">
                            <label for="avatar" class="avatar-upload-overlay" title="Choisir une photo">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="avatar" name="avatar" class="d-none" accept="image/png, image/jpeg, image/gif">
                        </div>
                        <p class="text-muted small mt-2 mb-0">PNG, JPG ou GIF. Max 5MB.</p>
                    </div>

                    <!-- Grille 2 colonnes (Nom, Prénom, Username, Email, Téléphone, Spécialité) -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="last_name" class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Nom du formateur" style="border-radius: 8px;" required>
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Prénom du formateur" style="border-radius: 8px;" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="user_name" class="form-label fw-semibold">Nom d'utilisateur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="user_name" name="user_name" placeholder="ex: jdoe" style="border-radius: 8px;" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Adresse Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="email@exemple.com" style="border-radius: 8px;" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Téléphone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="Numéro de téléphone" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6">
                            <label for="specialty" class="form-label fw-semibold">Spécialité d'enseignement</label>
                            <input type="text" class="form-control" id="specialty" name="specialty" placeholder="ex: Informatique, Mathématiques" style="border-radius: 8px;">
                        </div>
                    </div>

                    <!-- Custom Checklist autocomplete for Assigned Groups -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Groupes assignés</label>
                        
                        <!-- Tags Container -->
                        <div id="trainer-groups-tags" class="d-flex flex-wrap gap-2 mb-2"></div>
                        
                        <!-- Dropdown wrapper with checklist -->
                        <div class="dropdown" id="groupsDropdownWrapper">
                            <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="group-search-input" placeholder="Rechercher ou sélectionner des groupes..." autocomplete="off" data-bs-toggle="dropdown" aria-expanded="false">
                            </div>
                            <ul class="dropdown-menu w-100 p-2 shadow border-0 mt-1" id="group-checkboxes-list" style="max-height: 200px; overflow-y: auto; border-radius: 8px;">
                                @foreach($groups as $group)
                                    <li class="dropdown-item p-1 group-checkbox-item-li" data-search-name="{{ strtolower($group->name) }}">
                                        <div class="form-check d-flex align-items-center gap-2 py-1.5 px-2 rounded cursor-pointer group-checkbox-item">
                                            <input class="form-check-input group-checkbox-input" type="checkbox" name="group_ids[]" value="{{ $group->id }}" id="chk-group-{{ $group->id }}" data-name="{{ $group->name }}">
                                            <label class="form-check-label w-100 mb-0 cursor-pointer fw-semibold text-dark fs-7" for="chk-group-{{ $group->id }}">
                                                {{ $group->name }}
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- Biographie / Notes pédagogiques -->
                    <div class="mb-3">
                        <label for="biography" class="form-label fw-semibold">Biographie / Notes pédagogiques</label>
                        <textarea class="form-control" id="biography" name="biography" rows="3" placeholder="Présentation courte du formateur..." style="border-radius: 8px;"></textarea>
                    </div>

                    <!-- Password Fields for Add/Edit -->
                    <div class="password-group p-3 bg-light rounded" style="border: 1px solid var(--border);">
                        <h6 class="fw-bold mb-3 text-secondary" id="password-label-header">Sécurité & Compte</h6>
                        <span class="text-muted small d-block mb-3" id="password-label">
                            <i class="fas fa-info-circle me-1"></i> Laissez vide si vous ne souhaitez pas modifier le mot de passe actuel.
                        </span>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold">Mot de passe</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" minlength="8" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted fs-8">Min 8 caractères, majuscule, minuscule, chiffre, symbole.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label fw-semibold">Confirmer le mot de passe</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password_confirmation" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 500;">
                        <i class="fas fa-times me-1"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-save" style="background-color: var(--green-dark); border: none; border-radius: 8px; font-weight: 500;">
                        <i class="fas fa-save me-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Styling for profile image avatar container */
    .avatar-upload-container {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto;
    }
    
    .avatar-upload-preview {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid var(--green-dark);
        background-color: #f8fafc;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    
    .avatar-upload-overlay {
        position: absolute;
        bottom: 0;
        right: 0;
        background-color: var(--green-dark);
        color: white;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 2px solid white;
        transition: background-color 0.2s, transform 0.2s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    }
    
    .avatar-upload-overlay:hover {
        background-color: var(--green-mid);
        transform: scale(1.05);
    }
    
    /* Tags layout for group checklist */
    .group-tag {
        background-color: var(--green-light);
        border: 1px solid #b2ddd0;
        color: var(--green-dark);
        font-weight: 600;
        font-size: 0.82rem;
        padding: 4px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        animation: fadeIn 0.15s ease-in-out;
    }
    
    .group-tag .remove-group-tag-btn {
        border: none;
        background: none;
        padding: 0;
        color: var(--green-dark);
        font-size: 0.75rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        transition: background-color 0.15s, color 0.15s;
    }
    
    .group-tag .remove-group-tag-btn:hover {
        background-color: rgba(30, 111, 92, 0.1);
        color: #dc2626;
    }
    
    .group-checkbox-item {
        transition: background-color 0.15s;
    }
    
    .group-checkbox-item:hover {
        background-color: var(--green-xlight);
    }
    
    .group-checkbox-input:checked {
        background-color: var(--green-dark);
        border-color: var(--green-dark);
    }
    
    .group-checkbox-input:checked + label {
        color: var(--green-dark) !important;
        font-weight: 700 !important;
    }
    
    .fs-7 {
        font-size: 0.825rem !important;
    }
    
    .fs-8 {
        font-size: 0.725rem !important;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(2px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
