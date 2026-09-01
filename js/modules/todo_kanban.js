/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

"use strict";

/**
 * \file    js/modules/todo_kanban.js
 * \ingroup reedcrm
 * \brief   Kanban board of the todo tab: drag & drop between event statuses and
 *          inline edition of the agenda events (label, dates, owner, assigned users).
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.todoKanban = {};

/**
 * Pre-rendered cards awaiting injection, keyed by column. Populated by initLazyLoad().
 *
 * @type {Object}
 */
window.reedcrm.todoKanban.deferredCards = {};

/**
 * Number of cards injected per "load more" click (matches the server page size default).
 *
 * @type {number}
 */
window.reedcrm.todoKanban.chunkSize = 30;

/**
 * Init: only the todo board wires this module up.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.init = function () {
  if (!$('.todo-board').length) {
    return;
  }

  window.reedcrm.todoKanban.event();
  window.reedcrm.todoKanban.initLazyLoad();
  window.reedcrm.todoKanban.initSortable();
  window.reedcrm.todoKanban.initSettings();
};

/**
 * Register the delegated events of the board.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.event = function () {
  $(document).on('click', '.todo-editable-label', window.reedcrm.todoKanban.editLabel);
  $(document).on('click', '.todo-editable-date', window.reedcrm.todoKanban.editDate);
  $(document).on('mousedown', '.todo-progress-bar.todo-editable-progress', window.reedcrm.todoKanban.dragPercent);

  $(document).on('click', '.todo-initial-owner', window.reedcrm.todoKanban.toggleOwnerDropdown);
  $(document).on('click', '.todo-owner-dropdown .todo-user-option', window.reedcrm.todoKanban.selectOwner);
  $(document).on('click', '.todo-add-assigned-btn', window.reedcrm.todoKanban.toggleAssignedDropdown);
  $(document).on('click', '.todo-assigned-dropdown .todo-user-option', window.reedcrm.todoKanban.addAssigned);
  $(document).on('click', '.todo-remove-assigned', window.reedcrm.todoKanban.removeAssigned);

  $(document).on('input', '.todo-owner-search, .todo-assigned-search', window.reedcrm.todoKanban.filterOptions);

  $(document).on('click', '.todo-load-more', function (event) {
    event.preventDefault();
    event.stopPropagation();
    window.reedcrm.todoKanban.loadMore($(this).data('column'));
  });

  // Close every open dropdown when clicking outside of one
  $(document).on('click', function () {
    window.reedcrm.todoKanban.closeDropdowns();
  });
  $(document).on('click', '.todo-owner-dropdown, .todo-assigned-dropdown', function (event) {
    event.stopPropagation();
  });

  // Interacting with a control of the card must never start a drag
  $(document).on('mousedown', '.todo-editable-label, .todo-editable-date, .todo-card-progress, .todo-initial-owner, .todo-owner-dropdown, .todo-assigned-dropdown, .todo-add-assigned-btn, .todo-initial-wrapper, .todo-remove-assigned, .todo-load-more, .todo-card-ref, .todo-link-badge', function (event) {
    event.stopPropagation();
  });
};

/**
 * Close every open owner / assigned users dropdown.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.closeDropdowns = function () {
  $('.todo-owner-dropdown.visible, .todo-assigned-dropdown.visible').each(function () {
    $(this).removeClass('visible');
    $(this).closest('.todo-card').removeClass('todo-card-dropdown-open');
  });
};

/**
 * Send an update of the board to the page controller.
 *
 * @param  {Object}   params    Query parameters, action included
 * @param  {jQuery}   $card     Card to flag while the call runs
 * @param  {Function} onSuccess Called with the JSON payload when the update went through
 * @returns {void}
 */
window.reedcrm.todoKanban.request = function (params, $card, onSuccess) {
  var separator = window.saturne.toolbox.getQuerySeparator(document.URL);

  params.token = window.saturne.toolbox.getToken();
  $card.addClass('todo-card-saving');

  $.ajax({
    url: document.URL + separator + $.param(params),
    type: 'POST',
    dataType: 'json',
    success: function (response) {
      $card.removeClass('todo-card-saving');
      if (response && response.success) {
        window.reedcrm.todoKanban.flag($card, 'todo-card-saved', 2000);
        if (onSuccess) {
          onSuccess(response);
        }
      } else {
        window.reedcrm.todoKanban.flag($card, 'todo-card-error', 3000);
      }
    },
    error: function () {
      $card.removeClass('todo-card-saving');
      window.reedcrm.todoKanban.flag($card, 'todo-card-error', 3000);
    }
  });
};

