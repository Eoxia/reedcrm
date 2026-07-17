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
 * \file    ajax/set_default_call_list.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint — sets a call list as the favorite call list of the current user.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

dol_include_once('/reedcrm/class/calllist.class.php');
dol_include_once('/reedcrm/lib/reedcrm_call_list.lib.php');

global $db, $user, $langs;

$langs->loadLangs(['reedcrm@reedcrm']);

header('Content-Type: application/json');

$callListId = GETPOSTINT('call_list_id');

if ($callListId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

if (!$user->hasRight('reedcrm', 'call_list', 'read')) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$callList = new CallList($db);
if ($callList->fetch($callListId) <= 0) {
    echo json_encode(['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListNotFound')]);
    exit;
}

// An archived list would make the star widget of element cards fail on every click
if ($callList->status == CallList::STATUS_ARCHIVED) {
    echo json_encode(['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListCannotAddToArchivedList')]);
    exit;
}

// Own favorite only: a personal preference, so no right beyond reading the list is needed
if (reedcrm_set_user_default_call_list($db, $user, $callListId) <= 0) {
    echo json_encode(['success' => false, 'message' => $langs->transnoentitiesnoconv('DefaultCallListSetError')]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $langs->transnoentitiesnoconv('DefaultCallListSet', $callList->ref),
]);
exit;
