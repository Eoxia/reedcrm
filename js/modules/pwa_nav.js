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
 * \file    js/modules/pwa_nav.js
 * \ingroup reedcrm
 * \brief   PWA bottom nav: burger drawer + per-user favorite items (star toggle, AJAX persistence)
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.pwaNav = {};

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.pwaNav.init = function () {
  window.reedcrm.pwaNav.event();
};

/**
 * Bind events
 *
 * @returns {void}
 */
window.reedcrm.pwaNav.event = function () {
  $(document).on('click', '[data-action="toggle-pwa-nav-drawer"]', window.reedcrm.pwaNav.toggleDrawer);
  $(document).on('click', '[data-action="close-pwa-nav-drawer"]', window.reedcrm.pwaNav.closeDrawer);
  $(document).on('click', '[data-action="toggle-pwa-nav-favorite"]', window.reedcrm.pwaNav.toggleFavorite);
};

/**
 * Open/close the burger drawer.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.pwaNav.toggleDrawer = function (e) {
  e.preventDefault();
  window.reedcrm.pwaNav.setDrawerOpen(!$('.pwa-nav-drawer').hasClass('is-open'));
};

/**
 * Close the burger drawer.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.pwaNav.closeDrawer = function (e) {
  e.preventDefault();
  window.reedcrm.pwaNav.setDrawerOpen(false);
};

/**
 * Apply the drawer open/closed state.
 *
 * @param  {Boolean} open True to open, false to close
 * @returns {void}
 */
window.reedcrm.pwaNav.setDrawerOpen = function (open) {
  $('.pwa-nav-drawer').toggleClass('is-open', open);
  $('.pwa-nav-drawer-overlay').toggleClass('is-open', open);
  $('.pwa-nav-burger').toggleClass('active', open).attr('aria-expanded', open ? 'true' : 'false');
};

/**
 * Toggle an item as favorite from its star, persist via AJAX, then re-render the bar.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.pwaNav.toggleFavorite = function (e) {
  e.preventDefault();
  e.stopPropagation();

  var $btn    = $(this);
  var $item   = $btn.closest('.pwa-nav-drawer-item');
  var $drawer = $('.pwa-nav-drawer');
  var slug    = $item.attr('data-nav-slug');
  var max     = parseInt($drawer.attr('data-max-favorites'), 10) || 5;
  var adding  = !$btn.hasClass('is-favorite');

  // Rebuild the favorites list from the drawer (canonical DOM order) with the toggle applied
  var favorites = [];
  $drawer.find('.pwa-nav-drawer-item').each(function () {
    var itemSlug   = $(this).attr('data-nav-slug');
    var isFavorite = $(this).find('.pwa-nav-fav-toggle').hasClass('is-favorite');
    if (itemSlug === slug) {
      isFavorite = adding;
    }
    if (itemSlug && isFavorite) {
      favorites.push(itemSlug);
    }
  });

  if (adding && favorites.length > max) {
    $btn.addClass('shake');
    $drawer.find('.pwa-nav-drawer-hint').addClass('limit-reached');
    setTimeout(function () {
      $btn.removeClass('shake');
      $drawer.find('.pwa-nav-drawer-hint').removeClass('limit-reached');
    }, 1200);
    return;
  }

  var token = (window.saturne && window.saturne.toolbox) ? window.saturne.toolbox.getToken() : '';

  $btn.prop('disabled', true);
  $.ajax({
    url: $drawer.attr('data-ajax-url'),
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'save',
      token: token,
      favorites: favorites.join(',')
    }
  }).done(function (resp) {
    if (resp && resp.success) {
      $btn.toggleClass('is-favorite', adding);
      $btn.attr('aria-pressed', adding ? 'true' : 'false');
      $btn.attr('aria-label', adding ? 'Retirer des favoris' : 'Ajouter aux favoris');
      window.reedcrm.pwaNav.renderBar();
    }
  }).always(function () {
    $btn.prop('disabled', false);
  });
};

/**
 * Rebuild the bottom bar favorites from the drawer state (no reload needed).
 *
 * @returns {void}
 */
window.reedcrm.pwaNav.renderBar = function () {
  var $bar = $('.pwa-bottom-nav .nav-favorites');
  if (!$bar.length) {
    return;
  }

  $bar.empty();
  $('.pwa-nav-drawer .pwa-nav-drawer-item').each(function () {
    if (!$(this).find('.pwa-nav-fav-toggle').hasClass('is-favorite')) {
      return;
    }
    var $link  = $(this).find('.pwa-nav-drawer-link');
    var $navItem = $('<a>', {
      href: $link.attr('href'),
      'class': 'pwa-nav-item' + ($(this).hasClass('active') ? ' active' : ''),
      'data-nav-slug': $(this).attr('data-nav-slug')
    });
    $navItem.append($link.find('i').clone());
    $navItem.append($('<span>').text($link.find('span').text()));
    $bar.append($navItem);
  });
};
