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

/**
 * \file    js/quickcreation.js
 * \ingroup easycrm
 * \brief   JavaScript quickcreation file for module EasyCRM
 */

/**
 * Init quickcreation JS
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @type {Object}
 */
window.easycrm.quickcreation = {};

/**
 * Init rotation value of img on canvas
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 */
window.easycrm.quickcreation.rotation = 0;

/**
 * Init img in canvas
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 */
window.easycrm.quickcreation.img;

/**
 * Init latitude GPS
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 */
window.easycrm.quickcreation.latitude;

/**
 * Init longitude GPS
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 */
window.easycrm.quickcreation.longitude;

/**
 * QuickCreation init
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @returns {void}
 */
window.easycrm.quickcreation.init = function() {
  window.easycrm.quickcreation.event();
};

/**
 * QuickCreation event
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @returns {void}
 */
window.easycrm.quickcreation.event = function() {
  $(document).on('change', '#upload-image', window.easycrm.quickcreation.uploadImage);
  $(document).on('click', '.image-rotate-left', function() { window.easycrm.quickcreation.rotateImage(-90); });
  $(document).on('click', '.image-rotate-right', function() { window.easycrm.quickcreation.rotateImage(90); });
  $(document).on('click', '.image-undo', window.easycrm.quickcreation.undoLastDraw);
  $(document).on('click', '.image-erase', window.easycrm.quickcreation.clearCanvas);
  $(document).on('click', '.image-validate', window.easycrm.quickcreation.createImg);
  window.easycrm.quickcreation.getCurrentPosition();
  $(document).on('submit', '.quickcreation-form', window.easycrm.quickcreation.vibratePhone);
  $(document).on('input', '#opp_percent', window.easycrm.quickcreation.showOppPercentValue);
};

window.easycrm.quickcreation.uploadImage = function() {
  if (this.files && this.files[0]) {
    var reader = new FileReader();

    reader.onload = function(event) {
      $(document).find('.modal-upload-image').addClass('modal-active');
      window.easycrm.quickcreation.drawImageOnCanvas(event);
    };

    reader.readAsDataURL(this.files[0]);
  }
};

/**
 * Rotate image action
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @returns {void}
 */
window.easycrm.quickcreation.rotateImage = function(degrees) {
  window.easycrm.quickcreation.rotation += degrees;
  $('#canvas').css('transform', 'rotate(' + window.easycrm.quickcreation.rotation + 'deg)');
};

/**
 * Undo last drawing action
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.undoLastDraw = function() {
  let canvas = $(this).closest('.modal-upload-image').find('canvas');
  var data   = canvas[0].signaturePad.toData();
  if (data) {
    data.pop(); // remove the last dot or line
    canvas[0].signaturePad.fromData(data);
    // Redraw the image on the canvas
    window.easycrm.quickcreation.drawImageOnCanvas(window.easycrm.quickcreation.img);
  }
};

/**
 * Clear canvas action
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.clearCanvas = function() {
  let canvas = $(this).closest('.modal-upload-image').find('canvas');
  canvas[0].signaturePad.clear();
  window.easycrm.quickcreation.drawImageOnCanvas(window.easycrm.quickcreation.img);
};

/**
 * Draw img on canvas action
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.drawImageOnCanvas = function(event) {
  window.easycrm.quickcreation.canvas = document.querySelector('#modal-upload-image0 canvas');

  window.easycrm.quickcreation.canvas.signaturePad = new SignaturePad(window.easycrm.quickcreation.canvas, {
    penColor: 'rgb(255, 0, 0)'
  });

  window.easycrm.quickcreation.canvas.signaturePad.clear();

  // Draw the image on the canvas
  var img = new Image();
  img.src = event.target.result;
  window.easycrm.quickcreation.img = event;

  img.onload = function() {
    // let ratio = Math.max(window.devicePixelRatio || 1, 1);
    // window.easycrm.quickcreation.canvas.width  = window.easycrm.quickcreation.canvas.offsetWidth * ratio;
    // window.easycrm.quickcreation.canvas.height = window.easycrm.quickcreation.canvas.offsetHeight * ratio;
    //let context = window.easycrm.quickcreation.canvas.getContext('2d').scale(ratio, ratio);
    let context = window.easycrm.quickcreation.canvas.getContext('2d');
    window.easycrm.quickcreation.canvas.width  = 300;
    window.easycrm.quickcreation.canvas.height = 400;
    context.drawImage(img, 0, 0, window.easycrm.quickcreation.canvas.width, window.easycrm.quickcreation.canvas.height);
  };

  window.easycrm.quickcreation.rotation = 0; // Reset rotation when a new image is selected
};

/**
 * create img action
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.createImg = function() {
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
      $('.project-container .linked-medias-list').replaceWith($(resp).find('.project-container .linked-medias-list'));
    },
    error: function () {}
  });
};

/**
 * Get current GPS position of navigator user
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.getCurrentPosition = function() {
  // Check if geolocation is supported by the browser
  if (navigator.geolocation) {
    // Get the current position
    navigator.geolocation.getCurrentPosition(
      // Success callback function
      function (position) {
        // Access the latitude and longitude from the position object
        window.easycrm.quickcreation.latitude  = position.coords.latitude;
        window.easycrm.quickcreation.longitude = position.coords.longitude;
        $('.project-container #latitude').val(window.easycrm.quickcreation.latitude);
        $('.project-container #longitude').val(window.easycrm.quickcreation.longitude);
      },
      // Error callback function
      function (error) {
        // Handle errors
        switch (error.code) {
          case error.PERMISSION_DENIED:
            $('.project-container #geolocation-error').val('User denied the request for geolocation.');
            break;
          case error.POSITION_UNAVAILABLE:
            $('.project-container #geolocation-error').val('Location information is unavailable.');
            break;
          case error.TIMEOUT:
            $('.project-container #geolocation-error').val('The request to get user location timed out.');
            break;
          case error.UNKNOWN_ERROR:
            $('.project-container #geolocation-error').val('An unknown error occurred.');
            break;
        }
      }
    );
  } else {
    $('.project-container #geolocation-error').val('Geolocation is not supported by this browser.');
  }
};

/**
 * Do vibrate phone after submit quick creation
 *
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.vibratePhone = function() {
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
 * @memberof EasyCRM_QuickCreation
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return {void}
 */
window.easycrm.quickcreation.showOppPercentValue = function() {
  $('.opp_percent-value').text($('#opp_percent').val() + ' %');
};
