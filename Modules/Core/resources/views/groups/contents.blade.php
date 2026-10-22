@extends('core::layouts.master')

@section('title', 'Contenus — '.$group->name)
@section('header', 'Contenus '.term('of_group'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.groups.index') }}">{{ term('Groups') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $group->name }}</li>
@endsection

@section('content')
<div id="group-contents-app"
     data-group-id="{{ $group->id }}"
     data-sharing-mode="{{ $sharingMode }}"
     data-url-data="{{ route('cores.groups.contents.data', $group->id) }}"
     data-url-data-any="{{ route('cores.groups.contents.data', ['id' => '__GROUP__']) }}"
     data-url-candidates="{{ route('cores.groups.contents.candidates', $group->id) }}"
     data-url-folders="{{ route('cores.groups.contents.folders.store', $group->id) }}"
     data-url-folder="{{ route('cores.groups.contents.folders.update', ['id' => $group->id, 'folder' => '__FOLDER__']) }}"
     data-url-folder-move="{{ route('cores.groups.contents.folders.move', ['id' => $group->id, 'folder' => '__FOLDER__']) }}"
     data-url-place="{{ route('cores.groups.contents.items.place', $group->id) }}"
     data-url-reorder="{{ route('cores.groups.contents.items.reorder', $group->id) }}"
     data-url-attach="{{ route('cores.groups.contents.items.attach', $group->id) }}"
     data-url-detach="{{ route('cores.groups.contents.items.detach', ['id' => $group->id, 'type' => '__TYPE__', 'itemId' => '__ID__']) }}"
     data-url-copy="{{ route('cores.groups.contents.items.copy', ['id' => '__GROUP__', 'type' => '__TYPE__', 'itemId' => '__ID__']) }}"
     data-url-resources="{{ route('cores.groups.contents.resources.store', $group->id) }}"
     data-url-links="{{ route('cores.groups.contents.links.store', $group->id) }}"
     data-url-retry="{{ route('cores.groups.contents.resources.retry-conversion', ['id' => $group->id, 'resource' => '__ID__']) }}"
     data-url-resource="{{ route('cores.groups.contents.resources.update', ['id' => $group->id, 'resource' => '__ID__']) }}"
     data-url-edit-article="{{ route('cores.editor.articles.edit', ['article' => '__ID__']) }}"
     data-url-edit-quiz="{{ route('cores.editor.quizzes.edit', ['quiz' => '__ID__']) }}"
     data-url-edit-exam="{{ route('cores.editor.exams.edit', ['exam' => '__ID__']) }}"
     data-url-edit-deck="{{ route('cores.editor.flashcards.edit', ['deck' => '__ID__']) }}"
     data-copy-targets='@json($copyTargets)'>

    {{-- En-tête du groupe --}}
    <div class="card gc-hero mb-3">
        <div class="card-body d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <a href="{{ route('cores.groups.index') }}" class="btn btn-sm btn-light" title="Retour à la liste des {{ term('groups') }}">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h4 class="mb-0 fw-bold">{{ $group->name }}</h4>
                    @if ($sharingMode === 'isolated')
                        <span class="badge text-bg-warning" title="Un contenu n'appartient qu'à un{{ term('fem_e') }} seul{{ term('fem_e') }} {{ term('group') }}"><i class="bi bi-lock-fill me-1"></i>Contenus isolés</span>
                    @else
                        <span class="badge text-bg-info" title="Un contenu peut être partagé entre plusieurs {{ term('groups') }}"><i class="bi bi-share me-1"></i>Contenus partagés</span>
                    @endif
                </div>
                <p class="text-muted mb-0 small">
                    Organisez les contenus {{ term('of_group') }} en dossiers. Glissez une ligne pour la réordonner,
                    ou déposez-la sur un dossier de l'arborescence pour l'y déplacer.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('cores.groups.members.page', $group->id) }}" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i> Membres</a>
            </div>
        </div>
    </div>

    {{-- Barre d'actions --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-primary" id="btn-new-folder"><i class="bi bi-folder-plus me-1"></i> Nouveau dossier</button>
        <button type="button" class="btn btn-outline-primary" id="btn-upload"><i class="bi bi-upload me-1"></i> Téléverser une ressource</button>
        <button type="button" class="btn btn-outline-primary" id="btn-link"><i class="bi bi-link-45deg me-1"></i> Ajouter un lien</button>
        <button type="button" class="btn btn-outline-primary" id="btn-attach"><i class="bi bi-plus-square me-1"></i> Ajouter un contenu existant</button>
    </div>

    <div class="row g-3">
        {{-- Arborescence --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-diagram-3 me-1"></i> Dossiers</div>
                <div class="card-body p-2" id="gc-tree">
                    <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Chargement…</div>
                </div>
            </div>
        </div>

        {{-- Contenu du dossier courant --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <nav aria-label="Dossier courant" class="flex-grow-1">
                        <ol class="breadcrumb mb-0" id="gc-breadcrumb"></ol>
                    </nav>
                    <div class="btn-group btn-group-sm d-none" id="gc-folder-actions">
                        <button type="button" class="btn btn-light" data-folder-action="edit" title="Renommer, visibilité, dates"><i class="bi bi-pencil"></i> Paramètres</button>
                        <button type="button" class="btn btn-light text-danger" data-folder-action="delete" title="Supprimer le dossier"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="gc-list"></ul>
                    <p class="text-center text-muted py-5 mb-0 d-none" id="gc-empty">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Ce dossier est vide.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Ajout d'un contenu existant --}}
<div class="modal fade" id="attach-modal" tabindex="-1" aria-labelledby="attach-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attach-title">Ajouter un contenu existant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Le contenu est ajouté au dossier ouvert : <strong id="attach-folder-name">Racine</strong>.</p>
                <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                    <select id="attach-type" class="form-select w-auto" aria-label="Type de contenu">
                        <option value="quiz">Quiz</option>
                        <option value="exam">Examens</option>
                        <option value="article">Articles</option>
                        <option value="deck">Decks de flashcards</option>
                        <option value="resource">Ressources</option>
                    </select>
                    <div class="position-relative flex-grow-1">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                        <input type="search" id="attach-search" class="form-control ps-5" placeholder="Rechercher par titre…" aria-label="Rechercher un contenu">
                    </div>
                </div>
                <div id="attach-list" class="list-group list-group-flush"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    .gc-hero { border: 0; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    #gc-tree ul { list-style: none; margin: 0; padding-left: 1rem; }
    #gc-tree > ul { padding-left: 0; }
    .gc-node { display: flex; align-items: center; gap: .35rem; padding: .3rem .5rem; border-radius: .5rem; cursor: pointer; user-select: none; }
    .gc-node:hover { background: var(--bs-tertiary-bg); }
    .gc-node.active { background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); font-weight: 600; }
    .gc-node .gc-toggle { width: 1rem; text-align: center; color: var(--bs-secondary-color); }
    .gc-node .gc-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gc-drop-over { outline: 2px dashed var(--bs-primary); outline-offset: -2px; background: rgba(var(--bs-primary-rgb), .08) !important; }
    .gc-row { display: flex; align-items: center; gap: .75rem; }
    .gc-row .gc-handle { cursor: grab; color: var(--bs-secondary-color); }
    .gc-row .gc-icon { font-size: 1.15rem; width: 1.5rem; text-align: center; }
    .gc-row .gc-title { flex: 1; min-width: 0; }
    .gc-row .gc-title a, .gc-row .gc-title span.gc-label { overflow-wrap: anywhere; }
    .gc-row.sortable-ghost { opacity: .4; }
    .gc-folder-row .gc-label { cursor: pointer; font-weight: 600; }
    .gc-dates { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .5rem; }
    #attach-list .list-group-item { display: flex; align-items: center; gap: .75rem; }
    #gc-breadcrumb .breadcrumb-item a { text-decoration: none; padding: .1rem .3rem; border-radius: .35rem; }
</style>
@endpush

@push('js')
<script src="{{ asset('plugins/sortablejs/Sortable.min.js') }}"></script>
<script src="{{ asset('js/admin/group-folder-picker.js') }}"></script>
<script type="module" src="{{ asset('js/modules/core/groups/contents.js') }}"></script>
@endpush
