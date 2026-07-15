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
 * \file    js/modules/agenda_today_line.js
 * \ingroup reedcrm
 * \brief   Materialize the current day in agenda / linked-events lists (Dolibarr
 *          showactions tables) by inserting a red "today" separator line between
 *          future events and today-or-past events.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.agendaTodayLine = {};

/**
 * Init: enhance every agenda / linked-events list found on the page.
 *
 * @returns {void}
 */
window.reedcrm.agendaTodayLine.init = function () {
  // A Dolibarr "showactions" events list is recognizable by its sortable Date
  // column header, which sorts on 'a.datep'. Use it to locate agenda tables
  // without depending on any server-side marker.
  var tables = [];
  $('tr.liste_titre a[href*="a.datep"]').each(function () {
    var table = $(this).closest('table').get(0);
    if (table && tables.indexOf(table) === -1) {
      tables.push(table);
    }
  });

  var todayNum = window.reedcrm.agendaTodayLine.dateToNum(new Date());
  tables.forEach(function (table) {
    window.reedcrm.agendaTodayLine.render($(table), todayNum);
  });
};

/**
 * Convert a Date to a comparable YYYYMMDD integer.
 *
 * @param  {Date} date
 * @returns {number}
 */
window.reedcrm.agendaTodayLine.dateToNum = function (date) {
  return date.getFullYear() * 10000 + (date.getMonth() + 1) * 100 + date.getDate();
};

/**
 * Parse the start date (as a YYYYMMDD integer) from a date-cell innerHTML such as
 * '<div ...>15/08/26<br><span>14:00</span></div>'. The <br>/tags between the date
 * and the time keep the match anchored to the day portion. Returns null if none.
 *
 * @param  {string} html Inner HTML of the date cell
 * @returns {?number}
 */
window.reedcrm.agendaTodayLine.parseDateNum = function (html) {
  var match = (html || '').match(/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/);
  if (!match) {
    return null;
  }
  var day   = parseInt(match[1], 10);
  var month = parseInt(match[2], 10);
  var year  = parseInt(match[3], 10);
  if (year < 100) {
    year += 2000;
  }
  return year * 10000 + month * 100 + day;
};

/**
 * Compute where the today line must be inserted among the row date-numbers.
 * The line separates future events (date > today) from today-or-past events
 * (date <= today). Handles both descending (showactions default) and ascending
 * date sort, and the cases where today falls before/after the whole list.
 *
 * @param  {Array<?number>} nums     Row date-numbers in DOM order (null if unparseable)
 * @param  {number}         todayNum Today as YYYYMMDD
 * @returns {?{index:number, where:string}} Anchor row index + 'before'|'after', null if no dates
 */
window.reedcrm.agendaTodayLine.computeInsertion = function (nums, todayNum) {
  var firstValid = null;
  var lastValid  = null;
  for (var i = 0; i < nums.length; i++) {
    if (nums[i] !== null) {
      if (firstValid === null) {
        firstValid = nums[i];
      }
      lastValid = nums[i];
    }
  }
  if (firstValid === null) {
    return null;
  }

  var descending = firstValid >= lastValid;
  var idx;
  if (descending) {
    // future -> past: line before the first row that is today-or-past
    for (idx = 0; idx < nums.length; idx++) {
      if (nums[idx] !== null && nums[idx] <= todayNum) {
        return { index: idx, where: 'before' };
      }
    }
  } else {
    // past -> future: line before the first row that is in the future
    for (idx = 0; idx < nums.length; idx++) {
      if (nums[idx] !== null && nums[idx] > todayNum) {
        return { index: idx, where: 'before' };
      }
    }
  }
  // The today boundary is beyond the last row.
  return { index: nums.length - 1, where: 'after' };
};

/**
 * Insert (or refresh) the today line inside a single events table.
 *
 * @param  {jQuery} $table   The events table
 * @param  {number} todayNum Today as YYYYMMDD
 * @returns {void}
 */
window.reedcrm.agendaTodayLine.render = function ($table, todayNum) {
  $table.find('tr.reedcrm-today-line').remove();

  // Data rows only: skip the "None" / "More..." rows (single colspan cell).
  var $rows = $table.find('tr.oddeven').filter(function () {
    return $(this).children('td').length > 1;
  });
  if ($rows.length === 0) {
    return;
  }

  var nums = [];
  $rows.each(function () {
    nums.push(window.reedcrm.agendaTodayLine.parseDateNum($(this).children('td').eq(1).html()));
  });

  var insertion = window.reedcrm.agendaTodayLine.computeInsertion(nums, todayNum);
  if (!insertion) {
    return;
  }

  var colspan = $table.find('tr.liste_titre').first().children().length || 6;
  var $line = $(
    '<tr class="reedcrm-today-line" aria-hidden="true">' +
    '<td colspan="' + colspan + '"><span class="reedcrm-today-line__label">Aujourd\'hui</span></td>' +
    '</tr>'
  );

  var $anchor = $rows.eq(insertion.index);
  if (insertion.where === 'after') {
    $anchor.after($line);
  } else {
    $anchor.before($line);
  }
};
