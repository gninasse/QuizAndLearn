/**
 * TrainerForm.js
 * Handles Modal, Form Validation, and AJAX Submission for Trainers.
 */
export class TrainerForm {
    constructor(modalSelector, formSelector, tableInstance) {
        this.$modal = $(modalSelector);
        this.$form = $(formSelector);
        this.table = tableInstance;
        this.init();
    }

    init() {
        this.initValidation();
        this.initSubmission();
        this.initPasswordToggle();
        this.initImagePreview();
        this.initGroupChecklist();
    }

    /**
     * Initialiser le composant personnalisé de sélection de groupes (liste à cocher avec tags)
     */
    initGroupChecklist() {
        const self = this;

        // Événement lors du changement d'état d'une case à cocher de groupe
        $(document).on('change', '.group-checkbox-input', function() {
            self.renderGroupTags();
        });

        // Retirer un groupe depuis son tag de fermeture (bouton ×)
        $(document).on('click', '.remove-group-tag-btn', function(e) {
            e.preventDefault();
            const groupId = $(this).data('id');
            $(`#chk-group-${groupId}`).prop('checked', false).trigger('change');
        });

        // Filtrage dynamique des groupes lors de la recherche dans l'input
        $('#group-search-input').on('input', function() {
            const query = $(this).val().toLowerCase().trim();
            $('.group-checkbox-item-li').each(function() {
                const name = $(this).data('search-name');
                if (name.indexOf(query) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Empêcher la fermeture automatique du dropdown Bootstrap lors d'un clic sur un élément de la liste
        $('#group-checkboxes-list').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Générer le rendu visuel des tags des groupes sélectionnés
     */
    renderGroupTags() {
        const $tagsContainer = $('#trainer-groups-tags');
        $tagsContainer.empty();
        
        $('.group-checkbox-input:checked').each(function() {
            const groupId = $(this).val();
            const groupName = $(this).data('name');
            
            const tagHtml = `
                <span class="group-tag" data-id="${groupId}">
                    <span>${groupName}</span>
                    <button type="button" class="remove-group-tag-btn" data-id="${groupId}">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            `;
            $tagsContainer.append(tagHtml);
        });
    }

    initValidation() {
        $('input[required], select[required]', this.$form).on('invalid', function (e) {
            e.preventDefault();
            this.setCustomValidity('');

            if (this.validity.valueMissing) {
                this.setCustomValidity('Veuillez remplir ce champ.');
            } else if (this.validity.typeMismatch) {
                if ($(this).attr('type') === 'email') {
                    this.setCustomValidity('Veuillez saisir une adresse e-mail valide.');
                }
            } else if (this.validity.tooShort) {
                this.setCustomValidity('Veuillez utiliser au moins ' + $(this).attr('minlength') + ' caractères.');
            }
        });

        $('input[required], select[required]', this.$form).on('input change', function () {
            this.setCustomValidity('');
        });

        $('input, select, textarea', this.$form).on('input change', function () {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        });
    }

    initPasswordToggle() {
        $('.toggle-password', this.$form).click(function () {
            const target = $(this).data('target');
            const $input = $(target);
            const $icon = $(this).find('i');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    }

    initImagePreview() {
        $('#avatar', this.$form).on('change', function (e) {
            const file = e.target.files[0];

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();

                reader.onload = function (e) {
                    $('#avatar-preview').attr('src', e.target.result);
                };

                reader.readAsDataURL(file);
            }
        });
    }

    openForAdd() {
        this.resetForm();
        $('#modalTitle').text('Ajouter un formateur');
        $('#trainer_id').val('');
        $('#password').prop('required', true);
        $('#password_confirmation').prop('required', true);
        $('.password-group', this.$form).show();
        $('#password-label', this.$form).addClass('d-none');
        this.$modal.modal('show');
    }

    openForEdit(trainerId) {
        this.resetForm();
        $('#modalTitle').text('Modifier un formateur');
        $('#trainer_id').val(trainerId);
        $('#password').prop('required', false);
        $('#password_confirmation').prop('required', false);
        $('.password-group', this.$form).show();
        $('#password-label', this.$form).removeClass('d-none');

        // Fetch trainer data via AJAX
        $.ajax({
            url: route('cores.trainers.show', trainerId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    const data = response.data;
                    $('#name').val(data.name);
                    $('#last_name').val(data.last_name);
                    $('#user_name').val(data.user_name);
                    $('#email').val(data.email);
                    $('#phone').val(data.phone);
                    $('#specialty').val(data.specialty);
                    $('#biography').val(data.biography);
                    
                    if (data.avatar) {
                        $('#avatar-preview').attr('src', '/' + data.avatar);
                    }
                    
                    // Coche les cases correspondantes aux groupes du formateur
                    if (data.group_ids) {
                        data.group_ids.forEach(id => {
                            $(`#chk-group-${id}`).prop('checked', true);
                        });
                        this.renderGroupTags();
                    }
                    
                    this.$modal.modal('show');
                }
            },
            error: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de récupérer les informations du formateur'
                });
            }
        });
    }

    initSubmission() {
        this.$form.submit((e) => {
            e.preventDefault();

            if (!this.validateForm()) {
                return false;
            }

            const trainerId = $('#trainer_id').val();
            const url = trainerId ? route('cores.trainers.update', trainerId) : route('cores.trainers.store');
            
            const formData = new FormData(this.$form[0]);
            if (trainerId) {
                formData.append('_method', 'PUT');
            }

            $.ajax({
                url: url,
                method: 'POST', // Always POST for FormData upload, spoofed as PUT if needed
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: () => {
                    $('#btn-save').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enregistrement...');
                },
                success: (response) => {
                    if (response.success) {
                        this.$modal.modal('hide');
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
                    if (xhr.status === 422) {
                        this.displayErrors(xhr.responseJSON.errors);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON.message || 'Une erreur est survenue'
                        });
                    }
                },
                complete: () => {
                    $('#btn-save').prop('disabled', false).html('<i class="fas fa-save"></i> Enregistrer');
                }
            });
        });
    }

    validateForm() {
        this.clearErrors();
        let isValid = true;
        const errors = {};

        const checkEmpty = (selector, field, msg) => {
            if ($(selector).val().trim() === '') {
                errors[field] = [msg];
                return false;
            }
            return true;
        };

        if (!checkEmpty('#name', 'name', 'Le prénom est obligatoire')) isValid = false;
        if (!checkEmpty('#last_name', 'last_name', 'Le nom est obligatoire')) isValid = false;
        if (!checkEmpty('#user_name', 'user_name', "Le nom d'utilisateur est obligatoire")) isValid = false;

        const email = $('#email').val().trim();
        if (email === '') {
            errors.email = ["L'email est obligatoire"];
            isValid = false;
        } else if (!this.isValidEmail(email)) {
            errors.email = ["L'email doit être valide"];
            isValid = false;
        }

        const password = $('#password').val();
        const passwordConfirm = $('#password_confirmation').val();

        if ($('#trainer_id').val() === '' && password === '') {
            errors.password = ['Le mot de passe est obligatoire'];
            isValid = false;
        } else if (password !== '') {
            const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

            if (!strongPasswordRegex.test(password)) {
                errors.password = ['Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un symbole'];
                isValid = false;
            } else if (password !== passwordConfirm) {
                errors.password_confirmation = ['Les mots de passe ne correspondent pas'];
                isValid = false;
            }
        }

        if (!isValid) {
            this.displayErrors(errors);
        }

        return isValid;
    }

    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    displayErrors(errors) {
        this.clearErrors();
        $.each(errors, (field, messages) => {
            const $field = $(`#${field}`);
            $field.addClass('is-invalid');
            $field.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
        });
    }

    clearErrors() {
        $('.is-invalid', this.$form).removeClass('is-invalid');
        $('.invalid-feedback', this.$form).remove();
    }

    resetForm() {
        this.$form[0].reset();
        this.clearErrors();
        $('#avatar-preview').attr('src', window.emptyAvatar);
        
        // Décoche tous les groupes et vide les tags
        $('.group-checkbox-input').prop('checked', false);
        $('#trainer-groups-tags').empty();
        $('#group-search-input').val('');
        $('.group-checkbox-item-li').show();
    }
}
