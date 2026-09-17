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
 * \file    ajax/get_du_audit_documents.php
 * \ingroup reedcrm
 * \brief   Returns JSON with the proposals and invoices a DU audit line can be linked to for a client.
 */

// Load ReedCRM environment.
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

global $conf, $db, $langs, $user;

header('Content-Type: application/json');

if (!$user->hasRight('reedcrm', 'followup', 'read')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$socid = GETPOSTINT('socid');
if ($socid <= 0) {
    echo json_encode(['success' => true, 'propals' => [], 'factures' => []]);
    exit;
}

$docs   = reedcrmFollowupGetLinkableDocs($db, $socid);
$format = function (array $doc) use ($langs, $conf): array {
    // Ref — date — amount: everything needed to recognize the document in a dropdown.
    $label = $doc['ref']
        . ($doc['date'] ? ' — ' . dol_print_date($doc['date'], 'day') : '')
        . ($doc['total_ttc'] !== null ? ' — ' . price($doc['total_ttc'], 0, $langs, 1, -1, 0, $conf->currency) : '');

    return ['id' => $doc['id'], 'label' => html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
};

echo json_encode([
    'success'  => true,
    'propals'  => array_map($format, $docs['propals']),
    'factures' => array_map($format, $docs['factures']),
]);
