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
 * \file    ajax/save_intervention_dates.php
 * \ingroup reedcrm
 * \brief   Saves the intervention dates of one service line and keeps their agenda events in sync.
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

if (!$user->hasRight('propal', 'creer')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$lineID = GETPOSTINT('line_id');
if ($lineID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid line_id']);
    exit;
}

$sql  = 'SELECT pd.rowid, pd.fk_propal, pd.fk_product, pd.label, pd.description, pd.qty, pd.product_type, p.label as product_label';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'propaldet as pd';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = pd.fk_product';
$sql .= ' WHERE pd.rowid = ' . $lineID;

$resql = $db->query($sql);
$line  = $resql ? $db->fetch_object($resql) : null;
if (empty($line) || (int) $line->product_type !== Product::TYPE_SERVICE) {
    echo json_encode(['success' => false, 'error' => 'Line not found']);
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

$expected  = InterventionDate::getExpectedCount((float) $line->qty);
$lineLabel = reedcrmInterventionGetLineLabel($line);

$postedDates     = GETPOST('intervention_date', 'array:alphanohtml');
$postedTimes     = GETPOST('intervention_time', 'array:alphanohtml');
$postedDurations = GETPOST('intervention_duration', 'array:int');
$postedUsers     = GETPOST('intervention_user', 'array:int');
$postedLocations = GETPOST('intervention_location', 'array:alphanohtml');
$postedNotes     = GETPOST('intervention_note', 'array:alphanohtml');
$postedDone      = GETPOST('intervention_done', 'array:int');

$interventionDate = new InterventionDate($db);
$existingDates    = $interventionDate->fetchAllByLine('propal', $lineID);

$db->begin();

$error        = 0;
$errorMessage = '';
$planned      = 0;

for ($position = 1; $position <= $expected; $position++) {
    $existing = $existingDates[$position] ?? null;
    $rawDate  = trim((string) ($postedDates[$position] ?? ''));
    $rawTime  = trim((string) ($postedTimes[$position] ?? ''));

    // An empty date means the intervention is not planned any more : the row and its event go away
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $dateParts)) {
        if (!empty($existing)) {
            if ($existing->delete($user) < 0) {
                $error++;
                $errorMessage = $existing->error;
                break;
            }
            unset($existingDates[$position]);
        }
        continue;
    }

    $hour   = 0;
    $minute = 0;
    if (preg_match('/^(\d{1,2}):(\d{2})/', $rawTime, $timeParts)) {
        $hour   = min(23, (int) $timeParts[1]);
        $minute = min(59, (int) $timeParts[2]);
    }

    $timestamp = dol_mktime($hour, $minute, 0, (int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1], 'tzserver');
    $duration  = isset($postedDurations[$position]) ? max(0, (int) $postedDurations[$position]) : reedcrmInterventionDefaultDuration();
    $isDone    = !empty($postedDone[$position]);

    $record = $existing ?: new InterventionDate($db);

    $record->entity              = $conf->entity;
    $record->element_type        = 'propal';
    $record->element_id          = (int) $propal->id;
    $record->fk_element_line     = $lineID;
    $record->position            = $position;
    $record->date_intervention   = $timestamp;
    $record->duration            = $duration;
    $record->fk_user_intervenant = isset($postedUsers[$position]) ? max(0, (int) $postedUsers[$position]) : 0;
    $record->location            = dol_trunc((string) ($postedLocations[$position] ?? ''), 255, 'right', 'UTF-8', 1);
    $record->note                = dol_trunc((string) ($postedNotes[$position] ?? ''), 255, 'right', 'UTF-8', 1);
    $record->status              = $isDone ? InterventionDate::STATUS_DONE : InterventionDate::STATUS_PLANNED;

    // The event mirrors the date : it is written before the row so the row keeps its event id
    if ($record->syncEvent($user, $propal, $lineLabel) < 0) {
        $error++;
        $errorMessage = $record->error;
        break;
    }

    $result = empty($existing) ? $record->create($user) : $record->update($user);
    if ($result <= 0) {
        $error++;
        $errorMessage = $record->error;
        break;
    }

    $planned++;
}

// A quantity brought down leaves rows beyond the last position expected : they have no unit to sit on
foreach ($existingDates as $position => $existing) {
    if ($position > $expected && $existing->delete($user) < 0) {
        $error++;
        $errorMessage = $existing->error;
        break;
    }
}

if ($error) {
    $db->rollback();
    echo json_encode(['success' => false, 'error' => $errorMessage ?: $langs->transnoentities('InterventionDateSaveError')]);
    exit;
}

$db->commit();

echo json_encode([
    'success'  => true,
    'planned'  => $planned,
    'expected' => $expected
]);
exit;
