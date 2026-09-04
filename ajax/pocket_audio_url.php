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
 * \file    ajax/pocket_audio_url.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint resolving the signed audio URL of a Pocket recording.
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

require_once __DIR__ . '/../class/pocketapi.class.php';
require_once __DIR__ . '/../class/pocketrecording.class.php';

global $db, $langs, $user;

$langs->loadLangs(['reedcrm@reedcrm']);

top_httphead('application/json');

if (!$user->hasRight('reedcrm', 'pocketrecording', 'read')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$id = GETPOSTINT('id');

$recording = new PocketRecording($db);
if ($id <= 0 || $recording->fetch($id) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')]);
    exit;
}

// The URL Pocket signs is short lived, so it is resolved when the user actually presses play
// rather than on every card render, and it is never stored.
$pocketApi = new PocketApi($db);
$audioUrl  = $pocketApi->getAudioUrl($recording->pocket_id);

if (empty($audioUrl)) {
    echo json_encode(['success' => false, 'error' => $pocketApi->error ?: $langs->trans('PocketAudioUnavailable')]);
    exit;
}

echo json_encode(['success' => true, 'url' => $audioUrl]);
exit;
