<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/reedcrm_event_quick_close_modal.tpl.php
 * \ingroup reedcrm
 * \brief   Quick close modal for the to-do events listed by show_actions_done(),
 *          and for the event displayed alone on its own card.
 *          Loaded by the printCommonFooter hook on every page displaying one of them.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $langs, $object, $user;

// Postponement preselected in the reschedule block, set in the module configuration
$quickCloseDelayUnit  = getDolGlobalString('REEDCRM_QUICK_CLOSE_DELAY_UNIT', 'm') === 'd' ? 'd' : 'm';
$quickCloseDelayValue = getDolGlobalInt('REEDCRM_QUICK_CLOSE_DELAY_VALUE', 7);

// On the event card the badge sits alone in the banner, the list markers the JS relies on are missing.
// The event is handed over here, a system event (percentage -1) and a done one have no progress to close.
$quickCloseCardID    = 0;
$quickCloseCardLabel = '';
if (is_object($object) && $object->element === 'action' && $object->id > 0
    && $object->percentage >= 0 && $object->percentage < 100) {
    // Same rule as the endpoint : all the events, or mine when I am the author or the owner
    $canQuickClose = $user->hasRight('agenda', 'allactions', 'create')
        || (($object->authorid == $user->id || $object->userownerid == $user->id) && $user->hasRight('agenda', 'myactions', 'create'));

    if ($canQuickClose) {
        $quickCloseCardID    = (int) $object->id;
        $quickCloseCardLabel = (string) $object->label;
    }
}

// The wpeo framework is not loaded on native Dolibarr pages, the modal needs it to display
?>
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/css/temp-framework.css', 1)); ?>">
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1)); ?>">

<div id="reedcrm-quick-close-config"
     data-url="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/quick_close_event.php', 1)); ?>"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-default-unit="<?php echo dol_escape_htmltag($quickCloseDelayUnit); ?>"
     data-default-days="<?php echo (int) $quickCloseDelayValue; ?>"
     data-card-event-id="<?php echo $quickCloseCardID; ?>"
     data-card-event-label="<?php echo dol_escape_htmltag($quickCloseCardLabel); ?>"
     data-trans-tooltip="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventTooltip')); ?>"
     data-trans-error="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventError')); ?>"
     data-trans-date-required="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventDateRequired')); ?>"></div>

<div class="wpeo-modal modal-reedcrm-quick-close" id="reedcrm-quick-close-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventTitle')); ?></h2>
            <div class="modal-close"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <p class="reedcrm-quick-close-event"></p>

            <label class="reedcrm-quick-close-label" for="reedcrm-quick-close-comment"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventDescription')); ?></label>
            <textarea id="reedcrm-quick-close-comment" class="reedcrm-quick-close-comment" rows="4" placeholder="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventDescriptionPlaceholder')); ?>"></textarea>

            <label class="reedcrm-quick-close-toggle">
                <input type="checkbox" id="reedcrm-quick-close-reschedule">
                <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventReschedule')); ?></span>
            </label>

            <div class="reedcrm-quick-close-delay" id="reedcrm-quick-close-delay">
                <label class="reedcrm-quick-close-label" for="reedcrm-quick-close-new-label"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventNewLabel')); ?></label>
                <input type="text" id="reedcrm-quick-close-new-label" class="reedcrm-quick-close-new-label" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventNewLabelPlaceholder')); ?>">

                <label class="reedcrm-quick-close-delay-choice">
                    <input type="radio" name="reedcrm-quick-close-delay-unit" value="m"<?php echo $quickCloseDelayUnit === 'm' ? ' checked' : ''; ?>>
                    <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventInOneMonth')); ?></span>
                </label>
                <label class="reedcrm-quick-close-delay-choice">
                    <input type="radio" name="reedcrm-quick-close-delay-unit" value="d"<?php echo $quickCloseDelayUnit === 'd' ? ' checked' : ''; ?>>
                    <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventInDays')); ?></span>
                    <input type="number" id="reedcrm-quick-close-delay-value" class="reedcrm-quick-close-delay-value" value="<?php echo (int) $quickCloseDelayValue; ?>" min="1" max="3650">
                    <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventInDaysSuffix')); ?></span>
                </label>
                <?php // A day picked by hand, for a relaunch that has to fall on a given date ?>
                <label class="reedcrm-quick-close-delay-choice">
                    <input type="radio" name="reedcrm-quick-close-delay-unit" value="date">
                    <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventOnDate')); ?></span>
                    <input type="date" id="reedcrm-quick-close-delay-date" class="reedcrm-quick-close-delay-date" min="<?php echo dol_print_date(dol_now(), '%Y-%m-%d'); ?>">
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="wpeo-button button-grey reedcrm-quick-close-cancel"><?php echo dol_escape_htmltag($langs->trans('Cancel')); ?></button>
            <button type="button" class="wpeo-button button-blue reedcrm-quick-close-confirm"><i class="fas fa-check"></i>&nbsp;<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventConfirm')); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript" src="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/js/modules/event_quick_close.js', 1)); ?>"></script>
