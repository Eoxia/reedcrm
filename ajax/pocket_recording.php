<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

/**
 * \file    ajax/pocket_recording.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint to edit a Pocket recording in place, from the badge and the summary block.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

// Load main Dolibarr environment
if (file_exists(__DIR__ . '/../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}

require_once __DIR__ . '/../class/pocketrecording.class.php';
require_once __DIR__ . '/../lib/reedcrm_pocketrecording.lib.php';

global $db, $langs, $user;

$langs->loadLangs(['reedcrm@reedcrm']);

top_httphead('application/json');

if (!$user->hasRight('reedcrm', 'pocketrecording', 'write')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$subAction   = GETPOST('subaction', 'aZ09');
$recordingId = GETPOSTINT('recording_id');

$recording = new PocketRecording($db);
if ($recordingId <= 0 || $recording->fetch($recordingId) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')]);
    exit;
}

if ($subAction === 'set_status') {
    $status = GETPOSTINT('status');

    // Only the statuses the object declares are accepted: the picker is built from the same list
    if (!isset($recording->fields['status']['arrayofkeyval'][$status])) {
        echo json_encode(['success' => false, 'error' => $langs->trans('ErrorBadValue')]);
        exit;
    }

    $recording->status = $status;

    if ($recording->update($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $recording->error]);
        exit;
    }

    // The badge is rebuilt the way dol_banner_tab does it, so the banner keeps the look it had
    $statusHtml = $recording->getLibStatut(6);
    if (empty($statusHtml) || $statusHtml == $recording->getLibStatut(3)) {
        $statusHtml = $recording->getLibStatut(5);
    }

    echo json_encode(['success' => true, 'status' => $status, 'status_html' => $statusHtml]);
    exit;
}

if ($subAction === 'set_summary') {
    // Pocket writes the summary, the user owns it afterwards: emptying it hands it back to Pocket,
    // which fills it again on the next synchronisation.
    // The summary is taken raw and cleaned by the module: restricthtml knows nothing of the Pocket
    // blocks and would drop the graphs of a summary as soon as the text around them is edited
    $summary = reedcrm_pocket_sanitize_summary(GETPOST('summary', 'none'));

    $recording->summary        = $summary;
    $recording->summary_edited = $recording->summary !== '' ? 1 : 0;

    if ($recording->update($user) <= 0) {
        echo json_encode(['success' => false, 'error' => $recording->error]);
        exit;
    }

    // The markdown is rendered by the server, Pocket blocks included, so the block shows what a
    // reload would show and the browser never has to know the Pocket syntax
    $summaryHtml = !empty($recording->summary)
        ? reedcrm_pocket_summary_to_html($recording->summary)
        : '<span class="opacitymedium">' . $langs->trans('PocketNoSummary') . '</span>';

    echo json_encode([
        'success'      => true,
        'summary'      => $recording->summary,
        'summary_html' => $summaryHtml,
        'edited'       => (int) $recording->summary_edited
    ]);
    exit;
}

if ($subAction === 'search_objects') {
    // With no term, the objects of the thirdparty of the recording are listed: that is what the
    // conversation is about most of the time, and it saves the user from typing to get started
    $objects = reedcrm_pocket_search_objects(
        $recording,
        GETPOST('object_type', 'aZ09'),
        GETPOST('search', 'alphanohtml'),
        20
    );

    // The picto travels ready to print: it is the one of the type, drawn by Dolibarr, and the list
    // of the card is built the same way when it is printed with the page
    $choices = [];
    foreach (array_slice($objects, 0, 20) as $object) {
        $choices[] = [
            'key'   => $object['key'],
            'label' => reedcrm_pocket_format_object_choice($object),
            'picto' => !empty($object['picto']) ? img_picto('', $object['picto'], 'class="pictofixedwidth"') : ''
        ];
    }

    echo json_encode(['success' => true, 'objects' => $choices]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown subaction']);
exit;
