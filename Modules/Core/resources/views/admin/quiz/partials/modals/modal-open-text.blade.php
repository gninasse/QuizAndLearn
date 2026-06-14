<!-- Open Text Question Modal -->
<div class="modal fade" id="modalOpenText" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalOpenTextLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0" style="border-radius: 20px;">
      
      <!-- Header -->
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalOpenTextLabel" style="color: var(--green-dark);">
          <i class="bi bi-chat-left-text me-2"></i><span class="modal-title-text">Créer une Question Texte libre</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body p-4">
        <form id="formOpenText" data-question-id="">
          <input type="hidden" name="type" value="open_text">
          
          <div class="row g-4">
            
            <!-- Left Column: Settings (60%) -->
            <div class="col-lg-7 col-md-7">
              <!-- Switch Type -->
              <div class="mb-3">
                <label for="otQuestionTypeSelect" class="form-label fw-semibold">Type de Question</label>
                <select class="form-select question-type-switcher" id="otQuestionTypeSelect" style="border-radius: 8px;">
                  <option value="true_false">Vrai / Faux</option>
                  <option value="mcq">QCM (Choix unique/multiple)</option>
                  <option value="fill_blank">Texte à trous</option>
                  <option value="matching">Appariement</option>
                  <option value="ordering">Ordonnancement</option>
                  <option value="open_text" selected>Texte libre</option>
                </select>
              </div>
              
              <!-- Prompt textarea with WYSIWYG Mini-Toolbar -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Énoncé de la question <span class="text-danger">*</span></label>
                <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
                  <!-- Toolbar -->
                  <div class="wysiwyg-toolbar d-flex align-items-center gap-1 p-2 bg-light border-bottom">
                    <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
                    <div class="vr mx-1"></div>
                    <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="list" title="Liste à puces" style="border: 1px solid var(--border);"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="link" title="Insérer un lien" style="border: 1px solid var(--border);"><i class="bi bi-link-45deg"></i></button>
                  </div>
                  <!-- Textarea -->
                  <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="otQuestionPrompt" name="question_text" rows="5" placeholder="Écrivez votre question ici..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
                </div>
              </div>
              
              <!-- Settings Row (Points + Max Char + Required) -->
              <div class="row align-items-center g-3">
                <div class="col-sm-4">
                  <label for="otPointsInput" class="form-label fw-semibold">Points</label>
                  <input type="number" class="form-control" id="otPointsInput" name="points" value="10" min="1" required style="border-radius: 8px;">
                </div>
                <div class="col-sm-4">
                  <label for="otMaxCharsInput" class="form-label fw-semibold">Caractères Max</label>
                  <input type="number" class="form-control" id="otMaxCharsInput" name="options[max_characters]" value="500" min="10" required style="border-radius: 8px;">
                </div>
                <div class="col-sm-4">
                  <div class="form-check form-switch pt-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="otRequiredInput" name="options[required]" checked>
                    <label class="form-check-label fw-semibold" for="otRequiredInput">Obligatoire</label>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Right Column: Learner Preview (40%) -->
            <div class="col-lg-5 col-md-5">
              <label class="form-label fw-semibold">Aperçu Apprenant</label>
              
              <div class="card border-2 shadow-sm rounded-4 p-4 position-relative" style="background-color: var(--surface); min-height: 280px; border-color: var(--border) !important;">
                <!-- Preview badge -->
                <span class="badge position-absolute" style="top: 15px; right: 15px; background-color: var(--yellow-border); color: #fff; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">
                  Preview Mode
                </span>
                
                <!-- Card Header -->
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                  <span class="badge bg-secondary" id="otPreviewNumBadge" style="background-color: var(--green-dark) !important; font-size: 0.75rem;">QUESTION 1</span>
                  <span class="text-muted fw-bold" style="font-size: 0.8rem;" id="otPreviewPointsBadge">10 points</span>
                </div>
                
                <!-- Prompt Preview -->
                <div class="preview-prompt-text mb-4 text-break" id="otPreviewPrompt" style="font-size: 0.95rem; font-weight: 600; min-height: 40px; color: var(--text);">
                  <span class="text-muted italic" style="font-style: italic;">L'énoncé de la question s'affichera ici en temps réel...</span>
                </div>
                
                <!-- Answer Mock Input -->
                <div class="mock-answer-box border p-3 rounded-3" style="background-color: var(--bg); position: relative; min-height: 120px;">
                  <div class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size: 0.8rem;">
                    <i class="bi bi-keyboard-fill"></i>
                    <span>Saisissez votre réponse...</span>
                  </div>
                  <!-- Word/Char Counter -->
                  <div class="text-end text-muted position-absolute" style="bottom: 10px; right: 15px; font-size: 0.75rem;" id="otPreviewCounter">
                    0 / 500 caractères
                  </div>
                </div>
                
              </div>
            </div>
            
          </div>
          
        </form>
      </div>
      
      <!-- Footer -->
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveOpenText" style="background-color: var(--green-dark); border: none; border-radius: 8px;">Enregistrer la Question</button>
      </div>
      
    </div>
  </div>
</div>
