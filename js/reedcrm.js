/* Copyright (C) 2022-2025 EVARISK <technique@evarisk.com>
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
 * \brief   JavaScript file for module ReedCRM.
 */

'use strict';

if (!window.reedcrm) {
  /**
   * Init ReedCRM JS.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @type {Object}
   */
  window.reedcrm = {};

  /**
   * Init scriptsLoaded ReedCRM.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @type {Boolean}
   */
  window.reedcrm.scriptsLoaded = false;
}

if (!window.reedcrm.scriptsLoaded) {
  /**
   * ReedCRM init.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @returns {void}
   */
  window.reedcrm.init = function() {
    window.reedcrm.load_list_script();
  };

  /**
   * Load all modules' init.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @returns {void}
   */
  window.reedcrm.load_list_script = function() {
    if (!window.reedcrm.scriptsLoaded) {
      let key = undefined, slug = undefined;
      for (key in window.reedcrm) {
        if (window.reedcrm[key].init) {
          window.reedcrm[key].init();
        }
        for (slug in window.reedcrm[key]) {
          if (window.reedcrm[key] && window.reedcrm[key][slug] && window.reedcrm[key][slug].init) {
            window.reedcrm[key][slug].init();
          }
        }
      }
      window.reedcrm.scriptsLoaded = true;
    }
  };

  /**
   * Refresh and reload all modules' init.
   *
   * @memberof ReedCRM_Init
   *
   * @since   1.1.0
   * @version 1.1.0
   *
   * @returns {void}
   */
  window.reedcrm.refresh = function() {
    let key = undefined;
    let slug = undefined;
    for (key in window.reedcrm) {
      if (window.reedcrm[key].refresh) {
        window.reedcrm[key].refresh();
      }
      for (slug in window.reedcrm[key]) {
        if (window.reedcrm[key] && window.reedcrm[key][slug] && window.reedcrm[key][slug].refresh) {
          window.reedcrm[key][slug].refresh();
        }
      }
    }
  };
  $(document).ready(window.reedcrm.init);
}











window.saturne.contact_inline = {};

window.saturne.contact_inline.init = function() {
    window.saturne.contact_inline.mountCardUi();
    window.saturne.contact_inline.event();
};

window.saturne.contact_inline.event = function() {
    // Liste: copy icons, cancel buttons inside standard contacts (if any left)
    $(document).on('click', '.copy-action-icon', window.saturne.contact_inline.copyToClipboard);
    
    // Inline quick-edit click delegator logic (Handles Title, Contact spans, Percent, Amount, Company)
    $(document).on('click', '.inline-edit-proj-title, .inline-edit-contact', window.saturne.contact_inline.startInlineEdit);
    $(document).on('click', '.inline-edit-proj-percent', window.saturne.contact_inline.editPercent);
    $(document).on('click', '.inline-edit-proj-amount', window.saturne.contact_inline.editAmount);
    $(document).on('click', '.inline-edit-company-badge', window.saturne.contact_inline.startCompanyEdit);
    $(document).on('click', '.inline-edit-origin-badge', window.saturne.contact_inline.startOriginEdit);
};

window.saturne.contact_inline.copyToClipboard = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let textToCopy = $(this).data('copy');
    let icon = $(this);
    let originalClass = icon.attr('class');
    let originalColor = icon.css('color');
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            icon.attr('class', 'fas fa-check').css('color', '#38a169');
            setTimeout(() => { icon.attr('class', originalClass).css('color', originalColor); }, 1500);
        }).catch(err => console.error('Erreur Copie:', err));
    }
};

window.saturne.contact_inline.mountCardUi = function() {
    let contextData = $('#reedcrm-inline-data');
    if (contextData.length === 0) return;
    if ($('.reedcrm-card-header-blocks').length > 0) return; // UI already mounted, prevent duplicates
    
    let projId = contextData.data('project-id');
    let rawAmount = parseFloat(contextData.data('amount') || 0);
    let percentStr = contextData.data('percent-str');
    let amountStr = contextData.data('amount-str');
    let logoPath = contextData.data('logo-path');
    let percentVal = contextData.data('percent-val');
    
    // 1. Title Modification
    let refidno = $('div.refidno');
    if (refidno.length > 0) {
        let editval = refidno.find('.editval');
        if (editval.length === 0) {
            let firstNode = refidno.contents().filter(function() {
                return this.nodeType === 3 && $.trim(this.nodeValue) !== ''; 
            }).first();
            
            if (firstNode.length > 0) {
                let originalTitle = $.trim(firstNode.text());
                
                let wrapperHtml = '<div class="reedcrm-header-title-wrapper" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 600; font-size: 0.95em; margin-bottom: 2px;">' +
                    '<img src="' + logoPath + '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" title="Géré par ReedCRM" />' +
                    '</div>';
                
                let wrapper = $(wrapperHtml);
                firstNode.replaceWith(wrapper);
                
                let prefixSpan = $('<span class="dynamic-libelle-prefix" style="color: #4a5568; cursor: pointer; font-weight: bold; margin-right: 6px; padding-bottom: 1px;" title="Modifier le titre">Libellé :</span>');
                let editableSpan = $('<span class="inline-edit-proj-title" data-project-id="' + projId + '" data-val="" style="color: #0f172a; cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; min-width: 100px;" title="Modifier le titre"></span>');
                editableSpan.text(originalTitle).attr('data-val', originalTitle);
                
                wrapper.append(prefixSpan).append(editableSpan);
                prefixSpan.on('click', function(e) { e.stopPropagation(); editableSpan.click(); });
            }
        } else {
             let wrapperHtml = '<div class="reedcrm-header-title-wrapper" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 600; font-size: 0.95em; margin-bottom: 2px; margin-right: 6px;">' +
                 '<img src="' + logoPath + '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" title="Géré par ReedCRM" />' +
                 '<span style="color: #4a5568; font-weight: bold; margin-right: 4px;">Libellé :</span>' +
                 '</div>';
             $(wrapperHtml).insertBefore(editval);
        }
    }
    
    // 2. Probability and Amount
    let statusRef = $('.statusref');
    let titleBlock = $('.arearef > div').first();
    if ($('.reedcrm-header-stats').length === 0) {
        let statsHtml = '<div class="reedcrm-header-stats" style="' + (statusRef.length > 0 ? '' : 'float: right; ') + 'display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; margin-right: 15px; vertical-align: middle; font-weight: 600; font-size: 0.95em;">' +
            '<img src="' + logoPath + '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" title="Géré par ReedCRM" />' +
            '<span class="inline-edit-proj-percent" data-project-id="'+projId+'" data-val="'+percentVal+'" style="color: #0f172a; cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; align-items: center; white-space: nowrap; line-height: 1;" title="Modifier la probabilité">' + percentStr + '</span>' +
            '<span style="color: #cbd5e0; margin: 0 6px;">-</span>' +
            '<span class="inline-edit-proj-amount" data-project-id="'+projId+'" data-val="'+rawAmount+'" style="color: #3b82f6; cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; align-items: center; white-space: nowrap; line-height: 1;" title="Modifier le montant">' + amountStr + '</span>' +
            '</div>';
            
        if (statusRef.length > 0) {
            let badge = statusRef.find('.badge, .status, .label');
            if (badge.length > 0) {
                $(statsHtml).insertBefore(badge.first());
            } else {
                statusRef.prepend(statsHtml);
            }
        } else if (titleBlock.length > 0) {
            titleBlock.prepend(statsHtml);
        }
    }

    // 3. Align Title and Contact blocks perfectly on the left, side-by-side if space permits
    let titleWrapper = $('.reedcrm-header-title-wrapper');
    let contactWrapper = $('.contact-inline-wrapper.reedcrm-header-contact-master');
    if (titleWrapper.length > 0 && contactWrapper.length > 0) {
        let alignWrapper = $('<div class="reedcrm-card-header-blocks" style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px; margin-left: 0; padding-left: 0; align-items: flex-start; clear: both;"></div>');
        titleWrapper.wrap(alignWrapper);
        contactWrapper.appendTo(titleWrapper.parent());
        
        // Remove trailing or separating <br> tags within refidno to avoid random gaps
        refidno.children('br').remove();
        
        // In case previously added margins persist, wipe them
        titleWrapper.css({'margin-left': '0', 'margin-right': '0', 'margin-bottom': '0'});
        contactWrapper.css({'margin-left': '0', 'margin-right': '0', 'margin-bottom': '0'});
    }
    
    // 4. Third-Party Badge Styling & Interception
    // Find the standard third-party link (usually an <a> tag that contains "socid=" inside refidno, avoiding tiny C/P/S badges)
    let companyBadge = refidno.find('a[href*="socid="]').not('.customer-back').not('.vendor-back').first();
    if (companyBadge.length > 0) {
        let compHref = companyBadge.attr('href');
        
        // Remove native building icons from the text link
        let buildingIcon = companyBadge.find('.fa-building, .fa-building-o, .fa-industry');
        if (buildingIcon.length > 0) {
            buildingIcon.remove();
            // Also trim leading spaces left after icon removal
            companyBadge.html(companyBadge.html().replace(/&nbsp;/g, '').trim());
        }
        
        // Wrap it with our standard UI badge styling if not already done
        if (!companyBadge.parent().hasClass('reedcrm-header-company-wrapper')) {
            let compWrapperHtml = '<div class="reedcrm-header-company-wrapper" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;"></div>';
            companyBadge.wrap(compWrapperHtml);
            companyBadge.before('<img src="' + logoPath + '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />');
            
            // Inject the dedicated _blank hyperlink using the building icon
            companyBadge.before('<a href="' + compHref + '" target="_blank" style="color: #64748b; margin-right: 6px; display: inline-flex; align-items: center;" title="Ouvrir la fiche tiers"><i class="fas fa-building"></i></a>');
        }
        
        // Ensure the text looks like editable fields
        companyBadge.addClass('inline-edit-company-badge').css({
            'cursor': 'pointer',
            'transition': 'color 0.3s',
            'color': '#0f172a'
        }).attr('title', 'Modifier l\'entreprise rattachée');
        
        // Force the wrapper into the align flexbox if it exists
        let companyWrapper = companyBadge.closest('.reedcrm-header-company-wrapper');
        let alignWrapper = $('.reedcrm-card-header-blocks');
        if (alignWrapper.length > 0 && companyWrapper.length > 0) {
            companyWrapper.insertAfter($('.reedcrm-header-title-wrapper'));
            companyWrapper.css({'margin-left': '0', 'margin-right': '0', 'margin-bottom': '0'});
        }
    } else {
        // No company linked yet! Provide a UI to assign one.
        let titleWrapper = $('.reedcrm-header-title-wrapper');
        let alignWrapper = $('.reedcrm-card-header-blocks');
        
        if (titleWrapper.length > 0) {
             let placeholderHtml = '<div class="reedcrm-header-company-wrapper" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">' +
                 '<img src="' + logoPath + '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />' +
                 '<a href="#" class="inline-edit-company-badge" style="cursor: pointer; transition: color 0.3s; color: #0f172a; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px;" title="Affecter une entreprise"><i class="fas fa-building" style="margin-right:5px; color:#64748b;"></i>Affecter un tiers</a>' +
                 '</div>';
             
             let newWrapper = $(placeholderHtml);
             if (alignWrapper.length > 0) {
                 newWrapper.insertAfter(titleWrapper);
                 newWrapper.css({'margin-left': '0', 'margin-right': '0', 'margin-bottom': '0'});
             } else {
                 newWrapper.insertAfter(titleWrapper);
             }
        }
    }
}

