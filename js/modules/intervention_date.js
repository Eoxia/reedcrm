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
 * \file    js/modules/intervention_date.js
 * \ingroup reedcrm
 * \brief   Opens the intervention date modal of a service line, one date per unit of quantity,
 *          and saves them back with their agenda events.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * Init interventionDate JS
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.interventionDate = {};

/**
 * ID of the service line being planned, set when the modal opens
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Number}
 */
window.reedcrm.interventionDate.currentLineId = 0;

/**
 * Whether the delegated handlers are already bound
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Boolean}
 */
window.reedcrm.interventionDate.bound = false;

/**
 * Init
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.interventionDate.init = function () {
  // The file is both bundled into reedcrm.min.js and loaded alone on the native proposal card
  if (!$('#reedcrm-intervention-date-config').length || window.reedcrm.interventionDate.bound) {
    return;
  }

  window.reedcrm.interventionDate.bound = true;
  window.reedcrm.interventionDate.event();
};

/**
 * Read one value of the config block dropped by the modal template
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   {String} key Name of the data attribute, without the data- prefix
 * @returns {String}     Value, empty when the block is missing
 */
window.reedcrm.interventionDate.config = function (key) {
  const $config = $('#reedcrm-intervention-date-config');

  return $config.length ? String($config.data(key) === undefined ? '' : $config.data(key)) : '';
};

/**
 * Bind the triggers sitting on the service lines and the modal buttons
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.interventionDate.event = function () {
  $(document).on('click', '.reedcrm-intervention-trigger', window.reedcrm.interventionDate.open);
  $(document).on('click', '#reedcrm-intervention-date-modal .modal-close, .reedcrm-intervention-date-cancel', window.reedcrm.interventionDate.close);
  $(document).on('click', '.reedcrm-intervention-date-confirm', window.reedcrm.interventionDate.save);
  // The colored index follows the intervenant, so the modal reads like the calendar
  $(document).on('change', '#reedcrm-intervention-date-modal .reedcrm-intervention-user', window.reedcrm.interventionDate.refreshRowState);
  $(document).on('change', '#reedcrm-intervention-date-modal input[type="date"]', window.reedcrm.interventionDate.refreshRowState);
};

/**
 * Open the modal on the service line that was clicked and load its dates
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   {Object} event Click event
 * @returns {void}
 */
window.reedcrm.interventionDate.open = function (event) {
  // A chip carries a link to its proposal, that click belongs to the link
  if ($(event.target).closest('a').length) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();

  const lineId = parseInt($(this).data('line-id'), 10);
  if (!lineId) {
    return;
  }

  window.reedcrm.interventionDate.currentLineId = lineId;

  const $modal = $('#reedcrm-intervention-date-modal');
  $modal.find('.reedcrm-intervention-date-line').text('');
  $('#reedcrm-intervention-date-rows').html('<div class="reedcrm-intervention-loading"><i class="fas fa-spinner fa-spin"></i></div>');
  $modal.addClass('modal-active');

  $.ajax({
    url: window.reedcrm.interventionDate.config('get-url'),
    type: 'POST',
    dataType: 'json',
    data: {
      token: window.reedcrm.interventionDate.config('token'),
      line_id: lineId
    },
    success: function (response) {
      if (!response || !response.success) {
        $('#reedcrm-intervention-date-rows').html('');
        $.jnotify((response && response.error) ? response.error : window.reedcrm.interventionDate.config('trans-error'), 'error');
        window.reedcrm.interventionDate.close();
        return;
      }

      $modal.find('.reedcrm-intervention-date-line').text(response.title);
      $('#reedcrm-intervention-date-rows').html(response.html);
      window.reedcrm.interventionDate.enhance();
    },
    error: function () {
      $('#reedcrm-intervention-date-rows').html('');
      $.jnotify(window.reedcrm.interventionDate.config('trans-error'), 'error');
      window.reedcrm.interventionDate.close();
    }
  });
};

/**
 * Turn the intervenant selects into select2 ones, once the rows are in the modal
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.interventionDate.enhance = function () {
  if (!$.fn.select2) {
    return;
  }

  // The dropdown is anchored inside the modal, the rows container clips what it holds
  const $parent = $('#reedcrm-intervention-date-modal .modal-container');

  $('#reedcrm-intervention-date-rows').find('select.reedcrm-intervention-user').each(function () {
    const $select = $(this);
    if ($select.hasClass('select2-hidden-accessible')) {
      return;
    }

    $select.select2({
      width: '190px',
      dropdownParent: $parent,
      dropdownAutoWidth: true
    });
  });
};

/**
 * Close the modal
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.interventionDate.close = function () {
  $('#reedcrm-intervention-date-modal').removeClass('modal-active');
  window.reedcrm.interventionDate.currentLineId = 0;
};

/**
 * Keep a row telling whether it is planned, and its index colored like its intervenant
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.interventionDate.refreshRowState = function () {
  const $row = $(this).closest('.reedcrm-intervention-row');

  $row.toggleClass('reedcrm-intervention-row-planned', $row.find('input[type="date"]').val() !== '');
};

/**
 * Save every date of the line, then refresh the counter shown on the service line
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.interventionDate.save = function () {
  const lineId = window.reedcrm.interventionDate.currentLineId;
  if (!lineId) {
    return;
  }

  const $button = $(this);
  $button.addClass('button-disable');

  const data = $('#reedcrm-intervention-date-form').serializeArray();
  data.push({ name: 'token', value: window.reedcrm.interventionDate.config('token') });
  data.push({ name: 'line_id', value: lineId });

  $.ajax({
    url: window.reedcrm.interventionDate.config('save-url'),
    type: 'POST',
    dataType: 'json',
    data: $.param(data),
    success: function (response) {
      $button.removeClass('button-disable');

      if (!response || !response.success) {
        $.jnotify((response && response.error) ? response.error : window.reedcrm.interventionDate.config('trans-error'), 'error');
        return;
      }

      window.reedcrm.interventionDate.refreshTrigger(lineId, response.planned, response.expected);
      $.jnotify(window.reedcrm.interventionDate.config('trans-saved'), 'success');
      window.reedcrm.interventionDate.close();
    },
    error: function () {
      $button.removeClass('button-disable');
      $.jnotify(window.reedcrm.interventionDate.config('trans-error'), 'error');
    }
  });
};

/**
 * Refresh the counter of the trigger sitting on the service line
 *
 * @memberof ReedCRM_InterventionDate
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   {Number} lineId   ID of the service line
 * @param   {Number} planned  Dates filled
 * @param   {Number} expected Dates the quantity asks for
 * @returns {void}
 */
window.reedcrm.interventionDate.refreshTrigger = function (lineId, planned, expected) {
  const $trigger = $('.reedcrm-intervention-trigger[data-line-id="' + lineId + '"]');
  if (!$trigger.length) {
    return;
  }

  $trigger.find('.reedcrm-intervention-count').text(planned + '/' + expected);
  $trigger.toggleClass('reedcrm-intervention-trigger-complete', expected > 0 && planned >= expected);
  $trigger.addClass('reedcrm-intervention-trigger-flash');
  setTimeout(function () {
    $trigger.removeClass('reedcrm-intervention-trigger-flash');
  }, 1500);
};

$(document).ready(function () {
  window.reedcrm.interventionDate.init();
});
