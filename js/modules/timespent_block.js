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
 * \file    js/modules/timespent_block.js
 * \ingroup reedcrm
 * \brief   Quick time logging block of the ticket and project cards: moves it up into the card header,
 *          saves the time typed and refreshes the counter, the latest entry and the recap of the time spent.
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * Init timespentBlock JS
 *
 * The file is both bundled into reedcrm.min.js and loaded alone on the native cards: the second load keeps the state
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.timespentBlock = window.reedcrm.timespentBlock || {};

/**
 * Whether the block is already mounted
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Boolean}
 */
window.reedcrm.timespentBlock.mounted = window.reedcrm.timespentBlock.mounted || false;

/**
 * Init
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.timespentBlock.init = function () {
  if (window.reedcrm.timespentBlock.mounted || !$('#reedcrm-timespent-block').length) {
    return;
  }
  window.reedcrm.timespentBlock.mounted = true;

  window.reedcrm.timespentBlock.mount(0);
  window.reedcrm.timespentBlock.mountTable();
  window.reedcrm.timespentBlock.event();

  // A link to the table lands on it: the browser could not scroll to it while it waited hidden in the footer
  if (window.location.hash === '#reedcrm-project-time' && $('#reedcrm-project-time').length) {
    $('#reedcrm-project-time')[0].scrollIntoView();
  }
};

/**
 * Move the time card of the project into the status column of the banner, under the lifecycle timeline
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.timespentBlock.mountTable = function () {
  const $table = $('#reedcrm-project-time');
  if (!$table.length) {
    return;
  }

  // That column is shorter than the header blocks of the project beside it: the card fills it without making the
  // banner taller. Put after it, the grid of the banner would give the card a whole row of its own
  if ($('div.statusref').length) {
    $('div.statusref').first().append($table);
  } else {
    $('div.tabBar').first().prepend($table);
  }

  $table.prop('hidden', false);
  window.reedcrm.timespentBlock.refreshYearNav();
};

/**
 * Show the totals and the bars of the previous or the next year holding time
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.timespentBlock.switchYear = function () {
  const $charts = $('#reedcrm-project-time .reedcrm-project-time-chart');
  const index   = $charts.index($charts.filter(':not([hidden])')) + parseInt($(this).data('step'), 10);
  if (index < 0 || index >= $charts.length) {
    return;
  }

  // The totals in the header and the bars underneath carry the year they belong to
  const year = String($charts.eq(index).data('year'));
  $('#reedcrm-project-time [data-year]').each(function () {
    $(this).prop('hidden', String($(this).data('year')) !== year);
  });
  window.reedcrm.timespentBlock.refreshYearNav();
};

/**
 * Show the year displayed, grey the arrows leading to no year and frame the card in red when the year went beyond
 * the planned time
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.timespentBlock.refreshYearNav = function () {
  const $card   = $('#reedcrm-project-time');
  const $charts = $card.find('.reedcrm-project-time-chart');
  const $shown  = $charts.filter(':not([hidden])');
  const index   = $charts.index($shown);

  $card.find('.reedcrm-project-time-year').text($shown.data('year'));
  $card.find('.reedcrm-project-time-year-nav[data-step="-1"]').prop('disabled', index <= 0);
  $card.find('.reedcrm-project-time-year-nav[data-step="1"]').prop('disabled', index >= $charts.length - 1);
  $card.toggleClass('reedcrm-project-time-is-over', $card.find('.reedcrm-project-time-kpis:not([hidden]) .reedcrm-project-time-kpi-over').length > 0);
};

/**
 * Move the block up into the card header
 *
 * On the project card the header blocks are built by contact_inline.js, which may run after this module: wait for them a little
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   {Number} attempt Number of times the header blocks were already waited for
 * @returns {void}
 */