window.saturne.contact_inline.startCompanyEdit = function(e) {
    if ($(e.target).hasClass('select2-selection__choice__remove') || $(e.target).closest('.select2-container').length > 0) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    let aTag = $(this);
    let originalHtml = aTag.html();
    
    // Retrieve the hidden selector we injected in php
    let hiddenSelectorWrap = $('#reedcrm-hidden-company-selector');
    if (hiddenSelectorWrap.length === 0) return;
    
    // Check if we already injected it in place, avoiding duplicates
    if (aTag.parent().find('#reedcrm-hidden-company-selector').length > 0) {
        // Just show it
        aTag.hide();
        hiddenSelectorWrap.show();
        let s2 = hiddenSelectorWrap.find('select');
        if (s2.data('select2')) { s2.select2('open'); }
        return;
    }
    
    aTag.hide();
    hiddenSelectorWrap.insertAfter(aTag).show();
    
    let select2Elem = $('#reedcrm_inline_socid');
    if (select2Elem.data('select2')) {
        select2Elem.select2('open');
    }
    
    // Disable native change events from other listeners if any, then add ours
    select2Elem.off('change.inlineedit').on('change.inlineedit', function() {
        let newSocid = $(this).val();
        let projId = $('#reedcrm-inline-data').data('project-id');
        let token = $('input[name="token"]').val() || '';
        
        // Basic revert if no change or empty selected
        if (!newSocid || newSocid == 0) {
            hiddenSelectorWrap.hide();
            aTag.show();
            return;
        }
        
        let url = 'undefined' != typeof dolibarr_main_url_root && dolibarr_main_url_root ? dolibarr_main_url_root : '';
        if (!url) {
            if (document.URL.indexOf('/projet/') > 0) url = document.URL.substring(0, document.URL.indexOf('/projet/'));
            else if (document.URL.indexOf('/custom/') > 0) url = document.URL.substring(0, document.URL.indexOf('/custom/'));
        }
        
        let ajaxUrl = url + '/custom/reedcrm/view/frontend/quickcreation.php?action=updateoppsocid&token=' + token;
        
        aTag.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i> Enregistrement...');
        hiddenSelectorWrap.hide();
        aTag.show();
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { projectid: projId, socid: newSocid },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    if (res.new_company_url) {
                        // The res.new_company_url is already the full HTML string for the third party (Dolibarr's native format)
                        // It natively comes out as `<a href="..."> <span class="..."></span> Title </a>`
                        // So we extract its inner HTML and href
                        let tempDiv = $('<div>').html(res.new_company_url);
                        let nativeA = tempDiv.find('a').first();
                        
                        if (nativeA.length > 0) {
                            aTag.attr('href', nativeA.attr('href'));
                            aTag.html(nativeA.html());
                        } else {
                            aTag.html(res.new_company_url);
                        }
                    } else {
                        aTag.html(originalHtml);
                    }
                } else {
                    alert("Erreur: " + (res.error || "Inconnue"));
                    aTag.html(originalHtml);
                }
            },
            error: function() {
                alert("Impossible de joindre le serveur");
                aTag.html(originalHtml);
            }
        });
    });
    
    // Try to catch when Select2 is closed without change to revert UI
    select2Elem.on('select2:close', function () {
        setTimeout(function() {
            if (hiddenSelectorWrap.is(':visible')) {
                // Not selecting anything ? just revert
                hiddenSelectorWrap.hide();
                aTag.show();
            }
        }, 100);
    });
};

