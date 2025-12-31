/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    js/modules/eventpro.js
 * \ingroup reedcrm
 * \brief   JavaScript eventpro modal file for module ReedCRM
 */

// Create namespace if not exists
if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * Init eventpro JS
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.eventpro = {};

/**
 * Eventpro modal ID
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {String}
 */
window.reedcrm.eventpro.modalId = 'eventproCardModal';

/**
 * Track if refresh was already triggered to avoid double refresh
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Boolean}
 */
window.reedcrm.eventpro.refreshTriggered = false;

/**
 * Eventpro init
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventpro.init = function() {
  window.reedcrm.eventpro.event();
  window.reedcrm.eventpro.modalCloseWatcher();
};

/**
 * Inject CSS into iframe to show only #addeventform
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param {HTMLElement} iframe The iframe element
 * @returns {void}
 */
window.reedcrm.eventpro.injectIframeCSS = function(iframe) {
  try {
    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    var addeventform = iframeDoc.getElementById('addeventform');
    if (!addeventform) {
      console.log('addeventform not found');
      return;
    }

    // Hide all body children first
    Array.from(iframeDoc.body.children).forEach(function(child) {
      child.style.display = 'none';
    });

    // Move addeventform directly to body
    iframeDoc.body.appendChild(addeventform);

    // Remove any elements after the form
    var nextSibling = addeventform.nextSibling;
    while (nextSibling) {
      var toRemove = nextSibling;
      nextSibling = nextSibling.nextSibling;
      iframeDoc.body.removeChild(toRemove);
    }

    // Add classes for styling (CSS is loaded via reedcrm.min.css in procard.php)
    iframeDoc.documentElement.classList.add('reedcrm-modal-iframe-html');
    iframeDoc.body.classList.add('reedcrm-modal-iframe-body');
    addeventform.classList.add('reedcrm-modal-iframe-form');

    // Show iframe once classes are added and hide loader
    $(iframe).css('opacity', '1');
    if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
      window.saturne.loader.remove($('#' + window.reedcrm.eventpro.modalId + '-loader'));
    } else {
      $('#' + window.reedcrm.eventpro.modalId + '-loader').hide();
    }
  } catch (e) {
    // Cross-origin or other error, ignore
    console.log('Could not inject CSS into iframe:', e);
  }
};

/**
 * Refresh the project row
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param {String|Number} projectId The project ID
 * @returns {void}
 */
window.reedcrm.eventpro.refreshProjectRow = function(projectId) {
  if (!projectId) return;

  var baseUrl = window.location.href.split('&action=')[0].split('#')[0];
  var curTr = $('tr[data-rowid="' + projectId + '"]');

  if (!curTr.length) return;

  // Add a loading indicator
  curTr.css('opacity', '0.5');

  $.ajax({
    url: baseUrl,
    type: 'GET',
    success: function(resp) {
      var $resp = $(resp);
      var newTr = $resp.find('tr[data-rowid="' + projectId + '"]');
      if (newTr.length && curTr.length) {
        // Fade out, replace content, fade in
        curTr.fadeOut(200, function() {
          curTr.html(newTr.html());
          curTr.css('opacity', '1');
          curTr.fadeIn(200);
        });
      } else {
        curTr.css('opacity', '1');
      }
    },
    error: function() {
      curTr.css('opacity', '1');
    }
  });
};

/**
 * Handle modal close watcher - don't refresh on close, only on form submission
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventpro.modalCloseWatcher = function() {
  var previousModalState = false;
  setInterval(function() {
    var isModalActive = $('#' + window.reedcrm.eventpro.modalId).hasClass('modal-active');
    // If modal was closed, reset refresh flag for next time
    if (previousModalState && !isModalActive) {
      window.reedcrm.eventpro.refreshTriggered = false;
    }
    previousModalState = isModalActive;
  }, 200);
};

/**
 * Eventpro events
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.eventpro.event = function() {
  // Intercept clicks on reedcrm modal buttons to track initial URL
  $(document).on('click', '.reedcrm-modal-open', function(e) {
    var $button = $(this);
    var modalUrl = $button.attr('data-modal-url');
    var projectId = $button.attr('data-project-id');

    if (modalUrl) {
      var $iframe = $('#' + window.reedcrm.eventpro.modalId + '-iframe');
      // Store initial URL and project ID on the iframe element
      $iframe.data('initial-url', modalUrl);
      $iframe.data('project-id', projectId);
      $iframe.data('loaded-once', false);

      // Reset form submission flag
      $iframe.data('form-submitted', false);

      // Inject CSS when iframe loads
      $iframe.off('load.reedcrm').on('load.reedcrm', function() {
        window.reedcrm.eventpro.injectIframeCSS(this);
        
        // Listen for form submissions in the iframe
        try {
          var iframeDoc = this.contentDocument || this.contentWindow.document;
          var forms = iframeDoc.querySelectorAll('form');
          
          forms.forEach(function(form) {
            form.addEventListener('submit', function() {
              $iframe.data('form-submitted', true);
            }, true);
          });
          
          // Listen for tab clicks to show loader
          var tabLinks = iframeDoc.querySelectorAll('a[href*="tab="]');
          var iframeElement = this;
          tabLinks.forEach(function(tabLink) {
            tabLink.addEventListener('click', function(e) {
              // Show loader when tab is clicked
              var $loader = $('#' + window.reedcrm.eventpro.modalId + '-loader');
              $loader.show().addClass('wpeo-loader');
              if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
                window.saturne.loader.display($loader);
              }
              // Hide iframe during loading
              $(iframeElement).css('opacity', '0');
            });
          });
        } catch (e) {
          // Cross-origin error, ignore
        }
      });

      // Detect when iframe reloads after form submission
      $iframe.off('load.reedcrm-submit').on('load.reedcrm-submit', function() {
        try {
          var wasLoadedBefore = $iframe.data('loaded-once');
          var formSubmitted = $iframe.data('form-submitted');
          
          if (wasLoadedBefore && formSubmitted && !window.reedcrm.eventpro.refreshTriggered) {
            // Form was submitted and iframe reloaded, close modal and refresh
            window.reedcrm.eventpro.refreshTriggered = true;
            var projectIdToRefresh = $iframe.data('project-id');
            setTimeout(function() {
              $('#' + window.reedcrm.eventpro.modalId).removeClass('modal-active');
              window.reedcrm.eventpro.refreshProjectRow(projectIdToRefresh);
              $iframe.data('form-submitted', false); // Reset for next time
            }, 300);
          }
          $iframe.data('loaded-once', true);
        } catch (e) {
          // Ignore any errors (including extension errors)
        }
      });

      // Store project ID for refresh after modal close
      $('#' + window.reedcrm.eventpro.modalId).attr('data-project-id', projectId);

      // Reset iframe opacity
      $iframe.css('opacity', '0');

      // Show loader
      var $loader = $('#' + window.reedcrm.eventpro.modalId + '-loader');
      $loader.show().addClass('wpeo-loader');
      if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
        window.saturne.loader.display($loader);
      }

      // Set iframe src after setting up handlers
      $iframe.attr('src', modalUrl);

      // Open modal using Saturne's modal system
      $('#' + window.reedcrm.eventpro.modalId).addClass('modal-active');
    }
  });
};

