/**
 * ArticleActions.js
 * Handles Delete, Toggle Status, and HTML Export actions for articles.
 */
export class ArticleActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        this.initButtons();
    }

    initButtons() {
        $('#btn-add-article').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-article').click(() => {
            const articleId = this.table.getSelectedId();
            if (articleId) {
                window.location.href = route('admin.articles.edit', articleId);
            }
        });

        $('#btn-delete-article').click(() => {
            const articleId = this.table.getSelectedId();
            if (articleId) {
                this.deleteArticle(articleId);
            }
        });

        $('#btn-enable-article').click(() => {
            const articleId = this.table.getSelectedId();
            if (articleId) {
                this.toggleStatus(articleId, 'activer');
            }
        });

        $('#btn-disable-article').click(() => {
            const articleId = this.table.getSelectedId();
            if (articleId) {
                this.toggleStatus(articleId, 'désactiver');
            }
        });

        $('#btn-export-article').click(() => {
            const articleId = this.table.getSelectedId();
            if (articleId) {
                this.exportArticle(articleId);
            }
        });
    }

    deleteArticle(articleId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera définitivement l'article pédagogique !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.articles.destroy', articleId),
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

    toggleStatus(articleId, action) {
        Swal.fire({
            title: `Voulez-vous ${action} cet article ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.articles.toggle-status', articleId),
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

    exportArticle(articleId) {
        // Simple direct download redirection
        window.location.href = route('cores.articles.export', articleId);
    }
}
