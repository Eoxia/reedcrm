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
 * \file    ajax/set_relaunch_status.php
 * \ingroup reedcrm
 * \brief   Status of a relaunch picked from the selector of the panel of the relaunch widget: to do,
 *          running, done or not applicable. Answers the new badge of the line and the content of the
 *          two buttons, which the page repaints in place.
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

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_relaunch.lib.php';

global $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['agenda', 'errors', 'reedcrm@reedcrm']);

// Every message goes into the page as text, never as HTML: no entity encoding (transnoentitiesnoconv)
top_httphead('application/json');

/**
 * Stop with an error the panel prints under the badge.
 *
 * @param  string $error Translated message
 * @return void
 */
function reedcrm_set_relaunch_status_fail(string $error)
{
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// A status change writes, it never rides on a GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    reedcrm_set_relaunch_status_fail('Method not allowed');
}

$choices   = reedcrm_get_relaunch_status_choices();
$eventId   = GETPOSTINT('event_id');
$status    = GETPOSTISSET('status') ? GETPOSTINT('status') : null;
$projectId = GETPOSTINT('project_id');
$socid     = GETPOSTINT('socid');

// Dates the event carried before a status closed it ahead of time, sent back when it is reopened
// from the same panel, so that the move to now is undone exactly
$restoreDatep = GETPOSTINT('restore_datep');
$restoreDatef = GETPOSTINT('restore_datef');

if ($status === null || !array_key_exists($status, $choices)) {
    reedcrm_set_relaunch_status_fail($langs->transnoentitiesnoconv('ErrorBadParameters'));
}

$actionComm = new ActionComm($db);
if ($eventId <= 0 || $actionComm->fetch($eventId) <= 0) {
    reedcrm_set_relaunch_status_fail($langs->transnoentitiesnoconv('ErrorRecordNotFound'));
}

if (!reedcrm_user_can_edit_relaunch($user, (int) $actionComm->authorid, (int) $actionComm->userownerid)) {
    reedcrm_set_relaunch_status_fail($langs->transnoentitiesnoconv('NotEnoughPermissions'));
}

// Only what the panel lists can be changed from it: an event tagged as a relaunch or a reminder
$categoryIds = reedcrm_get_relaunch_category_ids();
$isRelaunch  = false;
if (!empty($categoryIds)) {
    $sql  = 'SELECT COUNT(*) as nb FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm';
    $sql .= ' WHERE fk_actioncomm = ' . ((int) $actionComm->id);
    $sql .= ' AND fk_categorie IN (' . implode(',', $categoryIds) . ')';

    $resql = $db->query($sql);
    if ($resql) {
        $obj        = $db->fetch_object($resql);
        $isRelaunch = !empty($obj->nb);
        $db->free($resql);
    }
}
if (!$isRelaunch) {
    reedcrm_set_relaunch_status_fail($langs->transnoentitiesnoconv('RelaunchStatusNotARelaunch'));
}

$current  = reedcrm_get_relaunch_status_choice((int) $actionComm->percentage);
$previous = null;

// Done and not applicable both close the relaunch, nobody waits for it any more
$wasClosed = ($current === 100 || $current === -1);
$isClosed  = ($status === 100 || $status === -1);

// Picking the status the line already shows writes nothing: a running event keeps its own progress
if ($status !== $current) {
    $actionComm->oldcopy = clone $actionComm;

    if ($isClosed && !$wasClosed) {
        // A relaunch of the past keeps its dates. One still to come is closed now, ahead of time: it
        // moves to now, or it would stay in the future, counted on neither button. Now is the minute
        // already started, strictly before the instant the counters are worked out right below, or
        // the event would sit on the boundary, neither past nor to come
        $now = dol_now();
        if (empty($actionComm->datep) || $actionComm->datep > $now) {
            $previous = [
                'datep' => (int) $actionComm->datep,
                'datef' => (int) $actionComm->datef,
            ];

            $doneAt            = ($now - 1) - (($now - 1) % 60);
            $actionComm->datep = $doneAt;
            $actionComm->datef = $doneAt;
        }
    } elseif (!$isClosed && $wasClosed && $restoreDatep > 0) {
        $actionComm->datep = $restoreDatep;
        $actionComm->datef = $restoreDatef >= $restoreDatep ? $restoreDatef : '';
    }

    $actionComm->percentage = $status;

    if ($actionComm->update($user) <= 0) {
        reedcrm_set_relaunch_status_fail($actionComm->error ?: $langs->transnoentitiesnoconv('Error'));
    }
}

$state = reedcrm_get_relaunch_row_state(['percent' => (int) $actionComm->percentage, 'datep' => (int) $actionComm->datep], dol_now());

echo json_encode([
    'success'    => true,
    'status'     => reedcrm_get_relaunch_status_choice((int) $actionComm->percentage),
    'closed'     => $isClosed ? 1 : 0,
    'badge'      => [
        'label' => $langs->transnoentitiesnoconv($state['key']),
        'css'   => $state['css'],
    ],
    'date'       => !empty($actionComm->datep) ? dol_print_date($actionComm->datep, 'dayhour') : '',
    'late_days'  => $state['late_days'],
    'late_label' => $state['late_days'] > 0 ? $langs->transnoentitiesnoconv('RelaunchLateBy', $state['late_days']) : '',
    'restore'    => $previous,
    // Counted again after the change, for every widget of the page showing the same project or third party
    'segments'   => ($projectId > 0 || $socid > 0) ? reedcrm_get_relaunch_segments(reedcrm_get_relaunch_counts($db, $projectId, $socid)) : null,
]);

$db->close();