window.saturne.contact_inline.startOriginEdit = function(e) {
    if ($(e.target).hasClass('select2-selection__choice__remove') || $(e.target).closest('.select2-container').length > 0) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    let aTag = $(this);
    let originalHtml = aTag.html();
    
    let hiddenSelectorWrap = aTag.parent().find('.reedcrm-hidden-origin-selector-wrap');
    if (hiddenSelectorWrap.length === 0) return;
    
    aTag.hide();
    hiddenSelectorWrap.show();
    
    let selectElem = hiddenSelectorWrap.find('select');
    if (selectElem.length === 0) return;
    
    try {
        // Ensure Select2 is instantiated
        if (!selectElem.data('select2') && !selectElem.hasClass('select2-hidden-accessible')) {
            selectElem.select2({ width: '180px' });
        } else {
            // Explicitly fix Select2 width if it was 0 px due to being initialized while display:none
            let sc = selectElem.next('.select2-container');
            if (sc.length > 0) {
                sc.css('width', '180px');
            }
        }
        
        selectElem.select2('open');
    } catch (err) {
        console.error("SELECT2 ERROR: ", err);
        return;
    }
    
    selectElem.off('change.inlineedit').on('change.inlineedit', function() {
        let newOrigin = $(this).val();
        let newOriginText = $(this).find('option:selected').text();
        let projId = $('#reedcrm-inline-data').data('project-id');
        let token = $('input[name="token"]').val() || '';
        
        let url = 'undefined' != typeof dolibarr_main_url_root && dolibarr_main_url_root ? dolibarr_main_url_root : '';
        if (!url) {
            if (document.URL.indexOf('/projet/') > 0) url = document.URL.substring(0, document.URL.indexOf('/projet/'));
            else if (document.URL.indexOf('/custom/') > 0) url = document.URL.substring(0, document.URL.indexOf('/custom/'));
        }
        let ajaxUrl = url + '/custom/reedcrm/view/frontend/quickcreation.php?action=updateopporigin';
        
        aTag.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i> Enregistrement...');
        hiddenSelectorWrap.hide();
        aTag.show();
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { projectid: projId, origin: newOrigin, token: token },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    aTag.text(newOriginText);
                    aTag.css('color', '#2ecc71');
                    setTimeout(() => aTag.css('color', ''), 1500);
                } else {
                    alert("Erreur: " + (res.error || "Inconnue"));
                    aTag.html(originalHtml);
                }
            },
            error: function() {
                alert("Impossible de joindre le serveur");
                aTag.html(originalHtml);
            }
        });
    });
    
    // Revert UI on close if no change happened
    selectElem.off('select2:close').on('select2:close', function () {
        setTimeout(function() {
            if (hiddenSelectorWrap.is(':visible')) {
                hiddenSelectorWrap.hide();
                aTag.show();
            }
        }, 100);
    });
};

