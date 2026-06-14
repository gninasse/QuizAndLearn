@extends('core::layouts.admin-editor')

@section('title', 'Éditeur d\'Article - ' . $article->title)

@section('page-title',  'Éditeur d\'Article - ' . $article->title)

@push('css')
  <link rel="stylesheet" href="{{ asset('css/admin/article-editor.css') }}">
@endpush

@section('sidebar')
  <div class="sidebar-editor-header">
    <div class="sidebar-editor-title">
      <i class="bi bi-file-earmark-richtext-fill text-success" style="color: var(--green-mid) !important;"></i>
      <span class="text-truncate" style="max-width: 180px;" id="sidebarArticleTitle">{{ $article->title }}</span>
    </div>
    <span class="badge {{ $article->is_active ? 'bg-success' : 'bg-secondary' }}" id="sidebarStatusBadge">
      {{ $article->is_active ? 'Actif' : 'Brouillon' }}
    </span>
    
    <button class="sidebar-publish-btn" id="btnPublishArticle">
      <i class="bi {{ $article->is_active ? 'bi-cloud-arrow-down-fill' : 'bi-cloud-arrow-up-fill' }}"></i>
      <span id="btnPublishText">{{ $article->is_active ? 'Passer en Brouillon' : 'Publier l\'Article' }}</span>
    </button>
  </div>
  
  <li class="menu-item">
    <a href="{{ route('cores.articles.index') }}" class="menu-link">
      <i class="bi bi-arrow-left-circle-fill"></i>
      <span>Retour aux Articles</span>
    </a>
  </li>
  <li class="menu-item active">
    <a href="#" class="menu-link">
      <i class="bi bi-pencil-square"></i>
      <span>Conception</span>
    </a>
  </li>
  <li class="menu-item">
    <a href="#modalSettings" class="menu-link" id="btnOpenSettings">
      <i class="bi bi-sliders"></i>
      <span>Paramètres & SEO</span>
    </a>
  </li>
  <li class="menu-item">
    <a href="{{ route('cores.articles.export', $article->id) }}" class="menu-link">
      <i class="bi bi-download"></i>
      <span>Exporter HTML</span>
    </a>
  </li>
@endsection

