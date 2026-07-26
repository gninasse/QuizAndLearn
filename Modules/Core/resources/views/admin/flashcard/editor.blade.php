@extends('core::layouts.admin-editor')

@section('title', 'Éditeur de Flashcards - ' . $deck->titre)

@section('page-title', 'Éditeur de Flashcards - ' . $deck->titre)

@push('css')
  <link rel="stylesheet" href="{{ asset('css/admin/quiz-editor.css') }}">
  <style>
    .question-item {
      border-left: 4px solid var(--green-dark) !important;
    }
    .card-preview-box {
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 15px;
      margin-bottom: 8px;
      background: var(--surface-1);
    }
    .card-preview-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--text-3);
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .card-preview-text {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-1);
    }
  </style>
@endpush

@section('sidebar')
  <div class="sidebar-editor-header">
    <div class="sidebar-editor-title">
      <i class="bi bi-card-text text-success" style="color: var(--green-mid) !important; font-size: 20px;"></i>
      <span class="text-truncate" style="max-width: 180px;" id="sidebarDeckTitle">{{ $deck->titre }}</span>
    </div>
    <span class="badge {{ $deck->active ? 'bg-success' : 'bg-secondary' }}" id="sidebarStatusBadge">
      {{ $deck->active ? 'Actif' : 'Draft Mode' }}
    </span>
  </div>
  
  <li class="menu-item">
    <a href="{{ route('cores.flashcard-decks.index') }}" class="menu-link">
      <i class="bi bi-arrow-left-circle-fill"></i>
      <span>Retour à la liste</span>
    </a>
  </li>
  <li class="menu-item active">
    <a href="#" class="menu-link">
      <i class="bi bi-layers-fill"></i>
      <span>Gérer les Cartes</span>
    </a>
  </li>
  <li class="menu-item">
    <a href="{{ route('cores.editor.flashcards.preview', $deck->id) }}" class="menu-link">
      <i class="bi bi-play-circle-fill"></i>
      <span>Prévisualiser</span>
    </a>
  </li>

@endsection

