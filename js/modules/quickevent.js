/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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

"use strict";

/**
 * \file    js/quickevent.js
 * \ingroup reedcrm
 * \brief   JavaScript quickevent file for module ReedCRM
 */

/**
 * Init quickevent JS
 *
 * @memberof ReedCRM_QuickEvent
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @type {Object}
 */
window.reedcrm.quickevent = {};

/**
 * QuickEvent init
 *
 * @memberof ReedCRM_QuickEvent
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @returns {void}
 */
window.reedcrm.quickevent.init = function() {
  window.reedcrm.quickevent.event();
};

/**
 * QuickEvent event
 *
 * @memberof ReedCRM_QuickEvent
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @returns {void}
 */
window.reedcrm.quickevent.event = function() {
  $(document).on('keyup', '#label', window.reedcrm.quickevent.labelKeyUp);
};

/**
 * QuickEvent labelkeyup
 *
 * @memberof ReedCRM_QuickEvent
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @returns {void}
 */
window.reedcrm.quickevent.labelKeyUp = function() {
  if ($("#label").val().length >= (parseInt($("#label").attr("maxlength")) * 0.7)) {
    $(".quickevent-label-warning-notice").removeClass("hidden");
  } else {
    $(".quickevent-label-warning-notice").addClass("hidden");
  }
};
