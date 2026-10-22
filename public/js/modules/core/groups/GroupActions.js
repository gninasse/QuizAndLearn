/**
 * GroupActions.js
 * Handles Delete, Toggle Status and navigation to the members and contents pages.
 */
export class GroupActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;

        this.initButtons();
    }

    initButtons() {
        $('#btn-add-group').click(() => {
            this.form.openForAdd();
        });

        $('#btn-edit-group').click(() => {
            const groupId = this.table.getSelectedId();
            if (groupId) {
                this.form.openForEdit(groupId);
            }
        });

        $('#btn-delete-group').click(() => {
            const groupId = this.table.getSelectedId();
            if (groupId) {
                this.deleteGroup(groupId);
            }
        });

        $('#btn-enable-group').click(() => {
            const groupId = this.table.getSelectedId();
            if (groupId) {
                this.toggleStatus(groupId, 'activer');
            }
        });

        $('#btn-disable-group').click(() => {
            const groupId = this.table.getSelectedId();
            if (groupId) {
                this.toggleStatus(groupId, 'désactiver');
            }
        });

        $('#btn-members-group').click(() => {
            const groupId = this.table.getSelectedId();
            if (groupId) {
                // Page dédiée (recherche, fiche détaillée, suspension…).
                window.location.href = `/cores/groups/${groupId}/membres`;
            }
        });

        $('#btn-contents-group').click(() => {
            const groupId = this.table.getSelectedId();
            if (groupId) {
                // Arborescence de dossiers et contenus du groupe.
                window.location.href = `/cores/groups/${groupId}/contenus`;
            }
        });
    }

    deleteGroup(groupId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: `Cette action supprimera définitivement ${T('the_group')} de formation !`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.groups.destroy', groupId),
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

    toggleStatus(groupId, action) {
        Swal.fire({
            title: `Voulez-vous ${action} ${T('this_group')} ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.groups.toggle-status', groupId),
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