@section('editor-content')
<div class="container-fluid p-0">
  
  <!-- Secondary Topbar -->
  <div class="secondary-topbar">
    <div class="secondary-search">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Rechercher des cartes..." id="cardSearch">
    </div>
    <div class="secondary-nav">
      <a href="{{ route('cores.dashboard') }}" class="secondary-nav-link">Dashboard</a>
      <a href="{{ route('cores.flashcard-decks.index') }}" class="secondary-nav-link">Flashcards</a>
    </div>
  </div>

  <!-- Breadcrumbs & Deck Info Block -->
  <div class="quiz-info-card" style="background: white; border-bottom: 1px solid var(--border); padding: 1.5rem;">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-2" style="font-size: 0.85rem;">
        <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}" class="text-decoration-none text-muted">Accueil</a></li>
        <li class="breadcrumb-item"><a href="{{ route('cores.flashcard-decks.index') }}" class="text-decoration-none text-muted">Flashcards</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $deck->titre }}</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="fw-bold h3 mb-2" id="deckTitleDisplay">{{ $deck->titre }}</h1>
        <p class="text-muted mb-0" id="deckDescriptionDisplay">{{ $deck->description ?: 'Aucune description fournie.' }}</p>
      </div>
    </div>
  </div>

  <!-- Main Workspace Grid -->
  <div class="row m-3">
    
    <!-- Left Column: Cards List -->
    <div class="col-lg-8 mb-4">
      <div class="questions-card" style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; box-shadow: var(--shadow-sm);">
        <div class="questions-header d-flex justify-content-between align-items-center mb-3">
          <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0" style="color: var(--green-dark);">Cartes Flash</h5>
            <span class="badge bg-primary" id="cardCounter">{{ $deck->cards->count() }} au total</span>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark" id="btnPrintDeck" style="border-radius: 8px;">
              <i class="bi bi-print me-1"></i> Imprimer (A4)
            </button>
            <button class="btn btn-sm btn-primary" id="btnAddCard" style="border-radius: 8px;">
              <i class="bi bi-plus-circle me-1"></i> Ajouter une carte
            </button>
          </div>
        </div>

        <div class="questions-list-wrapper" id="cardsList" data-deck-id="{{ $deck->id }}">
          @forelse($deck->cards as $index => $card)
            <div class="question-item d-flex align-items-stretch p-3 mb-3 bg-light" data-id="{{ $card->id }}" style="border-radius: 12px; transition: all 0.2s;">
              <div class="question-num me-3 fw-bold align-self-center" style="color: var(--green-dark);">#{{ $index + 1 }}</div>
              
              <div class="question-main flex-grow-1 d-flex flex-column gap-2">
                <div class="card-preview-box">
                  <div class="card-preview-label">Recto (Devant)</div>
                  <div class="card-preview-text">{!! nl2br(e($card->recto)) !!}</div>
                </div>
                <div class="card-preview-box">
                  <div class="card-preview-label">Verso (Derrière)</div>
                  <div class="card-preview-text" style="color: var(--brand-700);">{!! nl2br(e($card->verso)) !!}</div>
                </div>
                @if($card->tags || $card->note)
                  <div class="d-flex flex-wrap gap-2 mt-1">
                    @if($card->tags)
                      @foreach(explode(',', $card->tags) as $tag)
                        <span class="badge bg-secondary" style="font-size: 11px;">#{{ trim($tag) }}</span>
                      @endforeach
                    @endif
                    @if($card->note)
                      <span class="text-muted" style="font-size: 11px; font-style: italic;"><i class="bi bi-info-circle"></i> {{ $card->note }}</span>
                    @endif
                  </div>
                @endif
              </div>

              <div class="question-actions d-flex flex-column justify-content-center gap-2 ms-3">
                <button class="btn btn-sm btn-outline-secondary preview-card" data-id="{{ $card->id }}" style="border-radius: 6px;" title="Aperçu de la carte">
                  <i class="bi bi-eye-fill"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary edit-card" data-id="{{ $card->id }}" style="border-radius: 6px;" title="Modifier la carte">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger delete-card" data-id="{{ $card->id }}" style="border-radius: 6px;" title="Supprimer la carte">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </div>
            </div>
          @empty
            <div class="text-center py-5" id="noCardsAlert">
              <i class="bi bi-card-text text-muted" style="font-size: 3rem;"></i>
              <p class="text-muted mt-3">Aucune carte dans ce paquet. Utilisez le bouton "Ajouter" ou générez des cartes automatiquement à droite !</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- Right Column: Settings & Auto Generation -->
    <div class="col-lg-4">
      
      <!-- Group targeting box -->
      <div class="card mb-4" style="border-radius: 12px; box-shadow: var(--shadow-sm);">
        <div class="card-header bg-light">
          <h6 class="fw-bold mb-0 text-success" style="color:var(--green-mid);"><i class="bi bi-people-fill me-2"></i> Groupes cibles</h6>
        </div>
        <div class="card-body">
          <p class="text-muted small">Sélectionnez les groupes d'étudiants qui auront accès à ce paquet de flashcards.</p>
          <div class="mb-3">
            <select class="form-control select2" id="deckGroupsAssign" multiple="multiple" style="width: 100%;">
              @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ $deck->groups->contains($group->id) ? 'selected' : '' }}>{{ $group->name }}</option>
              @endforeach
            </select>
          </div>
          <button class="btn btn-primary btn-sm w-100" id="btnSaveGroups" style="border-radius: 8px;">
            Enregistrer les affectations
          </button>
        </div>
      </div>

      <!-- Auto Generation box -->
      <div class="card mb-4" style="border-radius: 12px; box-shadow: var(--shadow-sm);">
        <div class="card-header bg-light">
          <h6 class="fw-bold mb-0 text-success" style="color:var(--green-mid);"><i class="bi bi-lightning-charge-fill me-2"></i> Génération automatique</h6>
        </div>
        <div class="card-body">
          <p class="text-muted small">Générez instantanément des flashcards à partir d'autres contenus pédagogiques existants !</p>
          
          <form id="autoGenForm">
            @csrf
            <div class="mb-3">
              <label for="sourceTypeSelect" class="form-label small fw-bold">Source d'apprentissage</label>
              <select class="form-select" id="sourceTypeSelect" name="source_type" required>
                <option value="" disabled selected>Choisir une source...</option>
                <option value="quiz">Depuis un Quiz</option>
                <option value="examen">Depuis un Examen</option>
                <option value="article">Depuis un Article de cours</option>
              </select>
            </div>

            <!-- Resource lists (hidden by default) -->
            <div class="mb-3 d-none" id="sourceQuizContainer">
              <label for="sourceQuizSelect" class="form-label small fw-bold">Quiz source</label>
              <select class="form-select select2-single" id="sourceQuizSelect" name="source_id_quiz" style="width:100%;">
                <option value="" disabled selected>Choisir le quiz...</option>
                @foreach($quizzes ?? [] as $q)
                  <option value="{{ $q->id }}">{{ $q->title }} ({{ $q->questions->count() }} Q)</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3 d-none" id="sourceExamContainer">
              <label for="sourceExamSelect" class="form-label small fw-bold">Examen source</label>
              <select class="form-select select2-single" id="sourceExamSelect" name="source_id_exam" style="width:100%;">
                <option value="" disabled selected>Choisir l'examen...</option>
                @foreach($exams ?? [] as $ex)
                  <option value="{{ $ex->id }}">{{ $ex->titre }} ({{ $ex->questions->count() }} Q)</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3 d-none" id="sourceArticleContainer">
              <label for="sourceArticleSelect" class="form-label small fw-bold">Article source</label>
              <select class="form-select select2-single" id="sourceArticleSelect" name="source_id_article" style="width:100%;">
                <option value="" disabled selected>Choisir l'article...</option>
                @foreach($articles ?? [] as $art)
                  <option value="{{ $art->id }}">{{ $art->title }}</option>
                @endforeach
              </select>
            </div>

            <button type="submit" class="btn btn-warning btn-sm w-100 text-dark" id="btnAutoGenSubmit" style="font-weight: 700; border-radius: 8px;" disabled>
              <i class="bi bi-cpu-fill me-1"></i> Générer les Cartes
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Card details -->
<div class="modal fade" id="modalCard" tabindex="-1" aria-labelledby="modalCardLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="cardForm">
        @csrf
        <input type="hidden" id="cardId" name="card_id">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCardLabel">Ajouter une Flashcard</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="cardRecto" class="form-label fw-bold">Devant (Recto) <span class="text-danger">*</span></label>
            <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
              <!-- Toolbar -->
              <div class="wysiwyg-toolbar d-flex align-items-center gap-1 p-2 bg-light border-bottom">
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" data-target="cardRecto" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" data-target="cardRecto" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" data-target="cardRecto" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
                <div class="vr mx-1"></div>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="list" data-target="cardRecto" title="Liste à puces" style="border: 1px solid var(--border);"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="link" data-target="cardRecto" title="Insérer un lien" style="border: 1px solid var(--border);"><i class="bi bi-link-45deg"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="image" data-target="cardRecto" title="Insérer une image" style="border: 1px solid var(--border);"><i class="bi bi-image"></i></button>
              </div>
              <!-- Textarea -->
              <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="cardRecto" name="recto" rows="3" required placeholder="Question, terme ou formule à mémoriser..." style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          <div class="mb-3">
            <label for="cardVerso" class="form-label fw-bold">Derrière (Verso) <span class="text-danger">*</span></label>
            <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
              <!-- Toolbar -->
              <div class="wysiwyg-toolbar d-flex align-items-center gap-1 p-2 bg-light border-bottom">
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" data-target="cardVerso" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" data-target="cardVerso" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" data-target="cardVerso" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
                <div class="vr mx-1"></div>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="list" data-target="cardVerso" title="Liste à puces" style="border: 1px solid var(--border);"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="link" data-target="cardVerso" title="Insérer un lien" style="border: 1px solid var(--border);"><i class="bi bi-link-45deg"></i></button>
                <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="image" data-target="cardVerso" title="Insérer une image" style="border: 1px solid var(--border);"><i class="bi bi-image"></i></button>
              </div>
              <!-- Textarea -->
              <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="cardVerso" name="verso" rows="3" required placeholder="Réponse, définition ou explication..." style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
            </div>
          </div>
          <div class="mb-3">
            <label for="cardTags" class="form-label fw-bold">Tags / Mots-clés</label>
            <input type="text" class="form-control" id="cardTags" name="tags" placeholder="Ex: verbe, difficile, math, formules (séparés par virgules)">
          </div>
          <div class="mb-3">
            <label for="cardNote" class="form-label fw-bold">Note / Indice additionnel</label>
            <input type="text" class="form-control" id="cardNote" name="note" placeholder="Indice optionnel visible lors de l'apprentissage">
          </div>
          <div class="mb-3">
            <label for="cardOrdre" class="form-label fw-bold">Ordre d'affichage</label>
            <input type="number" class="form-control" id="cardOrdre" name="ordre" value="0">
          </div>
          <hr>
          <h6 class="fw-bold mb-3"><i class="bi bi-eye-fill me-1 text-success"></i> Aperçu en direct</h6>
          <div class="row g-2">
            <div class="col-6">
              <div class="border rounded p-3 bg-light" style="min-height: 120px; font-size: 0.9rem; overflow-y: auto;">
                <span class="text-muted d-block small mb-1 fw-bold text-uppercase">Recto</span>
                <div id="livePreviewRecto" style="word-break: break-word;">-</div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded p-3 bg-light" style="min-height: 120px; font-size: 0.9rem; overflow-y: auto;">
                <span class="text-muted d-block small mb-1 fw-bold text-uppercase">Verso</span>
                <div id="livePreviewVerso" style="word-break: break-word; color: var(--green-dark); font-weight: 600;">-</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Single Card Preview -->
