import { AdminTable } from './AdminTable.js';
import { AdminForm } from './AdminForm.js';
import { AdminActions } from './AdminActions.js';

$(document).ready(function () {
    console.log("Initializing Administrators Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const adminTable = new AdminTable('#admins-table');
    adminTable.init();

    const adminForm = new AdminForm('#adminModal', '#adminForm', adminTable);
    const adminActions = new AdminActions(adminTable, adminForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
