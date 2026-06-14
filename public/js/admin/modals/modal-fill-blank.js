/**
 * modal-fill-blank.js
 * Handles client-side logic for the Fill-in-the-blank question type modal.
 */
(function($) {
  $(function() {
    var $modal = $('#modalFillBlank');
    var $form = $('#formFillBlank');
    var $promptTextarea = $('#fbQuestionPrompt');
    var $pointsInput = $('#fbPointsInput');
    var $titleInput = $('#fbTitleInput');
    var $listContainer = $('#fbAnswerDefinitionsList');
    var $placeholder = $('#fbNoBlanksPlaceholder');
    
    // Local state to keep track of blanks: array of { answers: [], case_sensitive: false }
    var blanksState = [];

    // Helper to count [blank] occurrences
    function countBlanks(text) {
      return (text.match(/\[blank\]/g) || []).length;
    }

    // Helper to replace the Nth occurrence of [blank]
    function removeNthBlank(text, targetIndex) {
      var currentIndex = 0;
      return text.replace(/\[blank\]/g, function(match) {
        if (currentIndex === targetIndex) {
          currentIndex++;
          return '';
        }
        currentIndex++;
        return match;
      });
    }

    // Render tags list for a single blank line
    function renderTagsForLine(blankIndex) {
      var $tagsList = $('.blank-tags-list[data-index="' + blankIndex + '"]');
      $tagsList.empty();
      
      var answers = blanksState[blankIndex].answers || [];
      answers.forEach(function(ans, tagIndex) {
        var tagHtml = '<span class="blank-tag">' +
          '<span>' + ans + '</span>' +
          '<span class="blank-tag-remove" data-blank="' + blankIndex + '" data-tag="' + tagIndex + '"><i class="bi bi-x-circle-fill"></i></span>' +
          '</span>';
        $tagsList.append(tagHtml);
      });
    }

    // Render the list of definition rows
    function renderBlankRows() {
      if (blanksState.length === 0) {
        $placeholder.show();
        $listContainer.empty();
        return;
      }
      
      $placeholder.hide();
      
      // We rebuild the list while preserving existing DOM elements if possible,
      // or we just render them cleanly. Re-rendering is fast enough.
      $listContainer.empty();
      
      blanksState.forEach(function(blank, idx) {
        var isChecked = blank.case_sensitive ? 'checked' : '';
        var rowHtml = '<div class="blank-definition-line" data-index="' + idx + '">' +
          '<div class="d-flex align-items-center justify-content-between mb-2">' +
            '<div class="d-flex align-items-center gap-2">' +
              '<span class="blank-num-badge">#' + (idx + 1) + '</span>' +
              '<span class="fw-bold" style="font-size: 0.9rem; color: var(--green-dark);">Trou #' + (idx + 1) + '</span>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-blank" data-index="' + idx + '" title="Supprimer ce trou de l\'énoncé" style="border-radius: 6px; padding: 2px 8px;">' +
              '<i class="bi bi-trash"></i>' +
            '</button>' +
          '</div>' +
          '<div class="row align-items-center g-2">' +
            '<div class="col-sm-7">' +
              '<div class="input-group input-group-sm">' +
                '<input type="text" class="form-control blank-answer-input" data-index="' + idx + '" placeholder="Ajouter une réponse acceptée..." style="border-radius: 6px 0 0 6px;">' +
                '<button class="btn btn-success btn-add-answer" type="button" data-index="' + idx + '" style="border-radius: 0 6px 6px 0; background-color: var(--green-mid); border: none;">' +
                  '<i class="bi bi-plus-lg"></i>' +
                '</button>' +
              '</div>' +
            '</div>' +
            '<div class="col-sm-5">' +
              '<div class="form-check form-switch">' +
                '<input class="form-check-input blank-case-checkbox" type="checkbox" role="switch" id="caseSensitiveSwitch' + idx + '" data-index="' + idx + '" ' + isChecked + '>' +
                '<label class="form-check-label" for="caseSensitiveSwitch' + idx + '" style="font-size: 0.85rem;">Sensible à la casse</label>' +
              '</div>' +
            '</div>' +
          '</div>' +
          '<div class="blank-tags-list" data-index="' + idx + '"></div>' +
        '</div>';
        
        $listContainer.append(rowHtml);
        renderTagsForLine(idx);
      });
    }

    // Sync state and UI with textarea [blank] count
    function syncBlanks() {
      var text = $promptTextarea.val();
      var count = countBlanks(text);
      
      var stateChanged = false;
      
      if (count > blanksState.length) {
        while (blanksState.length < count) {
          blanksState.push({ answers: [], case_sensitive: false });
        }
        stateChanged = true;
      } 
      else if (count < blanksState.length) {
        blanksState = blanksState.slice(0, count);
        stateChanged = true;
      }
      
      if (stateChanged || $listContainer.children().length !== count) {
        renderBlankRows();
      }
    }

    // Input handler in textarea
    $promptTextarea.on('input', function() {
      syncBlanks();
    });

    // Insert [blank] button handler
    $('#fbInsertBlankBtn').on('click', function(e) {
      e.preventDefault();
      var el = $promptTextarea[0];
      var start = el.selectionStart;
      var end = el.selectionEnd;
      var text = el.value;
      var replacement = '[blank]';
      
      el.value = text.substring(0, start) + replacement + text.substring(end);
      el.focus();
      el.selectionStart = start + replacement.length;
      el.selectionEnd = start + replacement.length;
      
      $promptTextarea.trigger('input');
    });

    // Command WYSIWYG
    $modal.on('click', '.wysiwyg-btn', function() {
      var cmd = $(this).data('cmd');
      window.insertWysiwygTag($promptTextarea, cmd);
    });

    // Switcher type question
    $modal.on('change', '.question-type-switcher', function() {
      var newType = $(this).val();
      if (newType !== 'fill_blank') {
        $modal.modal('hide');
        $(document).trigger('open-question-modal', { type: newType, action: 'create' });
      }
    });

    // Delete blank icon handler
    $listContainer.on('click', '.btn-delete-blank', function(e) {
      e.preventDefault();
      var idx = $(this).data('index');
      var text = $promptTextarea.val();
      var newText = removeNthBlank(text, idx);
      
      $promptTextarea.val(newText);
      $promptTextarea.trigger('input');
    });

    // Add answer tags handlers
    function addAnswer(idx) {
      var $input = $('.blank-answer-input[data-index="' + idx + '"]');
      var ans = $input.val().trim();
      
      if (!ans) return;
      
      if (!blanksState[idx].answers) {
        blanksState[idx].answers = [];
      }
      
      // Avoid duplicate tags
      if (blanksState[idx].answers.indexOf(ans) === -1) {
        blanksState[idx].answers.push(ans);
        renderTagsForLine(idx);
      }
      
      $input.val('');
    }

    $listContainer.on('click', '.btn-add-answer', function(e) {
      e.preventDefault();
      var idx = $(this).data('index');
      addAnswer(idx);
    });

    $listContainer.on('keypress', '.blank-answer-input', function(e) {
      if (e.which === 13) {
        e.preventDefault();
        var idx = $(this).data('index');
        addAnswer(idx);
      }
    });

    // Remove answer tag handler
    $listContainer.on('click', '.blank-tag-remove', function() {
      var blankIdx = $(this).data('blank');
      var tagIdx = $(this).data('tag');
      
      blanksState[blankIdx].answers.splice(tagIdx, 1);
      renderTagsForLine(blankIdx);
    });

    // Checkbox case-sensitive state change
    $listContainer.on('change', '.blank-case-checkbox', function() {
      var idx = $(this).data('index');
      blanksState[idx].case_sensitive = $(this).is(':checked');
    });

    // open-question-modal Event Listener
    $(document).on('open-question-modal', function(e, data) {
      if (data.type !== 'fill_blank') return;
      
      var quizId = $('#questionsList').data('quiz-id');
      $modal.find('.question-type-switcher').val('fill_blank');
      
      if (data.action === 'create') {
        $modal.find('.modal-title-text').text('Ajouter une Question à trous');
        $form.attr('data-question-id', '');
        $promptTextarea.val('');
        $pointsInput.val(10);
        $titleInput.val('');
        blanksState = [];
        
        syncBlanks();
        $modal.modal('show');
      } 
      else if (data.action === 'edit' && data.id) {
        $modal.find('.modal-title-text').text('Modifier la Question à trous');
        $form.attr('data-question-id', data.id);
        
        $.ajax({
          url: '/admin/quizzes/' + quizId + '/questions/' + data.id,
          method: 'GET',
          success: function(response) {
            if (response.success && response.data) {
              var q = response.data;
              $promptTextarea.val(q.question_text);
              $pointsInput.val(q.points);
              
              var title = '';
              var blanks = [];
              if (q.options) {
                title = q.options.title || '';
                blanks = q.options.blanks || [];
              }
              
              $titleInput.val(title);
              blanksState = blanks;
              
              // Render UI rows
              renderBlankRows();
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
    $('#btnSaveFillBlank').on('click', function(e) {
      e.preventDefault();
      var quizId = $('#questionsList').data('quiz-id');
      var qId = $form.attr('data-question-id');
      var promptVal = $promptTextarea.val().trim();
      var pointsVal = $pointsInput.val();
      
      if (!promptVal) {
        showToast('warning', 'L\'énoncé de la question est obligatoire.');
        return;
      }
      
      var count = countBlanks(promptVal);
      if (count === 0) {
        showToast('warning', 'Vous devez définir au moins un trou [blank] dans le texte.');
        return;
      }
      
      // Validate that each blank has at least one answer tag defined
      var missingAnswers = false;
      for (var i = 0; i < blanksState.length; i++) {
        if (!blanksState[i].answers || blanksState[i].answers.length === 0) {
          missingAnswers = true;
          showToast('warning', 'Veuillez saisir au moins une réponse pour le Trou #' + (i + 1));
          break;
        }
      }
      
      if (missingAnswers) return;

      var url = '/admin/quizzes/' + quizId + '/questions';
      var method = 'POST';
      
      if (qId) {
        url = url + '/' + qId;
        method = 'PUT';
      }

      // Serialize form but append computed options payload
      var payload = {
        question_text: promptVal,
        points: pointsVal,
        type: 'fill_blank',
        options: {
          title: $titleInput.val(),
          blanks: blanksState
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
