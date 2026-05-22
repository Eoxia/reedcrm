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
 * \file    js/modules/projectPresets.js
 * \ingroup reedcrm
 * \brief   Save/delete per-user saved views in the project list presets bar
 */

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.projectPresets = {};

/**
 * Init
 *
 * @returns {void}
 */
window.reedcrm.projectPresets.init = function () {
  window.reedcrm.projectPresets.event();
};

/**
 * Bind events
 *
 * @returns {void}
 */
window.reedcrm.projectPresets.event = function () {
  $(document).on('click', '.reedcrm-save-view', window.reedcrm.projectPresets.saveView);
  $(document).on('click', '.saturne-list-preset-remove', window.reedcrm.projectPresets.deleteView);
};

/**
 * Endpoint URL
 *
 * @returns {String} The AJAX endpoint URL
 */
window.reedcrm.projectPresets.url = function () {
  var root = (window.saturne && window.saturne.config && window.saturne.config.urlRoot) ? window.saturne.config.urlRoot : '';
  return root + '/custom/reedcrm/ajax/save_project_view.php';
};

/**
 * Save the current filters as a named view
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.projectPresets.saveView = function (e) {
  e.preventDefault();

  var label = window.prompt('Nom de la vue ?');
  if (!label) {
    return;
  }

  var current = new URLSearchParams(window.location.search);
  var keep    = new URLSearchParams();
  current.forEach(function (value, key) {
    if (key.indexOf('search_') === 0 || key === 'search_preset') {
      keep.append(key, value);
    }
  });

  $.ajax({
    url: window.reedcrm.projectPresets.url(),
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'save',
      token: window.saturne.toolbox.getToken(),
      label: label,
      query: keep.toString()
    },
    success: function (response) {
      if (response && response.success) {
        window.location.reload();
      } else {
        window.alert((response && response.error) || 'Erreur');
      }
    },
    error: function () {
      window.alert('Erreur');
    }
  });
};

/**
 * Delete a saved view
 *
 * @param  {Event} e Click event
 * @returns {void}
 */
window.reedcrm.projectPresets.deleteView = function (e) {
  e.preventDefault();
  e.stopPropagation();

  var key = $(this).data('remove-key');
  if (!key) {
    return;
  }
  if (!window.confirm('Supprimer cette vue ?')) {
    return;
  }

  $.ajax({
    url: window.reedcrm.projectPresets.url(),
    method: 'POST',
    dataType: 'json',
    data: {
      action: 'delete',
      token: window.saturne.toolbox.getToken(),
      key: key
    },
    success: function (response) {
      if (response && response.success) {
        window.location.reload();
      } else {
        window.alert((response && response.error) || 'Erreur');
      }
    },
    error: function () {
      window.alert('Erreur');
    }
  });
};
