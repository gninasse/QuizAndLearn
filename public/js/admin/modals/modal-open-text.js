/**
 * modal-open-text.js
 * Handles client-side logic for the Open Text question type modal.
 */
(function($) {
  $(function() {
    var $modal = $('#modalOpenText');
    var $form = $('#formOpenText');
    var $promptTextarea = $('#otQuestionPrompt');
    var $pointsInput = $('#otPointsInput');
    var $maxCharsInput = $('#otMaxCharsInput');
    var $requiredInput = $('#otRequiredInput');
    
    // Preview fields
    var $previewPrompt = $('#otPreviewPrompt');
    var $previewPointsBadge = $('#otPreviewPointsBadge');
    var $previewCounter = $('#otPreviewCounter');
    var $previewNumBadge = $('#otPreviewNumBadge');

    // Sync preview prompt
    function syncPromptPreview() {
      var text = $promptTextarea.val().trim();
      if (text) {
        $previewPrompt.html(text.replace(/\n/g, '<br>'));
      } else {
        $previewPrompt.html('<span class="text-muted italic" style="font-style: italic;">L\'énoncé de la question s\'affichera ici en temps réel...</span>');
      }
    }

    // Sync preview points
    function syncPointsPreview() {
      var points = $pointsInput.val() || 0;
      $previewPointsBadge.text(points + ' points');
    }

    // Sync preview character limit
    function syncMaxCharsPreview() {
      var maxChars = $maxCharsInput.val() || 500;
      $previewCounter.text('0 / ' + maxChars + ' caractères');
    }

    // Hook inputs to sync function
    $promptTextarea.on('input', function() {
      syncPromptPreview();
    });

    $pointsInput.on('input change', function() {
      syncPointsPreview();
    });

    $maxCharsInput.on('input change', function() {
      syncMaxCharsPreview();
    });

    // WYSIWYG button commands wrapping
    $modal.on('click', '.wysiwyg-btn', function() {
      var cmd = $(this).data('cmd');
      window.insertWysiwygTag($promptTextarea, cmd);
    });

    // Question type switcher change
    $modal.on('change', '.question-type-switcher', function() {
      var newType = $(this).val();
      if (newType !== 'open_text') {
        $modal.modal('hide');
        $(document).trigger('open-question-modal', { type: newType, action: 'create' });
      }
    });

    // open-question-modal Event Listener
    $(document).on('open-question-modal', function(e, data) {
      if (data.type !== 'open_text') return;
      
      var quizId = $('#questionsList').data('quiz-id');
      $modal.find('.question-type-switcher').val('open_text');
      
      if (data.action === 'create') {
        $modal.find('.modal-title-text').text('Créer une Question Texte libre');
        $form.attr('data-question-id', '');
        $promptTextarea.val('');
        $pointsInput.val(10);
        $maxCharsInput.val(500);
        $requiredInput.prop('checked', true);
        
        // Update question number in preview dynamically
        var count = $('#questionsList').find('.question-item').length + 1;
        $previewNumBadge.text('QUESTION ' + count);
        
        syncPromptPreview();
        syncPointsPreview();
        syncMaxCharsPreview();
        
        $modal.modal('show');
      } 
      else if (data.action === 'edit' && data.id) {
        $modal.find('.modal-title-text').text('Modifier la Question Texte libre');
        $form.attr('data-question-id', data.id);
        
        // Find existing index in UI
        var index = $('#questionsList').find('.question-item[data-id="' + data.id + '"]').find('.question-num').text();
        $previewNumBadge.text('QUESTION ' + (index || 1));

        $.ajax({
          url: '/admin/quizzes/' + quizId + '/questions/' + data.id,
          method: 'GET',
          success: function(response) {
            if (response.success && response.data) {
              var q = response.data;
              $promptTextarea.val(q.question_text);
              $pointsInput.val(q.points);
              
              var maxChars = 500;
              var required = true;
              
              if (q.options) {
                maxChars = q.options.max_characters || 500;
                required = typeof q.options.required !== 'undefined' ? q.options.required : true;
              }
              
              $maxCharsInput.val(maxChars);
              $requiredInput.prop('checked', required);
              
              syncPromptPreview();
              syncPointsPreview();
              syncMaxCharsPreview();
              
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
    $('#btnSaveOpenText').on('click', function(e) {
      e.preventDefault();
      var quizId = $('#questionsList').data('quiz-id');
      var qId = $form.attr('data-question-id');
      var promptVal = $promptTextarea.val().trim();
      var pointsVal = $pointsInput.val();
      var maxCharsVal = $maxCharsInput.val();
      var requiredVal = $requiredInput.is(':checked');
      
      if (!promptVal) {
        showToast('warning', 'L\'énoncé de la question est obligatoire.');
        return;
      }

      var url = '/admin/quizzes/' + quizId + '/questions';
      var method = 'POST';
      
      if (qId) {
        url = url + '/' + qId;
        method = 'PUT';
      }

      var payload = {
        question_text: promptVal,
        points: pointsVal,
        type: 'open_text',
        options: {
          max_characters: maxCharsVal,
          required: requiredVal
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
