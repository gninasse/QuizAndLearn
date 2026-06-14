/**
 * TrainerActions.js
 * Handles Delete, Toggle Status, and Reset Password actions for trainers.
 */
export class TrainerActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-trainer').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-trainer').click(() => {
            const trainerId = this.table.getSelectedId();
            if (trainerId) {
                this.form.openForEdit(trainerId);
            }
        });

        $('#btn-delete-trainer').click(() => {
            const trainerId = this.table.getSelectedId();
            if (trainerId) {
                this.deleteTrainer(trainerId);
            }
        });

        $('#btn-reset-password').click(() => {
            const trainerId = this.table.getSelectedId();
            if (trainerId) {
                this.resetPassword(trainerId);
            }
        });

        $('#btn-enable-trainer').click(() => {
            const trainerId = this.table.getSelectedId();
            if (trainerId) {
                this.toggleStatus(trainerId, 'activer');
            }
        });

        $('#btn-disable-trainer').click(() => {
            const trainerId = this.table.getSelectedId();
            if (trainerId) {
                this.toggleStatus(trainerId, 'désactiver');
            }
        });
    }

    deleteTrainer(trainerId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera définitivement le compte formateur !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.trainers.destroy', trainerId),
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

    resetPassword(trainerId) {
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
                    url: route('cores.trainers.reset-password', trainerId),
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

    toggleStatus(trainerId, action) {
        Swal.fire({
            title: `Voulez-vous ${action} ce formateur ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.trainers.toggle-status', trainerId),
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
