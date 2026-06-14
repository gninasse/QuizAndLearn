/**
 * GroupForm.js
 * Handles Modal, Form Validation, and AJAX Submission for Groups.
 */
export class GroupForm {
    constructor(modalSelector, formSelector, tableInstance) {
        this.$modal = $(modalSelector);
        this.$form = $(formSelector);
        this.table = tableInstance;
        this.init();
    }

    init() {
        this.initValidation();
        this.initSubmission();
    }

    initValidation() {
        $('input[required]', this.$form).on('invalid', function (e) {
            e.preventDefault();
            this.setCustomValidity('');

            if (this.validity.valueMissing) {
                this.setCustomValidity('Veuillez remplir ce champ.');
            }
        });

        $('input[required]', this.$form).on('input change', function () {
            this.setCustomValidity('');
        });

        $('input, textarea', this.$form).on('input change', function () {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        });
    }

    openForAdd() {
        this.resetForm();
        $('#modalTitle').text('Ajouter un groupe');
        $('#group_id').val('');
        $('#is_active').prop('checked', true);
        this.$modal.modal('show');
    }

    openForEdit(groupId) {
        this.resetForm();
        $('#modalTitle').text('Modifier un groupe');
        $('#group_id').val(groupId);

        // Fetch group data via AJAX
        $.ajax({
            url: route('cores.groups.show', groupId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    const data = response.data;
                    $('#name').val(data.name);
                    $('#description').val(data.description);
                    
                    // Format dates (YYYY-MM-DD)
                    if (data.start_date) {
                        $('#start_date').val(data.start_date.substring(0, 10));
                    }
                    if (data.end_date) {
                        $('#end_date').val(data.end_date.substring(0, 10));
                    }
                    
                    $('#is_active').prop('checked', !!data.is_active);
                    this.$modal.modal('show');
                }
            },
            error: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de récupérer les informations du groupe'
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

            const groupId = $('#group_id').val();
            const url = groupId ? route('cores.groups.update', groupId) : route('cores.groups.store');
            
            // Standard form serialization is fine since we have no files
            const formData = new FormData(this.$form[0]);
            if (groupId) {
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

        if (!checkEmpty('#name', 'name', 'Le nom du groupe est obligatoire')) isValid = false;

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
        this.clearErrors();
    }
}
