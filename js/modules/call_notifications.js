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
 * \file    js/modules/call_notifications.js
 * \ingroup reedcrm
 * \brief   JavaScript call notifications file for module ReedCRM - Uses native Dolibarr jnotify
 */

// Create namespace if not exists
if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * Init call_notifications JS
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.callnotifications = {};

/**
 * Call notifications init
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.callnotifications.init = function() {
  console.log('ReedCRM Call Notifications initialized');
  window.reedcrm.callnotifications.config();
  window.reedcrm.callnotifications.start();
};

/**
 * Configure call notifications
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.callnotifications.config = function() {
  // Get config from data attributes or defaults
  var configEl = document.getElementById('reedcrm-call-config');

  if (configEl) {
    window.reedcrm.callnotifications.frequency = parseInt(configEl.dataset.frequency) || 60;
    window.reedcrm.callnotifications.autoOpen = parseInt(configEl.dataset.autoOpen) || 0;
    window.reedcrm.callnotifications.openNewTab = parseInt(configEl.dataset.openNewTab) || 1;
    window.reedcrm.callnotifications.checkUrl = configEl.dataset.checkUrl || '';

    // Get translations
    window.reedcrm.callnotifications.trans = {
      incomingCall: configEl.dataset.transIncomingCall || 'Incoming Call',
      from: configEl.dataset.transFrom || 'From',
      phone: configEl.dataset.transPhone || 'Phone',
      email: configEl.dataset.transEmail || 'Email',
      viewContact: configEl.dataset.transViewContact || 'View Contact'
    };
  } else {
    // Fallback defaults
    window.reedcrm.callnotifications.frequency = 60;
    window.reedcrm.callnotifications.autoOpen = 0;
    window.reedcrm.callnotifications.openNewTab = 1;
    window.reedcrm.callnotifications.checkUrl = '';
    window.reedcrm.callnotifications.trans = {
      incomingCall: 'Incoming Call',
      from: 'From',
      phone: 'Phone',
      email: 'Email',
      viewContact: 'View Contact'
    };
  }

  window.reedcrm.callnotifications.enabled = true;
  window.reedcrm.callnotifications.interval = null;
};

/**
 * Start checking for call events
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.callnotifications.start = function() {
  if (!window.reedcrm.callnotifications.checkUrl) {
    console.error('ReedCRM: No check URL configured for call notifications');
    return;
  }

  // Start checking after a small delay
  setTimeout(function() {
    console.log('Starting ReedCRM call check with frequency: ' + window.reedcrm.callnotifications.frequency + 's');
    window.reedcrm.callnotifications.check();
    window.reedcrm.callnotifications.interval = setInterval(
      window.reedcrm.callnotifications.check,
      window.reedcrm.callnotifications.frequency * 1000
    );
  }, 3000);
};

/**
 * Check for new call events
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.callnotifications.check = function() {
  if (!window.reedcrm.callnotifications.enabled) return;

  jQuery.ajax({
    url: window.reedcrm.callnotifications.checkUrl,
    type: 'GET',
    dataType: 'json',
    success: function(data) {
      if (data && data.length > 0) {
        console.log('Found ' + data.length + ' new call events');
        jQuery.each(data, function(index, event) {
          window.reedcrm.callnotifications.show(event);
        });
      }
    },
    error: function(xhr, status, error) {
      console.error('Error checking call events:', error);
    }
  });
};

/**
 * Show call notification using native jnotify
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   {Object} event Event data
 * @returns {void}
 */
window.reedcrm.callnotifications.show = function(event) {
  console.log('Showing call notification:', event);

  var trans = window.reedcrm.callnotifications.trans;
  var body = '<strong>' + trans.incomingCall + '</strong> : ' + event.contact_name;

  if (event.caller) {
    body += '<br>' + trans.from + ': ' + event.caller;
  }
  if (event.contact_phone) {
    body += '<br>' + trans.phone + ': ' + event.contact_phone;
  }
  if (event.contact_email) {
    body += '<br>' + trans.email + ': ' + event.contact_email;
  }

  body += '<br><br><button type="button" class="button" onclick="window.open(\'' + event.url + '\', \'_blank\')">';
  body += trans.viewContact + '</button>';

  // Use native Dolibarr jnotify
  jQuery.jnotify(body, 'success', true, {
    sticky: true,
    timeout: 30000
  });

  // Auto-open if configured
  if (window.reedcrm.callnotifications.autoOpen) {
    setTimeout(function() {
      var target = window.reedcrm.callnotifications.openNewTab ? '_blank' : '_self';
      window.open(event.url, target);
    }, 1000);
  }
};

/**
 * Enable call notifications
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.callnotifications.enable = function() {
  window.reedcrm.callnotifications.enabled = true;
  window.reedcrm.callnotifications.check();
  window.reedcrm.callnotifications.interval = setInterval(
    window.reedcrm.callnotifications.check,
    window.reedcrm.callnotifications.frequency * 1000
  );
  console.log('ReedCRM call notifications enabled');
};

/**
 * Disable call notifications
 *
 * @memberof ReedCRM_CallNotifications
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.callnotifications.disable = function() {
  window.reedcrm.callnotifications.enabled = false;
  if (window.reedcrm.callnotifications.interval) {
    clearInterval(window.reedcrm.callnotifications.interval);
  }
  console.log('ReedCRM call notifications disabled');
};

// Auto-initialize on document ready
jQuery(document).ready(function() {
  window.reedcrm.callnotifications.init();
});

