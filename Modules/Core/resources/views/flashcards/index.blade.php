@extends('core::layouts.master')

@section('title', 'Gestion des Flashcards')
@section('header', 'Gestion des Flashcards')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Flashcards</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des paquets de cartes (Decks)</h3>
    </div>
    <div class="card-body">

        <div id="toolbar" class="d-flex flex-wrap gap-2">
            <button id="btn-add-deck" class="btn btn-success" data-bs-toggle="tooltip" title="Créer un paquet de cartes">
                <i class="fas fa-plus me-1"></i> Nouveau
            </button>
            <button id="btn-edit-deck" class="btn btn-info text-white" disabled data-bs-toggle="tooltip" title="Modifier le paquet">
                <i class="fas fa-edit me-1"></i> Modifier
            </button>
            <button id="btn-builder-deck" class="btn btn-warning text-dark" disabled data-bs-toggle="tooltip" title="Gérer les cartes du paquet">
                <i class="fas fa-layer-group me-1"></i> Gérer les Cartes
            </button>
            <button id="btn-preview-deck" class="btn btn-outline-primary" disabled data-bs-toggle="tooltip" title="Prévisualiser le paquet (Simulateur)">
                <i class="fas fa-play me-1"></i> Prévisualiser
            </button>
            <button id="btn-print-deck" class="btn btn-outline-dark" disabled data-bs-toggle="tooltip" title="Imprimer les cartes (A4)">
                <i class="fas fa-print me-1"></i> Imprimer
            </button>
            <button id="btn-enable-deck" class="btn btn-outline-success" disabled data-bs-toggle="tooltip" title="Activer le paquet">
                <i class="fas fa-check me-1"></i> Activer
            </button>
            <button id="btn-disable-deck" class="btn btn-outline-secondary" disabled data-bs-toggle="tooltip" title="Désactiver le paquet">
                <i class="fas fa-ban me-1"></i> Désactiver
            </button>
            <button id="btn-delete-deck" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer le paquet">
                <i class="fas fa-trash me-1"></i> Supprimer
            </button>
        </div>
        <table id="decks-table"
               data-toggle="table"
               data-url="{{ route('cores.flashcard-decks.data') }}"
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
                    <th data-field="titre" data-sortable="true">Titre</th>
                    <th data-field="matiere" data-sortable="true">Matière</th>
                    <th data-field="algorithme" data-sortable="true" data-formatter="algorithmFormatter">Algorithme SRS</th>
                    <th data-field="cards_count" data-formatter="cardsCountFormatter">Cartes</th>
                    <th data-field="groups_list" data-formatter="groupsFormatter">Groupes cibles</th>
                    <th data-field="creator_name" data-sortable="true">Créateur</th>
                    <th data-field="active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Modal deck -->
<div class="modal fade" id="modal-deck" tabindex="-1" aria-labelledby="modal-deck-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deck-form">
                @csrf
                <input type="hidden" id="deck-id" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-deck-label">Nouveau paquet de cartes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="deck-titre" class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="deck-titre" name="titre" required placeholder="Ex: Vocabulaire Anglais, Anatomie, etc.">
                    </div>
                    <div class="mb-3">
                        <label for="deck-description" class="form-label">Description</label>
                        <textarea class="form-control" id="deck-description" name="description" rows="3" placeholder="Description du paquet..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="deck-matiere" class="form-label">Matière / Catégorie</label>
                        <input type="text" class="form-control" id="deck-matiere" name="matiere" placeholder="Ex: Langues, Médecine, Droit">
                    </div>
                    <div class="mb-3">
                        <label for="deck-algorithme" class="form-label">Algorithme de répétition espacée <span class="text-danger">*</span></label>
                        <select class="form-select" id="deck-algorithme" name="algorithme" required>
                            <option value="sm2" selected>SM-2 (SuperMemo 2 - Recommandé)</option>
                            <option value="leitner">Système Leitner (Boîtes)</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="deck-is-public" name="is_public" value="1">
                        <label class="form-check-label" for="deck-is-public">Rendre ce paquet public (Visible par tous)</label>
                    </div>
                    <div class="mb-3" id="groups-select-container">
                        <label for="deck-groups" class="form-label">Assigner aux groupes d'étudiants</label>
                        <select class="form-control select2" id="deck-groups" name="group_ids[]" multiple="multiple" style="width: 100%;">
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
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

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
<style>
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: calc(2.25rem + 2px) !important;
    }
</style>
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>

