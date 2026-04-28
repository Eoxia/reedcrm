<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/actions/reedcrm_project_quickcreation_frontend.tpl.php
 * \ingroup reedcrm
 * \brief   Template page for quick creation project frontend
 */

/**
 * The following vars must be defined :
 * Global   : $conf, $langs
 * Objects  : $extraFields, $form, $project
 * Variable : $permissionToAddProject
 */

// Protection to avoid direct call of template
if (!$permissionToAddProject) {
    exit;
}

require_once __DIR__ . '/../../../../saturne/core/tpl/medias/media_editor_modal.tpl.php'; ?>

<?php require_once __DIR__ . '/reedcrm_pwa_header.tpl.php'; ?>

<style>
    .quickcreation-frontend .quickcreation-form-container,
    .quickcreation-frontend .page-content {
        padding: 0 10px !important;
        margin: 0 auto !important;
        max-width: 1000px !important;
        height: auto !important;
        overflow: visible !important;
    }

    @media (max-width: 1024px) {
        .quickcreation-form-container .form-group {
            margin-bottom: 5px !important;
        }

        /* Force single-column collapse universally on all tablets and phones */
        .quickcreation-form-container .form-row-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 5px !important;
        }
        .opp-row {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 5px !important;
        }
        .opp-row > div.form-group {
            flex: none !important;
            width: 100% !important;
            margin-bottom: 0 !important;
        }
    }

    /* Force the native Dolibarr Form::showphoto element to fit symmetrically inside the 32x32px boundary */
    .custom-badge-avatar {
        width: 100% !important;
        height: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        object-fit: contain !important; /* Contain handles both true JPG ratios and the tiny fallback PNG natively */
        border: none !important;
    }
</style>

