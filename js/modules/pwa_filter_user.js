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
 * \file    js/modules/pwa_filter_user.js
 * \ingroup reedcrm
 * \brief   JavaScript module for the PWA person filter combobox in the header
 */

'use strict';

/**
 * Init pwaFilterUser JS
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {Object}
 */
window.reedcrm.pwaFilterUser = {};

/**
 * Cached user list loaded from the data attribute
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {Array}
 */
window.reedcrm.pwaFilterUser.allUsers = [];

/**
 * Label that was shown before the input was focused (restored on blur if nothing chosen)
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {string}
 */
window.reedcrm.pwaFilterUser.savedLabel = '';

/**
 * pwaFilterUser init
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.init = function() {
  var $combo = $('#pwa-user-combo');
  if ($combo.length === 0) {
    return;
  }

  var usersData = $combo.data('users');
  if (usersData) {
    window.reedcrm.pwaFilterUser.allUsers = usersData;
  }

  window.reedcrm.pwaFilterUser.savedLabel = $('#pwa-filter-uid-input').val();
  window.reedcrm.pwaFilterUser.event();
};

/**
 * pwaFilterUser event
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.event = function() {
  $(document).on('focus',     '#pwa-filter-uid-input',    window.reedcrm.pwaFilterUser.onFocus);
  $(document).on('input',     '#pwa-filter-uid-input',    window.reedcrm.pwaFilterUser.onInput);
  $(document).on('blur',      '#pwa-filter-uid-input',    window.reedcrm.pwaFilterUser.onBlur);
  $(document).on('mousedown', '#pwa-filter-uid-list li',  window.reedcrm.pwaFilterUser.onItemSelect);
  $(document).on('keydown',   '#pwa-filter-uid-input',    window.reedcrm.pwaFilterUser.onKeydown);
  $(document).on('click',     '#pwa-filter-uid-clear',    window.reedcrm.pwaFilterUser.onClear);
};

/**
 * Handle focus on the filter input: save current label, clear field, show full list
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.onFocus = function() {
  window.reedcrm.pwaFilterUser.savedLabel = $(this).val();
  $(this).val('');
  window.reedcrm.pwaFilterUser.renderList('');
  $('#pwa-filter-uid-list').show();
};

/**
 * Handle input on the filter field: filter the dropdown in real time
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.onInput = function() {
  window.reedcrm.pwaFilterUser.renderList($(this).val());
  $('#pwa-filter-uid-list').show();
};

/**
 * Handle blur on the filter input: hide dropdown, restore label if nothing was chosen
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.onBlur = function() {
  var $input = $(this);
  setTimeout(function() {
    $('#pwa-filter-uid-list').hide();
    if ($input.val() === '') {
      $input.val(window.reedcrm.pwaFilterUser.savedLabel);
    }
  }, 150);
};

/**
 * Handle mousedown on a dropdown item: select the user
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param   {Event} e
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.onItemSelect = function(e) {
  e.preventDefault();
  var $li    = $(this);
  var userId = $li.data('user-id');
  var label  = $li.data('user-label');
  window.reedcrm.pwaFilterUser.selectUser(userId, label);
};

/**
 * Handle keyboard shortcuts in the filter input
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param   {Event} e
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.onKeydown = function(e) {
  if (e.key === 'Escape') {
    $(this).val(window.reedcrm.pwaFilterUser.savedLabel);
    $('#pwa-filter-uid-list').hide();
    $(this).blur();
  }
  if (e.key === 'Enter') {
    e.preventDefault();
  }
};

/**
 * Handle click on the clear (×) button: reset to "Tout le monde"
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.onClear = function() {
  window.reedcrm.pwaFilterUser.selectUser(0, '');
};

/**
 * Select a user: update hidden input + visible label, then submit the form
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param   {number} userId
 * @param   {string} label
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.selectUser = function(userId, label) {
  $('#pwa-filter-uid-val').val(userId);
  $('#pwa-filter-uid-input').val(userId === 0 ? 'Tout le monde' : label);
  $('#pwa-filter-uid-list').hide();
  $('#pwa-filter-user-form').submit();
};

/**
 * Render the dropdown list filtered by the given query string
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param   {string} query
 * @returns {void}
 */
window.reedcrm.pwaFilterUser.renderList = function(query) {
  var $list    = $('#pwa-filter-uid-list');
  var q        = query.toLowerCase().trim();
  var filtered = $.grep(window.reedcrm.pwaFilterUser.allUsers, function(u) {
    return u.label.toLowerCase().indexOf(q) !== -1;
  });

  $list.empty();

  if (filtered.length === 0) {
    $list.append(
      $('<li>').addClass('pwa-combo-no-result').text('Aucun résultat')
    );
    return;
  }

  $.each(filtered, function(i, u) {
    var $avatarWrap = $('<div>').addClass('pwa-combo-item-avatar').html(u.avatarHtml);
    var $name       = $('<span>').addClass('pwa-combo-item-name').text(u.label);
    var $li         = $('<li>')
      .addClass('pwa-combo-item' + (u.id === 0 ? ' pwa-combo-item--everyone' : ''))
      .data('user-id',    u.id)
      .data('user-label', u.label)
      .append($avatarWrap)
      .append($name);
    $list.append($li);
  });
};
