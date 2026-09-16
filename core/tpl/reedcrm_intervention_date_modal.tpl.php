<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/reedcrm_intervention_date_modal.tpl.php
 * \ingroup reedcrm
 * \brief   Modal holding the intervention dates of one service line.
 *          Loaded by the printCommonFooter hook on the proposal card, its content is fetched line by line.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $langs, $object, $user;

// The dates live beside the proposal, not inside it : a signed or billed proposal is exactly the one
// whose interventions are planned, so only the right gates the writing.
$interventionCanWrite = $user->hasRight('propal', 'creer');

// The modal is also loaded by the calendar page, where no proposal is being displayed
$interventionElementID = (isset($object) && is_object($object) && $object->element === 'propal') ? (int) $object->id : 0;

// The wpeo framework is not loaded on native Dolibarr pages, the modal needs it to display.
// A ReedCRM page already carries the assets and tells so, to avoid loading them twice.
$interventionModalLoadAssets = !isset($interventionModalLoadAssets) || $interventionModalLoadAssets;
?>
<?php if ($interventionModalLoadAssets) { ?>
    <link rel="stylesheet" href="<?php echo dol_escape_htmltag(saturne_asset_full_url('/reedcrm/css/temp-framework.css')); ?>">
    <link rel="stylesheet" href="<?php echo dol_escape_htmltag(saturne_asset_full_url('/reedcrm/css/reedcrm.min.css')); ?>">
<?php } ?>

<div id="reedcrm-intervention-date-config"
     data-get-url="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/get_intervention_dates.php', 1)); ?>"
     data-save-url="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/save_intervention_dates.php', 1)); ?>"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-element-type="propal"
     data-element-id="<?php echo $interventionElementID; ?>"
     data-can-write="<?php echo $interventionCanWrite ? 1 : 0; ?>"
     data-trans-error="<?php echo dol_escape_htmltag($langs->trans('InterventionDateSaveError')); ?>"
     data-trans-saved="<?php echo dol_escape_htmltag($langs->trans('InterventionDateSaved')); ?>"></div>

<div class="wpeo-modal modal-reedcrm-intervention-date" id="reedcrm-intervention-date-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('InterventionDates')); ?></h2>
            <div class="modal-close"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <p class="reedcrm-intervention-date-line"></p>
            <form id="reedcrm-intervention-date-form" class="reedcrm-intervention-date-form" onsubmit="return false;">
                <div id="reedcrm-intervention-date-rows" class="reedcrm-intervention-date-rows"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="wpeo-button button-grey reedcrm-intervention-date-cancel"><?php echo dol_escape_htmltag($langs->trans('Cancel')); ?></button>
            <?php if ($interventionCanWrite) { ?>
                <button type="button" class="wpeo-button button-blue reedcrm-intervention-date-confirm"><i class="fas fa-check"></i>&nbsp;<?php echo dol_escape_htmltag($langs->trans('Save')); ?></button>
            <?php } ?>
        </div>
    </div>
</div>

<?php if ($interventionModalLoadAssets) { ?>
    <script type="text/javascript" src="<?php echo dol_escape_htmltag(saturne_asset_full_url('/reedcrm/js/modules/intervention_date.js')); ?>"></script>
<?php } ?>
