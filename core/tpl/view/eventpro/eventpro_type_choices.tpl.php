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
 * \file    core/tpl/view/eventpro/eventpro_type_choices.tpl.php
 * \ingroup reedcrm
 * \brief   Event type chips (call, email, meeting, other): a radio per relaunch type instead of the select of
 *          every event type of the agenda. Shared by the direct reminder form and the complete event form.
 *          Expects $typeChoicesSelected, the type code checked (AC_TEL, ...), and optionally $typeChoicesClass,
 *          extra classes of the wrapper.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs;
?>

<div class="reedcrm-reminder-type-choices<?php echo !empty($typeChoicesClass) ? ' ' . dol_escape_htmltag($typeChoicesClass) : ''; ?>">
    <?php foreach (reedcrm_get_relaunch_types() as $typeKey => $type) : ?>
        <label class="reedcrm-reminder-type-choice reedcrm-reminder-type-<?php echo dol_escape_htmltag($typeKey); ?>">
            <input type="radio" name="actioncode" value="<?php echo dol_escape_htmltag($type['actioncode']); ?>"
                <?php echo $typeChoicesSelected === $type['actioncode'] ? 'checked="checked"' : ''; ?>>
            <i class="fas fa-<?php echo dol_escape_htmltag($type['picto']); ?>"></i>
            <span><?php echo dol_escape_htmltag($langs->trans('RelaunchType' . ucfirst($typeKey))); ?></span>
        </label>
    <?php endforeach; ?>
</div>
