/* Section « Demande d'accès » du back-office Mode découverte. */
(function ($) {
  'use strict';

  var MAX_CUSTOM = 5;
  var boot = window.ACCESS_REQUEST || { config: {}, defaults: {} };
  var cfg = boot.config || {};
  var defaults = boot.defaults || {};
  var $card;

  function $channel(type) { return $card.find('.ar-channel[data-type="' + type + '"]'); }

  function toggleSub($row) {
    $row.find('.ar-sub').toggleClass('d-none', !$row.find('.ar-channel-on').is(':checked'));
  }

  function updateMoves() {
    var $rows = $card.find('.ar-channel');
    $rows.find('.ar-up, .ar-down').prop('disabled', false);
    $rows.first().find('.ar-up').prop('disabled', true);
    $rows.last().find('.ar-down').prop('disabled', true);
  }

  function updateCustomState() {
    var n = $card.find('.ar-custom-row').length;
    $('#ar_addCustom').prop('disabled', n >= MAX_CUSTOM);
  }

  function addCustom(f) {
    var $row = $($('#ar_customRow').html());
    $row.data('key', f && f.key ? f.key : null);
    if (f) {
      $row.find('[data-cf=label]').val(f.label);
      $row.find('[data-cf=type]').val(f.type);
      $row.find('[data-cf=required]').prop('checked', !!f.required);
      $row.find('[data-cf=options]').val((f.options || []).join('\n'));
    }
    $row.find('.ar-options').toggleClass('d-none', $row.find('[data-cf=type]').val() !== 'select');
    $row.find('[data-cf=required]').attr('id', 'ar_cfreq_' + Math.random().toString(36).slice(2))
      .next('label').attr('for', function () { return $(this).prev().attr('id'); });
    $('#ar_custom').append($row);
    updateCustomState();
  }

  function init() {
    $card = $('#accessRequestCard');
    if (!$card.length) { return; }

    $('#ar_enabled').prop('checked', !!cfg.enabled);

    // Canaux : les types configurés d'abord (dans l'ordre), les autres ensuite.
    var $box = $('#ar_channels');
    var $err = $box.children('.ar-err');
    (cfg.channels || []).forEach(function (ch) {
      var $row = $channel(ch.type);
      if (!$row.length) { return; }
      $row.find('.ar-channel-on').prop('checked', true);
      $row.find('[data-sub]').each(function () { $(this).val(ch[$(this).data('sub')] || ''); });
      $box.append($row);
    });
    $card.find('.ar-channel').not(function () { return $(this).find('.ar-channel-on').is(':checked'); })
      .each(function () { $box.append(this); });
    $box.append($err);
    $card.find('.ar-channel').each(function () { toggleSub($(this)); });
    updateMoves();

    $box.on('change', '.ar-channel-on', function () { toggleSub($(this).closest('.ar-channel')); });
    $box.on('click', '.ar-up', function () {
      var $r = $(this).closest('.ar-channel'); $r.prev('.ar-channel').before($r); updateMoves();
    });
    $box.on('click', '.ar-down', function () {
      var $r = $(this).closest('.ar-channel'); $r.next('.ar-channel').after($r); updateMoves();
    });

    // Champs réglables.
    $card.find('.ar-field').each(function () {
      var name = $(this).data('field');
      $(this).find('input[value="' + (cfg.fields && cfg.fields[name] || 'optional') + '"]').prop('checked', true);
    });

    // Champs personnalisés.
    (cfg.custom_fields || []).forEach(addCustom);
    $('#ar_addCustom').on('click', function () { if ($card.find('.ar-custom-row').length < MAX_CUSTOM) { addCustom(null); } });
    $('#ar_custom').on('click', '.ar-remove-custom', function () {
      $(this).closest('.ar-custom-row').remove(); updateCustomState();
    });
    $('#ar_custom').on('change', '[data-cf=type]', function () {
      $(this).closest('.ar-custom-row').find('.ar-options').toggleClass('d-none', $(this).val() !== 'select');
    });

    // Textes : la valeur par défaut sert de placeholder et n'est pas stockée.
    ['button_label', 'title', 'intro', 'confirmation'].forEach(function (k) {
      var $el = $('#ar_' + k);
      $el.attr('placeholder', defaults[k] || '');
      $el.val(cfg[k] && cfg[k] !== defaults[k] ? cfg[k] : '');
    });
  }

  function lines(text) {
    return String(text || '').split('\n').map(function (l) { return $.trim(l); }).filter(Boolean);
  }

  function collect() {
    var channels = [];
    $card.find('.ar-channel').each(function () {
      var $row = $(this);
      if (!$row.find('.ar-channel-on').is(':checked')) { return; }
      var ch = { type: $row.data('type') };
      var idx = channels.length;
      $row.find('[data-sub]').each(function () {
        ch[$(this).data('sub')] = $.trim($(this).val());
        $(this).attr('data-path', 'channels.' + idx + '.' + $(this).data('sub'));
      });
      channels.push(ch);
    });

    var fields = {};
    $card.find('.ar-field').each(function () {
      fields[$(this).data('field')] = $(this).find('input:checked').val() || 'optional';
    });

    var custom = $card.find('.ar-custom-row').map(function (i) {
      var $r = $(this);
      var type = $r.find('[data-cf=type]').val();
      $r.attr('data-path', 'custom_fields.' + i);
      var f = {
        label: $.trim($r.find('[data-cf=label]').val()),
        type: type,
        required: $r.find('[data-cf=required]').is(':checked')
      };
      if ($r.data('key')) { f.key = $r.data('key'); }
      if (type === 'select') { f.options = lines($r.find('[data-cf=options]').val()); }
      return f;
    }).get();

    return {
      enabled: $('#ar_enabled').is(':checked'),
      button_label: $.trim($('#ar_button_label').val()),
      title: $.trim($('#ar_title').val()),
      intro: $.trim($('#ar_intro').val()),
      confirmation: $.trim($('#ar_confirmation').val()),
      channels: channels,
      fields: fields,
      custom_fields: custom
    };
  }

  function clearErrors() {
    $card.find('.ar-err').text('');
    $card.find('.is-invalid').removeClass('is-invalid');
  }

  function showErrors(errors) {
    $.each(errors, function (key, messages) {
      if (key.indexOf('access_request.') !== 0) { return; }
      var path = key.substring('access_request.'.length);
      var text = [].concat(messages).join(' ');
      var $target = $card.find('.ar-err[data-path="' + path + '"]');
      if ($target.length) { $target.text(text); return; }
      var $input = $card.find('[data-path="' + path + '"]').not('.ar-err');
      if ($input.length && !$input.hasClass('ar-custom-row')) {
        $input.addClass('is-invalid');
        var $slot = $input.parent().find('.ar-err');
        if (!$slot.length) { $slot = $('<div class="text-danger small ar-err"></div>').insertAfter($input); }
        $slot.text(text);
        return;
      }
      // custom_fields.N.xxx -> message sous la ligne N ; sinon sous la section.
      var m = path.match(/^custom_fields\.(\d+)/);
      var $row = m ? $card.find('.ar-custom-row[data-path="custom_fields.' + m[1] + '"]') : $();
      var $slotRow = $row.length ? $row.find('[data-cf-err]') : $card.find('.ar-err[data-path="custom_fields"]');
      $slotRow.text(($slotRow.text() ? $slotRow.text() + ' ' : '') + text);
    });
  }

  $(init);
  window.AccessRequestForm = { collect: collect, clearErrors: clearErrors, showErrors: showErrors };
})(jQuery);