<div id="id-container" class="page-content">
    <?php print saturne_show_notice('', '', 'error', 'notice-infos', false, true, '', ['Error' => $langs->transnoentities('Error')]); ?>

    <div class="quickcreation-form-container">
        <!-- Project label -->
        <div class="form-group">
            <input type="text" id="title" name="title" placeholder="<?php echo $langs->trans('ProjectLabel'); ?> (ex: Projet Refonte Web...)" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>" required>
        </div>

        <!-- Description -->
        <?php if ($conf->global->REEDCRM_PROJECT_DESCRIPTION_VISIBLE > 0) : ?>
            <div class="form-group">
                <textarea name="description" id="description" rows="4" placeholder="<?php echo $langs->trans('Description'); ?> (Détails du lead...)"><?php echo dol_escape_htmltag((GETPOSTISSET('description') ? GETPOST('description', 'restricthtml') : '')); ?></textarea>
            </div>
        <?php endif; ?>

        <!-- ExtraFields -->
        <div class="form-row-grid">
        <?php if (getDolGlobalInt('REEDCRM_PROJECT_EXTRAFIELDS_VISIBLE')) :
            $positions = $extraFields->attributes['projet']['pos'];
            asort($positions);
            $sortedType = [];
            foreach ($positions as $key => $pos) {
                $sortedType[$key] = $extraFields->attributes['projet']['type'][$key];
            }
            $extraFields->attributes['projet']['type'] = $sortedType; 

            foreach ($extraFields->attributes['projet']['type'] as $key => $value) {
                if ((strpos($key, 'reedcrm') === false && $key != 'projectphone') || $key == 'reedcrm_gravityform') {
                    continue;
                }

                $inputType = 'text';
                if ($value == 'mail') {
                    $inputType = 'email';
                }
                if ($value == 'phone') {
                    $inputType = 'tel';
                }
                if ($value == 'url') {
                    $val = GETPOSTISSET('options_'.$key) ? GETPOST('options_'.$key) : '';
                    $protocol = 'https://';
                    if (strpos($val, 'http://') === 0) {
                        $protocol = 'http://';
                        $val = substr($val, 7);
                    } elseif (strpos($val, 'https://') === 0) {
                        $protocol = 'https://';
                        $val = substr($val, 8);
                    }
                    
                    print '<div class="form-group">';
                    print '<div class="website-input-group url-group-'.$key.'" style="display: flex; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #fff; transition: all 0.2s;">';
                    print '<select class="url-protocol" style="border: none; background: transparent; padding: 0 0 0 10px; color: #0f172a; outline: none; cursor: pointer; font-size: inherit; width: 85px;">';
                    print '<option value="https://"'.($protocol=='https://'?' selected':'').'>https://</option>';
                    print '<option value="http://"'.($protocol=='http://'?' selected':'').'>http://</option>';
                    print '</select>';
                    print '<input type="text" class="url-domain" placeholder="'.$langs->trans($extraFields->attributes['projet']['label'][$key]).'" value="'.dol_escape_htmltag($val).'" style="border: none; flex: 1; outline: none; background: transparent; padding: 10px 10px 10px 5px; min-width: 0; font-size: inherit;">';
                    print '<input type="hidden" id="'.$key.'" class="url-hidden" name="options_'.$key.'" value="'.dol_escape_htmltag($protocol.$val).'">';
                    print '</div>';
                    print '</div>';
                } else {
                    print '<div class="form-group">';
                    print '<input type="' . $inputType . '" id="' . $key . '" name="options_' . $key . '" placeholder="' . $langs->trans($extraFields->attributes['projet']['label'][$key]) . '" value="' . dol_escape_htmltag((GETPOSTISSET('options_'.$key) ? GETPOST('options_'.$key) : '')) . '">';
                    print '</div>';
                }
            }
        endif; ?>
        </div>

                <!-- Opportunity option -->
        <?php if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) : ?>
            <div class="opp-row" style="display: flex; gap: 15px; align-items: center; margin-top: 0; margin-bottom: 0;">
                <!-- 70% -->
                <div class="form-group" style="flex: 7; margin-bottom: 0;">
                    <div class="opp-percent" style="display: flex; align-items: center;">
                        <span style="font-size: 22px; margin-right: 8px;">🥵</span>
                        <div style="position: relative; flex: 1; display: flex; align-items: center; --val: <?php echo empty($project->opp_percent) ? '0' : $project->opp_percent; ?>;">
                            <input type="range" class="range" name="opp_percent" id="opp_percent" min="0" max="100" step="10" value="<?php echo empty($project->opp_percent) ? '0' : $project->opp_percent; ?>" style="width: 100%; cursor: pointer; margin: 0; background: transparent; outline: none;">
                            <div class="opp_percent-value"><?php echo empty($project->opp_percent) ? '0%' : $project->opp_percent . '%'; ?></div>
                        </div>
                        <span style="font-size: 22px; margin-left: 8px;">🤑</span>
                    </div>
                </div>

                <!-- 30% -->
                <div class="form-group" style="flex: 3; margin-bottom: 0;">
                    <?php if ($conf->global->REEDCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) : ?>
                        <div class="input-with-icon" style="margin-top: 0; line-height: 1;">
                            <span class="input-icon">€</span>
                            <input type="text" inputmode="decimal" name="opp_amount" id="opp_amount" placeholder="Montant" value="<?php echo dol_escape_htmltag((GETPOSTISSET('opp_amount') ? GETPOST('opp_amount', 'int') : '')); ?>" style="width: 100%;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Visual Section Divider Removed & Relocated Below -->

        <!-- Tags / Categories -->
        <?php if (isModEnabled('categorie')) : ?>
            <div class="form-group" style="margin-top: 8px;">
                <?php print $form->selectCategories(Categorie::TYPE_PROJECT, 'categories'); ?>
            </div>
        <?php endif; ?>

        <!-- Media & Submit Block -->
        <div class="action-buttons-row" style="margin-top: 15px; margin-bottom: 15px;">
            <?php include __DIR__ . '/reedcrm_project_quickcreation_media_frontend.tpl.php'; ?>
        </div>

        <!-- GPS -->
        <input type="hidden" id="latitude"  name="latitude" value="">
        <input type="hidden" id="longitude" name="longitude" value="">
        <input type="hidden" id="geolocation-error" name="geolocation-error" value="">

        <!-- Current address display -->
        <div id="current-address-block" style="display:flex; align-items:center; gap:10px; margin-top:10px; margin-bottom:20px; padding:12px 14px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; overflow:hidden;">
            <i id="current-address-icon" class="fas fa-circle-notch fa-spin" style="font-size:16px; color:#3498db; flex-shrink:0;"></i>
            <div style="flex:1; min-width:0;">
                <div id="current-address-text" style="font-size:13px; color:#34495e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo $langs->trans('DetectingLocation'); ?>…</div>
                <div id="current-address-coords" style="font-size:11px; color:#94a3b8; margin-top:2px;"></div>
            </div>
        </div>

    </div>
</div>

<!-- intl-tel-input (Local libphonenumber integration) -->
<link rel="stylesheet" href="<?php echo dol_buildpath('/reedcrm/js/intl-tel-input/css/intlTelInput.css', 1); ?>">
<style>
    /* Fix the width issue caused by the library inserting a wrapper */
    .iti { width: 100%; display: block; }
    
    /* Prevent the flag from overlapping the typed text by forcing padding */
    .iti input[type="tel"] {
        padding-left: 52px !important;
    }
    
    /* Material Design Error State for Inputs (and Custom Groups) */
    input.input-invalid-material, .website-input-group.input-invalid-material {
        border: 1px solid #e53935 !important;
        color: #e53935 !important;
        box-shadow: inset 0 0 0 1px #e53935 !important;
    }
    .website-input-group.input-invalid-material input, .website-input-group.input-invalid-material select {
        color: #e53935 !important;
    }
    
    .website-input-group:focus-within {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important;
    }
