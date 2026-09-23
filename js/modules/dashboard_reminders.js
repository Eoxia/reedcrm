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
 * \file    js/modules/dashboard_reminders.js
 * \ingroup reedcrm
 * \brief   Move the "upcoming call reminders" card (rendered above the dashboard by the
 *          saturneIndex hook) into the graph grid, in the top-right cell.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.dashboardReminders = {};

/**
 * Init.
 *
 * @returns {void}
 */
window.reedcrm.dashboardReminders.init = function () {
  window.reedcrm.dashboardReminders.placeInGrid();
};

/**
 * Relocate the reminders card into the dashboard graph grid (second cell = top right).
 * Falls back to its standalone top-right position if the grid is not present.
 *
 * @returns {void}
 */
window.reedcrm.dashboardReminders.placeInGrid = function () {
  var $slot = $('.reedcrm-reminders-slot');
  var $grid = $('#graph-dashboard');

  if (!$slot.length || !$grid.length) {
    return;
  }

  var $cells = $grid.children();
  if ($cells.length > 0) {
    $cells.eq(0).after($slot);
  } else {
    $grid.append($slot);
  }
};
