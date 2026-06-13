<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Ajouter un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="user_id" name="user_id">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4 text-center">
                            <div class="position-relative d-inline-block">
                                <img id="avatar-preview" src="{{ asset('media/user_avatar.svg') }}" 
                                     class="rounded-circle img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;" alt="Avatar">
                                <label for="avatar" class="position-absolute bottom-0 end-0 bg-primary text-white p-2 rounded-circle" style="cursor: pointer;">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="avatar" name="avatar"  class="d-none" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div> 
                            </div>
                            <div class="row m-t-small">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="user_name">Nom d'utilisateur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="user_name" name="user_name" required>
                            </div>
                        </div>                   
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>
                    

                    <div class="form-group m-t-small">
                        <label for="service">Service</label>
                        <input type="text" class="form-control" id="service" name="service">
                    </div>

                    <div class="password-group">
                        <hr>
                        <h6 id="password-label">Mot de passe <small class="text-muted">(Laisser vide pour si vous ne voulez pas modifier le mot de passe)</small></h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Mot de passe</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" minlength="8">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Min 8 carats, majuscule, minuscule, chiffre, symbole</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password_confirmation">Confirmer le mot de passe</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password_confirmation">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
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
<script>
    window.emptyAvatar = "{{ asset('media/user_avatar.svg') }}";
</script>