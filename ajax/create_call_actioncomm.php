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
 * \file    ajax/create_call_actioncomm.php
 * \ingroup reedcrm
 * \brief   Creates an ActionComm (phone call) for a CallListLine via AJAX POST.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once __DIR__ . '/../class/calllist.class.php';
require_once __DIR__ . '/../class/calllistline.class.php';

global $db, $langs, $user;

header('Content-Type: application/json');

if (!getDolGlobalInt('REEDCRM_CALL_LIST_CREATE_ACTIONCOMM')) {
    echo json_encode(['success' => false, 'error' => 'Feature disabled']);
    exit;
}

if (!$user->hasRight('reedcrm', 'call_list', 'write')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$lineId = GETPOSTINT('line_id');

if ($lineId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid line_id']);
    exit;
}

$line = new CallListLine($db);
if ($line->fetch($lineId) <= 0) {
    echo json_encode(['success' => false, 'error' => 'Line not found']);
    exit;
}

$callList = new CallList($db);
$callList->fetch($line->fk_call_list);

$contact = new Contact($db);
if (!empty($line->fk_contact)) {
    $contact->fetch($line->fk_contact);
}

saturne_load_langs();

$actioncomm                  = new ActionComm($db);
$actioncomm->type_code       = 'AC_TEL';
$actioncomm->label           = $langs->transnoentities('CallFrom') . ' ' . dol_escape_htmltag($callList->label);
$actioncomm->datep           = dol_now();
$actioncomm->datef           = dol_now();
$actioncomm->percent         = 100;
$actioncomm->userownerid     = $user->id;
$actioncomm->fk_user_author  = $user->id;

if (!empty($line->fk_contact)) {
    $actioncomm->socpeopleassigned[$line->fk_contact] = ['id' => $line->fk_contact, 'mandatory' => 0];
    if (!empty($contact->socid)) {
        $actioncomm->fk_soc = $contact->socid;
    }
}

if ($line->element_type === 'propal' && !empty($line->element_id)) {
    $actioncomm->fk_element  = $line->element_id;
    $actioncomm->elementtype = 'propal';
} elseif ($line->element_type === 'project' && !empty($line->element_id)) {
    $actioncomm->fk_element  = $line->element_id;
    $actioncomm->elementtype = 'project';
}

$result = $actioncomm->create($user);

if ($result <= 0) {
    echo json_encode(['success' => false, 'error' => $actioncomm->error]);
    exit;
}

echo json_encode(['success' => true]);
exit;
