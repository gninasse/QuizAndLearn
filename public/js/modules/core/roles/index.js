/**
 * index.js
 * Entry point for Roles management
 */
import { RoleForm } from './RoleForm.js';
import { RoleActions } from './RoleActions.js';

$(function () {
    // ---- 1. Bootstrap Table Configuration ----
    const $table = $('#roles-table');

    // Date formatter
    window.dateFormatter = function (value) {
        if (!value) return '-';
        return new Date(value).toLocaleDateString('fr-FR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    // Table instance helper
    const tableInstance = {
        refresh: () => $table.bootstrapTable('refresh'),
        getSelectedId: () => {
            const selections = $table.bootstrapTable('getSelections');
            if (selections.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Veuillez sélectionner une ligne'
                });
                return null;
            }
            return selections[0].id;
        }
    };

    // Initialize Form and Actions
    const roleForm = new RoleForm('#roleModal', '#roleForm', tableInstance);
    new RoleActions(tableInstance, roleForm);

    // Enable/Disable buttons on selection
    $table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function () {
        const selections = $table.bootstrapTable('getSelections');
        const hasSelection = selections.length > 0;
        const isSingleSelection = selections.length === 1;

        $('#btn-edit-role').prop('disabled', !isSingleSelection);
        $('#btn-delete-role').prop('disabled', !hasSelection);
        $('#btn-manage-permissions').prop('disabled', !isSingleSelection);
    });
});
