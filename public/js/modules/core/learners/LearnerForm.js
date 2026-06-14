/**
 * LearnerForm.js
 * Handles Modal, Form Validation, and AJAX Submission for Learners.
 */
export class LearnerForm {
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
        this.initSelect2();
    }

    initSelect2() {
        if ($.fn.select2) {
            $('#group_ids', this.$form).select2({
                theme: 'bootstrap-5',
                dropdownParent: this.$modal,
                placeholder: 'Sélectionner les groupes',
                allowClear: true,
                width: '100%'
            });
        }
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

        $('input, select', this.$form).on('input change', function () {
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
        $('#modalTitle').text('Ajouter un apprenant');
        $('#learner_id').val('');
        $('#password').prop('required', true);
        $('#password_confirmation').prop('required', true);
        $('.password-group', this.$form).show();
        $('#password-label', this.$form).addClass('d-none');
        this.$modal.modal('show');
    }

    openForEdit(learnerId) {
        this.resetForm();
        $('#modalTitle').text('Modifier un apprenant');
        $('#learner_id').val(learnerId);
        $('#password').prop('required', false);
        $('#password_confirmation').prop('required', false);
        $('.password-group', this.$form).show();
        $('#password-label', this.$form).removeClass('d-none');

        // Fetch learner data via AJAX
        $.ajax({
            url: route('cores.learners.show', learnerId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    const data = response.data;
                    $('#name').val(data.name);
                    $('#last_name').val(data.last_name);
                    $('#user_name').val(data.user_name);
                    $('#email').val(data.email);
                    $('#phone').val(data.phone);
                    $('#matricule').val(data.matricule);
                    
                    // Set multiple selected groups
                    if ($.fn.select2 && data.group_ids) {
                        $('#group_ids', this.$form).val(data.group_ids).trigger('change');
                    } else if (data.group_ids) {
                        $('#group_ids').val(data.group_ids);
                    }
                    
                    if (data.avatar) {
                        $('#avatar-preview').attr('src', '/' + data.avatar);
                    }
                    this.$modal.modal('show');
                }
            },
            error: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de récupérer les informations de l\'apprenant'
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

            const learnerId = $('#learner_id').val();
            const url = learnerId ? route('cores.learners.update', learnerId) : route('cores.learners.store');
            
            const formData = new FormData(this.$form[0]);
            if (learnerId) {
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
        if (!checkEmpty('#matricule', 'matricule', 'Le matricule est obligatoire')) isValid = false;

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

        if ($('#learner_id').val() === '' && password === '') {
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
        if ($.fn.select2) {
            $('#group_ids', this.$form).val(null).trigger('change');
        }
    }
}
