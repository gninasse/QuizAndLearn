/**
 * group-folder-picker.js
 * Sélecteur de dossier par groupe dans les éditeurs de contenu (article, quiz, examen, deck).
 *
 * Configuration : un élément [data-group-placement] (partial core::admin.partials.group-placement) portant
 *   data-type, data-id, data-isolated,
 *   data-url-data  (…/groups/__GROUP__/contenus/data),
 *   data-url-place (…/groups/__GROUP__/contenus/items/place).
 * Chaque emplacement .group-folder-slot[data-group-id] reçoit un <select> « Racine / dossiers ».
 * Un changement de dossier place le contenu en fin du dossier choisi (POST items.place).
 */
(function ($) {
  'use strict';

  var END_POSITION = 999999;

  function config() {
    var $cfg = $('[data-group-placement]').first();
    if (!$cfg.length) {
      return null;
    }
    return {
      type: String($cfg.data('type')),
      id: parseInt($cfg.data('id'), 10),
      isolated: String($cfg.data('isolated')) === '1',
      urlData: String($cfg.data('url-data')),
      urlPlace: String($cfg.data('url-place')),
      $root: $cfg
    };
  }

  function url(template, groupId) {
    return template.replace('__GROUP__', encodeURIComponent(groupId));
  }

  function notify(type, message) {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message);
    } else if (window.Swal) {
      Swal.fire({ toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: 2500 });
    }
  }

  function errorMessage(xhr, fallback) {
    return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback;
  }

  /**
   * Dossiers en ordre d'arborescence, avec un libellé indenté selon la profondeur.
   */
  function flatten(folders) {
    var byParent = {};
    folders.forEach(function (f) {
      var key = f.parent_id === null ? 'root' : String(f.parent_id);
      (byParent[key] = byParent[key] || []).push(f);
    });
    var out = [];
    (function walk(key, depth) {
      (byParent[key] || [])
        .sort(function (a, b) { return a.position - b.position || a.id - b.id; })
        .forEach(function (f) {
          out.push({ id: f.id, label: new Array(depth + 1).join('— ') + f.name });
          walk(String(f.id), depth + 1);
        });
    })('root', 0);
    return out;
  }

  function mount($slot, cfg) {
    var groupId = $slot.data('group-id');
    $slot.html('<span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Chargement des dossiers…</span>');

    $.getJSON(url(cfg.urlData, groupId)).done(function (res) {
      var item = (res.items || []).filter(function (i) { return i.type === cfg.type && i.id === cfg.id; })[0];
      if (!item) {
        $slot.empty();
        return;
      }
      if (item.is_public) {
        $slot.html('<span class="text-muted small"><i class="bi bi-globe2 me-1"></i>Deck public : hors dossiers</span>');
        return;
      }

      var $select = $('<select class="form-select form-select-sm group-folder-select" aria-label="Dossier"></select>');
      $select.append($('<option value=""></option>').text('Racine (aucun dossier)'));
      flatten(res.folders || []).forEach(function (f) {
        $select.append($('<option></option>').val(f.id).text(f.label));
      });
      $select.val(item.folder_id === null ? '' : String(item.folder_id));
      $select.data('current', $select.val());

      $slot.empty()
        .append($('<label class="form-label small text-muted mb-1"></label>').html('<i class="bi bi-folder2 me-1"></i>Dossier'))
        .append($select);
    }).fail(function (xhr) {
      $slot.html($('<span class="text-danger small"></span>').text(errorMessage(xhr, 'Dossiers indisponibles.')));
    });
  }

  function onChange() {
    var cfg = config();
    var $select = $(this);
    var groupId = $select.closest('.group-folder-slot').data('group-id');
    var folderId = $select.val();

    $select.prop('disabled', true);
    $.ajax({
      url: url(cfg.urlPlace, groupId),
      method: 'POST',
      data: { type: cfg.type, id: cfg.id, folder_id: folderId === '' ? null : folderId, position: END_POSITION }
    }).done(function (res) {
      $select.data('current', folderId);
      notify('success', res.message || 'Élément déplacé.');
    }).fail(function (xhr) {
      $select.val($select.data('current'));
      notify('error', errorMessage(xhr, 'Déplacement impossible.'));
    }).always(function () {
      $select.prop('disabled', false);
    });
  }

  window.GroupFolderPicker = {
    config: config,
    errorMessage: errorMessage,

    /** Monte un sélecteur dans chaque emplacement de $scope (document par défaut). */
    mountAll: function ($scope) {
      var cfg = config();
      if (!cfg) {
        return;
      }
      ($scope || $(document)).find('.group-folder-slot').each(function () {
        mount($(this), cfg);
      });
    },

    /** Emplacement HTML d'un groupe, à insérer dans une carte de groupe. */
    slotHtml: function (groupId) {
      return '<div class="group-folder-slot mt-1" data-group-id="' + parseInt(groupId, 10) + '"></div>';
    },

    /** Mode isolé : un seul groupe, la recherche se masque dès qu'un groupe est attribué. */
    syncSingle: function ($searchWrapper, assignedCount) {
      var cfg = config();
      if (!cfg || !cfg.isolated) {
        return;
      }
      $searchWrapper.toggle(assignedCount === 0);
      cfg.$root.find('.isolated-owned').toggle(assignedCount > 0);
    }
  };

  $(document).on('change', '.group-folder-select', onChange);
  $(function () {
    window.GroupFolderPicker.mountAll();
  });
})(jQuery);
