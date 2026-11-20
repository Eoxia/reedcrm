<?php
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

/**
 * \file    core/tpl/frontend/reedcrm_pwa_relaunch_form.tpl.php
 * \ingroup reedcrm
 * \brief   Mobile eventPro form: single column layout around the shared tab bodies.
 *          Expects the eventPro page context ($object, $id, $fromType, $currentTab, $actionCode,
 *          $form, $formProject, $formTicket, $permissiontoadd, $callListId).
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs, $user;

$formAction  = $_SERVER['PHP_SELF'] . '?from_id=' . (int) $id . '&from_type=' . $fromType . '&tab=' . $currentTab . '&actioncode=' . $actionCode;
$formAction .= !empty($callListId) ? '&call_list_id=' . (int) $callListId : '';
$thirdpartyId = !empty($object->thirdparty) ? $object->thirdparty->id : 0;
?>
<form action="<?php echo dol_escape_htmltag($formAction); ?>" method="POST" id="addeventform" class="pwa-relaunch-form">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="add_event">
    <input type="hidden" name="from_id" value="<?php echo (int) $id; ?>">
    <input type="hidden" name="from_type" value="<?php echo dol_escape_htmltag($fromType); ?>">
    <input type="hidden" name="tab" value="<?php echo dol_escape_htmltag($currentTab); ?>">
    <input type="hidden" name="project_id" value="<?php echo (int) $object->id; ?>">
    <?php if (!empty($callListId)) : ?>
        <?php /* Posted back so the redirect keeps the call list the user drilled down from */ ?>
        <input type="hidden" name="call_list_id" value="<?php echo (int) $callListId; ?>">
    <?php endif; ?>
    <?php
    // The add_event handler writes these back on the project: posting the current values keeps them
    // untouched (an absent field would be read as an empty one and would wipe the opportunity)
    if (!empty($object->usage_opportunity)) { ?>
        <input type="hidden" name="new_opportunity_amount" value="<?php echo (float) $object->opp_amount; ?>">
        <input type="hidden" name="new_opportunity_percent" value="<?php echo (float) $object->opp_percent; ?>">
        <input type="hidden" name="new_opportunity_status" value="<?php echo (int) $object->opp_status; ?>">
    <?php } ?>

    <?php // Sections titled like the direct reminder form of the same page (view_eventpro_reminder.tpl.php) ?>
    <div class="reedcrm-reminder-field">
        <label for="socid">
            <i class="fas fa-building reedcrm-reminder-label-picto"></i>
            <?php echo $langs->trans('ThirdParty'); ?>
        </label>
        <?php echo $form->select_company($thirdpartyId, 'socid', '', 1, 0, 0, [], 0, ''); ?>
    </div>

    <?php // The add contact form stays in this section: the JS looks for it next to the contact field ?>
    <div class="reedcrm-reminder-field">
        <label for="contactid">
            <i class="fas fa-address-book reedcrm-reminder-label-picto"></i>
            <?php echo $langs->trans('Contact'); ?>
        </label>
        <div class="pwa-relaunch-field reedcrm-contact-field-wrapper">
            <?php echo $form->selectcontacts($thirdpartyId, '', 'contactid', 1, '', '', 0, ''); ?>
            <button type="button" class="reedcrm-add-contact-btn" title="<?php echo dol_escape_htmltag($langs->trans('AddContact')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('AddContact')); ?>">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <div class="reedcrm-add-contact-form" style="display: none;">
            <label for="new_contact_lastname"><?php echo $langs->trans('Lastname'); ?></label>
            <input type="text" id="new_contact_lastname" name="new_contact_lastname">
            <label for="new_contact_firstname"><?php echo $langs->trans('Firstname'); ?></label>
            <input type="text" id="new_contact_firstname" name="new_contact_firstname">
            <label for="new_contact_phone_pro"><?php echo $langs->trans('PhonePro'); ?></label>
            <input type="text" id="new_contact_phone_pro" name="new_contact_phone_pro">
            <label for="new_contact_email"><?php echo $langs->trans('Email'); ?></label>
            <input type="email" id="new_contact_email" name="new_contact_email">
            <div class="reedcrm-add-contact-actions">
                <button type="button" class="reedcrm-add-contact-submit button"><?php echo $langs->trans('Add'); ?></button>
                <button type="button" class="reedcrm-add-contact-cancel button button-cancel"><?php echo $langs->trans('Cancel'); ?></button>
            </div>
        </div>
    </div>

    <div class="reedcrm-reminder-field">
        <label>
            <i class="fas fa-tags reedcrm-reminder-label-picto"></i>
            <?php echo $langs->trans('Type'); ?>
        </label>
        <?php
        // The chips of the direct reminder and of the desktop form rather than the select of every type of the
        // agenda. A default type set in the setup outside of those four falls back on "Other"
        $typeChoicesSelected = in_array($actionCode, array_column(reedcrm_get_relaunch_types(), 'actioncode'), true) ? $actionCode : 'AC_OTH';
        $typeChoicesClass    = '';
        require __DIR__ . '/../view/eventpro/eventpro_type_choices.tpl.php';
        ?>
    </div>

    <div class="reedcrm-reminder-field">
        <label for="event_">
            <i class="far fa-clock reedcrm-reminder-label-picto"></i>
            <?php echo $langs->trans('RelaunchWhen'); ?>
        </label>
        <div class="pwa-relaunch-when">
            <?php echo $form->selectDate(dol_now(), 'event_', 1, 1, 0, 'addeventform', 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel'); ?>
            <?php require __DIR__ . '/../view/eventpro/eventpro_event_duration.tpl.php'; ?>
        </div>
    </div>

    <?php require __DIR__ . '/../view/eventpro/eventpro_tab_content.tpl.php'; ?>
</form>
