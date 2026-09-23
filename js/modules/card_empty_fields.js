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
 * \file    js/modules/card_empty_fields.js
 * \ingroup reedcrm
 * \brief   Toggle hiding the fields left empty on every object card, Dolibarr ones included.
 *          The chosen mode is saved per user, "all" (show everything) being the default.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * Init cardEmptyFields JS
 *
 * The object is kept when it already exists : the file is both bundled in reedcrm.min.js and
 * loaded on its own by the template on the pages the bundle never reaches, so it can run twice.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.cardEmptyFields = window.reedcrm.cardEmptyFields || {};

/**
 * Whether the toggle has already been wired on this page
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Boolean}
 */
window.reedcrm.cardEmptyFields.mounted = window.reedcrm.cardEmptyFields.mounted || false;

/**
 * Tables holding the fields of a card. Lists and document lines have their own markup and stay out.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {String}
 */
window.reedcrm.cardEmptyFields.tableSelector = 'table.tableforfield, div.fichecenter table.border, div.fichecenter2 table.border, div.tabBar > table.border';

/**
 * Init
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.cardEmptyFields.init = function () {
  if (window.reedcrm.cardEmptyFields.mounted) {
    return;
  }
  window.reedcrm.cardEmptyFields.mounted = true;

  // Display setup of a user : the same preference, as one more row of the parameters table
  window.reedcrm.cardEmptyFields.mountUserParam();

  if (!$('#reedcrm-card-fields-config').length) {
    return;
  }

  var hidden = window.reedcrm.cardEmptyFields.markEmptyRows();
  if (!hidden) {
    // Nothing to hide on this card, the toggle would have no purpose
    return;
  }

  window.reedcrm.cardEmptyFields.mount(hidden);
  window.reedcrm.cardEmptyFields.event();
};

/**
 * Move the row printed by the user display setup template into the parameters table.
 *
 * The page opens no hook inside that table, so the row travels in a holder table printed in the
 * footer. The landing page checkbox is the anchor : it is the one field the page keeps identical
 * whether the setup is being read or edited, and it does not depend on a translation.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.cardEmptyFields.mountUserParam = function () {
  var $holder = $('#reedcrm-user-param-card-fields-holder');
  if (!$holder.length) {
    return;
  }

  var $table = $('#check_MAIN_LANDING_PAGE').closest('table');
  var $row   = $holder.find('#reedcrm-user-param-card-fields');
  if (!$table.length || !$row.length) {
    $holder.remove();

    return;
  }

  ($table.children('tbody').length ? $table.children('tbody').last() : $table).append($row);
  $holder.remove();
};

/**
 * Bind events
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.cardEmptyFields.event = function () {
  $(document).on('click', '.reedcrm-card-fields-toggle', window.reedcrm.cardEmptyFields.toggle);
};

/**
 * Read a config value injected by the toggle template
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {String} name Name of the data attribute, without its "data-" prefix
 * @return {String}      Its value, an empty string when the config block is missing
 */
window.reedcrm.cardEmptyFields.config = function (name) {
  var value = $('#reedcrm-card-fields-config').attr('data-' + name);

  return typeof value === 'undefined' ? '' : value;
};

/**
 * Tell whether a value cell holds no value at all.
 *
 * Anything rendered that is not plain text (picto, badge, input, nested table) makes the cell count
 * as filled, so a doubtful field stays visible. Two things are not a value : the edit pencil, and
 * what an editable field keeps out of sight, the hidden input carrying its value and its inline
 * edit form. innerText is read rather than the text of the cell as it leaves the hidden ones out.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {Object}  $cell The jQuery cell to weigh
 * @return {Boolean}       True when the cell carries no value
 */
window.reedcrm.cardEmptyFields.isEmptyCell = function ($cell) {
  var displayed = $cell.find('input, select, textarea, button, img, svg, canvas, iframe, table, .badge, [class*="fa-"]').filter(function () {
    return $(this).is(':visible') && !$(this).closest('a.editfielda, .pictoedit').length;
  });

  if (displayed.length) {
    return false;
  }

  // \s covers the non breaking spaces Dolibarr fills an empty cell with
  var text = ($cell[0].innerText || '').replace(/\s+/g, ' ').trim();

  return text === '' || text === '-' || text === '--' || text === '—';
};

/**
 * Tag every field row left empty, and the tables where no field is filled at all.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {Number} How many rows have been tagged
 */
