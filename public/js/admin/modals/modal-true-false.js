/**
 * modal-true-false.js
 * Handles client-side logic for the True/False question type modal.
 */
(function($) {
  $(function() {
    var $modal = $('#modalTrueFalse');
    var $form = $('#formTrueFalse');
    var $correctAnswerHidden = $('#tfCorrectAnswer');
    var $promptTextarea = $('#tfQuestionPrompt');
    var $pointsInput = $('#tfPointsInput');
    
    // 1. Toggle correct answer cards
    $modal.on('click', '.tf-answer-card', function() {
      var val = $(this).data('value');
      
      // Reset all cards style
      $modal.find('.tf-answer-card').removeClass('selected');
      $modal.find('.tf-answer-card h5').removeClass('text-success').addClass('text-muted');
      $modal.find('.tf-answer-card .tf-icon').html('<i class="bi bi-circle fs-4 text-muted"></i>');
      
      // Apply active style to selected card
      $(this).addClass('selected');
      $(this).find('h5').removeClass('text-muted').addClass('text-success');
      $(this).find('.tf-icon').html('<i class="bi bi-check-circle-fill text-success fs-4"></i>');
      
      // Update hidden input
      $correctAnswerHidden.val(val);
    });

    // 2. Question type switcher change
    $modal.on('change', '.question-type-switcher', function() {
      var newType = $(this).val();
      if (newType !== 'true_false') {
        $modal.modal('hide');
        $(document).trigger('open-question-modal', { type: newType, action: 'create' });
      }
    });

    // 3. Mini-WYSIWYG formatting buttons
    $modal.on('click', '.wysiwyg-btn', function() {
      var cmd = $(this).data('cmd');
      window.insertWysiwygTag($promptTextarea, cmd);
    });

    // 4. Custom event listener to open this modal
    $(document).on('open-question-modal', function(e, data) {
      if (data.type !== 'true_false') {
        return;
      }
      
      var quizId = $('#questionsList').data('quiz-id');
      
      // Always align switcher select
      $modal.find('.question-type-switcher').val('true_false');
      
      if (data.action === 'create') {
        $modal.find('.modal-title-text').text('Ajouter une Question Vrai / Faux');
        $form.attr('data-question-id', '');
        $promptTextarea.val('');
        $pointsInput.val(10);
        $correctAnswerHidden.val('true');
        
        // Reset answer cards selection to True
        $modal.find('.tf-answer-card[data-value="true"]').click();
        
        $modal.modal('show');
      } 
      else if (data.action === 'edit' && data.id) {
        $modal.find('.modal-title-text').text('Modifier la Question Vrai / Faux');
        $form.attr('data-question-id', data.id);
        
        $.ajax({
          url: '/admin/quizzes/' + quizId + '/questions/' + data.id,
          method: 'GET',
          success: function(response) {
            if (response.success && response.data) {
              var q = response.data;
              $promptTextarea.val(q.question_text);
              $pointsInput.val(q.points);
              
              var correctVal = 'true';
              if (q.options && typeof q.options.correct_answer !== 'undefined') {
                correctVal = String(q.options.correct_answer);
              }
              
              $correctAnswerHidden.val(correctVal);
              $modal.find('.tf-answer-card[data-value="' + correctVal + '"]').click();
              
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

    // 5. Submit Question
    $('#btnSaveTrueFalse').on('click', function(e) {
      e.preventDefault();
      var quizId = $('#questionsList').data('quiz-id');
      var qId = $form.attr('data-question-id');
      var promptVal = $promptTextarea.val().trim();
      
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
      
      $.ajax({
        url: url,
        method: method,
        data: $form.serialize(),
        success: function(response) {
          if (response.success && response.data) {
            showToast('success', response.message);
            $modal.modal('hide');
            
            // Dynamically update lists in the parent page workspace
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