@section('editor-content')
<div class="container-fluid p-0">
  
  <!-- Secondary Topbar -->
  <div class="secondary-topbar">
    <div class="secondary-search">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Rechercher dans l'article..." id="editorSearch">
    </div>
    <div class="secondary-nav">
      <a href="{{ route('cores.dashboard') }}" class="secondary-nav-link">Dashboard</a>
      <a href="{{ route('cores.articles.index') }}" class="secondary-nav-link">Articles</a>
      <button class="btn btn-sm btn-outline-secondary py-2 px-3 me-2" id="btnPreviewArticle" style="border-radius: 8px;">
        <i class="bi bi-eye"></i> Prévisualiser
      </button>
      <button class="btn btn-sm btn-primary py-2 px-3" id="btnSaveArticle" style="border-radius: 8px; background-color: var(--green-dark); border: none;">
        <i class="bi bi-check-circle"></i> Enregistrer
      </button>
    </div>
  </div>

  <!-- Workspace Row -->
  <div class="row">
    
    <!-- Left Editor Area (70%) -->
    <div class="col-lg-8 mb-4">
      <div class="editor-workspace-card">
        
        <!-- WYSIWYG Editing Toolbar -->
        <div class="editor-toolbar-wrapper">
          <div class="editor-toolbar-btn-group">
            <button class="toolbar-btn" data-cmd="bold" title="Gras (Ctrl+B)">
              <i class="bi bi-type-bold"></i>
            </button>
            <button class="toolbar-btn" data-cmd="italic" title="Italique (Ctrl+I)">
              <i class="bi bi-type-italic"></i>
            </button>
            <button class="toolbar-btn" data-cmd="underline" title="Souligné (Ctrl+U)">
              <i class="bi bi-type-underline"></i>
            </button>
            <button class="toolbar-btn" data-cmd="strikeThrough" title="Barré">
              <i class="bi bi-type-strikethrough"></i>
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" data-cmd="formatBlock" data-val="h2" title="Titre 2">
              <i class="bi bi-type-h2"></i>
            </button>
            <button class="toolbar-btn" data-cmd="formatBlock" data-val="h3" title="Titre 3">
              <i class="bi bi-type-h3"></i>
            </button>
            <button class="toolbar-btn" data-cmd="formatBlock" data-val="p" title="Paragraphe">
              <i class="bi bi-type-paragraph"></i>
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" data-cmd="insertUnorderedList" title="Liste à puces">
              <i class="bi bi-list-ul"></i>
            </button>
            <button class="toolbar-btn" data-cmd="insertOrderedList" title="Liste numérotée">
              <i class="bi bi-list-ol"></i>
            </button>
            <button class="toolbar-btn" data-cmd="formatBlock" data-val="blockquote" title="Citation">
              <i class="bi bi-quote"></i>
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" data-cmd="createLink" title="Insérer un lien">
              <i class="bi bi-link-45deg"></i>
            </button>
            <button class="toolbar-btn" data-cmd="unlink" title="Supprimer le lien">
              <i class="bi bi-link-45deg text-danger" style="text-decoration: line-through;"></i>
            </button>
            <button class="toolbar-btn" id="btnInsertLocalImage" title="Insérer une image">
              <i class="bi bi-image"></i>
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn text-danger" data-cmd="removeFormat" title="Effacer le formatage">
              <i class="bi bi-eraser-fill"></i>
            </button>
          </div>
        </div>
        
        <!-- Hidden input for file upload -->
        <input type="file" id="imageUploadInput" accept="image/*" class="d-none">
        <input type="file" id="audioUploadInput" accept="audio/*" class="d-none">

        <!-- Editor canvas -->
        <div class="editor-canvas">
          <!-- H1 Title (Editable) -->
          <div class="article-title-editor-wrapper">
            <h1 contenteditable="true" id="articleTitle" placeholder="Titre de l'article...">{{ $article->title }}</h1>
          </div>
          <hr class="editor-separator">
          
          <!-- WYSIWYG Content Editable -->
          <div contenteditable="true" id="articleContent" class="article-body-editor" placeholder="Commencez à rédiger votre article ici...">
            {!! $article->content !!}
          </div>
        </div>

        <!-- Status Toolbar -->
        <div class="editor-status-bar">
          <div class="status-metrics">
            <span class="status-metric-item">
              <i class="bi bi-file-earmark-word"></i> <span id="wordCount">0</span> mots
            </span>
            <span class="status-metric-item">
              <i class="bi bi-hash"></i> <span id="charCount">0</span> caractères
            </span>
          </div>
          <div class="status-save-state">
            <span id="autosaveStatus">
              <i class="bi bi-cloud-check text-success"></i> Synchronisé
            </span>
          </div>
        </div>

      </div>
    </div>
    
    <!-- Right Area - Elements & Access Pane (30%) -->
    <div class="col-lg-4">
      
      <!-- Right Pane Container using Premium Card -->
      <div class="right-pane-card">
        <!-- Tab navigation inside Right Card -->
        <ul class="nav nav-pills custom-pills mb-3" id="rightPaneTabs" role="tablist">
          <li class="nav-item" role="presentation" style="flex: 1;">
            <button class="nav-link w-100 active" id="elements-tab" data-bs-toggle="tab" data-bs-target="#elementsPane" type="button" role="tab" aria-controls="elementsPane" aria-selected="true">
              <i class="bi bi-grid-fill me-2"></i>Éléments
            </button>
          </li>
          <li class="nav-item" role="presentation" style="flex: 1;">
            <button class="nav-link w-100" id="access-tab" data-bs-toggle="tab" data-bs-target="#accessPane" type="button" role="tab" aria-controls="accessPane" aria-selected="false">
              <i class="bi bi-collection-fill me-2"></i>Accès & Statut
            </button>
          </li>
        </ul>
        
        <div class="tab-content" id="rightPaneTabsContent">
          
          <!-- Tab 1: Elements Pane -->
          <div class="tab-pane fade show active" id="elementsPane" role="tabpanel" aria-labelledby="elements-tab">
            <p class="text-muted" style="font-size: 0.8rem;">
              Cliquez sur un élément ci-dessous pour l'insérer à l'emplacement actuel de votre curseur dans l'article.
            </p>
            
            <div class="elements-list">
              <!-- Text layouts -->
              <h6 class="element-section-title">Mise en forme de texte</h6>
              <div class="element-grid">
                <button class="element-insert-btn" data-element="h2">
                  <i class="bi bi-type-h2"></i>
                  <span>Titre H2</span>
                </button>
                <button class="element-insert-btn" data-element="h3">
                  <i class="bi bi-type-h3"></i>
                  <span>Titre H3</span>
                </button>
                <button class="element-insert-btn" data-element="blockquote">
                  <i class="bi bi-quote"></i>
                  <span>Citation</span>
                </button>
                <button class="element-insert-btn" data-element="callout">
                  <i class="bi bi-info-circle-fill"></i>
                  <span>Encart info</span>
                </button>
              </div>

              <!-- Structures / Columns -->
              <h6 class="element-section-title mt-4">Mises en page (Layouts)</h6>
              <div class="element-grid">
                <button class="element-insert-btn" data-element="columns-2">
                  <i class="bi bi-columns-gap"></i>
                  <span>2 Colonnes</span>
                </button>
                <button class="element-insert-btn" data-element="columns-3">
                  <i class="bi bi-columns"></i>
                  <span>3 Colonnes</span>
                </button>
              </div>

              <!-- Media items -->
              <h6 class="element-section-title mt-4">Médias</h6>
              <div class="element-grid">
                <button class="element-insert-btn" data-element="image-placeholder">
                  <i class="bi bi-image"></i>
                  <span>Image</span>
                </button>
                <button class="element-insert-btn" data-element="video-placeholder">
                  <i class="bi bi-youtube"></i>
                  <span>Vidéo Embed</span>
                </button>
                <button class="element-insert-btn" data-element="audio-upload">
                  <i class="bi bi-music-note-beamed"></i>
                  <span>Audio</span>
                </button>
              </div>

              <!-- Interactive Widgets -->
              <h6 class="element-section-title mt-4">Interactif</h6>
              <div class="element-grid">
                <button class="element-insert-btn w-100 py-3" data-element="quiz-widget" style="grid-column: span 2; border-color: var(--green-mid); background: var(--green-xlight);">
                  <i class="bi bi-question-circle text-success" style="font-size: 1.4rem;"></i>
                  <span class="fw-bold text-success">Intégrer un Quiz</span>
                </button>
              </div>

            </div>
          </div>
          
          <!-- Tab 2: Status & Access Pane -->
          <div class="tab-pane fade" id="accessPane" role="tabpanel" aria-labelledby="access-tab">
            
            <!-- Quick Active State Toggle -->
            <div class="access-section-card mb-4">
              <h6 class="fw-bold mb-3" style="color: var(--green-dark);">Statut de visibilité</h6>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="paramIsActive" name="is_active" {{ $article->is_active ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="paramIsActive">Article Publié / Actif</label>
              </div>
              <p class="text-muted mb-0" style="font-size: 0.75rem;">
                Si désactivé, l'article sera en mode brouillon et invisible pour les apprenants.
              </p>
            </div>

            <!-- Group Access Search and Tagging -->
            <div class="access-section-card">
              <h6 class="fw-bold mb-2" style="color: var(--green-dark);"><i class="bi bi-collection-fill me-2"></i>Groupes autorisés</h6>
              <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 1rem;">
                Attribuez cet article à des groupes pour restreindre sa visibilité aux apprenants de ces groupes.
              </p>

              <!-- Search input for adding groups -->
              <div class="group-search-wrapper">
                <div class="input-group">
                  <span class="input-group-text bg-white border-end-0" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                    <i class="bi bi-search text-muted"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" id="groupSearchInput" placeholder="Rechercher un groupe..." autocomplete="off" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                </div>
                <!-- Results Dropdown -->
                <div class="group-search-results" id="groupSearchResults"></div>
              </div>

              <!-- Assigned Groups List -->
              <div class="groups-list mt-3" id="assignedGroupsList" data-article-id="{{ $article->id }}">
                @forelse($article->groups as $group)
                  <div class="group-badge-card" data-id="{{ $group->id }}">
                    <div class="group-badge-info">
                      <span class="group-badge-name">{{ $group->name }}</span>
                      <span class="group-badge-learners">{{ $group->learners()->count() }} apprenants</span>
                    </div>
                    <button class="group-remove-btn" data-id="{{ $group->id }}" title="Retirer ce groupe">
                      <i class="bi bi-x"></i>
                    </button>
                  </div>
                @empty
                  <div class="text-center py-4 text-muted" id="noGroupsPlaceholder">
                    <i class="bi bi-tags display-6 mb-2 d-block"></i>
                    <p class="mb-0" style="font-size: 0.8rem;">Visible pour tous (aucun groupe assigné).</p>
                  </div>
                @endforelse
              </div>
            </div>

          </div>
          
        </div>
      </div>
      
    </div>
    
  </div>

</div>
@endsection

@section('modals')
  <!-- Settings & SEO Modal -->
  @include('core::admin.article.partials.modals.modal-article-settings')
@endsection

@push('scripts')
  <!-- Main Article Editor JS -->
  <script src="{{ asset('js/admin/article-editor.js') }}"></script>
@endpush
