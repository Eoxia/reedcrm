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
 * \file    ajax/get_element_primary_contact.php
 * \ingroup reedcrm
 * \brief   Returns JSON with primary contact of a propal or project.
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
dol_include_once('/reedcrm/lib/reedcrm_call_list.lib.php');

global $db, $user;

header('Content-Type: application/json');

$elementType = GETPOST('element_type', 'aZ09');
$elementId   = GETPOSTINT('element_id');

if (empty($elementType) || empty($elementId)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

if (!$user->hasRight('reedcrm', 'call_list', 'read')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$element = reedcrm_call_list_fetch_element($db, $elementType, $elementId);
if ($element === null) {
    echo json_encode(['success' => false, 'error' => 'Element not found']);
    exit;
}

$resolved = reedcrm_call_list_resolve_element_contact($db, $elementType, $elementId);

echo json_encode([
    'success'             => true,
    'found'               => (bool) $resolved['found'],
    'contact_id'          => (int) $resolved['contact_id'],
    'lastname'            => (string) $resolved['lastname'],
    'firstname'           => (string) $resolved['firstname'],
    'phone'               => (string) $resolved['phone'],
    'source'              => (string) $resolved['source'],
    'project_phone'       => (string) $resolved['project_phone'],
    'can_save_on_project' => (bool) $resolved['can_save_on_project'],
    'thirdparty_contacts' => $resolved['thirdparty_contacts'],
    'phone_choices'       => $resolved['phone_choices'],
]);
exit;
