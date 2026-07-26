@extends('core::layouts.master')

@section('title', 'Supervision - ' . $exam->title)
@section('header', 'Supervision en Direct - ' . $exam->title)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cores.exams.index') }}">Examens</a></li>
    <li class="breadcrumb-item active" aria-current="page">Supervision</li>
@endsection

@section('content')
<div class="row">
    <!-- Live Stats Widgets -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Tentatives en cours</span>
                <span class="info-box-number" id="stats-active">0</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check-double"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Terminées</span>
                <span class="info-box-number" id="stats-completed">0</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Tricherie / Annulés</span>
                <span class="info-box-number text-danger" id="stats-violations">0</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Temps écoulé</span>
                <span class="info-box-number" id="stats-timeup">0</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Apprenants connectés à l'examen</h3>
        <div class="card-tools d-flex align-items-center gap-2">
            <span class="badge bg-danger pulse-dot me-2">LIVE</span>
            <button id="btn-refresh-live" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sync-alt"></i> Rafraîchir
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="supervision-table"
               data-toggle="table"
               data-pagination="true"
               data-search="true"
               data-show-columns="true"
               data-click-to-select="true"
               data-id-field="id"
               data-page-list="[10, 25, 50]">
            <thead>
                <tr>
                    <th data-field="apprenant_name" data-sortable="true">Apprenant</th>
                    <th data-field="date_debut" data-sortable="true">Heure de Début</th>
                    <th data-field="duree_reelle">Temps Passé</th>
                    <th data-field="status" data-formatter="statusFormatter" data-sortable="true">Statut</th>
                    <th data-field="capture_attempts" data-formatter="alertFormatter" data-sortable="true">Captures Écran</th>
                    <th data-field="navigation_violations" data-formatter="alertFormatter" data-sortable="true">Sorties Plein Écran</th>
                    <th data-field="note_sur_vingt" data-sortable="true">Score Final</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<style>
.pulse-dot {
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { opacity: 0.4; }
    50% { opacity: 1; }
    100% { opacity: 0.4; }
}
</style>
@stop

@push('css')
<link rel="stylesheet" href="{{ asset('plugins/bootstrap-table/bootstrap-table.min.css') }}">
@endpush

@push('js')
<script src="{{ asset('plugins/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-table/locale/bootstrap-table-fr-FR.min.js') }}"></script>

<script>
    const examId = "{{ $exam->id }}";
    const $table = $('#supervision-table');

    function statusFormatter(value, row, index) {
        if (value === 'en_cours') {
            return '<span class="badge bg-info"><i class="fas fa-spinner fa-spin me-1"></i> En cours</span>';
        } else if (value === 'termine' || value === 'completed') {
            return '<span class="badge bg-success"><i class="fas fa-check me-1"></i> Terminé</span>';
        } else if (value === 'annule' || value === 'cancelled') {
            return '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Annulé (Triche)</span>';
        } else if (value === 'time_up') {
            return '<span class="badge bg-warning text-dark"><i class="fas fa-hourglass-end me-1"></i> Temps expiré</span>';
        }
        return `<span class="badge bg-secondary">${value}</span>`;
    }

    function alertFormatter(value, row, index) {
        const val = parseInt(value) || 0;
        if (val === 0) {
            return '<span class="text-success font-weight-bold">0</span>';
        }
        return `<span class="badge bg-danger px-2">${val} ⚠️</span>`;
    }

    function loadSupervisionData() {
        $.get(`/cores/exams/${examId}/supervision-data`, function (res) {
            if (res.success) {
                // Update table
                $table.bootstrapTable('load', res.rows);

                // Re-calculate statistics
                let active = 0, completed = 0, violations = 0, timeup = 0;
                res.rows.forEach(row => {
                    if (row.status === 'en_cours') active++;
                    else if (row.status === 'termine' || row.status === 'completed') completed++;
                    else if (row.status === 'annule' || row.status === 'cancelled') violations++;
                    else if (row.status === 'time_up') timeup++;
                });

                $('#stats-active').text(active);
                $('#stats-completed').text(completed);
                $('#stats-violations').text(violations);
                $('#stats-timeup').text(timeup);
            }
        });
    }

    $(document).ready(function () {
        // Initial load
        $table.bootstrapTable();
        loadSupervisionData();

        // Refresh click
        $('#btn-refresh-live').click(function () {
            loadSupervisionData();
        });

        // Live polling every 5 seconds
        const pollInterval = setInterval(loadSupervisionData, 5000);

        // Clear interval on page unload
        $(window).on('beforeunload', function () {
            clearInterval(pollInterval);
        });
    });
</script>
@endpush
