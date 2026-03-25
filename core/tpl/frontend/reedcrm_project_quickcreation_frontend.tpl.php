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

<div id="id-top" class="page-header-tabs" style="position: fixed; top: 0; left: 0; right: 0; z-index: 999; width: 100%; box-sizing: border-box; margin: 0; border-radius: 0; background-color: #ffffff; padding: 0 15px; height: 48px; border-bottom: 2px solid #3b82f6; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; align-items: center;">
    <div class="company-logo-wrapper" style="margin-right: 20px; display: flex; align-items: center;">
        <?php
        global $mysoc, $db, $conf;
        if (empty($mysoc)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $mysoc = new Societe($db);
            $mysoc->setMysoc($conf);
        }
        $logoFile = '';
        if (!empty($mysoc->logo_squarred)) {
            $logoFile = 'logos/'.$mysoc->logo_squarred; // viewimage handles thumbs directly if you pass the root file, or we just pass logos/...
        } elseif (!empty($mysoc->logo)) {
            $logoFile = 'logos/'.$mysoc->logo;
        }
        if (!empty($logoFile)) {
            $logoUrl = DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode($logoFile);
            print '<img class="company-logo" src="'.$logoUrl.'" alt="Logo" style="max-height: 28px; max-width: 120px; object-fit: contain;">';
        }
        ?>
    </div>
    <div class="tabs-nav" style="display: flex; height: 100%; gap: 15px;">
        <a href="<?php echo $_SERVER["PHP_SELF"]; ?>" class="tab active" style="display: flex; align-items: center; height: 100%; padding: 0 10px; color: #0f172a; text-decoration: none; font-size: 15px; font-weight: 600; border-bottom: 3px solid #0f172a;">
            <i class="fas fa-share-alt" style="margin-right: 6px; font-size: 14px;"></i> Opportunités
        </a>
        <a href="<?php echo dol_buildpath('/ticket/list.php', 1); ?>" target="_blank" class="tab" style="display: flex; align-items: center; height: 100%; padding: 0 10px; color: #64748b; text-decoration: none; font-size: 15px; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s ease;">
            <i class="fas fa-ticket-alt" style="margin-right: 6px; font-size: 14px;"></i> Ticket
        </a>
    </div>
    
    <!-- User Profile Badge & VCard Trigger -->
    <?php
    global $user;
    $vcardUrl = '';
    if (getDolUserInt('USER_ENABLE_PUBLIC', 0, $user)) {
        $vcardUrl = $user->getOnlineVirtualCardUrl('', 'external');
    }
    ?>
    <?php
    if ($vcardUrl) {
        $widgetTag = 'div';
        $widgetAttr = 'onclick="document.getElementById(\'vcard-modal\').style.display=\'flex\'"';
    } else {
        $widgetTag = 'a';
        $profileUrl = dol_buildpath('/user/virtualcard.php', 1) . '?id=' . $user->id;
        $widgetAttr = 'href="' . dol_escape_htmltag($profileUrl) . '" target="_blank" title="Cliquez ici pour activer votre carte de visite (rechargez ensuite cette page)"';
    }
    ?>
    <<?php echo $widgetTag; ?> class="user-profile-widget" <?php echo $widgetAttr; ?> style="margin-left: auto; display: flex; align-items: center; gap: 12px; cursor: pointer; color: #1e293b; text-decoration: none; padding: 4px 10px; border-radius: 8px; transition: background 0.2s; border: 1px solid transparent;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
        <?php
        global $db;
        $formObj = new Form($db);
        $nativeAvatar = $formObj->showphoto('userphoto', $user, 0, 0, 0, 'custom-badge-avatar', 'small', 0);
        
        print '<div class="user-avatar-wrap" style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; background: transparent;">';
        print $nativeAvatar;
        print '</div>';
        
        if ($vcardUrl) {
            print '<i class="fas fa-qrcode" style="font-size: 22px; color: #64748b;"></i>';
        }
        ?>
    </<?php echo $widgetTag; ?>>
</div>

<!-- VCard Modal -->
<?php if ($vcardUrl) { ?>
<div id="vcard-modal" class="wpeo-modal modal-vcard" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; display: none; align-items: center; justify-content: center;">
    <div class="modal-container" style="background: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); width: 95%; max-width: 480px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; animation: modalFadeIn 0.3s ease;">
        <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <h2 class="modal-title" style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">Carte de visite</h2>
            <div class="modal-close" onclick="document.getElementById('vcard-modal').style.display='none'" style="cursor: pointer; color: #64748b; font-size: 20px; line-height: 1;" onmouseover="this.style.color='#e74c3c';" onmouseout="this.style.color='#64748b';"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content" style="padding: 0; overflow-y: auto; flex-grow: 1; height: 75vh; background: #f1f5f9;">
            <iframe src="<?php echo dol_escape_htmltag($vcardUrl); ?>" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>
<?php } ?>

