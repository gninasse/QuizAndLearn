@extends('core::layouts.admin-editor')

@section('title', 'Exam Editor - ' . $exam->title)

@section('page-title', 'Exam Editor - ' . $exam->title)

@push('css')
  <link rel="stylesheet" href="{{ asset('css/admin/quiz-editor.css') }}">
  <style>
    .question-item {
      border-left: 4px solid var(--green-dark) !important;
    }
  </style>
@endpush

@section('sidebar')
  <div class="sidebar-editor-header">
    <div class="sidebar-editor-title">
      <i class="bi bi-file-earmark-check-fill text-success" style="color: var(--green-mid) !important;"></i>
      <span class="text-truncate" style="max-width: 180px;" id="sidebarExamTitle">{{ $exam->title }}</span>
    </div>
    <span class="badge {{ $exam->is_active ? 'bg-success' : 'bg-secondary' }}" id="sidebarStatusBadge">
      {{ $exam->is_active ? 'Actif' : 'Draft Mode' }}
    </span>
    
    <button class="sidebar-publish-btn" id="btnPublishExam">
      <i class="bi {{ $exam->is_active ? 'bi-cloud-arrow-down-fill' : 'bi-cloud-arrow-up-fill' }}"></i>
      <span id="btnPublishText">{{ $exam->is_active ? 'Désactiver l\'Examen' : 'Publier l\'Examen' }}</span>
    </button>
  </div>
  
  <li class="menu-item">
    <a href="{{ route('cores.exams.index') }}" class="menu-link">
      <i class="bi bi-arrow-left-circle-fill"></i>
      <span>Retour à la liste</span>
    </a>
  </li>
  <li class="menu-item active">
    <a href="#" class="menu-link">
      <i class="bi bi-patch-question-fill"></i>
      <span>Questions</span>
    </a>
  </li>
  <li class="menu-item">
    <a href="#settings" class="menu-link">
      <i class="bi bi-sliders"></i>
      <span>Paramètres</span>
    </a>
  </li> 
  <li class="menu-item">
    <a href="{{ route('cores.editor.exams.preview', $exam->id) }}" class="menu-link">
      <i class="bi bi-play-circle-fill"></i>
      <span>Prévisualiser</span>
    </a>
  </li>
  <li class="menu-item">
    <a href="{{ route('cores.editor.exams.supervision', $exam->id) }}" class="menu-link text-danger">
      <i class="bi bi-desktop"></i>
      <span>Supervision Live</span>
    </a>
  </li>
@endsection

