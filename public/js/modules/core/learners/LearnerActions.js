/**
 * LearnerActions.js
 * Handles Delete, Toggle Status, and Reset Password actions for learners.
 */
export class LearnerActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-learner').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-learner').click(() => {
            const learnerId = this.table.getSelectedId();
            if (learnerId) {
                this.form.openForEdit(learnerId);
            }
        });

        $('#btn-delete-learner').click(() => {
            const learnerId = this.table.getSelectedId();
            if (learnerId) {
                this.deleteLearner(learnerId);
            }
        });

        $('#btn-reset-password').click(() => {
            const learnerId = this.table.getSelectedId();
            if (learnerId) {
                this.resetPassword(learnerId);
            }
        });

        $('#btn-enable-learner').click(() => {
            const learnerId = this.table.getSelectedId();
            if (learnerId) {
                this.toggleStatus(learnerId, 'activer');
            }
        });

        $('#btn-disable-learner').click(() => {
            const learnerId = this.table.getSelectedId();
            if (learnerId) {
                this.toggleStatus(learnerId, 'désactiver');
            }
        });
    }

    deleteLearner(learnerId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera définitivement le compte apprenant !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.learners.destroy', learnerId),
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

    resetPassword(learnerId) {
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
                    url: route('cores.learners.reset-password', learnerId),
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

    toggleStatus(learnerId, action) {
        Swal.fire({
            title: `Voulez-vous ${action} cet apprenant ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.learners.toggle-status', learnerId),
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
