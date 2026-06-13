import { UserTable } from './UserTable.js';
import { UserForm } from './UserForm.js';
import { UserActions } from './UserActions.js';

$(document).ready(function () {
    console.log("Initializing Users Module (Native ES)...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Components
    const userTable = new UserTable('#users-table');
    userTable.init();

    const userForm = new UserForm('#userModal', '#userForm', userTable);
    const userActions = new UserActions(userTable, userForm);

    // Initialize Global Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
