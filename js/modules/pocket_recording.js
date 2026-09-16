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
    window.reedcrm.pocketRecording.buildStatusPicker();
  },

  event: function() {
    // init() runs twice, once from the end of this file and once from load_list_script(), so the
    // handlers are dropped before being hung again: bound twice, a toggle undoes itself and every
    // save fires two requests
    $(document).off('.pocketRecording');

    $(document).on('change.pocketRecording', '.pocket-action-assign', window.reedcrm.pocketRecording.assignUser);
    $(document).on('click.pocketRecording', '.pocket-action-create-event', window.reedcrm.pocketRecording.createEvent);
    $(document).on('click.pocketRecording', '.reedcrm-pocket-audio-load', window.reedcrm.pocketRecording.loadAudio);
    $(document).on('change.pocketRecording', '.pocket-action-due-date', window.reedcrm.pocketRecording.setDueDate);
    $(document).on('change.pocketRecording', '.pocket-action-label, .pocket-action-description', window.reedcrm.pocketRecording.setText);
    $(document).on('click.pocketRecording', '.reedcrm-pocket-status-badge', window.reedcrm.pocketRecording.toggleStatusMenu);
    $(document).on('click.pocketRecording', '.reedcrm-pocket-status-menu li', window.reedcrm.pocketRecording.setStatus);
    $(document).on('click.pocketRecording', '.reedcrm-pocket-summary-block[data-url] .reedcrm-pocket-summary', window.reedcrm.pocketRecording.editSummary);
    $(document).on('focus.pocketRecording', '.reedcrm-pocket-object-search', window.reedcrm.pocketRecording.openObjectResults);
    $(document).on('input.pocketRecording', '.reedcrm-pocket-object-search', window.reedcrm.pocketRecording.searchObjects);
    $(document).on('change.pocketRecording', '.reedcrm-pocket-object-type', window.reedcrm.pocketRecording.searchObjects);
    $(document).on('click.pocketRecording', '.reedcrm-pocket-object-results li[data-key]', window.reedcrm.pocketRecording.pickObject);
    $(document).on('blur.pocketRecording', '.reedcrm-pocket-summary-edit', window.reedcrm.pocketRecording.saveSummary);
    $(document).on('keydown.pocketRecording', '.reedcrm-pocket-summary-edit', window.reedcrm.pocketRecording.cancelSummary);
    // A click outside the picker closes the menu. Both handlers are delegated on the document, and
    // jQuery runs the delegated one first, so stopping the propagation would not spare the opening
    // click: the target is tested instead.
    $(document).on('click.pocketRecording', function(event) {
      if (!$(event.target).closest('.reedcrm-pocket-status').length) {
        $('.reedcrm-pocket-status').removeClass('open');
      }
      if (!$(event.target).closest('.reedcrm-pocket-object-search-wrapper').length) {
        $('.reedcrm-pocket-object-results').prop('hidden', true);
      }
    });
  },

  /**
   * Show the objects already loaded when the search field takes the focus.
   */
  openObjectResults: function() {
    $(this).closest('.reedcrm-pocket-object-search-wrapper').find('.reedcrm-pocket-object-results').prop('hidden', false);
  },

  /**
   * Search the objects that may be attached, on the type and the term the user gives.
   *
   * The search is asked to the module and not to the native link block of Dolibarr: it only offers
   * the types enabled for the recordings, and it reaches every thirdparty, not only the one of the
   * recording. With an empty term it answers the objects of that thirdparty.
   */
  searchObjects: function() {
    var $form   = $(this).closest('.reedcrm-pocket-link-form');
    var $input  = $form.find('.reedcrm-pocket-object-search');
    var $result = $form.find('.reedcrm-pocket-object-results');
    var $submit = $form.find('input[type="submit"]');

    // Picking again starts from a blank choice, the button waits for a new one
    $form.closest('form').find('input[name="object_to_link"]').val('');
    $submit.prop('disabled', true);

    // The empty option of a Dolibarr selector is worth -1 and means every type here
    var objectType = $form.find('.reedcrm-pocket-object-type').val();

    clearTimeout(window.reedcrm.pocketRecording.searchTimer);
    window.reedcrm.pocketRecording.searchTimer = setTimeout(function() {
      $result.prop('hidden', false).addClass('opacitymedium');

      $.post($form.data('url'), {
        subaction:    'search_objects',
        recording_id: $form.data('recording-id'),
        object_type:  (objectType === '-1' ? '' : objectType),
        search:       $input.val(),
        token:        $form.data('token')
      }, null, 'json').done(function(data) {
        $result.removeClass('opacitymedium').empty();

        if (!data || !data.success || !data.objects.length) {
          $result.append($('<li class="opacitymedium reedcrm-pocket-object-empty"></li>').text($result.data('empty-label') || ''));
          return;
        }

        $.each(data.objects, function(index, object) {
          $result.append($('<li></li>').attr('data-key', object.key).text(object.label));
        });
      }).fail(function() {
        $result.removeClass('opacitymedium');
      });
    }, 250);
  },

  /**
   * Keep the object picked in the list, and let the form be submitted.
   */
  pickObject: function() {
    var $item = $(this);
    var $form = $item.closest('.reedcrm-pocket-link-form');

    $form.closest('form').find('input[name="object_to_link"]').val($item.data('key'));
    $form.find('.reedcrm-pocket-object-search').val($item.text());
    $form.find('input[type="submit"]').prop('disabled', false);
    $form.find('.reedcrm-pocket-object-results').prop('hidden', true);
  },

  /**
   * Swap the rendered summary for its markdown source, ready to be edited.
   *
   * A link inside the summary keeps its own job: clicking it opens the target instead of the editor.
   */
  editSummary: function(event) {
    if ($(event.target).closest('a').length) {
      return;
    }

    var $block    = $(this).closest('.reedcrm-pocket-summary-block');
    var $textarea = $block.find('.reedcrm-pocket-summary-edit');

    // The source keeps the height the rendered block had, so the page does not jump on a click
    $textarea.css('min-height', Math.max($(this).outerHeight(), 120) + 'px');

    $(this).prop('hidden', true);
    $block.find('.reedcrm-pocket-summary-edited').prop('hidden', true);
    $block.find('.reedcrm-pocket-summary-help').prop('hidden', false);
    $textarea.prop('hidden', false).focus();
  },

  /**
   * Close the editor without saving, and put back the summary as it was.
   */
  cancelSummary: function(event) {
    if (event.key !== 'Escape') {
      return;
    }

    var $textarea = $(this);
    var $block    = $textarea.closest('.reedcrm-pocket-summary-block');

    // The saved source is the one the block was rendered from, typing again is undone by leaving
    $textarea.val($textarea.data('pocket-saved') !== undefined ? $textarea.data('pocket-saved') : $textarea.prop('defaultValue'));
    window.reedcrm.pocketRecording.closeSummaryEditor($block);
  },

  /**
   * Save the summary when the editor loses the focus, then show what the server rendered.
   */
  saveSummary: function() {
    var $textarea = $(this);
    var $block    = $textarea.closest('.reedcrm-pocket-summary-block');
    var $summary  = $block.find('.reedcrm-pocket-summary');

    window.reedcrm.pocketRecording.closeSummaryEditor($block);

    // Nothing was rewritten, the block already shows the right text
    var saved = $textarea.data('pocket-saved') !== undefined ? $textarea.data('pocket-saved') : $textarea.prop('defaultValue');
    if (saved === $textarea.val()) {
      return;
    }

    $block.addClass('opacitymedium');

    $.post($block.data('url'), {
      subaction:    'set_summary',
      recording_id: $block.data('recording-id'),
      summary:      $textarea.val(),
      token:        $block.data('token')
    }, null, 'json').done(function(data) {
      $block.removeClass('opacitymedium');
      if (data && data.success) {
        $textarea.data('pocket-saved', data.summary);
        $summary.html(data.summary_html).removeClass('error');
        $block.find('.reedcrm-pocket-summary-edited').prop('hidden', !data.edited);
      } else {
        $summary.addClass('error');
      }
    }).fail(function() {
      $block.removeClass('opacitymedium');
      $summary.addClass('error');
    });
  },

  /**
   * Put the summary block back in reading mode.
   */
  closeSummaryEditor: function($block) {
    $block.find('.reedcrm-pocket-summary-edit').prop('hidden', true);
    $block.find('.reedcrm-pocket-summary-help').prop('hidden', true);
    $block.find('.reedcrm-pocket-summary').prop('hidden', false);
  },

  /**
   * Hang the status choices under the badge of the banner.
   *
   * The banner is printed by Dolibarr, the card only leaves the data next to it: the badge is the
   * place where the status is read, so it is also the place where it is changed.
   */
  buildStatusPicker: function() {
    var $picker = $('.reedcrm-pocket-status-picker');
    var $status = $('.arearef .statusref').first();

    if (!$picker.length || !$status.length || $status.hasClass('reedcrm-pocket-status')) {
      return;
    }

    var statuses = $picker.data('statuses') || [];
    if (!statuses.length) {
      return;
    }

    var $menu = $('<ul class="reedcrm-pocket-status-menu"></ul>');
    $.each(statuses, function(index, status) {
      $menu.append($('<li></li>').attr('data-status', status.key).text(status.label));
    });

    $status.addClass('reedcrm-pocket-status');
    $status.wrapInner('<span class="reedcrm-pocket-status-badge" title="' + $picker.data('title') + '"></span>');
    $status.find('.reedcrm-pocket-status-badge').append('<i class="fas fa-caret-down"></i>');
    $status.append($menu);
  },

  /**
   * Open the status menu, and close any other one already open.
   */
  toggleStatusMenu: function() {
    $(this).closest('.reedcrm-pocket-status').toggleClass('open');
  },

  /**
   * Save the status picked in the menu, then repaint the badge with the answer of the server.
   */
  setStatus: function() {
    var $item   = $(this);
    var $status = $item.closest('.reedcrm-pocket-status');
    var $picker = $('.reedcrm-pocket-status-picker');

    $status.removeClass('open').addClass('opacitymedium');

    $.post($picker.data('url'), {
      subaction:    'set_status',
      recording_id: $picker.data('recording-id'),
      status:       $item.data('status'),
      token:        $picker.data('token')
    }, null, 'json').done(function(data) {
      $status.removeClass('opacitymedium');
      if (data && data.success) {
        $status.find('.reedcrm-pocket-status-badge').html(data.status_html + '<i class="fas fa-caret-down"></i>');
      } else {
        $status.addClass('error');
      }
    }).fail(function() {
      $status.removeClass('opacitymedium').addClass('error');
    });
  },

  /**
   * Save the label and the description of an action item once the edited field loses the focus.
   *
   * Both fields travel together: they are two halves of the same wording and the endpoint writes
   * the row once, so an edit on one never resets the other with a stale value.
   */
  setText: function() {
    var $field = $(this);
    var $row   = $field.closest('tr');

    // A field fires its change twice when it is left with the keyboard then with the mouse, and
    // the second one carries nothing new: the last saved wording is kept to skip the write
    if ($field.data('pocket-saved') === $field.val()) {
      return;
    }
    $field.data('pocket-saved', $field.val());

    $row.addClass('opacitymedium');

    $.post($row.data('url'), {
      subaction:      'set_text',
      action_item_id: $row.data('action-item-id'),
      label:          $row.find('.pocket-action-label').val(),
      description:    $row.find('.pocket-action-description').val(),
      token:          $row.data('token')
    }, null, 'json').done(function(data) {
      $row.removeClass('opacitymedium');
      $field.toggleClass('error', !(data && data.success));
    }).fail(function() {
      $row.removeClass('opacitymedium');
      $field.addClass('error');
      // The write did not land, the next change on the same value has to be sent again
      $field.removeData('pocket-saved');
    });
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
