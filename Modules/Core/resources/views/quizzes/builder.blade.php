@extends('core::layouts.master')

@section('title', 'Concepteur de Quiz - ' . $quiz->title)
@section('header', 'Concepteur de Quiz')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cores.quizzes.index') }}">Quiz</a></li>
    <li class="breadcrumb-item active" aria-current="page">Concepteur</li>
@endsection

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('cores.quizzes.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Retour à la liste
    </a>
    <span class="badge bg-secondary p-2">
        <i class="fas fa-shield-alt"></i> Mode Conception
    </span>
</div>

<div class="card card-outline card-primary mb-4 shadow-sm border-0">
    <div class="card-body bg-light rounded-top">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1 text-primary"><strong>{{ $quiz->title }}</strong></h4>
                <p class="text-muted mb-0">{{ $quiz->description ?? 'Aucune description fournie.' }}</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-purple p-2 me-1" style="font-size: 0.9rem;">
                    <i class="far fa-clock"></i> {{ $quiz->duration ? $quiz->duration . ' min' : 'Temps illimité' }}
                </span>
                <span class="badge bg-warning text-dark p-2" style="font-size: 0.9rem;">
                    <i class="fas fa-check-circle"></i> Réussite : {{ $quiz->passing_score }}%
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left column: Questions list -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center border-0">
                <h6 class="card-title mb-0"><i class="fas fa-list"></i> Questions (<span id="questions-count">{{ count($quiz->questions) }}</span>)</h6>
                <button id="btn-add-question" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush overflow-auto" style="max-height: 550px;" id="questions-list">
                    @forelse($quiz->questions as $index => $question)
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center question-item cursor-pointer py-3" 
                             data-id="{{ $question->id }}"
                             data-order="{{ $question->order }}"
                             data-type="{{ $question->type }}">
                            <div class="d-flex align-items-center flex-grow-1 min-width-0 me-2">
                                <span class="badge bg-secondary me-2 question-index">{{ $index + 1 }}</span>
                                <div class="text-truncate flex-grow-1">
                                    <strong class="question-text-preview">{{ $question->question_text }}</strong>
                                    <div class="text-muted small">
                                        {{ $question->type === 'single_choice' ? 'Choix unique' : '' }}
                                        {{ $question->type === 'multiple_choice' ? 'Choix multiples' : '' }}
                                        {{ $question->type === 'true_false' ? 'Vrai/Faux' : '' }}
                                        {{ $question->type === 'open_text' ? 'Texte libre' : '' }}
                                        {{ $question->type === 'matching' ? 'Association' : '' }}
                                        {{ $question->type === 'fill_in_the_blank' ? 'Phrase à trous' : '' }}
                                        &bull; {{ $question->points }} {{ $question->points > 1 ? 'pts' : 'pt' }}
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <button class="btn btn-xs btn-outline-secondary btn-move-up" title="Monter">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button class="btn btn-xs btn-outline-secondary btn-move-down" title="Descendre">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <button class="btn btn-xs btn-outline-danger btn-delete-question-action" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted id="no-questions-placeholder">
                            <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                            <p class="mb-0">Aucune question dans ce quiz.</p>
                            <small>Cliquez sur "Ajouter" pour commencer.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Right column: Question Editor -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100" id="editor-card">
            <div class="card-header bg-primary text-white border-0">
                <h6 class="card-title mb-0" id="editor-title"><i class="fas fa-edit"></i> Éditeur de Question</h6>
            </div>
            <div class="card-body">
                <!-- Select item state placeholder -->
                <div id="editor-placeholder" class="text-center py-5 text-muted">
                    <i class="fas fa-edit fa-3x mb-3 text-secondary"></i>
                    <h5>Aucune question sélectionnée</h5>
                    <p>Sélectionnez une question dans la liste de gauche ou cliquez sur "Ajouter" pour en concevoir une nouvelle.</p>
                </div>

                <!-- Actual Form -->
                <form id="question-form" class="d-none">
                    @csrf
                    <input type="hidden" id="question_id" name="question_id">
                    
                    <div class="form-group mb-3">
                        <label for="question_text" class="form-label">Intitulé de la question <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required placeholder="Saisissez la question ici..."></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type" class="form-label">Type de question <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="single_choice">Choix unique (QCM)</option>
                                    <option value="multiple_choice">Choix multiples (QCM)</option>
                                    <option value="true_false">Vrai / Faux</option>
                                    <option value="open_text">Réponse libre (Texte)</option>
                                    <option value="matching">Association d'éléments (Paires)</option>
                                    <option value="fill_in_the_blank">Texte à trous</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="points" class="form-label">Points <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="points" name="points" min="0" value="1" required>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic options area depending on selected type -->
                    <div class="card bg-light border-0 mb-3" id="options-card">
                        <div class="card-body">
                            <h6 class="mb-3 text-secondary border-bottom pb-2" id="options-title">Configuration des réponses</h6>
                            
                            <!-- Dynamic Content Inserted Here by JS -->
                            <div id="options-container"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary" id="btn-cancel-edit">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn-save-question">
                            <i class="fas fa-save"></i> Enregistrer la question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    .cursor-pointer { cursor: pointer; }
    .question-item.active {
        background-color: rgba(0, 123, 255, 0.08);
        border-left: 4px solid #007bff !important;
        font-weight: bold;
    }
    .question-item:hover:not(.active) {
        background-color: #f8f9fa;
    }
    .min-width-0 { min-width: 0; }
    .btn-xs {
        padding: 0.125rem 0.25rem;
        font-size: 0.75rem;
        line-height: 1.5;
        border-radius: 0.15rem;
    }
</style>
@endpush

@push('js')
<script>
    window.quizId = {{ $quiz->id }};
    window.questionsData = @json($quiz->questions);
</script>
<script type="module" src="{{ asset('js/modules/core/quizzes/builder.js') }}"></script>
@endpush
