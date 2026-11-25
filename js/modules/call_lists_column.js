/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    js/modules/call_lists_column.js
 * \ingroup reedcrm
 * \brief   Call lists column of the opportunity list: checklist adding the opportunity to a list or taking it off one
 */

'use strict';

window.reedcrm = window.reedcrm || {};
window.reedcrm.callListsColumn = window.reedcrm.callListsColumn || {};

window.reedcrm.callListsColumn.pressStartedInside = false;

window.reedcrm.callListsColumn.init = function () {
  $(document)
    .off('.reedcrmCallListsColumn')
    .on('click.reedcrmCallListsColumn', '.reedcrm-call-lists-edit', window.reedcrm.callListsColumn.toggle)
    .on('change.reedcrmCallListsColumn', '.reedcrm-call-lists-popover input[type="checkbox"]', window.reedcrm.callListsColumn.onToggleList)
    .on('input.reedcrmCallListsColumn', '.reedcrm-call-lists-search', window.reedcrm.callListsColumn.onSearch)
    .on('mousedown.reedcrmCallListsColumn', window.reedcrm.callListsColumn.onPress)
    .on('click.reedcrmCallListsColumn', window.reedcrm.callListsColumn.onOutsideClick)
    .on('keydown.reedcrmCallListsColumn', window.reedcrm.callListsColumn.onKeydown);
  $(window).off('.reedcrmCallListsColumn').on('resize.reedcrmCallListsColumn', window.reedcrm.callListsColumn.close);
};

window.reedcrm.callListsColumn.settings = function () {
  return $('#reedcrm-call-lists-settings');
};

window.reedcrm.callListsColumn.toggle = function (event) {
  event.preventDefault();

  var $cell    = $(this).closest('.reedcrm-call-lists-cell');
  var $current = $('.reedcrm-call-lists-popover');
  var wasOpen  = $current.length && $current.data('cell') && $current.data('cell').is($cell);

  window.reedcrm.callListsColumn.close();
  if (!wasOpen) {
    window.reedcrm.callListsColumn.open($cell, $(this));
  }
};

window.reedcrm.callListsColumn.open = function ($cell, $button) {
  var $settings = window.reedcrm.callListsColumn.settings();
  var lists     = $settings.data('lists') || [];
  var canUnlink = parseInt($settings.data('can-unlink'), 10) === 1;

  // The phone prompt of the widget of the cards reads its labels and the element on its wrapper
  var $popover = $('<div class="reedcrm-call-lists-popover"></div>');
  $.each(['enter-phone-label', 'enter-phone-generic-label', 'phone-required-label', 'phone-invalid-label', 'save-label', 'cancel-label'], function (index, name) {
    $popover.attr('data-' + name, $settings.attr('data-' + name) || '');
  });
  $popover.attr('data-element-type', $cell.data('element-type')).attr('data-element-id', $cell.data('element-id'));
  $popover.data('cell', $cell);

  $popover.append($('<div class="reedcrm-call-lists-popover-title"></div>').text($settings.data('title-label')));

  if (!lists.length) {
    $popover.append($('<div class="opacitymedium"></div>').text($settings.data('empty-label')));
  } else {
    if (lists.length > 8) {
      $popover.append('<input type="search" class="reedcrm-call-lists-search" autocomplete="off">');
    }
    var $options = $('<div class="reedcrm-call-lists-options"></div>');
    $.each(lists, function (index, list) {
      var onList    = $cell.find('.reedcrm-call-list-pill[data-call-list-id="' + list.id + '"]').length > 0;
      var $checkbox = $('<input type="checkbox">').val(list.id).prop('checked', onList);
      var $option   = $('<label class="reedcrm-call-lists-option"></label>').attr('title', list.title).append($checkbox, $('<span></span>').text(list.label));
      if (onList && !canUnlink) {
        $checkbox.prop('disabled', true);
        $option.attr('title', $settings.data('unlink-forbidden-label'));
      }
      $options.append($option);
    });
    $popover.append($options);
  }

  $('body').append($popover);
  window.reedcrm.callListsColumn.place($popover, $button);
  $popover.find('.reedcrm-call-lists-search').trigger('focus');

  // The list scrolls sideways in its own box: the popover, placed on the page, follows its button
  $cell.closest('.div-table-responsive, .div-table-responsive-no-min').on('scroll.reedcrmCallListsColumn', function () {
    window.reedcrm.callListsColumn.place($popover, $button);
  });
};

window.reedcrm.callListsColumn.place = function ($popover, $button) {
  var offset   = $button.offset();
  var maxLeft  = $(window).scrollLeft() + $(window).width() - $popover.outerWidth() - 8;
  $popover.css({
    top: offset.top + $button.outerHeight() + 4,
    left: Math.max(8, Math.min(offset.left - 8, maxLeft))
  });
};

window.reedcrm.callListsColumn.close = function () {
  $('.reedcrm-call-lists-popover').remove();
  $('.div-table-responsive, .div-table-responsive-no-min').off('scroll.reedcrmCallListsColumn');
};

window.reedcrm.callListsColumn.onPress = function (event) {
  window.reedcrm.callListsColumn.pressStartedInside = $(event.target).closest('.reedcrm-call-lists-popover, .reedcrm-call-lists-edit').length > 0;
};