@section('editor-content')
<div class="container-fluid p-0">
  
  <!-- Secondary Topbar -->
  <div class="secondary-topbar">
    <div class="secondary-search">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Rechercher des ressources..." id="resourceSearch">
    </div>
    <div class="secondary-nav">
      <a href="{{ route('cores.dashboard') }}" class="secondary-nav-link">Dashboard</a>
      <a href="{{ route('cores.exams.index') }}" class="secondary-nav-link">Examens</a>
      <a href="{{ route('cores.editor.exams.supervision', $exam->id) }}" class="btn btn-sm btn-danger py-2 px-3" style="border-radius: 8px; border: none;">
        <i class="bi bi-display"></i> Supervision en direct
      </a>
    </div>
  </div>

  <!-- Breadcrumbs & Exam Info Block -->
  <div class="quiz-info-card">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-2" style="font-size: 0.85rem;">
        <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}" class="text-decoration-none text-muted">Accueil</a></li>
        <li class="breadcrumb-item"><a href="{{ route('cores.exams.index') }}" class="text-decoration-none text-muted">Examens</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $exam->title }}</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="fw-bold h3 mb-2" id="examTitleDisplay">{{ $exam->title }}</h1>
        <p class="text-muted mb-0" id="examDescriptionDisplay">{{ $exam->description ?: 'Aucune description fournie.' }}</p>
      </div>
    </div>
  </div>

  <!-- Columns Grid -->
  <div class="row">
    
    <!-- Left Column: Questions List -->
    <div class="col-lg-8 mb-4">
      <div class="questions-card" style="background: white; border: 1px solid var(--border); border-radius: var(--radius-card); padding: 1.5rem; box-shadow: var(--shadow);">
        <div class="questions-header d-flex justify-content-between align-items-center mb-3">
          <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0" style="color: var(--green-dark);">Questions</h5>
            <span class="badge bg-primary" id="questionCounter">{{ $exam->questions->count() }} total</span>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark" id="btnPrintExam" style="border-radius: 8px;">
              <i class="bi bi-print me-1"></i> Imprimer (A4)
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnReorderQuestions" style="border-radius: 8px;">
              <i class="bi bi-arrow-down-up me-1"></i> Réordonner
            </button>
            <button class="btn btn-sm btn-primary" id="btnAddQuestion" style="border-radius: 8px;">
              <i class="bi bi-plus-circle me-1"></i> Ajouter
            </button>
          </div>
        </div>

        <div class="questions-list-wrapper" id="questionsList" data-exam-id="{{ $exam->id }}">
          @forelse($exam->questions as $index => $question)
            <div class="question-item d-flex align-items-center p-3 mb-3 bg-light" data-id="{{ $question->id }}" style="border-radius: 12px; transition: all 0.2s;">
              <div class="question-handle me-3 cursor-grab" style="color: var(--text-muted);">
                <i class="bi bi-grid-3x2-gap-fill"></i>
              </div>
              <div class="question-num me-3 fw-bold" style="color: var(--green-dark);">#{{ $index + 1 }}</div>
              <div class="question-main flex-grow-1">
                <div class="question-text fw-semibold text-dark" style="font-size: 0.95rem;">
                  {!! strip_tags($question->question_text) !!}
                </div>
                <div class="question-meta mt-1" style="font-size: 0.8rem;">
                  <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $question->type) }}</span>
                  <span class="badge bg-success">+{{ $question->points }} pts</span>
                  @if($question->points_negatifs > 0)
                    <span class="badge bg-danger">-{{ $question->points_negatifs }} pts</span>
                  @endif
                </div>
              </div>
              <div class="question-actions d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary edit-question" data-id="{{ $question->id }}" style="border-radius: 6px;">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger delete-question" data-id="{{ $question->id }}" style="border-radius: 6px;">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </div>
            </div>
          @empty
            <div class="text-center py-5 text-muted" id="noQuestionsPlaceholder">
              <i class="bi bi-question-circle display-4 mb-3 d-block text-muted"></i>
              <p class="mb-0">Aucune question dans cet examen. Cliquez sur "Ajouter" pour commencer.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- Right Column: Settings & Parameters -->
    <div class="col-lg-4">
      
      <!-- Settings Panel -->
      <div class="params-card bg-white p-3 border mb-4" id="settings" style="border-radius: 12px; box-shadow: var(--shadow);">
        <h5 class="fw-bold mb-3" style="color: var(--green-dark);"><i class="bi bi-sliders me-2"></i>Paramètres</h5>
        <form id="examParamsForm" data-exam-id="{{ $exam->id }}">
          @csrf
          <div class="mb-3">
            <label for="paramTitle" class="form-label fw-semibold">Titre de l'Examen</label>
            <input type="text" class="form-control" id="paramTitle" name="title" value="{{ $exam->title }}" required style="border-radius: 8px;">
          </div>

          <div class="mb-3">
            <label for="paramDescription" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="paramDescription" name="description" rows="3" style="border-radius: 8px;">{{ $exam->description }}</textarea>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label for="paramDuration" class="form-label fw-semibold">Durée (min)</label>
              <input type="number" class="form-control" id="paramDuration" name="duration" value="{{ $exam->duration }}" min="1" style="border-radius: 8px;">
            </div>
            <div class="col-6 mb-3">
              <label for="paramPassingScore" class="form-label fw-semibold">Réussite (%)</label>
              <input type="number" class="form-control" id="paramPassingScore" name="passing_score" value="{{ $exam->passing_score }}" min="0" max="100" style="border-radius: 8px;">
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label for="paramNoteMax" class="form-label fw-semibold">Note sur</label>
              <input type="number" class="form-control" id="paramNoteMax" name="note_max" value="{{ $exam->note_max }}" min="1" style="border-radius: 8px;">
            </div>
            <div class="col-6 mb-3">
              <label for="paramAttempts" class="form-label fw-semibold">Tentatives max</label>
              <input type="number" class="form-control" id="paramAttempts" name="max_attempts" value="{{ $exam->max_attempts }}" min="1" style="border-radius: 8px;">
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label for="paramFrom" class="form-label fw-semibold">Date d'ouverture</label>
              <input type="datetime-local" class="form-control" id="paramFrom" name="available_from" value="{{ $exam->available_from ? $exam->available_from->format('Y-m-d\TH:i') : '' }}" style="border-radius: 8px;">
            </div>
            <div class="col-6 mb-3">
              <label for="paramUntil" class="form-label fw-semibold">Date de fermeture</label>
              <input type="datetime-local" class="form-control" id="paramUntil" name="available_until" value="{{ $exam->available_until ? $exam->available_until->format('Y-m-d\TH:i') : '' }}" style="border-radius: 8px;">
            </div>
          </div>

          <hr>
          <h6 class="fw-bold"><i class="bi bi-shield-lock-fill text-danger me-1"></i>Sécurité</h6>
          
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="paramPleinEcran" name="plein_ecran_force" {{ $exam->plein_ecran_force ? 'checked' : '' }}>
            <label class="form-check-label" for="paramPleinEcran">Plein écran obligatoire</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="paramAntiCapture" name="anti_capture_strict" {{ $exam->anti_capture_strict ? 'checked' : '' }}>
            <label class="form-check-label" for="paramAntiCapture">Anti-capture strict</label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="paramNavigation" name="navigation_interdite" {{ $exam->navigation_interdite ? 'checked' : '' }}>
            <label class="form-check-label" for="paramNavigation">Navigation interdite (Alt-Tab)</label>
          </div>

          <hr>
          <h6 class="fw-bold"><i class="bi bi-trophy-fill text-warning me-1"></i>Résultats</h6>
          
          <div class="mb-3">
            <label for="paramPublication" class="form-label fw-semibold">Publication des notes</label>
            <select class="form-select" id="paramPublication" name="publication_resultats" style="border-radius: 8px;">
              <option value="immediate" {{ $exam->publication_resultats == 'immediate' ? 'selected' : '' }}>Immédiate</option>
              <option value="apres_fermeture" {{ $exam->publication_resultats == 'apres_fermeture' ? 'selected' : '' }}>Après fermeture</option>
              <option value="manuelle" {{ $exam->publication_resultats == 'manuelle' ? 'selected' : '' }}>Manuelle</option>
            </select>
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="paramClassement" name="classement_visible" {{ $exam->classement_visible ? 'checked' : '' }}>
            <label class="form-check-label" for="paramClassement">Classement visible</label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="paramAnonyme" name="classement_anonyme" {{ $exam->classement_anonyme ? 'checked' : '' }}>
            <label class="form-check-label" for="paramAnonyme">Classement anonyme</label>
          </div>

          <div class="text-muted text-center mt-3" style="font-size: 0.8rem;" id="autosaveStatus">
            <i class="bi bi-cloud-check me-1 text-success"></i> Enregistrement automatique activé
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('modals')
  <!-- Unified Question Editor Modal -->
  <div class="modal fade" id="questionEditorModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="questionEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0" style="border-radius: 20px;">
        <div class="modal-header border-0 bg-light py-3">
          <h5 class="modal-title fw-bold" id="questionEditorModalLabel" style="color: var(--green-dark);">
            <i class="bi bi-pencil-square me-2"></i><span id="modalQuestionTitleText">Ajouter une Question</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <form id="formQuestion" data-question-id="">
            @csrf
            <!-- Select Type, Points, Penalty -->
            <div class="row mb-3">
              <div class="col-md-4">
                <label for="qTypeSelect" class="form-label fw-semibold">Type de Question</label>
                <select class="form-select" id="qTypeSelect" name="type" style="border-radius: 8px;">
                  <option value="true_false">Vrai / Faux</option>
                  <option value="mcq">QCM (Choix unique/multiple)</option>
                  <option value="fill_blank">Texte à trous</option>
                  <option value="matching">Appariement</option>
                  <option value="ordering">Ordonnancement</option>
                  <option value="open_text">Texte libre</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="qPointsInput" class="form-label fw-semibold">Points</label>
                <input type="number" class="form-control" id="qPointsInput" name="points" value="1" min="1" required style="border-radius: 8px;">
              </div>
              <div class="col-md-4">
                <label for="qPointsNegatifsInput" class="form-label fw-semibold">Points Négatifs (pénalité)</label>
                <input type="number" class="form-control" id="qPointsNegatifsInput" name="points_negatifs" value="0" min="0" step="0.25" style="border-radius: 8px;">
              </div>
            </div>

            <!-- Question Text -->
            <div class="mb-3">
              <label for="qQuestionText" class="form-label fw-semibold">Énoncé de la question</label>
              <div class="wysiwyg-editor-container border" style="border-radius: 8px; overflow: hidden;">
                <!-- Toolbar -->
                <div class="wysiwyg-toolbar d-flex align-items-center gap-1 p-2 bg-light border-bottom">
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="bold" title="Gras" style="border: 1px solid var(--border);"><i class="bi bi-type-bold"></i></button>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="italic" title="Italique" style="border: 1px solid var(--border);"><i class="bi bi-type-italic"></i></button>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="underline" title="Souligné" style="border: 1px solid var(--border);"><i class="bi bi-type-underline"></i></button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="list" title="Liste à puces" style="border: 1px solid var(--border);"><i class="bi bi-list-ul"></i></button>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="link" title="Insérer un lien" style="border: 1px solid var(--border);"><i class="bi bi-link-45deg"></i></button>
                  <button type="button" class="btn btn-sm btn-light wysiwyg-btn" data-cmd="image" title="Insérer une image" style="border: 1px solid var(--border);"><i class="bi bi-image"></i></button>
                </div>
                <!-- Textarea -->
                <textarea class="form-control border-0 p-3 wysiwyg-textarea" id="qQuestionText" name="question_text" rows="3" placeholder="Saisissez la question..." required style="border-radius: 0; outline: none; box-shadow: none;"></textarea>
              </div>
            </div>

            <!-- Dynamic Question Configuration Views -->
            <div class="card p-3 bg-light border-0 mb-3" style="border-radius: 12px;">
              
              <!-- 1. True False Panel -->
              <div class="panel-type" id="panel-true_false" style="display:none;">
                <label class="form-label fw-semibold d-block">Bonne Réponse</label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="tf_answer" id="tf_true" value="true" checked>
                  <label class="btn btn-outline-success" for="tf_true"><i class="bi bi-check-circle me-1"></i>VRAI</label>

                  <input type="radio" class="btn-check" name="tf_answer" id="tf_false" value="false">
                  <label class="btn btn-outline-danger" for="tf_false"><i class="bi bi-x-circle me-1"></i>FAUX</label>
                </div>
              </div>

              <!-- 2. MCQ Panel -->
              <div class="panel-type" id="panel-mcq" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-semibold mb-0">Options de Réponses</label>
                  <button type="button" class="btn btn-xs btn-outline-primary" id="btnMcqAddOption"><i class="bi bi-plus"></i> Option</button>
                </div>
                <div id="mcqOptionsContainer">
                  <!-- JS will append option rows here -->
                </div>
              </div>

              <!-- 3. Fill Blank Panel -->
              <div class="panel-type" id="panel-fill_blank" style="display:none;">
                <label class="form-label fw-semibold">Format du texte à trous</label>
                <p class="text-muted small">Utilisez des crochets doubles pour indiquer un blanc avec les réponses possibles séparées par un trait vertical. <br>Exemple: <code>La capitale de la France est [[Paris|Lille]].</code></p>
                <textarea class="form-control" id="fillBlankFormat" rows="2" placeholder="Écrivez le texte avec les blancs..."></textarea>
              </div>

              <!-- 4. Matching Panel -->
              <div class="panel-type" id="panel-matching" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-semibold mb-0">Paires d'Appariement</label>
                  <button type="button" class="btn btn-xs btn-outline-primary" id="btnMatchingAddPair"><i class="bi bi-plus"></i> Paire</button>
                </div>
                <div id="matchingPairsContainer">
                  <!-- Pairs will go here -->
                </div>
              </div>

              <!-- 5. Ordering Panel -->
              <div class="panel-type" id="panel-ordering" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-semibold mb-0">Éléments à Ordonner (dans le bon ordre)</label>
                  <button type="button" class="btn btn-xs btn-outline-primary" id="btnOrderingAdd"><i class="bi bi-plus"></i> Élément</button>
                </div>
                <div id="orderingItemsContainer">
                  <!-- Items will go here -->
                </div>
              </div>

              <!-- 6. Open Text Panel -->
              <div class="panel-type" id="panel-open_text" style="display:none;">
                <label class="form-label fw-semibold">Note / Consigne de correction libre</label>
                <textarea class="form-control" id="openTextHint" rows="2" placeholder="Ex: L'apprenant doit définir brièvement les termes..."></textarea>
              </div>

            </div>
          </form>
        </div>
        <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
          <button type="button" class="btn btn-primary px-4 py-2" id="btnSaveQuestion" style="border-radius: 8px;">Enregistrer</button>
        </div>
      </div>
    </div>
  </div>
