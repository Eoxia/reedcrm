/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    js/modules/event_quick_close.js
 * \ingroup reedcrm
 * \brief   Turns the status badge of every to-do event, listed by show_actions_done() or shown in the
 *          banner of its own card, into a quick close trigger : optional comment, and optional clone
 *          renamed at will and postponed by 1 month or X days.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * Init eventQuickClose JS
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.eventQuickClose = {};

/**
 * ID of the event being closed, set when the modal opens
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Number}
 */
window.reedcrm.eventQuickClose.currentEventId = 0;

/**
 * Init
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventQuickClose.init = function () {
  if (!$('#reedcrm-quick-close-config').length) {
    return;
  }

  window.reedcrm.eventQuickClose.decorate();
  window.reedcrm.eventQuickClose.event();
};

/**
 * Read a config value injected by the modal template
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {String} name The data attribute name, without the "data-" prefix
 * @return {String}      The value, empty when the config block is missing
 */
window.reedcrm.eventQuickClose.config = function (name) {
  return $('#reedcrm-quick-close-config').attr('data-' + name) || '';
};

/**
 * Turn every closable status badge of the page into a trigger, in a list and on an event card alike
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventQuickClose.decorate = function () {
  window.reedcrm.eventQuickClose.decorateList();
  window.reedcrm.eventQuickClose.decorateCard();
};

/**
 * Flag every event row whose progress is below 100% as closable. The events list is rendered by
 * a core function without any marker, a row is identified by its link to the event card and its
 * progress badge (a "NA" badge is a system event, it has no progress to close).
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventQuickClose.decorateList = function () {
  $('a[href*="/comm/action/card.php?id="]').each(function () {
    var $row = $(this).closest('tr');
    if (!$row.length || $row.hasClass('reedcrm-quick-close-row')) {
      return;
    }

    var eventId = ($(this).attr('href').match(/[?&]id=(\d+)/) || [])[1];
    if (!eventId) {
      return;
    }

    var $badge = $row.find('span[class*="badge-status"]').filter(function () {
      var percent = $(this).text().trim().match(/^(\d{1,3})\s*%$/);
      return percent !== null && parseInt(percent[1], 10) < 100;
    }).first();

    if (!$badge.length) {
      return;
    }

    $row.addClass('reedcrm-quick-close-row');
    $badge.addClass('reedcrm-quick-close-trigger')
      .attr('data-event-id', eventId)
      .attr('title', window.reedcrm.eventQuickClose.config('trans-tooltip'))
      .append('<i class="fas fa-check-circle reedcrm-quick-close-icon"></i>');
  });
};

/**
 * Flag the status badge of the banner as closable on the card of a to-do event. There is no row to
 * read here, the template hands over the event of the page and an empty id means it is not closable.
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventQuickClose.decorateCard = function () {
  var eventId = parseInt(window.reedcrm.eventQuickClose.config('card-event-id'), 10);
  if (!eventId) {
    return;
  }

  // showrefnav() wraps the status of the banner in its own block, the badge is the only one there
  var $badge = $('.statusref').find('span[class*="badge-status"]').first();
  if (!$badge.length || $badge.hasClass('reedcrm-quick-close-trigger')) {
    return;
  }

  $badge.addClass('reedcrm-quick-close-trigger reedcrm-quick-close-trigger-banner')
    .attr('data-event-id', eventId)
    .attr('data-event-label', window.reedcrm.eventQuickClose.config('card-event-label'))
    .attr('title', window.reedcrm.eventQuickClose.config('trans-tooltip'))
    .append('<i class="fas fa-check-circle reedcrm-quick-close-icon"></i>');
};

/**
 * Bind the trigger, the modal controls and the confirmation
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventQuickClose.event = function () {
  // The module is both bundled into reedcrm.min.js and loaded by the modal template, the namespace
  // keeps a single set of handlers when a reedcrm page pulls it twice
  $(document).off('.reedcrmQuickClose');

  $(document).on('click.reedcrmQuickClose', '.reedcrm-quick-close-trigger', function (event) {
    event.preventDefault();
    event.stopPropagation();
    window.reedcrm.eventQuickClose.open($(this));
  });

  $(document).on('click.reedcrmQuickClose', '#reedcrm-quick-close-modal .modal-close, .reedcrm-quick-close-cancel', function () {
    window.reedcrm.eventQuickClose.close();
  });

  $(document).on('click.reedcrmQuickClose', '#reedcrm-quick-close-modal', function (event) {
    if ($(event.target).is('#reedcrm-quick-close-modal')) {
      window.reedcrm.eventQuickClose.close();
    }
  });

  $(document).on('change.reedcrmQuickClose', '#reedcrm-quick-close-reschedule', function () {
    $('#reedcrm-quick-close-delay').toggleClass('reedcrm-quick-close-delay-visible', $(this).is(':checked'));
  });

  // Typing a number of days is meaningless while another choice is selected, picking a day too
  $(document).on('focus.reedcrmQuickClose', '#reedcrm-quick-close-delay-value', function () {
    $('input[name="reedcrm-quick-close-delay-unit"][value="d"]').prop('checked', true);
  });

  $(document).on('focus.reedcrmQuickClose', '#reedcrm-quick-close-delay-date', function () {
    $('input[name="reedcrm-quick-close-delay-unit"][value="date"]').prop('checked', true);
  });

  $(document).on('click.reedcrmQuickClose', '.reedcrm-quick-close-confirm', function () {
    window.reedcrm.eventQuickClose.confirm($(this));
  });
};

/**
 * Open the modal for one event
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {Object} $trigger The clicked status badge
 * @return {void}
 */
