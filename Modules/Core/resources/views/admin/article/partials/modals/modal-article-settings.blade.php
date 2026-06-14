<!-- Modal Paramètres & SEO de l'Article -->
<div class="modal fade" id="modalSettings" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalSettingsLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
      <div class="modal-header border-0 bg-light py-3">
        <h5 class="modal-title fw-bold" id="modalSettingsLabel" style="color: var(--green-dark);">
          <i class="bi bi-sliders me-2"></i>Paramètres & Référencement (SEO)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="articleSettingsForm">
          @csrf
          <div class="row">
            <!-- Left Column: Settings -->
            <div class="col-md-6 mb-3">
              <h6 class="fw-bold mb-3" style="color: var(--green-mid);"><i class="bi bi-gear-fill me-2"></i>Métadonnées</h6>
              
              <div class="mb-3">
                <label for="settingsCategory" class="form-label fw-semibold">Catégorie</label>
                <input type="text" class="form-control" id="settingsCategory" name="category" placeholder="Ex: Développement, Management..." style="border-radius: 8px;" value="{{ $article->category }}">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold d-block">Auteur / Créateur</label>
                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded" style="border: 1px solid var(--border);">
                  <img src="{{ $article->creator && $article->creator->avatar ? asset('storage/' . $article->creator->avatar) : asset('adminlte/assets/img/avatar5.png') }}" alt="Avatar" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; border: 2px solid var(--green-dark);">
                  <div>
                    <div class="fw-bold text-truncate" style="max-width: 200px;">
                      {{ $article->creator ? ($article->creator->name . ' ' . $article->creator->last_name) : 'Système' }}
                    </div>
                    <div class="text-muted small">
                      Créé le {{ $article->created_at ? $article->created_at->format('d/m/Y') : now()->format('d/m/Y') }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">État de publication</label>
                <div class="form-check form-switch p-3 bg-light rounded" style="border: 1px solid var(--border); padding-left: 3.5em !important;">
                  <input class="form-check-input" type="checkbox" role="switch" id="settingsIsActive" name="is_active" {{ $article->is_active ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold ms-2" for="settingsIsActive" id="settingsIsActiveLabel">
                    {{ $article->is_active ? 'Publié (Actif)' : 'Brouillon (Inactif)' }}
                  </label>
                </div>
              </div>
            </div>

            <!-- Right Column: SEO -->
            <div class="col-md-6 mb-3">
              <h6 class="fw-bold mb-3" style="color: var(--green-mid);"><i class="bi bi-search me-2"></i>Optimisation SEO</h6>
              
              <div class="mb-3">
                <label for="settingsSeoKeywords" class="form-label fw-semibold">Mots-clés SEO</label>
                <input type="text" class="form-control" id="settingsSeoKeywords" name="seo_keywords" placeholder="mot-clé, article, tags..." style="border-radius: 8px;" value="{{ $article->seo_keywords }}">
                <div class="form-text">Séparez les mots-clés par des virgules.</div>
              </div>

              <div class="mb-3">
                <label for="settingsSeoDescription" class="form-label fw-semibold">Description SEO (Meta-description)</label>
                <textarea class="form-control" id="settingsSeoDescription" name="seo_description" rows="5" placeholder="Saisissez une courte description de l'article pour les moteurs de recherche..." style="border-radius: 8px;" maxlength="160">{{ $article->seo_description }}</textarea>
                <div class="form-text d-flex justify-content-between">
                  <span>Recommandé : max 160 caractères.</span>
                  <span id="seoDescCounter" class="fw-semibold">0/160</span>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 bg-light py-3" style="border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
        <button type="button" class="btn btn-primary" id="btnSaveSettings" style="background-color: var(--green-dark); border: none; border-radius: 8px;">
          <i class="bi bi-check-circle me-1"></i>Enregistrer les paramètres
        </button>
      </div>
    </div>
  </div>
</div>
