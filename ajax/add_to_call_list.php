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
 * \file    ajax/add_to_call_list.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint — adds an element to a call list from a card widget.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllist.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllistline.class.php';

global $db, $user, $langs;

$langs->loadLangs(['reedcrm@reedcrm']);

header('Content-Type: application/json');

$elementType = GETPOST('element_type', 'aZ09');
$elementId   = GETPOSTINT('element_id');
$callListId  = GETPOSTINT('call_list_id');

if (empty($elementType) || empty($elementId) || empty($callListId)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

if (!$user->hasRight('reedcrm', 'call_list', 'write')) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$callList = new CallList($db);
if ($callList->fetch($callListId) <= 0) {
    echo json_encode(['success' => false, 'message' => $langs->trans('CallListNotFound')]);
    exit;
}
if (!in_array($callList->status, [CallList::STATUS_DRAFT, CallList::STATUS_ACTIVE])) {
    echo json_encode(['success' => false, 'message' => $langs->trans('CallListCannotAddToArchivedList')]);
    exit;
}

$lineObject = new CallListLine($db);
if ($lineObject->existsByElement($callListId, $elementType, $elementId)) {
    echo json_encode(['success' => false, 'message' => $langs->trans('CallListWidgetDuplicate')]);
    exit;
}

$element = null;
if ($elementType === 'project' && isModEnabled('projet')) {
    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
    $element = new Project($db);
} elseif ($elementType === 'propal' && isModEnabled('propale')) {
    require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
    $element = new Propal($db);
} elseif ($elementType === 'facture' && isModEnabled('facture')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
    $element = new Facture($db);
}

if ($element === null || $element->fetch($elementId) <= 0) {
    echo json_encode(['success' => false, 'message' => $langs->trans('CallListWidgetError')]);
    exit;
}

$contacts = array_filter(
    (array) $element->liste_contact(-1, 'external'),
    static function ($c) { return $c['code'] !== 'PROJECTADDRESS'; }
);

if (empty($contacts)) {
    echo json_encode(['success' => false, 'message' => $langs->trans('CallListWidgetNoContact')]);
    exit;
}

$firstContact = reset($contacts);
$fkContact    = (int) $firstContact['id'];

$newLine               = new CallListLine($db);
$newLine->fk_call_list = $callListId;
$newLine->element_type = $elementType;
$newLine->element_id   = $elementId;
$newLine->fk_contact   = $fkContact;
$newLine->status       = CallListLine::STATUS_TO_CALL;

if ($newLine->create($user) <= 0) {
    echo json_encode(['success' => false, 'message' => $langs->trans('CallListWidgetError')]);
    exit;
}

echo json_encode(['success' => true, 'message' => $langs->trans('CallListWidgetSuccess', $callList->label)]);
exit;