window.saturne.contact_inline.startInlineEdit = function(e) {
    if ($(this).find('input').length > 0) return;
    e.stopPropagation();
    
    let span = $(this);
    let isTitle = span.hasClass('inline-edit-proj-title');
    let currentVal = span.data('val') || '';
    let originalHtml = span.html();
    
    let isPhone = span.data('field') === 'phone';
    let isWebsite = span.data('field') === 'website';
    let isEmail = span.data('field') === 'email';
    let isFlexChild = span.data('field') === 'firstname' || span.data('field') === 'lastname' || isEmail;
    let inputType = isPhone ? 'tel' : (isWebsite ? 'url' : (isEmail ? 'email' : 'text'));
    
    let inputWidth = isTitle ? '250px' : (isFlexChild ? '100%' : (isPhone || isWebsite ? '180px' : '140px'));
    
    // Pour le téléphone avec le widget intlTelInput, forcer un padding à gauche pour éviter la superposition du drapeau
    let extraPadding = isPhone ? 'padding-left: 52px !important; ' : '';
    let input = $('<input type="' + inputType + '" style="width: ' + inputWidth + '; border: 1px solid #3b82f6; border-radius: 4px; padding: 2px 6px; ' + extraPadding + 'font-weight: inherit; font-size: inherit; color: #0f172a; outline: none; box-sizing: border-box; background: white; margin: 0; display: inline-block; line-height: normal; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);" value="">');
    input.val(currentVal);
    
    if (isWebsite) {
        if (currentVal === '' || currentVal === 'http://') {
            input.val('https://');
        } else if (currentVal.startsWith('http://')) {
            input.val('https://' + currentVal.substring(7));
        }
    }
    
    span.html('').append(input);
    
    if (isWebsite) {
        input.on('input', function() {
            let val = $(this).val().trim();
            if (val.startsWith('www.')) {
                val = 'https://' + val;
                $(this).val(val);
            }
            const materialUrlRegex = /^(https?:\/\/)?([\w\-]+(\.[\w\-]+)+)([\/?#].*)?$/i;
            if (val !== '' && val !== 'https://' && val !== 'http://' && !materialUrlRegex.test(val)) {
                $(this).css({ 'border-color': '#e53935', 'color': '#e53935', 'box-shadow': '0 0 0 2px rgba(229, 57, 53, 0.2)' });
            } else {
                $(this).css({ 'border-color': '#3b82f6', 'color': '#0f172a', 'box-shadow': '0 0 0 2px rgba(59, 130, 246, 0.2)' });
            }
        });
    }
    
    if (isPhone && typeof window.intlTelInput !== 'undefined') {
        let baseRoot = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
        if (!baseRoot) {
            if (document.URL.indexOf('/projet/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/projet/'));
            else if (document.URL.indexOf('/custom/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/custom/'));
        }
        input[0].iti = window.intlTelInput(input[0], {
            utilsScript: baseRoot + '/custom/reedcrm/js/intl-tel-input/js/utils.js',
            initialCountry: "fr",
            preferredCountries: ["fr", "be", "ch", "lu", "ca"],
            nationalMode: false,
            autoPlaceholder: "polite",
            dropdownContainer: document.body
        });
        $('.iti__flag-container').css('z-index', 9999);
        
        input[0].addEventListener("open:countrydropdown", function() {
            input[0].itiDropdownOpen = true;
        });
        input[0].addEventListener("close:countrydropdown", function() {
            input[0].itiDropdownOpen = false;
            setTimeout(() => input.focus(), 50);
        });
    }
    
    input.focus();
    input.select();
    
    let submitFunction = isTitle ? window.saturne.contact_inline.submitTitleDetail : window.saturne.contact_inline.submitContactDetail;
    
    input.on('blur', function() { 
        if (input[0] && input[0].itiDropdownOpen) return;
        submitFunction(span, input, originalHtml, currentVal, false); 
    });
    input.on('keydown', function(ev) { 
        ev.stopPropagation(); 
        if (ev.which === 13) { ev.preventDefault(); input.off('blur'); submitFunction(span, input, originalHtml, currentVal, false); }
        else if (ev.which === 9) { ev.preventDefault(); input.off('blur'); submitFunction(span, input, originalHtml, currentVal, true); }
    });
    input.on('click', function(ev) { ev.stopPropagation(); });
};

window.saturne.contact_inline.submitTitleDetail = function(span, input, originalHtml, currentVal, isTabbing) {
    let focusNextSpan = function() {
        let firstContactSpan = $('.contact-inline-wrapper .inline-edit-contact').first();
        if (firstContactSpan.length > 0) {
            firstContactSpan.click();
        }
    };

    let newVal = input.val().trim();
    if (newVal === currentVal) {
        span.html(originalHtml);
        if (isTabbing) {
            focusNextSpan();
        }
        return;
    }
    
    span.data('val', newVal);
    var originalWidth = span.width();
    span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i>').css('min-width', originalWidth + 'px');
    let projectId = span.data('project-id');
    let token = $('input[name="token"]').val() || '';
    
    let baseRoot = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
    if (!baseRoot) {
        if (document.URL.indexOf('/projet/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/projet/'));
        else if (document.URL.indexOf('/custom/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/custom/'));
    }
    let targetUrl = baseRoot + '/custom/reedcrm/view/frontend/quickcreation.php?action=updateopptitle';
    if (document.URL.indexOf('quickcreation.php') > 0) targetUrl = document.URL.split('?')[0] + '?action=updateopptitle';
    
    $.ajax({
        url: targetUrl,
        type: 'POST',
        data: { token: token, projectid: projectId, title: newVal },
        success: function(res) {
            span.css({color: '#2ecc71', 'min-width': ''});
            span.html(newVal ? newVal : '<span style="color:#cbd5e0; font-style:italic;">Sans titre</span>');
            setTimeout(function() { span.css({color: ''}); }, 1500);
            if (isTabbing) {
                focusNextSpan();
            }
        },
        error: function(jqXHR) {
            alert("Erreur XHR Titre: " + jqXHR.status + "\n" + (jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'Aucune reponse'));
            span.html(originalHtml).css('min-width', '');
            span.data('val', currentVal);
            span.css({color: '#e74c3c'});
            setTimeout(() => span.css({color: ''}), 1500);
        }
    });
};

window.saturne.contact_inline.submitContactDetail = function(span, input, originalHtml, currentVal, isTabbing) {
    let newVal = input.val().trim();
    if (span.data('field') === 'phone' && input[0].iti && window.intlTelInputUtils) {
        if (input[0].iti.isValidNumber()) {
            newVal = input[0].iti.getNumber();
        }
    }
    let contactWrapper = span.closest('.contact-inline-wrapper');
    
    let focusNextSpan = function() {
        let allSpans = contactWrapper.find('.inline-edit-contact:visible');
        let currentIndex = allSpans.index(span);
        if (currentIndex >= 0 && currentIndex < allSpans.length - 1) {
            allSpans.eq(currentIndex + 1).click();
        }
    };
    
    let field = span.data('field');
    
    // Email Validation
    if (field === 'email' && newVal !== '') {
        const materialEmailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
        if (!materialEmailRegex.test(newVal)) {
            alert('Format de l\'adresse e-mail invalide.');
            input.focus();
            return;
        }
    }
    
    // Website Validation
    if (field === 'website') {
        if (newVal === 'https://' || newVal === 'http://') {
            newVal = '';
            input.val('');
        }
        if (newVal !== '') {
            if (!newVal.startsWith('http://') && !newVal.startsWith('https://')) {
                newVal = 'https://' + newVal;
                input.val(newVal);
            }
            const materialUrlRegex = /^(https?:\/\/)?([\w\-]+(\.[\w\-]+)+)([\/?#].*)?$/i;
            if (!materialUrlRegex.test(newVal)) {
                alert('Format du site web invalide.');
                input.focus();
                return;
            }
        }
    }

    if (newVal === currentVal) {
        span.html(originalHtml);
        if (isTabbing) focusNextSpan();
        return;
    }
    
    span.data('val', newVal);
    var originalWidth = span.width();
    span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i>').css('min-width', originalWidth + 'px');
    
    let contactProjectId = contactWrapper.data('project-id');
    
    let bFirst = contactWrapper.find('.inline-edit-contact[data-field="firstname"]').data('val');
    let bLast = contactWrapper.find('.inline-edit-contact[data-field="lastname"]').data('val');
    let bPhone = contactWrapper.find('.inline-edit-contact[data-field="phone"]').data('val');
    let bEmail = contactWrapper.find('.inline-edit-contact[data-field="email"]').data('val');
    let bWeb = contactWrapper.find('.inline-edit-contact[data-field="website"]').data('val');
    
    let token = $('input[name="token"]').val() || '';
    
    let baseRoot = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
    if (!baseRoot) {
        if (document.URL.indexOf('/projet/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/projet/'));
        else if (document.URL.indexOf('/custom/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/custom/'));
    }
    let targetUrl = baseRoot + '/custom/reedcrm/view/frontend/quickcreation.php?action=updateoppcontact';
    if (document.URL.indexOf('quickcreation.php') > 0) targetUrl = document.URL.split('?')[0] + '?action=updateoppcontact';
    
    $.ajax({
        url: targetUrl,
        type: 'POST',
        data: {
            token: token,
            projectid: contactProjectId,
            firstname: bFirst,
            lastname: bLast,
            phone: bPhone,
            email: bEmail,
            website: bWeb
        },
        success: function(res) {
            span.css({color: '#2ecc71', 'min-width': ''});
            var pText = '';
            if (field === 'firstname') pText = 'Prénom';
            if (field === 'lastname') pText = 'Nom';
            if (field === 'phone') pText = 'Téléphone';
            if (field === 'email') pText = 'Email';
            if (field === 'website') pText = 'Site Web';
            span.html(newVal ? newVal : '<span style="color:#cbd5e0; font-style:italic;">' + pText + '</span>');
            setTimeout(function() { span.css({color: ''}); }, 1500);
            
            if (isTabbing) {
                focusNextSpan();
            }
        },
        error: function(jqXHR) {
            alert("Erreur XHR Titre: " + jqXHR.status + "\n" + (jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'Aucune reponse'));
            span.html(originalHtml).css('min-width', '');
            span.data('val', currentVal);
            span.css({color: '#e74c3c'});
            setTimeout(() => span.css({color: ''}), 1500);
        }
    });
    
    if (span.data('field') === 'phone' && input[0].iti) {
        input[0].iti.destroy();
    }
};

window.saturne.contact_inline.editPercent = function(e) {
    if ($(this).find('input').length > 0) return;
    e.stopPropagation();
    
    let span = $(this);
    let projId = span.data('project-id');
    let currentVal = parseInt(span.data('val'));
    let originalText = span.text();
    
    let input = $('<input type="number" min="0" max="100" class="percent-input" style="width: 35px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0; font-weight: 600; font-size: 1em; color: #0f172a; outline: none; box-sizing: border-box; background: transparent; margin: 0; display: inline-block; vertical-align: middle; line-height: normal; -moz-appearance: textfield;" value="'+currentVal+'">');
    
    if ($('#css-no-spinners').length === 0) {
        $('head').append('<style id="css-no-spinners">input[type="number"].percent-input::-webkit-outer-spin-button, input[type="number"].percent-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }</style>');
    }
    
    span.html('').append(input).append('<span style="font-weight:normal; margin-left: 2px;">%</span>');
    input.focus();
    
    let submitValuePercent = function() {
        let newVal = parseInt(input.val());
        if (isNaN(newVal) || newVal < 0) newVal = 0;
        if (newVal > 100) newVal = 100;
        
        if (newVal === currentVal) {
            span.html(originalText);
            return;
        }
        
        span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6; line-height: 22px;"></i>');
        let token = $('input[name="token"]').val() || '';
        
        let baseRoot = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
        if (!baseRoot) {
            if (document.URL.indexOf('/projet/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/projet/'));
            else if (document.URL.indexOf('/custom/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/custom/'));
        }
        let targetUrl = baseRoot + '/custom/reedcrm/view/frontend/quickcreation.php?action=updateopppercent';
        if (document.URL.indexOf('quickcreation.php') > 0) targetUrl = document.URL.split('?')[0] + '?action=updateopppercent';
        
        $.ajax({
            url: targetUrl,
            type: 'POST',
            data: { token: token, projectid: projId, percent: newVal },
            success: function(res) {
                if(res && res.success) {
                    span.data('val', newVal);
                    span.html(newVal + ' %');
                    span.css({color: '#2ecc71'});
                    setTimeout(() => span.css({color: '#0f172a'}), 1500);
                } else {
                    span.html(originalText).css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: '#0f172a'}), 1500);
                }
            },
            error: function(jqXHR) {
                alert("Erreur XHR Percent: " + jqXHR.status + "\n" + (jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'Aucune reponse'));
                span.html(originalText).css({color: '#e74c3c'});
                setTimeout(() => span.css({color: originalColor}), 1500);
            }
        });
    };
    
    input.on('blur', submitValuePercent);
    input.on('keypress', function(ev) { ev.stopPropagation(); if (ev.which === 13) { ev.preventDefault(); input.off('blur'); submitValuePercent(); } });
    input.on('keydown', function(ev) {
        if (ev.which === 9) { // Tab
            ev.preventDefault();
            input.off('blur');
            submitValuePercent();
            let amountSpan = span.parent().find('.inline-edit-proj-amount');
            if (amountSpan.length > 0) {
                setTimeout(() => amountSpan.click(), 50);
            }
        }
    });
    input.on('click', function(ev) { ev.stopPropagation(); });
};

window.saturne.contact_inline.editAmount = function(e) {
    if ($(this).find('input').length > 0) return;
    e.stopPropagation();
    
    let span = $(this);
    let projId = span.data('project-id');
    let currentVal = parseFloat(span.data('val'));
    let originalText = span.text();
    
    let inputWidth = originalText.length > 8 ? '80px' : '65px';
    let input = $('<input type="text" class="amount-input" style="width: '+inputWidth+'; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 2px; font-weight: 600; font-size: 1em; color: #3b82f6; outline: none; box-sizing: border-box; background: transparent; margin: 0; display: inline-block; vertical-align: middle; line-height: normal; -moz-appearance: textfield;" value="'+currentVal+'">');
    
    span.html('').append(input).append('<span style="font-weight:normal; margin-left: 4px;">€</span>');
    input.focus();
    
    let submitValueAmount = function() {
        let userStr = input.val().replace(',', '.');
        let newVal = parseFloat(userStr);
        if (isNaN(newVal) || newVal < 0) newVal = 0;
        
        if (newVal === currentVal) {
            span.html(originalText);
            return;
        }
        
        span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6; line-height: 22px;"></i>');
        let token = $('input[name="token"]').val() || '';
        
        let baseRoot = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
        if (!baseRoot) {
            if (document.URL.indexOf('/projet/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/projet/'));
            else if (document.URL.indexOf('/custom/') > 0) baseRoot = document.URL.substring(0, document.URL.indexOf('/custom/'));
        }
        let targetUrl = baseRoot + '/custom/reedcrm/view/frontend/quickcreation.php?action=updateoppamount';
        if (document.URL.indexOf('quickcreation.php') > 0) targetUrl = document.URL.split('?')[0] + '?action=updateoppamount';
        
        $.ajax({
            url: targetUrl,
            type: 'POST',
            data: { token: token, projectid: projId, amount: newVal },
            success: function(res) {
                if(res && res.success) {
                    span.data('val', newVal);
                    span.html(res.formatted_amount);
                    span.css({color: '#2ecc71'});
                    setTimeout(() => span.css({color: '#3b82f6'}), 1500);
                } else {
                    span.html(originalText).css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: '#3b82f6'}), 1500);
                }
            },
            error: function(jqXHR) {
                alert("Erreur XHR Percent: " + jqXHR.status + "\n" + (jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'Aucune reponse'));
                span.html(originalText).css({color: '#e74c3c'});
                setTimeout(() => span.css({color: '#3b82f6'}), 1500);
            }
        });
    };
    
    input.on('blur', submitValueAmount);
    input.on('keypress', function(ev) { ev.stopPropagation(); if (ev.which === 13) { ev.preventDefault(); input.off('blur'); submitValueAmount(); } });
    input.on('keydown', function(ev) {
        if (ev.which === 9) { // Tab
            ev.preventDefault();
            input.off('blur');
            submitValueAmount();
        }
    });
    input.on('click', function(ev) { ev.stopPropagation(); });
};

