/**
 * Permissions Matrix AJAX Logic
 */
$(function () {
    // Search Functionality
    $('#permission-search').on('keyup', function () {
        var value = $(this).val().toLowerCase();
        $("#permissions-table tbody tr.permission-row").filter(function () {
            // Search in the permission-name cell
            $(this).toggle($(this).find('.permission-name').text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Configuration Modal Trigger
    $('#btn-permissions-config').on('click', function () {
        $('#configModal').modal('show');
    });

    // Apply Filters Logic
    $('#btn-apply-filters').on('click', function () {
        const $form = $('#configForm');
        const selectedModules = $form.find('input[name="modules[]"]:checked');
        const selectedRoles = $form.find('input[name="roles[]"]:checked');

        // Validation: at least one role and one module
        if (selectedModules.length === 0 || selectedRoles.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Veuillez sélectionner au moins un module et au moins un rôle.'
            });
            return;
        }

        // Build URL parameters
        const params = new URLSearchParams();
        selectedModules.each(function () {
            params.append('modules[]', $(this).val());
        });
        selectedRoles.each(function () {
            params.append('roles[]', $(this).val());
        });

        // Redirect to same page with filters
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    });

    // Toggle Change Event (Bootstrap Toggle uses 'change')
    $('.permission-toggle').change(function () {
        const $checkbox = $(this);
        const roleId = $checkbox.data('role-id');
        const permissionId = $checkbox.data('permission-id');
        const isAttached = $checkbox.prop('checked'); // Bootstrap toggle updates underlying checkbox

        // Bootstrap Toggle disables the input, but we can visually disable the toggle if needed
        // For now, we rely on the AJAX speed or add a loading overlay if strictly necessary

        $.ajax({
            url: route('cores.permissions.toggle'),
            method: 'POST',
            data: {
                role_id: roleId,
                permission_id: permissionId,
                attach: isAttached ? 1 : 0
            },
            success: (response) => {
                if (response.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });

                    Toast.fire({
                        icon: 'success',
                        title: response.message
                    });
                }
            },
            error: (xhr) => {
                // Revert check state on error
                // For Bootstrap Toggle, we need to programmatically toggle it back
                $checkbox.bootstrapToggle(isAttached ? 'off' : 'on', true); // true = silent (no event req)

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: xhr.responseJSON.message || 'Une erreur est survenue'
                });
            }
        });
    });
});
