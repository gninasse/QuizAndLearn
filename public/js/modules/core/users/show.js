/**
 * show.js
 * Handles user profile page interactions and AJAX updates
 */

$(document).ready(function () {
    console.log("Initializing User Show Page...");

    // Setup CSRF for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let isEditMode = false;
    const originalAvatarUrl = window.userAvatarUrl;

    // Edit Mode Toggle
    $('#btn-edit-mode').click(function () {
        isEditMode = true;
        enableEditMode();
    });

    // Cancel Button
    $('#btn-cancel').click(function () {
        isEditMode = false;
        disableEditMode();
        resetForm();
    });

    // Enable Edit Mode
    function enableEditMode() {
        $('#profile-form input:not([type="password"])').prop('disabled', false);
        $('#form-actions').removeClass('d-none');
        $('#btn-edit-mode').prop('disabled', true);
    }

    // Disable Edit Mode
    function disableEditMode() {
        $('#profile-form input').prop('disabled', true);
        $('#form-actions').addClass('d-none');
        $('#btn-edit-mode').prop('disabled', false);
    }

    // Reset Form to Original Values
    function resetForm() {
        $('#profile-form')[0].reset();
        $('#profile-avatar-preview').attr('src', originalAvatarUrl);
        clearErrors();
    }

    // Avatar Preview
    $('#profile-avatar').on('change', function (e) {
        const file = e.target.files[0];

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();

            reader.onload = function (e) {
                $('#profile-avatar-preview').attr('src', e.target.result);
            };

            reader.readAsDataURL(file);

            // Upload avatar immediately
            uploadAvatar(file);
        }
    });

    // Upload Avatar via AJAX
    function uploadAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);

        $.ajax({
            url: route('cores.users.update-avatar', window.userId),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function () {
                Swal.fire({
                    title: 'Upload en cours...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: response.message,
                        timer: 2000
                    });
                    // Update avatar in header
                    $('.card-body img[alt="' + $('h3').first().text() + '"]').attr('src', response.avatar_url);
                    window.userAvatarUrl = response.avatar_url;
                }
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: xhr.responseJSON?.message || 'Erreur lors de l\'upload'
                });
                // Reset preview
                $('#profile-avatar-preview').attr('src', originalAvatarUrl);
            }
        });
    }

    // Profile Form Submission
    $('#profile-form').submit(function (e) {
        e.preventDefault();

        if (!isEditMode) return;

        const formData = {
            name: $('#name').val(),
            last_name: $('#last_name').val(),
            user_name: $('#user_name').val(),
            email: $('#email').val(),
            service: $('#service').val()
        };

        $.ajax({
            url: route('cores.users.update-profile', window.userId),
            method: 'PUT',
            data: formData,
            beforeSend: function () {
                $('#btn-save-profile').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin"></i> Enregistrement...');
                clearErrors();
            },
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: response.message,
                        timer: 2000
                    });

                    // Update header
                    $('h3').first().text(response.data.name + ' ' + response.data.last_name);

                    const statusBadgeHtml = $('#user-status-badge').length ? $('#user-status-badge')[0].outerHTML : '';

                    $('.text-muted .fa-envelope').parent().html(
                        '<i class="fas fa-envelope me-1"></i> ' + response.data.email +
                        '<span class="mx-2">•</span>' +
                        '<i class="fas fa-calendar me-1"></i> Inscrit le ' +
                        new Date(response.data.created_at).toLocaleDateString('fr-FR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        }) +
                        (statusBadgeHtml ? '<span class="mx-2">•</span>' + statusBadgeHtml : '')
                    );

                    isEditMode = false;
                    disableEditMode();
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    displayErrors(xhr.responseJSON.errors);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: xhr.responseJSON?.message || 'Une erreur est survenue'
                    });
                }
            },
            complete: function () {
                $('#btn-save-profile').prop('disabled', false)
                    .html('<i class="fas fa-save"></i> Enregistrer');
            }
        });
    });

    // Display Validation Errors
    function displayErrors(errors) {
        clearErrors();
        $.each(errors, function (field, messages) {
            const $field = $('#' + field);
            $field.addClass('is-invalid');
            $field.after('<div class="invalid-feedback d-block">' + messages[0] + '</div>');
        });
    }

    // Clear Errors
    function clearErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
    }

    // Reset Password Button
    $('#btn-reset-password').click(function () {
        Swal.fire({
            title: 'Réinitialiser le mot de passe ?',
            text: 'Le mot de passe sera réinitialisé à la valeur par défaut.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, réinitialiser',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.users.reset-password', window.userId),
                    method: 'POST',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                html: response.message,
                                timer: 5000
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON?.message || 'Une erreur est survenue'
                        });
                    }
                });
            }
        });
    });

    // Toggle Status
    $('#btn-toggle-status').click(function () {
        const action = $(this).data('action');

        Swal.fire({
            title: `Voulez-vous ${action} cet utilisateur ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.users.toggle-status', window.userId),
                    method: 'POST',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: response.message,
                                timer: 2000
                            }).then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON?.message || 'Erreur changement de statut'
                        });
                    }
                });
            }
        });
    });

    // Tab Persistence
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeUserTab', $(e.target).attr('href'));
    });

    const activeTab = localStorage.getItem('activeUserTab');
    if (activeTab) {
        $('.nav-tabs a[href="' + activeTab + '"]').tab('show');
    }

    // --- Role Management Logic ---
    let availableRoles = [];
    let selectedRoleId = null;

    // Open Assign Role Modal
    $('#btn-assign-role').click(function () {
        loadAvailableRoles();
        $('#assignRoleModal').modal('show');
    });

    // Load available roles
    function loadAvailableRoles() {
        $.ajax({
            url: route('cores.users.available-roles', window.userId),
            method: 'GET',
            beforeSend: function () {
                $('#roles-list').html('<div class="p-4 text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Chargement des rôles...</div>');
                selectedRoleId = null;
                $('#btn-confirm-assign').prop('disabled', true);
            },
            success: function (response) {
                if (response.success) {
                    availableRoles = response.roles;
                    renderRoleList(availableRoles);
                }
            }
        });
    }

    // Render roles in modal list
    function renderRoleList(roles) {
        let html = '';
        if (roles.length === 0) {
            html = '<div class="p-4 text-center text-muted">Aucun rôle supplémentaire disponible.</div>';
        } else {
            roles.forEach(role => {
                html += `
                    <div class="role-item p-3 border-bottom role-selectable" data-id="${role.id}" style="cursor: pointer;">
                        <div class="d-flex align-items-center">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="selected_role" value="${role.id}" id="role_${role.id}">
                            </div>
                            <div>
                                <h6 class="mb-0">${role.name}</h6>
                                <small class="text-muted">${role.description}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        $('#roles-list').html(html);
    }

    // Search roles in modal
    $('#role-search').on('keyup', function () {
        const query = $(this).val().toLowerCase();
        const filtered = availableRoles.filter(role =>
            role.name.toLowerCase().includes(query) ||
            role.description.toLowerCase().includes(query)
        );
        renderRoleList(filtered);
    });

    // Select role from list
    $(document).on('click', '.role-selectable', function () {
        $('.role-selectable').removeClass('bg-light');
        $(this).addClass('bg-light');

        const radio = $(this).find('input[type="radio"]');
        radio.prop('checked', true);
        selectedRoleId = radio.val();
        $('#btn-confirm-assign').prop('disabled', false);
    });

    // Assign Role Form Submission
    $('#assign-role-form').submit(function (e) {
        e.preventDefault();
        if (!selectedRoleId) return;

        $.ajax({
            url: route('cores.users.assign-role', window.userId),
            method: 'POST',
            data: {
                role_id: selectedRoleId,
                note: $('#assignment-note').val()
            },
            beforeSend: function () {
                $('#btn-confirm-assign').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Attribution...');
            },
            success: function (response) {
                if (response.success) {
                    $('#assignRoleModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Réussi',
                        text: response.message,
                        timer: 2000
                    }).then(() => {
                        location.reload(); // Reload to refresh tables and stats
                    });
                }
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: xhr.responseJSON?.message || 'Une erreur est survenue'
                });
                $('#btn-confirm-assign').prop('disabled', false).html('<i class="fas fa-plus"></i> ASSIGNER LE RÔLE');
            }
        });
    });

    // Remove Role
    $(document).on('click', '.btn-remove-role', function () {
        const roleId = $(this).data('role-id');
        const roleName = $(this).data('role-name');

        Swal.fire({
            title: 'Retirer le rôle ?',
            html: `Êtes-vous sûr de vouloir retirer le rôle <strong>${roleName}</strong> à cet utilisateur ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, retirer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.users.remove-role', window.userId),
                    method: 'DELETE',
                    data: {
                        role_id: roleId
                    },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Retiré',
                                text: response.message,
                                timer: 2000
                            }).then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON?.message || 'Une erreur est survenue'
                        });
                    }
                });
            }
        });
    });

    // Search assigned roles in table
    $('#search-roles').on('keyup', function () {
        const query = $(this).val().toLowerCase();
        $('#roles-table tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(query) > -1);
        });
    });

    // Search direct permissions in table
    $('#search-direct-permissions').on('keyup', function () {
        const query = $(this).val().toLowerCase();
        $('#direct-permissions-table tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(query) > -1);
        });
    });

    // Search effective permissions in table
    $('#search-effective-permissions').on('keyup', function () {
        const query = $(this).val().toLowerCase();
        $('#effective-permissions-table tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(query) > -1);
        });
    });

    // Remove Direct Permission
    $(document).on('click', '.btn-remove-permission', function () {
        const permissionId = $(this).data('permission-id');
        const permissionName = $(this).data('permission-name');

        Swal.fire({
            title: 'Retirer la permission ?',
            html: `Êtes-vous sûr de vouloir retirer la permission directe <strong>${permissionName}</strong> ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, retirer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.users.remove-permission', window.userId),
                    method: 'DELETE',
                    data: {
                        permission_id: permissionId
                    },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Retirée',
                                text: response.message,
                                timer: 2000
                            }).then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON?.message || 'Une erreur est survenue'
                        });
                    }
                });
            }
        });
    });

    // --- Direct Permissions Management ---

    let availablePermissionsByModule = [];

    // Open Assign Permissions Modal
    $('#btn-assign-permissions').on('click', function () {
        loadAvailablePermissions();
        $('#assignPermissionsModal').modal('show');
    });

    // Fetch and Load Available Permissions
    function loadAvailablePermissions() {
        const $list = $('#permissions-list');
        const $moduleFilter = $('#module-filter');

        $list.html('<div class="p-4 text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Chargement des permissions...</p></div>');

        $.ajax({
            url: route('cores.users.available-permissions', window.userId),
            success: function (response) {
                if (response.success) {
                    availablePermissionsByModule = response.permissions_by_module;

                    // Populate module filter
                    $moduleFilter.html('<option value="all">Tous les modules</option>');
                    response.modules.forEach(module => {
                        $moduleFilter.append(`<option value="${module}">${module}</option>`);
                    });

                    renderPermissions();
                }
            }
        });
    }

    // Render Permissions in Modal
    function renderPermissions() {
        const $list = $('#permissions-list');
        const searchQuery = $('#permission-search').val().toLowerCase();
        const moduleFilter = $('#module-filter').val();

        let html = '';
        let hasPermissions = false;

        availablePermissionsByModule.forEach(group => {
            if (moduleFilter !== 'all' && group.module !== moduleFilter) {
                return;
            }

            const filteredPermissions = group.permissions.filter(p =>
                (p.name && p.name.toLowerCase().includes(searchQuery)) ||
                (p.label && p.label.toLowerCase().includes(searchQuery))
            );

            if (filteredPermissions.length > 0) {
                hasPermissions = true;
                html += `
                    <div class="bg-light px-3 py-2 border-bottom sticky-top" style="z-index: 10;">
                        <span class="fw-bold text-uppercase small text-muted">${group.module}</span>
                    </div>
                    <div class="list-group list-group-flush">
                `;

                filteredPermissions.forEach(p => {
                    html += `
                        <label class="list-group-item d-flex align-items-center py-2 cursor-pointer">
                            <div class="form-check mb-0">
                                <input class="form-check-input permission-checkbox" 
                                       type="checkbox" 
                                       value="${p.id}" 
                                       id="perm-${p.id}">
                            </div>
                            <div class="ms-3">
                                <div class="fw-bold fs-6">${p.label || p.name}</div>
                                <code class="small text-muted">${p.name}</code>
                            </div>
                        </label>
                    `;
                });

                html += `</div>`;
            }
        });

        $list.html(hasPermissions ? html : '<div class="p-4 text-center text-muted">Aucune permission trouvée correspondant à vos critères.</div>');
    }

    // Modal search and filter handlers
    $('#permission-search').on('keyup', renderPermissions);
    $('#module-filter').on('change', renderPermissions);

    // Submit Permissions Assignment
    $('#assign-permissions-form').on('submit', function (e) {
        e.preventDefault();

        const selectedIds = [];
        $('.permission-checkbox:checked').each(function () {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Veuillez sélectionner au moins une permission.'
            });
            return;
        }

        const $btn = $('#btn-confirm-assign-permissions');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Assignation...');

        $.ajax({
            url: route('cores.users.assign-permissions', window.userId),
            method: 'POST',
            data: {
                permission_ids: selectedIds
            },
            success: function (response) {
                if (response.success) {
                    $('#assignPermissionsModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Assignées',
                        text: response.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                }
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: xhr.responseJSON?.message || 'Une erreur est survenue'
                });
            },
            complete: function () {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
