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
 * \file    ajax/add_to_default_call_list.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint — adds an element to the current user's default call list.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/lib/reedcrm_call_list.lib.php';

global $db, $user, $langs;

$langs->loadLangs(['reedcrm@reedcrm']);

header('Content-Type: application/json');

$elementType = GETPOST('element_type', 'aZ09');
$elementId   = GETPOSTINT('element_id');

if (empty($elementType) || empty($elementId)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

if (!$user->hasRight('reedcrm', 'call_list', 'write')) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$callListId = isset($user->conf->REEDCRM_DEFAULT_CALL_LIST) ? (int) $user->conf->REEDCRM_DEFAULT_CALL_LIST : 0;

if ($callListId <= 0) {
    echo json_encode(['success' => false, 'message' => $langs->transnoentitiesnoconv('DefaultCallListNotFound')]);
    exit;
}

echo json_encode(reedcrm_add_element_to_call_list($db, $user, $callListId, $elementType, $elementId));
exit;
