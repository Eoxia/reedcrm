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
 * \file    core/tpl/view/eventpro/eventpro_add_project.tpl.php
 * \ingroup reedcrm
 * \brief   "+" button and inline creation form of a project, printed inside a
 *          .reedcrm-project-field-wrapper right after its project select (eventPro form and ticket tab).
 *          Expects $eventProProjectFormKey (keeps the ids unique: both forms can be on the page at once)
 *          and $eventProProjectSocidField (name of the third party select the new project belongs to).
 *          The inputs have no name: they must not be posted with the eventPro form.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs, $user;

if (!isModEnabled('project') || !$user->hasRight('projet', 'creer')) {
    return;
}

$langs->load('projects');
?>
<button type="button" class="reedcrm-add-project-btn" title="<?php echo dol_escape_htmltag($langs->trans('AddProject')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('AddProject')); ?>">
    <i class="fas fa-plus"></i>
</button>
<div class="reedcrm-add-project-form" data-socid-field="<?php echo dol_escape_htmltag($eventProProjectSocidField); ?>" data-error-required="<?php echo dol_escape_htmltag($langs->transnoentities('ErrorFieldRequired', $langs->transnoentities('ProjectLabel'))); ?>">
    <div class="wpeo-grid grid-2">
        <div>
            <label for="new_project_title_<?php echo $eventProProjectFormKey; ?>"><?php echo $langs->trans('ProjectLabel'); ?></label>
            <input type="text" id="new_project_title_<?php echo $eventProProjectFormKey; ?>" class="reedcrm-add-project-title" maxlength="255">
        </div>
        <?php if (getDolGlobalInt('PROJECT_USE_OPPORTUNITIES')) : ?>
            <div>
                <label for="new_project_amount_<?php echo $eventProProjectFormKey; ?>"><?php echo $langs->trans('OpportunityAmount'); ?></label>
                <input type="text" id="new_project_amount_<?php echo $eventProProjectFormKey; ?>" class="reedcrm-add-project-amount" inputmode="decimal">
            </div>
        <?php endif; ?>
    </div>
    <div class="reedcrm-add-project-actions">
        <button type="button" class="reedcrm-add-project-submit button"><?php echo $langs->trans('Add'); ?></button>
        <button type="button" class="reedcrm-add-project-cancel button button-cancel"><?php echo $langs->trans('Cancel'); ?></button>
    </div>
</div>
