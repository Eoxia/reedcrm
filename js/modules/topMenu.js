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
 * \file    js/modules/topMenu.js
 * \ingroup reedcrm
 * \brief   Gather the decluttered (non-CRM) top-menu entries into a "Plus" dropdown (CRM context only)
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.topMenu = {};

// Top-menu entries kept visible in the CRM context (must mirror _top-menu.scss)
window.reedcrm.topMenu.keep = [
  'mainmenutd_companylogo', 'mainmenutd_menu', 'mainmenutd_home', 'mainmenutd_companies',
  'mainmenutd_commercial', 'mainmenutd_project', 'mainmenutd_agenda', 'mainmenutd_reedcrm', 'mainmenutd_'
];

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.topMenu.init = function () {
  window.reedcrm.topMenu.build();
  window.reedcrm.topMenu.event();
};

/**
 * Events: toggle the dropdown, close on outside click.
 *
 * @returns {void}
 */
window.reedcrm.topMenu.event = function () {
  $(document).on('click', '.reedcrm-topmenu-more-toggle', function (e) {
    e.preventDefault();
    $(this).closest('.reedcrm-topmenu-more').toggleClass('open');
  });
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.reedcrm-topmenu-more').length) {
      $('.reedcrm-topmenu-more').removeClass('open');
    }
  });
};

/**
 * Build the "Plus" dropdown from the hidden (non-kept) top-menu entries.
 *
 * @returns {void}
 */
window.reedcrm.topMenu.build = function () {
  var $first = $('#id-top li[id^="mainmenutd_"]').first();
  if (!$first.length || $('.reedcrm-topmenu-more').length) {
    return;
  }
  var $menu = $first.parent();

  var items = [];
  $menu.children('li[id^="mainmenutd_"]').each(function () {
    if (window.reedcrm.topMenu.keep.indexOf(this.id) !== -1) {
      return;
    }
    var $a = $(this).find('a').first();
    var href = $a.attr('href');
    if ($a.length && href && href !== '#') {
      items.push({ href: href, label: $a.attr('title') || $.trim($a.text()) });
    }
  });
  if (!items.length) {
    return;
  }

  var html = '<li class="tmenu reedcrm-topmenu-more">'
    + '<a href="#" class="tmenu reedcrm-topmenu-more-toggle"><span class="fas fa-ellipsis-h"></span> Plus</a>'
    + '<ul class="reedcrm-topmenu-more-panel">';
  items.forEach(function (it) {
    html += '<li><a href="' + it.href + '">' + $('<div>').text(it.label).html() + '</a></li>';
  });
  html += '</ul></li>';

  $menu.append(html);
};