window.reedcrm.eventQuickClose.open = function ($trigger) {
  // On a card the name comes from the trigger. In a list getNomUrl() puts it in the title of the
  // reference link of the row, whatever the column order, but without MAIN_ENABLE_AJAX_TOOLTIP that
  // title holds the whole summary of the event : the text of the link is the name in that case.
  var $row  = $trigger.closest('tr');
  var $card = $trigger.closest('.todo-card');
  var $link = $row.find('a[href*="/comm/action/card.php?id="]').first();
  var label = ($trigger.attr('data-event-label') || '').trim();

  // On the to-do board the name is the label of the card, editable in place: read it rather than
  // a copy the inline edition would have left behind
  if (!label && $card.length) {
    label = $card.find('.todo-card-label').first().text().trim();
  }

  if (!label) {
    label = ($link.attr('title') || '').trim();
    if (!label || label.indexOf('<') !== -1) {
      label = $link.text().trim();
    }
  }

  window.reedcrm.eventQuickClose.currentEventId = parseInt($trigger.attr('data-event-id'), 10);

  // The postponement always reopens on the delay configured for the module
  var defaultUnit = window.reedcrm.eventQuickClose.config('default-unit') || 'm';
  var defaultDays = window.reedcrm.eventQuickClose.config('default-days') || 7;

  $('#reedcrm-quick-close-comment').val('');
  $('#reedcrm-quick-close-reschedule').prop('checked', false);
  $('#reedcrm-quick-close-delay').removeClass('reedcrm-quick-close-delay-visible');
  $('input[name="reedcrm-quick-close-delay-unit"][value="' + defaultUnit + '"]').prop('checked', true);
  $('#reedcrm-quick-close-delay-value').val(defaultDays);
  $('#reedcrm-quick-close-delay-date').val('');
  // The rescheduled event repeats the closed one, its name stays editable
  $('#reedcrm-quick-close-new-label').val(label);

  $('#reedcrm-quick-close-modal .reedcrm-quick-close-event').text(label);
  $('#reedcrm-quick-close-modal').addClass('modal-active');
  $('#reedcrm-quick-close-comment').trigger('focus');
};

/**
 * Close the modal
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventQuickClose.close = function () {
  $('#reedcrm-quick-close-modal').removeClass('modal-active');
  window.reedcrm.eventQuickClose.currentEventId = 0;
};

/**
 * Send the closure, then refresh the badge in place. A rescheduled event adds a row to the list,
 * only that case needs a reload.
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {Object} $button The confirm button
 * @return {void}
 */
