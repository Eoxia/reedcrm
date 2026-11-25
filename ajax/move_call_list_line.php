<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    ajax/move_call_list_line.php
 * \ingroup reedcrm
 * \brief   Moves a CallListLine one step up or down in its call list via AJAX POST (arrows of the call list card).
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

$lineId    = GETPOSTINT('line_id');
$direction = GETPOST('direction', 'aZ09');
$order     = GETPOST('order', 'array');

if ($lineId <= 0 || (empty($order) && !in_array($direction, ['up', 'down'], true))) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$line = new CallListLine($db);
if ($line->fetch($lineId) <= 0) {
    echo json_encode(['success' => false, 'error' => 'Line not found']);
    exit;
}

if (!empty($order)) {
    $res = $line->reorderInCallList($user, (int) $line->fk_call_list, array_map('intval', $order));
} else {
    $res = $line->moveInCallList($user, (int) $line->fk_call_list, $lineId, $direction);
}

if ($res < 0) {
    echo json_encode(['success' => false, 'error' => $line->error]);
    exit;
}

echo json_encode(['success' => true]);
exit;
