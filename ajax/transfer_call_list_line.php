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
 * \file    ajax/transfer_call_list_line.php
 * \ingroup reedcrm
 * \brief   Copies or moves a CallListLine to another call list via AJAX POST (App call list).
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once __DIR__ . '/../class/calllist.class.php';
require_once __DIR__ . '/../class/calllistline.class.php';
require_once __DIR__ . '/../lib/reedcrm_call_list.lib.php';

global $db, $langs, $user;

saturne_load_langs();

header('Content-Type: application/json');

$lineId       = GETPOSTINT('line_id');
$targetListId = GETPOSTINT('target_list_id');
$move         = GETPOST('mode', 'aZ09') === 'move';

// The rights of the call list card: writing to copy, and deleting as well to move, as the line leaves its list
if (!$user->hasRight('reedcrm', 'call_list', 'write') || ($move && !$user->hasRight('reedcrm', 'call_list', 'delete'))) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

$line = new CallListLine($db);
if ($lineId <= 0 || $line->fetch($lineId) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorRecordNotFound')]);
    exit;
}

$transfer = reedcrm_call_list_transfer_lines($db, $user, (int) $line->fk_call_list, [$lineId], $targetListId, $move);
if (!empty($transfer['errors'])) {
    echo json_encode(['success' => false, 'error' => implode(' ', array_unique($transfer['errors']))]);
    exit;
}

$targetLabel = $transfer['target'] !== null ? (string) $transfer['target']->label : '';
if ($transfer['duplicates'] > 0) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('CallListLineAlreadyInList', $targetLabel)]);
    exit;
}

echo json_encode([
    'success' => $transfer['done'] > 0,
    'moved'   => $move,
    'message' => $langs->transnoentities($move ? 'CallListLineMovedTo' : 'CallListLineCopiedTo', $targetLabel),
    'error'   => $transfer['done'] > 0 ? '' : $langs->transnoentities('ErrorCallListLineTransfer'),
]);
exit;
