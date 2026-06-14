@extends('core::layouts.master')

@section('title', 'Gestion des Articles')
@section('header', 'Gestion des Articles')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Articles</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des articles pédagogiques</h3>
    </div>
    <div class="card-body">
        <div id="toolbar" class="d-flex flex-wrap gap-2">
            <button id="btn-add-article" class="btn btn-success" data-bs-toggle="tooltip" title="Créer un article">
                <i class="fas fa-plus me-1"></i> Nouveau
            </button>
            <button id="btn-edit-article" class="btn btn-info text-white" disabled data-bs-toggle="tooltip" title="Modifier l'article">
                <i class="fas fa-edit me-1"></i> Modifier
            </button>
            <button id="btn-export-article" class="btn btn-warning text-dark" disabled data-bs-toggle="tooltip" title="Exporter en HTML autonome">
                <i class="fas fa-file-download me-1"></i> Exporter
            </button>
            <button id="btn-enable-article" class="btn btn-outline-success" disabled data-bs-toggle="tooltip" title="Activer l'article">
                <i class="fas fa-check me-1"></i> Activer
            </button>
            <button id="btn-disable-article" class="btn btn-outline-secondary" disabled data-bs-toggle="tooltip" title="Désactiver l'article">
                <i class="fas fa-ban me-1"></i> Désactiver
            </button>
            <button id="btn-delete-article" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer l'article">
                <i class="fas fa-trash me-1"></i> Supprimer
            </button>
        </div>
        <table id="articles-table"
               data-toggle="table"
               data-url="{{ route('cores.articles.data') }}"
               data-pagination="true"
               data-side-pagination="server"
               data-search="true"
               data-show-refresh="true"
               data-show-columns="true"
               data-toolbar="#toolbar"
               data-click-to-select="true"
               data-single-select="true"
               data-id-field="id"
               data-page-list="[10, 25, 50, 100]">
            <thead>
                <tr>
                    <th data-field="state" data-radio="true"></th>
                    <th data-field="id" data-sortable="true">ID</th>
                    <th data-field="title" data-sortable="true">Titre</th>
                    <th data-field="content_excerpt" data-sortable="false">Extrait du contenu</th>
                    <th data-field="groups_list" data-formatter="groupsFormatter">Groupes</th>
                    <th data-field="creator_name" data-sortable="true">Créateur</th>
                    <th data-field="is_active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::articles._modal')

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
<style>
    .m-t-small { margin-top: 15px; }
    .ck-editor__editable_inline {
        min-height: 250px;
    }
</style>
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>
<!-- CKEditor 5 Classic editor from CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>

<script>
    function statusFormatter(value, row, index) {
        return value 
            ? '<span class="badge bg-success">Actif</span>' 
            : '<span class="badge bg-danger">Inactif</span>';
    }

    function groupsFormatter(value, row, index) {
        if (!value || value.length === 0) {
            return '<span class="text-muted">Aucun groupe</span>';
        }
        return value.map(g => `<span class="badge bg-info">${g}</span>`).join(' ');
    }
</script>
<script type="module" src="{{ asset('js/modules/core/articles/index.js') }}"></script>
@endpush