/**
 * Flag a card for a while (saved / error feedback).
 *
 * @param  {jQuery} $card    Card to flag
 * @param  {string} cssClass Class to add then remove
 * @param  {number} delay    Milliseconds the flag stays on
 * @returns {void}
 */
window.reedcrm.todoKanban.flag = function ($card, cssClass, delay) {
  $card.addClass(cssClass);
  setTimeout(function () {
    $card.removeClass(cssClass);
  }, delay);
};

/**
 * Inline edition of the label of an event.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.editLabel = function (event) {
  event.stopPropagation();

  var $label = $(this);
  if ($label.find('textarea').length) {
    return;
  }

  var originalText = $label.text().trim();
  var $card        = $label.closest('.todo-card');
  var eventId      = $card.data('event-id');
  var $textarea    = $('<textarea class="todo-inline-edit"></textarea>').val(originalText);

  $label.empty().append($textarea);
  $textarea.trigger('focus').select();

  $textarea.on('blur', function () {
    var newLabel = $textarea.val().trim();
    if (newLabel === '' || newLabel === originalText) {
      $label.text(originalText);
      return;
    }

    $label.text(newLabel);
    window.reedcrm.todoKanban.request(
      {action: 'updateEventLabel', event_id: eventId, new_label: newLabel},
      $card,
      null
    );
  });

  $textarea.on('keydown', function (keyEvent) {
    if (keyEvent.key === 'Enter' && !keyEvent.shiftKey) {
      keyEvent.preventDefault();
      $textarea.trigger('blur');
    } else if (keyEvent.key === 'Escape') {
      $textarea.off('blur');
      $label.text(originalText);
    }
  });
};

/**
 * Inline edition of a date of an event, through the native picker of the browser.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.editDate = function (event) {
  event.stopPropagation();

  var $date = $(this);
  if ($date.find('input').length) {
    return;
  }

  var $value       = $date.find('.todo-date-value');
  var $card        = $date.closest('.todo-card');
  var eventId      = $card.data('event-id');
  var field        = $date.data('field');
  var rawValue     = $date.data('raw') || '';
  var originalText = $value.text();
  var $input       = $('<input class="todo-date-input">').attr('type', $date.data('input-type') || 'datetime-local').val(rawValue);

  $value.replaceWith($input);
  $input.trigger('focus');

  // Typing rewrites the field segment by segment and the browser fires a `change` on
  // every intermediate value: hold the save until the user leaves the field or validates.
  var isTyping    = false;
  var typingTimer = null;
  var isDone      = false;

  function restore(text) {
    $input.replaceWith($('<span class="todo-date-value"></span>').text(text));
  }

  function save() {
    if (isDone) {
      return;
    }
    isDone = true;
    clearTimeout(typingTimer);
    $input.off('blur change keydown');

    var value = $input.val();

    // A half typed date reads as an empty value, do not erase what was being edited
    if (!value && $input[0].validity && $input[0].validity.badInput) {
      restore(originalText);
      return;
    }
    if (value === rawValue) {
      restore(originalText);
      return;
    }
    if (field === 'date_start' && !value) {
      restore(originalText);
      return;
    }

    // An end date before the start date is refused before the round trip
    var otherRaw = $date.closest('.todo-dates-row').find('.todo-date').not($date).data('raw') || '';
    if (value && otherRaw && ((field === 'date_end' && value < otherRaw) || (field === 'date_start' && otherRaw && value > otherRaw))) {
      window.reedcrm.todoKanban.flag($card, 'todo-card-error', 3000);
      restore(originalText);
      return;
    }

    restore(originalText);
    window.reedcrm.todoKanban.request(
      {action: 'updateEventDate', event_id: eventId, field: field, value: value},
      $card,
      function (response) {
        $date.data('raw', response.raw);
        $date.find('.todo-date-value').text(response.formatted || '-');
      }
    );
  }

  // Picking a date in the native calendar fires `change` without any keystroke
  $input.on('change', function () {
    if (!isTyping) {
      save();
    }
  });
  $input.on('blur', save);
  $input.on('keydown', function (keyEvent) {
    if (keyEvent.key === 'Escape') {
      isDone = true;
      clearTimeout(typingTimer);
      $input.off('blur change');
      restore(originalText);
      return;
    }
    if (keyEvent.key === 'Enter') {
      keyEvent.preventDefault();
      save();
      return;
    }
    isTyping = true;
    clearTimeout(typingTimer);
    typingTimer = setTimeout(function () {
      isTyping = false;
    }, 1500);
  });
};

/**
 * Drag the progress bar of a card to set the percentage of the event.
 *
 * @param  {Event} event Mouse down event
 * @returns {void}
 */
