/**
 * quiz-editor.js
 * Handles client-side logic for the Quiz Editor page.
 */
(function($) {
  $(function() {
    var quizId = $('#questionsList').data('quiz-id');
    var $questionsList = $('#questionsList');
    var $groupSearchInput = $('#groupSearchInput');
    var $groupSearchResults = $('#groupSearchResults');
    var $assignedGroupsList = $('#assignedGroupsList');
    var $quizParamsForm = $('#quizParamsForm');
    var $autosaveStatus = $('#autosaveStatus');
    var $btnPublishQuiz = $('#btnPublishQuiz');
    var $btnPublishText = $('#sidebarPublishBtn span, #btnPublishText');
    var $paramIsActive = $('#paramIsActive');
    var $sidebarStatusBadge = $('#sidebarStatusBadge');
    
    // -------------------------------------------------------------
    // 1. Sortable Questions List
    // -------------------------------------------------------------
    if ($questionsList.length && typeof Sortable !== 'undefined') {
      var sortable = new Sortable($questionsList[0], {
        handle: '.question-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function() {
          var questionIds = [];
          $questionsList.find('.question-item').each(function() {
            questionIds.push($(this).data('id'));
          });
          
          // Update order numbers in UI
          $questionsList.find('.question-item').each(function(index) {
            $(this).find('.question-num').text(index + 1);
          });
          
          // Send reorder request to backend
          $.ajax({
            url: '/admin/quizzes/' + quizId + '/reorder',
            method: 'POST',
            data: { question_ids: questionIds },
            success: function(response) {
              if (response.success) {
                showToast('success', response.message || 'Questions réordonnées.');
              } else {
                showToast('error', response.message || 'Erreur lors du réordonnement.');
              }
            },
            error: function() {
              showToast('error', 'Une erreur est survenue lors de la réorganisation.');
            }
          });
        }
      });
    }

    // -------------------------------------------------------------
    // 2. Group Assignment Auto-complete and management
    // -------------------------------------------------------------

    // Placement par groupe (dossiers) et règle du mode isolé : voir group-folder-picker.js
    function afterGroupsChanged() {
      if (window.GroupFolderPicker) {
        GroupFolderPicker.syncSingle($('.group-search-wrapper'), $assignedGroupsList.children('.group-badge-card').length);
      }
    }

    var searchTimeout = null;
    $groupSearchInput.on('input', function() {
      clearTimeout(searchTimeout);
      var query = $(this).val().trim();
      
      if (query.length < 2) {
        $groupSearchResults.hide().empty();
        return;
      }
      
      searchTimeout = setTimeout(function() {
        $.ajax({
          url: $assignedGroupsList.data('url-search'),
          method: 'GET',
          data: { q: query },
          success: function(response) {
            $groupSearchResults.empty();
            if (response.success && response.data.length > 0) {
              response.data.forEach(function(group) {
                // Check if already assigned
                var alreadyAssigned = $assignedGroupsList.find('.group-badge-card[data-id="' + group.id + '"]').length > 0;
                if (!alreadyAssigned) {
                  var itemHtml = '<div class="group-search-item" data-id="' + group.id + '" data-name="' + group.name + '" data-count="' + group.learners_count + '">' +
                    '<strong>' + group.name + '</strong> <span class="text-muted" style="font-size:0.75rem;">(' + group.learners_count + ' apprenants)</span>' +
                    '</div>';
                  $groupSearchResults.append(itemHtml);
                }
              });
              $groupSearchResults.show();
            } else {
              $groupSearchResults.append('<div class="p-2 text-muted text-center" style="font-size:0.8rem;">' + T('no_group').replace(/^./, function (c) { return c.toUpperCase(); }) + ' trouvé' + T('fem_e') + '</div>').show();
            }
          }
        });
      }, 300);
    });

    // Close search dropdown on click outside
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.group-search-wrapper').length) {
        $groupSearchResults.hide();
      }
    });

    // Assign group on selection
    $groupSearchResults.on('click', '.group-search-item', function() {
      var gId = $(this).data('id');
      var gName = $(this).data('name');
      var gCount = $(this).data('count');
      
      $groupSearchResults.hide();
      $groupSearchInput.val('');

      $.ajax({
        url: $assignedGroupsList.data('url-assign'),
        method: 'POST',
        data: { group_id: gId },
        success: function(response) {
          if (response.success) {
            showToast('success', response.message || (T('Group') + ' assigné' + T('fem_e') + ' avec succès.'));
            
            // Remove placeholder if present
            $('#noGroupsPlaceholder').remove();
            
            // Add card to list
            var cardHtml = '<div class="group-badge-card" data-id="' + gId + '">' +
              '<div class="group-badge-info">' +
                '<span class="group-badge-name">' + gName + '</span>' +
                '<span class="group-badge-learners">' + gCount + ' apprenants</span>' +
                (window.GroupFolderPicker ? GroupFolderPicker.slotHtml(gId) : '') +
              '</div>' +
              '<button class="group-remove-btn" data-id="' + gId + '" title="Retirer ' + T('this_group') + '">' +
                '<i class="bi bi-x"></i>' +
              '</button>' +
            '</div>';
            var $card = $(cardHtml).appendTo($assignedGroupsList);
            if (window.GroupFolderPicker) {
              GroupFolderPicker.mountAll($card);
            }
            afterGroupsChanged();
          } else {
            showToast('error', response.message || 'Erreur lors de l\'assignation.');
          }
        },
        error: function(xhr) {
          showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Erreur de communication avec le serveur.');
        }
      });
    });

    // Unassign group on close click
    $assignedGroupsList.on('click', '.group-remove-btn', function() {
      var $card = $(this).closest('.group-badge-card');
      var gId = $card.data('id');

      Swal.fire({
        title: 'Retirer ' + T('the_group') + ' ?',
        text: "Les apprenants de " + T('this_group') + " n'auront plus accès à ce quiz.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1e6f5c',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, retirer',
        cancelButtonText: 'Annuler'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: String($assignedGroupsList.data('url-unassign')).replace('__GROUP__', gId),
            method: 'DELETE',
            success: function(response) {
              if (response.success) {
                showToast('success', response.message || (T('Group') + ' retiré' + T('fem_e') + '.'));
                $card.fadeOut(300, function() {
                  $(this).remove();
                  afterGroupsChanged();
                  if ($assignedGroupsList.children('.group-badge-card').length === 0) {
                    $assignedGroupsList.append('<div class="text-center py-4 text-muted" id="noGroupsPlaceholder">' +
                      '<i class="bi bi-tags display-6 mb-2 d-block"></i>' +
                      '<p class="mb-0" style="font-size: 0.8rem;">' + T('no_group').replace(/^./, function (c) { return c.toUpperCase(); }) + ' assigné' + T('fem_e') + '.</p>' +
                    '</div>');
                  }
                });
              } else {
                showToast('error', response.message || ('Impossible de retirer ' + T('the_group') + '.'));
              }
            },
            error: function() {
              showToast('error', 'Erreur de communication avec le serveur.');
            }
          });
        }
      });
    });

    // -------------------------------------------------------------
    // 3. Toggle Active/Published State
    // -------------------------------------------------------------
    function updateActiveUI(isActive) {
      $paramIsActive.prop('checked', isActive);
      
      if (isActive) {
        $sidebarStatusBadge.text('Actif').removeClass('bg-secondary').addClass('bg-success');
        $btnPublishText.text('Désactiver le Quiz');
        $btnPublishQuiz.find('i').removeClass('bi-cloud-arrow-up-fill').addClass('bi-cloud-arrow-down-fill');
      } else {
        $sidebarStatusBadge.text('Brouillon').removeClass('bg-success').addClass('bg-secondary');
        $btnPublishText.text('Publier le Quiz');
        $btnPublishQuiz.find('i').removeClass('bi-cloud-arrow-down-fill').addClass('bi-cloud-arrow-up-fill');
      }
    }

    function toggleActiveState() {
      $.ajax({
        url: '/admin/quizzes/' + quizId + '/toggle-active',
        method: 'PATCH',
        success: function(response) {
          if (response.success) {
            updateActiveUI(response.is_active);
            showToast('success', response.message);
          } else {
            showToast('error', response.message);
          }
        },
        error: function() {
          showToast('error', 'Erreur lors de la modification de l\'état.');
        }
      });
    }

    $paramIsActive.on('change', function(e) {
      e.preventDefault();
      toggleActiveState();
    });

    $btnPublishQuiz.on('click', function(e) {
      e.preventDefault();
      toggleActiveState();
    });

    // -------------------------------------------------------------
    // 4. Autosave Form (every 30 seconds if modified)
    // -------------------------------------------------------------
    var lastSavedData = $quizParamsForm.serialize();
    
    function autosave() {
      var currentData = $quizParamsForm.serialize();
      if (currentData === lastSavedData) {
        return; // No change
      }
      
      $autosaveStatus.html('<i class="bi bi-arrow-repeat spin me-1"></i> Enregistrement...');
      
      $.ajax({
        url: '/admin/quizzes/' + quizId + '/autosave',
        method: 'POST',
        data: $quizParamsForm.serialize(),
        success: function(response) {
          if (response.success) {
            lastSavedData = currentData;
            var time = new Date().toLocaleTimeString();
            $autosaveStatus.html('<i class="bi bi-cloud-check-fill me-1 text-success"></i> Sauvegardé à ' + time);
            
            // Update displayed titles
            if (response.data && response.data.title) {
              $('#quizTitleDisplay').text(response.data.title);
              $('#sidebarQuizTitle').text(response.data.title);
              $('.breadcrumb-item.active').text(response.data.title);
            }
            if (response.data && response.data.description) {
              $('#quizDescriptionDisplay').text(response.data.description);
            }
          } else {
            $autosaveStatus.html('<i class="bi bi-cloud-slash-fill me-1 text-danger"></i> Échec de l\'autosave');
          }
        },
        error: function() {
          $autosaveStatus.html('<i class="bi bi-cloud-slash-fill me-1 text-danger"></i> Erreur réseau');
        }
      });
    }
    
    setInterval(autosave, 30000);

    // -------------------------------------------------------------
    // 5. Question Creation & Modals triggers
    // -------------------------------------------------------------
    var $questionTypeModal = $('#questionTypeModal');
    
    $('#btnAddQuestion').on('click', function() {
      $questionTypeModal.modal('show');
    });

    // Question type selection inside the grid
    $questionTypeModal.on('click', '.type-select-card', function() {
      var type = $(this).data('type');
      $questionTypeModal.modal('hide');
      
      // Dispatch an event so sub-modal handlers can open their respective modal
      $(document).trigger('open-question-modal', { type: type, action: 'create' });
    });

    // Delete question handler
    $questionsList.on('click', '.question-btn.delete', function() {
      var $item = $(this).closest('.question-item');
      var qId = $(this).data('id');
      
      Swal.fire({
        title: 'Supprimer la question ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: '/admin/quizzes/' + quizId + '/questions/' + qId,
            method: 'DELETE',
            success: function(response) {
              if (response.success) {
                showToast('success', response.message || 'Question supprimée.');
                $item.fadeOut(300, function() {
                  $(this).remove();
                  
                  // Update indexes
                  $questionsList.find('.question-item').each(function(index) {
                    $(this).find('.question-num').text(index + 1);
                  });
                  
                  // Update badge counter
                  var count = $questionsList.find('.question-item').length;
                  $('#questionCounter').text(count + ' total');
                  
                  if (count === 0) {
                    $questionsList.append('<div class="text-center py-5 text-muted" id="noQuestionsPlaceholder">' +
                      '<i class="bi bi-question-circle display-4 mb-3 d-block text-muted"></i>' +
                      '<p class="mb-0">Aucune question dans ce quiz. Cliquez sur "Ajouter" pour commencer.</p>' +
                    '</div>');
                  }
                });
              } else {
                showToast('error', response.message || 'Erreur lors de la suppression.');
              }
            },
            error: function() {
              showToast('error', 'Erreur de communication avec le serveur.');
            }
          });
        }
      });
    });

    // Edit question handler (triggers open-question-modal event for sub-modals)
    $questionsList.on('click', '.question-btn.edit', function() {
      var qId = $(this).data('id');
      var type = $(this).data('type');
      
      $(document).trigger('open-question-modal', { type: type, action: 'edit', id: qId });
    });

  });

  // Global helper to create HTML template for a question item
  window.createQuestionItemHtml = function(question, num) {
    var typeText = question.type.replace('_', ' ');
    // Strip HTML tags for title attribute
    var textSnippet = $('<div>').html(question.question_text).text();
    return '<div class="question-item" data-id="' + question.id + '">' +
      '<div class="question-handle">' +
        '<i class="bi bi-grid-3x2-gap-fill"></i>' +
      '</div>' +
      '<div class="question-num">' + num + '</div>' +
      '<div class="question-main">' +
        '<div class="question-text" title="' + textSnippet + '">' +
          question.question_text +
        '</div>' +
        '<div class="question-meta">' +
          '<span class="question-type-badge">' + typeText + '</span>' +
          '<span class="question-points-badge">' + question.points + ' pts</span>' +
        '</div>' +
      '</div>' +
      '<div class="question-actions">' +
        '<button class="question-btn edit" data-id="' + question.id + '" data-type="' + question.type + '" title="Modifier">' +
          '<i class="bi bi-pencil-fill"></i>' +
        '</button>' +
        '<button class="question-btn delete" data-id="' + question.id + '" title="Supprimer">' +
          '<i class="bi bi-trash-fill"></i>' +
        '</button>' +
      '</div>' +
    '</div>';
  };

  // Global helper to add or update question in UI list dynamically
  window.addOrUpdateQuestionInList = function(question) {
    var $questionsList = $('#questionsList');
    $('#noQuestionsPlaceholder').remove();
    
    var $existingItem = $questionsList.find('.question-item[data-id="' + question.id + '"]');
    
    if ($existingItem.length) {
      var num = $existingItem.find('.question-num').text();
      var newHtml = window.createQuestionItemHtml(question, num);
      $existingItem.replaceWith(newHtml);
    } else {
      var nextNum = $questionsList.find('.question-item').length + 1;
      var newHtml = window.createQuestionItemHtml(question, nextNum);
      $questionsList.append(newHtml);
    }
    
    var total = $questionsList.find('.question-item').length;
    $('#questionCounter').text(total + ' total');
  };

})(jQuery);
