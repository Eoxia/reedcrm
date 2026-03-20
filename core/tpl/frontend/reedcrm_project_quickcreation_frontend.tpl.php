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

<div id="id-top" class="page-header-tabs" style="max-width: 600px; margin: 0 auto; margin-bottom: 20px; border-radius: 8px; background-color: #ffffff; padding: 0 15px; height: 60px; border-bottom: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center;">
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
            print '<img class="company-logo" src="'.$logoUrl.'" alt="Logo" style="max-height: 40px; max-width: 120px; object-fit: contain;">';
        }
        ?>
    </div>
    <div class="tabs-nav" style="display: flex; height: 100%; gap: 15px;">
        <a href="<?php echo $_SERVER["PHP_SELF"]; ?>" class="tab active" style="display: flex; align-items: center; height: 100%; padding: 0 10px; color: #0f172a; text-decoration: none; font-size: 15px; font-weight: 600; border-bottom: 3px solid #0f172a;">
            <i class="fas fa-share-alt" style="margin-right: 6px; font-size: 14px;"></i> Opportunités
        </a>
        <a href="<?php echo dol_buildpath('/ticket/list.php', 1); ?>" target="_blank" class="tab" style="display: flex; align-items: center; height: 100%; padding: 0 10px; color: #64748b; text-decoration: none; font-size: 15px; font-weight: 600; border-bottom: 3px solid transparent;">
            <i class="fas fa-ticket-alt" style="margin-right: 6px; font-size: 14px;"></i> Ticket
        </a>
    </div>
</div>

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
                print '<input type="' . $inputType . '" id="' . $key . '" name="options_' . $key . '" placeholder="' . $langs->trans($extraFields->attributes['projet']['label'][$key]) . '" value="' . dol_escape_htmltag((GETPOSTISSET($key) ? GETPOST($key) : '')) . '">';
                print '</div>';
            }
        endif; ?>
        </div>

                <!-- Opportunity option -->
        <?php if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) : ?>
            <div style="display: flex; gap: 15px; align-items: center; margin-top: 10px; margin-bottom: 15px;">
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

        <!-- Media & Submit Block -->
        <div class="action-buttons-row">
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
    .iti { width: 100%; }
    
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
    // --- Phone Validation ---
    const phoneInput = document.getElementById('projectphone');
    if (phoneInput) {
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "fr",
            utilsScript: "<?php echo dol_buildpath('/reedcrm/vendor/intl-tel-input/js/utils.js', 1); ?>",
            formatOnDisplay: true,
            nationalMode: false,
            autoPlaceholder: "aggressive",
            preferredCountries: ["fr", "be", "ch", "lu", "mc"]
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
        
        // Bloquer la soumission et afficher la bulle d'erreur native
        const form = emailInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const emailValue = emailInput.value.trim();
                if (emailValue !== '' && !materialEmailRegex.test(emailValue)) {
                    e.preventDefault();
                    emailInput.classList.add('input-invalid-material');
                    emailInput.reportValidity();
                    emailInput.focus();
                }
            });
        }
    });
});
</script>

<?php
