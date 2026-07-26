@extends('core::layouts.master')

@section('title', 'Gestion des Examens')
@section('header', 'Gestion des Examens')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Examens</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Liste des examens</h3>
    </div>
    <div class="card-body">
        <div id="toolbar" class="d-flex flex-wrap gap-2">
            <button id="btn-add-exam" class="btn btn-success" data-bs-toggle="tooltip" title="Créer un examen">
                <i class="fas fa-plus me-1"></i> Nouveau
            </button>
            <button id="btn-edit-exam" class="btn btn-info text-white" disabled data-bs-toggle="tooltip" title="Modifier l'examen">
                <i class="fas fa-edit me-1"></i> Modifier
            </button>
            <button id="btn-builder-exam" class="btn btn-warning text-dark" disabled data-bs-toggle="tooltip" title="Concevoir les questions d'examen">
                <i class="fas fa-cubes me-1"></i> Questions
            </button>
            <button id="btn-preview-exam" class="btn btn-primary" disabled data-bs-toggle="tooltip" title="Prévisualiser l'examen">
                <i class="fas fa-eye me-1"></i> Aperçu
            </button>
            <button id="btn-print-exam" class="btn btn-outline-dark" disabled data-bs-toggle="tooltip" title="Imprimer l'examen (A4)">
                <i class="fas fa-print me-1"></i> Imprimer
            </button>
            <button id="btn-supervise-exam" class="btn btn-outline-danger" disabled data-bs-toggle="tooltip" title="Superviser les tentatives en temps réel">
                <i class="fas fa-desktop me-1"></i> Supervision
            </button>
            <button id="btn-enable-exam" class="btn btn-outline-success" disabled data-bs-toggle="tooltip" title="Activer l'examen">
                <i class="fas fa-check me-1"></i> Activer
            </button>
            <button id="btn-disable-exam" class="btn btn-outline-secondary" disabled data-bs-toggle="tooltip" title="Désactiver l'examen">
                <i class="fas fa-ban me-1"></i> Désactiver
            </button>
            <button id="btn-delete-exam" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="Supprimer l'examen">
                <i class="fas fa-trash me-1"></i> Supprimer
            </button>
        </div>
        <table id="exams-table"
               data-toggle="table"
               data-url="{{ route('cores.exams.data') }}"
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
                    <th data-field="duration" data-sortable="true" data-formatter="durationFormatter">Durée</th>
                    <th data-field="passing_score" data-sortable="true" data-formatter="scoreFormatter">Score de réussite</th>
                    <th data-field="note_max" data-sortable="true" data-formatter="noteMaxFormatter">Note Max</th>
                    <th data-field="questions_count" data-formatter="questionsCountFormatter">Questions</th>
                    <th data-field="groups_list" data-formatter="groupsFormatter">Groupes</th>
                    <th data-field="creator_name" data-sortable="true">Créateur</th>
                    <th data-field="is_active" data-sortable="true" data-formatter="statusFormatter">Statut</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@include('core::exams._modal')

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

@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>

