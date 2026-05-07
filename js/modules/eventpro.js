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
window.reedcrm.eventpro.init = function () {
  window.reedcrm.eventpro.event();
  window.reedcrm.eventpro.modalCloseWatcher();
  window.reedcrm.eventpro.initAddContact();
  window.reedcrm.eventpro.initRelaunchTooltips();
};

/**
 * Load content into modal via AJAX
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @param {String} url The URL to load
 * @returns {void}
 */
window.reedcrm.eventpro.loadModalContent = function (url) {
  var $content = $('#' + window.reedcrm.eventpro.modalId + '-content');
  var $loader = $('#' + window.reedcrm.eventpro.modalId + '-loader');

  $loader.show().addClass('wpeo-loader');
  if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
    window.saturne.loader.display($loader);
  }
  $content.hide().empty();

  var separator = url.indexOf('?') !== -1 ? '&' : '?';
  var ajaxUrl = url + separator + 'modal=1';

  $.ajax({
    url: ajaxUrl,
    type: 'GET',
    success: function (html) {
      $content.html(html);
      $content.show();

      if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
        window.saturne.loader.remove($loader);
      } else {
        $loader.hide();
      }

      window.reedcrm.eventpro.bindModalContentEvents();
    },
    error: function () {
      if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
        window.saturne.loader.remove($loader);
      } else {
        $loader.hide();
      }
      $content.html('<div class="error" style="padding: 20px;">Erreur lors du chargement</div>');
      $content.show();
    }
  });
};

/**
 * Bind events on AJAX-loaded modal content (form submit, tab clicks)
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.1.0
 * @version 1.1.0
 *
 * @returns {void}
 */
