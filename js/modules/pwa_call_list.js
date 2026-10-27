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
 * \file    js/modules/pwa_call_list.js
 * \ingroup reedcrm
 * \brief   PWA call list view: copy phone number to clipboard with visual feedback, unlink a line, counters and nav badge sync
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.pwaCallList = {};

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.pwaCallList.init = function () {
  window.reedcrm.pwaCallList.event();
  window.reedcrm.pwaCallList.watchToCallCount();
};

/**
 * Keep the header counters, and the nav badge on the user's default call list, in sync
 * while lines change status or are unlinked.
 *
 * The status buttons only update the card data-status attribute and the unlink button
 * removes the card, so watch both instead of hooking into their AJAX calls.
 *
 * @returns {void}
 */
window.reedcrm.pwaCallList.watchToCallCount = function () {
  var container = document.querySelector('.pwa-call-list-container');
  if (!container || !window.MutationObserver) {
    return;
  }
  var isDefaultList = container.dataset.defaultList === '1';

  new MutationObserver(function () {
    var toCallCount = container.querySelectorAll('.pwa-call-card[data-status="0"]').length;
    $('.pwa-call-list-to-call-count').text(toCallCount);
    $('.pwa-call-list-total-count').text(container.querySelectorAll('.pwa-call-card').length);
    if (isDefaultList && window.reedcrm.pwaNav) {
      window.reedcrm.pwaNav.setBadge('call_list', toCallCount);
    }
  }).observe(container, {attributes: true, attributeFilter: ['data-status'], childList: true, subtree: true});
};

/**
 * Bind events
 *
 * @returns {void}
 */
window.reedcrm.pwaCallList.event = function () {
  $(document).on('click', '[data-action="copy-phone"]', window.reedcrm.pwaCallList.copyPhone);
  $(document).on('click', '[data-action="unlink-line"]', window.reedcrm.pwaCallList.unlinkLine);
  $(document).on('change', '#pwa-call-list-select', window.reedcrm.pwaCallList.switchList);
};

/**
 * Remove the line of the card from the call list, after confirmation.
 *
 * Once the last card is gone, reload so the page shows its empty state.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.pwaCallList.unlinkLine = function (e) {
  e.preventDefault();

  var $btn       = $(this);
  var $card      = $btn.closest('.pwa-call-card');
  var $container = $btn.closest('.pwa-call-list-container');
  if ($btn.hasClass('loading') || !window.confirm($container.attr('data-unlink-confirm'))) {
    return;
  }

  $btn.addClass('loading');
  $.ajax({
    url: $container.attr('data-unlink-url'),
    type: 'POST',
    data: {line_id: $card.attr('data-line-id'), token: $container.attr('data-token')},
    dataType: 'json',
    success: function (data) {
      if (!data.success) {
        window.reedcrm.pwaCallList.showError($card, data.error || $container.attr('data-unlink-error'));
        return;
      }
      $card.fadeOut(200, function () {
        $card.remove();
        if (!$container.find('.pwa-call-card').length) {
          window.location.reload();
        }
      });
    },
    error: function () {
      window.reedcrm.pwaCallList.showError($card, $container.attr('data-unlink-error'));
    },
    complete: function () {
      $btn.removeClass('loading');
    }
  });
};

/**
 * Show an error at the bottom of a card for a few seconds.
 *
 * @param  {Object} $card   jQuery call card
 * @param  {String} message Error message
 * @returns {void}
 */
window.reedcrm.pwaCallList.showError = function ($card, message) {
  var $error = $card.find('.pwa-call-error');
  $error.text(message).show();
  setTimeout(function () {
    $error.hide();
  }, 3000);
};

/**
 * Navigate to the call list picked in the selector.
 *
 * @returns {void}
 */
window.reedcrm.pwaCallList.switchList = function () {
  var listId = $(this).val();
  if (listId) {
    window.location.href = '?id=' + listId;
  }
};

/**
 * Copy the phone number to the clipboard then show a short visual feedback.
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.pwaCallList.copyPhone = function (e) {
  e.preventDefault();

  var $btn  = $(this);
  var phone = ($btn.attr('data-phone') || '').trim();
  if (!phone) {
    return;
  }

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(phone).then(function () {
      window.reedcrm.pwaCallList.copyFeedback($btn);
    }).catch(function () {
      window.reedcrm.pwaCallList.copyFallback(phone, $btn);
    });
  } else {
    window.reedcrm.pwaCallList.copyFallback(phone, $btn);
  }
};

/**
 * Clipboard fallback for non-secure contexts (textarea + execCommand).
 *
 * @param  {String} phone Phone number to copy
 * @param  {Object} $btn  jQuery copy button
 * @returns {void}
 */
window.reedcrm.pwaCallList.copyFallback = function (phone, $btn) {
  var textarea = document.createElement('textarea');
  textarea.value = phone;
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.focus();
  textarea.select();
  try {
    if (document.execCommand('copy')) {
      window.reedcrm.pwaCallList.copyFeedback($btn);
    }
  } catch (err) {
    // Clipboard unavailable: nothing else we can do
  }
  document.body.removeChild(textarea);
};

/**
 * Short "copied" feedback on the button (check icon + green state).
 *
 * @param  {Object} $btn jQuery copy button
 * @returns {void}
 */
window.reedcrm.pwaCallList.copyFeedback = function ($btn) {
  $btn.addClass('is-copied');
  $btn.find('i').removeClass('fa-copy').addClass('fa-check');
  setTimeout(function () {
    $btn.removeClass('is-copied');
    $btn.find('i').removeClass('fa-check').addClass('fa-copy');
  }, 1500);
};
