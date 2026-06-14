/**
 * QuizActions.js
 * Handles Delete and Toggle Status actions for quizzes.
 */
export class QuizActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-quiz').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-quiz').click(() => {
            const quizId = this.table.getSelectedId();
            if (quizId) {
                this.form.openForEdit(quizId);
            }
        });

        $('#btn-builder-quiz').click(() => {
            const quizId = this.table.getSelectedId();
            if (quizId) {
                window.location.href = route('admin.quizzes.edit', quizId);
            }
        });

        $('#btn-preview-quiz').click(() => {
            const quizId = this.table.getSelectedId();
            if (quizId) {
                window.location.href = route('admin.quizzes.preview', quizId);
            }
        });

        $('#btn-delete-quiz').click(() => {
            const quizId = this.table.getSelectedId();
            if (quizId) {
                this.deleteQuiz(quizId);
            }
        });

        $('#btn-enable-quiz').click(() => {
            const quizId = this.table.getSelectedId();
            if (quizId) {
                this.toggleStatus(quizId, 'activer');
            }
        });

        $('#btn-disable-quiz').click(() => {
            const quizId = this.table.getSelectedId();
            if (quizId) {
                this.toggleStatus(quizId, 'désactiver');
            }
        });
    }

    deleteQuiz(quizId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera définitivement le quiz et toutes ses questions !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.quizzes.destroy', quizId),
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

    toggleStatus(quizId, action) {
        Swal.fire({
            title: `Voulez-vous ${action} ce quiz ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.quizzes.toggle-status', quizId),
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
