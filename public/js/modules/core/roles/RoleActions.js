/**
 * RoleActions.js
 * Handles Edit and Delete actions for Roles.
 */
export class RoleActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-role').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-role').click(() => {
            const roleId = this.table.getSelectedId();
            if (roleId) this.editRole(roleId);
        });

        $('#btn-delete-role').click(() => {
            const roleId = this.table.getSelectedId();
            if (roleId) this.deleteRole(roleId);
        });

        $('#btn-manage-permissions').click(() => {
            const roleId = this.table.getSelectedId();
            if (roleId) this.managePermissions(roleId);
        });
    }

    managePermissions(roleId) {
        let permissionsByModule = [];
        const $modal = $('#permissionModal');
        const $container = $('#permissions-container');
        const $roleNameDisplay = $('#role-name-display');
        const $search = $('#permission-search');
        const $filter = $('#module-filter');
        const $selectAll = $('#select-all-permissions');

        $roleNameDisplay.text('...');
        $container.html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Chargement des permissions...</p></div>');
        $modal.modal('show');

        // Fetch permissions
        $.ajax({
            url: route('cores.roles.permissions', roleId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    permissionsByModule = response.permissions_by_module;
                    $roleNameDisplay.text(response.role_name);

                    // Populate filter
                    $filter.html('<option value="all">Tous les modules</option>');
                    response.modules.forEach(m => {
                        $filter.append(`<option value="${m}">${m}</option>`);
                    });

                    this.renderPermissions(permissionsByModule, $container);
                    this.initPermissionEvents(roleId, permissionsByModule, $container, $search, $filter, $selectAll);
                }
            },
            error: (xhr) => {
                $modal.modal('hide');
                Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de charger les permissions' });
            }
        });
    }

    renderPermissions(data, $container, searchTerm = '', moduleFilter = 'all') {
        let html = '';
        let hasVisible = false;

        data.forEach(moduleData => {
            const filteredPermissions = moduleData.permissions.filter(p => {
                const matchesSearch = p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    p.label.toLowerCase().includes(searchTerm.toLowerCase());
                const matchesModule = moduleFilter === 'all' || moduleData.module === moduleFilter;
                return matchesSearch && matchesModule;
            });

            if (filteredPermissions.length > 0) {
                hasVisible = true;
                html += `<div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3 text-uppercase small fw-bold">
                                <i class="fas fa-folder me-2"></i>MODULE ${moduleData.module}
                            </h6>
                            <div class="row">`;

                filteredPermissions.forEach(p => {
                    html += `<div class="col-md-6 mb-3">
                                <div class="card h-100 border-light shadow-sm">
                                    <div class="card-body py-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold small">${p.label}</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">${p.name}</div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input permission-switch" type="checkbox" 
                                                   data-id="${p.id}" ${p.assigned ? 'checked' : ''}>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                });

                html += `</div></div>`;
            }
        });

        $container.html(hasVisible ? html : '<div class="text-center p-5 text-muted">Aucune permission trouvée</div>');
    }

    initPermissionEvents(roleId, data, $container, $search, $filter, $selectAll) {
        const updateView = () => {
            this.renderPermissions(data, $container, $search.val(), $filter.val());
        };

        $search.off('input').on('input', updateView);
        $filter.off('change').on('change', updateView);

        // Bind toggle event (delegated because of re-rendering)
        $container.off('change', '.permission-switch').on('change', '.permission-switch', (e) => {
            const $switch = $(e.target);
            const permissionId = $switch.data('id');
            const isAssigned = $switch.is(':checked');

            $switch.prop('disabled', true);

            $.ajax({
                url: route('cores.roles.toggle-permission', roleId),
                method: 'POST',
                data: {
                    permission_id: permissionId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: (response) => {
                    if (!response.success) {
                        $switch.prop('checked', !isAssigned);
                        Swal.fire({ icon: 'error', title: 'Erreur', text: response.message });
                    } else {
                        // Toast notification for instant feedback
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
                    $switch.prop('checked', !isAssigned);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: xhr.responseJSON.message || 'Erreur lors de la synchronisation'
                    });
                },
                complete: () => {
                    $switch.prop('disabled', false);
                }
            });
        });

        $selectAll.off('change').on('change', (e) => {
            const isChecked = $(e.target).is(':checked');
            const switches = $('.permission-switch', $container).filter(function () {
                return $(this).is(':checked') !== isChecked;
            });

            if (switches.length > 5 && isChecked) {
                Swal.fire({
                    title: 'Attention',
                    text: `Vous allez activer ${switches.length} permissions. Voulez-vous continuer ?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, tout activer',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.processBulkToggle(roleId, switches, isChecked);
                    } else {
                        $selectAll.prop('checked', !isChecked);
                    }
                });
            } else {
                this.processBulkToggle(roleId, switches, isChecked);
            }
        });
    }

    processBulkToggle(roleId, switches, isChecked) {
        switches.each(function () {
            const $s = $(this);
            $s.prop('checked', isChecked).trigger('change');
        });
    }

    editRole(roleId) {
        $.ajax({
            url: route('cores.roles.show', roleId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    this.form.openForEdit(roleId, response.data);
                }
            },
            error: (xhr) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger les données'
                });
            }
        });
    }

    deleteRole(roleId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.roles.destroy', roleId),
                    method: 'DELETE',
                    success: (response) => {
                        if (response.success) {
                            this.table.refresh();
                            Swal.fire({
                                icon: 'success',
                                title: 'Supprimé',
                                text: response.message,
                                timer: 2000
                            });
                        }
                    },
                    error: (xhr) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON.message || 'Erreur lors de la suppression'
                        });
                    }
                });
            }
        });
    }
}
