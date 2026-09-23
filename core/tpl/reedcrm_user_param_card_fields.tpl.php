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
 * \file    core/tpl/reedcrm_user_param_card_fields.tpl.php
 * \ingroup reedcrm
 * \brief   Row added to the display setup of a user (user/param_ihm.php) to choose whether the
 *          fields left empty are shown on the cards. The page opens no hook inside its table, so
 *          the row is printed here, in a holder table, and moved into place by the JS : a <tr>
 *          printed outside a table would be dropped by the HTML parser.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $form, $langs, $object;

// The page edits the user held by $object, which is not always the one logged in
$userCardFieldsDisplay = (isset($object->conf->REEDCRM_CARD_FIELDS_DISPLAY) && $object->conf->REEDCRM_CARD_FIELDS_DISPLAY === 'filled') ? 'filled' : '';
$cardFieldsChoices     = ['' => $langs->trans('CardShowAllFields'), 'filled' => $langs->trans('CardHideEmptyFields')];
$isEditMode            = GETPOST('action', 'aZ09') === 'edit';
?>
<table id="reedcrm-user-param-card-fields-holder" hidden>
    <tbody>
    <tr class="oddeven" id="reedcrm-user-param-card-fields">
        <td><?php echo dol_escape_htmltag($langs->trans('CardEmptyFieldsUserParam')); ?></td>
        <td><?php echo dol_escape_htmltag($langs->trans('CardShowAllFields')); ?></td>
        <td class="nowrap" width="20%">
            <?php if ($isEditMode) { ?>
                <input class="oddeven" type="checkbox" name="check_REEDCRM_CARD_FIELDS_DISPLAY" id="check_REEDCRM_CARD_FIELDS_DISPLAY"<?php echo !empty($userCardFieldsDisplay) ? ' checked' : ''; ?>>
                <label for="check_REEDCRM_CARD_FIELDS_DISPLAY"><?php echo dol_escape_htmltag($langs->trans('UsePersonalValue')); ?></label>
            <?php } else { ?>
                <input class="oddeven" type="checkbox" disabled<?php echo !empty($userCardFieldsDisplay) ? ' checked' : ''; ?>>
                <?php echo dol_escape_htmltag($langs->trans('UsePersonalValue')); ?>
            <?php } ?>
        </td>
        <td>
            <?php
            if ($isEditMode) {
                print $form->selectarray('REEDCRM_CARD_FIELDS_DISPLAY', $cardFieldsChoices, $userCardFieldsDisplay, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth250');
            } else {
                print !empty($userCardFieldsDisplay) ? dol_escape_htmltag($cardFieldsChoices[$userCardFieldsDisplay]) : '&nbsp;';
            }
            ?>
        </td>
    </tr>
    </tbody>
</table>
