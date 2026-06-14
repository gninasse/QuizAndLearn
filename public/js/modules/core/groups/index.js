import { GroupTable } from './GroupTable.js';
import { GroupForm } from './GroupForm.js';
import { GroupActions } from './GroupActions.js';

$(document).ready(function () {
    console.log("Initializing Groups Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const groupTable = new GroupTable('#groups-table');
    groupTable.init();

    const groupForm = new GroupForm('#groupModal', '#groupForm', groupTable);
    const groupActions = new GroupActions(groupTable, groupForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
