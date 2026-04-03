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
        // Wrap it with our standard UI badge styling if not already done
        if (!companyBadge.parent().hasClass('reedcrm-header-company-wrapper')) {
            let compWrapperHtml = '<div class="reedcrm-header-company-wrapper" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;"></div>';
            companyBadge.wrap(compWrapperHtml);
            companyBadge.before('<img src="' + logoPath + '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />');
        }
        
        // Ensure the link looks like the editable fields
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
                } else {
                    span.html(originalText).css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: ''}), 1500);
                }
            },
            error: function(jqXHR) {
                alert("Erreur XHR Percent: " + jqXHR.status + "\n" + (jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'Aucune reponse'));
                span.html(originalText).css({color: '#e74c3c'});
                setTimeout(() => span.css({color: ''}), 1500);
            }
        });
    };
    
    input.on('blur', submitValuePercent);
    input.on('keypress', function(ev) { ev.stopPropagation(); if (ev.which === 13) { ev.preventDefault(); input.off('blur'); submitValuePercent(); } });
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
                } else {
                    span.html(originalText).css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: ''}), 1500);
                }
            },
            error: function(jqXHR) {
                alert("Erreur XHR Percent: " + jqXHR.status + "\n" + (jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'Aucune reponse'));
                span.html(originalText).css({color: '#e74c3c'});
                setTimeout(() => span.css({color: ''}), 1500);
            }
        });
    };
    
    input.on('blur', submitValueAmount);
    input.on('keypress', function(ev) { ev.stopPropagation(); if (ev.which === 13) { ev.preventDefault(); input.off('blur'); submitValueAmount(); } });
    input.on('click', function(ev) { ev.stopPropagation(); });
};

// Initialize module on ready
$(document).ready(function() {
    if (typeof window.saturne !== 'undefined' && window.saturne.contact_inline) {
        // Mount the UI elements into the banner (Title, Percentage, Amount)
        window.saturne.contact_inline.mountCardUi();
        
        // Bind all click delegates
        $(document).on('click', '.inline-edit-proj-title', window.saturne.contact_inline.startInlineEdit);
        $(document).on('click', '.inline-edit-contact', window.saturne.contact_inline.startInlineEdit);
        $(document).on('click', '.inline-edit-proj-percent', window.saturne.contact_inline.startPercentEdit);
        $(document).on('click', '.inline-edit-proj-amount', window.saturne.contact_inline.startAmountEdit);
        $(document).on('click', '.reedcrm-copy-text', window.saturne.contact_inline.copyText);
    }
});
