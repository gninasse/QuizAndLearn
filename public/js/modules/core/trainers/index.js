import { TrainerTable } from './TrainerTable.js';
import { TrainerForm } from './TrainerForm.js';
import { TrainerActions } from './TrainerActions.js';

$(document).ready(function () {
    console.log("Initializing Trainers Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const trainerTable = new TrainerTable('#trainers-table');
    trainerTable.init();

    const trainerForm = new TrainerForm('#trainerModal', '#trainerForm', trainerTable);
    const trainerActions = new TrainerActions(trainerTable, trainerForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
