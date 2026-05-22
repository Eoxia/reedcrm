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
 * \file    js/modules/kpiCustomize.js
 * \ingroup reedcrm
 * \brief   Customize mode for the opportunity KPI banner (drag reorder, hide/show, per-user persistence)
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.kpiCustomize = {};
window.reedcrm.kpiCustomize.dragSrc = null;

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.kpiCustomize.init = function () {
  window.reedcrm.kpiCustomize.event();
};

/**
 * Bind events
 *
 * @returns {void}
 */
window.reedcrm.kpiCustomize.event = function () {
  $(document).on('click', '.reedcrm-kpi-customize-toggle', window.reedcrm.kpiCustomize.toggle);
  $(document).on('click', '.reedcrm-kpi-customize-reset', window.reedcrm.kpiCustomize.reset);
  $(document).on('click', '.kpi-hide-btn', window.reedcrm.kpiCustomize.toggleHide);
  $(document).on('dragstart', '.saturne-kpi-card', window.reedcrm.kpiCustomize.onDragStart);
  $(document).on('dragover', '.saturne-kpi-card', window.reedcrm.kpiCustomize.onDragOver);
  $(document).on('dragend', '.saturne-kpi-card', window.reedcrm.kpiCustomize.onDragEnd);
};

/**
 * Endpoint URL
 *
 * @returns {String} The AJAX endpoint URL
 */
window.reedcrm.kpiCustomize.url = function () {
  var root = (window.saturne && window.saturne.config && window.saturne.config.urlRoot) ? window.saturne.config.urlRoot : '';
  return root + '/custom/reedcrm/ajax/save_kpi_layout.php';
};

/**
 * Toggle edit mode on the KPI cards bar.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.kpiCustomize.toggle = function (e) {
  e.preventDefault();
  var $cards = $('.saturne-kpi-cards');
  if (!$cards.length) {
    return;
  }

  var entering = !$cards.hasClass('saturne-kpi-customizing');
  $cards.toggleClass('saturne-kpi-customizing', entering);
  $('.reedcrm-kpi-customize-toggle').toggleClass('active', entering);
  $('.reedcrm-kpi-customize-bar').toggleClass('customizing', entering);

  if (entering) {
    $cards.find('.saturne-kpi-card').attr('draggable', 'true').each(function () {
      if (!$(this).find('.kpi-hide-btn').length) {
        $(this).append('<span class="kpi-hide-btn" title="Masquer / afficher"><i class="fas fa-eye-slash"></i></span>');
      }
    });
  } else {
    $cards.find('.saturne-kpi-card').removeAttr('draggable');
    window.reedcrm.kpiCustomize.save();
  }
};

/**
 * Hide / show a card.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.kpiCustomize.toggleHide = function (e) {
  e.preventDefault();
  e.stopPropagation();
  $(this).closest('.saturne-kpi-card').toggleClass('saturne-kpi-card--hidden');
  window.reedcrm.kpiCustomize.save();
};

/**
 * Drag start
 *
 * @param  {Event} e Drag event
 * @returns {void}
 */
window.reedcrm.kpiCustomize.onDragStart = function (e) {
  if (!$(this).closest('.saturne-kpi-cards').hasClass('saturne-kpi-customizing')) {
    return;
  }
  window.reedcrm.kpiCustomize.dragSrc = this;
  $(this).addClass('kpi-dragging');
  if (e.originalEvent.dataTransfer) {
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    try { e.originalEvent.dataTransfer.setData('text/plain', ''); } catch (err) { /* IE guard */ }
  }
};

/**
 * Drag over: reorder live based on cursor position.
 *
 * @param  {Event} e Drag event
 * @returns {void}
 */
window.reedcrm.kpiCustomize.onDragOver = function (e) {
  var src = window.reedcrm.kpiCustomize.dragSrc;
  if (!src || src === this) {
    return;
  }
  e.preventDefault();
  var rect  = this.getBoundingClientRect();
  var after = (e.originalEvent.clientX - rect.left) > (rect.width / 2);
  if (after) {
    this.parentNode.insertBefore(src, this.nextSibling);
  } else {
    this.parentNode.insertBefore(src, this);
  }
};

/**
 * Drag end: persist the new order.
 *
 * @returns {void}
 */
window.reedcrm.kpiCustomize.onDragEnd = function () {
  $(this).removeClass('kpi-dragging');
  window.reedcrm.kpiCustomize.dragSrc = null;
  window.reedcrm.kpiCustomize.save();
};

/**
 * Serialize the current order + hidden cards and persist them.
 *
 * @returns {void}
 */
window.reedcrm.kpiCustomize.save = function () {
  var order  = [];
  var hidden = [];
  $('.saturne-kpi-cards .saturne-kpi-card').each(function () {
    var id = $(this).attr('data-kpi-id');
    if (!id) {
      return;
    }
    order.push(id);
    if ($(this).hasClass('saturne-kpi-card--hidden')) {
      hidden.push(id);
    }
  });

  $.ajax({
    url: window.reedcrm.kpiCustomize.url(),
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'save',
      token: window.saturne.toolbox.getToken(),
      layout: JSON.stringify({ order: order, hidden: hidden })
    }
  });
};

/**
 * Reset the layout to defaults.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.kpiCustomize.reset = function (e) {
  e.preventDefault();
  $.ajax({
    url: window.reedcrm.kpiCustomize.url(),
    method: 'POST',
    dataType: 'json',
    data: { action: 'reset', token: window.saturne.toolbox.getToken() },
    success: function () { window.location.reload(); },
    error: function () { window.location.reload(); }
  });
};
