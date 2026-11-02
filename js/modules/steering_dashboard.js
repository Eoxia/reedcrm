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
 * \file    js/modules/steering_dashboard.js
 * \ingroup reedcrm
 * \brief   Keep the widgets of the sales and direction dashboards in step with their filters.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.steeringDashboard = {};

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.steeringDashboard.init = function () {
  window.reedcrm.steeringDashboard.event();
  window.reedcrm.steeringDashboard.showFilters();
};

/**
 * Show the filters of the dashboard right away
 *
 * The person and the period are the first thing a reader of these dashboards changes: the Saturne dashboard folds
 * its filters behind a button, they are unfolded on these pages, before and after every change.
 *
 * @returns {void}
 */
window.reedcrm.steeringDashboard.showFilters = function () {
  if (!$('body').hasClass('reedcrm-steering-dashboard')) {
    return;
  }

  $('#graph-filters').css('display', 'flex');
};

/**
 * Bind events
 *
 * @returns {void}
 */
window.reedcrm.steeringDashboard.event = function () {
  $(document).ajaxSuccess(window.reedcrm.steeringDashboard.refreshWidgets);
};

/**
 * Replace the widgets with the ones of the page the dashboard filter answered with
 *
 * The Saturne dashboard only redraws its graphs when a filter changes, while every widget of these dashboards
 * follows the period and the sales representative as well: without this, they would keep showing the figures of
 * the previous filter until the page is reloaded.
 *
 * @param  {Event}  event    Ajax event
 * @param  {Object} xhr      Request answered
 * @param  {Object} settings Settings of the request
 * @returns {void}
 */
window.reedcrm.steeringDashboard.refreshWidgets = function (event, xhr, settings) {
  if (!$('body').hasClass('reedcrm-steering-dashboard') || !settings || !settings.url || settings.url.indexOf('action=dashboardfilter') === -1) {
    return;
  }

  // The filters were just replaced by the folded ones of the answer
  window.reedcrm.steeringDashboard.showFilters();

  let newWidgets = $('<div>').append($.parseHTML(xhr.responseText)).find('#widget-dashboard');
  if (!newWidgets.length) {
    return;
  }
  $('#widget-dashboard').replaceWith(newWidgets);

  // Dolibarr binds its tooltips once, on page load: the help pictos of the new widgets need theirs
  if ($.fn.tooltip) {
    newWidgets.find('.classfortooltip').tooltip({
      show: {collision: 'flipfit', effect: 'toggle', delay: 50},
      hide: {delay: 50},
      tooltipClass: 'mytooltip',
      content: function () {
        return $(this).prop('title');
      }
    });
  }
};