window.reedcrm.todoKanban.dragPercent = function (event) {
  event.preventDefault();
  event.stopPropagation();

  var $bar     = $(this);
  var $card    = $bar.closest('.todo-card');
  var barWidth = $bar.width();
  var percent  = 0;

  function computePercent(pageX) {
    var value = Math.round(((pageX - $bar.offset().left) / barWidth) * 100);
    return Math.max(0, Math.min(100, value));
  }

  percent = computePercent(event.pageX);
  window.reedcrm.todoKanban.paintCard($card, percent);
  $bar.addClass('todo-bar-dragging');

  $(document).on('mousemove.todoPercent', function (moveEvent) {
    percent = computePercent(moveEvent.pageX);
    window.reedcrm.todoKanban.paintCard($card, percent);
  });

  $(document).on('mouseup.todoPercent', function () {
    $(document).off('mousemove.todoPercent mouseup.todoPercent');
    $bar.removeClass('todo-bar-dragging');
    window.reedcrm.todoKanban.moveToColumn($card, percent);
    window.reedcrm.todoKanban.savePercent($card, percent);
  });
};

/**
 * Repaint a card with the colour and the percentage of the column it belongs to.
 *
 * @param  {jQuery} $card   Card to repaint
 * @param  {number} percent New percentage (-1 for an event carrying none)
 * @returns {void}
 */
window.reedcrm.todoKanban.paintCard = function ($card, percent) {
  var $column = window.reedcrm.todoKanban.findColumn($card, percent);
  var color   = $column ? $column.data('color') : $card.closest('.todo-column').data('color');

  $card.data('percent', percent).attr('data-percent', percent);
  $card.find('.todo-progress-fill').css({width: Math.max(0, percent) + '%', background: color});
  $card.find('.todo-progress-text').text(percent >= 0 ? percent + '%' : String($('.todo-board').data('na-label') || ''));
  $card.find('.todo-initial-owner').css('background', color);
};

/**
 * Return the column a card belongs to, mirroring reedcrmTodoGetColumnForEvent(): a relaunch
 * stays in its own backlog as long as it is neither done nor dropped.
 *
 * @param  {jQuery} $card   Card to place
 * @param  {number} percent Percentage of the event
 * @returns {jQuery|null}    Matching column
 */
window.reedcrm.todoKanban.findColumn = function ($card, percent) {
  var code   = String($card.data('event-code') || '');
  var $found = null;

  $('.todo-column').each(function () {
    var $column     = $(this);
    var columnCode  = String($column.data('code') || '');

    if (columnCode) {
      if (columnCode === code && percent >= 0 && percent < 100) {
        $found = $column;
        return false;
      }
      return true;
    }

    var minimum = parseInt($column.data('percent-min'), 10);
    var maximum = parseInt($column.data('percent-max'), 10);
    if (!isNaN(minimum) && percent >= minimum && percent <= maximum) {
      $found = $column;
      return false;
    }

    return true;
  });

  return $found;
};

/**
 * Move a card to the column matching a percentage.
 *
 * @param  {jQuery} $card   Card to move
 * @param  {number} percent Percentage of the event
 * @returns {void}
 */
window.reedcrm.todoKanban.moveToColumn = function ($card, percent) {
  var $target = window.reedcrm.todoKanban.findColumn($card, percent);

  if (!$target || $target[0] === $card.closest('.todo-column')[0]) {
    return;
  }

  var $sourceBody = $card.closest('.todo-column-body');
  var $targetBody = $target.find('.todo-column-body');

  $card.detach();
  $targetBody.find('.todo-empty').remove();
  $targetBody.append($card);
  // Keep the "load more" button anchored at the bottom of the column
  $targetBody.append($targetBody.children('.todo-load-more'));

  if (!$sourceBody.children('.todo-card').length && !$sourceBody.children('.todo-load-more').length) {
    $sourceBody.append($('<div class="todo-empty"></div>').text($('.todo-board').data('empty-label') || ''));
  }

  window.reedcrm.todoKanban.updateCounts();
};

/**
 * Save the percentage of an event.
 *
 * @param  {jQuery} $card   Card holding the event
 * @param  {number} percent New percentage
 * @returns {void}
 */