<script>
    function statusFormatter(value, row, index) {
        return value 
            ? '<span class="badge bg-success">Actif</span>' 
            : '<span class="badge bg-danger">Inactif</span>';
    }

    function durationFormatter(value, row, index) {
        return value 
            ? `<span class="badge bg-secondary"><i class="far fa-clock"></i> ${value} min</span>`
            : '<span class="text-muted">—</span>';
    }

    function scoreFormatter(value, row, index) {
        return `<span class="badge bg-warning text-dark">${value}%</span>`;
    }

    function noteMaxFormatter(value, row, index) {
        return `<span class="badge bg-purple text-white">/${value}</span>`;
    }

    function questionsCountFormatter(value, row, index) {
        return `<span class="badge bg-primary"><i class="fas fa-question-circle"></i> ${value}</span>`;
    }

    function groupsFormatter(value, row, index) {
        if (!value || value.length === 0) {
            return '<span class="text-muted">Aucun groupe</span>';
        }
        return value.map(g => `<span class="badge bg-info">${g}</span>`).join(' ');
    }

    $(document).ready(function () {
        // Set up CSRF token
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#examModal')
        });

        const $table = $('#exams-table');
        const $btnEdit = $('#btn-edit-exam');
        const $btnDelete = $('#btn-delete-exam');
        const $btnBuilder = $('#btn-builder-exam');
        const $btnPreview = $('#btn-preview-exam');
        const $btnPrint = $('#btn-print-exam');
        const $btnSupervise = $('#btn-supervise-exam');
        const $btnEnable = $('#btn-enable-exam');
        const $btnDisable = $('#btn-disable-exam');

        // Selection Handlers
        $table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table load-success.bs.table', function () {
            const selections = $table.bootstrapTable('getSelections');
            const isSingle = selections.length === 1;

            $btnEdit.prop('disabled', !isSingle);
            $btnDelete.prop('disabled', !isSingle);
            $btnBuilder.prop('disabled', !isSingle);
            $btnPreview.prop('disabled', !isSingle);
            $btnPrint.prop('disabled', !isSingle);
            $btnSupervise.prop('disabled', !isSingle);

            if (isSingle) {
                const row = selections[0];
                if (row.is_active) {
                    $btnEnable.hide();
                    $btnDisable.show().prop('disabled', false);
                } else {
                    $btnEnable.show().prop('disabled', false);
                    $btnDisable.hide();
                }
            } else {
                $btnEnable.hide();
                $btnDisable.hide();
            }
        });

        function getSelectedId() {
            const selections = $table.bootstrapTable('getSelections');
            return selections.length === 1 ? selections[0].id : null;
        }

        // Open Modal for Add
        $('#btn-add-exam').click(function () {
            $('#examForm')[0].reset();
            $('#exam_id').val('');
            $('#group_ids').val(null).trigger('change');
            $('#modalTitle').text('Créer un examen');
            
            // Set defaults
            $('#duration').val(60);
            $('#passing_score').val(50);
            $('#note_max').val(20);
            $('#max_attempts').val(1);
            $('#is_active').prop('checked', true);
            $('#plein_ecran_force').prop('checked', true);
            $('#anti_capture_strict').prop('checked', true);
            $('#navigation_interdite').prop('checked', true);
            $('#publication_resultats').val('immediate');
            $('#classement_visible').prop('checked', true);
            $('#classement_anonyme').prop('checked', false);

            $('#examModal').modal('show');
        });

        // Open Modal for Edit
        $btnEdit.click(function () {
            const id = getSelectedId();
            if (!id) return;

            $.get(`/cores/exams/${id}`, function (res) {
                if (res.success) {
                    const data = res.data;
                    $('#exam_id').val(data.id);
                    $('#title').val(data.title);
                    $('#description').val(data.description);
                    $('#duration').val(data.duration);
                    $('#passing_score').val(data.passing_score);
                    $('#note_max').val(data.note_max);
                    $('#max_attempts').val(data.max_attempts);
                    
                    if (data.available_from) {
                        $('#available_from').val(data.available_from.substring(0, 16));
                    } else {
                        $('#available_from').val('');
                    }
                    if (data.available_until) {
                        $('#available_until').val(data.available_until.substring(0, 16));
                    } else {
                        $('#available_until').val('');
                    }

                    $('#plein_ecran_force').prop('checked', data.plein_ecran_force);
                    $('#anti_capture_strict').prop('checked', data.anti_capture_strict);
                    $('#navigation_interdite').prop('checked', data.navigation_interdite);
                    $('#publication_resultats').val(data.publication_resultats);
                    $('#classement_visible').prop('checked', data.classement_visible);
                    $('#classement_anonyme').prop('checked', data.classement_anonyme);
                    $('#is_active').prop('checked', data.is_active);

                    $('#group_ids').val(data.group_ids).trigger('change');
                    $('#modalTitle').text('Modifier l\'examen');
                    $('#examModal').modal('show');
                }
            });
        });

        // Submit Form
        $('#examForm').submit(function (e) {
            e.preventDefault();
            const id = $('#exam_id').val();
            const url = id ? `/cores/exams/${id}` : '/cores/exams';
            const method = id ? 'PUT' : 'POST';

            // Gather inputs including checkboxes
            const formData = {
                title: $('#title').val(),
                description: $('#description').val(),
                duration: $('#duration').val(),
                passing_score: $('#passing_score').val(),
                max_attempts: $('#max_attempts').val(),
                available_from: $('#available_from').val() || null,
                available_until: $('#available_until').val() || null,
                plein_ecran_force: $('#plein_ecran_force').is(':checked') ? 1 : 0,
                anti_capture_strict: $('#anti_capture_strict').is(':checked') ? 1 : 0,
                navigation_interdite: $('#navigation_interdite').is(':checked') ? 1 : 0,
                publication_resultats: $('#publication_resultats').val(),
                classement_visible: $('#classement_visible').is(':checked') ? 1 : 0,
                classement_anonyme: $('#classement_anonyme').is(':checked') ? 1 : 0,
                note_max: $('#note_max').val(),
                is_active: $('#is_active').is(':checked') ? 1 : 0,
                group_ids: $('#group_ids').val() || []
            };

            $.ajax({
                url: url,
                type: method,
                data: formData,
                success: function (res) {
                    if (res.success) {
                        $('#examModal').modal('hide');
                        $table.bootstrapTable('refresh');
                        Swal.fire('Succès', res.message, 'success');
                    } else {
                        Swal.fire('Erreur', res.message, 'error');
                    }
                },
                error: function (xhr) {
                    let msg = 'Une erreur est survenue';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire('Erreur', msg, 'error');
                }
            });
        });

        // Toggle published status
        function toggleActive(id) {
            $.post(`/cores/exams/${id}/toggle-status`, function (res) {
                if (res.success) {
                    $table.bootstrapTable('refresh');
                    Swal.fire('Succès', res.message, 'success');
                }
            });
        }

        $btnEnable.click(function () {
            const id = getSelectedId();
            if (id) toggleActive(id);
        });

        $btnDisable.click(function () {
            const id = getSelectedId();
            if (id) toggleActive(id);
        });

        // Delete exam
        $btnDelete.click(function () {
            const id = getSelectedId();
            if (!id) return;

            Swal.fire({
                title: 'Êtes-vous sûr ?',
                text: "Cette action supprimera également toutes les questions et les tentatives associées !",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Oui, supprimer !',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/cores/exams/${id}`,
                        type: 'DELETE',
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

        // Route actions
        $btnBuilder.click(function () {
            const id = getSelectedId();
            if (id) window.location.href = `/cores/editor/exams/${id}/edit`;
        });

        $btnPreview.click(function () {
            const id = getSelectedId();
            if (id) window.location.href = `/cores/editor/exams/${id}/preview`;
        });

        $btnSupervise.click(function () {
            const id = getSelectedId();
            if (id) window.location.href = `/cores/editor/exams/${id}/supervision`;
        });

        // Print
        $btnPrint.click(function () {
            const id = getSelectedId();
            if (id) {
                const printUrl = `/cores/editor/exams/${id}/print-iframe`;
                $('#print-iframe-loader').attr('src', printUrl);
                $('#modal-print-exam').modal('show');
            }
        });

        // Print Confirmation
        $('#btn-print-confirm').click(function () {
            document.getElementById('print-iframe-loader').contentWindow.print();
        });
    });
</script>
@endpush
