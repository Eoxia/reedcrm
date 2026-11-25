/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    js/modules/opp_inline_sync.js
 * \ingroup reedcrm
 * \brief   Keep inline opportunity status and probability (0-100%) synchronized in list tables
 */

'use strict';

window.reedcrm = window.reedcrm || {};
window.reedcrm.oppInlineSync = window.reedcrm.oppInlineSync || {};

window.reedcrm.oppInlineSync.PERCENT_REGEX = /^(?:100(?:[.,]0{1,2})?|[0-9]{1,2}(?:[.,][0-9]{1,2})?)$/;

window.reedcrm.oppInlineSync.init = function () {
  $(document)
    .off('.reedcrmOppSync')
    .on('focus.reedcrmOppSync', '.contenteditable[data-field="opp_percent"]', window.reedcrm.oppInlineSync.onPercentFocus)
    .on('blur.reedcrmOppSync', '.contenteditable[data-field="opp_percent"]', window.reedcrm.oppInlineSync.onPercentBlur)
    .on('change.reedcrmOppSync', 'select.reedcrm-opp-status-select', window.reedcrm.oppInlineSync.onStatusChange);
};

window.reedcrm.oppInlineSync.parsePercent = function (raw) {
  var trimmed = $.trim(String(raw || ''));
  if (!trimmed || !window.reedcrm.oppInlineSync.PERCENT_REGEX.test(trimmed)) {
    return null;
  }
  var num = parseFloat(trimmed.replace(',', '.'));
  if (isNaN(num) || num < 0 || num > 100) {
    return null;
  }
  return num;
};

window.reedcrm.oppInlineSync.formatPercent = function (num) {
  return Number(num.toFixed(2)).toString();
};

window.reedcrm.oppInlineSync.updateStatusSelectClass = function ($select, code) {
  var classes = ($select.attr('class') || '').split(/\s+/).filter(function (cls) {
    return cls && (cls === 'reedcrm-opp-status-select' || cls.indexOf('reedcrm-opp-status-') !== 0);
  });
  if (code) {
    classes.push('reedcrm-opp-status-' + code);
  }
  $select.attr('class', classes.join(' '));
};

window.reedcrm.oppInlineSync.updateRowTint = function ($tr, projectId, percent) {
  if (!$tr.length) {
    return;
  }
  var rowColor;
  if (percent < 20) {
    rowColor = 'rgba(107, 114, 128, 0.08)';
  } else if (percent < 60) {
    rowColor = 'rgba(239, 68, 68, 0.08)';
  } else {
    rowColor = 'rgba(34, 197, 94, 0.08)';
  }

  var rowId = projectId || $tr.attr('data-rowid');
  var $style = rowId ? $tr.find('style[data-opp-row-style="' + rowId + '"]') : $();
  if ($style.length && rowId) {
    $style.text('tr[data-rowid="' + rowId + '"]{background-color:' + rowColor + '!important}');
  } else {
    $tr[0].style.setProperty('background-color', rowColor, 'important');
  }
};

window.reedcrm.oppInlineSync.findMatchingStatusId = function ($select, percent) {
  var $current = $select.find('option:selected');
  var currentCode = $current.attr('data-code') || '';
  if (currentCode === 'LOST' && percent === 0) {
    return $select.val();
  }

  var candidates = [];
  $select.find('option').each(function () {
    var $opt = $(this);
    var val = parseInt($opt.val(), 10);
    var optPctAttr = $opt.attr('data-percent');
    var code = $opt.attr('data-code') || '';
    if (!val || val <= 0 || optPctAttr === undefined || optPctAttr === '' || code === 'LOST') {
      return;
    }
    var optPct = parseFloat(optPctAttr);
    if (!isNaN(optPct)) {
      candidates.push({ id: String(val), percent: optPct });
    }
  });

  if (!candidates.length) {
    return null;
  }

  candidates.sort(function (a, b) {
    return a.percent - b.percent;
  });

  // Match the active stage with the highest default probability not exceeding the entered percentage
  var matchedId = candidates[0].id;
  for (var i = 0; i < candidates.length; i++) {
    if (candidates[i].percent > percent) {
      break;
    }
    matchedId = candidates[i].id;
  }
  return matchedId;
};

window.reedcrm.oppInlineSync.onStatusChange = function () {
  var $select = $(this);
  var $opt = $select.find('option:selected');
  var statusCode = $opt.attr('data-code') || '';
  var defaultPct = $opt.attr('data-percent');

  window.reedcrm.oppInlineSync.updateStatusSelectClass($select, statusCode);

  if ($select.data('reedcrmSyncedFromPercent')) {
    $select.removeData('reedcrmSyncedFromPercent');
    return;
  }

  if (defaultPct === undefined || defaultPct === '') {
    return;
  }

  var $tr = $select.closest('tr');
  var $pctField = $tr.find('.contenteditable[data-field="opp_percent"]').first();
  if (!$pctField.length) {
    return;
  }

  var numPct = window.reedcrm.oppInlineSync.parsePercent(defaultPct);
  if (numPct !== null) {
    window.reedcrm.oppInlineSync.updateRowTint($tr, $pctField.data('id') || $select.data('id'), numPct);
  }

  if ($.trim($pctField.text()) !== String(defaultPct)) {
    $pctField.data('reedcrmOppOrig', String(defaultPct));
    $pctField.data('originalValue', String(defaultPct));
    $pctField.data('reedcrmSyncedFromStatus', true);
    $pctField.text(String(defaultPct)).data('changed', true).trigger('blur');
  }
};

window.reedcrm.oppInlineSync.onPercentFocus = function () {
  var $el = $(this);
  $el.data('reedcrmOppOrig', $.trim($el.text()));
};

window.reedcrm.oppInlineSync.onPercentBlur = function () {
  var $el = $(this);
  var rawVal = $.trim($el.text());
  var origVal = $el.data('reedcrmOppOrig');
  if (origVal === undefined) {
    origVal = $el.data('originalValue');
  }

  if (rawVal === '') {
    if ($el.data('reedcrmSyncedFromStatus')) {
      $el.removeData('reedcrmSyncedFromStatus');
      return;
    }
    if (origVal !== undefined && origVal !== '') {
      $el.text(origVal).data('changed', false);
    }
    return;
  }

  var pct = window.reedcrm.oppInlineSync.parsePercent(rawVal);
  if (pct === null) {
    $el.text(origVal !== undefined ? origVal : '').data('changed', false);
    $el.removeData('reedcrmSyncedFromStatus');
    return;
  }

  var formatted = window.reedcrm.oppInlineSync.formatPercent(pct);
  if (rawVal !== formatted) {
    $el.text(formatted);
  }

  var origPct = (origVal !== undefined && origVal !== '') ? window.reedcrm.oppInlineSync.parsePercent(origVal) : null;
  $el.data('reedcrmOppOrig', formatted);
  $el.data('originalValue', formatted);

  var $tr = $el.closest('tr');
  window.reedcrm.oppInlineSync.updateRowTint($tr, $el.data('id'), pct);

  if ($el.data('reedcrmSyncedFromStatus')) {
    $el.removeData('reedcrmSyncedFromStatus');
    return;
  }

  if (pct !== origPct) {
    var $select = $tr.find('select.reedcrm-opp-status-select').first();
    if ($select.length) {
      var matchedStatusId = window.reedcrm.oppInlineSync.findMatchingStatusId($select, pct);
      if (matchedStatusId && String($select.val()) !== String(matchedStatusId)) {
        $select.data('reedcrmSyncedFromPercent', true);
        $select.val(String(matchedStatusId)).trigger('change');
      }
    }
  }
};
