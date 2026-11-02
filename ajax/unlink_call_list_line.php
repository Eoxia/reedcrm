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
 * \file    ajax/unlink_call_list_line.php
 * \ingroup reedcrm
 * \brief   Removes a CallListLine from its call list via AJAX POST (PWA call list).
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

// Same right as the line bin of the call list card
if (!$user->hasRight('reedcrm', 'call_list', 'delete')) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

$lineId = GETPOSTINT('line_id');

$line     = new CallListLine($db);
$callList = new CallList($db);
if ($lineId <= 0 || $line->fetch($lineId) <= 0 || $callList->fetch($line->fk_call_list) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorRecordNotFound')]);
    exit;
}

if (!reedcrm_call_list_user_can_read($user, $callList)) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

// Soft delete, like the call list card: the line leaves the list, its media and events stay
if ($line->delete($user) < 0) {
    echo json_encode(['success' => false, 'error' => $line->error]);
    exit;
}

echo json_encode(['success' => true]);
exit;
