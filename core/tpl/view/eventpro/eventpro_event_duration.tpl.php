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
 * \file    core/tpl/view/eventpro/eventpro_event_duration.tpl.php
 * \ingroup reedcrm
 * \brief   Duration of the event, beside its date in the eventPro forms (desktop card and mobile App).
 *          It sets the end of the event and, once the event is done, is logged on the project of the event.
 *          Rendered by view_eventpro_actioncomm.tpl.php and reedcrm_pwa_relaunch_form.tpl.php (inherits their scope).
 *          Expects: $langs. The type of event preselected is read from the request, as the type select does.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

require_once __DIR__ . '/../../../../lib/reedcrm_function.lib.php';

$eventDurations  = reedcrm_get_event_default_durations();
$eventTypeKeys   = [];
foreach (reedcrm_get_relaunch_types() as $relaunchTypeKey => $relaunchType) {
    $eventTypeKeys[$relaunchType['actioncode']] = $relaunchTypeKey;
}
$eventActionCode = GETPOST('actioncode', 'aZ09') ?: getDolGlobalString('REEDCRM_EVENT_TYPE_CODE_VALUE');
$eventDuration   = GETPOSTISSET('event_duration') ? GETPOSTINT('event_duration') : $eventDurations[reedcrm_get_relaunch_type_key((string) $eventActionCode)];
?>
<span class="reedcrm-event-duration" title="<?php echo dol_escape_htmltag($langs->trans('EventDurationHelp')); ?>">
    <i class="fas fa-hourglass-half"></i>
    <input type="number" id="event_duration" name="event_duration" min="0" step="1" value="<?php echo (int) $eventDuration; ?>"
           data-durations="<?php echo dol_escape_htmltag(json_encode($eventDurations)); ?>"
           data-type-keys="<?php echo dol_escape_htmltag(json_encode($eventTypeKeys)); ?>">
    <span class="reedcrm-event-duration-unit"><?php echo $langs->trans('MinuteShort'); ?></span>
</span>
