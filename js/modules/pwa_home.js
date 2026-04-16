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
 * \brief   JavaScript module for the PWA home page (week picker navigation)
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
 * pwaHome init
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.init = function() {
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
  $(document).on('change', '#pwa-week-picker', window.reedcrm.pwaHome.onWeekChange);
};

/**
 * Navigate to the selected week when the week input changes.
 * Converts the YYYY-Www value to the ISO Monday date and redirects.
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaHome.onWeekChange = function() {
  var val = $(this).val();
  if (!val) {
    return;
  }

  var pageUrl = $(this).data('page-url');
  var parts   = val.split('-W');
  if (parts.length !== 2) {
    return;
  }

  var year = parseInt(parts[0], 10);
  var week = parseInt(parts[1], 10);

  // ISO week 1 contains Jan 4. Find the Monday of week 1, then offset by (week-1)*7 days.
  var jan4         = new Date(year, 0, 4);
  var dayOfWeek    = jan4.getDay() || 7; // 1=Mon … 7=Sun
  var monday       = new Date(jan4);
  monday.setDate(jan4.getDate() - (dayOfWeek - 1) + (week - 1) * 7);

  var pad     = function(n) { return ('0' + n).slice(-2); };
  var dateStr = monday.getFullYear() + '-' + pad(monday.getMonth() + 1) + '-' + pad(monday.getDate());

  window.location.href = pageUrl + '?week_start=' + dateStr;
};
