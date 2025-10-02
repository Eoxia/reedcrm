/* Copyright (C) 2022-2025 EVARISK <technique@evarisk.com>
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
 * \file    js/reedcrm.js
 * \ingroup reedcrm
 * \brief   JavaScript file for module ReedCRM.
 */

'use strict';

if (!window.reedcrm) {
  /**
   * Init ReedCRM JS.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @type {Object}
   */
  window.reedcrm = {};

  /**
   * Init scriptsLoaded ReedCRM.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @type {Boolean}
   */
  window.reedcrm.scriptsLoaded = false;
}

if (!window.reedcrm.scriptsLoaded) {
  /**
   * ReedCRM init.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @returns {void}
   */
  window.reedcrm.init = function() {
    window.reedcrm.load_list_script();
  };

  /**
   * Load all modules' init.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @returns {void}
   */
  window.reedcrm.load_list_script = function() {
    if (!window.reedcrm.scriptsLoaded) {
      let key = undefined, slug = undefined;
      for (key in window.reedcrm) {
        if (window.reedcrm[key].init) {
          window.reedcrm[key].init();
        }
        for (slug in window.reedcrm[key]) {
          if (window.reedcrm[key] && window.reedcrm[key][slug] && window.reedcrm[key][slug].init) {
            window.reedcrm[key][slug].init();
          }
        }
      }
      window.reedcrm.scriptsLoaded = true;
    }
  };

  /**
   * Refresh and reload all modules' init.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @returns {void}
   */
  window.reedcrm.refresh = function() {
    let key = undefined;
    let slug = undefined;
    for (key in window.reedcrm) {
      if (window.reedcrm[key].refresh) {
        window.reedcrm[key].refresh();
      }
      for (slug in window.reedcrm[key]) {
        if (window.reedcrm[key] && window.reedcrm[key][slug] && window.reedcrm[key][slug].refresh) {
          window.reedcrm[key][slug].refresh();
        }
      }
    }
  };
  $(document).ready(window.reedcrm.init);
}