<div class="modal fade" id="modalPreviewCard" tabindex="-1" aria-labelledby="modalPreviewCardLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
      <div class="modal-header bg-light py-3 border-0">
        <h6 class="modal-title fw-bold" id="modalPreviewCardLabel" style="color: var(--green-dark);">
          <i class="bi bi-eye-fill me-1"></i> Aperçu de la Flashcard
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-light d-flex justify-content-center">
        <!-- 3D Card Scene -->
        <div class="flashcard-scene" style="width: 100%; height: 260px; max-width: 400px; cursor: pointer; perspective: 1000px;">
          <div class="flashcard-wrapper" id="singleCardWrapper" style="width: 100%; height: 100%; position: relative; transform-style: preserve-3d; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);">
            
            <!-- Front Face -->
            <div class="flashcard-face front" style="position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; background-color: #ffffff; border-top: 5px solid var(--primary); overflow-y: auto;">
              <span class="card-label" style="position: absolute; top: 15px; left: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Recto (Devant)</span>
              <div class="card-content" id="singleCardRecto" style="font-size: 1.15rem; font-weight: 600; color: var(--text); word-break: break-word;">...</div>
              <div class="card-note" id="singleCardNote" style="position: absolute; bottom: 15px; font-size: 0.75rem; font-style: italic; color: var(--text-muted); width: 80%;"></div>
            </div>
            
            <!-- Back Face -->
            <div class="flashcard-face back" style="position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; background-color: #fafdfb; border-top: 5px solid var(--warning); transform: rotateY(180deg); overflow-y: auto;">
              <span class="card-label" style="position: absolute; top: 15px; left: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Verso (Derrière)</span>
              <div class="card-content" id="singleCardVerso" style="font-size: 1.15rem; font-weight: 600; color: #124e3f; word-break: break-word;">...</div>
            </div>

          </div>
        </div>
      </div>
      <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-center">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFlipSingleCard" style="border-radius: 20px;">
          <i class="bi bi-arrow-repeat me-1"></i> Retourner la carte
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Print Deck -->
<div class="modal fade" id="modal-print-deck" tabindex="-1" aria-labelledby="modal-print-deck-label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
      <div class="modal-header bg-light py-3">
        <h5 class="modal-title fw-bold" id="modal-print-deck-label" style="color: #1e6f5c;">
          <i class="bi bi-printer-fill me-2"></i> Aperçu Avant Impression - Format A4
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-light" style="height: 70vh;">
        <iframe id="print-iframe-loader" src="" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
      <div class="modal-footer bg-white border-top p-3 d-flex justify-content-between">
        <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Utilisez les options d'impression du navigateur pour forcer l'orientation Portrait et activer les graphismes d'arrière-plan.</span>
        <div>
          <button type="button" class="btn btn-secondary px-4 py-2 me-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
          <button type="button" class="btn btn-primary px-4 py-2" id="btn-print-confirm" style="background-color: #1e6f5c; border: none; border-radius: 8px; font-weight: 600;">
            <i class="bi bi-printer me-1"></i> Imprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@stop