window.reedcrm.todoKanban.savePercent = function ($card, percent) {
  window.reedcrm.todoKanban.request(
    {action: 'updateEventPercent', event_id: $card.data('event-id'), new_percent: percent},
    $card,
    null
  );
};

/**
 * Open or close the owner selector of a card.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.toggleOwnerDropdown = function (event) {
  var $dropdown = $(this).closest('.todo-owner-wrapper').find('.todo-owner-dropdown');
  if (!$dropdown.length) {
    return;
  }

  event.stopPropagation();
  window.reedcrm.todoKanban.openDropdown($dropdown, '.todo-owner-search');
};

/**
 * Open or close the assigned users selector of a card.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.toggleAssignedDropdown = function (event) {
  event.preventDefault();
  event.stopPropagation();

  window.reedcrm.todoKanban.openDropdown($(this).siblings('.todo-assigned-dropdown'), '.todo-assigned-search');
};

/**
 * Show one dropdown, closing the others and focusing its search field.
 *
 * @param  {jQuery} $dropdown     Dropdown to toggle
 * @param  {string} searchSelector Selector of its search field
 * @returns {void}
 */
window.reedcrm.todoKanban.openDropdown = function ($dropdown, searchSelector) {
  var $card = $dropdown.closest('.todo-card');

  $('.todo-owner-dropdown.visible, .todo-assigned-dropdown.visible').not($dropdown).each(function () {
    $(this).removeClass('visible');
    $(this).closest('.todo-card').removeClass('todo-card-dropdown-open');
  });

  $dropdown.toggleClass('visible');
  if ($dropdown.hasClass('visible')) {
    window.reedcrm.todoKanban.fillDropdown($dropdown);
    $card.addClass('todo-card-dropdown-open');
    $dropdown.find(searchSelector).val('').trigger('input').trigger('focus');
  } else {
    $card.removeClass('todo-card-dropdown-open');
  }
};

/**
 * Fill a dropdown from the list of users rendered once for the whole board, then flag
 * the users already on the event so they are not offered twice.
 *
 * @param  {jQuery} $dropdown Dropdown being opened
 * @returns {void}
 */
window.reedcrm.todoKanban.fillDropdown = function ($dropdown) {
  var $options = $dropdown.children('.todo-user-options');
  var isOwner  = $dropdown.hasClass('todo-owner-dropdown');

  if (!$options.data('filled')) {
    $options.html($('#todoUserOptions').html()).data('filled', 1);
    // Only an owner may be cleared, a user is unassigned from his own chip
    if (!isOwner) {
      $options.children('.todo-user-option-none').remove();
    }
  }

  // The owner is never offered as an assigned user, he already carries his own chip
  var $card    = $dropdown.closest('.todo-card');
  var ownerId  = parseInt($card.find('.todo-owner-wrapper').attr('data-current-user'), 10) || 0;
  var takenIds = [ownerId];

  if (!isOwner) {
    $card.find('.todo-initial-wrapper').each(function () {
      takenIds.push(parseInt($(this).data('user-id'), 10));
    });
  }

  $options.children('.todo-user-option').each(function () {
    var $option = $(this);
    var isTaken = takenIds.indexOf(parseInt($option.data('value'), 10)) !== -1;

    $option.toggleClass('assigned', isTaken);
    $option.children('.todo-option-check').remove();
    if (isTaken) {
      $option.append('<i class="fas fa-check todo-option-check"></i>');
    }
  });
};

