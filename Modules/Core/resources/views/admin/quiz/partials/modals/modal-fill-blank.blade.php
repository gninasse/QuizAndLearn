<!-- Fill-in-the-blank Question Modal -->
<div class="modal fade" id="modalFillBlank" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalFillBlankLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0" style="border-radius: 20px;">
      
      <!-- Header -->
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalFillBlankLabel" style="color: var(--green-dark);">
          <i class="bi bi-file-earmark-text me-2"></i><span class="modal-title-text">Ajouter une Question à trous</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body p-4">
        <form id="formFillBlank" data-question-id="">
          <input type="hidden" name="type" value="fill_blank">
          
          <!-- Ligne Type & Points -->
          <div class="row mb-3">
            <div class="col-md-8">
              <label for="fbQuestionTypeSelect" class="form-label fw-semibold">Type de Question</label>
              <select class="form-select question-type-switcher" id="fbQuestionTypeSelect" style="border-radius: 8px;">
                <option value="true_false">Vrai / Faux</option>
                <option value="mcq">QCM (Choix unique/multiple)</option>
                <option value="fill_blank" selected>Texte à trous</option>
                <option value="matching">Appariement</option>
                <option value="ordering">Ordonnancement</option>
                <option value="open_text">Texte libre</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="fbPointsInput" class="form-label fw-semibold">Points</label>
              <input type="number" class="form-control" id="fbPointsInput" name="points" value="10" min="1" required style="border-radius: 8px;">
            </div>
          </div>
          
          <!-- Ligne Titre Interne -->
          <div class="mb-3">
            <label for="fbTitleInput" class="form-label fw-semibold">Titre de la question (interne)</label>
            <input type="text" class="form-control" id="fbTitleInput" name="options[title]" placeholder="Ex: Histoire de France - Paris..." style="border-radius: 8px;">
          </div>
          
          <!-- Textarea area with Instruction and Toolbar -->
          <div class="mb-3">
            <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
              <span>Texte de la question</span>
              <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Saisissez <code>[blank]</code> pour définir un trou.</span>
            </label>
            
            <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
              <!-- Toolbar -->
              <div class="wysiwyg-toolbar d-flex align-items-center justify-content-between p-2 bg-light border-bottom">
                <div class="d-flex align-items-center gap-1">
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
                </div>
                <button type="button" class="btn btn-sm btn-success px-3" id="fbInsertBlankBtn" style="border-radius: 6px; background-color: var(--green-mid); border: none;">
                  <i class="bi bi-plus-circle me-1"></i> Insérer [blank]
                </button>
              </div>
              <!-- Textarea -->
              <textarea class="form-control border-0 p-3" id="fbQuestionPrompt" name="question_text" rows="4" placeholder="Tapez votre texte ici et ajoutez des trous en utilisant le bouton..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          
          <!-- Answer Definitions Section (fond bleu très clair) -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Définition des réponses pour les trous</label>
            <div class="p-3 border" id="fbAnswerDefinitionsContainer" style="background-color: var(--blue); border-color: var(--blue-border) !important; border-radius: 12px;">
              <div id="fbAnswerDefinitionsList" class="d-flex flex-column gap-3">
                <!-- Les lignes de trous seront insérées dynamiquement ici -->
              </div>
              <div class="text-center py-2 text-muted" id="fbNoBlanksPlaceholder" style="font-size: 0.85rem;">
                <i class="bi bi-info-circle me-1"></i> Aucun trou <code>[blank]</code> détecté dans le texte ci-dessus.
              </div>
            </div>
          </div>
          
        </form>
      </div>
      
      <!-- Footer -->
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveFillBlank" style="background-color: var(--green-dark); border: none; border-radius: 8px;">Enregistrer la Question</button>
      </div>
      
    </div>
  </div>
</div>

<style>
/* Style specific to Fill Blank Modal */
.blank-definition-line {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1rem;
}

.blank-num-badge {
  background-color: var(--green-dark);
  color: white;
  font-weight: 700;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}

.blank-tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.blank-tag {
  background-color: var(--green-light);
  color: var(--green-dark);
  border: 1px solid #b2ddd0;
  border-radius: 20px;
  padding: 2px 10px;
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}

.blank-tag-remove {
  cursor: pointer;
  color: #dc2626;
  font-weight: bold;
}
</style>
