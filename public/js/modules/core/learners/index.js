import { LearnerTable } from './LearnerTable.js';
import { LearnerForm } from './LearnerForm.js';
import { LearnerActions } from './LearnerActions.js';

$(document).ready(function () {
    console.log("Initializing Learners Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const learnerTable = new LearnerTable('#learners-table');
    learnerTable.init();

    const learnerForm = new LearnerForm('#learnerModal', '#learnerForm', learnerTable);
    const learnerActions = new LearnerActions(learnerTable, learnerForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