<style>
    /* Expand global desktop form limit to leverage widescreen real estate, whilst sticking header to the top */
    body.quickcreation-frontend {
        padding: 0 !important;
        padding-top: 48px !important; /* Offset content exactly by the height of the fixed navbar */
        margin: 0 !important;
    }
    
    /* Safely zero-out Dolibarr's native llxHeader layout classes only inside our module */
    body.quickcreation-frontend .fiche,
    body.quickcreation-frontend #id-right,
    body.quickcreation-frontend #id-container {
        padding-top: 0 !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }
    .quickcreation-frontend .quickcreation-form-container,
    .quickcreation-frontend .page-content {
        padding: 0 10px !important;
        margin: 0 auto !important;
        max-width: 1000px !important;
        height: auto !important;
        overflow: visible !important;
    }

    @media (max-width: 1024px) {
        body.quickcreation-frontend {
            padding: 0 !important;
            padding-top: 48px !important; /* Retain identical 48px offset on mobile to dock content cleanly under fixed header */
            margin: 0 !important;
        }
        .page-content {
            max-width: 100% !important;
            padding-top: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-top: 0 !important;
        }
        .quickcreation-form-container {
            padding: 5px 5px 10px 5px !important;
            margin-top: 0 !important;
            border-radius: 0 !important;
        }
        .quickcreation-form-container .form-group {
            margin-bottom: 5px !important;
        }
        
        /* Compress Navigation Header (id-top) to fit sub-300px viepworts */
        #id-top {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            border-bottom: none !important;
            border-radius: 0 !important;
            padding: 0 10px !important;
            gap: 5px !important;
            overflow-x: auto;
        }
        #id-top .company-logo-wrapper {
            margin-right: 5px !important;
        }
        #id-top .company-logo {
            max-width: 60px !important;
        }
        #id-top .tabs-nav {
            gap: 5px !important;
        }
        #id-top .tab {
            padding: 0 5px !important;
            font-size: 12px !important;
            white-space: nowrap;
        }
        #id-top .user-profile-widget {
            gap: 6px !important;
            padding: 2px 4px !important;
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
                if (strpos($key, 'reedcrm') === false && $key != 'projectphone') {
                    continue;
                }

                $inputType = 'text';
                if ($value == 'mail') {
                    $inputType = 'email';
                }
                if ($value == 'phone') {
                    $inputType = 'tel';
                }

                print '<div class="form-group">';
                print '<input type="' . $inputType . '" id="' . $key . '" name="options_' . $key . '" placeholder="' . $langs->trans($extraFields->attributes['projet']['label'][$key]) . '" value="' . dol_escape_htmltag((GETPOSTISSET('options_'.$key) ? GETPOST('options_'.$key) : '')) . '">';
                print '</div>';
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

        <!-- Media & Submit Block -->
        <div class="action-buttons-row" style="margin-top: 15px; margin-bottom: 15px;">
            <?php include __DIR__ . '/reedcrm_project_quickcreation_media_frontend.tpl.php'; ?>
        </div>

        <!-- GPS -->
        <input type="hidden" id="latitude"  name="latitude" value="">
        <input type="hidden" id="longitude" name="longitude" value="">
        <input type="hidden" id="geolocation-error" name="geolocation-error" value="">

    </div>
</div>

<!-- intl-tel-input (Local libphonenumber integration) -->
<link rel="stylesheet" href="<?php echo dol_buildpath('/reedcrm/vendor/intl-tel-input/css/intlTelInput.css', 1); ?>">
<style>
    /* Fix the width issue caused by the library inserting a wrapper */
    .iti { width: 100%; display: block; }
    
    /* Prevent the flag from overlapping the typed text by forcing padding */
    .iti input[type="tel"] {
        padding-left: 52px !important;
    }
    
    /* Material Design Error State for Inputs */
    input.input-invalid-material {
        border-color: #e53935 !important;
        border-bottom: 2px solid #e53935 !important;
        color: #e53935 !important;
    }
</style>
<script src="<?php echo dol_buildpath('/reedcrm/vendor/intl-tel-input/js/intlTelInput.min.js', 1); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Phone Validation (Local libphonenumber) ---
    const phoneInput = document.getElementById('projectphone');
    if (phoneInput) {
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "fr",
            utilsScript: "<?php echo dol_buildpath('/reedcrm/vendor/intl-tel-input/js/utils.js', 1); ?>",
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

    // --- AJAX Form Submission (Prevent media loss on validation error) ---
    const mainForm = document.querySelector('.quickcreation-form');
    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            // Only intercept if native HTML5 validation passes
            if (!this.checkValidity()) return;
            
            // Check custom material email regex
            let hasEmailError = false;
            const formEmailInputs = this.querySelectorAll('input[type="email"]');
            formEmailInputs.forEach(function(emailInput) {
                const emailValue = emailInput.value.trim();
                if (emailValue !== '' && !materialEmailRegex.test(emailValue)) {
                    hasEmailError = true;
                    emailInput.classList.add('input-invalid-material');
                    emailInput.reportValidity();
                    emailInput.focus();
                }
            });
            if (hasEmailError) {
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
});
</script>

<?php
