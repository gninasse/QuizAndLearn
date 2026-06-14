/**
 * AdminActions.js
 * Handles Delete, Toggle Status, and Reset Password actions for administrators.
 */
export class AdminActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-admin').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-admin').click(() => {
            const adminId = this.table.getSelectedId();
            if (adminId) {
                this.form.openForEdit(adminId);
            }
        });

        $('#btn-delete-admin').click(() => {
            const adminId = this.table.getSelectedId();
            if (adminId) {
                this.deleteAdmin(adminId);
            }
        });

        $('#btn-reset-password').click(() => {
            const adminId = this.table.getSelectedId();
            if (adminId) {
                this.resetPassword(adminId);
            }
        });

        $('#btn-enable-admin').click(() => {
            const adminId = this.table.getSelectedId();
            if (adminId) {
                this.toggleStatus(adminId, 'activer');
            }
        });

        $('#btn-disable-admin').click(() => {
            const adminId = this.table.getSelectedId();
            if (adminId) {
                this.toggleStatus(adminId, 'désactiver');
            }
        });
    }

    deleteAdmin(adminId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera définitivement le compte administrateur !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.admins.destroy', adminId),
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

    resetPassword(adminId) {
        Swal.fire({
            title: 'Réinitialiser le mot de passe ?',
            text: "Le mot de passe sera réinitialisé par défaut.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f39c12',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, réinitialiser',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.admins.reset-password', adminId),
                    method: 'POST',
                    success: (response) => {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: response.message,
                            });
                        }
                    },
                    error: (xhr) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON.message || 'Erreur lors de la réinitialisation'
                        });
                    }
                });
            }
        });
    }

    toggleStatus(adminId, action) {
        Swal.fire({
            title: `Voulez-vous ${action} cet administrateur ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.admins.toggle-status', adminId),
                    method: 'POST',
                    success: (response) => {
                        if (response.success) {
                            this.table.refresh();
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: response.message,
                                timer: 2000
                            });
                        }
                    },
                    error: (xhr) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON.message || 'Erreur changement de statut'
                        });
                    }
                });
            }
        });
    }
}
