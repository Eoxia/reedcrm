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
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

/**
 * \file    ajax/pocket_action_item.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint to assign a user to a Pocket action item and to turn it into an event.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once __DIR__ . '/../class/pocketactionitem.class.php';
require_once __DIR__ . '/../class/pocketrecording.class.php';

global $db, $langs, $user;

$langs->loadLangs(['reedcrm@reedcrm', 'agenda']);

top_httphead('application/json');

if (!$user->hasRight('reedcrm', 'pocketrecording', 'write')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$subAction  = GETPOST('subaction', 'aZ09');
$actionId   = GETPOSTINT('action_item_id');
$assignedId = GETPOSTINT('fk_user_assign');

$actionItem = new PocketActionItem($db);
if ($actionId <= 0 || $actionItem->fetch($actionId) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')]);
    exit;
}

if ($subAction === 'assign') {
    $actionItem->fk_user_assign = $assignedId > 0 ? $assignedId : null;

    if ($actionItem->update($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $actionItem->error]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($subAction === 'set_text') {
    // Pocket writes the wording, the user owns it afterwards: an empty label is a legitimate
    // value, the fallback only applies to the event that carries the action in the agenda
    // restricthtml encodes the ampersands and the quotes of a plain text, and the card escapes the
    // wording again when it prints it: without the decoding, an edit saved twice would store the
    // HTML source of the previous one
    $label       = htmlspecialchars_decode(GETPOST('label', 'restricthtml'), ENT_QUOTES);
    $description = htmlspecialchars_decode(GETPOST('description', 'restricthtml'), ENT_QUOTES);

    $actionItem->label       = dol_trunc($label, 255, 'right', 'UTF-8', 1);
    $actionItem->description = $description;
    // What the user wrote wins over what Pocket sends on the next synchronisation
    $actionItem->user_edited = 1;

    if ($actionItem->update($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $actionItem->error]);
        exit;
    }

    // The event created from the action carries the same wording, keep the two aligned
    if ($actionItem->fk_actioncomm > 0) {
        $event = new ActionComm($db);
        if ($event->fetch($actionItem->fk_actioncomm) > 0) {
            $event->label        = $actionItem->label ?: $langs->transnoentities('PocketActionItems');
            $event->note_private = $actionItem->description;
            $event->update($user);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($subAction === 'set_due_date') {
    $dueDate = GETPOST('due_date', 'alphanohtml');

    // An emptied field clears the deadline, it is not an error
    $actionItem->due_date    = $dueDate !== '' ? dol_stringtotime($dueDate) : null;
    $actionItem->user_edited = 1;

    if ($actionItem->update($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $actionItem->error]);
        exit;
    }

    // The event created from the action carries the same deadline, keep the two aligned
    if ($actionItem->fk_actioncomm > 0 && !empty($actionItem->due_date)) {
        $event = new ActionComm($db);
        if ($event->fetch($actionItem->fk_actioncomm) > 0) {
            $event->datep = (int) $actionItem->due_date;
            $event->datef = (int) $actionItem->due_date;
            $event->update($user);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($subAction === 'create_event') {
    // One event per action: a second click must land on the existing one, not create a duplicate
    if ($actionItem->fk_actioncomm > 0) {
        echo json_encode([
            'success'  => true,
            'event_id' => (int) $actionItem->fk_actioncomm,
            'url'      => dol_buildpath('/comm/action/card.php', 1) . '?id=' . ((int) $actionItem->fk_actioncomm)
        ]);
        exit;
    }

    $recording = new PocketRecording($db);
    $recording->fetch($actionItem->fk_pocket_recording);

    $ownerId = $actionItem->fk_user_assign > 0 ? (int) $actionItem->fk_user_assign : (int) $user->id;

    $event              = new ActionComm($db);
    $event->type_code   = 'AC_OTH';
    $event->label       = $actionItem->label ?: $langs->transnoentities('PocketActionItems');
    $event->note_private = $actionItem->description;
    $event->datep       = !empty($actionItem->due_date) ? (int) $actionItem->due_date : dol_now();
    $event->datef       = $event->datep;
    // 0 is what Dolibarr reads as "to do", and what reedcrmTodoGetKanbanColumns() puts in the
    // column of that name. -1 is the percentage of an event that does not apply: it used to be set
    // here for the to-do board, which is the one column it never reaches, and it left the action
    // showing "not applicable" and beyond the reach of the quick close
    $event->percentage  = 0;
    $event->userownerid = $ownerId;
    $event->socid       = $recording->fk_soc > 0 ? (int) $recording->fk_soc : 0;
    $event->fk_element  = (int) $recording->id;
    $event->elementtype = 'pocketrecording@reedcrm';

    if ($ownerId != $user->id) {
        $event->userassigned = [$ownerId => ['id' => $ownerId, 'mandatory' => 0]];
    }

    if ($event->create($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $event->error]);
        exit;
    }

    $actionItem->fk_actioncomm = $event->id;
    $actionItem->update($user);

    echo json_encode([
        'success'  => true,
        'event_id' => (int) $event->id,
        'url'      => dol_buildpath('/comm/action/card.php', 1) . '?id=' . ((int) $event->id)
    ]);
    exit;
}

if ($subAction === 'dismiss') {
    // The row is kept and marked instead of being deleted: Pocket still holds the action, and
    // importActionItems() recreates every action it does not find in base. A deleted row would
    // therefore come back at the next refresh of the recording or at the next run of the cron.
    $actionItem->status      = PocketActionItem::STATUS_DISMISSED;
    $actionItem->user_edited = 1;

    if ($actionItem->update($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $actionItem->error]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown subaction']);
exit;
