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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
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
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
if (file_exists('../../reedcrm.main.inc.php')) {
    require_once '../../reedcrm.main.inc.php';
} elseif (file_exists('../../../reedcrm.main.inc.php')) {
    require_once '../../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once __DIR__ . '/../../lib/reedcrm_ticket_time.lib.php';

header('Content-Type: application/json');

$action = GETPOST('action', 'alpha');
$ticket_id = GETPOSTINT('ticket_id');
$note = GETPOST('note', 'restricthtml');
$minutes = GETPOSTINT('minutes');

// Basic checks
if (empty($user->id)) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($action !== 'save_time' || $ticket_id <= 0 || $minutes <= 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides. Veuillez fournir un temps > 0.']);
    exit;
}

$ticket = new Ticket($db);
if ($ticket->fetch($ticket_id) <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
    exit;
}

// Task "<prefix> <ticket ref>" of the project, time line and agenda event: shared with the money banner of the ticket card
$error = reedcrm_ticket_log_time($db, $user, $ticket, $minutes, (string) $note);
if ($error !== '') {
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$dateTs   = dol_now();
$dateStr  = dol_print_date($dateTs, '%d/%m/%y %H:%M');
$userStr  = $user->login;
$noteStr  = dol_trunc(strip_tags($note), 100);
$dureeStr = convertSecondToTime($minutes * 60, 'allhourmin');

$initial = strtoupper(substr($userStr, 0, 1));
$colorHash = substr(md5($userStr), 0, 6);
$userHtml = '<span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background-color: #'.$colorHash.'; color: white; font-size: 0.7em; font-weight: bold; margin: 0 4px;" title="'.dol_escape_htmltag($userStr).'">'.$initial.'</span>';

$lineStr = '<div style="display: flex; align-items: center; width: 100%;">';
$lineStr .= '<span style="color: #a0aec0; margin-right: 4px; white-space: nowrap;">' . dol_escape_htmltag($dateStr) . '</span>';
$lineStr .= $userHtml;
$lineStr .= '<span style="margin: 0 4px; white-space: nowrap;">| ' . dol_escape_htmltag($dureeStr) . '</span>';
if (!empty($noteStr)) {
    $lineStr .= '<span style="margin: 0 4px; white-space: nowrap;">|</span><span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-grow: 1;" title="' . dol_escape_htmltag($noteStr) . '">' . dol_escape_htmltag($noteStr) . '</span>';
}
$lineStr .= '</div>';

$htmlLine = '<div id="reedcrm-ticket-last-time" style="flex-basis: 100%; font-weight: normal; font-size: 0.85em; color: #718096; padding-left: 4px; margin-top: 2px; max-width: 100%; overflow: hidden;">' . $lineStr . '</div>';

echo json_encode(['success' => true, 'new_line_html' => $htmlLine]);