/**
 * Filter the options of a dropdown on the typed text.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.filterOptions = function () {
  var query = $(this).val().toLowerCase();

  $(this).siblings('.todo-user-options').children('.todo-user-option').each(function () {
    var search = String($(this).data('search') || '');
    $(this).toggleClass('hidden', query !== '' && search.indexOf(query) === -1);
  });
};

/**
 * Set the owner of an event.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.selectOwner = function (event) {
  event.stopPropagation();

  var $option = $(this);
  if ($option.hasClass('assigned')) {
    return;
  }

  var $dropdown = $option.closest('.todo-owner-dropdown');
  var $wrapper  = $dropdown.closest('.todo-owner-wrapper');
  var $card     = $option.closest('.todo-card');
  var userId    = parseInt($option.data('value'), 10);
  var fullname  = $option.text().trim();
  var initials  = userId > 0 ? String($option.data('initial') || fullname.substring(0, 2).toUpperCase()) : '?';

  $dropdown.children('.todo-user-options').children('.assigned').removeClass('assigned').children('.todo-option-check').remove();
  $option.addClass('assigned').append('<i class="fas fa-check todo-option-check"></i>');

  $dropdown.removeClass('visible');
  $card.removeClass('todo-card-dropdown-open');
  $wrapper.attr('data-current-user', userId);
  $wrapper.find('.todo-initial-owner')
    .text(initials)
    .attr('title', userId > 0 ? fullname : '')
    .toggleClass('todo-initial-empty', userId === 0);

  // The owner is also an assigned user: he keeps a single chip, his own
  $card.find('.todo-initial-wrapper[data-user-id="' + userId + '"]').remove();

  window.reedcrm.todoKanban.request(
    {action: 'updateEventOwner', event_id: $card.data('event-id'), user_id: userId},
    $card,
    null
  );
};

/**
 * Assign a user to an event.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.addAssigned = function (event) {
  event.stopPropagation();

  var $option = $(this);
  if ($option.hasClass('assigned')) {
    return;
  }

  var $dropdown = $option.closest('.todo-assigned-dropdown');
  var $card     = $option.closest('.todo-card');
  var eventId   = $card.data('event-id');
  var userId    = parseInt($option.data('value'), 10);

  $dropdown.removeClass('visible');
  $card.removeClass('todo-card-dropdown-open');

  window.reedcrm.todoKanban.request(
    {action: 'addEventAssigned', event_id: eventId, user_id: userId},
    $card,
    function (response) {
      var user     = response.user || {};
      var $wrapper = $('<span class="todo-initial-wrapper"></span>').attr({'data-event-id': eventId, 'data-user-id': userId});

      $wrapper.append($('<span class="todo-initial todo-initial-assigned"></span>').attr('title', user.fullname || '').text(user.initials || '??'));
      $wrapper.append($('<span class="todo-remove-assigned">&times;</span>'));
      $card.find('.todo-add-assigned-wrapper').before($wrapper);

      $option.addClass('assigned').append('<i class="fas fa-check todo-option-check"></i>');
    }
  );
};

/**
 * Unassign a user from an event.
 *
 * @param  {Event} event Click event
 * @returns {void}
 */
window.reedcrm.todoKanban.removeAssigned = function (event) {
  event.preventDefault();
  event.stopPropagation();

  var $wrapper = $(this).closest('.todo-initial-wrapper');
  var $card    = $wrapper.closest('.todo-card');
  var userId   = $wrapper.data('user-id');

  $wrapper.css('opacity', '0.4');

  window.reedcrm.todoKanban.request(
    {action: 'removeEventAssigned', event_id: $card.data('event-id'), user_id: userId},
    $card,
    function () {
      $wrapper.remove();
      $card.find('.todo-user-option[data-value="' + userId + '"]').removeClass('assigned').find('.todo-option-check').remove();
    }
  );

  // The chip is restored when the call failed
  setTimeout(function () {
    $wrapper.css('opacity', '1');
  }, 1500);
};

/**
 * Make the columns of the board sortable and save the status a dropped card lands on.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.initSortable = function () {
  if (!$('.todo-board').data('editable') || !$('.todo-sortable').length) {
    return;
  }

  $('.todo-sortable').sortable({
    connectWith: '.todo-sortable',
    items: '> .todo-card',
    placeholder: 'todo-card-placeholder',
    tolerance: 'pointer',
    cursor: 'grabbing',
    cancel: '.todo-editable-label, .todo-inline-edit, .todo-editable-date, .todo-date-input, .todo-card-progress, .todo-progress-bar, .todo-initial-owner, .todo-owner-dropdown, .todo-owner-search, .todo-assigned-dropdown, .todo-assigned-search, .todo-user-option, .todo-add-assigned-btn, .todo-initial-wrapper, .todo-remove-assigned, .todo-load-more, .todo-card-ref, .todo-link-badge',
    receive: function (event, ui) {
      var $card   = ui.item;
      var $column = $(this).closest('.todo-column');
      var minimum = parseInt($column.data('percent-min'), 10);
      var maximum = parseInt($column.data('percent-max'), 10);
      var percent = parseInt($card.data('percent'), 10);

      // A percentage already inside the range of the column is kept, otherwise the card
      // takes the lowest value of the column (0 for "to do", 100 for "done", -1 for "n/a")
      if (isNaN(percent) || percent < minimum || percent > maximum) {
        percent = minimum;
      }

      // A relaunch backlog holds cards on their code, no percentage puts a card in it, and
      // a relaunch only leaves its backlog once done or dropped. Any other move would be
      // undone by the next reload: send the card back where it came from instead.
      var $wouldLand = window.reedcrm.todoKanban.findColumn($card, percent);
      if (isNaN(minimum) || !$wouldLand || $wouldLand[0] !== $column[0]) {
        ui.sender.sortable('cancel');
        return;
      }

      window.reedcrm.todoKanban.paintCard($card, percent);

      $column.find('.todo-empty').remove();
      $(this).append($(this).children('.todo-load-more'));

      var $source = ui.sender;
      if (!$source.children('.todo-card').length && !$source.children('.todo-load-more').length) {
        $source.append($('<div class="todo-empty"></div>').text($('.todo-board').data('empty-label') || ''));
      }

      window.reedcrm.todoKanban.updateCounts();
      window.reedcrm.todoKanban.savePercent($card, percent);
    }
  });
};

/**
 * Read the deferred card payloads emitted per column and stash them in memory.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.initLazyLoad = function () {
  window.reedcrm.todoKanban.deferredCards = {};

  $('.todo-deferred-data').each(function () {
    var columnKey = $(this).data('column');
    try {
      window.reedcrm.todoKanban.deferredCards[columnKey] = JSON.parse($(this).text()) || [];
    } catch (error) {
      window.reedcrm.todoKanban.deferredCards[columnKey] = [];
    }
    // Free the inline payload from the DOM once parsed
    $(this).remove();
  });
};

/**
 * Inject the next chunk of deferred cards into a column.
 *
 * @param  {string} columnKey Key of the column
 * @returns {void}
 */