window.reedcrm.eventQuickClose.confirm = function ($button) {
  var eventId = window.reedcrm.eventQuickClose.currentEventId;
  if (!eventId || $button.hasClass('button-disable')) {
    return;
  }

  var delayUnit = $('input[name="reedcrm-quick-close-delay-unit"]:checked').val();
  var delayDate = $('#reedcrm-quick-close-delay-date').val();

  // The day choice has nothing to schedule without a day
  if ($('#reedcrm-quick-close-reschedule').is(':checked') && delayUnit === 'date' && !delayDate) {
    window.reedcrm.eventQuickClose.notify(window.reedcrm.eventQuickClose.config('trans-date-required'), 'error');
    $('#reedcrm-quick-close-delay-date').trigger('focus');
    return;
  }

  $button.addClass('button-disable');

  $.ajax({
    url: window.reedcrm.eventQuickClose.config('url'),
    type: 'POST',
    dataType: 'json',
    data: {
      token: window.reedcrm.eventQuickClose.config('token'),
      event_id: eventId,
      comment: $('#reedcrm-quick-close-comment').val(),
      reschedule: $('#reedcrm-quick-close-reschedule').is(':checked') ? 1 : 0,
      delay_unit: delayUnit,
      delay_value: $('#reedcrm-quick-close-delay-value').val(),
      delay_date: delayDate,
      new_label: $('#reedcrm-quick-close-new-label').val()
    },
    success: function (response) {
      $button.removeClass('button-disable');

      if (!response || !response.success) {
        window.reedcrm.eventQuickClose.notify((response && response.error) || window.reedcrm.eventQuickClose.config('trans-error'), 'error');
        return;
      }

      var $trigger = $('.reedcrm-quick-close-trigger[data-event-id="' + eventId + '"]');
      var $card    = $trigger.closest('.todo-card');

      // On the to-do board the closed event is repainted at 100% and moves to the column it now belongs to
      if ($card.length && window.reedcrm.todoKanban) {
        window.reedcrm.todoKanban.paintCard($card, 100);
        window.reedcrm.todoKanban.moveToColumn($card, 100);
        window.reedcrm.todoKanban.flag($card, 'todo-card-saved', 2000);

        window.reedcrm.eventQuickClose.close();
        window.reedcrm.eventQuickClose.notify(response.message, 'success');

        // The rescheduled event is a card of its own, only a reload brings it into the board
        if (response.new_event && response.new_event.id > 0) {
          setTimeout(function () {
            window.location.reload();
          }, 1500);
        }
        return;
      }

      // On the card the action buttons and the dates follow the status, only a reload renders them again
      if ($trigger.hasClass('reedcrm-quick-close-trigger-banner')) {
        window.reedcrm.eventQuickClose.close();
        window.reedcrm.eventQuickClose.notify(response.message, 'success');
        setTimeout(function () {
          window.location.reload();
        }, 1500);
        return;
      }

      $trigger.closest('td').html(response.status_html);
      $trigger.closest('tr').removeClass('reedcrm-quick-close-row').addClass('reedcrm-quick-close-flash');

      window.reedcrm.eventQuickClose.close();
      window.reedcrm.eventQuickClose.notify(response.message, 'success');

      if (response.new_event && response.new_event.id > 0) {
        setTimeout(function () {
          window.location.reload();
        }, 1500);
      }
    },
    error: function () {
      $button.removeClass('button-disable');
      window.reedcrm.eventQuickClose.notify(window.reedcrm.eventQuickClose.config('trans-error'), 'error');
    }
  });
};

/**
 * Display a transient message through the Dolibarr notifier
 *
 * @memberof ReedCRM_EventQuickClose
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {String} message The text to display
 * @param  {String} type    "success" or "error"
 * @return {void}
 */
window.reedcrm.eventQuickClose.notify = function (message, type) {
  if (!message) {
    return;
  }

  // jnotify is loaded by main.inc.php unless it has been disabled
  if (typeof $.jnotify !== 'function') {
    alert(message);
    return;
  }

  if (type === 'error') {
    $.jnotify(message, 'error', true, { remove: function () {} });
  } else {
    $.jnotify(message, 3000, false, { remove: function () {} });
  }
};

// Auto-initialize on document ready
jQuery(document).ready(function () {
  window.reedcrm.eventQuickClose.init();
});
