/**
 * article-editor.js
 * Client-side logic for the Learn&Quiz Article Editor.
 */
(function($) {
  $(function() {
    var articleId = $('#assignedGroupsList').data('article-id');
    var $articleContent = $('#articleContent');
    var $articleTitle = $('#articleTitle');
    var $groupSearchInput = $('#groupSearchInput');
    var $groupSearchResults = $('#groupSearchResults');
    var $assignedGroupsList = $('#assignedGroupsList');
    var $autosaveStatus = $('#autosaveStatus');
    
    var $paramIsActive = $('#paramIsActive');
    var $settingsIsActive = $('#settingsIsActive');
    var $sidebarStatusBadge = $('#sidebarStatusBadge');
    var $btnPublishArticle = $('#btnPublishArticle');
    var $btnPublishText = $('#btnPublishText');
    
    // Track if modifications happened
    var isModified = false;
    var lastSavedTitle = $articleTitle.text().trim();
    var lastSavedContent = $articleContent.html().trim();
    
    // CSRF Setup
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });

    // -------------------------------------------------------------
    // 1. WYSIWYG Editing commands
    // -------------------------------------------------------------
    $('.toolbar-btn[data-cmd]').on('click', function(e) {
      e.preventDefault();
      var cmd = $(this).data('cmd');
      var val = $(this).data('val') || null;
      
      $articleContent.focus();
      
      if (cmd === 'createLink') {
        var url = prompt("Entrez l'URL du lien :", "https://");
        if (url) {
          document.execCommand(cmd, false, url);
        }
      } else {
        document.execCommand(cmd, false, val);
      }
      
      updateToolbarActiveStates();
      checkChanges();
    });

    // Handle local image file loading
    $('#btnInsertLocalImage').on('click', function(e) {
      e.preventDefault();
      $('#imageUploadInput').click();
    });

    $('#imageUploadInput').on('change', function() {
      var file = this.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          Swal.fire('Erreur', 'L\'image ne doit pas dépasser 5 Mo.', 'error');
          $(this).val('');
          return;
        }

        var formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'image');
        formData.append('article_id', articleId);

        Swal.fire({
          title: 'Importation de l\'image...',
          text: 'Veuillez patienter pendant l\'upload de l\'image.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        $.ajax({
          url: '/admin/articles/upload-media',
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
            Swal.close();
            if (response.success) {
              var imgHtml = '<figure class="figure my-3 text-center w-100">' +
                '<img src="' + response.url + '" class="figure-img img-fluid rounded" alt="' + response.name + '">' +
                '<figcaption class="figure-caption" contenteditable="true">Légende de l\'image</figcaption>' +
                '</figure><p><br></p>';
              insertHtmlAtCursor(imgHtml);
              checkChanges();
              showToast('success', 'Image uploadée avec succès.');
            } else {
              Swal.fire('Erreur', response.message || 'Une erreur est survenue.', 'error');
            }
          },
          error: function(xhr) {
            Swal.close();
            var errMsg = xhr.responseJSON?.message || 'Une erreur est survenue lors de l\'upload.';
            Swal.fire('Erreur', errMsg, 'error');
          }
        });
      }
      $(this).val(''); // Reset
    });

    // Handle local audio file loading
    $('#audioUploadInput').on('change', function() {
      var file = this.files[0];
      if (file) {
        // 10MB limit
        if (file.size > 10 * 1024 * 1024) {
          Swal.fire('Erreur', 'Le fichier audio ne doit pas dépasser 10 Mo.', 'error');
          $(this).val('');
          return;
        }

        var formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'audio');
        formData.append('article_id', articleId);

        Swal.fire({
          title: 'Importation de l\'audio...',
          text: 'Veuillez patienter pendant l\'upload de l\'audio.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        $.ajax({
          url: '/admin/articles/upload-media',
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
            Swal.close();
            if (response.success) {
              var audioHtml = '<div class="audio-block my-3 p-3 bg-light rounded text-center border" contenteditable="false">' +
                '<p class="mb-2 fw-semibold"><i class="bi bi-music-note-beamed me-2"></i>' + response.name + '</p>' +
                '<audio controls controlsList="nodownload nofullscreen noremoteplayback" disablePictureInPicture="true" disableRemotePlayback="true" src="' + response.url + '" class="w-100"></audio>' +
                '</div><p><br></p>';
              insertHtmlAtCursor(audioHtml);
              checkChanges();
              showToast('success', 'Fichier audio uploadé avec succès.');
            } else {
              Swal.fire('Erreur', response.message || 'Une erreur est survenue.', 'error');
            }
          },
          error: function(xhr) {
            Swal.close();
            var errMsg = xhr.responseJSON?.message || 'Une erreur est survenue lors de l\'upload.';
            Swal.fire('Erreur', errMsg, 'error');
          }
        });
      }
      $(this).val(''); // Reset
    });

    // Helper to insert HTML at cursor position or end of editor
    function insertHtmlAtCursor(html) {
      $articleContent.focus();
      var sel, range;
      if (window.getSelection) {
        sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount && $articleContent[0].contains(sel.anchorNode)) {
          range = sel.getRangeAt(0);
          range.deleteContents();
          
          var el = document.createElement("div");
          el.innerHTML = html;
          
          var frag = document.createDocumentFragment(), node, lastNode;
          while ((node = el.firstChild)) {
            lastNode = frag.appendChild(node);
          }
          range.insertNode(frag);
          
          if (lastNode) {
            range = range.cloneRange();
            range.setStartAfter(lastNode);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
          }
        } else {
          // If editor is not in active selection focus, append to end
          $articleContent.append(html);
        }
      } else {
        $articleContent.append(html);
      }
      updateMetrics();
    }

    // Toggle button UI active states depending on current cursor styles
    function updateToolbarActiveStates() {
      $('.toolbar-btn[data-cmd]').each(function() {
        var cmd = $(this).data('cmd');
        if (cmd !== 'createLink' && cmd !== 'unlink' && cmd !== 'removeFormat' && cmd !== 'formatBlock') {
          try {
            if (document.queryCommandState(cmd)) {
              $(this).addClass('active');
            } else {
              $(this).removeClass('active');
            }
          } catch(e) {}
        }
      });
    }

    $(document).on('selectionchange', function() {
      if (document.activeElement === $articleContent[0]) {
        updateToolbarActiveStates();
      }
    });

    // -------------------------------------------------------------
    // 2. Elements insertion panel
    // -------------------------------------------------------------
    $('.element-insert-btn').on('click', function(e) {
      e.preventDefault();
      var element = $(this).data('element');
      
      switch(element) {
        case 'h2':
          insertHtmlAtCursor('<h2>Nouveau Titre H2</h2><p>Rédigez votre paragraphe ici...</p>');
          break;
        case 'h3':
          insertHtmlAtCursor('<h3>Nouveau Sous-titre H3</h3><p>Rédigez votre paragraphe ici...</p>');
          break;
        case 'blockquote':
          insertHtmlAtCursor('<blockquote>« Saisissez une citation inspirante ici »</blockquote><p><br></p>');
          break;
        case 'callout':
          insertHtmlAtCursor('<div class="callout-block"><p><strong>Note importante :</strong> Saisissez vos remarques ou informations clés ici.</p></div><p><br></p>');
          break;
        case 'columns-2':
          insertHtmlAtCursor('<div class="editor-row"><div class="editor-col" contenteditable="true"><p>Colonne Gauche</p></div><div class="editor-col" contenteditable="true"><p>Colonne Droite</p></div></div><p><br></p>');
          break;
        case 'columns-3':
          insertHtmlAtCursor('<div class="editor-row"><div class="editor-col" contenteditable="true"><p>Colonne 1</p></div><div class="editor-col" contenteditable="true"><p>Colonne 2</p></div><div class="editor-col" contenteditable="true"><p>Colonne 3</p></div></div><p><br></p>');
          break;
        case 'image-placeholder':
          $('#imageUploadInput').click();
          break;
        case 'video-placeholder':
          Swal.fire({
            title: 'Insérer une vidéo YouTube',
            input: 'url',
            inputLabel: 'Lien de la vidéo YouTube',
            inputPlaceholder: 'https://www.youtube.com/watch?v=...',
            showCancelButton: true,
            confirmButtonColor: '#1e6f5c',
            confirmButtonText: 'Insérer',
            cancelButtonText: 'Annuler',
            inputValidator: (value) => {
              if (!value) {
                return 'Vous devez saisir une URL !';
              }
              if (!value.includes('youtube.com') && !value.includes('youtu.be')) {
                return 'Veuillez saisir un lien YouTube valide.';
              }
            }
          }).then((result) => {
            if (result.isConfirmed && result.value) {
              var url = result.value;
              var videoId = null;

              // Parse youtube ID
              var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
              var match = url.match(regExp);

              if (match && match[2].length == 11) {
                videoId = match[2];
              }

              if (videoId) {
                var embedUrl = 'https://www.youtube.com/embed/' + videoId + '?modestbranding=1&rel=0';
                var videoHtml = '<div class="ratio ratio-16x9 my-3" contenteditable="false">' +
                  '<iframe src="' + embedUrl + '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>' +
                  '</div><p><br></p>';
                insertHtmlAtCursor(videoHtml);
                checkChanges();
                showToast('success', 'Vidéo YouTube insérée.');
              } else {
                Swal.fire('Erreur', 'Impossible de récupérer l\'identifiant de la vidéo YouTube.', 'error');
              }
            }
          });
          break;
        case 'audio-upload':
          $('#audioUploadInput').click();
          break;
        case 'quiz-widget':
          triggerQuizSearchAndInsert();
          break;
      }
      
      checkChanges();
    });

    // Modal to search quizzes and insert selected one
    function triggerQuizSearchAndInsert() {
      Swal.fire({
        title: 'Intégrer un Quiz',
        text: 'Recherchez un quiz actif pour l\'intégrer à l\'article :',
        input: 'text',
        inputPlaceholder: 'Entrez le titre du quiz...',
        showCancelButton: true,
        confirmButtonColor: '#1e6f5c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Rechercher',
        cancelButtonText: 'Annuler',
        preConfirm: function(query) {
          return $.ajax({
            url: '/admin/articles/quizzes/search',
            method: 'GET',
            data: { q: query }
          }).then(function(response) {
            if (response.success && response.data.length > 0) {
              return response.data;
            } else {
              Swal.showValidationMessage('Aucun quiz actif trouvé avec ce nom.');
              return false;
            }
          });
        }
      }).then(function(result) {
        if (result.isConfirmed && result.value) {
          var quizzes = result.value;
          var optionsHtml = {};
          quizzes.forEach(function(q) {
            optionsHtml[q.id] = q.title;
          });
          
          Swal.fire({
            title: 'Sélectionner le quiz',
            input: 'select',
            inputOptions: optionsHtml,
            inputPlaceholder: 'Sélectionnez un quiz...',
            showCancelButton: true,
            confirmButtonColor: '#1e6f5c',
            confirmButtonText: 'Insérer',
            cancelButtonText: 'Annuler'
          }).then(function(selectResult) {
            if (selectResult.isConfirmed && selectResult.value) {
              var qId = selectResult.value;
              var qTitle = optionsHtml[qId];
              var widgetHtml = '<div class="quiz-widget-embed" data-quiz-id="' + qId + '" contenteditable="false">' +
                '<i class="bi bi-question-circle-fill"></i>' +
                '<div class="quiz-title">Quiz Interactif : ' + qTitle + '</div>' +
                '<p class="mb-0 small text-muted">ID: ' + qId + ' — Cet encadré sera remplacé par le module de quiz lors de la consultation.</p>' +
                '</div><p><br></p>';
              insertHtmlAtCursor(widgetHtml);
              checkChanges();
            }
          });
        }
      });
    }

    // -------------------------------------------------------------
    // 3. Metrics and status
    // -------------------------------------------------------------
    function updateMetrics() {
      var text = $articleContent.text().trim();
      var charCount = text.length;
      var wordCount = text === '' ? 0 : text.split(/\s+/).filter(Boolean).length;
      
      $('#charCount').text(charCount);
      $('#wordCount').text(wordCount);
    }

    $articleContent.on('input', function() {
      updateMetrics();
      checkChanges();
    });

    $articleTitle.on('input', function() {
      checkChanges();
      var title = $(this).text().trim();
      $('#sidebarArticleTitle').text(title || 'Sans titre');
      $('.breadcrumb-item.active').text(title || 'Sans titre');
    });

    updateMetrics();

    // Check if contents are different from initial or saved states
    function checkChanges() {
      var currentTitle = $articleTitle.text().trim();
      var currentContent = $articleContent.html().trim();
      
      if (currentTitle !== lastSavedTitle || currentContent !== lastSavedContent) {
        if (!isModified) {
          isModified = true;
          $autosaveStatus.html('<i class="bi bi-cloud-upload text-warning"></i> Modifications non enregistrées');
        }
      } else {
        if (isModified) {
          isModified = false;
          $autosaveStatus.html('<i class="bi bi-cloud-check text-success"></i> Synchronisé');
        }
      }
    }

    // -------------------------------------------------------------
    // 4. Save and Autosave logic
    // -------------------------------------------------------------
    function saveArticleData(isManual) {
      var title = $articleTitle.text().trim();
      var content = $articleContent.html().trim();
      var is_active = $paramIsActive.is(':checked') ? 1 : 0;
      
      // Get settings metadata from modal form
      var category = $('#settingsCategory').val() || '';
      var seo_keywords = $('#settingsSeoKeywords').val() || '';
      var seo_description = $('#settingsSeoDescription').val() || '';

      if (title === '') {
        if (isManual) {
          showToast('error', 'Le titre de l\'article ne peut pas être vide.');
        }
        return;
      }

      $autosaveStatus.html('<i class="bi bi-arrow-repeat spin me-1 text-primary"></i> Enregistrement...');

      $.ajax({
        url: '/admin/articles/' + articleId + '/autosave',
        method: 'POST',
        data: {
          title: title,
          content: content,
          is_active: is_active,
          category: category,
          seo_keywords: seo_keywords,
          seo_description: seo_description
        },
        success: function(response) {
          if (response.success) {
            isModified = false;
            lastSavedTitle = title;
            lastSavedContent = content;
            
            var time = new Date().toLocaleTimeString();
            $autosaveStatus.html('<i class="bi bi-cloud-check-fill text-success"></i> Enregistré à ' + time);
            
            if (isManual) {
              showToast('success', response.message || 'Article enregistré.');
            }
          } else {
            $autosaveStatus.html('<i class="bi bi-cloud-slash text-danger"></i> Échec de la sauvegarde');
            if (isManual) {
              showToast('error', response.message || 'Erreur lors de la sauvegarde.');
            }
          }
        },
        error: function(xhr) {
          $autosaveStatus.html('<i class="bi bi-cloud-slash text-danger"></i> Erreur réseau');
          if (isManual) {
            showToast('error', 'Erreur de connexion avec le serveur.');
          }
        }
      });
    }

    // Manual Save triggers
    $('#btnSaveArticle').on('click', function(e) {
      e.preventDefault();
      saveArticleData(true);
    });

    // Auto-save timer (every 30 seconds)
    setInterval(function() {
      if (isModified) {
        saveArticleData(false);
      }
    }, 30000);

    // -------------------------------------------------------------
    // 5. Visibility / Publish state management
    // -------------------------------------------------------------
    function updateActiveUI(isActive) {
      $paramIsActive.prop('checked', isActive);
      $settingsIsActive.prop('checked', isActive);
      
      if (isActive) {
        $sidebarStatusBadge.text('Actif').removeClass('bg-secondary').addClass('bg-success');
        $btnPublishText.text('Passer en Brouillon');
        $btnPublishArticle.find('i').removeClass('bi-cloud-arrow-up-fill').addClass('bi-cloud-arrow-down-fill');
        $('#settingsIsActiveLabel').text('Publié (Actif)');
      } else {
        $sidebarStatusBadge.text('Brouillon').removeClass('bg-success').addClass('bg-secondary');
        $btnPublishText.text('Publier l\'Article');
        $btnPublishArticle.find('i').removeClass('bi-cloud-arrow-down-fill').addClass('bi-cloud-arrow-up-fill');
        $('#settingsIsActiveLabel').text('Brouillon (Inactif)');
      }
    }

    function toggleArticleStatus() {
      $.ajax({
        url: '/admin/articles/' + articleId + '/toggle-active',
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
          showToast('error', 'Erreur lors du changement de statut de publication.');
        }
      });
    }

    $paramIsActive.on('change', function(e) {
      e.preventDefault();
      toggleArticleStatus();
    });

    $settingsIsActive.on('change', function(e) {
      e.preventDefault();
      toggleArticleStatus();
    });

    $btnPublishArticle.on('click', function(e) {
      e.preventDefault();
      toggleArticleStatus();
    });

    // -------------------------------------------------------------
    // 6. Group access membership & search
    // -------------------------------------------------------------
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
          url: '/admin/groups/search',
          method: 'GET',
          data: { q: query },
          success: function(response) {
            $groupSearchResults.empty();
            if (response.success && response.data.length > 0) {
              response.data.forEach(function(group) {
                var alreadyAssigned = $assignedGroupsList.find('.group-badge-card[data-id="' + group.id + '"]').length > 0;
                if (!alreadyAssigned) {
                  var itemHtml = '<div class="group-search-item" data-id="' + group.id + '" data-name="' + group.name + '" data-count="' + group.learners_count + '">' +
                    '<strong>' + group.name + '</strong> <span class="text-muted small">(' + group.learners_count + ' apprenants)</span>' +
                    '</div>';
                  $groupSearchResults.append(itemHtml);
                }
              });
              $groupSearchResults.show();
            } else {
              $groupSearchResults.append('<div class="p-2 text-muted text-center small">Aucun groupe trouvé</div>').show();
            }
          }
        });
      }, 300);
    });

    $(document).on('click', function(e) {
      if (!$(e.target).closest('.group-search-wrapper').length) {
        $groupSearchResults.hide();
      }
    });

    // Assign Group
    $groupSearchResults.on('click', '.group-search-item', function() {
      var gId = $(this).data('id');
      var gName = $(this).data('name');
      var gCount = $(this).data('count');
      
      $groupSearchResults.hide();
      $groupSearchInput.val('');

      $.ajax({
        url: '/admin/articles/' + articleId + '/groups',
        method: 'POST',
        data: { group_id: gId },
        success: function(response) {
          if (response.success) {
            showToast('success', response.message || 'Accès accordé au groupe.');
            $('#noGroupsPlaceholder').remove();
            
            var badgeHtml = '<div class="group-badge-card" data-id="' + gId + '">' +
              '<div class="group-badge-info">' +
                '<span class="group-badge-name">' + gName + '</span>' +
                '<span class="group-badge-learners">' + gCount + ' apprenants</span>' +
              '</div>' +
              '<button class="group-remove-btn" data-id="' + gId + '" title="Retirer l\'accès">' +
                '<i class="bi bi-x"></i>' +
              '</button>' +
            '</div>';
            $assignedGroupsList.append(badgeHtml);
          } else {
            showToast('error', response.message || 'Erreur d\'assignation.');
          }
        },
        error: function() {
          showToast('error', 'Erreur de communication avec le serveur.');
        }
      });
    });

    // Remove Group Access
    $assignedGroupsList.on('click', '.group-remove-btn', function() {
      var $card = $(this).closest('.group-badge-card');
      var gId = $card.data('id');

      Swal.fire({
        title: 'Retirer l\'accès ?',
        text: "Les membres de ce groupe ne pourront plus consulter cet article.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1e6f5c',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, retirer',
        cancelButtonText: 'Annuler'
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: '/admin/articles/' + articleId + '/groups/' + gId,
            method: 'DELETE',
            success: function(response) {
              if (response.success) {
                showToast('success', response.message || 'Accès retiré.');
                $card.fadeOut(300, function() {
                  $(this).remove();
                  if ($assignedGroupsList.children('.group-badge-card').length === 0) {
                    $assignedGroupsList.append('<div class="text-center py-4 text-muted" id="noGroupsPlaceholder">' +
                      '<i class="bi bi-tags display-6 mb-2 d-block"></i>' +
                      '<p class="mb-0" style="font-size: 0.8rem;">Visible pour tous (aucun groupe assigné).</p>' +
                    '</div>');
                  }
                });
              } else {
                showToast('error', response.message || 'Erreur lors du retrait.');
              }
            },
            error: function() {
              showToast('error', 'Erreur serveur.');
            }
          });
        }
      });
    });

    // -------------------------------------------------------------
    // 7. Settings Modal & SEO triggers
    // -------------------------------------------------------------
    var $modalSettings = $('#modalSettings');
    var $seoDesc = $('#settingsSeoDescription');
    var $seoCounter = $('#seoDescCounter');

    $('#btnOpenSettings').on('click', function(e) {
      e.preventDefault();
      $modalSettings.modal('show');
    });

    // Live character count for SEO description
    function updateSeoCounter() {
      var len = $seoDesc.val().length;
      $seoCounter.text(len + '/160');
      if (len > 160) {
        $seoCounter.addClass('text-danger');
      } else {
        $seoCounter.removeClass('text-danger');
      }
    }
    $seoDesc.on('input', updateSeoCounter);
    updateSeoCounter(); // Initial

    // Save settings inside modal
    $('#btnSaveSettings').on('click', function(e) {
      e.preventDefault();
      $modalSettings.modal('hide');
      saveArticleData(true);
    });

    // -------------------------------------------------------------
    // 8. Article Preview Handler
    // -------------------------------------------------------------
    $('#btnPreviewArticle').on('click', function(e) {
      e.preventDefault();
      var title = $articleTitle.text().trim();
      var content = $articleContent.html().trim();
      
      // Enforce controlsList and protections on audio/video tags dynamically before previewing
      var protectedContent = content
        .replace(/<audio(?![^>]*controlsList)/gi, '<audio controlsList="nodownload nofullscreen noremoteplayback" disablePictureInPicture="true" disableRemotePlayback="true" oncontextmenu="return false;"')
        .replace(/<video(?![^>]*controlsList)/gi, '<video controlsList="nodownload nofullscreen noremoteplayback" disablePictureInPicture="true" disableRemotePlayback="true" oncontextmenu="return false;"');

      var previewWindow = window.open('', '_blank');
      previewWindow.document.write('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Aperçu : ' + title + '</title>' +
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">' +
        '<style>' +
        'body { font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; padding: 40px 0; color: #1a2c3e; user-select: none; }' +
        '.preview-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }' +
        'h1 { color: #1e6f5c; border-bottom: 2px solid #e8f5f1; padding-bottom: 15px; font-weight: 800; }' +
        '.callout-block { background-color: #f0faf6; border-left: 4px solid #1e6f5c; padding: 1.25rem 1.5rem; margin: 1.5rem 0; border-radius: 0 8px 8px 0; }' +
        'blockquote { border-left: 4px solid #289672; padding-left: 1.25rem; font-style: italic; color: #5a7289; margin: 1.5rem 0; }' +
        '.editor-row { display: flex; gap: 1.5rem; margin: 1.5rem 0; }' +
        '.editor-col { flex: 1; border: 1px solid #dde4ec; padding: 1rem; border-radius: 6px; }' +
        '.quiz-widget-embed { background-color: #e8f5f1; border: 2px dashed #289672; border-radius: 12px; padding: 1.5rem; text-align: center; margin: 1.5rem 0; }' +
        'audio::-internal-media-controls-download-button, video::-internal-media-controls-download-button { display:none !important; }' +
        'audio::-webkit-media-controls-panel-menu, video::-webkit-media-controls-panel-menu { display:none !important; }' +
        '</style></head><body>' +
        '<div class="preview-container">' +
        '<h1>' + title + '</h1>' +
        '<div class="my-4">' + protectedContent + '</div>' +
        '</div>' +
        '<script>' +
        'document.addEventListener("DOMContentLoaded", function() {' +
        '  const mediaElements = document.querySelectorAll("audio, video");' +
        '  mediaElements.forEach(el => {' +
        '    el.setAttribute("controlsList", "nodownload nofullscreen noremoteplayback");' +
        '    el.setAttribute("disablePictureInPicture", "true");' +
        '    el.setAttribute("disableRemotePlayback", "true");' +
        '    el.addEventListener("contextmenu", e => e.preventDefault());' +
        '    el.addEventListener("dragstart", e => e.preventDefault());' +
        '  });' +
        '  document.addEventListener("contextmenu", e => e.preventDefault());' +
        '  document.addEventListener("keydown", e => {' +
        '    if ((e.ctrlKey || e.metaKey) && (e.key === "s" || e.key === "u")) e.preventDefault();' +
        '    if (e.key === "F12" || ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === "I" || e.key === "i" || e.key === "C" || e.key === "c"))) e.preventDefault();' +
        '  });' +
        '});' +
        '</script>' +
        '</body></html>');
      previewWindow.document.close();
    });

  });
})(jQuery);
