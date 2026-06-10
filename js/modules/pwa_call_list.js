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
 * \brief   PWA call list view: copy phone number to clipboard with visual feedback
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
};

/**
 * Bind events
 *
 * @returns {void}
 */
window.reedcrm.pwaCallList.event = function () {
  $(document).on('click', '[data-action="copy-phone"]', window.reedcrm.pwaCallList.copyPhone);
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
