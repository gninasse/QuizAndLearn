/**
 * UserActions.js
 * Handles Delete, Toggle Status, Reset Password actions.
 */
export class UserActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-user').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-user').click(() => {
            const userId = this.table.getSelectedId();
            if (userId) this.editUser(userId);
        });

        $('#btn-delete-user').click(() => {
            const userId = this.table.getSelectedId();
            if (userId) this.deleteUser(userId);
        });

        $('#btn-reset-password').click(() => {
            const userId = this.table.getSelectedId();
            if (userId) this.resetPassword(userId);
        });

        $('#btn-enable-user').click(() => {
            const userId = this.table.getSelectedId();
            if (userId) this.toggleStatus(userId, 'activer');
        });

        $('#btn-disable-user').click(() => {
            const userId = this.table.getSelectedId();
            if (userId) this.toggleStatus(userId, 'désactiver');
        });
    }

    editUser(userId) {
        window.location.href = route('cores.users.show', userId);
    }

    deleteUser(userId) {
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
                    url: route('cores.users.destroy', userId),
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

    resetPassword(userId) {
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
                    url: route('cores.users.reset-password', userId),
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

    toggleStatus(userId, action) {
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
                    url: route('cores.users.toggle-status', userId),
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
