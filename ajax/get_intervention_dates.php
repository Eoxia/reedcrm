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
 * \file    ajax/get_intervention_dates.php
 * \ingroup reedcrm
 * \brief   Returns the intervention date rows of one service line, ready to be dropped into the modal.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/../class/interventiondate.class.php';
require_once __DIR__ . '/../lib/reedcrm_interventiondate.lib.php';

global $conf, $db, $langs, $user;

header('Content-Type: application/json');

saturne_load_langs();

if (!reedcrmInterventionIsEnabled()) {
    echo json_encode(['success' => false, 'error' => 'Feature disabled']);
    exit;
}

if (!$user->hasRight('propal', 'lire')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$lineID = GETPOSTINT('line_id');
if ($lineID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid line_id']);
    exit;
}

// The line carries the quantity, so it carries the number of interventions expected
$sql  = 'SELECT pd.rowid, pd.fk_propal, pd.fk_product, pd.label, pd.description, pd.qty, pd.product_type, p.label as product_label';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'propaldet as pd';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = pd.fk_product';
$sql .= ' WHERE pd.rowid = ' . $lineID;

$resql = $db->query($sql);
$line  = $resql ? $db->fetch_object($resql) : null;
if (empty($line)) {
    echo json_encode(['success' => false, 'error' => 'Line not found']);
    exit;
}
if ((int) $line->product_type !== Product::TYPE_SERVICE) {
    echo json_encode(['success' => false, 'error' => 'Not a service line']);
    exit;
}
if (!reedcrmInterventionProductHasTag((int) $line->fk_product)) {
    echo json_encode(['success' => false, 'error' => 'Service out of scope']);
    exit;
}

$propal = new Propal($db);
if ($propal->fetch((int) $line->fk_propal) <= 0) {
    echo json_encode(['success' => false, 'error' => 'Proposal not found']);
    exit;
}

$interventionDate = new InterventionDate($db);

$interventionDates           = $interventionDate->fetchAllByLine('propal', $lineID);
$interventionExpected        = InterventionDate::getExpectedCount((float) $line->qty);
$interventionUsers           = reedcrmInterventionGetUsers();
$interventionLocations       = reedcrmInterventionGetLocationSuggestions($propal);
$interventionDefaultDuration = reedcrmInterventionDefaultDuration();
$interventionCanWrite        = $user->hasRight('propal', 'creer');

$planned = 0;
foreach ($interventionDates as $existingDate) {
    if (!empty($existingDate->date_intervention)) {
        $planned++;
    }
}

ob_start();
require __DIR__ . '/../core/tpl/reedcrm_intervention_date_rows.tpl.php';
$html = ob_get_clean();

echo json_encode([
    'success'  => true,
    'title'    => reedcrmInterventionGetLineLabel($line) . ' - ' . $langs->transnoentities('Quantity') . ' ' . price2num($line->qty, 'MS'),
    'expected' => $interventionExpected,
    'planned'  => $planned,
    'html'     => $html
]);
exit;