@endsection

<!-- Modal Print Exam -->
<div class="modal fade" id="modal-print-exam" tabindex="-1" aria-labelledby="modal-print-exam-label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
      <div class="modal-header bg-light py-3">
        <h5 class="modal-title fw-bold" id="modal-print-exam-label" style="color: #1a365d;">
          <i class="bi bi-printer-fill me-2"></i> Aperçu Avant Impression - Format A4
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-light" style="height: 70vh;">
        <iframe id="print-iframe-loader" src="" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
      <div class="modal-footer bg-white border-top p-3 d-flex justify-content-between">
        <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> La grille de correction est automatiquement générée et insérée sur une feuille séparée à la fin du document.</span>
        <div>
          <button type="button" class="btn btn-secondary px-4 py-2 me-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
          <button type="button" class="btn btn-primary px-4 py-2" id="btn-print-confirm" style="background-color: #1a365d; border: none; border-radius: 8px; font-weight: 600; color: white;">
            <i class="bi bi-printer me-1"></i> Imprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
  <!-- SortableJS library -->
  <script src="{{ asset('plugins/sortablejs/Sortable.min.js') }}"></script>
  
  <script>
    $(document).ready(function () {
        var examId = $('#questionsList').data('exam-id');

        // Setup CSRF
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // -------------------------------------------------------------
        // Sortable questions list
        // -------------------------------------------------------------
        if ($('#questionsList').length) {
            new Sortable($('#questionsList')[0], {
                handle: '.question-handle',
                animation: 150,
                onEnd: function() {
                    var questionIds = [];
                    $('#questionsList .question-item').each(function() {
                        questionIds.push($(this).data('id'));
                    });
                    
                    // Re-index UI numbers
                    $('#questionsList .question-item').each(function(idx) {
                        $(this).find('.question-num').text('#' + (idx + 1));
                    });

                    // Send to server
                    $.post(`/cores/editor/exams/${examId}/reorder`, { question_ids: questionIds }, function(res) {
                        if (res.success) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: res.message,
                                showConfirmButton: false,
                                timer: 1500
                            });
                        }
                    });
                }
            });
        }

        // -------------------------------------------------------------
        // Settings Autosave
        // -------------------------------------------------------------
        var autosaveTimeout = null;
        $('#examParamsForm input, #examParamsForm textarea, #examParamsForm select').on('input change', function () {
            clearTimeout(autosaveTimeout);
            $('#autosaveStatus').html('<i class="bi bi-arrow-repeat spin text-warning"></i> Enregistrement en cours...');

            autosaveTimeout = setTimeout(function () {
                var formData = {
                    title: $('#paramTitle').val(),
                    description: $('#paramDescription').val(),
                    duration: $('#paramDuration').val(),
                    passing_score: $('#paramPassingScore').val(),
                    note_max: $('#paramNoteMax').val(),
                    max_attempts: $('#paramAttempts').val(),
                    available_from: $('#paramFrom').val() || null,
                    available_until: $('#paramUntil').val() || null,
                    plein_ecran_force: $('#paramPleinEcran').is(':checked') ? 1 : 0,
                    anti_capture_strict: $('#paramAntiCapture').is(':checked') ? 1 : 0,
                    navigation_interdite: $('#paramNavigation').is(':checked') ? 1 : 0,
                    publication_resultats: $('#paramPublication').val(),
                    classement_visible: $('#paramClassement').is(':checked') ? 1 : 0,
                    classement_anonyme: $('#paramAnonyme').is(':checked') ? 1 : 0
                };

                $.ajax({
                    url: `/cores/exams/${examId}`,
                    type: 'PUT',
                    data: formData,
                    success: function (res) {
                        if (res.success) {
                            $('#autosaveStatus').html('<i class="bi bi-cloud-check text-success"></i> Modifications enregistrées');
                            $('#sidebarExamTitle').text(res.data.title);
                            $('#examTitleDisplay').text(res.data.title);
                            $('#examDescriptionDisplay').text(res.data.description || 'Aucune description.');
                        }
                    },
                    error: function () {
                        $('#autosaveStatus').html('<i class="bi bi-exclamation-triangle text-danger"></i> Échec de sauvegarde');
                    }
                });
            }, 800);
        });

        // Publish Toggle
        $('#btnPublishExam, #paramIsActive').click(function(e) {
            if(e.target === document.getElementById('paramIsActive')) return; // Avoid double triggering
            
            $.post(`/cores/exams/${examId}/toggle-status`, function (res) {
                if (res.success) {
                    const active = res.is_active;
                    $('#paramIsActive').prop('checked', active);
                    $('#sidebarStatusBadge').text(active ? 'Actif' : 'Draft Mode')
                        .removeClass('bg-success bg-secondary')
                        .addClass(active ? 'bg-success' : 'bg-secondary');
                    
                    $('#btnPublishText').text(active ? 'Désactiver l\'Examen' : 'Publier l\'Examen');
                    $('#btnPublishExam i').removeClass('bi-cloud-arrow-down-fill bi-cloud-arrow-up-fill')
                        .addClass(active ? 'bi-cloud-arrow-down-fill' : 'bi-cloud-arrow-up-fill');
                        
                    Swal.fire('Succès', res.message, 'success');
                }
            });
        });

        // -------------------------------------------------------------
        // Unified Question Creator Modal
        // -------------------------------------------------------------
        const modal = $('#questionEditorModal');

        $('#btnAddQuestion').click(function () {
            $('#formQuestion')[0].reset();
            $('#formQuestion').attr('data-question-id', '');
            $('#modalQuestionTitleText').text('Ajouter une Question');
            $('#mcqOptionsContainer').empty();
            $('#matchingPairsContainer').empty();
            $('#orderingItemsContainer').empty();
            $('#qTypeSelect').val('true_false').trigger('change');
            
            // Add default MCQ option builders
            addMcqOptionRow('', true);
            addMcqOptionRow('', false);
            
            modal.modal('show');
        });

        // Type switcher
        $('#qTypeSelect').change(function () {
            const type = $(this).val();
            $('.panel-type').hide();
            $('#panel-' + type).show();
        });

        // WYSIWYG Editor Actions
        modal.on('click', '.wysiwyg-btn', function() {
            const cmd = $(this).data('cmd');
            const $promptTextarea = $('#qQuestionText');
            window.insertWysiwygTag($promptTextarea, cmd);
        });

        // MCQ Option Rows
        function addMcqOptionRow(text = '', isCorrect = false) {
            const index = $('#mcqOptionsContainer .option-row').length;
            const html = `
                <div class="option-row d-flex align-items-center mb-2 gap-2">
                    <input type="checkbox" class="form-check-input correct-chk" ${isCorrect ? 'checked' : ''} style="transform:scale(1.2);">
                    <input type="text" class="form-control option-text" placeholder="Réponse alternative..." value="${text}" required style="border-radius:6px;">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-option"><i class="bi bi-trash"></i></button>
                </div>
            `;
            $('#mcqOptionsContainer').append(html);
        }

        $('#btnMcqAddOption').click(function() {
            addMcqOptionRow('', false);
        });

        $(document).on('click', '.btn-remove-option', function() {
            if ($('#mcqOptionsContainer .option-row').length > 1) {
                $(this).closest('.option-row').remove();
            }
        });

        // Matching Pairs
        function addMatchingPairRow(left = '', right = '') {
            const html = `
                <div class="pair-row d-flex align-items-center mb-2 gap-2">
                    <input type="text" class="form-control pair-left" placeholder="Élément gauche..." value="${left}" required style="border-radius:6px;">
                    <i class="bi bi-arrow-right"></i>
                    <input type="text" class="form-control pair-right" placeholder="Élément droit..." value="${right}" required style="border-radius:6px;">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-pair"><i class="bi bi-trash"></i></button>
                </div>
            `;
            $('#matchingPairsContainer').append(html);
        }

        $('#btnMatchingAddPair').click(function() {
            addMatchingPairRow('', '');
        });

        $(document).on('click', '.btn-remove-pair', function() {
            $(this).closest('.pair-row').remove();
        });

        // Ordering Items
        function addOrderingRow(text = '') {
            const html = `
                <div class="ordering-row d-flex align-items-center mb-2 gap-2">
                    <span class="order-idx fw-bold"></span>
                    <input type="text" class="form-control order-text" placeholder="Élément..." value="${text}" required style="border-radius:6px;">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-order-row"><i class="bi bi-trash"></i></button>
                </div>
            `;
            $('#orderingItemsContainer').append(html);
            reindexOrdering();
        }

        function reindexOrdering() {
            $('#orderingItemsContainer .ordering-row').each(function(idx) {
                $(this).find('.order-idx').text((idx + 1) + '.');
            });
        }

        $('#btnOrderingAdd').click(function() {
            addOrderingRow('');
        });

        $(document).on('click', '.btn-remove-order-row', function() {
            $(this).closest('.ordering-row').remove();
            reindexOrdering();
        });

        // Edit Question
        $(document).on('click', '.edit-question', function () {
            const id = $(this).data('id');
            $.get(`/cores/editor/exams/${examId}/questions/${id}`, function (res) {
                if (res.success) {
                    const q = res.data;
                    $('#formQuestion').attr('data-question-id', q.id);
                    $('#modalQuestionTitleText').text('Modifier la Question');
                    $('#qTypeSelect').val(q.type).trigger('change');
                    $('#qPointsInput').val(q.points);
                    $('#qPointsNegatifsInput').val(q.points_negatifs);
                    $('#qQuestionText').val(q.question_text);

                    // Reset sub-panels
                    $('#mcqOptionsContainer').empty();
                    $('#matchingPairsContainer').empty();
                    $('#orderingItemsContainer').empty();

                    // Parse choices/options
                    if (q.type === 'true_false') {
                        const answer = q.options && q.options.correct_answer === 'true';
                        if (answer) $('#tf_true').prop('checked', true);
                        else $('#tf_false').prop('checked', true);
                    } else if (q.type === 'mcq') {
                        if (q.options && q.options.choices) {
                            q.options.choices.forEach(c => {
                                addMcqOptionRow(c.text, c.is_correct);
                            });
                        }
                    } else if (q.type === 'fill_blank') {
                        $('#fillBlankFormat').val(q.options ? q.options.format : '');
                    } else if (q.type === 'matching') {
                        if (q.options && q.options.pairs) {
                            q.options.pairs.forEach(p => {
                                addMatchingPairRow(p.left, p.right);
                            });
                        }
                    } else if (q.type === 'ordering') {
                        if (q.options && q.options.items) {
                            q.options.items.forEach(it => {
                                addOrderingRow(it);
                            });
                        }
                    } else if (q.type === 'open_text') {
                        $('#openTextHint').val(q.options ? q.options.hint : '');
                    }

                    modal.modal('show');
                }
            });
        });

        // Save Question
        $('#btnSaveQuestion').click(function () {
            const id = $('#formQuestion').attr('data-question-id');
            const type = $('#qTypeSelect').val();
            const points = $('#qPointsInput').val();
            const points_negatifs = $('#qPointsNegatifsInput').val();
            const question_text = $('#qQuestionText').val();

            if (!question_text.trim()) {
                Swal.fire('Erreur', 'L\'énoncé est obligatoire.', 'error');
                return;
            }

            // Gather type specific options
            let options = {};
            if (type === 'true_false') {
                options = {
                    correct_answer: $('input[name="tf_answer"]:checked').val()
                };
            } else if (type === 'mcq') {
                const choices = [];
                $('#mcqOptionsContainer .option-row').each(function () {
                    choices.push({
                        text: $(this).find('.option-text').val(),
                        is_correct: $(this).find('.correct-chk').is(':checked')
                    });
                });
                options = { choices: choices };
            } else if (type === 'fill_blank') {
                options = {
                    format: $('#fillBlankFormat').val()
                };
            } else if (type === 'matching') {
                const pairs = [];
                $('#matchingPairsContainer .pair-row').each(function () {
                    pairs.push({
                        left: $(this).find('.pair-left').val(),
                        right: $(this).find('.pair-right').val()
                    });
                });
                options = { pairs: pairs };
            } else if (type === 'ordering') {
                const items = [];
                $('#orderingItemsContainer .order-text').each(function () {
                    items.push($(this).val());
                });
                options = { items: items };
            } else if (type === 'open_text') {
                options = {
                    hint: $('#openTextHint').val()
                };
            }

            const data = {
                type: type,
                points: points,
                points_negatifs: points_negatifs,
                question_text: question_text,
                options: options
            };

            const url = id 
                ? `/cores/editor/exams/${examId}/questions/${id}` 
                : `/cores/editor/exams/${examId}/questions`;
            const method = id ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                data: data,
                success: function (res) {
                    if (res.success) {
                        modal.modal('hide');
                        Swal.fire('Enregistré', res.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }
                },
                error: function (xhr) {
                    Swal.fire('Erreur', xhr.responseJSON.message || 'Erreur lors de la sauvegarde.', 'error');
                }
            });
        });

        // Delete Question
        $(document).on('click', '.delete-question', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Supprimer la question ?',
                text: "Cette action est irréversible.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer !'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/cores/editor/exams/${examId}/questions/${id}`,
                        type: 'DELETE',
                        success: function (res) {
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

        // Print
        $('#btnPrintExam').click(function () {
            const printUrl = `/cores/editor/exams/${examId}/print-iframe`;
            $('#print-iframe-loader').attr('src', printUrl);
            $('#modal-print-exam').modal('show');
        });

        // Print Confirmation
        $('#btn-print-confirm').click(function () {
            document.getElementById('print-iframe-loader').contentWindow.print();
        });
    });
  </script>
@endpush
