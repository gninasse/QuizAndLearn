<!-- Modal -->
<div class="modal fade" id="quizModal" tabindex="-1" role="dialog" aria-labelledby="quizModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Créer un quiz</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quizForm">
                @csrf
                <input type="hidden" id="quiz_id" name="quiz_id">
                
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="title">Titre du quiz <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Description du quiz..."></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="duration">Durée (minutes)</label>
                                <input type="number" class="form-control" id="duration" name="duration" min="1" placeholder="Laisser vide pour illimité">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="passing_score">Score de réussite (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" min="0" max="100" value="50" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="group_ids">Groupes assignés</label>
                        <select id="group_ids" name="group_ids[]" class="form-select select2" multiple data-placeholder="Sélectionner les groupes" style="width: 100%;">
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
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
