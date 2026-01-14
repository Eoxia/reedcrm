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
  window.reedcrm.eventpro.initAddContact();
  window.reedcrm.eventpro.initRelaunchTooltips();
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
      var $modal = $('#' + window.reedcrm.eventpro.modalId);
      var $iframe = $('#' + window.reedcrm.eventpro.modalId + '-iframe');
      var $loader = $('#' + window.reedcrm.eventpro.modalId + '-loader');

      // Show loader and open modal IMMEDIATELY for better UX
      $loader.show().addClass('wpeo-loader');
      if (typeof window.saturne !== 'undefined' && window.saturne.loader) {
        window.saturne.loader.display($loader);
      }
      $modal.addClass('modal-active');

      // Reset iframe opacity immediately
      $iframe.css('opacity', '0');

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

          // Initialize add contact functionality in iframe
          window.reedcrm.eventpro.initAddContactInIframe(iframeDoc, this);
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
      $modal.attr('data-project-id', projectId);

      // Set iframe src AFTER opening modal and showing loader (loads in background)
      // Use setTimeout to ensure modal is visible first
      setTimeout(function() {
        $iframe.attr('src', modalUrl);
      }, 50);
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
window.reedcrm.eventpro.initAddContact = function() {
  // Handle clicks on the add contact button (both in main page and iframe)
  $(document).on('click', '.reedcrm-add-contact-btn', function(e) {
    e.preventDefault();
    var $form = $(this).closest('.reedcrm-contact-field-wrapper').parent().find('.reedcrm-add-contact-form');
    $form.slideDown();
  });

  // Handle cancel button
  $(document).on('click', '.reedcrm-add-contact-cancel', function(e) {
    e.preventDefault();
    var $form = $(this).closest('.reedcrm-add-contact-form');
    $form.slideUp();
    $form.find('input').val('');
  });

  // Handle form submission
  $(document).on('click', '.reedcrm-add-contact-submit', function(e) {
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
 * @version 1.0.0
 *
 * @param {jQuery} $button The submit button element
 */
window.reedcrm.eventpro.submitAddContact = function($button) {
  var $form = $button.closest('.reedcrm-add-contact-form');
  var $mainForm = $form.closest('form');
  var $contactSelect = $mainForm.find('select[name="contactid"]');
  var socid = $mainForm.find('select[name="socid"]').val();

  // Validation
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

  // Get form data
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

  // Disable button during submission
  $button.prop('disabled', true);

  // Get the current page URL (could be in iframe or main page)
  var currentUrl = window.location.href;
  var baseUrl = currentUrl.split('?')[0];

  // Submit via AJAX
  $.ajax({
    url: baseUrl,
    type: 'POST',
    data: formData,
    dataType: 'json',
    success: function(response) {
      if (response && response.success) {
        // Add new option to select2
        var newOption = new Option(response.contact_label, response.contact_id, true, true);
        $contactSelect.append(newOption).trigger('change');

        // Hide form and clear inputs
        $form.slideUp();
        $form.find('input').val('');

        // Show success message
        if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
          window.saturne.notification.success('Contact créé avec succès');
        }
      } else {
        // Show error message
        var errorMsg = (response && response.error) ? response.error : 'Erreur lors de la création du contact';
        if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
          window.saturne.notification.error(errorMsg);
        }
      }
    },
    error: function(xhr, status, error) {
      console.error('AJAX Error:', xhr, status, error);
      // Try to parse error response
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
    complete: function() {
      $button.prop('disabled', false);
    }
  });
};

/**
 * Initialize add contact functionality in iframe
 *
 * @memberof ReedCRM_EventPro
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param {Document} iframeDoc The iframe document
 * @param {HTMLElement} iframeElement The iframe element
 */
window.reedcrm.eventpro.initAddContactInIframe = function(iframeDoc, iframeElement) {
  var iframeWindow = iframeDoc.defaultView || iframeDoc.parentWindow;
  var iframe$ = iframeWindow.$ || iframeWindow.jQuery || $;

  // Handle clicks on the add contact button in iframe
  iframe$(iframeDoc).on('click', '.reedcrm-add-contact-btn', function(e) {
    e.preventDefault();
    var $form = iframe$(this).closest('.reedcrm-contact-field-wrapper').parent().find('.reedcrm-add-contact-form');
    $form.slideDown();
  });

  // Handle cancel button in iframe
  iframe$(iframeDoc).on('click', '.reedcrm-add-contact-cancel', function(e) {
    e.preventDefault();
    var $form = iframe$(this).closest('.reedcrm-add-contact-form');
    $form.slideUp();
    $form.find('input').val('');
  });

  // Handle form submission in iframe
  iframe$(iframeDoc).on('click', '.reedcrm-add-contact-submit', function(e) {
    e.preventDefault();
    var $button = iframe$(this);
    var $form = $button.closest('.reedcrm-add-contact-form');
    var $contactForm = $button.closest('form');
    var $contactSelect = $contactForm.find('select[name="contactid"]');
    var socid = $contactForm.find('select[name="socid"]').val();

    // Validation
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

    // Get form data
    var formData = {
      action: 'create_contact',
      token: $contactForm.find('input[name="token"]').val(),
      from_id: $contactForm.find('input[name="from_id"]').val(),
      from_type: $contactForm.find('input[name="from_type"]').val(),
      socid: socid,
      new_contact_lastname: lastname,
      new_contact_firstname: $form.find('#new_contact_firstname').val().trim(),
      new_contact_phone_pro: $form.find('#new_contact_phone_pro').val().trim(),
      new_contact_email: $form.find('#new_contact_email').val().trim()
    };

    // Disable button during submission
    $button.prop('disabled', true);

    // Get URL from iframe
    var baseUrl = iframeWindow.location.href.split('?')[0];

    // Submit via AJAX using parent window's jQuery (or iframe's if available)
    $.ajax({
      url: baseUrl,
      type: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        if (response && response.success) {
          // Add new option to select2 in iframe
          var newOption = new Option(response.contact_label, response.contact_id, true, true);
          $contactSelect.append(newOption).trigger('change');

          // Hide form and clear inputs
          $form.slideUp();
          $form.find('input').val('');

          // Show success message
          if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
            window.saturne.notification.success('Contact créé avec succès');
          }
        } else {
          // Show error message
          var errorMsg = (response && response.error) ? response.error : 'Erreur lors de la création du contact';
          if (typeof window.saturne !== 'undefined' && window.saturne.notification) {
            window.saturne.notification.error(errorMsg);
          }
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX Error:', xhr, status, error);
        // Try to parse error response
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
      complete: function() {
        $button.prop('disabled', false);
      }
    });
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
window.reedcrm.eventpro.initRelaunchTooltips = function() {
  var tooltipTimeout;
  var $currentTooltip = null;
  var tooltipHovered = false;
  var loadingTooltip = false;

  $(document).on('mouseenter', '.reedcrm-relaunch-button', function() {
    var $button = $(this);
    var type = $button.data('relaunch-type');
    var projectId = $button.closest('.reedcrm-relaunch-buttons').find('.reedcrm-modal-open').first().data('project-id');

    if (!projectId || !type) {
      return;
    }

    // Clear any existing timeout
    clearTimeout(tooltipTimeout);

    // Remove existing tooltip if any
    if ($currentTooltip) {
      $currentTooltip.remove();
      $currentTooltip = null;
    }
    tooltipHovered = false;
    loadingTooltip = true;

    // Map type to action code
    var actionTypeMap = {
      'call': 'AC_TEL',
      'email': 'AC_EMAIL',
      'rdv': 'AC_RDV',
      'other': 'AC_OTH'
    };
    var actionType = actionTypeMap[type] || 'AC_OTH';

    // Create tooltip container with loader
    var tooltipContent = '<div class="reedcrm-relaunch-tooltip">';
    tooltipContent += '<div class="reedcrm-relaunch-tooltip-header">';

    var typeLabels = {
      'call': 'Appels',
      'email': 'Emails',
      'rdv': 'RDV',
      'other': 'Autres'
    };

    tooltipContent += '<strong>' + (typeLabels[type] || type) + '</strong>';
    tooltipContent += '</div>';
    tooltipContent += '<div class="reedcrm-relaunch-tooltip-content">';
    tooltipContent += '<div class="reedcrm-relaunch-tooltip-loading">' + (typeof window.saturne !== 'undefined' && window.saturne.loader ? '' : 'Chargement...') + '</div>';
    tooltipContent += '</div>';
    tooltipContent += '</div>';

    // Create tooltip element
    $currentTooltip = $(tooltipContent);
    $currentTooltip.css({
      position: 'absolute',
      zIndex: 10000,
      display: 'none'
    });
    $('body').append($currentTooltip);

    // Position tooltip below the button, aligned to the left
    var buttonOffset = $button.offset();
    var buttonHeight = $button.outerHeight();

    // Show tooltip first to get dimensions
    $currentTooltip.css({ visibility: 'hidden', display: 'block' });
    var tooltipWidth = $currentTooltip.outerWidth();
    var tooltipHeight = $currentTooltip.outerHeight();
    $currentTooltip.css({ visibility: 'visible', display: 'none' });

    // Position below the button, aligned to left edge
    var left = buttonOffset.left;
    var top = buttonOffset.top + buttonHeight + 5; // 5px below the button

    // Adjust if tooltip goes off screen to the right
    if (left + tooltipWidth > $(window).width()) {
      left = $(window).width() - tooltipWidth - 10; // 10px margin from right
    }
    // Adjust if tooltip goes off screen to the left
    if (left < 10) {
      left = 10; // 10px margin from left
    }
    // Adjust if tooltip goes off screen to the bottom
    if (top + tooltipHeight > $(window).height()) {
      top = buttonOffset.top - tooltipHeight - 5; // 5px above the button
    }
    // Adjust if tooltip goes off screen to the top
    if (top < 10) {
      top = 10; // 10px margin from top
    }

    $currentTooltip.css({
      left: left + 'px',
      top: top + 'px'
    });

    // Show tooltip with slight delay
    tooltipTimeout = setTimeout(function() {
      $currentTooltip.fadeIn(200);
        var currentUrl = window.location.href;
        var urlObj = new URL(currentUrl);
        var pathname = urlObj.pathname;

        // Extract base path (remove /projet/list.php or similar)
        var pathParts = pathname.split('/').filter(function(p) { return p && p !== ''; });
        // Remove the last 2 parts (module/page.php) to get base
        if (pathParts.length >= 2) {
          pathParts = pathParts.slice(0, -2);
        }
        var basePath = pathParts.length > 0 ? '/' + pathParts.join('/') : '';
        const ajaxUrl = basePath + '/custom/reedcrm/ajax/get_relaunches_list.php';

      $.ajax({
        url: ajaxUrl,
        type: 'GET',
        data: {
          project_id: projectId,
          action_type: actionType,
          token: $('meta[name=anti-csrf-currenttoken]').attr('content') || ''
        },
        dataType: 'json',
        success: function(response) {
          loadingTooltip = false;
          if (response && response.success && response.html) {
            $currentTooltip.find('.reedcrm-relaunch-tooltip-content').html(response.html);
          } else {
            var errorMsg = (response && response.error) ? response.error : 'Erreur lors du chargement';
            $currentTooltip.find('.reedcrm-relaunch-tooltip-content').html('<div class="reedcrm-relaunch-tooltip-empty">' + errorMsg + '</div>');
          }
        },
        error: function(xhr, status, error) {
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

    // Keep tooltip visible when hovering over it
    $currentTooltip.on('mouseenter', function() {
      tooltipHovered = true;
      clearTimeout(tooltipTimeout);
    });

    $currentTooltip.on('mouseleave', function() {
      tooltipHovered = false;
      if (!loadingTooltip) {
        $currentTooltip.fadeOut(150, function() {
          $(this).remove();
          $currentTooltip = null;
        });
      }
    });
  });

  $(document).on('mouseleave', '.reedcrm-relaunch-button', function() {
    clearTimeout(tooltipTimeout);
    if ($currentTooltip && !tooltipHovered && !loadingTooltip) {
      $currentTooltip.fadeOut(150, function() {
        $(this).remove();
        $currentTooltip = null;
      });
    }
  });
};

