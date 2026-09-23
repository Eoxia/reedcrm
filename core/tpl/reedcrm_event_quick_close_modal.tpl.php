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

// The four relaunch types the module works with, shared with the relaunch chips, and
// reedcrm_asset_full_url() used by the assets printed below: without a version, the same
// stylesheet is a second cache entry the browser may serve stale.
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';

// Postponement preselected in the reschedule block, set in the module configuration
$quickCloseDelayUnit   = getDolGlobalString('REEDCRM_QUICK_CLOSE_DELAY_UNIT', 'm') === 'd' ? 'd' : 'm';
$quickCloseDelayValue  = getDolGlobalInt('REEDCRM_QUICK_CLOSE_DELAY_VALUE', 7);
$quickCloseDelayMonths = max(1, getDolGlobalInt('REEDCRM_QUICK_CLOSE_DELAY_MONTHS', 1));

// The type of the reminder is picked either on four pills or in a drop-down list, at the choice
// of the module configuration: the pills read faster, the list takes less room in the modal
$quickCloseTypeDisplay = getDolGlobalString('REEDCRM_QUICK_CLOSE_TYPE_DISPLAY', 'buttons') === 'select' ? 'select' : 'buttons';

// On the event card the badge sits alone in the banner, the list markers the JS relies on are missing.
// The event is handed over here, a system event (percentage -1) and a done one have no progress to close.
$quickCloseCardID    = 0;
$quickCloseCardLabel = '';
$quickCloseCardType  = '';
if (is_object($object) && $object->element === 'action' && $object->id > 0
    && $object->percentage >= 0 && $object->percentage < 100) {
    // Same rule as the endpoint : all the events, or mine when I am the author or the owner
    $canQuickClose = $user->hasRight('agenda', 'allactions', 'create')
        || (($object->authorid == $user->id || $object->userownerid == $user->id) && $user->hasRight('agenda', 'myactions', 'create'));

    if ($canQuickClose) {
        $quickCloseCardID    = (int) $object->id;
        $quickCloseCardLabel = (string) $object->label;
        $quickCloseCardType  = (string) $object->type_code;
    }
}

// The wpeo framework is not loaded on native Dolibarr pages, the modal needs it to display
?>
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(reedcrm_asset_full_url('/reedcrm/css/temp-framework.css')); ?>">
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(reedcrm_asset_full_url('/reedcrm/css/reedcrm.min.css')); ?>">

<div id="reedcrm-quick-close-config"
     data-url="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/quick_close_event.php', 1)); ?>"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-default-unit="<?php echo dol_escape_htmltag($quickCloseDelayUnit); ?>"
     data-default-days="<?php echo (int) $quickCloseDelayValue; ?>"
     data-default-months="<?php echo (int) $quickCloseDelayMonths; ?>"
     data-card-event-id="<?php echo $quickCloseCardID; ?>"
     data-card-event-label="<?php echo dol_escape_htmltag($quickCloseCardLabel); ?>"
     data-card-event-type="<?php echo dol_escape_htmltag($quickCloseCardType); ?>"
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
            <?php // The name of the event being closed both recalls which one it is and stays editable:
                  // a name is often only settled once the call is over ?>
            <label class="reedcrm-quick-close-label" for="reedcrm-quick-close-event-label"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventCurrentLabel')); ?></label>
            <input type="text" id="reedcrm-quick-close-event-label" class="reedcrm-quick-close-event-label" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventCurrentLabelPlaceholder')); ?>">

            <label class="reedcrm-quick-close-label" for="reedcrm-quick-close-comment"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventDescription')); ?></label>
            <textarea id="reedcrm-quick-close-comment" class="reedcrm-quick-close-comment" rows="4" placeholder="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventDescriptionPlaceholder')); ?>"></textarea>

            <label class="reedcrm-quick-close-toggle">
                <input type="checkbox" id="reedcrm-quick-close-reschedule">
                <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventReschedule')); ?></span>
            </label>

            <div class="reedcrm-quick-close-delay" id="reedcrm-quick-close-delay">
                <label class="reedcrm-quick-close-label" for="reedcrm-quick-close-new-label"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventNewLabel')); ?></label>
                <input type="text" id="reedcrm-quick-close-new-label" class="reedcrm-quick-close-new-label" maxlength="128" placeholder="<?php echo dol_escape_htmltag($langs->trans('QuickCloseEventNewLabelPlaceholder')); ?>">

                <?php // Type of the reminder being raised. Nothing is preselected when the page cannot
                      // tell the type of the closed event: the reminder then simply repeats it ?>
                <span class="reedcrm-quick-close-label"><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventNewType')); ?></span>
                <?php if ($quickCloseTypeDisplay === 'select') : ?>
                    <?php // The empty choice is what keeps the type of the closed event, which is also
                          // what a list row falls back on since it cannot tell that type ?>
                    <select id="reedcrm-quick-close-new-type-select" class="reedcrm-quick-close-type-select">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventKeepType')); ?></option>
                        <?php foreach (reedcrm_get_relaunch_types() as $typeKey => $type) : ?>
                            <option value="<?php echo dol_escape_htmltag($type['actioncode']); ?>"><?php echo dol_escape_htmltag($langs->trans('RelaunchType' . ucfirst($typeKey))); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <div class="reedcrm-quick-close-type-choices">
                        <?php foreach (reedcrm_get_relaunch_types() as $typeKey => $type) : ?>
                            <label class="reedcrm-quick-close-type-choice reedcrm-quick-close-type-<?php echo dol_escape_htmltag($typeKey); ?>">
                                <input type="radio" name="reedcrm-quick-close-new-type" value="<?php echo dol_escape_htmltag($type['actioncode']); ?>">
                                <i class="fas fa-<?php echo dol_escape_htmltag($type['picto']); ?>"></i>
                                <span><?php echo dol_escape_htmltag($langs->trans('RelaunchType' . ucfirst($typeKey))); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label class="reedcrm-quick-close-delay-choice">
                    <input type="radio" name="reedcrm-quick-close-delay-unit" value="m"<?php echo $quickCloseDelayUnit === 'm' ? ' checked' : ''; ?>>
                    <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventInMonths')); ?></span>
                    <input type="number" id="reedcrm-quick-close-delay-months" class="reedcrm-quick-close-delay-value" value="<?php echo (int) $quickCloseDelayMonths; ?>" min="1" max="120">
                    <span><?php echo dol_escape_htmltag($langs->trans('QuickCloseEventInMonthsSuffix')); ?></span>
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

<script type="text/javascript" src="<?php echo dol_escape_htmltag(reedcrm_asset_full_url('/reedcrm/js/modules/event_quick_close.js')); ?>"></script>
