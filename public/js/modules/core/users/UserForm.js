/**
 * UserForm.js
 * Handles Modal, Form Validation, and Submission.
 */
export class UserForm {
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
    }

    initValidation() {
        // Native HTML5 validation customization
        $('input[required], textarea[required], select[required]', this.$form).on('invalid', function (e) {
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

        $('input[required], textarea[required], select[required]', this.$form).on('input change', function () {
            this.setCustomValidity('');
        });

        // Remove 'is-invalid' class on input
        $('input', this.$form).on('input', function () {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        });
    }

    initPasswordToggle() {
        $('.toggle-password').click(function () {
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
        $('#avatar').on('change', function (e) {
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
        $('#modalTitle').text('Ajouter un utilisateur');
        $('#user_id').val('');
        $('#password').prop('required', true);
        $('#password_confirmation').prop('required', true);
        $('.password-group').show();
        $('#password-label').addClass('d-none');
        this.$modal.modal('show');
    }

    openForEdit(userId, data) {
        $('#modalTitle').text('Modifier un utilisateur');
        $('#user_id').val(data.id);
        $('#name').val(data.name);
        $('#last_name').val(data.last_name);
        $('#user_name').val(data.user_name);
        $('#email').val(data.email);
        $('#service').val(data.service);
        $('#password').prop('required', false);
        $('#password_confirmation').prop('required', false);
        $('.password-group').hide();
        $('#password-label').addClass('d-none');
        this.$modal.modal('show');
    }

    initSubmission() {
        this.$form.submit((e) => {
            e.preventDefault();

            if (!this.validateForm()) {
                return false;
            }

            const userId = $('#user_id').val();
            const url = userId ? route('cores.users.update', userId) : route('cores.users.store');
            const method = userId ? 'PUT' : 'POST';

            const formData = new FormData(this.$form[0]);
            if (userId) formData.append('_method', 'PUT');

            $.ajax({
                url: url,
                method: 'POST', // Always POST for FormData with binary content, spoof method for Laravel
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

        // Helper to check empty
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

        if ($('#user_id').val() === '' && password === '') {
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
    }
}
