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
  window.reedcrm.quickcreation.setAddressBlockState('searching', 'Détection de la position en cours...');
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

  // Show the dedicated contact fields when the contact is not the third party one
  $(document).on('change', '.reedcrm-same-contact', window.reedcrm.quickcreation.toggleSameContactFields);

  // Get current GPS position of navigator user
  window.reedcrm.quickcreation.getCurrentPosition();

  // Vibrate phone on submit form
  $(document).on('submit', '.quickcreation-form', window.reedcrm.quickcreation.vibratePhone);

  // Show opp percent value on range input
  $(document).on('input', '#opp_percent', window.reedcrm.quickcreation.showOppPercentValue);

  // Remind me later
  $(document).on('change', '#add_reminder', window.reedcrm.quickcreation.toggleReminder);
  $(document).on('click', '.quickcreation-reminder__preset', window.reedcrm.quickcreation.applyReminderPreset);
  $(document).on('input', '#reminder_date', window.reedcrm.quickcreation.clearReminderPreset);

  // Photo read by the AI (wand of the media block)
  $(document).on('saturne:media-analysed', window.reedcrm.quickcreation.fillFromPhotoAnalysis);
};

/**
 * Fill the form with what the AI read on a photo: the contact details it found, and its analysis
 * appended to the description
 *
 * @since   23.3.1
 * @version 23.3.1
 *
 * @param  {Event}  event    saturne:media-analysed event
 * @param  {Object} response Answer of the readFileAI action
 * @return {void}
 */
window.reedcrm.quickcreation.fillFromPhotoAnalysis = function(event, response) {
  if (response.type === 'contact' && response.contact) {
    var contactFields = {
      reedcrm_lastname : response.contact.nom,
      reedcrm_firstname: response.contact.prenom,
      projectphone     : response.contact.telephone,
      reedcrm_email    : response.contact.email
    };

    // A detail the AI did not find leaves what the user typed alone
    $.each(contactFields, function(fieldId, value) {
      value = String(value || '').trim();
      if (value !== '') {
        $('#' + fieldId).val(value);
      }
    });
  }

  var description = $('textarea#description');
  if (description.length && response.text) {
    var currentDescription = description.val();
    description.val(currentDescription !== '' ? currentDescription + '\n' + response.text : response.text);
  }
};

/**
 * Show or hide the reminder fields: a reminder needs a date, the first preset is picked by default
 *
 * @since   23.3.1
 * @version 23.3.1
 *
 * @return {void}
 */
window.reedcrm.quickcreation.toggleReminder = function() {
  var checked = $(this).is(':checked');
  var $date   = $('#reminder_date');

  $('.quickcreation-reminder__fields').toggleClass('is-open', checked);
  $date.prop('required', checked).attr('min', window.reedcrm.quickcreation.toDateTimeLocal(new Date()));
  if (checked && !$date.val()) {
    $('.quickcreation-reminder__preset').first().trigger('click');
  }
};

/**
 * Fill the reminder date from a preset (in N days or N months, at 9:00)
 *
 * @since   23.3.1
 * @version 23.3.1
 *
 * @return {void}
 */
window.reedcrm.quickcreation.applyReminderPreset = function() {
  var $preset = $(this);
  var date    = new Date();

  date.setDate(date.getDate() + (parseInt($preset.data('days')) || 0));
  date.setMonth(date.getMonth() + (parseInt($preset.data('months')) || 0));
  date.setHours(9, 0, 0, 0);

  $('#reminder_date').val(window.reedcrm.quickcreation.toDateTimeLocal(date));
  $('.quickcreation-reminder__preset').removeClass('is-active');
  $preset.addClass('is-active');
};

/**
 * A date typed by hand no longer matches any preset
 *
 * @since   23.3.1
 * @version 23.3.1
 *
 * @return {void}
 */
window.reedcrm.quickcreation.clearReminderPreset = function() {
  $('.quickcreation-reminder__preset').removeClass('is-active');
};

/**
 * Format a date as a datetime-local value (Y-m-d\TH:i), in the device timezone
 *
 * @since   23.3.1
 * @version 23.3.1
 *
 * @param  {Date}   date Date to format
 * @return {string}      Formatted date
 */
window.reedcrm.quickcreation.toDateTimeLocal = function(date) {
  var pad = function(value) {
    return (value < 10 ? '0' : '') + value;
  };

  return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
};


/**
 * Toggle the fields of a dedicated contact (address or project) on checkbox change
 *
 * @since   22.0.0
 * @version 22.0.0
 *
 * @return {void}
 */
window.reedcrm.quickcreation.toggleSameContactFields = function() {
  $('.' + $(this).attr('data-target')).toggle(!$(this).is(':checked'));
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

  // Safety fallback: if browser silently ignores permission (no popup shown),
  // the native timeout may never fire. Force error state after 12s.
  var _geolocResolved = false;
  var _safetyTimer = setTimeout(function() {
    if (!_geolocResolved) {
      _geolocResolved = true;
      $('#id-container #geolocation-error').val('Timeout');
      window.reedcrm.quickcreation.setAddressBlockState('error', 'Délai dépassé. Autorisez la localisation dans votre navigateur.');
    }
  }, 12000);

  navigator.geolocation.getCurrentPosition(
    function (position) {
      _geolocResolved = true;
      clearTimeout(_safetyTimer);
      $('#id-container #geolocation-error').val('');
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
      if (_geolocResolved) return; // safety timer already fired
      _geolocResolved = true;
      clearTimeout(_safetyTimer);
      var messages = {
        1: 'Accès à la position refusé.',
        2: 'Position indisponible.',
        3: 'Délai de géolocalisation dépassé.'
      };
      $('#id-container #geolocation-error').val(error.message || '');
      window.reedcrm.quickcreation.setAddressBlockState('error', messages[error.code] || 'Erreur de géolocalisation.');
    },
    { timeout: 10000, maximumAge: 300000 }
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
  $('#current-address-block').attr('href', 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lon).attr('target', '_blank');

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
  var $ko   = $('#current-address-ko');

  $icon.removeClass('fa-circle-notch fa-spin fa-map-marker-alt fa-exclamation-triangle');
  $ko.hide();

  var $block = $('#current-address-block');

  if (state === 'success') {
    $icon.addClass('fa-map-marker-alt').css('color', '#2ecc71');
    $text.css('color', '#34495e');
    $block.addClass('is-visible');
  } else if (state === 'error') {
    $icon.addClass('fa-map-marker-alt').css('color', '#e74c3c');
    $ko.show();
    $text.css('color', '#e74c3c');
    $block.addClass('is-visible');
  } else {
    $icon.addClass('fa-circle-notch fa-spin').css('color', '#3498db');
    $text.css('color', '#94a3b8');
    $block.removeClass('is-visible');
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

// Initialisation assurée par reedcrm.load_list_script() dans reedcrm.min.js
// qui itère window.reedcrm et appelle .init() sur chaque module au document.ready.
