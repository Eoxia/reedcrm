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
 * \file    core/tpl/frontend/reedcrm_project_quickcreation_reminder_frontend.tpl.php
 * \ingroup reedcrm
 * \brief   Template page for the "remind me later" block of the quick creation frontend.
 *          Rendered by reedcrm_project_quickcreation_frontend.tpl.php (inherits its scope).
 *          The presets and the minimum date are computed by js/modules/quickcreation.js, in the device timezone.
 */

/**
 * The following vars must be defined :
 * Global   : $langs
 * Variable : $permissionToAddProject
 */

// Protection to avoid direct call of template
if (!$permissionToAddProject) {
    exit;
}

$reminderChecked = GETPOSTINT('add_reminder') > 0;
$reminderPresets = [
    ['days' => 1, 'months' => 0, 'label' => 'Tomorrow'],
    ['days' => 3, 'months' => 0, 'label' => 'QuickCreationReminderIn3Days'],
    ['days' => 7, 'months' => 0, 'label' => 'QuickCreationReminderIn1Week'],
    ['days' => 0, 'months' => 1, 'label' => 'QuickCreationReminderIn1Month'],
]; ?>

<div class="form-group quickcreation-reminder">
    <label class="quickcreation-reminder__toggle" for="add_reminder">
        <input type="checkbox" id="add_reminder" name="add_reminder" value="1"<?php echo $reminderChecked ? ' checked' : ''; ?>>
        <i class="far fa-bell"></i>
        <span><?php echo $langs->trans('QuickCreationRemindMeLater'); ?></span>
    </label>

    <div class="quickcreation-reminder__fields<?php echo $reminderChecked ? ' is-open' : ''; ?>">
        <div class="quickcreation-reminder__presets">
            <?php foreach ($reminderPresets as $reminderPreset) : ?>
                <button type="button" class="quickcreation-reminder__preset" data-days="<?php echo $reminderPreset['days']; ?>" data-months="<?php echo $reminderPreset['months']; ?>"><?php echo $langs->trans($reminderPreset['label']); ?></button>
            <?php endforeach; ?>
        </div>
        <input type="datetime-local" id="reminder_date" name="reminder_date" aria-label="<?php echo dol_escape_htmltag($langs->trans('QuickCreationReminderDate')); ?>" value="<?php echo dol_escape_htmltag(GETPOST('reminder_date', 'alphanohtml')); ?>"<?php echo $reminderChecked ? ' required' : ''; ?>>
        <input type="text" id="reminder_title" name="reminder_title" maxlength="128" placeholder="<?php echo dol_escape_htmltag($langs->trans('QuickCreationReminderTitlePlaceholder')); ?>" value="<?php echo dol_escape_htmltag(GETPOST('reminder_title', 'alphanohtml')); ?>">
    </div>
</div>