// Initialize module on ready
$(document).ready(function() {
    if (typeof window.saturne !== 'undefined' && window.saturne.contact_inline) {
        window.saturne.contact_inline.mountCardUi();
        $(document).on('click', '.reedcrm-copy-text', window.saturne.contact_inline.copyToClipboard);
    }

    // --- Data-action event delegation (replaces all inline onclick attributes) ---
    $(document).on('click', '[data-action="open-vcard-modal"]', function() {
        var m = document.getElementById('vcard-modal');
        if (m) m.style.display = 'flex';
    });
    $(document).on('click', '[data-action="close-vcard-modal"]', function() {
        var m = document.getElementById('vcard-modal');
        if (m) m.style.display = 'none';
    });
    $(document).on('click', '[data-action="toggle-geoloc-address"]', function() {
        $('#current-address-block').toggleClass('is-visible');
    });

    // --- CSS hover for .reedcrm-hover-bg (replaces inline onmouseover/onmouseout) ---
    $(document).on('mouseenter', '.reedcrm-hover-bg', function() {
        $(this).css({ 'background': '#f1f5f9', 'border-color': '#e2e8f0' });
    }).on('mouseleave', '.reedcrm-hover-bg', function() {
        $(this).css({ 'background': 'transparent', 'border-color': 'transparent' });
    });
    // Close vcard modal on overlay click
    $(document).on('click', '#vcard-modal', function(e) {
        if ($(e.target).is('#vcard-modal')) { this.style.display = 'none'; }
    });
});

/**
 * PWA Project Card - Client & Contact Selector (inline, no modal)
 * Opens a Select2 dropdown directly under each button.
 */
window.saturne.pwa_selectors = {};

window.saturne.pwa_selectors.init = function() {
    window.saturne.pwa_selectors.event();
};

window.saturne.pwa_selectors.getBaseUrl = function() {
    var url = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
    if (!url) {
        if (document.URL.indexOf('/custom/') > 0) url = document.URL.substring(0, document.URL.indexOf('/custom/'));
    }
    return url + '/custom/reedcrm/view/frontend/quickcreation.php';
};

