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
 * \file    js/quickcreation.js
 * \ingroup reedcrm
 * \brief   JavaScript quickcreation file for module ReedCRM
 */

'use strict';

/**
 * Init quickcreation JS
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @type {Object}
 */
window.reedcrm.quickcreation = {};

/**
 * Init latitude GPS
 *
 * @since   1.3.0
 * @version 22.0.0
 */
window.reedcrm.quickcreation.latitude = null;

/**
 * Init longitude GPS
 *
 * @since   1.3.0
 * @version 22.0.0
 */
window.reedcrm.quickcreation.longitude = null;

/**
 * QuickCreation init
 *
 * @since   1.3.0
 * @version 22.0.0
 *
 * @returns {void}
 */
window.reedcrm.quickcreation.init = function() {
  window.reedcrm.quickcreation.event();
};

/**
 * QuickCreation event
 *
 * @since   1.3.0
 * @version 22.0.0
 *
 * @returns {void}
 */
window.reedcrm.quickcreation.event = function() {
  // Upload image and display on canvas with signature pad
  // Image manipulation (uploadImage, drawOnImage, rotation, undo, erase) features from saturne/js/modules/media.js
  $(document).on('change', '#upload-image', window.saturne.media.uploadImage);
  $(document).on('click', '.image-validate', window.reedcrm.quickcreation.createImg);

  // Get current GPS position of navigator user
  window.reedcrm.quickcreation.getCurrentPosition();

  // Vibrate phone on submit form
  $(document).on('submit', '.quickcreation-form', window.reedcrm.quickcreation.vibratePhone);

  // Show opp percent value on range input
  $(document).on('input', '#opp_percent', window.reedcrm.quickcreation.showOppPercentValue);
};

/**
 * create img action
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.reedcrm.quickcreation.createImg = function() {
  let canvas = $(this).closest('.wpeo-modal').find('canvas')[0];
  let img    = canvas.toDataURL('image/jpeg');

  let token          = window.saturne.toolbox.getToken();
  let querySeparator = window.saturne.toolbox.getQuerySeparator(document.URL);

  let url = document.URL + querySeparator + 'action=add_img&token=' + token;
  $.ajax({
    url: url,
    type: 'POST',
    processData: false,
    contentType: 'application/octet-stream',
    data: JSON.stringify({
      img: img,
    }),
    success: function(resp) {
      $('.wpeo-modal').removeClass('modal-active');
      $('#id-container .linked-medias-list').replaceWith($(resp).find('#id-container .linked-medias-list'));
    },
    error: function () {}
  });
};

/**
 * Get current GPS position of navigator user
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.reedcrm.quickcreation.getCurrentPosition = function() {
  // Check if geolocation is supported by the browser
  if (navigator.geolocation) {
    // Get the current position
    navigator.geolocation.getCurrentPosition(
      // Success callback function
      function (position) {
        // Access the latitude and longitude from the position object
        window.reedcrm.quickcreation.latitude  = position.coords.latitude;
        window.reedcrm.quickcreation.longitude = position.coords.longitude;
        $('#id-container #latitude').val(window.reedcrm.quickcreation.latitude);
        $('#id-container #longitude').val(window.reedcrm.quickcreation.longitude);
      },
      // Error callback function
      function (error) {
        // Handle errors
        switch (error.code) {
          case error.PERMISSION_DENIED:
            $('#id-container #geolocation-error').val('User denied the request for geolocation.');
            break;
          case error.POSITION_UNAVAILABLE:
            $('#id-container #geolocation-error').val('Location information is unavailable.');
            break;
          case error.TIMEOUT:
            $('#id-container #geolocation-error').val('The request to get user location timed out.');
            break;
          case error.UNKNOWN_ERROR:
            $('#id-container #geolocation-error').val('An unknown error occurred.');
            break;
        }
      }
    );
  } else {
    $('#id-container #geolocation-error').val('Geolocation is not supported by this browser.');
  }
};

/**
 * Do vibrate phone after submit quick creation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.reedcrm.quickcreation.vibratePhone = function() {
  if ('vibrate' in navigator) {
    // Trigger a vibration in the form of a pattern
    // Vibrate for 1 second, pause for 0.5 seconds,
    // Vibrate for 0.2 seconds, pause for 0.2 seconds,
    // Vibrate for 0.5 seconds, pause for 1 second
    navigator.vibrate([1000, 500, 200, 200, 500, 1000]);
  }
  window.saturne.loader.display($('.page-footer button'));
};

/**
 * Show opp percent value on range input
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.reedcrm.quickcreation.showOppPercentValue = function() {
  $('.opp_percent-value').text($('#opp_percent').val() + ' %');
};
