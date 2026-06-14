/**
 * modal-mcq.js
 * Handles client-side logic for the MCQ question type modal.
 */
(function($) {
  $(function() {
    var $modal = $('#modalMcq');
    var $form = $('#formMcq');
    var $promptTextarea = $('#mcqQuestionPrompt');
    var $pointsInput = $('#mcqPointsInput');
    var $groupSelect = $('#mcqGroupSelect');
    var $answersList = $('#mcqAnswersList');
    var $partialScoreWrapper = $('#mcqPartialScoreWrapper');
    var $partialScoreInput = $('#mcqPartialScoreInput');
    
    var sortableAnswers = null;

    // Initialize SortableJS on answers list
    function initSortableAnswers() {
      if ($answersList.length && typeof Sortable !== 'undefined' && !sortableAnswers) {
        sortableAnswers = new Sortable($answersList[0], {
          handle: '.mcq-answer-handle',
          animation: 150,
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen'
        });
      }
    }

    // Helper to generate a new proposition row
    function getAnswerRowHtml(text, isCorrect, isMultiple) {
      text = text || '';
      isCorrect = isCorrect || false;
      var typeAttr = isMultiple ? 'checkbox' : 'radio';
      var checkedAttr = isCorrect ? 'checked' : '';
      var rowClass = isCorrect ? 'mcq-answer-row correct' : 'mcq-answer-row';
      var checkName = isMultiple ? 'mcq_correct[]' : 'mcq_correct';
      
      return '<div class="' + rowClass + '">' +
        '<div class="mcq-answer-handle" title="Faire glisser pour réordonner">' +
          '<i class="bi bi-grid-3x2-gap-fill"></i>' +
        '</div>' +
        '<div class="form-check m-0">' +
          '<input class="form-check-input correct-check" type="' + typeAttr + '" name="' + checkName + '" ' + checkedAttr + ' style="cursor: pointer; width: 20px; height: 20px;">' +
        '</div>' +
        '<input type="text" class="form-control form-control-sm answer-text" placeholder="Proposition de réponse..." value="' + text + '" required style="border-radius: 6px;">' +
        '<button type="button" class="btn btn-sm btn-link text-danger btn-delete-answer" title="Supprimer cette proposition">' +
          '<i class="bi bi-trash-fill"></i>' +
        '</button>' +
      '</div>';
    }

    // MCQ Type Radio change handler (Choix Unique vs Choix Multiple)
    $('input[name="options[multiple]"]').on('change', function() {
      var isMultiple = $(this).val() === 'true';
      
      // Update check inputs type
      $answersList.find('.mcq-answer-row').each(function() {
        var $check = $(this).find('.correct-check');
        var wasChecked = $check.is(':checked');
        
        if (isMultiple) {
          $check.attr('type', 'checkbox').attr('name', 'mcq_correct[]');
        } else {
          $check.attr('type', 'radio').attr('name', 'mcq_correct');
        }
        $check.prop('checked', wasChecked);
      });

      if (isMultiple) {
        $partialScoreWrapper.slideDown();
      } else {
        $partialScoreWrapper.slideUp();
        $partialScoreInput.prop('checked', false);
        
        // Ensure only one remains checked in single-choice mode
        var checkCount = 0;
        $answersList.find('.mcq-answer-row').each(function() {
          var $check = $(this).find('.correct-check');
          if ($check.is(':checked')) {
            checkCount++;
            if (checkCount > 1) {
              $check.prop('checked', false);
              $(this).removeClass('correct');
            }
          }
        });
      }
    });

    // Checkbox/Radio click handler inside rows to apply background highlight
    $answersList.on('change', '.correct-check', function() {
      var isMultiple = $('input[name="options[multiple]"]:checked').val() === 'true';
      var $row = $(this).closest('.mcq-answer-row');
      
      if (isMultiple) {
        if ($(this).is(':checked')) {
          $row.addClass('correct');
        } else {
          $row.removeClass('correct');
        }
      } else {
        // Unmark all others
        $answersList.find('.mcq-answer-row').removeClass('correct');
        if ($(this).is(':checked')) {
          $row.addClass('correct');
        }
      }
    });

    // Add Answer proposition row
    $('#btnAddMcqAnswer').on('click', function(e) {
      e.preventDefault();
      var count = $answersList.find('.mcq-answer-row').length;
      if (count >= 10) {
        showToast('warning', 'Vous ne pouvez pas ajouter plus de 10 propositions.');
        return;
      }
      
      var isMultiple = $('input[name="options[multiple]"]:checked').val() === 'true';
      $answersList.append(getAnswerRowHtml('', false, isMultiple));
    });

    // Delete Answer proposition row
    $answersList.on('click', '.btn-delete-answer', function(e) {
      e.preventDefault();
      var count = $answersList.find('.mcq-answer-row').length;
      if (count <= 2) {
        showToast('warning', 'Un QCM doit posséder au moins 2 propositions.');
        return;
      }
      $(this).closest('.mcq-answer-row').remove();
    });

    // WYSIWYG mini-toolbar wrapping commands
    $modal.on('click', '.wysiwyg-btn', function() {
      var cmd = $(this).data('cmd');
      window.insertWysiwygTag($promptTextarea, cmd);
    });

    // Question type switcher change
    $modal.on('change', '.question-type-switcher', function() {
      var newType = $(this).val();
      if (newType !== 'mcq') {
        $modal.modal('hide');
        $(document).trigger('open-question-modal', { type: newType, action: 'create' });
      }
    });

    // open-question-modal Event Listener
    $(document).on('open-question-modal', function(e, data) {
      if (data.type !== 'mcq') return;
      
      var quizId = $('#questionsList').data('quiz-id');
      $modal.find('.question-type-switcher').val('mcq');
      initSortableAnswers();
      
      if (data.action === 'create') {
        $modal.find('.modal-title-text').text('Créer une Question à Choix Multiple (QCM)');
        $form.attr('data-question-id', '');
        $promptTextarea.val('');
        $pointsInput.val(10);
        $groupSelect.val('general');
        $partialScoreInput.prop('checked', false);
        $partialScoreWrapper.hide();
        
        // Single choice by default
        $('#mcqTypeSingle').prop('checked', true).trigger('change');
        
        // Add 4 default empty rows
        $answersList.empty();
        $answersList.append(getAnswerRowHtml('', false, false));
        $answersList.append(getAnswerRowHtml('', false, false));
        $answersList.append(getAnswerRowHtml('', false, false));
        $answersList.append(getAnswerRowHtml('', false, false));
        
        $modal.modal('show');
      } 
      else if (data.action === 'edit' && data.id) {
        $modal.find('.modal-title-text').text('Modifier la Question à Choix Multiple (QCM)');
        $form.attr('data-question-id', data.id);
        
        $.ajax({
          url: '/admin/quizzes/' + quizId + '/questions/' + data.id,
          method: 'GET',
          success: function(response) {
            if (response.success && response.data) {
              var q = response.data;
              $promptTextarea.val(q.question_text);
              $pointsInput.val(q.points);
              
              var multiple = false;
              var partialScore = false;
              var group = 'general';
              var answers = [];
              
              if (q.options) {
                multiple = q.options.multiple === true || q.options.multiple === 'true';
                partialScore = q.options.partial_score === true || q.options.partial_score === 'true';
                group = q.options.group || 'general';
                answers = q.options.answers || [];
              }
              
              $groupSelect.val(group);
              $partialScoreInput.prop('checked', partialScore);
              
              if (multiple) {
                $('#mcqTypeMultiple').prop('checked', true);
                $partialScoreWrapper.show();
              } else {
                $('#mcqTypeSingle').prop('checked', true);
                $partialScoreWrapper.hide();
              }
              
              // Load answers list
              $answersList.empty();
              if (answers.length > 0) {
                answers.forEach(function(ans) {
                  $answersList.append(getAnswerRowHtml(ans.text, ans.is_correct, multiple));
                });
              } else {
                $answersList.append(getAnswerRowHtml('', false, multiple));
                $answersList.append(getAnswerRowHtml('', false, multiple));
              }
              
              $modal.modal('show');
            } else {
              showToast('error', response.message || 'Impossible de charger les données de la question.');
            }
          },
          error: function() {
            showToast('error', 'Erreur réseau lors du chargement de la question.');
          }
        });
      }
    });

    // Save Question Handler
    $('#btnSaveMcq').on('click', function(e) {
      e.preventDefault();
      var quizId = $('#questionsList').data('quiz-id');
      var qId = $form.attr('data-question-id');
      var promptVal = $promptTextarea.val().trim();
      var pointsVal = $pointsInput.val();
      
      if (!promptVal) {
        showToast('warning', 'L\'énoncé de la question est obligatoire.');
        return;
      }
      
      var answers = [];
      var valid = true;
      var correctCount = 0;
      var multiple = $('#mcqTypeMultiple').is(':checked');
      
      $answersList.find('.mcq-answer-row').each(function() {
        var text = $(this).find('.answer-text').val().trim();
        var isCorrect = $(this).find('.correct-check').is(':checked');
        
        if (text) {
          answers.push({ text: text, is_correct: isCorrect });
          if (isCorrect) {
            correctCount++;
          }
        } else {
          valid = false;
        }
      });
      
      if (!valid) {
        showToast('warning', 'Veuillez saisir du texte pour toutes les propositions de réponse.');
        return;
      }
      
      if (answers.length < 2) {
        showToast('warning', 'Un QCM doit posséder au moins 2 propositions de réponse.');
        return;
      }
      
      if (correctCount === 0) {
        showToast('warning', 'Veuillez désigner au moins une bonne réponse.');
        return;
      }
      
      if (!multiple && correctCount > 1) {
        showToast('warning', 'En mode Choix Unique, une seule proposition de réponse peut être correcte.');
        return;
      }

      var url = '/admin/quizzes/' + quizId + '/questions';
      var method = 'POST';
      
      if (qId) {
        url = url + '/' + qId;
        method = 'PUT';
      }

      // Compile MCQ options payload
      var payload = {
        question_text: promptVal,
        points: pointsVal,
        type: 'mcq',
        options: {
          multiple: multiple,
          partial_score: multiple ? $partialScoreInput.is(':checked') : false,
          group: $groupSelect.val(),
          answers: answers
        }
      };

      $.ajax({
        url: url,
        method: method,
        data: payload,
        success: function(response) {
          if (response.success && response.data) {
            showToast('success', response.message);
            $modal.modal('hide');
            
            // Dynamically update UI list in the parent page workspace
            window.addOrUpdateQuestionInList(response.data);
          } else {
            showToast('error', response.message || 'Erreur lors de la sauvegarde.');
          }
        },
        error: function(xhr) {
          var errorMsg = xhr.responseJSON && xhr.responseJSON.message 
            ? xhr.responseJSON.message 
            : 'Erreur lors de l\'enregistrement de la question.';
          showToast('error', errorMsg);
        }
      });
    });

  });
})(jQuery);