window.reedcrm.timespentBlock.mount = function (attempt) {
  const $block        = $('#reedcrm-timespent-block');
  const $headerBlocks = $('.reedcrm-card-header-blocks').first();

  if (!$headerBlocks.length && $('#reedcrm-inline-data').length && attempt < 10) {
    setTimeout(function () {
      window.reedcrm.timespentBlock.mount(attempt + 1);
    }, 50);
    return;
  }

  if ($headerBlocks.length) {
    $headerBlocks.append($block);
  } else if ($('div.arearefonsamedir > div:first-child').length) {
    $('div.arearefonsamedir > div:first-child').prepend($block);
  } else if ($('div.arearef').length) {
    // Shared with the severity and assignment blocks of the ticket card, laid out on the same line
    let $wrap = $('#reedcrm-ticket-blocks-wrap');
    if (!$wrap.length) {
      $wrap = $('<div id="reedcrm-ticket-blocks-wrap"></div>');
      $('div.arearef').first().append($wrap);
    }
    $wrap.append($block);
  } else {
    $('.refidno').first().after($block);
  }

  $block.prop('hidden', false);
};

/**
 * Bind the inputs of the block
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.timespentBlock.event = function () {
  $(document).off('input.reedcrmTimespent').on('input.reedcrmTimespent', '.reedcrm-timespent-note, .reedcrm-timespent-minutes', function () {
    $('.reedcrm-timespent-save').toggleClass('reedcrm-timespent-save-ready', $('.reedcrm-timespent-note').val().trim() !== '');
  });
  $(document).off('click.reedcrmTimespent').on('click.reedcrmTimespent', '.reedcrm-timespent-save', window.reedcrm.timespentBlock.save);
  $(document).on('click.reedcrmTimespent', '.reedcrm-project-time-year-nav', window.reedcrm.timespentBlock.switchYear);
};

/**
 * Save the time typed
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.timespentBlock.save = function () {
  const $block  = $('#reedcrm-timespent-block');
  const $button = $(this);
  const $icon   = $button.find('i');

  $button.prop('disabled', true);
  $icon.attr('class', 'fas fa-spinner fa-spin');

  $.ajax({
    url: $block.data('url'),
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'save_time',
      token: $block.data('token'),
      element_type: $block.data('element-type'),
      element_id: $block.data('element-id'),
      note: $block.find('.reedcrm-timespent-note').val(),
      minutes: $block.find('.reedcrm-timespent-minutes').val()
    },
    success: function (response) {
      if (!response || !response.success) {
        $.jnotify((response && response.error) || $block.data('trans-error'), 'error', true);
        return;
      }

      $block.find('.reedcrm-timespent-note').val('');
      $button.removeClass('reedcrm-timespent-save-ready').addClass('reedcrm-timespent-save-done');
      setTimeout(function () {
        $button.removeClass('reedcrm-timespent-save-done');
      }, 1500);

      window.reedcrm.timespentBlock.refresh($block, response);
    },
    error: function () {
      $.jnotify($block.data('trans-error'), 'error', true);
    },
    complete: function () {
      $button.prop('disabled', false);
      $icon.attr('class', 'fas fa-save');
    }
  });
};

/**
 * Refresh the block with the time spent sent back once the time is saved
 *
 * @memberof ReedCRM_TimespentBlock
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   {jQuery} $block   Block
 * @param   {Object} response Response of the save
 * @returns {void}
 */
window.reedcrm.timespentBlock.refresh = function ($block, response) {
  const $entries = $block.find('.reedcrm-timespent-entries');
  const $count   = $block.find('.reedcrm-timespent-count');

  // The task of a ticket is created by its first time entry: the counter only links to it from then on
  $entries.attr('title', response.tooltip);
  if (response.url) {
    $entries.attr('href', response.url);
  }

  $count.text(response.count).prop('hidden', response.count <= 0).addClass('reedcrm-timespent-count-pulse');
  setTimeout(function () {
    $count.removeClass('reedcrm-timespent-count-pulse');
  }, 300);

  if ($block.find('.reedcrm-timespent-last').length) {
    $block.find('.reedcrm-timespent-last').replaceWith(response.last_html);
  } else {
    $block.find('.reedcrm-timespent-row').after(response.last_html);
  }

  const $recap = $block.find('.reedcrm-timespent-recap');
  $recap.attr('title', response.recap_tooltip);
  $recap.find('.reedcrm-timespent-recap-total').text(response.total);
  $recap.find('.reedcrm-timespent-recap-planned').text(response.planned).prop('hidden', response.planned === '');

  // Monthly table of a project, shown from its first time entry on
  if (response.table_html) {
    $('#reedcrm-project-time').remove();
    $('body').append(response.table_html);
    window.reedcrm.timespentBlock.mountTable();
  }
};

$(document).ready(function () {
  window.reedcrm.timespentBlock.init();
});
