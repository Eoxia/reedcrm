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
 * \file    ajax/update_call_list_line_status.php
 * \ingroup reedcrm
 * \brief   Updates the status of a CallListLine via AJAX POST.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once __DIR__ . '/../class/calllistline.class.php';

global $db, $user;

header('Content-Type: application/json');

if (!$user->hasRight('reedcrm', 'call_list', 'write')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$lineId = GETPOSTINT('line_id');
$status = GETPOSTINT('status');

if ($lineId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid line_id']);
    exit;
}

$validStatuses = [
    CallListLine::STATUS_TO_CALL,
    CallListLine::STATUS_CALLED,
    CallListLine::STATUS_NO_ANSWER,
    CallListLine::STATUS_CALLBACK,
];

if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

$line = new CallListLine($db);
if ($line->fetch($lineId) <= 0) {
    echo json_encode(['success' => false, 'error' => 'Line not found']);
    exit;
}

$line->status = $status;
$result       = $line->update($user);

if ($result < 0) {
    echo json_encode(['success' => false, 'error' => $line->error]);
    exit;
}

// Follow-up records (agenda event / commercial task), driven by the PWA admin toggles.
// Skipped when the status goes back to "to call" (toggle off on the active button).
$warnings = [];
if ($status !== CallListLine::STATUS_TO_CALL) {
    require_once __DIR__ . '/../lib/reedcrm_call_list.lib.php';
    $warnings = reedcrm_call_list_line_record_status_change($db, $user, $line, $status);
}

echo json_encode(['success' => true, 'warnings' => $warnings]);
exit;
