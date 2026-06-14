/**
 * ArticleForm.js
 * Handles Modal, Form Validation, and AJAX Submission for Articles.
 */
export class ArticleForm {
    constructor(modalSelector, formSelector, tableInstance) {
        this.$modal = $(modalSelector);
        this.$form = $(formSelector);
        this.table = tableInstance;
        this.editor = null;
        this.init();
    }

    init() {
        this.initSelect2();
        this.initEditor();
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

    initEditor() {
        // Initialize CKEditor 5 Classic
        const editorElement = document.querySelector('#content_editor');
        if (editorElement) {
            ClassicEditor
                .create(editorElement, {
                    toolbar: [
                        'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                        'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo'
                    ]
                })
                .then(newEditor => {
                    this.editor = newEditor;
                    console.log("CKEditor 5 initialized successfully");
                })
                .catch(error => {
                    console.error("Error initializing CKEditor 5:", error);
                });
        }
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

        $('input, select', this.$form).on('input change', function () {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        });
    }

    openForAdd() {
        this.resetForm();
        $('#modalTitle').text('Créer un article');
        $('#article_id').val('');
        $('#is_active').prop('checked', true);

        if (this.editor) {
            this.editor.setData('');
        }

        this.$modal.modal('show');
    }

    openForEdit(articleId) {
        this.resetForm();
        $('#modalTitle').text('Modifier l\'article');
        $('#article_id').val(articleId);

        // Fetch article data via AJAX
        $.ajax({
            url: route('cores.articles.show', articleId),
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    const data = response.data;
                    $('#title').val(data.title);
                    $('#is_active').prop('checked', !!data.is_active);

                    if (this.editor) {
                        this.editor.setData(data.content || '');
                    } else {
                        $('#content_editor').val(data.content || '');
                    }

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
                    text: 'Impossible de récupérer les informations de l\'article'
                });
            }
        });
    }

    initSubmission() {
        this.$form.submit((e) => {
            e.preventDefault();

            // Sync CKEditor data to textarea
            if (this.editor) {
                $('#content_editor').val(this.editor.getData());
            }

            if (!this.validateForm()) {
                return false;
            }

            const articleId = $('#article_id').val();
            const url = articleId ? route('cores.articles.update', articleId) : route('cores.articles.store');

            const formData = new FormData(this.$form[0]);
            if (articleId) {
                formData.append('_method', 'PUT');
            }

            // Ensure is_active status is explicitly appended
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
                        // Rediriger immédiatement vers le nouvel éditeur moderne
                        window.location.href = route('admin.articles.edit', response.data.id);
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

        if (!checkEmpty('#title', 'title', 'Le titre de l\'article est obligatoire')) isValid = false;

        // Validate CKEditor content
        // const content = $('#content_editor').val().trim();
        // if (content === '' || content === '<p>&nbsp;</p>' || content === '<p></p>') {
        //     errors.content = ['Le contenu de l\'article est obligatoire'];
        //     isValid = false;
        // }

        if (!isValid) {
            this.displayErrors(errors);
        }

        return isValid;
    }

    displayErrors(errors) {
        this.clearErrors();
        $.each(errors, (field, messages) => {
            if (field === 'content') {
                // For editor, insert error after editor wrapper container
                const $editorWrapper = $('.ck-editor');
                if ($editorWrapper.length > 0) {
                    $editorWrapper.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
                } else {
                    $('#content_editor').addClass('is-invalid').after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
                }
            } else {
                const $field = $(`#${field}`);
                $field.addClass('is-invalid');
                $field.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
            }
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
