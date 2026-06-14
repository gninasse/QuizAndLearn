/**
 * GroupActions.js
 * Handles Delete, Toggle Status, and Members Assignment actions for groups.
 */
export class GroupActions {
    constructor(tableInstance, formInstance) {
        this.table = tableInstance;
        this.form = formInstance;
        
        // Members Modal selectors
        this.$membersModal = $('#groupMembersModal');
        this.$membersForm = $('#groupMembersForm');
        this.$trainersContainer = $('#trainers-list-container');
        this.$learnersContainer = $('#learners-list-container');
        
        this.trainers = [];
        this.learners = [];
        this.quizzes = [];
        
        this.initButtons();
        this.initMembersEvents();
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
                this.openMembersModal(groupId);
            }
        });
    }

    deleteGroup(groupId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera définitivement le groupe de formation !",
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
            title: `Voulez-vous ${action} ce groupe ?`,
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

    openMembersModal(groupId) {
        $('#members_group_id').val(groupId);
        $('#search-available-learners').val('');
        $('#members_start_date').val('');
        $('#members_end_date').val('');
        $('#members_is_active').prop('checked', false);
        
        $('#trainer-tags-input-container .trainer-tag').remove();
        $('#trainer-search-input').val('');
        $('#available-learners-container').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Chargement...</div>');
        $('#assigned-learners-container').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Chargement...</div>');
        $('#assigned-quizzes-wrapper').html('<div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Chargement...</div>');
        
        this.$membersModal.modal('show');

        $.ajax({
            url: route('cores.groups.members', groupId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    $('#membersGroupName').text(response.group_name);
                    
                    const statusBadge = response.is_active 
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-2.5 fs-7 rounded-pill">Groupe Actif</span>' 
                        : '<span class="badge bg-secondary-subtle text-secondary border py-1.5 px-2.5 fs-7 rounded-pill">Groupe Inactif</span>';
                    $('#group-status-badge').html(statusBadge);

                    // Mettre à jour les dates et le switch d'activation
                    $('#members_start_date').val(response.start_date || '');
                    $('#members_end_date').val(response.end_date || '');
                    $('#members_is_active').prop('checked', !!response.is_active);

                    this.trainers = response.trainers || [];
                    this.learners = response.learners || [];
                    this.quizzes = response.quizzes || [];
                    
                    this.renderTrainers(this.trainers);
                    this.renderLearners(this.learners);
                    this.renderQuizzes(this.quizzes);
                }
            },
            error: () => {
                this.$membersModal.modal('hide');
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de récupérer les membres du groupe.'
                });
            }
        });
    }

    renderTrainers(trainersList) {
        const assignedTrainers = trainersList.filter(t => t.assigned);
        $('#trainersCount').text(`${assignedTrainers.length} / 3 max`);

        // Remove old tags
        $('#trainer-tags-input-container .trainer-tag').remove();

        // Render new tags before search input
        assignedTrainers.forEach((trainer, idx) => {
            const initials = trainer.name.split(' ').map(n => n[0]).join('').substring(0, 2);
            const avatarHtml = trainer.avatar_url 
                ? `<img src="${trainer.avatar_url}" style="width: 18px; height: 18px; object-fit: cover; border-radius: 50%;">` 
                : `<div class="avatar-circle me-1" style="background-color: #3b82f6; width: 18px; height: 18px; font-size: 0.5rem; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;">${initials}</div>`;
            
            const tagHtml = `
                <span class="trainer-tag">
                    ${avatarHtml}
                    <span>${trainer.name}</span>
                    <button type="button" class="remove-tag-btn" data-id="${trainer.id}">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            `;
            $('#trainer-search-input').before(tagHtml);
        });

        // Clear input value
        $('#trainer-search-input').val('');
        $('#trainer-autocomplete-dropdown').hide();
    }

    renderLearners(learnersList) {
        const query = $('#search-available-learners').val().toLowerCase().trim();
        
        // Filter lists
        const available = learnersList.filter(l => !l.assigned);
        const assigned = learnersList.filter(l => l.assigned);
        
        const filteredAvailable = available.filter(l => 
            l.name.toLowerCase().includes(query) || l.email.toLowerCase().includes(query)
        );

        // Update counts
        $('#available-count').text(`${filteredAvailable.length} disponibles`);
        $('#assigned-badge-count').text(assigned.length);
        $('#learnersCount').text(`${learnersList.length} inscrits`);
        $('#totalAssignedCount').text(`${assigned.length} apprenants assignés au total`);
        
        const totalTrainersAssigned = this.trainers.filter(t => t.assigned).length;
        $('#totalMembersCount').text(totalTrainersAssigned + assigned.length);

        // Render Available
        const availableContainer = $('#available-learners-container');
        availableContainer.empty();
        
        if (filteredAvailable.length === 0) {
            availableContainer.html('<div class="text-muted text-center py-4 fs-8">Aucun apprenant disponible</div>');
        } else {
            filteredAvailable.forEach(learner => {
                const initials = learner.name.split(' ').map(n => n[0]).join('').substring(0, 2);
                const avatarHtml = learner.avatar_url 
                    ? `<img src="${learner.avatar_url}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">` 
                    : `<div class="avatar-circle" style="background-color: #7f9cf5; width: 32px; height: 32px; font-size: 0.8rem;">${initials}</div>`;
                
                const itemHtml = `
                    <div class="dual-list-item d-flex align-items-center gap-2" data-id="${learner.id}">
                        ${avatarHtml}
                        <div style="flex-grow: 1; min-width: 0;">
                            <div class="fw-bold text-dark text-truncate fs-7" style="line-height: 1.2;">${learner.name}</div>
                            <div class="text-secondary text-truncate fs-8">${learner.email}</div>
                        </div>
                    </div>
                `;
                availableContainer.append(itemHtml);
            });
        }

        // Render Assigned
        const assignedContainer = $('#assigned-learners-container');
        assignedContainer.empty();
        
        if (assigned.length === 0) {
            assignedContainer.html('<div class="text-muted text-center py-4 fs-8">Aucun apprenant assigné</div>');
        } else {
            assigned.forEach(learner => {
                const initials = learner.name.split(' ').map(n => n[0]).join('').substring(0, 2);
                const avatarHtml = learner.avatar_url 
                    ? `<img src="${learner.avatar_url}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">` 
                    : `<div class="avatar-circle" style="background-color: #7f9cf5; width: 32px; height: 32px; font-size: 0.8rem;">${initials}</div>`;
                
                const itemHtml = `
                    <div class="dual-list-item d-flex align-items-center gap-2" data-id="${learner.id}">
                        ${avatarHtml}
                        <div style="flex-grow: 1; min-width: 0;">
                            <div class="fw-bold text-dark text-truncate fs-7" style="line-height: 1.2;">${learner.name}</div>
                            <div class="text-secondary text-truncate fs-8">${learner.email}</div>
                        </div>
                    </div>
                `;
                assignedContainer.append(itemHtml);
            });
        }
    }

    /**
     * Générer le rendu visuel des cartes de quiz associés
     */
    renderQuizzes(quizzesList) {
        $('#assignedQuizzesCount').text(`${quizzesList.length} quiz`);
        const container = $('#assigned-quizzes-wrapper');
        container.empty();
        
        if (quizzesList.length === 0) {
            container.html('<div class="col-12 text-muted text-center py-3 fs-8"><i class="fas fa-exclamation-circle me-1"></i>Aucun quiz assigné à ce groupe</div>');
        } else {
            quizzesList.forEach(quiz => {
                const cardHtml = `
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded border d-flex justify-content-between align-items-center" style="box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div class="text-truncate me-2">
                                <div class="fw-bold text-dark fs-7 text-truncate" title="${quiz.title}">${quiz.title}</div>
                                <span class="text-muted fs-8">${quiz.questions_count} questions</span>
                            </div>
                            <a href="/admin/quizzes/${quiz.id}/edit" class="btn btn-xs btn-outline-success py-1 px-2 border-0" style="font-size: 0.75rem; border-radius: 4px; color: var(--green-dark);" title="Modifier le Quiz" target="_blank">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </div>
                `;
                container.append(cardHtml);
            });
        }
    }

    initMembersEvents() {
        // --- Formateurs autocomplete events ---
        
        // Input text / focus handler
        $('#trainer-search-input').on('input focus', (e) => {
            const query = $(e.target).val().toLowerCase().trim();
            const filtered = this.trainers.filter(t => !t.assigned && t.name.toLowerCase().includes(query));
            
            const dropdown = $('#trainer-autocomplete-dropdown');
            dropdown.empty();
            
            if (filtered.length > 0) {
                filtered.forEach(t => {
                    const initials = t.name.split(' ').map(n => n[0]).join('').substring(0, 2);
                    const avatarHtml = t.avatar_url 
                        ? `<img src="${t.avatar_url}" class="rounded-circle border" style="width: 24px; height: 24px; object-fit: cover;">` 
                        : `<div class="avatar-circle" style="background-color: #3b82f6; width: 24px; height: 24px; font-size: 0.65rem;">${initials}</div>`;
                    
                    dropdown.append(`
                        <div class="trainer-dropdown-item d-flex align-items-center gap-2 py-2 px-3 cursor-pointer" data-id="${t.id}">
                            ${avatarHtml}
                            <span style="font-size: 0.85rem; font-weight: 500;">${t.name}</span>
                        </div>
                    `);
                });
                dropdown.show();
            } else {
                dropdown.html('<div class="text-muted text-center py-2 fs-8">Aucun formateur disponible</div>').show();
            }
        });

        // Click dropdown item to assign
        $(document).on('click', '.trainer-dropdown-item', (e) => {
            const assignedCount = this.trainers.filter(t => t.assigned).length;
            if (assignedCount >= 3) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Limite atteinte',
                    text: 'Vous ne pouvez pas assigner plus de 3 formateurs à ce groupe.',
                    timer: 2000
                });
                $('#trainer-autocomplete-dropdown').hide();
                $('#trainer-search-input').val('');
                return;
            }
            
            const id = parseInt($(e.currentTarget).data('id'));
            const trainer = this.trainers.find(t => t.id === id);
            if (trainer) {
                trainer.assigned = true;
                this.renderTrainers(this.trainers);
                this.renderLearners(this.learners);
            }
        });

        // Remove trainer tag
        $(document).on('click', '.trainer-tag .remove-tag-btn', (e) => {
            const id = parseInt($(e.currentTarget).data('id'));
            const trainer = this.trainers.find(t => t.id === id);
            if (trainer) {
                trainer.assigned = false;
                this.renderTrainers(this.trainers);
                this.renderLearners(this.learners);
            }
        });

        // Click tag container to focus search input
        $('#trainer-tags-input-container').click((e) => {
            if (e.target.id !== 'trainer-search-input') {
                $('#trainer-search-input').focus();
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', (e) => {
            if (!$(e.target).closest('#trainer-tags-input-container').length && !$(e.target).closest('#trainer-autocomplete-dropdown').length) {
                $('#trainer-autocomplete-dropdown').hide();
            }
        });

        // --- Apprenants Dual List Box events ---

        // Selection toggling on click
        $('#available-learners-container, #assigned-learners-container').on('click', '.dual-list-item', function(e) {
            $(this).toggleClass('selected');
        });

        // Double click to transfer directly
        $('#available-learners-container').on('dblclick', '.dual-list-item', (e) => {
            const id = parseInt($(e.currentTarget).data('id'));
            const learner = this.learners.find(l => l.id === id);
            if (learner) {
                learner.assigned = true;
                this.renderLearners(this.learners);
            }
        });

        // Double click to remove directly
        $('#assigned-learners-container').on('dblclick', '.dual-list-item', (e) => {
            const id = parseInt($(e.currentTarget).data('id'));
            const learner = this.learners.find(l => l.id === id);
            if (learner) {
                learner.assigned = false;
                this.renderLearners(this.learners);
            }
        });

        // Transfer Right button click
        $('#btn-transfer-right').click(() => {
            const selectedAvailable = $('#available-learners-container .dual-list-item.selected');
            selectedAvailable.each((i, el) => {
                const id = parseInt($(el).data('id'));
                const learner = this.learners.find(l => l.id === id);
                if (learner) {
                    learner.assigned = true;
                }
            });
            this.renderLearners(this.learners);
        });

        // Transfer Left button click
        $('#btn-transfer-left').click(() => {
            const selectedAssigned = $('#assigned-learners-container .dual-list-item.selected');
            selectedAssigned.each((i, el) => {
                const id = parseInt($(el).data('id'));
                const learner = this.learners.find(l => l.id === id);
                if (learner) {
                    learner.assigned = false;
                }
            });
            this.renderLearners(this.learners);
        });

        // Tout retirer button click
        $('#btn-remove-all').click((e) => {
            e.preventDefault();
            this.learners.forEach(l => l.assigned = false);
            this.renderLearners(this.learners);
        });

        // Search available filter input
        $('#search-available-learners').on('input', () => {
            this.renderLearners(this.learners);
        });

        // Submit form (AJAX)
        this.$membersForm.submit((e) => {
            e.preventDefault();
            const groupId = $('#members_group_id').val();
            
            const payload = {
                trainer_ids: this.trainers.filter(t => t.assigned).map(t => t.id),
                learner_ids: this.learners.filter(l => l.assigned).map(l => l.id),
                start_date: $('#members_start_date').val() || null,
                end_date: $('#members_end_date').val() || null,
                is_active: $('#members_is_active').is(':checked') ? 1 : 0
            };

            $.ajax({
                url: route('cores.groups.members.assign', groupId),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                beforeSend: () => {
                    $('#btn-save-members').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Enregistrement...');
                },
                success: (response) => {
                    if (response.success) {
                        this.$membersModal.modal('hide');
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
                        text: xhr.responseJSON.message || 'Erreur lors de l\'enregistrement des membres'
                    });
                },
                complete: () => {
                    $('#btn-save-members').prop('disabled', false).html('<i class="fas fa-save me-1.5"></i> Enregistrer les modifications');
                }
            });
        });
    }
}
