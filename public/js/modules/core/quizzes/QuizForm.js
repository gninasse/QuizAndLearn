/**
 * QuizForm.js
 * Handles Modal, Form Validation, and AJAX Submission for Quizzes.
 */
export class QuizForm {
    constructor(modalSelector, formSelector, tableInstance) {
        this.$modal = $(modalSelector);
        this.$form = $(formSelector);
        this.table = tableInstance;
        this.init();
    }

    init() {
        this.initSelect2();
        this.initValidation();
        this.initSubmission();
    }

    initSelect2() {
        if ($.fn.select2) {
            $('#group_ids', this.$form).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: $('#group_ids', this.$form).data('placeholder'),
                dropdownParent: this.$modal
            });
        }
    }

    initValidation() {
        $('input[required], select[required]', this.$form).on('invalid', function (e) {
            e.preventDefault();
            this.setCustomValidity('');

            if (this.validity.valueMissing) {
                this.setCustomValidity('Veuillez remplir ce champ.');
            } else if (this.validity.rangeUnderflow) {
                this.setCustomValidity('La valeur ne peut pas être inférieure à ' + $(this).attr('min') + '.');
            } else if (this.validity.rangeOverflow) {
                this.setCustomValidity('La valeur ne peut pas être supérieure à ' + $(this).attr('max') + '.');
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

    openForAdd() {
        this.resetForm();
        $('#modalTitle').text('Créer un quiz');
        $('#quiz_id').val('');
        $('#is_active').prop('checked', true);
        this.$modal.modal('show');
    }

    openForEdit(quizId) {
        this.resetForm();
        $('#modalTitle').text('Modifier le quiz');
        $('#quiz_id').val(quizId);

        // Fetch quiz data via AJAX
        $.ajax({
            url: route('cores.quizzes.show', quizId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    const data = response.data;
                    $('#title').val(data.title);
                    $('#description').val(data.description);
                    $('#duration').val(data.duration);
                    $('#passing_score').val(data.passing_score);
                    $('#is_active').prop('checked', !!data.is_active);
                    
                    // Set multiple selected groups
                    if (data.group_ids) {
                        $('#group_ids').val(data.group_ids);
                        if ($.fn.select2) {
                            $('#group_ids').trigger('change');
                        }
                    } else {
                        $('#group_ids').val(null);
                        if ($.fn.select2) {
                            $('#group_ids').trigger('change');
                        }
                    }
                    
                    this.$modal.modal('show');
                }
            },
            error: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de récupérer les informations du quiz'
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

            const quizId = $('#quiz_id').val();
            const url = quizId ? route('cores.quizzes.update', quizId) : route('cores.quizzes.store');
            
            const formData = new FormData(this.$form[0]);
            if (quizId) {
                formData.append('_method', 'PUT');
            }

            // Ensure is_active status is explicitly appended since unchecked checkboxes don't submit
            if (!$('#is_active').is(':checked')) {
                formData.append('is_active', '0');
            } else {
                formData.append('is_active', '1');
            }

            $.ajax({
                url: url,
                method: 'POST', // Spoofed as PUT if editing
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

        if (!checkEmpty('#title', 'title', 'Le titre du quiz est obligatoire')) isValid = false;

        const passingScoreVal = parseInt($('#passing_score').val());
        if (isNaN(passingScoreVal) || passingScoreVal < 0 || passingScoreVal > 100) {
            errors.passing_score = ['Le score de réussite doit être compris entre 0 et 100%'];
            isValid = false;
        }

        if (!isValid) {
            this.displayErrors(errors);
        }

        return isValid;
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
        if ($.fn.select2) {
            $('#group_ids', this.$form).val(null).trigger('change');
        }
        this.clearErrors();
    }
}