window.saturne.pwa_selectors.event = function() {
    // Close all inline selectors when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.pwa-selector-wrap').length) {
            $('.pwa-inline-select-wrap').hide();
        }
    });

    // Client selector : open inline AJAX Select2
    $(document).on('click', '.pwa-client-selector', function(e) {
        e.stopPropagation();
        var btn       = $(this);
        var projectId = btn.data('project-id');
        var wrap      = $('#pwa-client-wrap-' + projectId);
        var $select   = $('#pwa-client-select-' + projectId);
        var baseUrl   = window.saturne.pwa_selectors.getBaseUrl();
        var token     = $('input[name="token"]').val() || '';

        // Toggle: close if already open
        if (wrap.is(':visible')) { wrap.hide(); return; }

        // Hide all other open selectors
        $('.pwa-inline-select-wrap').hide();
        wrap.show();

        // Init Select2 once (AJAX — shows 10 recent companies on open, search on typing)
        if (!$select.data('select2')) {
            $select.select2({
                placeholder: 'Clients récents ou tapez pour chercher...',
                minimumInputLength: 0,
                language: {
                    searching: function() { return 'Chargement...'; },
                    noResults: function() { return 'Aucun résultat'; }
                },
                ajax: {
                    url:      baseUrl,
                    dataType: 'json',
                    delay:    200,
                    cache:    true,
                    data:     function(p) { return { action: 'search_tiers_ajax', q: p.term || '' }; },
                    processResults: function(d) { return { results: d.results || [] }; }
                }
            });

            // On selection → save & update DOM (no page reload)
            $select.on('select2:select', function(ev) {
                var newSocid    = ev.params.data.id;
                var newSocName  = ev.params.data.text;
                wrap.hide();
                var $btn       = $('.pwa-client-selector[data-project-id="' + projectId + '"]');
                var origHtml   = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin" style="color:#9b59b6;"></i>');
                $.post(baseUrl + '?action=updateoppsocid&token=' + token, { projectid: projectId, socid: newSocid }, function(res) {
                    if (res && res.success) {
                        var badge = (res.new_company_badge && res.new_company_badge.trim()) ? res.new_company_badge + ' ' : '<i class="far fa-building" style="color:#64748b;"></i> ';
                        $btn.html(
                            badge +
                            '<span style="font-weight:500;">' + $('<span>').text(newSocName).html() + '</span>' +
                            '<i class="fas fa-chevron-down" style="color:#94a3b8;font-size:0.8em;"></i>'
                        );
                        $btn.removeClass('empty').attr('title', 'Changer le tiers');

                        // ── Inject / update the clickable building icon link (absent when project had no client) ──
                        var cardUrl = res.new_company_card_url || '';
                        if (cardUrl) {
                            var $selectorWrap = $btn.closest('.pwa-selector-wrap');
                            var $existingIcon = $selectorWrap.find('a[title="Voir la fiche client"]');
                            if ($existingIcon.length) {
                                // Update existing link
                                $existingIcon.attr('href', cardUrl);
                            } else {
                                // Project had no client before → inject the icon link before the button
                                $btn.before(
                                    '<a href="' + cardUrl + '" class="prevent-edit-click" title="Voir la fiche client" ' +
                                    'style="display:inline-flex;align-items:center;color:#64748b;font-size:1.15em;flex-shrink:0;">' +
                                    '<i class="fas fa-building"></i></a>'
                                );
                            }
                        }

                        // Reset the client Select2 field (clear search term so next open is clean)
                        $select.val(null).trigger('change');

                        // Reset contact preload flag since company changed (old contact selector)
                        var $cSelect = $('#pwa-contact-select-' + projectId);
                        $cSelect.removeData('contacts-loaded').empty();
                        if ($cSelect.data('select2')) { $cSelect.select2('destroy'); }

                        // ── New chip system: update data-tiers-id on the contact tags wrap ──
                        var $tagsWrap = $('.pwa-contact-tags-wrap[data-project-id="' + projectId + '"]');
                        $tagsWrap.data('tiers-id', newSocid);
                        $tagsWrap.attr('data-tiers-id', newSocid);

                        // Update tiers-id on old contact button (legacy, harmless)
                        $('.pwa-contact-selector[data-project-id="' + projectId + '"]').data('tiers-id', newSocid);
                        $('#pwa-contact-wrap-' + projectId).hide();
                    } else {
                        $btn.html(origHtml);
                        $btn.css({ border: '1px solid #e74c3c' });
                        setTimeout(function() { $btn.css({ border: '' }); }, 2000);
                    }
                }, 'json').fail(function() {
                    $btn.html(origHtml);
                    $btn.css({ border: '1px solid #e74c3c' });
                    setTimeout(function() { $btn.css({ border: '' }); }, 2000);
                });
            });

            // Close on clear / close event
            $select.on('select2:close', function() {
                setTimeout(function() { wrap.hide(); }, 100);
            });
        }

        $select.select2('open');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Multi-contact tag system : [+] add button → native dropdown (no Select2)
    // ─────────────────────────────────────────────────────────────────────────
    $(document).on('click', '.pwa-add-contact-btn', function(e) {
        e.stopPropagation();
        var $btn      = $(this);
        var $wrap     = $btn.closest('.pwa-contact-tags-wrap');
        var $panel    = $wrap.find('.pwa-contact-add-panel');
        var projectId = $wrap.data('project-id');
        var tiersId   = $wrap.data('tiers-id') || 0;
        var baseUrl   = window.saturne.pwa_selectors.getBaseUrl();

        // Toggle
        if ($panel.is(':visible')) { $panel.hide(); return; }

        // Close other open panels
        $('.pwa-contact-add-panel').hide();
        $panel.show();

        // No company → warn
        if (tiersId <= 0) {
            $panel.html('<div class="pwa-contact-loading-inline"><i class="fas fa-exclamation-triangle" style="color:#d97706;"></i> Associez d\'abord un client</div>');
            return;
        }

        // Collect already linked contact IDs
        var linkedIds = [];
        $wrap.find('.pwa-contact-chip').each(function() {
            linkedIds.push(parseInt($(this).data('contact-id'), 10));
        });

        // Show spinner while loading
        $panel.html('<div class="pwa-contact-loading-inline"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>');

        // Fetch contacts and build native <ul> — no Select2, no "Searching...", list appears immediately
        $.getJSON(baseUrl, { action: 'search_contact_ajax', socid: tiersId, q: '' }, function(data) {
            var results = (data && data.results) ? data.results : [];
            var $ul = $('<ul class="pwa-contact-list">');
            if (results.length === 0) {
                $ul.append('<li class="pwa-contact-list-empty">Aucun contact pour ce client</li>');
            } else {
                $.each(results, function(i, item) {
                    var isLinked = linkedIds.indexOf(parseInt(item.id, 10)) !== -1;
                    var $li = $('<li>').attr('data-contact-id', item.id).attr('data-contact-name', item.text);
                    if (isLinked) {
                        $li.addClass('pwa-contact-list-linked').html($('<span>').text(item.text).prop('outerHTML') + '<i class="fas fa-check pwa-linked-check"></i>');
                    } else {
                        $li.text(item.text);
                    }
                    $ul.append($li);
                });
            }
            $panel.html($ul); // render immediately — no intermediate state
        }).fail(function() {
            $panel.html('<div class="pwa-contact-loading-inline" style="color:#e74c3c;"><i class="fas fa-exclamation-circle"></i> Impossible de charger les contacts</div>');
        });
    });

    // Click on a contact row → addoppcontact
    $(document).on('click', '.pwa-contact-list li:not(.pwa-contact-list-empty):not(.pwa-contact-list-linked)', function(e) {
        e.stopPropagation();
        var $li            = $(this);
        var $panel         = $li.closest('.pwa-contact-add-panel');
        var $wrap          = $panel.closest('.pwa-contact-tags-wrap');
        var $btn           = $wrap.find('.pwa-add-contact-btn');
        var newContactId   = parseInt($li.data('contact-id'), 10);
        var newContactName = $li.data('contact-name') || $li.text().trim();
        var projectId      = $wrap.data('project-id');
        var baseUrl        = window.saturne.pwa_selectors.getBaseUrl();
        var token          = $('input[name="token"]').val() || '';

        if (!newContactId) return;
        $panel.hide();
        $btn.html('<i class="fas fa-spinner fa-spin"></i>');

        $.post(baseUrl + '?action=addoppcontact&token=' + token,
            { projectid: projectId, contactid: newContactId },
            function(res) {
                $btn.html('<i class="fas fa-plus"></i>');
                if (res && res.success && res.link_id) {
                    var chipHtml =
                        '<span class="pwa-contact-chip" data-link-id="' + res.link_id + '" data-contact-id="' + newContactId + '">' +
                            '<a href="' + res.contact_url + '" class="prevent-edit-click" title="Voir la fiche contact">' +
                                $('<span>').text(newContactName).html() +
                            '</a>' +
                            '<span class="pwa-chip-role">- Intervenant</span>' +
                            '<span class="pwa-chip-remove prevent-edit-click" data-link-id="' + res.link_id + '" title="Retirer ce contact"><i class="fas fa-unlink" style="font-size:0.75em;"></i></span>' +
                        '</span>';
                    $btn.before(chipHtml);
                    $wrap.find('.pwa-contact-icon-nolink').replaceWith(
                        '<a href="' + res.contact_url + '" class="pwa-contact-icon-link prevent-edit-click" title="Voir la fiche contact"><i class="fas fa-address-book"></i></a>'
                    );
                } else {
                    var errMsg = (res && res.error) ? ' (' + res.error + ')' : '';
                    $btn.attr('title', 'Erreur' + errMsg).css({ outline: '2px solid #e74c3c' });
                    setTimeout(function() { $btn.css({ outline: '' }).removeAttr('title'); }, 3000);
                }
            }, 'json').fail(function() {
                $btn.html('<i class="fas fa-plus"></i>');
                $btn.css({ outline: '2px solid #e74c3c' });
                setTimeout(function() { $btn.css({ outline: '' }); }, 2000);
            });
    });

    // Close add-panel on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.pwa-contact-tags-wrap').length) {
            $('.pwa-contact-add-panel').hide();
        }
    });


    // ─────────────────────────────────────────────────────────────────────────
    // Remove a contact chip → unlink via removeoppcontact
    // ─────────────────────────────────────────────────────────────────────────
    $(document).on('click', '.pwa-chip-remove', function(e) {
        e.stopPropagation();
        var $removeBtn = $(this);
        var linkId     = parseInt($removeBtn.data('link-id'), 10);
        var $chip      = $removeBtn.closest('.pwa-contact-chip');
        var $wrap      = $chip.closest('.pwa-contact-tags-wrap');
        var baseUrl    = window.saturne.pwa_selectors.getBaseUrl();
        var token      = $('input[name="token"]').val() || '';

        $removeBtn.html('<i class="fas fa-spinner fa-spin" style="font-size:0.75em;"></i>');

        $.post(baseUrl + '?action=removeoppcontact&token=' + token, { link_id: linkId }, function(res) {
            if (res && res.success) {
                // Green flash confirmation before fade-out
                $chip.css({ outline: '2px solid #38a169', transition: 'outline 0.2s' });
                setTimeout(function() {
                    $chip.fadeOut(250, function() {
                        $(this).remove();
                        // If no chips left, revert icon to greyed-out
                        if ($wrap.find('.pwa-contact-chip').length === 0) {
                            $wrap.find('.pwa-contact-icon-link').replaceWith(
                                '<i class="fas fa-address-book pwa-contact-icon-nolink" title="Ajouter un contact"></i>'
                            );
                        }
                    });
                }, 300);
            } else {
                $removeBtn.html('<i class="fas fa-unlink" style="font-size:0.75em;"></i>');
                $chip.css({ outline: '2px solid #e74c3c' });
                setTimeout(function() { $chip.css({ outline: '' }); }, 2000);
            }
        }, 'json').fail(function() {
            $removeBtn.html('<i class="fas fa-unlink" style="font-size:0.75em;"></i>');
        });
    });
};




