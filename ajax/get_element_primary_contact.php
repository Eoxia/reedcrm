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

$object = null;

if ($elementType === 'propal' && isModEnabled('propale')) {
    require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
    $object = new Propal($db);
} elseif ($elementType === 'project' && isModEnabled('projet')) {
    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
    $object = new Project($db);
}

if ($object === null) {
    echo json_encode(['success' => false, 'error' => 'Unsupported element type']);
    exit;
}

$result = $object->fetch($elementId);
if ($result <= 0) {
    echo json_encode(['success' => false, 'error' => 'Element not found']);
    exit;
}

$allContacts = $object->liste_contact(-1, 'external');
$contacts    = array_filter(
    is_array($allContacts) ? $allContacts : [],
    static function ($c) { return $c['code'] !== 'PROJECTADDRESS'; }
);

$object->fetch_optionals();
$contactId = 0;

// Priority 1: First external contact (excluding PROJECTADDRESS code)
if (!empty($contacts)) {
    $firstContact = reset($contacts);
    $contactId    = (int) $firstContact['id'];
}
// Priority 2: Project's extrafield 'projectaddress'
elseif ($elementType === 'project' && !empty($object->array_options['options_projectaddress'])) {
    $contactId = (int) $object->array_options['options_projectaddress'];
}

if ($contactId > 0) {
    $contact = new Contact($db);
    $contact->fetch($contactId);

    echo json_encode([
        'success'    => true,
        'contact_id' => $contactId,
        'lastname'   => $contact->lastname,
        'firstname'  => $contact->firstname,
        'phone'      => $contact->phone_pro ?: $contact->phone_mobile ?: '',
    ]);
} else {
    // Priority 3: Fallback to Thirdparty (Societe)
    if ($object->socid > 0) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $soc = new Societe($db);
        $soc->fetch($object->socid);
        echo json_encode([
            'success'    => true,
            'contact_id' => 0,
            'lastname'   => $soc->name,
            'firstname'  => '',
            'phone'      => $soc->phone,
        ]);
    } else {
        echo json_encode(['success' => true, 'contact_id' => 0, 'lastname' => '', 'firstname' => '', 'phone' => '']);
    }
}
exit;
