<!-- Modal -->
<div class="modal fade" id="examModal" role="dialog" aria-labelledby="examModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Créer un examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="examForm">
                @csrf
                <input type="hidden" id="exam_id" name="exam_id">
                
                <div class="modal-body">
                    <!-- Title -->
                    <div class="form-group mb-3">
                        <label for="title" class="form-label font-weight-bold">Titre de l'examen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required placeholder="Ex: Examen de Mathématiques Semestre 1">
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group mb-3">
                        <label for="description" class="form-label">Description / Consignes</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Saisir les consignes de l'examen..."></textarea>
                    </div>
                    
                    <!-- Duration & Passing Score -->
                    <div class="row mb-3">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="duration" class="form-label">Durée (minutes) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="duration" name="duration" min="1" value="60" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="passing_score" class="form-label">Score de réussite (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" min="0" max="100" value="50" required>
                            </div>
                        </div>
                    </div>

                    <!-- Note Max & Max Attempts -->
                    <div class="row mb-3">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="note_max" class="form-label">Note sur (par défaut 20) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="note_max" name="note_max" min="1" value="20" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="max_attempts" class="form-label">Tentatives max <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="max_attempts" name="max_attempts" min="1" value="1" required>
                            </div>
                        </div>
                    </div>

                    <!-- Dates d'ouverture et fermeture -->
                    <div class="row mb-3">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="available_from" class="form-label">Date d'ouverture (début de la fenêtre)</label>
                                <input type="datetime-local" class="form-control" id="available_from" name="available_from">
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="available_until" class="form-label">Date de fermeture (fin de la fenêtre)</label>
                                <input type="datetime-local" class="form-control" id="available_until" name="available_until">
                            </div>
                        </div>
                    </div>

                    <!-- Groupes assignés -->
                    <div class="form-group mb-4">
                        <label for="group_ids" class="form-label">Groupes d'apprenants autorisés</label>
                        <select id="group_ids" name="group_ids[]" class="form-select select2" multiple data-placeholder="Sélectionner les groupes d'apprenants" style="width: 100%;">
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-shield-alt text-danger me-2"></i>Paramètres de Sécurité et Triche</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-4 col-sm-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="plein_ecran_force" name="plein_ecran_force" value="1" checked>
                                <label class="form-check-label font-weight-bold" for="plein_ecran_force">Plein écran forcé</label>
                            </div>
                            <small class="text-muted d-block mb-3">Exige le mode plein écran pour démarrer et continuer l'examen.</small>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="anti_capture_strict" name="anti_capture_strict" value="1" checked>
                                <label class="form-check-label font-weight-bold" for="anti_capture_strict">Anti-capture strict</label>
                            </div>
                            <small class="text-muted d-block mb-3">Détecte et bloque les captures ou impressions d'écran.</small>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="navigation_interdite" name="navigation_interdite" value="1" checked>
                                <label class="form-check-label font-weight-bold" for="navigation_interdite">Navigation interdite</label>
                            </div>
                            <small class="text-muted d-block mb-3">Bloque ou enregistre les changements d'onglets (Alt+Tab).</small>
                        </div>
                    </div>

                    <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-trophy text-warning me-2"></i>Classement & Résultats</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 col-sm-12 mb-3">
                            <label for="publication_resultats" class="form-label">Publication des résultats</label>
                            <select id="publication_resultats" name="publication_resultats" class="form-select">
                                <option value="immediate">Immédiate (après soumission)</option>
                                <option value="apres_fermeture">Après fermeture de la fenêtre</option>
                                <option value="manuelle">Manuelle (par le formateur)</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-check form-switch mb-2 mt-4">
                                <input class="form-check-input" type="checkbox" id="classement_visible" name="classement_visible" value="1" checked>
                                <label class="form-check-label font-weight-bold" for="classement_visible">Classement visible</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="classement_anonyme" name="classement_anonyme" value="1">
                                <label class="form-check-label font-weight-bold" for="classement_anonyme">Classement anonyme</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3 mt-4 border-top pt-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label font-weight-bold text-success" for="is_active">Actif (Publié et visible par les apprenants éligibles)</label>
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
