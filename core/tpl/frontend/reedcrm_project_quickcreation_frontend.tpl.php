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

    /* Liseret (Border) manquant pour les composants natifs de Dolibarr (Select2 et Autocomplete) dans la PWA */
    .quickcreation-form-container .select2-container {
        width: 100% !important;
    }
    .quickcreation-form-container .select2-container--default .select2-selection--single {
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        height: 38px !important;
        display: flex !important;
        align-items: center !important;
        background: #fff !important;
    }
    .quickcreation-form-container .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal !important;
        padding-left: 8px !important;
    }
    .quickcreation-form-container .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100% !important;
    }
    .quickcreation-form-container input.ui-autocomplete-input,
    .quickcreation-form-container select {
        padding: 8px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        background: #fff !important;
        box-sizing: border-box !important;
        height: 38px !important;
    }
    .geoloc-address-link {
        display: none;
        flex-direction: column;
        text-align: right;
        max-width: 250px;
        background: #f8fafc;
        padding: 4px 10px;
        border-radius: 6px;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        transition: background 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .geoloc-address-link.is-visible {
        display: flex !important;
    }
    .geoloc-address-link:hover {
        background: #e2e8f0 !important;
    }
    /* Style custom pour Categories */
    .category-wrapper {
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #fff;
        padding: 4px 8px;
        display: flex;
        align-items: center;
        min-height: 38px;
        box-sizing: border-box;
    }
    .category-select-container {
        display: flex;
        align-items: center;
        width: 100%;
        gap: 8px;
    }
    .category-select-container > span.fa-tag {
        color: #0f172a;
        font-size: 16px;
    }
    .category-select-container > span.multiselectarraycategories {
        flex: 1;
        min-width: 0;
        display: block;
    }
    .category-select-container .select2-container {
        width: 100% !important;
    }
    .category-select-container .select2-container--default .select2-selection--multiple,
    .category-select-container .select2-container--default .select2-selection--single {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        box-shadow: none !important;
    }
    .category-select-container .select2-container--default.select2-container--focus .select2-selection--multiple {
        border: none !important;
    }
    .category-select-container > a {
        margin-left: auto;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .category-select-container > a span.fa-plus-circle {
        font-size: 18px;
        color: #0f172a;
    }
</style>

<div id="id-container" class="page-content">
    <?php print saturne_show_notice('', '', 'error', 'notice-infos', false, true, '', ['Error' => $langs->transnoentities('Error')]); ?>

    <div class="quickcreation-form-container" style="position: relative;">
        <!-- Geoloc Wrapper (Will be moved to PWA header left of avatar via JS) -->
        <div id="geoloc-header-wrapper" style="display: none; align-items: center; gap: 10px; margin-right: 15px;">
            <a id="current-address-block" class="geoloc-address-link" href="javascript:void(0);">
                <div id="current-address-text" style="font-size: 11px; color: #34495e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; font-weight: 500;"><?php echo $langs->trans('DetectingLocation'); ?>…</div>
                <div id="current-address-coords" style="font-size: 9px; color: #94a3b8; line-height: 1.2;"></div>
            </a>
            <div id="geoloc-top-right-icon" style="cursor: pointer; display: flex; align-items: center; gap: 5px;" title="Cliquez pour afficher/masquer l'adresse" data-action="toggle-geoloc-address">
                <span id="current-address-ko" style="display: none; color: #e74c3c; font-weight: bold; font-size: 14px;">KO</span>
                <i id="current-address-icon" class="fas fa-circle-notch fa-spin" style="font-size: 20px; color: #3498db;"></i>
            </div>
        </div>


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

        <!-- Thirdparty -->
        <div class="form-group">
            <?php
            $events = array(array('method'=>'getContacts', 'url'=>dol_buildpath('/core/ajax/contacts.php', 1), 'htmlname'=>'contactid', 'params'=>array('add-customer-contact'=>'disabled')));
            print $form->select_company(GETPOST('socid', 'int'), 'socid', '', 'SelectThirdParty', 0, 0, $events, 0, 'widthcentpercent');
            ?>
        </div>

        <!-- Contact -->
        <div class="form-group" id="contact-wrapper" style="display: none;">
            <select name="contactid" id="contactid" class="flat widthcentpercent" data-placeholder="Contact/Adresse">
                <option value="-1"></option>
            </select>
        </div>

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
            <div class="opp-row" style="display: flex; flex-direction: column; gap: 15px; margin-top: 0; margin-bottom: 0;">
                <!-- 100% Slider -->
                <div class="form-group" style="width: 100%; margin-bottom: 0;">
                    <div class="opp-percent" style="display: flex; align-items: center;">
                        <span style="font-size: 22px; margin-right: 8px;">🥵</span>
                        <div style="position: relative; flex: 1; display: flex; align-items: center; --val: <?php echo empty($project->opp_percent) ? '0' : $project->opp_percent; ?>;">
                            <input type="range" class="range" name="opp_percent" id="opp_percent" min="0" max="100" step="10" value="<?php echo empty($project->opp_percent) ? '0' : $project->opp_percent; ?>" style="width: 100%; cursor: pointer; margin: 0; background: transparent; outline: none;">
                            <div class="opp_percent-value"><?php echo empty($project->opp_percent) ? '0%' : $project->opp_percent . '%'; ?></div>
                        </div>
                        <span style="font-size: 22px; margin-left: 8px;">🤑</span>
                    </div>
                </div>

                <!-- 100% Amount -->
                <?php if ($conf->global->REEDCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) : ?>
                <div class="form-group" style="width: 100%; margin-bottom: 0;">
                    <div class="input-with-icon" style="margin-top: 0; line-height: 1;">
                        <span class="input-icon">€</span>
                        <input type="text" inputmode="decimal" name="opp_amount" id="opp_amount" placeholder="Montant" value="<?php echo dol_escape_htmltag((GETPOSTISSET('opp_amount') ? GETPOST('opp_amount', 'int') : '')); ?>" style="width: 100%;">
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Commercial -->
        <?php if ($conf->global->REEDCRM_PROJECT_COMMERCIAL_VISIBLE > 0 && empty($conf->global->REEDCRM_PROJECT_COMMERCIAL_INHERIT)) : ?>
            <div class="form-group" style="margin-top: 15px;">
                <div class="category-wrapper">
                    <div style="flex: 1; min-width: 0;" class="category-select-container">
                        <?php
                        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
                        if (!isset($userList) || empty($userList)) {
                            $userList = $form->select_dolusers('', '', 0, null, 0, '', '', 0, 0, 0, '((u.statut:=:1) AND (u.employee:=:1))', 0, '', '', 0, 1);
                        }
                        print $form->multiselectarray('commercial_project', $userList, GETPOST('commercial_project', 'array'), '', 0, 'widthcentpercent', 0, 0, 'widthcentpercent');
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Visual Section Divider Removed & Relocated Below -->

        <!-- Tags / Categories -->
        <?php if (isModEnabled('categorie')) : ?>
            <div class="form-group" style="margin-top: 15px;">
                <div class="category-wrapper">
                    <div style="flex: 1; min-width: 0;" class="category-select-container">
                        <?php print $form->selectCategories(Categorie::TYPE_PROJECT, 'categories'); ?>
                    </div>
                </div>
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

        <!-- Current address display removed from here (moved to header) -->

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
<?php
// Data attributes for JS — lang is passed via HTML, no inline script needed
$defaultLang = substr($langs->defaultlang, 0, 2);
$utilsPath   = dol_buildpath('/reedcrm/js/intl-tel-input/js/utils.js', 1);
?>
<div id="reedcrm-quickcreation-data"
     data-lang="<?php echo dol_escape_htmltag($defaultLang); ?>"
     data-utils-path="<?php echo dol_escape_htmltag($utilsPath); ?>"
     style="display:none;"></div>
<?php