window.reedcrm.cardEmptyFields.markEmptyRows = function () {
  var hidden = 0;

  $(window.reedcrm.cardEmptyFields.tableSelector).each(function () {
    var $table = $(this);

    // Lists, search filters and document lines are not cards
    if ($table.hasClass('liste') || $table.attr('id') === 'tablelines' || $table.closest('table.liste, .div-table-responsive').length) {
      return;
    }

    var fieldRows = 0;
    var emptyRows = 0;

    $table.children('tbody').children('tr').add($table.children('tr')).each(function () {
      var $row = $(this);

      // Section titles, the rows spread over a single cell and the collapsed blocks are left alone
      if ($row.hasClass('liste_titre') || $row.children('th').length || !$row.is(':visible')) {
        return;
      }

      var $cells = $row.children('td');
      if ($cells.length < 2) {
        return;
      }

      // An empty label is a spacer row, and a field being edited must keep its input reachable.
      // Only the inputs on screen count : an editable field keeps a hidden one holding its value.
      if (!$cells.first().text().trim().length || $row.find('input, select, textarea').filter(':visible').length) {
        return;
      }

      fieldRows++;

      var filled = false;
      $cells.slice(1).each(function () {
        if (!window.reedcrm.cardEmptyFields.isEmptyCell($(this))) {
          filled = true;

          return false;
        }
      });

      if (!filled) {
        emptyRows++;
        $row.addClass('reedcrm-empty-field-row');
      }
    });

    hidden += emptyRows;

    // A table where every single field is empty leaves an empty frame behind, its separator too
    if (fieldRows > 0 && fieldRows === emptyRows && $table.find('tr').length === fieldRows) {
      $table.addClass('reedcrm-empty-field-table');
      $table.prev('.underbanner').addClass('reedcrm-empty-field-table');
    }
  });

  return hidden;
};

/**
 * Bring the toggle up into the card and apply the mode saved for the user.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {Number} hidden How many rows can be hidden
 * @return {void}
 */
window.reedcrm.cardEmptyFields.mount = function (hidden) {
  var $bar = $('#reedcrm-card-fields-bar');
  if (!$bar.length) {
    return;
  }

  var $firstRow = $('.reedcrm-empty-field-row').first();
  var $anchor   = $firstRow.closest('div.fichecenter, div.fichecenter2');
  if (!$anchor.length) {
    $anchor = $firstRow.closest('table');
  }
  if (!$anchor.length) {
    return;
  }

  $anchor.before($bar);
  $bar.attr('data-hidden-count', hidden).removeAttr('hidden');

  window.reedcrm.cardEmptyFields.apply(window.reedcrm.cardEmptyFields.config('mode') === 'filled' ? 'filled' : 'all');
};

/**
 * Apply a mode : hide or show the empty fields, and label the toggle with what it does next.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {String} mode "all" to show every field, "filled" to hide the empty ones
 * @return {void}
 */
window.reedcrm.cardEmptyFields.apply = function (mode) {
  var $bar     = $('#reedcrm-card-fields-bar');
  var $toggle  = $bar.find('.reedcrm-card-fields-toggle');
  var filled   = mode === 'filled';
  var hidden   = parseInt($bar.attr('data-hidden-count'), 10) || 0;
  var countFmt = window.reedcrm.cardEmptyFields.config('trans-hidden-count');

  $('body').toggleClass('reedcrm-hide-empty-fields', filled);

  $toggle.attr('data-mode', mode);
  $toggle.find('.reedcrm-card-fields-icon').attr('class', 'reedcrm-card-fields-icon fas ' + (filled ? 'fa-eye' : 'fa-eye-slash'));
  $toggle.find('.reedcrm-card-fields-label').text(window.reedcrm.cardEmptyFields.config(filled ? 'trans-show-all' : 'trans-hide-empty'));
  $toggle.find('.reedcrm-card-fields-count').text(filled ? countFmt.replace('%s', hidden) : '');
};

/**
 * Flip the mode and save it for the user.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {Event} event Click event
 * @return {void}
 */
window.reedcrm.cardEmptyFields.toggle = function (event) {
  event.preventDefault();

  var mode = $(this).attr('data-mode') === 'filled' ? 'all' : 'filled';

  window.reedcrm.cardEmptyFields.apply(mode);
  window.reedcrm.cardEmptyFields.save(mode);
};

/**
 * Save the mode as a user parameter, so every other card opens the same way.
 *
 * @memberof ReedCRM_CardEmptyFields
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {String} mode The mode to save
 * @return {void}
 */
window.reedcrm.cardEmptyFields.save = function (mode) {
  $.ajax({
    url: window.reedcrm.cardEmptyFields.config('url'),
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'set_card_fields_display',
      token: window.reedcrm.cardEmptyFields.config('token'),
      mode: mode
    }
  });
};

// Auto-initialize on document ready
jQuery(document).ready(function () {
  window.reedcrm.cardEmptyFields.init();
});
