<!-- Ordering Question Modal -->
<div class="modal fade" id="modalOrdering" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalOrderingLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0" style="border-radius: 20px;">
      
      <!-- Header -->
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalOrderingLabel" style="color: var(--green-dark);">
          <i class="bi bi-sort-down me-2"></i><span class="modal-title-text">Ajouter une Question d'ordonnancement</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body p-4">
        <form id="formOrdering" data-question-id="">
          <input type="hidden" name="type" value="ordering">
          
          <!-- Ligne Type & Points -->
          <div class="row mb-3">
            <div class="col-md-8">
              <label for="orQuestionTypeSelect" class="form-label fw-semibold">Type de Question</label>
              <select class="form-select question-type-switcher" id="orQuestionTypeSelect" style="border-radius: 8px;">
                <option value="true_false">Vrai / Faux</option>
                <option value="mcq">QCM (Choix unique/multiple)</option>
                <option value="fill_blank">Texte à trous</option>
                <option value="matching">Appariement</option>
                <option value="ordering" selected>Ordonnancement</option>
                <option value="open_text">Texte libre</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="orPointsInput" class="form-label fw-semibold">Points</label>
              <input type="number" class="form-control" id="orPointsInput" name="points" value="10" min="1" required style="border-radius: 8px;">
            </div>
          </div>
          
          <!-- Ligne Titre Interne -->
          <div class="mb-3">
            <label for="orTitleInput" class="form-label fw-semibold">Titre de la question (interne)</label>
            <input type="text" class="form-control" id="orTitleInput" name="options[title]" placeholder="Ex: Ordre chronologique des rois..." style="border-radius: 8px;">
          </div>
          
          <!-- Textarea area with Instruction and Toolbar -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Énoncé de la question</label>
            <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
              <!-- Toolbar -->
              <div class="wysiwyg-toolbar d-flex align-items-center gap-1 p-2 bg-light border-bottom">
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
              </div>
              <!-- Textarea -->
              <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="orQuestionPrompt" name="question_text" rows="3" placeholder="Saisissez la consigne (ex: Classez les éléments dans l'ordre chronologique)..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          
          <!-- Ordering Items Section -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Éléments à ordonner (Saisir dans le bon ordre)</label>
            <div class="ordering-items-wrapper border p-3" style="background-color: var(--blue-xlight, #f0f4f8); border-radius: 12px; border-color: var(--blue-border, #d0e0f0) !important;">
              <!-- Sortable items list -->
              <div id="orderingItemsList" class="d-flex flex-column gap-2">
                <!-- Les lignes d'éléments seront insérées en JS -->
              </div>
              
              <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-3" id="btnAddOrderingItem" style="border-radius: 8px; border-style: dashed; border-width: 2px;">
                <i class="bi bi-plus-lg me-1"></i> Ajouter un élément
              </button>
            </div>
          </div>
          
        </form>
      </div>
      
      <!-- Footer -->
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveOrdering" style="background-color: var(--green-dark); border: none; border-radius: 8px;">Enregistrer la Question</button>
      </div>
      
    </div>
  </div>
</div>

<style>
/* CSS specific to Ordering modal rows */
.ordering-item-row {
  display: flex;
  align-items: center;
  gap: 10px;
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 12px;
}
.ordering-item-handle {
  cursor: grab;
  color: var(--text-muted);
  font-size: 1.1rem;
}
.ordering-item-handle:active {
  cursor: grabbing;
}
.ordering-item-num {
  font-weight: bold;
  color: var(--primary, #0d6efd);
  min-width: 25px;
}
</style>