@push('js')
<script>
  $(function() {
    // Select2
    $('#deckGroupsAssign').select2({
      theme: 'bootstrap-5',
      placeholder: "Sélectionner les groupes",
      allowClear: true
    });

    $('.select2-single').select2({
      theme: 'bootstrap-5',
      allowClear: true
    });

    const deckId = $('#cardsList').data('deck-id');

    // WYSIWYG button commands
    $('#modalCard').on('click', '.wysiwyg-btn', function() {
        const cmd = $(this).data('cmd');
        const targetId = $(this).data('target');
        const $promptTextarea = $('#' + targetId);
        window.insertWysiwygTag($promptTextarea, cmd);
    });

    // Update live previews in modalCard
    function updateLivePreview() {
      var rectoVal = $('#cardRecto').val();
      var versoVal = $('#cardVerso').val();
      
      $('#livePreviewRecto').html(rectoVal ? rectoVal.replace(/\n/g, '<br>') : '-');
      $('#livePreviewVerso').html(versoVal ? versoVal.replace(/\n/g, '<br>') : '-');
    }

    $('#cardRecto, #cardVerso').on('input change keyup', updateLivePreview);
    
    // Also trigger on wysiwyg toolbar clicks
    $('#modalCard').on('click', '.wysiwyg-btn', function() {
      setTimeout(updateLivePreview, 50);
    });

    // Add Card Modal
    $('#btnAddCard').click(function() {
      $('#cardForm')[0].reset();
      $('#cardId').val('');
      $('#modalCardLabel').text('Ajouter une Flashcard');
      $('#modalCard').modal('show');
      updateLivePreview();
    });

    // Edit Card Modal
    $(document).on('click', '.edit-card', function() {
      const cardId = $(this).data('id');
      $.get(`/cores/editor/flashcards/${deckId}/cards/${cardId}`, function(res) {
        if (res.success) {
          $('#cardId').val(res.card.id);
          $('#cardRecto').val(res.card.recto);
          $('#cardVerso').val(res.card.verso);
          $('#cardTags').val(res.card.tags);
          $('#cardNote').val(res.card.note);
          $('#cardOrdre').val(res.card.ordre || 0);

          $('#modalCardLabel').text('Modifier la Flashcard');
          $('#modalCard').modal('show');
          setTimeout(updateLivePreview, 150);
        }
      });
    });

    // Single Card Preview
    $(document).on('click', '.preview-card', function() {
      var cardId = $(this).data('id');
      $.get(`/cores/editor/flashcards/${deckId}/cards/${cardId}`, function(res) {
        if (res.success) {
          $('#singleCardWrapper').removeClass('is-flipped');
          $('#singleCardRecto').html(res.card.recto);
          $('#singleCardVerso').html(res.card.verso);
          $('#singleCardNote').text(res.card.note ? '💡 ' + res.card.note : '');
          $('#modalPreviewCard').modal('show');
        }
      });
    });

    // Flip single card
    $('#btnFlipSingleCard, #modalPreviewCard .flashcard-scene').click(function(e) {
      e.stopPropagation();
      $('#singleCardWrapper').toggleClass('is-flipped');
    });

    // Print Deck
    $('#btnPrintDeck').click(function() {
      const printUrl = `/cores/editor/flashcards/${deckId}/print-iframe`;
      $('#print-iframe-loader').attr('src', printUrl);
      $('#modal-print-deck').modal('show');
    });

    // Print Confirmation
    $('#btn-print-confirm').click(function() {
      document.getElementById('print-iframe-loader').contentWindow.print();
    });


    // Save Card AJAX
    $('#cardForm').submit(function(e) {
      e.preventDefault();
      const cardId = $('#cardId').val();
      const url = cardId ? `/cores/editor/flashcards/${deckId}/cards/${cardId}` : `/cores/editor/flashcards/${deckId}/cards`;
      const type = cardId ? 'PUT' : 'POST';

      $.ajax({
        url: url,
        type: type,
        data: $(this).serialize(),
        success: function(res) {
          if (res.success) {
            $('#modalCard').modal('hide');
            Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'success',
              title: res.message,
              showConfirmButton: false,
              timer: 2000
            }).then(() => {
              window.location.reload();
            });
          }
        },
        error: function(xhr) {
          Swal.fire('Erreur', xhr.responseJSON?.message || 'Erreur lors de l\'enregistrement', 'error');
        }
      });
    });

    // Delete Card AJAX
    $(document).on('click', '.delete-card', function() {
      const cardId = $(this).data('id');
      Swal.fire({
        title: 'Supprimer cette carte ?',
        text: "Cette action est définitive.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: `/cores/editor/flashcards/${deckId}/cards/${cardId}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
              if (res.success) {
                Swal.fire('Supprimé', res.message, 'success').then(() => {
                  window.location.reload();
                });
              }
            }
          });
        }
      });
    });

    // Save Groups Target Assignments
    $('#btnSaveGroups').click(function() {
      const selectedGroups = $('#deckGroupsAssign').val() || [];
      
      // Clear existing groups and sync them
      $.ajax({
        url: `/cores/flashcard-decks/${deckId}`,
        type: 'PUT',
        data: {
          _token: '{{ csrf_token() }}',
          titre: '{{ $deck->titre }}',
          algorithme: '{{ $deck->algorithme }}',
          group_ids: selectedGroups
        },
        success: function(res) {
          if (res.success) {
            Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'success',
              title: 'Groupes affectés mis à jour.',
              showConfirmButton: false,
              timer: 2000
            });
          }
        }
      });
    });

    // Search cards inline filter
    $('#cardSearch').on('keyup', function() {
      const value = $(this).val().toLowerCase();
      $('.question-item').filter(function() {
        const text = $(this).find('.card-preview-text').text().toLowerCase();
        const num = $(this).find('.question-num').text().toLowerCase();
        $(this).toggle(text.indexOf(value) > -1 || num.indexOf(value) > -1);
      });
    });

    // Auto-generation dropdown listeners
    $('#sourceTypeSelect').change(function() {
      const val = $(this).val();
      $('#sourceQuizContainer, #sourceExamContainer, #sourceArticleContainer').addClass('d-none');
      $('#sourceQuizSelect, #sourceExamSelect, #sourceArticleSelect').val('').trigger('change');

      if (val === 'quiz') {
        $('#sourceQuizContainer').removeClass('d-none');
      } else if (val === 'examen') {
        $('#sourceExamContainer').removeClass('d-none');
      } else if (val === 'article') {
        $('#sourceArticleContainer').removeClass('d-none');
      }

      checkAutoGenButtonState();
    });

    function getSelectedSourceId() {
      const type = $('#sourceTypeSelect').val();
      if (type === 'quiz') return $('#sourceQuizSelect').val();
      if (type === 'examen') return $('#sourceExamSelect').val();
      if (type === 'article') return $('#sourceArticleSelect').val();
      return null;
    }

    function checkAutoGenButtonState() {
      const hasSourceType = !!$('#sourceTypeSelect').val();
      const hasSourceId = !!getSelectedSourceId();
      $('#btnAutoGenSubmit').prop('disabled', !(hasSourceType && hasSourceId));
    }

    $('#sourceQuizSelect, #sourceExamSelect, #sourceArticleSelect').change(function() {
      checkAutoGenButtonState();
    });

    // Submit Auto Generation Request
    $('#autoGenForm').submit(function(e) {
      e.preventDefault();
      const sourceType = $('#sourceTypeSelect').val();
      const sourceId = getSelectedSourceId();

      Swal.fire({
        title: 'Génération en cours...',
        text: 'Veuillez patienter pendant l\'extraction et la génération des flashcards.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      $.ajax({
        url: `/cores/editor/flashcards/${deckId}/generate`,
        type: 'POST',
        data: {
          _token: '{{ csrf_token() }}',
          source_type: sourceType,
          source_id: sourceId
        },
        success: function(res) {
          Swal.close();
          if (res.success) {
            Swal.fire('Génération réussie !', res.message, 'success').then(() => {
              window.location.reload();
            });
          } else {
            Swal.fire('Erreur', res.message, 'error');
          }
        },
        error: function(xhr) {
          Swal.close();
          Swal.fire('Erreur', xhr.responseJSON?.message || 'Une erreur est survenue.', 'error');
        }
      });
    });

  });
</script>
@endpush
