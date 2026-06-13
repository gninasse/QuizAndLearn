$(function () {
    const $table = $('#activities-table');
    const $filterForm = $('#filter-form');
    const $modal = $('#detailModal');
    const $modalContent = $('#modal-content');

    // Rafraîchir le tableau lors de l'application des filtres
    $('#btn-apply').on('click', function () {
        $table.bootstrapTable('refresh', {
            query: {
                module: $('#module').val(),
                user_id: $('#user_id').val(),
                role: $('#role').val(),
                action_type: $('#action_type').val(),
                log_name: $('#log_name').val(),
                subject_type: $('#subject_type').val(),
                subject_id: $('#subject_id').val(),
                ip_address: $('#ip_address').val(),
                causer_type: $('#causer_type').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val()
            }
        });
    });

    // Réinitialiser les filtres
    $('#btn-reset').on('click', function () {
        $filterForm[0].reset();
        // Reset Select2 if they exist
        if ($.fn.select2) {
            $('.select2').val(null).trigger('change');
        }
        $table.bootstrapTable('refresh', {
            query: {
                module: '',
                user_id: '',
                role: '',
                action_type: '',
                log_name: '',
                subject_type: '',
                subject_id: '',
                ip_address: '',
                causer_type: '',
                date_from: '',
                date_to: ''
            }
        });
    });

    // Bouton de rafraîchissement natif
    $('.btn-refresh').on('click', function () {
        $table.bootstrapTable('refresh');
    });

    // Événement d'ouverture de la modale
    $(document).on('click', '.btn-view-detail', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        showActivityDetail(id);
    });
});

/**
 * Charge les détails de l'activité par AJAX
 */
function showActivityDetail(id) {
    const $modal = $('#detailModal');
    const $modalContent = $('#modal-content');

    // Reset du contenu avec un spinner
    $modalContent.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>');

    const bootstrapModal = new bootstrap.Modal($modal[0]);
    bootstrapModal.show();

    $.ajax({
        url: `/cores/activities/${id}`,
        method: 'GET',
        success: function (response) {
            $modalContent.html(response);
        },
        error: function (xhr) {
            $modalContent.html('<div class="alert alert-danger m-3">Erreur lors du chargement des détails.</div>');
            console.error(xhr);
        }
    });
}

/**
 * Formatters pour Bootstrap Table
 */
window.actionFormatter = function (value, row) {
    return `<i class="fas ${row.icon} fa-lg" title="${row.description}"></i>`;
};

window.descriptionFormatter = function (value, row) {
    let label = row.description;
    const actions = {
        'created': 'Création',
        'updated': 'Modification',
        'deleted': 'Suppression',
        'restored': 'Restauration',
        'login': 'Connexion',
        'logout': 'Déconnexion',
        'permission_changed': 'Changement permission',
        'role_changed': 'Changement rôle'
    };

    if (actions[row.description]) {
        label = actions[row.description];
    }

    return `<span class="badge bg-${row.badge_color}">${label}</span>`;
};

window.rolesFormatter = function (value, row) {
    if (!value || value.length === 0) return '-';
    return value.map(role => `<span class="badge bg-secondary me-1">${role}</span>`).join('');
};

window.operateFormatter = function (value, row) {
    return [
        `<button class="btn btn-sm btn-info btn-view-detail" data-id="${row.id}" title="Voir les détails">`,
        '<i class="fas fa-eye"></i>',
        '</button>'
    ].join('');
};