<script>
    $(function () {
        // Init Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: "Sélectionner les groupes",
            allowClear: true
        });

        const $table = $('#decks-table');
        const $btnEdit = $('#btn-edit-deck');
        const $btnBuilder = $('#btn-builder-deck');
        const $btnEnable = $('#btn-enable-deck');
        const $btnDisable = $('#btn-disable-deck');
        const $btnDelete = $('#btn-delete-deck');

        $table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function () {
            const selections = $table.bootstrapTable('getSelections');
            const hasSelection = selections.length === 1;

            $btnEdit.prop('disabled', !hasSelection);
            $btnBuilder.prop('disabled', !hasSelection);
            $('#btn-preview-deck').prop('disabled', !hasSelection);
            $('#btn-print-deck').prop('disabled', !hasSelection);
            $btnEnable.prop('disabled', !hasSelection || selections[0].active);
            $btnDisable.prop('disabled', !hasSelection || !selections[0].active);
            $btnDelete.prop('disabled', !hasSelection);
        });

        // Add
        $('#btn-add-deck').click(function () {
            $('#deck-form')[0].reset();
            $('#deck-id').val('');
            $('#deck-groups').val([]).trigger('change');
            $('#modal-deck-label').text('Nouveau paquet de cartes');
            $('#modal-deck').modal('show');
        });

        // Edit
        $btnEdit.click(function () {
            const selection = $table.bootstrapTable('getSelections')[0];
            if (!selection) return;

            $.get(`/cores/flashcard-decks/${selection.id}`, function (res) {
                if (res.success) {
                    $('#deck-id').val(res.data.id);
                    $('#deck-titre').val(res.data.titre);
                    $('#deck-description').val(res.data.description);
                    $('#deck-matiere').val(res.data.matiere);
                    $('#deck-algorithme').val(res.data.algorithme);
                    $('#deck-is-public').prop('checked', !!res.data.is_public);
                    $('#deck-groups').val(res.data.group_ids).trigger('change');

                    $('#modal-deck-label').text('Modifier le paquet de cartes');
                    $('#modal-deck').modal('show');
                }
            });
        });

        // Save
        $('#deck-form').submit(function (e) {
            e.preventDefault();
            const id = $('#deck-id').val();
            const url = id ? `/cores/flashcard-decks/${id}` : '/cores/flashcard-decks';
            const method = id ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(),
                success: function (res) {
                    if (res.success) {
                        $('#modal-deck').modal('hide');
                        $table.bootstrapTable('refresh');
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: res.message,
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                },
                error: function (xhr) {
                    const err = xhr.responseJSON?.message || 'Erreur lors de l\'enregistrement';
                    Swal.fire('Erreur', err, 'error');
                }
            });
        });

        // Delete
        $btnDelete.click(function () {
            const selection = $table.bootstrapTable('getSelections')[0];
            if (!selection) return;

            Swal.fire({
                title: 'Supprimer ce paquet ?',
                text: "Cette action supprimera également toutes les flashcards associées à ce paquet.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/cores/flashcard-decks/${selection.id}`,
                        type: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function (res) {
                            if (res.success) {
                                $table.bootstrapTable('refresh');
                                Swal.fire('Supprimé', res.message, 'success');
                            }
                        }
                    });
                }
            });
        });

        // Builder
        $btnBuilder.click(function () {
            const selection = $table.bootstrapTable('getSelections')[0];
            if (selection) {
                window.location.href = `/cores/editor/flashcards/${selection.id}/edit`;
            }
        });

        // Preview
        $('#btn-preview-deck').click(function () {
            const selection = $table.bootstrapTable('getSelections')[0];
            if (selection) {
                window.location.href = `/cores/editor/flashcards/${selection.id}/preview`;
            }
        });

        // Print
        $('#btn-print-deck').click(function () {
            const selection = $table.bootstrapTable('getSelections')[0];
            if (selection) {
                const printUrl = `/cores/editor/flashcards/${selection.id}/print-iframe`;
                $('#print-iframe-loader').attr('src', printUrl);
                $('#modal-print-deck').modal('show');
            }
        });

        // Print Confirmation
        $('#btn-print-confirm').click(function () {
            document.getElementById('print-iframe-loader').contentWindow.print();
        });

        // Toggle Active status
        function toggleActive(active) {
            const selection = $table.bootstrapTable('getSelections')[0];
            if (!selection) return;

            $.post(`/cores/flashcard-decks/${selection.id}/toggle-status`, {
                _token: '{{ csrf_token() }}'
            }, function (res) {
                if (res.success) {
                    $table.bootstrapTable('refresh');
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Statut mis à jour avec succès.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            });
        }

        $btnEnable.click(() => toggleActive(true));
        $btnDisable.click(() => toggleActive(false));
    });

    function statusFormatter(value, row, index) {
        return value 
            ? '<span class="badge bg-success">Actif</span>' 
            : '<span class="badge bg-danger">Inactif</span>';
    }

    function algorithmFormatter(value, row, index) {
        return value === 'sm2' 
            ? '<span class="badge bg-primary">SM-2 (SuperMemo)</span>' 
            : '<span class="badge bg-info text-white">Leitner</span>';
    }

    function cardsCountFormatter(value, row, index) {
        return `<span class="badge bg-dark">${value} cartes</span>`;
    }

    function groupsFormatter(value, row, index) {
        if (!value || !value.length) return '<span class="text-muted">Aucun</span>';
        return value.map(g => `<span class="badge bg-secondary me-1">${g}</span>`).join('');
    }
</script>
@endpush
