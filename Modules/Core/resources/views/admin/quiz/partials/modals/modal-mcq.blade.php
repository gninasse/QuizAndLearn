<!-- MCQ Question Modal -->
<div class="modal fade" id="modalMcq" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalMcqLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0" style="border-radius: 20px;">
      
      <!-- Header -->
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalMcqLabel" style="color: var(--green-dark);">
          <i class="bi bi-list-check me-2"></i><span class="modal-title-text">Créer une Question à Choix Multiple (QCM)</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body p-4">
        <form id="formMcq" data-question-id="">
          <input type="hidden" name="type" value="mcq">
          
          <!-- Mode Toggle: Single vs Multiple choice -->
          <div class="mb-4 text-center">
            <label class="form-label fw-bold d-block mb-2">Mode de sélection</label>
            <div class="btn-group w-100" role="group" aria-label="MCQ Type Select">
              <input type="radio" class="btn-check" name="options[multiple]" id="mcqTypeSingle" value="false" checked autocomplete="off">
              <label class="btn btn-outline-primary py-2 fw-semibold" for="mcqTypeSingle" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                <i class="bi bi-dot me-1"></i> Choix Unique
              </label>
              
              <input type="radio" class="btn-check" name="options[multiple]" id="mcqTypeMultiple" value="true" autocomplete="off">
              <label class="btn btn-outline-primary py-2 fw-semibold" for="mcqTypeMultiple" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                <i class="bi bi-check-all me-1"></i> Choix Multiple
              </label>
            </div>
          </div>
          
          <!-- Ligne Points & Group -->
          <div class="row mb-3">
            <div class="col-md-4">
              <label for="mcqQuestionTypeSelect" class="form-label fw-semibold">Type de Question</label>
              <select class="form-select question-type-switcher" id="mcqQuestionTypeSelect" style="border-radius: 8px;">
                <option value="true_false">Vrai / Faux</option>
                <option value="mcq" selected>QCM (Choix unique/multiple)</option>
                <option value="fill_blank">Texte à trous</option>
                <option value="matching">Appariement</option>
                <option value="ordering">Ordonnancement</option>
                <option value="open_text">Texte libre</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="mcqPointsInput" class="form-label fw-semibold">Points</label>
              <input type="number" class="form-control" id="mcqPointsInput" name="points" value="10" min="1" required style="border-radius: 8px;">
            </div>
            <div class="col-md-5">
              <label for="mcqGroupSelect" class="form-label fw-semibold">Groupe de Questions</label>
              <select class="form-select" id="mcqGroupSelect" name="options[group]" style="border-radius: 8px;">
                <option value="general" selected>Général</option>
                <option value="vocabulaire">Vocabulaire</option>
                <option value="grammaire">Grammaire</option>
                <option value="culture_générale">Culture Générale</option>
              </select>
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
              </div>
              <!-- Textarea -->
              <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="mcqQuestionPrompt" name="question_text" rows="3" placeholder="Saisissez l'énoncé de la question..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          
          <!-- MCQ Answers / Propositions -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Propositions de réponses (Cochez la/les bonne(s) réponse(s))</label>
            
            <div class="mcq-answers-wrapper border p-3" style="background-color: var(--green-xlight); border-radius: 12px; border-color: #b2ddd0 !important;">
              <!-- Sortable answers list -->
              <div id="mcqAnswersList" class="d-flex flex-column gap-2">
                <!-- Les propositions seront générées dynamiquement en JS -->
              </div>
              
              <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" id="btnAddMcqAnswer" style="border-radius: 8px; border-style: dashed; border-width: 2px;">
                  <i class="bi bi-plus-lg me-1"></i> Ajouter une proposition
                </button>
              </div>
            </div>
          </div>
          
          <!-- Option partial score (choix multiple uniquement) -->
          <div class="mb-3" id="mcqPartialScoreWrapper" style="display: none;">
            <div class="form-check form-switch p-3 border" style="background-color: var(--blue); border-color: var(--blue-border) !important; border-radius: 8px;">
              <input class="form-check-input" type="checkbox" role="switch" id="mcqPartialScoreInput" name="options[partial_score]">
              <label class="form-check-label fw-bold" for="mcqPartialScoreInput">
                <i class="bi bi-percent me-1 text-primary"></i> Activer le score partiel
              </label>
              <div class="form-text text-muted" style="margin-left: 2.5rem; font-size: 0.75rem;">
                Permet à l'apprenant d'obtenir des points partiels si une partie seulement des réponses est correcte.
              </div>
            </div>
          </div>
          
        </form>
      </div>
      
      <!-- Footer -->
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveMcq" style="background-color: var(--green-dark); border: none; border-radius: 8px;">Enregistrer la Question</button>
      </div>
      
    </div>
  </div>
</div>

<style>
/* CSS specific to MCQ modal rows */
.mcq-answer-row {
  display: flex;
  align-items: center;
  gap: 12px;
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 12px;
  transition: all 0.2s;
}
.mcq-answer-handle {
  cursor: grab;
  color: var(--text-muted);
  font-size: 1.1rem;
}
.mcq-answer-row.correct {
  background-color: var(--green-light) !important;
  border-color: var(--green-dark) !important;
}
.mcq-answer-row.correct .correct-indicator {
  color: var(--green-dark) !important;
  font-weight: bold;
}
.mcq-answer-row.sortable-ghost {
  opacity: 0.4;
}
.mcq-answer-row.sortable-chosen {
  border-color: var(--green-dark);
}
</style>
