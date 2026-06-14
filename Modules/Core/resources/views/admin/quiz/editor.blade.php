@extends('core::layouts.admin-editor')

@section('title', 'Quiz Editor - ' . $quiz->title)

@section('page-title', 'Quiz Editor - ' . $quiz->title)

@push('css')
  <link rel="stylesheet" href="{{ asset('css/admin/quiz-editor.css') }}">
@endpush

@section('sidebar')
  <div class="sidebar-editor-header">
    <div class="sidebar-editor-title">
      <i class="bi bi-question-circle-fill text-success" style="color: var(--green-mid) !important;"></i>
      <span class="text-truncate" style="max-width: 180px;" id="sidebarQuizTitle">{{ $quiz->title }}</span>
    </div>
    <span class="badge {{ $quiz->is_active ? 'bg-success' : 'bg-secondary' }}" id="sidebarStatusBadge">
      {{ $quiz->is_active ? 'Actif' : 'Draft Mode' }}
    </span>
    
    <button class="sidebar-publish-btn" id="btnPublishQuiz">
      <i class="bi {{ $quiz->is_active ? 'bi-cloud-arrow-down-fill' : 'bi-cloud-arrow-up-fill' }}"></i>
      <span id="btnPublishText">{{ $quiz->is_active ? 'Désactiver le Quiz' : 'Publier le Quiz' }}</span>
    </button>
  </div>
  
  <li class="menu-item">
    <a href="{{ route('cores.quizzes.index') }}" class="menu-link">
      <i class="bi bi-eye-fill"></i>
      <span>Overview</span>
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
      <span>Settings</span>
    </a>
  </li> 
  <li class="menu-item">
    <a href="{{ route('admin.quizzes.preview', $quiz->id) }}" class="menu-link">
      <i class="bi bi-play-circle-fill"></i>
      <span>Preview</span>
    </a>
  </li>
  
  <div class="mt-auto border-top pt-2">
    <li class="menu-item">
      <a href="#" class="menu-link text-muted">
        <i class="bi bi-question-diamond"></i>
        <span>Help Center</span>
      </a>
    </li>
  </div>
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
      <a href="{{ route('cores.quizzes.index') }}" class="secondary-nav-link">Quizzes</a>
      <a href="{{ route('cores.quizzes.index') }}" class="btn btn-sm btn-primary py-2 px-3" style="border-radius: 8px; background-color: var(--green-dark); border: none;">
        <i class="bi bi-plus"></i> Nouveau Quiz
      </a>
    </div>
  </div>

  <!-- Breadcrumbs & Quiz Info Block -->
  <div class="quiz-info-card">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-2" style="font-size: 0.85rem;">
        <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}" class="text-decoration-none text-muted">Accueil</a></li>
        <li class="breadcrumb-item"><a href="{{ route('cores.quizzes.index') }}" class="text-decoration-none text-muted">Quiz</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $quiz->title }}</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="fw-bold h3 mb-2" id="quizTitleDisplay">{{ $quiz->title }}</h1>
        <p class="text-muted mb-0" id="quizDescriptionDisplay">{{ $quiz->description ?: 'Aucune description fournie.' }}</p>
      </div>
    </div>
  </div>

  <!-- Columns Grid -->
  <div class="row">
    
    <!-- Left Column: Questions List -->
    <div class="col-lg-8 mb-4">
      <div class="questions-card">
        <div class="questions-header">
          <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0" style="color: var(--green-dark);">Questions</h5>
            <span class="badge bg-primary" id="questionCounter">{{ $quiz->questions->count() }} total</span>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnReorderQuestions" style="border-radius: 8px;">
              <i class="bi bi-arrow-down-up me-1"></i> Réordonner
            </button>
            <button class="btn btn-sm btn-primary" id="btnAddQuestion" style="background-color: var(--green-dark); border: none; border-radius: 8px;">
              <i class="bi bi-plus-circle me-1"></i> Ajouter
            </button>
          </div>
        </div>

        <div class="questions-list-wrapper" id="questionsList" data-quiz-id="{{ $quiz->id }}">
          @forelse($quiz->questions as $index => $question)
            <div class="question-item" data-id="{{ $question->id }}">
              <div class="question-handle">
                <i class="bi bi-grid-3x2-gap-fill"></i>
              </div>
              <div class="question-num">{{ $index + 1 }}</div>
              <div class="question-main">
                <div class="question-text" title="{{ strip_tags($question->question_text) }}">
                  {!! strip_tags($question->question_text) !!}
                </div>
                <div class="question-meta">
                  <span class="question-type-badge">{{ str_replace('_', ' ', $question->type) }}</span>
                  <span class="question-points-badge">{{ $question->points }} pts</span>
                </div>
              </div>
              <div class="question-actions">
                <button class="question-btn edit" data-id="{{ $question->id }}" data-type="{{ $question->type }}" title="Modifier">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <button class="question-btn delete" data-id="{{ $question->id }}" title="Supprimer">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </div>
            </div>
          @empty
            <div class="text-center py-5 text-muted" id="noQuestionsPlaceholder">
              <i class="bi bi-question-circle display-4 mb-3 d-block text-muted"></i>
              <p class="mb-0">Aucune question dans ce quiz. Cliquez sur "Ajouter" pour commencer.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- Right Column: Settings & Group Assignment -->
    <div class="col-lg-4">
      
      <!-- Settings Panel -->
      <div class="params-card" id="settings">
        <h5><i class="bi bi-sliders me-2"></i>Paramètres</h5>
        <form id="quizParamsForm" data-quiz-id="{{ $quiz->id }}">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="paramIsActive" name="is_active" {{ $quiz->is_active ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="paramIsActive">Actif / Publié</label>
          </div>

          <div class="mb-3">
            <label for="paramTitle" class="form-label fw-semibold">Titre du Quiz</label>
            <input type="text" class="form-control" id="paramTitle" name="title" value="{{ $quiz->title }}" required style="border-radius: 8px;">
          </div>

          <div class="mb-3">
            <label for="paramDescription" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="paramDescription" name="description" rows="3" style="border-radius: 8px;">{{ $quiz->description }}</textarea>
          </div>

          <div class="mb-3">
            <label for="paramDuration" class="form-label fw-semibold">Durée (minutes)</label>
            <input type="number" class="form-control" id="paramDuration" name="duration" value="{{ $quiz->duration ?: 0 }}" min="0" style="border-radius: 8px;">
            <div class="form-text">0 pour une durée illimitée.</div>
          </div>

          <div class="mb-3">
            <label for="paramPassingScore" class="form-label fw-semibold">Score de réussite (%)</label>
            <input type="number" class="form-control" id="paramPassingScore" name="passing_score" value="{{ $quiz->passing_score ?: 70 }}" min="1" max="100" style="border-radius: 8px;">
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="paramShuffle" name="shuffle_questions" {{ $quiz->shuffle_questions ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="paramShuffle">Mélanger les questions</label>
          </div>

          <div class="text-muted text-center" style="font-size: 0.8rem;" id="autosaveStatus">
            <i class="bi bi-cloud-check me-1 text-success"></i> Enregistrement automatique activé
          </div>
        </form>
      </div>

      <!-- Group Assignment Panel -->
      <div class="assigned-groups-card">
        <h5><i class="bi bi-collection-fill me-2"></i>Groupes Assignés</h5>
        <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 1rem;">
          Assignez ce quiz à des groupes pour que les apprenants puissent y accéder.
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
        <div class="groups-list" id="assignedGroupsList" data-quiz-id="{{ $quiz->id }}">
          @forelse($quiz->groups as $group)
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
              <p class="mb-0" style="font-size: 0.8rem;">Aucun groupe assigné.</p>
            </div>
          @endforelse
        </div>
      </div>

    </div>

  </div>

</div>
@endsection

@section('modals')
  <!-- Selection Modal for adding question type -->
  <div class="modal fade" id="questionTypeModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="questionTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0" style="border-radius: 20px;">
        <div class="modal-header border-0 bg-light py-3">
          <h5 class="modal-title fw-bold" id="questionTypeModalLabel" style="color: var(--green-dark);">Choisissez un type de question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="question-type-grid">
            <div class="type-select-card" data-type="true_false">
              <i class="bi bi-check2-circle"></i>
              <h6>Vrai / Faux</h6>
              <p>Une question à choix binaire.</p>
            </div>
            <div class="type-select-card" data-type="mcq">
              <i class="bi bi-list-check"></i>
              <h6>QCM</h6>
              <p>Choix unique ou multiple.</p>
            </div>
            <div class="type-select-card" data-type="fill_blank">
              <i class="bi bi-file-earmark-text"></i>
              <h6>Texte à trous</h6>
              <p>Remplissez les espaces vides.</p>
            </div>
            <div class="type-select-card" data-type="matching">
              <i class="bi bi-arrow-left-right"></i>
              <h6>Appariement</h6>
              <p>Associez des éléments correspondants.</p>
            </div>
            <div class="type-select-card" data-type="ordering">
              <i class="bi bi-sort-down"></i>
              <h6>Ordonnancement</h6>
              <p>Mettez les propositions dans l'ordre.</p>
            </div>
            <div class="type-select-card" data-type="open_text">
              <i class="bi bi-chat-left-text"></i>
              <h6>Texte libre</h6>
              <p>Réponse rédigée par l'apprenant.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @include('core::admin.quiz.partials.modals.modal-true-false')
  @include('core::admin.quiz.partials.modals.modal-fill-blank')
  @include('core::admin.quiz.partials.modals.modal-matching')
  @include('core::admin.quiz.partials.modals.modal-ordering')
  @include('core::admin.quiz.partials.modals.modal-open-text')
  @include('core::admin.quiz.partials.modals.modal-mcq')
  @yield('question-modals')
@endsection

@push('scripts')
  <!-- SortableJS library locally installed -->
  <script src="{{ asset('plugins/sortablejs/Sortable.min.js') }}"></script>
  <!-- Main Quiz Editor JS -->
  <script src="{{ asset('js/admin/quiz-editor.js') }}"></script>
  <script src="{{ asset('js/admin/modals/modal-true-false.js') }}"></script>
  <script src="{{ asset('js/admin/modals/modal-fill-blank.js') }}"></script>
  <script src="{{ asset('js/admin/modals/modal-matching.js') }}"></script>
  <script src="{{ asset('js/admin/modals/modal-ordering.js') }}"></script>
  <script src="{{ asset('js/admin/modals/modal-open-text.js') }}"></script>
  <script src="{{ asset('js/admin/modals/modal-mcq.js') }}"></script>
@endpush
