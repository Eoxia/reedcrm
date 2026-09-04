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

/**
 * \file    js/modules/pocket_recording.js
 * \ingroup reedcrm
 * \brief   JavaScript actions of the Pocket recording card for module ReedCRM
 */

'use strict';

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.pocketRecording = {

  init: function() {
    window.reedcrm.pocketRecording.event();
  },

  event: function() {
    $(document).on('change', '.pocket-action-assign', window.reedcrm.pocketRecording.assignUser);
    $(document).on('click', '.pocket-action-create-event', window.reedcrm.pocketRecording.createEvent);
    $(document).on('click', '.reedcrm-pocket-audio-load', window.reedcrm.pocketRecording.loadAudio);
    $(document).on('change', '.pocket-action-due-date', window.reedcrm.pocketRecording.setDueDate);
  },

  /**
   * Save the deadline of an action item as soon as the date is picked.
   */
  setDueDate: function() {
    var $input = $(this);
    var $row   = $input.closest('tr');

    $row.addClass('opacitymedium');

    $.post($row.data('url'), {
      subaction:      'set_due_date',
      action_item_id: $row.data('action-item-id'),
      due_date:       $input.val(),
      token:          $row.data('token')
    }, null, 'json').done(function(data) {
      $row.removeClass('opacitymedium');
      $input.toggleClass('error', !(data && data.success));
    }).fail(function() {
      $row.removeClass('opacitymedium');
      $input.addClass('error');
    });
  },

  /**
   * Resolve the signed audio URL, then swap the button for a native player already playing.
   *
   * The URL is signed for a short window, which is why it is fetched on demand instead of being
   * rendered with the page: a player built at render time would be dead by the time it is used.
   */
  loadAudio: function() {
    var $button    = $(this);
    var $container = $button.closest('.reedcrm-pocket-audio');

    if ($button.hasClass('loading')) {
      return;
    }
    $button.addClass('loading');

    $.getJSON($container.data('url'), function(data) {
      if (data && data.success && data.url) {
        var $player = $('<audio controls autoplay preload="none"></audio>');
        $player.attr('src', data.url);
        $container.empty().append($player);
      } else {
        $button.removeClass('loading').addClass('error');
        $container.append('<span class="error">' + ((data && data.error) ? data.error : '') + '</span>');
      }
    }).fail(function() {
      $button.removeClass('loading').addClass('error');
    });
  },

  /**
   * Assign a Dolibarr user to an action item extracted by Pocket.
   */
  assignUser: function() {
    var $select = $(this);
    var $row    = $select.closest('tr');

    $row.addClass('opacitymedium');

    $.post($row.data('url'), {
      subaction:      'assign',
      action_item_id: $row.data('action-item-id'),
      fk_user_assign: $select.val(),
      token:          $row.data('token')
    }, null, 'json').done(function(data) {
      $row.removeClass('opacitymedium');
      if (!data || !data.success) {
        $row.addClass('error');
      }
    }).fail(function() {
      $row.removeClass('opacitymedium').addClass('error');
    });
  },

  /**
   * Turn an action item into an agenda event, then replace the button by a link to that event.
   */
  createEvent: function(event) {
    event.preventDefault();

    var $button = $(this);
    var $row    = $button.closest('tr');
    if ($button.hasClass('loading')) {
      return;
    }
    $button.addClass('loading');

    $.post($row.data('url'), {
      subaction:      'create_event',
      action_item_id: $row.data('action-item-id'),
      token:          $row.data('token')
    }, null, 'json').done(function(data) {
      $button.removeClass('loading');
      if (data && data.success && data.url) {
        $button.replaceWith('<a href="' + data.url + '"><i class="fas fa-calendar-check pictofixedwidth"></i>' + $button.data('created-label') + '</a>');
      } else {
        $button.addClass('butActionRefused').attr('title', (data && data.error) ? data.error : 'KO');
      }
    }).fail(function() {
      $button.removeClass('loading').addClass('butActionRefused');
    });
  }

};

window.reedcrm.pocketRecording.init();