/**
 * PWA Quickcreation Form — Geoloc, Phone, Email, URL, Slider, Contact Select
 * Replaces the inline scripts removed from reedcrm_project_quickcreation_frontend.tpl.php
 */
window.saturne.quickcreation_form = {};

window.saturne.quickcreation_form.init = function() {
    if (!document.querySelector('.quickcreation-form') && !document.getElementById('geoloc-header-wrapper')) return;
    window.saturne.quickcreation_form.moveGeolocIcon();
    window.saturne.quickcreation_form.initPhoneValidation();
    window.saturne.quickcreation_form.initEmailValidation();
    window.saturne.quickcreation_form.initWebsiteValidation();
    window.saturne.quickcreation_form.initFormSubmit();
    window.saturne.quickcreation_form.initOppSlider();
    window.saturne.quickcreation_form.initContactSelect();
};

window.saturne.quickcreation_form.moveGeolocIcon = function() {
    setTimeout(function() {
        var $pwaHeader = $('#id-top');
        if ($pwaHeader.length) {
            var $userWidget = $pwaHeader.find('.user-profile-widget');
            if ($userWidget.length) {
                $('#geoloc-header-wrapper').css('display', 'flex').insertBefore($userWidget);
            } else {
                $pwaHeader.append($('#geoloc-header-wrapper').css('display', 'flex'));
            }
            return;
        }
        var $headerRight = $('.login_block, .header-pwa-right, .saturne-header-right').first();
        if ($headerRight.length) {
            $('#geoloc-header-wrapper').css('display', 'flex').prependTo($headerRight);
        } else {
            $('#geoloc-header-wrapper').css({'display':'flex','position':'fixed','top':'12px','right':'80px','z-index':'9999','background':'rgba(255,255,255,0.9)','padding':'4px 8px','border-radius':'4px'}).appendTo('body');
        }
    }, 500);
};

window.saturne.quickcreation_form.initPhoneValidation = function() {
    var dataDiv = document.getElementById('reedcrm-quickcreation-data');
    if (!dataDiv) return;
    var utilsPath = dataDiv.getAttribute('data-utils-path') || '';
    var phoneInput = document.getElementById('projectphone');
    if (!phoneInput || typeof window.intlTelInput === 'undefined') return;
    var iti = window.intlTelInput(phoneInput, {initialCountry:'fr', utilsScript:utilsPath, formatOnDisplay:true, nationalMode:true, autoPlaceholder:'aggressive', preferredCountries:['fr','be','ch','lu','mc']});
    phoneInput.addEventListener('input', function() {
        var val = phoneInput.value;
        var correctedVal = val.replace(/^(?:\+33|0033)[\s\-.]*0([1-9])/, '+33 $1');
        if (correctedVal !== val) { phoneInput.value = val = correctedVal; }
        if (window.intlTelInputUtils) {
            var cp = phoneInput.selectionStart, isEnd = (cp === phoneInput.value.length);
            var ft = val.startsWith('+') ? window.intlTelInputUtils.numberFormat.INTERNATIONAL : window.intlTelInputUtils.numberFormat.NATIONAL;
            var formatted = window.intlTelInputUtils.formatNumber(val, iti.getSelectedCountryData().iso2, ft);
            if (formatted && formatted !== val) { phoneInput.value = formatted; if (!isEnd && phoneInput.setSelectionRange) phoneInput.setSelectionRange(cp, cp); }
        }
        if (phoneInput.value.trim()) {
            if (!iti.isValidNumber()) { phoneInput.classList.add('input-invalid-material'); phoneInput.setCustomValidity('Numéro de téléphone invalide.'); }
            else { phoneInput.classList.remove('input-invalid-material'); phoneInput.setCustomValidity(''); }
        } else { phoneInput.classList.remove('input-invalid-material'); phoneInput.setCustomValidity(''); }
    });
    var form = phoneInput.closest('form');
    if (form) form.addEventListener('submit', function() { if (phoneInput.value.trim() && iti.isValidNumber()) phoneInput.value = iti.getNumber(); });
};

window.saturne.quickcreation_form.initEmailValidation = function() {
    var re = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    document.querySelectorAll('input[type="email"]').forEach(function(el) {
        el.addEventListener('input', function() {
            var v = this.value.trim();
            if (v && !re.test(v)) { this.classList.add('input-invalid-material'); this.setCustomValidity("Format de l'adresse e-mail invalide."); }
            else { this.classList.remove('input-invalid-material'); this.setCustomValidity(''); }
        });
    });
};