window.reedcrm.eventpro.bindModalContentEvents = function () {
  var $content = $('#' + window.reedcrm.eventpro.modalId + '-content');

  $content.find('form').off('submit.reedcrm').on('submit.reedcrm', function (e) {
    e.preventDefault();
    var $form = $(this);
    var formAction = $form.attr('action');
    var separator = formAction.indexOf('?') !== -1 ? '&' : '?';

    $.ajax({
      url: formAction + separator + 'modal=1',
      type: 'POST',
      data: $form.serialize(),
      dataType: 'json',
      success: function (response) {
        if (response && response.success) {
          var projectId = $('#' + window.reedcrm.eventpro.modalId).attr('data-project-id');
          $('#' + window.reedcrm.eventpro.modalId).removeClass('modal-active');
          $('#' + window.reedcrm.eventpro.modalId + '-content').empty();
          window.reedcrm.eventpro.refreshProjectRow(projectId);

          if (response.message && typeof window.saturne !== 'undefined' && window.saturne.notification) {
            window.saturne.notification.success(response.message);
          }
        } else {
          var errorMsg = (response && response.error) ? response.error : 'Erreur';
          if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
            window.saturne.notification.error(errorMsg);
          } else {
            alert(errorMsg);
          }
        }
      },
      error: function () {
        if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
          window.saturne.notification.error('Erreur lors de la soumission');
        } else {
          alert('Erreur lors de la soumission');
        }
      }
    });
  });

  $content.find('a[href*="tab="]').on('click', function (e) {
    e.preventDefault();
    var url = $(this).attr('href');
    window.reedcrm.eventpro.loadModalContent(url);
  });
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
window.reedcrm.eventpro.refreshProjectRow = function (projectId) {
  if (!projectId) {
    window.location.reload();
    return;
  }

  var baseUrl = window.location.href.split('&action=')[0].split('#')[0];
  var curTr = $('tr[data-rowid="' + projectId + '"]');

  if (!curTr.length) {
    window.location.reload();
    return;
  }

  curTr.css('opacity', '0.5');

  $.ajax({
    url: baseUrl,
    type: 'GET',
    success: function (resp) {
      var $resp = $(resp);
      var newTr = $resp.find('tr[data-rowid="' + projectId + '"]');
      if (newTr.length && curTr.length) {
        curTr.fadeOut(200, function () {
          curTr.html(newTr.html());
          curTr.css('opacity', '1');
          curTr.fadeIn(200);
        });
      } else {
        curTr.css('opacity', '1');
      }
    },
    error: function () {
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
window.reedcrm.eventpro.modalCloseWatcher = function () {
  var previousModalState = false;
  setInterval(function () {
    var isModalActive = $('#' + window.reedcrm.eventpro.modalId).hasClass('modal-active');
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
 * @version 1.1.0
 *
 * @returns {void}
 */
window.reedcrm.eventpro.event = function () {
  var modalSelector = '#' + window.reedcrm.eventpro.modalId;

  $(document).on('click', modalSelector + ' .modal-close, ' + modalSelector + ' .modal-close i', function (e) {
    e.preventDefault();
    var $modal = $(modalSelector);
    $modal.removeClass('modal-active');
    $modal.find('#' + window.reedcrm.eventpro.modalId + '-content').empty();
  });

  var mousedownOnBackdrop = false;
  $(document).on('mousedown', modalSelector, function (e) {
    mousedownOnBackdrop = $(e.target).is(modalSelector);
  });

  $(document).on('click', modalSelector, function (e) {
    if ($(e.target).is(modalSelector) && mousedownOnBackdrop) {
      $(this).removeClass('modal-active');
      $(this).find('#' + window.reedcrm.eventpro.modalId + '-content').empty();
    }
  });

  $(document).on('click', '.reedcrm-modal-open, .reedcrm-card-modal-open', function (e) {
    var $button = $(this);
    var modalUrl = $button.attr('data-modal-url');
    var projectId = $button.attr('data-project-id');

    if (modalUrl) {
      var $modal = $('#' + window.reedcrm.eventpro.modalId);

      $modal.addClass('modal-active');
      $modal.attr('data-project-id', projectId);

      window.reedcrm.eventpro.loadModalContent(modalUrl);
    }
  });
};

/**
 * Initialize add contact functionality
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 */
window.reedcrm.eventpro.initAddContact = function () {
  $(document).on('click', '.reedcrm-add-contact-btn', function (e) {
    e.preventDefault();
    var $form = $(this).closest('.reedcrm-contact-field-wrapper').parent().find('.reedcrm-add-contact-form');
    $form.slideDown();
  });

  $(document).on('click', '.reedcrm-add-contact-cancel', function (e) {
    e.preventDefault();
    var $form = $(this).closest('.reedcrm-add-contact-form');
    $form.slideUp();
    $form.find('input').val('');
  });

  $(document).on('click', '.reedcrm-add-contact-submit', function (e) {
    e.preventDefault();
    window.reedcrm.eventpro.submitAddContact($(this));
  });
};

/**
 * Submit add contact form
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.1.0
 *
 * @param {jQuery} $button The submit button element
 */
window.reedcrm.eventpro.submitAddContact = function ($button) {
  var $form = $button.closest('.reedcrm-add-contact-form');
  var $mainForm = $form.closest('form');
  var $contactSelect = $mainForm.find('select[name="contactid"]');
  var socid = $mainForm.find('select[name="socid"]').val();

  var lastname = $form.find('#new_contact_lastname').val().trim();
  if (!lastname) {
    if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
      window.saturne.notification.error('Le nom est obligatoire');
    }
    return;
  }

  if (!socid) {
    if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
      window.saturne.notification.error('Veuillez sélectionner un tiers');
    }
    return;
  }

  var formData = {
    action: 'create_contact',
    token: $mainForm.find('input[name="token"]').val(),
    from_id: $mainForm.find('input[name="from_id"]').val(),
    from_type: $mainForm.find('input[name="from_type"]').val(),
    socid: socid,
    new_contact_lastname: lastname,
    new_contact_firstname: $form.find('#new_contact_firstname').val().trim(),
    new_contact_phone_pro: $form.find('#new_contact_phone_pro').val().trim(),
    new_contact_email: $form.find('#new_contact_email').val().trim()
  };

  $button.prop('disabled', true);

  var baseUrl = $mainForm.attr('action');
  if (baseUrl) {
    baseUrl = baseUrl.split('?')[0];
  } else {
    baseUrl = window.location.href.split('?')[0];
  }

  $.ajax({
    url: baseUrl,
    type: 'POST',
    data: formData,
    dataType: 'json',
    success: function (response) {
      if (response && response.success) {
        var newOption = new Option(response.contact_label, response.contact_id, true, true);
        $contactSelect.append(newOption).trigger('change');

        $form.slideUp();
        $form.find('input').val('');

        if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
          window.saturne.notification.success('Contact créé avec succès');
        }
      } else {
        var errorMsg = (response && response.error) ? response.error : 'Erreur lors de la création du contact';
        if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
          window.saturne.notification.error(errorMsg);
        }
      }
    },
    error: function (xhr, status, error) {
      console.error('AJAX Error:', xhr, status, error);
      var errorMsg = 'Erreur lors de la création du contact';
      try {
        if (xhr.responseText) {
          var errorResponse = JSON.parse(xhr.responseText);
          if (errorResponse.error) {
            errorMsg = errorResponse.error;
          }
        }
      } catch (e) {
        console.error('Error parsing response:', e);
      }

      if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
        window.saturne.notification.error(errorMsg);
      }
    },
    complete: function () {
      $button.prop('disabled', false);
    }
  });
};

/**
 * Initialize tooltips for relaunch buttons
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 */
window.reedcrm.eventpro.initRelaunchTooltips = function () {
  var tooltipTimeout;
  var $currentTooltip = null;
  var tooltipHovered = false;
  var loadingTooltip = false;

  $(document).on('mouseenter', '.reedcrm-relaunch-button', function () {
    var $button = $(this);
    var type = $button.data('relaunch-type');
    var $wrapper = $button.closest('.reedcrm-relaunch-buttons');
    var projectId = $wrapper.find('.reedcrm-modal-open').first().data('project-id');
    var socid = $wrapper.data('socid') || '';

    if ((!projectId && !socid) || !type) {
      return;
    }

    clearTimeout(tooltipTimeout);

    if ($currentTooltip) {
      $currentTooltip.remove();
      $currentTooltip = null;
    }
    tooltipHovered = false;
    loadingTooltip = true;

    var actionTypeMap = {
      'call': 'AC_TEL',
      'email': 'AC_EMAIL',
      'rdv': 'AC_RDV',
      'other': 'AC_OTH',
      'all': 'all'
    };
    var actionType = actionTypeMap[type] || 'AC_OTH';

    var tooltipContent = '<div class="reedcrm-relaunch-tooltip">';
    tooltipContent += '<div class="reedcrm-relaunch-tooltip-header">';

    var typeLabels = {
      'call': 'Appels',
      'email': 'Emails',
      'rdv': 'RDV',
      'other': 'Autres',
      'all': 'Relances commerciales'
    };

    tooltipContent += '<strong>' + (typeLabels[type] || type) + '</strong>';
    tooltipContent += '</div>';
    tooltipContent += '<div class="reedcrm-relaunch-tooltip-content">';
    tooltipContent += '<div class="reedcrm-relaunch-tooltip-loading">' + (typeof window.saturne !== 'undefined' && window.saturne.loader ? '' : 'Chargement...') + '</div>';
    tooltipContent += '</div>';
    tooltipContent += '</div>';

    $currentTooltip = $(tooltipContent);
    $currentTooltip.css({
      position: 'absolute',
      zIndex: 10000,
      display: 'none'
    });
    $('body').append($currentTooltip);

    var buttonOffset = $button.offset();
    var buttonHeight = $button.outerHeight();

    $currentTooltip.css({ visibility: 'hidden', display: 'block' });
    var tooltipWidth = $currentTooltip.outerWidth();
    var tooltipHeight = $currentTooltip.outerHeight();
    $currentTooltip.css({ visibility: 'visible', display: 'none' });

    var left = buttonOffset.left;
    var top = buttonOffset.top + buttonHeight + 5;

    if (left + tooltipWidth > $(window).width()) {
      left = $(window).width() - tooltipWidth - 10;
    }
    if (left < 10) {
      left = 10;
    }
    if (top + tooltipHeight > $(window).height()) {
      top = buttonOffset.top - tooltipHeight - 5;
    }
    if (top < 10) {
      top = 10;
    }

    $currentTooltip.css({
      left: left + 'px',
      top: top + 'px'
    });

    tooltipTimeout = setTimeout(function () {
      $currentTooltip.fadeIn(200);
      const ajaxUrl = $wrapper.find('.reedcrm-modal-open').first().data('ajax-url') || '/custom/reedcrm/ajax/get_relaunches_list.php';

      $.ajax({
        url: ajaxUrl,
        type: 'GET',
        data: {
          project_id: projectId || '',
          socid: socid,
          action_type: actionType,
          limit: $button.data('limit') || 0,
          token: $('meta[name=anti-csrf-currenttoken]').attr('content') || ''
        },
        dataType: 'json',
        success: function (response) {
          loadingTooltip = false;
          if (response && response.success && response.html) {
            $currentTooltip.find('.reedcrm-relaunch-tooltip-content').html(response.html);
          } else {
            var errorMsg = (response && response.error) ? response.error : 'Erreur lors du chargement';
            $currentTooltip.find('.reedcrm-relaunch-tooltip-content').html('<div class="reedcrm-relaunch-tooltip-empty">' + errorMsg + '</div>');
          }
        },
        error: function (xhr, status, error) {
          loadingTooltip = false;
          var errorMsg = 'Erreur lors du chargement';
          if (xhr.responseJSON && xhr.responseJSON.error) {
            errorMsg = xhr.responseJSON.error;
          } else if (xhr.status === 0) {
            errorMsg = 'Erreur de connexion';
          } else if (xhr.status === 404) {
            errorMsg = 'Fichier non trouvé';
          } else if (xhr.status === 500) {
            errorMsg = 'Erreur serveur';
          }
          $currentTooltip.find('.reedcrm-relaunch-tooltip-content').html('<div class="reedcrm-relaunch-tooltip-empty">' + errorMsg + '</div>');
          console.error('AJAX Error:', status, error, xhr);
        }
      });
    }, 300);

    $currentTooltip.on('mouseenter', function () {
      tooltipHovered = true;
      clearTimeout(tooltipTimeout);
    });

    $currentTooltip.on('mouseleave', function () {
      tooltipHovered = false;
      if (!loadingTooltip) {
        $currentTooltip.fadeOut(150, function () {
          $(this).remove();
          $currentTooltip = null;
        });
      }
    });
  });

  $(document).on('mouseleave', '.reedcrm-relaunch-button', function () {
    clearTimeout(tooltipTimeout);
    tooltipTimeout = setTimeout(function () {
      if ($currentTooltip && !tooltipHovered && !loadingTooltip) {
        $currentTooltip.fadeOut(150, function () {
          $(this).remove();
          $currentTooltip = null;
        });
      }
    }, 150);
  });
};
