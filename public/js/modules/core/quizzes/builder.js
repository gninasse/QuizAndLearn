/**
 * builder.js
 * Handles visual questions management for the quiz designer.
 */

$(document).ready(function () {
    console.log("Initializing Quiz Builder...");

    // Global state
    let questions = window.questionsData || [];
    let selectedQuestionId = null;

    // Setup CSRF
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize UI
    renderQuestionsList();

    // Event listeners
    $('#btn-add-question').click(function () {
        openEditorForAdd();
    });

    $('#btn-cancel-edit').click(function () {
        closeEditor();
    });

    $('#type').change(function () {
        renderOptionsContainer();
    });

    // Form submit
    $('#question-form').submit(function (e) {
        e.preventDefault();
        saveQuestion();
    });

    // Select question from list
    $('#questions-list').on('click', '.question-item', function (e) {
        // Prevent click if clicking actions
        if ($(e.target).closest('button').length > 0) {
            return;
        }
        const id = $(this).data('id');
        selectQuestion(id);
    });

    // Delete question action button in list
    $('#questions-list').on('click', '.btn-delete-question-action', function (e) {
        e.stopPropagation();
        const id = $(this).closest('.question-item').data('id');
        deleteQuestion(id);
    });

    // Reordering actions
    $('#questions-list').on('click', '.btn-move-up', function (e) {
        e.stopPropagation();
        const id = $(this).closest('.question-item').data('id');
        moveQuestion(id, 'up');
    });

    $('#questions-list').on('click', '.btn-move-down', function (e) {
        e.stopPropagation();
        const id = $(this).closest('.question-item').data('id');
        moveQuestion(id, 'down');
    });

    // Dynamic Options Addition Buttons
    $('#options-card').on('click', '#btn-add-choice', function () {
        addChoiceRow();
    });

    $('#options-card').on('click', '#btn-add-pair', function () {
        addPairRow();
    });

    // Removing choices / pairs
    $('#options-card').on('click', '.btn-remove-choice-row', function () {
        $(this).closest('.choice-row').remove();
    });

    $('#options-card').on('click', '.btn-remove-pair-row', function () {
        $(this).closest('.pair-row').remove();
    });

    // Ensure single choice radio buttons sync properly (only one checked)
    $('#options-card').on('change', '.single-choice-radio', function () {
        if ($(this).is(':checked')) {
            $('.single-choice-radio').not(this).prop('checked', false);
        }
    });

    // Render questions list
    function renderQuestionsList() {
        // Sort local array by order field
        questions.sort((a, b) => a.order - b.order);
        
        const $list = $('#questions-list');
        $list.empty();

        if (questions.length === 0) {
            $list.html(`
                <div class="text-center py-5 text-muted" id="no-questions-placeholder">
                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                    <p class="mb-0">Aucune question dans ce quiz.</p>
                    <small>Cliquez sur "Ajouter" pour commencer.</small>
                </div>
            `);
            $('#questions-count').text(0);
            return;
        }

        $('#questions-count').text(questions.length);

        questions.forEach((q, index) => {
            const activeClass = q.id === selectedQuestionId ? 'active' : '';
            const typeLabel = getTypeLabel(q.type);
            
            const itemHtml = `
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center question-item cursor-pointer py-3 ${activeClass}" 
                     data-id="${q.id}"
                     data-order="${q.order}"
                     data-type="${q.type}">
                    <div class="d-flex align-items-center flex-grow-1 min-width-0 me-2">
                        <span class="badge bg-secondary me-2 question-index">${index + 1}</span>
                        <div class="text-truncate flex-grow-1">
                            <strong class="question-text-preview">${escapeHtml(q.question_text)}</strong>
                            <div class="text-muted small">${typeLabel} &bull; ${q.points} ${q.points > 1 ? 'pts' : 'pt'}</div>
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn btn-xs btn-outline-secondary btn-move-up" title="Monter" ${index === 0 ? 'disabled' : ''}>
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button class="btn btn-xs btn-outline-secondary btn-move-down" title="Descendre" ${index === questions.length - 1 ? 'disabled' : ''}>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <button class="btn btn-xs btn-outline-danger btn-delete-question-action" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            $list.append(itemHtml);
        });
    }

    function getTypeLabel(type) {
        switch (type) {
            case 'single_choice': return 'Choix unique';
            case 'multiple_choice': return 'Choix multiples';
            case 'true_false': return 'Vrai/Faux';
            case 'open_text': return 'Texte libre';
            case 'matching': return 'Association';
            case 'fill_in_the_blank': return 'Phrase à trous';
            default: return type;
        }
    }

    // Select question to view
    function selectQuestion(id) {
        selectedQuestionId = id;
        renderQuestionsList(); // Highlight selected
        
        const q = questions.find(item => item.id === id);
        if (!q) return;

        // Populate editor
        $('#editor-placeholder').addClass('d-none');
        const $form = $('#question-form');
        $form.removeClass('d-none');
        
        $('#editor-title').html(`<i class="fas fa-edit"></i> Modifier la question #${questions.indexOf(q) + 1}`);
        $('#question_id').val(q.id);
        $('#question_text').val(q.question_text);
        $('#type').val(q.type).prop('disabled', false); // Allow type change
        $('#points').val(q.points);

        renderOptionsContainer(q.options);
    }

    // Open for Add
    function openEditorForAdd() {
        selectedQuestionId = null;
        renderQuestionsList(); // Clear highlights

        $('#editor-placeholder').addClass('d-none');
        const $form = $('#question-form');
        $form.removeClass('d-none');
        
        $('#editor-title').html('<i class="fas fa-plus"></i> Nouvelle Question');
        $('#question_id').val('');
        $('#question_text').val('');
        $('#type').val('single_choice').prop('disabled', false);
        $('#points').val(1);

        renderOptionsContainer();
    }

    function closeEditor() {
        selectedQuestionId = null;
        renderQuestionsList();
        $('#question-form').addClass('d-none')[0].reset();
        $('#editor-placeholder').removeClass('d-none');
    }

    // Render Options Block dynamically based on selected type
    function renderOptionsContainer(savedOptions = null) {
        const type = $('#type').val();
        const $container = $('#options-container');
        $container.empty();

        if (type === 'single_choice' || type === 'multiple_choice') {
            $('#options-card').show();
            $('#options-title').text('Options possibles (cochez la/les réponse(s) correcte(s))');
            
            $container.append(`
                <div id="choices-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btn-add-choice">
                    <i class="fas fa-plus"></i> Ajouter une option
                </button>
            `);

            if (savedOptions && Array.isArray(savedOptions)) {
                savedOptions.forEach(opt => addChoiceRow(opt.text, opt.is_correct, type === 'single_choice'));
            } else {
                // Add two default empty rows
                addChoiceRow('Option 1', true, type === 'single_choice');
                addChoiceRow('Option 2', false, type === 'single_choice');
            }
        } 
        else if (type === 'true_false') {
            $('#options-card').show();
            $('#options-title').text('Réponse correcte');
            
            const isTrue = savedOptions && savedOptions.correct === 'true';
            const isFalse = savedOptions && savedOptions.correct === 'false';

            $container.append(`
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tf_correct" id="tf_true" value="true" ${isTrue || !savedOptions ? 'checked' : ''}>
                        <label class="form-check-label cursor-pointer" for="tf_true">
                            Vrai
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tf_correct" id="tf_false" value="false" ${isFalse ? 'checked' : ''}>
                        <label class="form-check-label cursor-pointer" for="tf_false">
                            Faux
                        </label>
                    </div>
                </div>
            `);
        } 
        else if (type === 'matching') {
            $('#options-card').show();
            $('#options-title').text('Définition des associations (A -> B)');
            
            $container.append(`
                <div id="pairs-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btn-add-pair">
                    <i class="fas fa-plus"></i> Ajouter une association
                </button>
            `);

            if (savedOptions && Array.isArray(savedOptions)) {
                savedOptions.forEach(pair => addPairRow(pair.left, pair.right));
            } else {
                addPairRow('Elément A1', 'Elément B1');
                addPairRow('Elément A2', 'Elément B2');
            }
        } 
        else if (type === 'fill_in_the_blank') {
            $('#options-card').show();
            $('#options-title').text('Réponses attendues pour les trous');
            
            const textValue = savedOptions && Array.isArray(savedOptions) ? savedOptions.join(', ') : '';

            $container.append(`
                <div class="form-group">
                    <label class="form-label" for="blank_answers">Mots corrects (dans l'ordre, séparés par des virgules)</label>
                    <input type="text" class="form-control" id="blank_answers" value="${textValue}" placeholder="ex: chat, chien, oiseau">
                    <small class="form-text text-muted">Exemple de question: "Le [blank] fait meow et le [blank] fait woof."</small>
                </div>
            `);
        } 
        else {
            // open_text
            $('#options-card').hide();
        }
    }

    // Helper: Add Choice Row (for QCM)
    function addChoiceRow(text = '', isCorrect = false, isSingle = true) {
        const $list = $('#choices-list');
        const index = $('.choice-row').length;
        const inputType = isSingle ? 'radio' : 'checkbox';
        const checked = isCorrect ? 'checked' : '';
        const inputClass = isSingle ? 'single-choice-radio' : '';

        const rowHtml = `
            <div class="row g-2 align-items-center mb-2 choice-row">
                <div class="col-auto">
                    <input class="form-check-input ${inputClass}" type="${inputType}" name="choice_correct" ${checked} style="transform: scale(1.2);">
                </div>
                <div class="col">
                    <input type="text" class="form-control form-control-sm choice-text" value="${escapeHtml(text)}" placeholder="Texte de l'option..." required>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-choice-row" title="Supprimer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        $list.append(rowHtml);
    }

    // Helper: Add Matching Pair Row
    function addPairRow(left = '', right = '') {
        const $list = $('#pairs-list');
        const rowHtml = `
            <div class="row g-2 align-items-center mb-2 pair-row">
                <div class="col">
                    <input type="text" class="form-control form-control-sm pair-left" value="${escapeHtml(left)}" placeholder="Elément de gauche (ex: Chat)" required>
                </div>
                <div class="col-auto text-muted"><i class="fas fa-long-arrow-alt-right"></i></div>
                <div class="col">
                    <input type="text" class="form-control form-control-sm pair-right" value="${escapeHtml(right)}" placeholder="Elément associé (ex: Meow)" required>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-pair-row" title="Supprimer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        $list.append(rowHtml);
    }

    // Save Question (Store or Update)
    function saveQuestion() {
        const id = $('#question_id').val();
        const type = $('#type').val();
        const questionText = $('#question_text').val();
        const points = $('#points').val();

        // Format options based on type
        let options = null;

        if (type === 'single_choice' || type === 'multiple_choice') {
            options = [];
            const $rows = $('.choice-row');
            if ($rows.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Attention', text: 'Vous devez ajouter au moins une option.' });
                return;
            }
            let hasCorrect = false;
            $rows.each(function () {
                const text = $(this).find('.choice-text').val().trim();
                const isCorrect = $(this).find('.form-check-input').is(':checked');
                if (isCorrect) hasCorrect = true;
                options.push({ text, is_correct: isCorrect });
            });

            if (!hasCorrect) {
                Swal.fire({ icon: 'warning', title: 'Attention', text: 'Veuillez désigner au moins une réponse comme correcte.' });
                return;
            }
        } 
        else if (type === 'true_false') {
            const correctVal = $('input[name="tf_correct"]:checked').val();
            options = { correct: correctVal };
        } 
        else if (type === 'matching') {
            options = [];
            const $rows = $('.pair-row');
            if ($rows.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Attention', text: 'Vous devez ajouter au moins une association.' });
                return;
            }
            $rows.each(function () {
                const left = $(this).find('.pair-left').val().trim();
                const right = $(this).find('.pair-right').val().trim();
                options.push({ left, right });
            });
        } 
        else if (type === 'fill_in_the_blank') {
            const answersText = $('#blank_answers').val().trim();
            if (answersText === '') {
                Swal.fire({ icon: 'warning', title: 'Attention', text: 'Veuillez saisir au moins un mot de réponse.' });
                return;
            }
            options = answersText.split(',').map(item => item.trim()).filter(item => item !== '');
        }

        const payload = {
            question_text: questionText,
            type: type,
            points: points,
            options: options
        };

        const url = id 
            ? route('cores.quizzes.questions.update', { id: window.quizId, questionId: id })
            : route('cores.quizzes.questions.store', { id: window.quizId });

        const method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            method: method,
            data: JSON.stringify(payload),
            contentType: 'application/json',
            beforeSend: () => {
                $('#btn-save-question').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enregistrement...');
            },
            success: (response) => {
                if (response.success) {
                    if (id) {
                        // Update local array element
                        const index = questions.findIndex(item => item.id === parseInt(id));
                        if (index !== -1) {
                            questions[index] = response.data;
                        }
                    } else {
                        // Append newly created question
                        questions.push(response.data);
                        selectedQuestionId = response.data.id;
                    }
                    
                    renderQuestionsList();
                    selectQuestion(response.data.id);

                    Swal.fire({
                        icon: 'success',
                        title: 'Enregistré',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            },
            error: (xhr) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: xhr.responseJSON.message || 'Une erreur est survenue lors de la sauvegarde.'
                });
            },
            complete: () => {
                $('#btn-save-question').prop('disabled', false).html('<i class="fas fa-save"></i> Enregistrer la question');
            }
        });
    }

    // Delete question from DB and array
    function deleteQuestion(id) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette question sera supprimée définitivement du quiz !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('cores.quizzes.questions.destroy', { id: window.quizId, questionId: id }),
                    method: 'DELETE',
                    success: (response) => {
                        if (response.success) {
                            questions = questions.filter(item => item.id !== id);
                            
                            if (selectedQuestionId === id) {
                                closeEditor();
                            } else {
                                renderQuestionsList();
                            }
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Supprimée',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: (xhr) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: xhr.responseJSON.message || 'Erreur lors de la suppression.'
                        });
                    }
                });
            }
        });
    }

    // Move Question Up/Down in sorting order
    function moveQuestion(id, direction) {
        const currentIndex = questions.findIndex(item => item.id === id);
        if (currentIndex === -1) return;

        const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
        if (targetIndex < 0 || targetIndex >= questions.length) return;

        // Swap orders in local array
        const tempOrder = questions[currentIndex].order;
        questions[currentIndex].order = questions[targetIndex].order;
        questions[targetIndex].order = tempOrder;

        // Swap array items to trigger re-rendering in correct sequence
        const tempItem = questions[currentIndex];
        questions[currentIndex] = questions[targetIndex];
        questions[targetIndex] = tempItem;

        renderQuestionsList();

        // Push new order state to backend via AJAX
        const payload = {
            question_ids: questions.map(q => q.id)
        };

        $.ajax({
            url: route('cores.quizzes.questions.reorder', { id: window.quizId }),
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            success: (response) => {
                if (!response.success) {
                    console.error("Failed to persist order swap.");
                }
            },
            error: (xhr) => {
                console.error("AJAX Error during reorder request:", xhr);
            }
        });
    }

    // Escape HTML strings for XSS prevention in previews
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
