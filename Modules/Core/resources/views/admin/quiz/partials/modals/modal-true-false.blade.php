<!-- True/False Question Modal -->
<div class="modal fade" id="modalTrueFalse" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalTrueFalseLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0" style="border-radius: 20px;">
      
      <!-- Header -->
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalTrueFalseLabel" style="color: var(--green-dark);">
          <i class="bi bi-pencil-square me-2"></i><span class="modal-title-text">Ajouter une Question Vrai / Faux</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body p-4">
        <form id="formTrueFalse" data-question-id="">
          <input type="hidden" name="type" value="true_false">
          
          <!-- Ligne Type & Points -->
          <div class="row mb-3">
            <div class="col-md-8">
              <label for="tfQuestionTypeSelect" class="form-label fw-semibold">Type de Question</label>
              <select class="form-select question-type-switcher" id="tfQuestionTypeSelect" style="border-radius: 8px;">
                <option value="true_false" selected>Vrai / Faux</option>
                <option value="mcq">QCM (Choix unique/multiple)</option>
                <option value="fill_blank">Texte à trous</option>
                <option value="matching">Appariement</option>
                <option value="ordering">Ordonnancement</option>
                <option value="open_text">Texte libre</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="tfPointsInput" class="form-label fw-semibold">Points</label>
              <input type="number" class="form-control" id="tfPointsInput" name="points" value="10" min="1" required style="border-radius: 8px;">
            </div>
          </div>
          
          <!-- Prompt area with WYSIWYG Mini-Toolbar -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Énoncé de la question</label>
            <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
              <!-- Toolbar -->
              <div class="wysiwyg-toolbar d-flex align-items-center gap-1 p-2 bg-light border-bottom">
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
                <div class="vr mx-1"></div>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="list" title="Liste à puces" style="border: 1px solid var(--border);"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="link" title="Insérer un lien" style="border: 1px solid var(--border);"><i class="bi bi-link-45deg"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="code" title="Bloc de code" style="border: 1px solid var(--border);"><i class="bi bi-code-slash"></i></button>
              </div>
              <!-- Textarea -->
              <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="tfQuestionPrompt" name="question_text" rows="4" placeholder="Saisissez votre question ici..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          
          <!-- Set Correct Answer (True / False cards) -->
          <div class="mb-3">
            <label class="form-label fw-semibold d-block">Définir la bonne réponse</label>
            <input type="hidden" id="tfCorrectAnswer" name="options[correct_answer]" value="true">
            <div class="row g-3">
              <!-- True Card -->
              <div class="col-md-6">
                <div class="card tf-answer-card selected p-3 text-center border-2 cursor-pointer" data-value="true" style="border-radius: 12px; transition: all 0.2s;">
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="tf-icon"><i class="bi bi-check-circle-fill text-success fs-4"></i></span>
                    <h5 class="fw-bold mb-0 text-success">VRAI</h5>
                  </div>
                </div>
              </div>
              <!-- False Card -->
              <div class="col-md-6">
                <div class="card tf-answer-card p-3 text-center border-2 cursor-pointer" data-value="false" style="border-radius: 12px; transition: all 0.2s;">
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="tf-icon"><i class="bi bi-circle fs-4 text-muted"></i></span>
                    <h5 class="fw-bold mb-0 text-muted">FAUX</h5>
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
        <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveTrueFalse" style="background-color: var(--green-dark); border: none; border-radius: 8px;">Enregistrer la Question</button>
      </div>
      
    </div>
  </div>
</div>

<style>
/* CSS specific to True/False modal answers */
.tf-answer-card {
  border-color: var(--border);
  background-color: var(--surface);
}
.tf-answer-card:hover {
  border-color: var(--green-mid);
  background-color: var(--green-xlight);
}
.tf-answer-card.selected {
  border-color: var(--green-dark) !important;
  background-color: var(--green-light) !important;
}
.tf-answer-card.selected .tf-icon i {
  color: var(--green-dark) !important;
}
.tf-answer-card.selected h5 {
  color: var(--green-dark) !important;
}
</style>
