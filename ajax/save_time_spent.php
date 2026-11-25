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

/**
 * \file    ajax/save_time_spent.php
 * \ingroup reedcrm
 * \brief   Logs the time typed in the quick block of a ticket or project card, and sends back what the block shows.
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

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once __DIR__ . '/../lib/reedcrm_timespent.lib.php';

global $db, $langs, $user;

header('Content-Type: application/json');

saturne_load_langs(['errors']);

$elementType = GETPOST('element_type', 'aZ09');
$elementID   = GETPOSTINT('element_id');
$minutes     = GETPOSTINT('minutes');
$note        = GETPOST('note', 'alphanohtml');

if (GETPOST('action', 'aZ09') !== 'save_time' || !reedcrm_timespent_user_can_log($user)) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

if ($minutes <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('ReedCRMTimeSpentInvalidMinutes')]);
    exit;
}

if ($elementType === 'project') {
    $object = new Project($db);
} elseif ($elementType === 'ticket' && isModEnabled('ticket') && $user->hasRight('ticket', 'read')) {
    require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
    $object = new Ticket($db);
} else {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

if ($elementID <= 0 || $object->fetch($elementID) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorRecordNotFound')]);
    exit;
}

$project = reedcrm_timespent_get_project($db, $object);
if ($project === null) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('PleaseLinkProjectFirst')]);
    exit;
}

// The time goes on a task of the project: same access as the one Dolibarr asks to read it
if ($project->restrictedProjectArea($user) <= 0) {
    echo json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

$error = reedcrm_timespent_add($db, $user, $object, $minutes, $note);
if ($error !== '') {
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$summary = reedcrm_timespent_get_summary($db, $object);

// The monthly table of a project follows the time spent
$tableHtml = '';
if ($object instanceof Project) {
    ob_start();
    require __DIR__ . '/../core/tpl/reedcrm_project_time_table.tpl.php';
    $tableHtml = ob_get_clean();
}

echo json_encode([
    'success'       => true,
    'count'         => $summary['count'],
    'url'           => $summary['url'],
    'tooltip'       => reedcrm_timespent_render_latest_tooltip($summary),
    'last_html'     => reedcrm_timespent_render_last_entry($summary),
    'total'         => reedcrm_timespent_format_hours($summary['total']),
    'planned'       => $summary['planned'] > 0 ? reedcrm_timespent_format_hours($summary['planned']) : '',
    'recap_tooltip' => reedcrm_timespent_render_recap_tooltip($summary),
    'table_html'    => $tableHtml
]);
