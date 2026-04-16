/* Copyright (C) 2021-2025 EVARISK <technique@evarisk.com>
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
 *
 * Library javascript to enable Browser notifications
 */

/**
 * \file    js/modules/pwa_home.js
 * \ingroup reedcrm
 * \brief   JavaScript module for the PWA home page (Flatpickr week picker)
 */

'use strict';

/**
 * Init pwaHome JS
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {Object}
 */
window.reedcrm.pwaHome = {};

/**
 * Flatpickr instance for the week picker
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {Object|null}
 */
window.reedcrm.pwaHome.picker = null;

/**
 * pwaHome init
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.init = function() {
  window.reedcrm.pwaHome.initWeekPicker();
  window.reedcrm.pwaHome.event();
};

/**
 * pwaHome event
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.event = function() {
  $(document).on('click', '.pwa-week-picker-wrap', window.reedcrm.pwaHome.openPicker);
};

/**
 * Open the Flatpickr calendar when clicking the date range label
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.openPicker = function() {
  if (window.reedcrm.pwaHome.picker) {
    window.reedcrm.pwaHome.picker.open();
  }
};

/**
 * Initialise Flatpickr on the hidden text input as a week selector.
 * Highlights the full week row on hover and redirects on selection.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.initWeekPicker = function() {
  var $input = $('#pwa-week-picker');
  if ($input.length === 0 || typeof flatpickr === 'undefined') {
    return;
  }

  var pageUrl     = $input.data('page-url');
  var defaultDate = $input.data('default-date');
  var maxDate     = $input.data('max-date');

  window.reedcrm.pwaHome.picker = flatpickr('#pwa-week-picker', {
    locale:      'fr',
    weekNumbers: true,
    defaultDate: defaultDate,
    maxDate:     maxDate,
    disableMobile: true,

    // Highlight the whole week row on day create
    onDayCreate: function(dObj, dStr, fp, dayElem) {
      dayElem.addEventListener('mouseenter', window.reedcrm.pwaHome.highlightWeek);
      dayElem.addEventListener('mouseleave', window.reedcrm.pwaHome.clearWeekHighlight);
    },

    // On selection: snap to Monday and redirect
    onChange: function(selectedDates) {
      if (!selectedDates.length) {
        return;
      }
      var d       = selectedDates[0];
      var dow     = d.getDay() || 7; // 1=Mon … 7=Sun
      var monday  = new Date(d);
      monday.setDate(d.getDate() - (dow - 1));

      var pad     = function(n) { return ('0' + n).slice(-2); };
      var dateStr = monday.getFullYear() + '-' + pad(monday.getMonth() + 1) + '-' + pad(monday.getDate());

      window.location.href = pageUrl + '?week_start=' + dateStr;
    }
  });

  // Highlight the currently selected week on open
  window.reedcrm.pwaHome.picker.config.onOpen = [function() {
    window.reedcrm.pwaHome.highlightSelectedWeek();
  }];
};

/**
 * Add .pwa-week-hover class to all days in the same visual week row as the hovered day.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.highlightWeek = function() {
  var $day     = $(this);
  var $all     = $day.closest('.dayContainer').find('.flatpickr-day');
  var idx      = $all.index($day);
  var rowStart = Math.floor(idx / 7) * 7;
  $all.removeClass('pwa-week-hover');
  $all.slice(rowStart, rowStart + 7).addClass('pwa-week-hover');
};

/**
 * Remove the .pwa-week-hover class from all day cells.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.clearWeekHighlight = function() {
  $('.flatpickr-day').removeClass('pwa-week-hover');
};

/**
 * Highlight the week row of the currently selected / default date.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.highlightSelectedWeek = function() {
  var $selected = $('.flatpickr-day.selected');
  if ($selected.length) {
    window.reedcrm.pwaHome.highlightWeek.call($selected[0]);
  }
};