window.saturne.quickcreation_form.initWebsiteValidation = function() {
    var dr = /^([\w\-]+(\.[\w\-]+)+)([\/?#].*)?$/i;
    document.querySelectorAll('.website-input-group').forEach(function(group) {
        var ps = group.querySelector('.url-protocol'), di = group.querySelector('.url-domain'), hi = group.querySelector('.url-hidden');
        function validate() {
            var v = di.value.trim();
            if (/^https?:\/\//i.test(v)) { if (v.toLowerCase().startsWith('http://')) { ps.value='http://'; v=v.substring(7); } else { ps.value='https://'; v=v.substring(8); } di.value=v; }
            if (!v) { hi.value=''; group.classList.remove('input-invalid-material'); di.setCustomValidity(''); return; }
            hi.value = ps.value + v;
            if (!dr.test(v)) { group.classList.add('input-invalid-material'); di.setCustomValidity('Format du nom de domaine invalide.'); }
            else { group.classList.remove('input-invalid-material'); di.setCustomValidity(''); }
        }
        ps.addEventListener('change', validate);
        di.addEventListener('input', validate);
    });
};

window.saturne.quickcreation_form.initFormSubmit = function() {
    var er = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    var dr = /^([\w\-]+(\.[\w\-]+)+)([\/?#].*)?$/i;
    var mainForm = document.querySelector('.quickcreation-form');
    if (!mainForm) return;
    mainForm.addEventListener('submit', function(e) {
        if (!this.checkValidity()) return;
        var hasError = false;
        this.querySelectorAll('input[type="email"]').forEach(function(el) { if (el.value.trim() && !er.test(el.value.trim())) { hasError=true; el.classList.add('input-invalid-material'); el.reportValidity(); el.focus(); } });
        this.querySelectorAll('.website-input-group').forEach(function(g) { var di=g.querySelector('.url-domain'); if (di.value.trim() && !dr.test(di.value.trim())) { hasError=true; g.classList.add('input-invalid-material'); di.reportValidity(); di.focus(); } });
        if (hasError) { e.preventDefault(); return; }
        e.preventDefault();
        var btn = mainForm.querySelector('button[type="submit"]');
        if (!btn) return;
        var orig = btn.innerHTML;
        btn.innerHTML='<i class="fas fa-spinner fa-spin" style="font-size:20px;color:#fff;"></i>'; btn.disabled=true;
        var fd = new FormData(mainForm); fd.append('ajax_submission','1');
        fetch(window.location.href,{method:'POST',body:fd})
            .then(function(r) { var ct=r.headers.get('content-type'); return ct&&ct.indexOf('application/json')!==-1?r.json():r.text(); })
            .then(function(data) {
                if (typeof data==='object'&&data.success) { window.location.href=data.redirect_url||window.location.href; }
                else if (typeof data==='string') {
                    var doc=(new DOMParser()).parseFromString(data,'text/html');
                    var errs=doc.querySelectorAll('.error,.theme-error,.jnotify-container,.alert-danger,.warning,.theme-warning');
                    if (errs.length) { document.querySelectorAll('.error,.theme-error,.jnotify-container,.alert-danger,.warning,.theme-warning').forEach(function(n){n.remove();}); var cont=document.getElementById('id-container')||mainForm; errs.forEach(function(n){cont.insertBefore(n,cont.firstChild);}); window.scrollTo({top:0,behavior:'smooth'}); }
                    else if (doc.querySelector('.ok,.theme-success,.theme-statut-ok')) { window.location.reload(); }
                    else { document.open(); document.write(data); document.close(); }
                }
            })
            .catch(function(err) { console.error('Erreur de soumission',err); alert("Une erreur technique s'est produite."); })
            .finally(function() { btn.innerHTML=orig; btn.disabled=false; });
    });
};

window.saturne.quickcreation_form.initOppSlider = function() {
    var s=document.getElementById('opp_percent'), v=document.querySelector('.opp_percent-value');
    if (!s||!v) return;
    function upd() { var val=parseInt(s.value)||0; v.textContent=val+'%'; var p=val/100; v.style.left='calc('+(p*100)+'% - '+(p*45)+'px + 22.5px)'; }
    upd(); s.addEventListener('input', upd);
};

window.saturne.quickcreation_form.initContactSelect = function() {
    if (typeof jQuery === 'undefined') return;
    var dataDiv = document.getElementById('reedcrm-quickcreation-data');
    var lang = dataDiv ? (dataDiv.getAttribute('data-lang') || 'fr') : 'fr';
    function initS2() { if (!jQuery.fn.select2) return; var s=$('#contactid'); if (s.hasClass('select2-hidden-accessible')) s.select2('destroy'); s.select2({width:'100%',language:lang,placeholder:'Contact/Adresse'}); }
    function toggleCW() { var sid=$('#socid').val()||($('#search_socid').length?$('#search_socid').val():null); if (sid&&parseInt(sid)>0) $('#contact-wrapper').slideDown(200); else $('#contact-wrapper').slideUp(200); }
    initS2(); toggleCW();
    $(document).ajaxComplete(function(ev,xhr,s) { if (s.url&&s.url.indexOf('contacts.php')!==-1) { initS2(); toggleCW(); } });
    $(document).on('change','#socid, #search_socid', toggleCW);
    $(document).on('change','#contactid', function() {
        var cid=$(this).val(); if (!cid||cid<=0) return;
        var fd=new FormData(); fd.append('action','get_contact_details'); fd.append('contactid',cid);
        fetch(window.location.href,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(c) {
            if (!c||!c.id) return;
            var pi=document.getElementById('projectphone'); if (pi&&c.phone) { pi.value=c.phone; pi.dispatchEvent(new Event('input',{bubbles:true})); }
            document.querySelectorAll('input[type="email"]').forEach(function(i) { if (i.name==='options_reedcrm_email'&&c.email) { i.value=c.email; i.dispatchEvent(new Event('input',{bubbles:true})); } });
            var fi=document.getElementById('reedcrm_firstname'); if (fi&&c.firstname) { fi.value=c.firstname; fi.dispatchEvent(new Event('input')); }
            var li=document.getElementById('reedcrm_lastname'); if (li&&c.lastname) { li.value=c.lastname; li.dispatchEvent(new Event('input')); }
        }).catch(function(err){console.error('Error fetching contact details',err);});
    });
};

window.reedcrm.call_list_widget = {};

window.reedcrm.call_list_widget.init = function() {
    window.reedcrm.call_list_widget.event();
    window.reedcrm.call_list_widget.initSelect2();
};

window.reedcrm.call_list_widget.initSelect2 = function() {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
    $('.reedcrm-call-list-select').each(function() {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({ width: '200px', minimumResultsForSearch: Infinity });
        }
    });
};

window.reedcrm.call_list_widget.event = function() {
    $(document).off('click', '.reedcrm-call-list-add-btn', window.reedcrm.call_list_widget.handleAdd)
               .on('click', '.reedcrm-call-list-add-btn', window.reedcrm.call_list_widget.handleAdd);
};

window.reedcrm.call_list_widget.handleAdd = function() {
    var wrapper     = $(this).closest('.reedcrm-add-to-call-list-wrapper');
    var elementType = wrapper.data('element-type');
    var elementId   = wrapper.data('element-id');
    var ajaxUrl     = wrapper.data('ajax-url');
    var callListId  = wrapper.find('.reedcrm-call-list-select').val();
    var feedback    = wrapper.find('.reedcrm-call-list-feedback');
    var token       = $('input[name="token"]').val() || '';

    if (!callListId) {
        feedback.removeClass('success').addClass('error').text('Sélectionner une liste');
        setTimeout(function() { feedback.text('').removeClass('error'); }, 3000);
        return;
    }

    var fd = new FormData();
    fd.append('element_type', elementType);
    fd.append('element_id', elementId);
    fd.append('call_list_id', callListId);
    fd.append('token', token);

    fetch(ajaxUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            feedback.text(res.message);
            if (res.success) {
                feedback.removeClass('error').addClass('success');
            } else {
                feedback.removeClass('success').addClass('error');
            }
            setTimeout(function() { feedback.text('').removeClass('success error'); }, 3000);
        })
        .catch(function() {
            feedback.removeClass('success').addClass('error').text('Erreur réseau');
            setTimeout(function() { feedback.text('').removeClass('error'); }, 3000);
        });
};