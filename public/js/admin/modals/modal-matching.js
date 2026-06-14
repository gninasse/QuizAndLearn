/**
 * modal-matching.js
 * Handles client-side logic for the Matching question type modal.
 */
(function($) {
  $(function() {
    var $modal = $('#modalMatching');
    var $form = $('#formMatching');
    var $promptTextarea = $('#mtQuestionPrompt');
    var $pointsInput = $('#mtPointsInput');
    var $groupSelect = $('#mtGroupSelect');
    var $pairsList = $('#matchingPairsList');
    
    var sortablePairs = null;

    // Initialize SortableJS on the pairs list
    function initSortablePairs() {
      if ($pairsList.length && typeof Sortable !== 'undefined' && !sortablePairs) {
        sortablePairs = new Sortable($pairsList[0], {
          handle: '.matching-pair-handle',
          animation: 150,
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen'
        });
      }
    }

    // Helper to generate a new pair row HTML
    function getPairRowHtml(term, definition) {
      term = term || '';
      definition = definition || '';
      return '<div class="matching-pair-row">' +
        '<div class="matching-pair-handle" title="Faire glisser pour réordonner">' +
          '<i class="bi bi-grid-3x2-gap-fill"></i>' +
        '</div>' +
        '<input type="text" class="form-control form-control-sm pair-term" placeholder="Terme..." value="' + term + '" required style="border-radius: 6px;">' +
        '<span class="text-muted"><i class="bi bi-arrow-right"></i></span>' +
        '<input type="text" class="form-control form-control-sm pair-definition" placeholder="Définition..." value="' + definition + '" required style="border-radius: 6px;">' +
        '<button type="button" class="btn btn-sm btn-link text-danger btn-delete-pair" title="Supprimer cette paire">' +
          '<i class="bi bi-trash-fill"></i>' +
        '</button>' +
      '</div>';
    }

    // Add empty pair row
    $('#btnAddMatchingPair').on('click', function(e) {
      e.preventDefault();
      $pairsList.append(getPairRowHtml());
    });

    // Delete pair row
    $pairsList.on('click', '.btn-delete-pair', function(e) {
      e.preventDefault();
      $(this).closest('.matching-pair-row').remove();
    });

    // Shuffle array helper
    function shuffleArray(array) {
      var arr = array.slice();
      for (var i = arr.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = arr[i];
        arr[i] = arr[j];
        arr[j] = temp;
      }
      return arr;
    }

    // Shuffle Preview Action
    $('#btnShufflePairsPreview').on('click', function(e) {
      e.preventDefault();
      
      var terms = [];
      var definitions = [];
      var valid = true;
      
      $pairsList.find('.matching-pair-row').each(function() {
        var term = $(this).find('.pair-term').val().trim();
        var def = $(this).find('.pair-definition').val().trim();
        
        if (term && def) {
          terms.push(term);
          definitions.push(def);
        } else {
          valid = false;
        }
      });
      
      if (!valid || terms.length < 2) {
        showToast('warning', 'Veuillez saisir au moins 2 paires complètes pour générer l\'aperçu.');
        return;
      }
      
      // Shuffle the definitions list
      var shuffledDefinitions = shuffleArray(definitions);
      
      // Build premium modal preview table using Swal
      var tableRowsHtml = '';
      terms.forEach(function(term, i) {
        tableRowsHtml += '<tr>' +
          '<td class="text-start fw-bold" style="padding: 10px; border-bottom: 1px solid #eee;">' + term + '</td>' +
          '<td style="padding: 10px; border-bottom: 1px solid #eee;"><i class="bi bi-arrow-right-left text-muted"></i></td>' +
          '<td class="text-start" style="padding: 10px; border-bottom: 1px solid #eee; background-color: var(--green-xlight);">' + shuffledDefinitions[i] + '</td>' +
        '</tr>';
      });
      
      var previewHtml = '<p class="text-muted text-start" style="font-size:0.85rem; margin-bottom: 1rem;">Voici comment les éléments seront mélangés pour l\'apprenant :</p>' +
        '<table class="w-100" style="font-size:0.85rem; border-collapse: collapse;">' +
          '<thead>' +
            '<tr style="background-color: var(--green-light); color: var(--green-dark);">' +
              '<th class="text-start" style="padding: 10px;">Terme de gauche</th>' +
              '<th style="padding: 10px; width: 40px;"></th>' +
              '<th class="text-start" style="padding: 10px;">Aperçu mélangé à droite</th>' +
            '</tr>' +
          '</thead>' +
          '<tbody>' + tableRowsHtml + '</tbody>' +
        '</table>';
        
      Swal.fire({
        title: 'Aperçu du mélange',
        html: previewHtml,
        confirmButtonColor: 'var(--green-dark)',
        confirmButtonText: 'Fermer',
        customClass: {
          popup: 'rounded-4'
        }
      });
    });

    // WYSIWYG button commands wrapping
    $modal.on('click', '.wysiwyg-btn', function() {
      var cmd = $(this).data('cmd');
      window.insertWysiwygTag($promptTextarea, cmd);
    });

    // Question type switcher change
    $modal.on('change', '.question-type-switcher', function() {
      var newType = $(this).val();
      if (newType !== 'matching') {
        $modal.modal('hide');
        $(document).trigger('open-question-modal', { type: newType, action: 'create' });
      }
    });

    // open-question-modal Event Listener
    $(document).on('open-question-modal', function(e, data) {
      if (data.type !== 'matching') return;
      
      var quizId = $('#questionsList').data('quiz-id');
      $modal.find('.question-type-switcher').val('matching');
      initSortablePairs();
      
      if (data.action === 'create') {
        $modal.find('.modal-title-text').text('Créer une Question d\'appariement');
        $form.attr('data-question-id', '');
        $promptTextarea.val('');
        $pointsInput.val(10);
        $groupSelect.val('general');
        
        // Setup 2 default empty rows
        $pairsList.empty();
        $pairsList.append(getPairRowHtml());
        $pairsList.append(getPairRowHtml());
        
        $modal.modal('show');
      } 
      else if (data.action === 'edit' && data.id) {
        $modal.find('.modal-title-text').text('Modifier la Question d\'appariement');
        $form.attr('data-question-id', data.id);
        
        $.ajax({
          url: '/admin/quizzes/' + quizId + '/questions/' + data.id,
          method: 'GET',
          success: function(response) {
            if (response.success && response.data) {
              var q = response.data;
              $promptTextarea.val(q.question_text);
              $pointsInput.val(q.points);
              
              var group = 'general';
              var pairs = [];
              
              if (q.options) {
                group = q.options.group || 'general';
                pairs = q.options.pairs || [];
              }
              
              $groupSelect.val(group);
              
              // Load rows
              $pairsList.empty();
              if (pairs.length > 0) {
                pairs.forEach(function(pair) {
                  $pairsList.append(getPairRowHtml(pair.term, pair.definition));
                });
              } else {
                $pairsList.append(getPairRowHtml());
                $pairsList.append(getPairRowHtml());
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
    $('#btnSaveMatching').on('click', function(e) {
      e.preventDefault();
      var quizId = $('#questionsList').data('quiz-id');
      var qId = $form.attr('data-question-id');
      var promptVal = $promptTextarea.val().trim();
      var pointsVal = $pointsInput.val();
      
      if (!promptVal) {
        showToast('warning', 'L\'énoncé de la question est obligatoire.');
        return;
      }
      
      var pairs = [];
      var valid = true;
      
      $pairsList.find('.matching-pair-row').each(function() {
        var term = $(this).find('.pair-term').val().trim();
        var def = $(this).find('.pair-definition').val().trim();
        
        if (term || def) {
          if (term && def) {
            pairs.push({ term: term, definition: def });
          } else {
            valid = false; // pair is incomplete (only one field filled)
          }
        }
      });
      
      if (!valid) {
        showToast('warning', 'Veuillez remplir à la fois le terme et sa définition pour toutes les paires saisies.');
        return;
      }
      
      if (pairs.length < 2) {
        showToast('warning', 'Vous devez définir au moins 2 paires d\'appariement complètes.');
        return;
      }

      var url = '/admin/quizzes/' + quizId + '/questions';
      var method = 'POST';
      
      if (qId) {
        url = url + '/' + qId;
        method = 'PUT';
      }

      // Compile matching options payload
      var payload = {
        question_text: promptVal,
        points: pointsVal,
        type: 'matching',
        options: {
          group: $groupSelect.val(),
          pairs: pairs
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
