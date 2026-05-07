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
  if (typeof window.saturne !== 'undefined' && window.saturne.media && typeof window.saturne.media.uploadImage === 'function') {
      $(document).on('change', '#upload-image', window.saturne.media.uploadImage);
  }
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
  if (!navigator.geolocation) {
    $('#id-container #geolocation-error').val('Geolocation is not supported by this browser.');
    window.reedcrm.quickcreation.setAddressBlockState('error', 'Géolocalisation non supportée.');
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function (position) {
      window.reedcrm.quickcreation.latitude  = position.coords.latitude;
      window.reedcrm.quickcreation.longitude = position.coords.longitude;
      $('#id-container #latitude').val(window.reedcrm.quickcreation.latitude);
      $('#id-container #longitude').val(window.reedcrm.quickcreation.longitude);
      window.reedcrm.quickcreation.resolveCurrentAddress(
        window.reedcrm.quickcreation.latitude,
        window.reedcrm.quickcreation.longitude
      );
    },
    function (error) {
      var messages = {
        1: 'User denied the request for geolocation.',
        2: 'Location information is unavailable.',
        3: 'The request to get user location timed out.'
      };
      $('#id-container #geolocation-error').val(messages[error.code] || 'An unknown error occurred.');
      window.reedcrm.quickcreation.setAddressBlockState('error', 'Accès à la position refusé.');
    }
  );
};

/**
 * Reverse geocode lat/lon via OSM Nominatim and display the resolved address
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param {number} lat Latitude
 * @param {number} lon Longitude
 *
 * @return {void}
 */
window.reedcrm.quickcreation.resolveCurrentAddress = function(lat, lon) {
  $('#current-address-coords').text(lat.toFixed(6) + ' / ' + lon.toFixed(6));

  $.getJSON(
    'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lon + '&format=json&addressdetails=1',
    function (data) {
      if (!data || !data.address) {
        window.reedcrm.quickcreation.setAddressBlockState('error', 'Adresse introuvable.');
        return;
      }

      var a        = data.address;
      var road     = a.road || a.pedestrian || a.footway || '';
      var house    = a.house_number || '';
      var postcode = a.postcode || '';
      var city     = a.city || a.town || a.village || '';
      var street   = house ? house + ' ' + road : road;
      var parts    = $.grep([street, postcode, city], function(v) { return v !== ''; });
      var label    = parts.length > 0 ? parts.join(', ') : data.display_name;

      window.reedcrm.quickcreation.setAddressBlockState('success', label);
    }
  ).fail(function() {
    window.reedcrm.quickcreation.setAddressBlockState('error', 'Impossible de récupérer l\'adresse.');
  });
};

/**
 * Update the address block visual state
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @param {string} state   'loading' | 'success' | 'error'
 * @param {string} message Text to display
 *
 * @return {void}
 */
window.reedcrm.quickcreation.setAddressBlockState = function(state, message) {
  var $icon = $('#current-address-icon');
  var $text = $('#current-address-text');

  $icon.removeClass('fa-circle-notch fa-spin fa-map-marker-alt fa-exclamation-triangle');

  var $block = $('#current-address-block');

  if (state === 'success') {
    $icon.addClass('fa-map-marker-alt').css('color', '#3498db');
    $text.css('color', '#34495e');
    $block.css({ background: '#f1f5f9', 'border-color': '#e2e8f0' });
  } else if (state === 'error') {
    $icon.addClass('fa-exclamation-triangle').css('color', '#e74c3c');
    $text.css('color', '#e74c3c');
    $block.css({ background: '#fef2f2', 'border-color': '#fecaca' });
  } else {
    $icon.addClass('fa-circle-notch fa-spin').css('color', '#3498db');
    $text.css('color', '#94a3b8');
    $block.css({ background: '#f1f5f9', 'border-color': '#e2e8f0' });
  }

  $text.text(message);
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
  
  var $btn = $('.btn-submit-purple');
  if ($btn.length === 0) {
      $btn = $('.page-footer button');
  }
  
  window.saturne.loader.display($btn);
  
  // Disable button after a tiny delay to ensure form still submits
  setTimeout(function() {
      $btn.prop('disabled', true);
  }, 10);
  
  // Re-enable after 5 seconds just in case the page didn't reload
  setTimeout(function() {
      $btn.prop('disabled', false);
      if (window.saturne && window.saturne.loader) {
          window.saturne.loader.remove($btn);
      }
  }, 5000);
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
  var val = $('#opp_percent').val();
  $('.opp_percent-value').text(val + '%');
  $('#opp_percent').parent().get(0).style.setProperty('--val', val);
};
