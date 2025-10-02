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
 * \file    js/reedcrm.js
 * \ingroup reedcrm
 * \brief   JavaScript address file for module ReedCRM.
 */

/**
 * Init address JS.
 *
 * @memberof ReedCRM_Task
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @type {Object}
 */
window.reedcrm.address = {};

/**
 * Task init.
 *
 * @memberof ReedCRM_Task
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @returns {void}
 */
window.reedcrm.address.init = function() {
  window.reedcrm.address.event();
};

/**
 * Task event.
 *
 * @memberof ReedCRM_Task
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @returns {void}
 */
window.reedcrm.address.event = function() {
  $(document).on('click', '[name="favorite_address"]', window.reedcrm.address.toggleAddressFavorite);
};


/**
 * Toggle favorite address.
 *
 * @memberof ReedCRM_Address
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @return {void}
 */
window.reedcrm.address.toggleAddressFavorite = function() {
  let contactID = $(this).attr('value');
  let addressContainer = $(this);
  let token = window.saturne.toolbox.getToken();
  let querySeparator = '?';

  document.URL.match(/\?/) ? querySeparator = '&' : 1;

  $.ajax({
    url: document.URL + querySeparator + 'action=toggle_favorite&contact_id=' + contactID + '&token=' + token,
    type: "POST",
    processData: false,
    contentType: false,
    success: function() {
      let elements = $(".fas.fa-star");

      if (addressContainer.hasClass('far')) {
        addressContainer.removeClass('far');
        addressContainer.addClass('fas');
      }
      elements.removeClass('fas').addClass('far');
    },
    error: function() {}
  });
};