</style>
<script src="<?php echo dol_buildpath('/reedcrm/js/intl-tel-input/js/intlTelInput.min.js', 1); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Phone Validation (Local libphonenumber) ---
    const phoneInput = document.getElementById('projectphone');
    if (phoneInput) {
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "fr",
            utilsScript: "<?php echo dol_buildpath('/reedcrm/js/intl-tel-input/js/utils.js', 1); ?>",
            formatOnDisplay: true,
            nationalMode: true,
            autoPlaceholder: "aggressive",
            preferredCountries: ["fr", "be", "ch", "lu", "mc"]
        });

        // Saisie au fur et à mesure et Validation dynamique
        phoneInput.addEventListener('input', function() {
            let val = phoneInput.value;
            
            // Correction automatique du +33 06 -> +33 6 (Erreur classique)
            let correctedVal = val.replace(/^(?:\+33|0033)[\s\-.]*0([1-9])/, '+33 $1');
            if (correctedVal !== val) {
                val = correctedVal;
                phoneInput.value = val;
            }

            // Mettre à jour "au fur et à mesure"
            if (window.intlTelInputUtils) {
                let currentPos = phoneInput.selectionStart;
                let isAtEnd = (currentPos === phoneInput.value.length);
                
                let formatType = val.startsWith('+') ? window.intlTelInputUtils.numberFormat.INTERNATIONAL : window.intlTelInputUtils.numberFormat.NATIONAL;
                let formatted = window.intlTelInputUtils.formatNumber(val, iti.getSelectedCountryData().iso2, formatType);
                
                if (formatted && formatted !== val) {
                    phoneInput.value = formatted;
                    // Empêcher le saut de curseur
                    if (!isAtEnd && phoneInput.setSelectionRange) {
                        phoneInput.setSelectionRange(currentPos, currentPos); 
                    }
                }
            }

            if (phoneInput.value.trim() !== '') {
                if (!iti.isValidNumber()) {
                    phoneInput.classList.add('input-invalid-material');
                    phoneInput.setCustomValidity('Numéro de téléphone invalide.');
                } else {
                    phoneInput.classList.remove('input-invalid-material');
                    phoneInput.setCustomValidity('');
                }
            } else {
                phoneInput.classList.remove('input-invalid-material');
                phoneInput.setCustomValidity('');
            }
        });

        // Ensure we send a validly formatted E.164 number on submit if present
        const form = phoneInput.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                if (phoneInput.value.trim() && iti.isValidNumber()) {
                    phoneInput.value = iti.getNumber();
                }
            });
        }
    }

    // --- Email Validation (Material Design) ---
    const emailInputs = document.querySelectorAll('input[type="email"]');
    // La regex la plus proche du standard HTML5 préconisée par Google
    const materialEmailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;

    emailInputs.forEach(function(emailInput) {
        // Validation dynamique
        emailInput.addEventListener('input', function() {
            const emailValue = this.value.trim();
            if (emailValue !== '' && !materialEmailRegex.test(emailValue)) {
                this.classList.add('input-invalid-material');
                this.setCustomValidity('Format de l\'adresse e-mail invalide.');
            } else {
                this.classList.remove('input-invalid-material');
                this.setCustomValidity('');
            }
        });
    });

    // --- URL Validation (Website Split Input) ---
    const websiteGroups = document.querySelectorAll('.website-input-group');
    const domainRegex = /^([\w\-]+(\.[\w\-]+)+)([\/?#].*)?$/i;

    websiteGroups.forEach(function(group) {
        const protocolSelect = group.querySelector('.url-protocol');
        const domainInput = group.querySelector('.url-domain');
        const hiddenInput = group.querySelector('.url-hidden');

        function updateHiddenAndValidate() {
            let domainVal = domainInput.value.trim();
            
            // Clean up if user pasted the full URL including protocol into the domain part
            if (/^https?:\/\//i.test(domainVal)) {
                if (domainVal.toLowerCase().startsWith('http://')) {
                    protocolSelect.value = 'http://';
                    domainVal = domainVal.substring(7);
                } else if (domainVal.toLowerCase().startsWith('https://')) {
                    protocolSelect.value = 'https://';
                    domainVal = domainVal.substring(8);
                }
                domainInput.value = domainVal;
            }
            
            // Allow empty string to pass validation natively (it's not required)
            if (domainVal === '') {
                hiddenInput.value = '';
                group.classList.remove('input-invalid-material');
                domainInput.setCustomValidity('');
                return false;
            }
            
            hiddenInput.value = protocolSelect.value + domainVal;

            if (!domainRegex.test(domainVal)) {
                group.classList.add('input-invalid-material');
                domainInput.setCustomValidity('Format du nom de domaine invalide.');
                return true; // has format error
            } else {
                group.classList.remove('input-invalid-material');
                domainInput.setCustomValidity('');
                return false; // no error
            }
        }

        protocolSelect.addEventListener('change', updateHiddenAndValidate);
        domainInput.addEventListener('input', updateHiddenAndValidate);
    });

    // --- AJAX Form Submission (Prevent media loss on validation error) ---
    const mainForm = document.querySelector('.quickcreation-form');
    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            // Only intercept if native HTML5 validation passes
            if (!this.checkValidity()) return;
            
            // Check custom material email regex
            let hasFormatError = false;
            const formEmailInputs = this.querySelectorAll('input[type="email"]');
            formEmailInputs.forEach(function(emailInput) {
                const emailValue = emailInput.value.trim();
                if (emailValue !== '' && !materialEmailRegex.test(emailValue)) {
                    hasFormatError = true;
                    emailInput.classList.add('input-invalid-material');
                    emailInput.reportValidity();
                    emailInput.focus();
                }
            });
            
            // Check custom material url split regex
            const formWebsiteGroups = this.querySelectorAll('.website-input-group');
            formWebsiteGroups.forEach(function(group) {
                const domainInput = group.querySelector('.url-domain');
                let domainVal = domainInput.value.trim();
                
                if (domainVal !== '' && !domainRegex.test(domainVal)) {
                    hasFormatError = true;
                    group.classList.add('input-invalid-material');
                    domainInput.reportValidity();
                    domainInput.focus();
                }
            });
            
            if (hasFormatError) {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            
            const submitBtn = mainForm.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            const originalBtnContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 20px; color: #fff;"></i>';
            submitBtn.disabled = true;
            
            const formData = new FormData(mainForm);
            formData.append('ajax_submission', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // If standard dolibarr redirect fires or JSON is returned depending on backend
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text();
                }
            })
            .then(data => {
                if (typeof data === 'object' && data.success) {
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                } else if (typeof data === 'string') {
                    // It's probably an HTML error page or standard Dolibarr notice rendering
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    
                    const errorDivs = doc.querySelectorAll('.error, .theme-error, .jnotify-container, .alert-danger, .warning, .theme-warning');
                    if (errorDivs.length > 0) {
                        // Clear old errors
                        let oldNotices = document.querySelectorAll('.error, .theme-error, .jnotify-container, .alert-danger, .warning, .theme-warning');
                        oldNotices.forEach(n => n.remove());
                        
                        const container = document.getElementById('id-container') || mainForm;
                        if (container) {
                            errorDivs.forEach(errNode => {
                                container.insertBefore(errNode, container.firstChild);
                            });
                            // Scroll to top to see error
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    } else if (doc.querySelector('.ok, .theme-success, .theme-statut-ok')) {
                        // Success messages detected in HTML reload
                        window.location.reload();
                    } else {
                        // Fallback reload if we somehow get HTML without recognizable states
                        document.open();
                        document.write(data);
                        document.close();
                    }
                }
            })
            .catch(err => {
                console.error("Erreur de soumission", err);
                alert("Une erreur technique s'est produite lors de la soumission.");
            })
            .finally(() => {
                submitBtn.innerHTML = originalBtnContent;
                submitBtn.disabled = false;
            });
        });
    }

    // --- Slider Opportunity Percent Sync ---
    const oppSlider = document.getElementById('opp_percent');
    const oppValueEl = document.querySelector('.opp_percent-value');
    if (oppSlider && oppValueEl) {
        
        function updateSlider() {
            let val = parseInt(oppSlider.value) || 0;
            oppValueEl.textContent = val + '%';
            
            // Calc exact left position directly via JS to override any broken CSS calc() on mobile
            // Assume track is 100%, thumb is 45px width.
            // At 0%, center is 22.5px. At 100%, center is calc(100% - 22.5px).
            let percentage = val / 100;
            oppValueEl.style.left = 'calc(' + (percentage * 100) + '% - ' + (percentage * 45) + 'px + 22.5px)';
        }
        // Init
        updateSlider();
        
        // Update on drag
        oppSlider.addEventListener('input', updateSlider);
    }
});
</script>

<?php
