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
 * \file    ajax/quick_close_event.php
 * \ingroup reedcrm
 * \brief   Closes a to-do event (progress set to 100%) with an optional comment,
 *          and optionally clones it as a new to-do event postponed by one month or X days.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

global $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['agenda', 'errors', 'reedcrm@reedcrm']);

header('Content-Type: application/json');

$eventID    = GETPOSTINT('event_id');
$comment    = GETPOST('comment', 'alphanohtml');
$reschedule = GETPOSTINT('reschedule');
$delayUnit  = GETPOST('delay_unit', 'aZ09');
$delayValue = GETPOSTINT('delay_value');

if ($eventID <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')]);
    exit;
}

$actionComm = new ActionComm($db);
if ($actionComm->fetch($eventID) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')]);
    exit;
}

// Same rule as comm/action/card.php : all actions, or my actions when I am the author or the owner
$canClose = $user->hasRight('agenda', 'allactions', 'create')
    || (($actionComm->authorid == $user->id || $actionComm->userownerid == $user->id) && $user->hasRight('agenda', 'myactions', 'create'));

if (!$canClose) {
    echo json_encode(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')]);
    exit;
}

// Only a to-do event can be closed, a system event has no progress to set
if ($actionComm->percentage >= 100 || $actionComm->percentage < 0) {
    echo json_encode(['success' => false, 'error' => $langs->trans('QuickCloseEventNotTodo')]);
    exit;
}

// Keep an untouched copy to feed the clone, the closed event gets the comment appended
$originalDatep = $actionComm->datep;
$originalDatef = $actionComm->datef;
$originalNote  = $actionComm->note_private;

$db->begin();

$actionComm->oldcopy    = clone $actionComm;
$actionComm->percentage = 100;

if (dol_strlen($comment) > 0) {
    $stamp = dol_print_date(dol_now(), 'dayhour', 'tzuserrel') . ' - ' . $user->getFullName($langs);
    // The description is rendered with dol_htmlentitiesbr(), appending HTML keeps plain and rich notes readable
    $entry    = '<b>' . dol_escape_htmltag($stamp, 0, 1) . '</b><br>' . dol_nl2br(dol_escape_htmltag($comment, 0, 1));
    $existing = trim($originalNote);

    $actionComm->note_private = ($existing !== '') ? $existing . '<br><br>' . $entry : $entry;
}

if ($actionComm->update($user) <= 0) {
    $db->rollback();
    echo json_encode(['success' => false, 'error' => $actionComm->error]);
    exit;
}

$newEvent = ['id' => 0];

if ($reschedule > 0) {
    // A caller sending nothing falls back on the delay configured for the module
    if ($delayUnit !== 'm' && $delayUnit !== 'd') {
        $delayUnit = getDolGlobalString('REEDCRM_QUICK_CLOSE_DELAY_UNIT', 'm') === 'd' ? 'd' : 'm';
    }
    if ($delayValue < 1) {
        $delayValue = getDolGlobalInt('REEDCRM_QUICK_CLOSE_DELAY_VALUE', 7);
    }

    if ($delayUnit === 'd') {
        $delayValue = max(1, min(3650, $delayValue));
        $newDatep   = dol_time_plus_duree(dol_now(), $delayValue, 'd');
    } else {
        $newDatep = dol_time_plus_duree(dol_now(), 1, 'm');
    }

    $clone = new ActionComm($db);
    if ($clone->fetch($eventID) <= 0) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')]);
        exit;
    }

    // The clone repeats the event as it was, the closure comment belongs to the closed one only
    $clone->percentage   = 0;
    $clone->datep        = $newDatep;
    $clone->datef        = (!empty($originalDatef) && !empty($originalDatep)) ? $newDatep + ($originalDatef - $originalDatep) : null;
    // create() falls back on the deprecated note property when note_private is empty, both must be reset
    $clone->note_private = $originalNote;
    $clone->note         = $originalNote;

    $newID = $clone->createFromClone($user, $clone->socid);
    if ($newID <= 0) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => $clone->error]);
        exit;
    }

    $newEvent = [
        'id'    => $newID,
        'ref'   => $clone->ref,
        'date'  => dol_print_date($newDatep, 'dayhour', 'tzuserrel'),
        'url'   => DOL_URL_ROOT . '/comm/action/card.php?id=' . $newID
    ];
}

$db->commit();

echo json_encode([
    'success'     => true,
    'status_html' => $actionComm->LibStatut(100, 2, 0, $actionComm->datep),
    'new_event'   => $newEvent,
    'message'     => $newEvent['id'] > 0 ? $langs->trans('QuickCloseEventDoneAndRescheduled', $newEvent['date']) : $langs->trans('QuickCloseEventDone')
]);