window.reedcrm.callListsColumn.onOutsideClick = function (event) {
  // A selection started in the popover and released outside sends the click to the common ancestor
  if (window.reedcrm.callListsColumn.pressStartedInside) {
    window.reedcrm.callListsColumn.pressStartedInside = false;
    return;
  }
  if (!$(event.target).closest('.reedcrm-call-lists-popover, .reedcrm-call-lists-edit').length) {
    window.reedcrm.callListsColumn.close();
  }
};

window.reedcrm.callListsColumn.onKeydown = function (event) {
  if (event.which === 27 && $('.reedcrm-call-lists-popover').length && !$(event.target).closest('.reedcrm-call-list-phone-prompt').length) {
    window.reedcrm.callListsColumn.close();
  }
};

window.reedcrm.callListsColumn.onSearch = function () {
  var search = $.trim($(this).val()).toLowerCase();
  $(this).closest('.reedcrm-call-lists-popover').find('.reedcrm-call-lists-option').each(function () {
    $(this).toggleClass('hidden', search !== '' && $(this).text().toLowerCase().indexOf(search) === -1);
  });
};

window.reedcrm.callListsColumn.onToggleList = function () {
  var $checkbox = $(this);
  var $popover  = $checkbox.closest('.reedcrm-call-lists-popover');
  var $cell     = $popover.data('cell');
  var listId    = parseInt($checkbox.val(), 10);
  var label     = $checkbox.siblings('span').text();
  var wanted    = $checkbox.prop('checked');

  // The box follows the server: it only changes once the line is created or taken off
  $checkbox.prop('checked', !wanted).prop('disabled', true);

  if (wanted) {
    window.reedcrm.callListsColumn.addToList($popover, $cell, $checkbox, listId, label);
  } else {
    window.reedcrm.callListsColumn.removeFromList($cell, $checkbox, listId);
  }
};

window.reedcrm.callListsColumn.post = function (url, data) {
  var formData = new FormData();
  $.each(data, function (name, value) {
    formData.append(name, value);
  });
  formData.append('token', $('input[name="token"]').first().val() || '');

  return fetch(url, { method: 'POST', body: formData }).then(function (response) {
    return response.json();
  });
};

window.reedcrm.callListsColumn.addToList = function ($popover, $cell, $checkbox, listId, label) {
  var $settings = window.reedcrm.callListsColumn.settings();
  var addUrl    = $settings.data('add-url');
  var onAdded   = function (result) {
    $checkbox.prop('checked', true);
    window.reedcrm.callListsColumn.addPill($cell, listId, label, result.line_id);
    if (parseInt($settings.data('can-unlink'), 10) !== 1) {
      $checkbox.prop('disabled', true);
    }
  };

  window.reedcrm.callListsColumn.post(addUrl, { element_type: $cell.data('element-type'), element_id: $cell.data('element-id'), call_list_id: listId })
    .then(function (result) {
      $checkbox.prop('disabled', false);
      if (result.success) {
        onAdded(result);
        $.jnotify(result.message);
      } else if (result.need_phone && window.reedcrm.call_list_widget) {
        window.reedcrm.call_list_widget.showPhonePrompt($popover, addUrl, listId, !!result.can_save_on_project, onAdded);
      } else {
        $.jnotify(result.message, 'error');
      }
    })
    .catch(function () {
      $checkbox.prop('disabled', false);
      $.jnotify('Erreur réseau', 'error');
    });
};

window.reedcrm.callListsColumn.removeFromList = function ($cell, $checkbox, listId) {
  var $settings = window.reedcrm.callListsColumn.settings();
  var $pill     = $cell.find('.reedcrm-call-list-pill[data-call-list-id="' + listId + '"]');

  window.reedcrm.callListsColumn.post($settings.data('unlink-url'), { line_id: $pill.data('line-id') })
    .then(function (result) {
      $checkbox.prop('disabled', false);
      if (result.success) {
        $checkbox.prop('checked', false);
        $pill.remove();
      } else {
        $.jnotify(result.error, 'error');
      }
    })
    .catch(function () {
      $checkbox.prop('disabled', false);
      $.jnotify('Erreur réseau', 'error');
    });
};

window.reedcrm.callListsColumn.addPill = function ($cell, listId, label, lineId) {
  var $settings = window.reedcrm.callListsColumn.settings();
  var lists     = $settings.data('lists') || [];
  var $pills    = $cell.find('.reedcrm-call-lists-pills');
  var title     = label;
  $.each(lists, function (index, list) {
    if (list.id === listId) {
      title = list.title;
    }
  });
  var $pill     = $('<a class="reedcrm-call-list-pill"></a>')
    .attr('href', $settings.data('card-url') + '?id=' + listId)
    .attr('data-call-list-id', listId)
    .attr('data-line-id', lineId)
    .attr('title', title)
    .append('<span class="fas fa-headset"></span>', document.createTextNode(label));

  // Same order as the checklist, the lists the checklist does not offer (archived) last
  var order = $.map(lists, function (list) {
    return list.id;
  });
  var rank  = order.indexOf(listId);
  var $next = $pills.children('.reedcrm-call-list-pill').filter(function () {
    var otherRank = order.indexOf(parseInt($(this).attr('data-call-list-id'), 10));
    return otherRank === -1 || otherRank > rank;
  }).first();

  if ($next.length) {
    $pill.insertBefore($next);
  } else {
    $pills.append($pill);
  }
};