window.reedcrm.todoKanban.loadMore = function (columnKey) {
  var remaining = window.reedcrm.todoKanban.deferredCards[columnKey] || [];
  if (!remaining.length) {
    return;
  }

  var $button = $('.todo-column[data-column="' + columnKey + '"]').find('.todo-load-more');

  $button.before(remaining.splice(0, window.reedcrm.todoKanban.chunkSize).join(''));

  if (!remaining.length) {
    $button.remove();
  } else {
    $button.attr('data-remaining', remaining.length);
    $button.find('.todo-load-more-text').text(String($button.data('label') || '+ %s').replace('%s', remaining.length));
  }

  // Make the freshly injected cards draggable
  if ($('.todo-board').data('editable')) {
    $('.todo-sortable').sortable('refresh');
  }
};

/**
 * Update the counters of the columns: cards in the DOM plus the ones not injected yet.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.updateCounts = function () {
  $('.todo-column').each(function () {
    var deferred = window.reedcrm.todoKanban.deferredCards[$(this).data('column')];
    $(this).find('.todo-column-count').text($(this).find('.todo-card').length + (deferred ? deferred.length : 0));
  });
};

/**
 * Width and gap of the columns, kept in the browser of the user.
 *
 * @returns {void}
 */
window.reedcrm.todoKanban.initSettings = function () {
  var $button       = $('#todoSettingsBtn');
  var $popover      = $('#todoSettingsPopover');
  var $board        = $('.todo-board');
  var $widthSlider  = $('#todoColWidth');
  var $gapSlider    = $('#todoColGap');
  var $widthValue   = $('#todoColWidthVal');
  var $gapValue     = $('#todoColGapVal');

  if (!$button.length) {
    return;
  }

  var savedWidth = localStorage.getItem('todoColWidth');
  var savedGap   = localStorage.getItem('todoColGap');

  if (savedWidth) {
    $widthSlider.val(savedWidth);
    $widthValue.text(savedWidth + 'px');
    $('.todo-column').css({'min-width': savedWidth + 'px', 'max-width': savedWidth + 'px'});
  }
  if (savedGap) {
    $gapSlider.val(savedGap);
    $gapValue.text(savedGap + 'px');
    $board.css('gap', savedGap + 'px');
  }

  $button.on('click', function (event) {
    event.stopPropagation();
    $popover.toggleClass('open');
  });

  $(document).on('click', function (event) {
    if (!$(event.target).closest('.todo-settings-wrapper').length) {
      $popover.removeClass('open');
    }
  });

  $widthSlider.on('input', function () {
    var value = $(this).val();
    $widthValue.text(value + 'px');
    $('.todo-column').css({'min-width': value + 'px', 'max-width': value + 'px'});
    localStorage.setItem('todoColWidth', value);
  });

  $gapSlider.on('input', function () {
    var value = $(this).val();
    $gapValue.text(value + 'px');
    $board.css('gap', value + 'px');
    localStorage.setItem('todoColGap', value);
  });
};
