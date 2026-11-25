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
 * \file    js/modules/home_dashboard.js
 * \ingroup reedcrm
 * \brief   Home page of ReedCRM: tooltip of the revenue chart and tabs of the product sales.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.homeDashboard = {};

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.homeDashboard.init = function () {
  window.reedcrm.homeDashboard.event();
};

/**
 * Bind events
 *
 * The module bundle calls init() once more than the page needs: the namespaced handlers are dropped before being set.
 *
 * @returns {void}
 */
window.reedcrm.homeDashboard.event = function () {
  $(document).off('.reedcrmHomeDashboard');
  $(document).on('mouseenter.reedcrmHomeDashboard', '.reedcrm-home-chart__hit', window.reedcrm.homeDashboard.showTooltip);
  $(document).on('mouseleave.reedcrmHomeDashboard', '.reedcrm-home-chart__hit', window.reedcrm.homeDashboard.hideTooltip);
  $(document).on('click.reedcrmHomeDashboard', '.reedcrm-home-tabs__tab', window.reedcrm.homeDashboard.switchTab);
};

/**
 * Show the revenue of the hovered month, next to the same month one year earlier
 *
 * @returns {void}
 */
window.reedcrm.homeDashboard.showTooltip = function () {
  const hit     = $(this);
  const plot    = hit.closest('.reedcrm-home-chart__plot');
  const tooltip = plot.find('.reedcrm-home-chart__tooltip');
  const nbMonth = plot.find('.reedcrm-home-chart__hit').length;
  const index   = parseInt(hit.data('index'), 10);

  plot.find('.reedcrm-home-chart__hit').removeClass('is-hovered');
  hit.addClass('is-hovered');

  const row = function (label, value, modifier) {
    return $('<span class="reedcrm-home-chart__tooltip-row">')
      .append($('<span class="reedcrm-home-chart__tooltip-key">').addClass(modifier).text(label))
      .append($('<span>').text(value));
  };

  tooltip.empty()
    .append($('<strong>').text(hit.data('month') + (hit.data('evolution') ? ' · ' + hit.data('evolution') : '')))
    .append(row(hit.data('title'), hit.data('current'), ''))
    .append(row(hit.data('previous-title'), hit.data('previous'), 'reedcrm-home-chart__tooltip-key--previous'));

  // The tooltip sits over the middle of its month, kept inside the plot at both ends
  const plotWidth = plot.width();
  const center    = (index + 0.5) / nbMonth * plotWidth;
  const half      = tooltip.outerWidth() / 2 || 90;
  tooltip.css('left', Math.min(Math.max(center, half), plotWidth - half) + 'px');
  tooltip.prop('hidden', false);
};

/**
 * Hide the tooltip of the revenue chart
 *
 * @returns {void}
 */
window.reedcrm.homeDashboard.hideTooltip = function () {
  const plot = $(this).closest('.reedcrm-home-chart__plot');

  $(this).removeClass('is-hovered');
  plot.find('.reedcrm-home-chart__tooltip').prop('hidden', true);
};

/**
 * Show the panel of the clicked tab of the product sales
 *
 * @returns {void}
 */
window.reedcrm.homeDashboard.switchTab = function () {
  const tab  = $(this);
  const card = tab.closest('.reedcrm-home-card');

  card.find('.reedcrm-home-tabs__tab').removeClass('is-active').attr('aria-selected', 'false');
  tab.addClass('is-active').attr('aria-selected', 'true');

  card.find('.reedcrm-home-tabs__panel').each(function () {
    $(this).prop('hidden', $(this).data('panel') !== tab.data('tab'));
  });
};
