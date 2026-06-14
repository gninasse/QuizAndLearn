/**
 * modal-ordering.js
 * Handles client-side logic for the Ordering question type modal.
 */
(function($) {
  $(function() {
    var $modal = $('#modalOrdering');
    var $form = $('#formOrdering');
    var $promptTextarea = $('#orQuestionPrompt');
    var $pointsInput = $('#orPointsInput');
    var $titleInput = $('#orTitleInput');
    var $itemsList = $('#orderingItemsList');
    
    var sortableItems = null;

    // Initialize SortableJS on the items list
    function initSortableItems() {
      if ($itemsList.length && typeof Sortable !== 'undefined' && !sortableItems) {
        sortableItems = new Sortable($itemsList[0], {
          handle: '.ordering-item-handle',
          animation: 150,
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen',
          onEnd: function() {
            updateItemIndexes();
          }
        });
      }
    }

    // Helper to generate a new item row HTML
    function getItemRowHtml(content) {
      content = content || '';
      return '<div class="ordering-item-row">' +
        '<div class="ordering-item-handle" title="Faire glisser pour réordonner">' +
          '<i class="bi bi-grid-3x2-gap-fill"></i>' +
        '</div>' +
        '<span class="ordering-item-num"></span>' +
        '<input type="text" class="form-control form-control-sm ordering-item-input" placeholder="Saisir la proposition..." value="' + content + '" required style="border-radius: 6px;">' +
        '<button type="button" class="btn btn-sm btn-link text-danger btn-delete-item" title="Supprimer cet élément">' +
          '<i class="bi bi-trash-fill"></i>' +
        '</button>' +
      '</div>';
    }

    // Update item index numbers dynamically
    function updateItemIndexes() {
      $itemsList.find('.ordering-item-row').each(function(index) {
        $(this).find('.ordering-item-num').text((index + 1) + '.');
      });
    }

    // Add empty item row
    $('#btnAddOrderingItem').on('click', function(e) {
      e.preventDefault();
      $itemsList.append(getItemRowHtml());
      updateItemIndexes();
    });

    // Delete item row
    $itemsList.on('click', '.btn-delete-item', function(e) {
      e.preventDefault();
      $(this).closest('.ordering-item-row').remove();
      updateItemIndexes();
    });

    // Switcher type question
    $modal.on('change', '.question-type-switcher', function() {
      var newType = $(this).val();
      if (newType !== 'ordering') {
        $modal.modal('hide');
        $(document).trigger('open-question-modal', { type: newType, action: 'create' });
      }
    });

    // Command WYSIWYG
    $modal.on('click', '.wysiwyg-btn', function() {
      var cmd = $(this).data('cmd');
      window.insertWysiwygTag($promptTextarea, cmd);
    });

    // open-question-modal Event Listener
    $(document).on('open-question-modal', function(e, data) {
      if (data.type !== 'ordering') return;
      
      var quizId = $('#questionsList').data('quiz-id');
      $modal.find('.question-type-switcher').val('ordering');
      
      initSortableItems();
      
      if (data.action === 'create') {
        $modal.find('.modal-title-text').text('Ajouter une Question d\'ordonnancement');
        $form.attr('data-question-id', '');
        $promptTextarea.val('');
        $pointsInput.val(10);
        $titleInput.val('');
        $itemsList.empty();
        
        // Add 3 default empty items
        $itemsList.append(getItemRowHtml(''));
        $itemsList.append(getItemRowHtml(''));
        $itemsList.append(getItemRowHtml(''));
        updateItemIndexes();
        
        $modal.modal('show');
      } 
      else if (data.action === 'edit' && data.id) {
        $modal.find('.modal-title-text').text('Modifier la Question d\'ordonnancement');
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
              var items = [];
              if (q.options) {
                title = q.options.title || '';
                items = q.options.items || [];
              }
              
              $titleInput.val(title);
              $itemsList.empty();
              if (items.length) {
                items.forEach(function(item) {
                  $itemsList.append(getItemRowHtml(item));
                });
              } else {
                $itemsList.append(getItemRowHtml(''));
                $itemsList.append(getItemRowHtml(''));
              }
              updateItemIndexes();
              $modal.modal('show');
            } else {
              showToast('error', response.message || 'Impossible de charger la question.');
            }
          },
          error: function() {
            showToast('error', 'Erreur réseau lors du chargement de la question.');
          }
        });
      }
    });

    // Save Question Handler
    $('#btnSaveOrdering').on('click', function(e) {
      e.preventDefault();
      var quizId = $('#questionsList').data('quiz-id');
      var qId = $form.attr('data-question-id');
      var promptVal = $promptTextarea.val().trim();
      var pointsVal = $pointsInput.val();
      
      if (!promptVal) {
        showToast('warning', 'L\'énoncé de la question est obligatoire.');
        return;
      }
      
      var items = [];
      var valid = true;
      
      $itemsList.find('.ordering-item-row').each(function() {
        var val = $(this).find('.ordering-item-input').val().trim();
        if (val) {
          items.push(val);
        } else {
          valid = false;
        }
      });
      
      if (!valid) {
        showToast('warning', 'Veuillez remplir toutes les propositions d\'ordonnancement.');
        return;
      }
      
      if (items.length < 2) {
        showToast('warning', 'Vous devez définir au moins 2 propositions à ordonner.');
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
        type: 'ordering',
        options: {
          title: $titleInput.val(),
          items: items
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
