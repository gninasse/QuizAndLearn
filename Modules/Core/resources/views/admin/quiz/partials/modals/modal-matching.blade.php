<!-- Matching Question Modal -->
<div class="modal fade" id="modalMatching" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalMatchingLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0" style="border-radius: 20px;">
      
      <!-- Header -->
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalMatchingLabel" style="color: var(--green-dark);">
          <i class="bi bi-arrow-left-right me-2"></i><span class="modal-title-text">Créer une Question d'appariement</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Body -->
      <div class="modal-body p-4">
        <form id="formMatching" data-question-id="">
          <input type="hidden" name="type" value="matching">
          
          <!-- Ligne Type & Points & Group -->
          <div class="row mb-3">
            <div class="col-md-5">
              <label for="mtQuestionTypeSelect" class="form-label fw-semibold">Type de Question</label>
              <select class="form-select question-type-switcher" id="mtQuestionTypeSelect" style="border-radius: 8px;">
                <option value="true_false">Vrai / Faux</option>
                <option value="mcq">QCM (Choix unique/multiple)</option>
                <option value="fill_blank">Texte à trous</option>
                <option value="matching" selected>Appariement</option>
                <option value="ordering">Ordonnancement</option>
                <option value="open_text">Texte libre</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="mtPointsInput" class="form-label fw-semibold">Points</label>
              <input type="number" class="form-control" id="mtPointsInput" name="points" value="10" min="1" required style="border-radius: 8px;">
            </div>
            <div class="col-md-4">
              <label for="mtGroupSelect" class="form-label fw-semibold">Groupe de Questions</label>
              <select class="form-select" id="mtGroupSelect" name="options[group]" style="border-radius: 8px;">
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
              <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="mtQuestionPrompt" name="question_text" rows="3" placeholder="Saisissez les instructions pour l'appariement..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          
          <!-- Matching Pairs Section -->
          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-semibold mb-0">Paires d'appariement (Terme → Définition)</label>
              <button type="button" class="btn btn-xs btn-outline-secondary" id="btnShufflePairsPreview" style="font-size:0.75rem; border-radius: 6px; padding: 2px 8px;">
                <i class="bi bi-shuffle me-1"></i> Shuffle Preview
              </button>
            </div>
            
            <div class="matching-pairs-wrapper border p-3" style="background-color: var(--green-xlight); border-radius: 12px; border-color: #b2ddd0 !important;">
              <!-- Sortable pairs list -->
              <div id="matchingPairsList" class="d-flex flex-column gap-2">
                <!-- Les lignes de paires seront insérées en JS -->
              </div>
              
              <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-3" id="btnAddMatchingPair" style="border-radius: 8px; border-style: dashed; border-width: 2px;">
                <i class="bi bi-plus-lg me-1"></i> Ajouter une paire
              </button>
            </div>
          </div>
          
        </form>
      </div>
      
      <!-- Footer -->
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveMatching" style="background-color: var(--green-dark); border: none; border-radius: 8px;">Enregistrer la Question</button>
      </div>
      
    </div>
  </div>
</div>

<style>
/* CSS specific to Matching modal rows */
.matching-pair-row {
  display: flex;
  align-items: center;
  gap: 10px;
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 12px;
}
.matching-pair-handle {
  cursor: grab;
  color: var(--text-muted);
  font-size: 1.1rem;
}
.matching-pair-row.sortable-ghost {
  opacity: 0.4;
}
.matching-pair-row.sortable-chosen {
  border-color: var(--green-dark);
}
</style>
