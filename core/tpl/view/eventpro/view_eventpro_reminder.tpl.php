<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/view/eventpro/view_eventpro_reminder.tpl.php
 * \ingroup reedcrm
 * \brief   Direct reminder form (#873): what the right-hand button of the relaunch widget opens.
 *          Deliberately short - a reminder is "who calls back, about what, when" and nothing else.
 *          The complete event form stays behind the left-hand button.
 *          Expects $object, $id, $fromType, $form and $permissiontoadd, exactly like the event form.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

// Not loaded by every host page, and a missing date.lib.php only shows up at runtime
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

global $langs, $user;

$reminderProjectId = ($object->element === 'project') ? (int) $object->id : (int) GETPOSTINT('project_id');
$reminderSocId     = ($object->element === 'societe') ? (int) $object->id : (int) ($object->socid ?? 0);
$reminderTypes     = reedcrm_get_relaunch_types();
$selectedType      = GETPOST('actioncode', 'aZ09') ?: 'AC_TEL';
$reminderDate      = GETPOSTISSET('reminder_year') ? dol_mktime(0, 0, 0, GETPOSTINT('reminder_month'), GETPOSTINT('reminder_day'), GETPOSTINT('reminder_year')) : dol_time_plus_duree(dol_now(), 1, 'd');
?>

<form action="<?php echo $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType; ?>" method="POST" class="border" id="addreminderform">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="add_reminder">
    <input type="hidden" name="from_id" value="<?php echo (int) $id; ?>">
    <input type="hidden" name="from_type" value="<?php echo dol_escape_htmltag($fromType); ?>">
    <input type="hidden" name="project_id" value="<?php echo $reminderProjectId; ?>">
    <input type="hidden" name="socid" value="<?php echo $reminderSocId; ?>">
    <?php if (!empty($callListId)) : ?>
        <input type="hidden" name="call_list_id" value="<?php echo (int) $callListId; ?>">
    <?php endif; ?>

    <div id="id-container" class="template-pwa reedcrm-reminder-form">
        <div id="reedcrm-modal-title-data">
            <?php echo img_picto('', 'action', 'class="pictofixedwidth"'); ?>
            <?php echo dol_escape_htmltag($langs->trans('RelaunchAddDirectReminder')); ?>
            <?php if (!empty($object->ref)) : ?>
                &nbsp;&nbsp;<?php echo dol_escape_htmltag($object->ref); ?>
            <?php endif; ?>
        </div>

        <div class="reedcrm-reminder-field">
            <label for="reminder_title">
                <i class="fas fa-pen reedcrm-reminder-label-picto"></i>
                <?php echo $langs->trans('RelaunchWhat'); ?>
            </label>
            <input type="text" id="reminder_title" name="reminder_title" maxlength="255" autofocus
                   placeholder="<?php echo dol_escape_htmltag($langs->trans('RelaunchReminderTitlePlaceholder')); ?>"
                   value="<?php echo dol_escape_htmltag(GETPOSTISSET('reminder_title') ? GETPOST('reminder_title') : ''); ?>">
        </div>

        <div class="reedcrm-reminder-field">
            <label>
                <i class="fas fa-tags reedcrm-reminder-label-picto"></i>
                <?php echo $langs->trans('Type'); ?>
            </label>
            <div class="reedcrm-reminder-type-choices">
                <?php foreach ($reminderTypes as $typeKey => $type) : ?>
                    <label class="reedcrm-reminder-type-choice reedcrm-reminder-type-<?php echo dol_escape_htmltag($typeKey); ?>">
                        <input type="radio" name="actioncode" value="<?php echo dol_escape_htmltag($type['actioncode']); ?>"
                            <?php echo $selectedType === $type['actioncode'] ? 'checked="checked"' : ''; ?>>
                        <i class="fas fa-<?php echo dol_escape_htmltag($type['picto']); ?>"></i>
                        <span><?php echo dol_escape_htmltag($langs->trans('RelaunchType' . ucfirst($typeKey))); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="reedcrm-reminder-field">
            <label>
                <i class="far fa-clock reedcrm-reminder-label-picto"></i>
                <?php echo $langs->trans('RelaunchWhen'); ?>
            </label>
            <div class="reedcrm-reminder-shortcuts">
                <button type="button" class="reedcrm-reminder-shortcut" data-days="1">
                    <i class="fas fa-arrow-right"></i><?php echo $langs->trans('RelaunchInOneDay'); ?>
                </button>
                <button type="button" class="reedcrm-reminder-shortcut" data-days="3">
                    <i class="fas fa-angle-double-right"></i><?php echo $langs->trans('RelaunchInThreeDays'); ?>
                </button>
                <button type="button" class="reedcrm-reminder-shortcut" data-days="7">
                    <i class="fas fa-calendar-week"></i><?php echo $langs->trans('RelaunchInOneWeek'); ?>
                </button>
            </div>
            <div class="reminder-date-row">
                <?php echo $form->selectDate($reminderDate, 'reminder_', 1, 1, 0, 'addreminderform', 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel'); ?>
            </div>
        </div>

        <div class="reedcrm-reminder-field reminder-user-row">
            <label for="reminder_user_id">
                <i class="fas fa-user reedcrm-reminder-label-picto"></i>
                <?php echo $langs->trans('RelaunchWho'); ?>
            </label>
            <?php echo $form->select_dolusers(GETPOSTISSET('reminder_user_id') ? GETPOSTINT('reminder_user_id') : $user->id, 'reminder_user_id', 0, null, 0, '', '', 0, 0, 0, '', 0, '', 'minwidth200 maxwidth300'); ?>
        </div>

        <?php if (!empty($permissiontoadd)) : ?>
            <div class="reedcrm-reminder-submit">
                <button type="submit" class="butAction">
                    <i class="far fa-bell"></i><?php echo $langs->trans('Add'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>
