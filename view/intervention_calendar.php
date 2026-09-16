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
 * \file    view/intervention_calendar.php
 * \ingroup reedcrm
 * \brief   Calendar of the intervention dates carried by the service lines, filtered by intervenant.
 */

// Load ReedCRM environment.
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../class/interventiondate.class.php';
require_once __DIR__ . '/../lib/reedcrm_interventiondate.lib.php';

global $conf, $db, $form, $hookmanager, $langs, $user;

saturne_load_langs(['propal', 'agenda', 'other', 'companies', 'products']);

$action = GETPOST('action', 'aZ09') ?: 'view';

$hookmanager->initHooks(['interventioncalendar']);

// Security check.
$permissiontoread = $user->hasRight('reedcrm', 'read') && $user->hasRight('propal', 'lire');
saturne_check_access($permissiontoread);

if (!reedcrmInterventionIsEnabled()) {
    accessforbidden($langs->trans('InterventionDateFeatureDisabled'));
}

/*
 * Filters and period.
 */
$viewMode = GETPOST('view_mode', 'aZ09') === 'list' ? 'list' : 'month';
$month    = GETPOSTINT('month');
$year     = GETPOSTINT('year');
$now      = dol_now();

if (empty($month) || $month < 1 || $month > 12) {
    $month = (int) dol_print_date($now, '%m');
}
if (empty($year) || $year < 1970) {
    $year = (int) dol_print_date($now, '%Y');
}

if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha')) {
    $searchUsers        = [];
    $searchSocID        = 0;
    $searchStatus       = -1;
    $searchPropalStatus = -2;
} else {
    $searchUsers        = GETPOST('search_users', 'array:int');
    $searchSocID        = GETPOSTINT('search_socid');
    $searchStatus       = GETPOST('search_status', 'alpha') === '' ? -1 : GETPOSTINT('search_status');
    $searchPropalStatus = GETPOST('search_propal_status', 'alpha') === '' ? -2 : GETPOSTINT('search_propal_status');
}

// Only my interventions : one click, the most asked filter of the page.
if (GETPOSTINT('mine')) {
    $searchUsers = [$user->id];
}

$firstDay = dol_mktime(0, 0, 0, $month, 1, $year, 'tzserver');
$lastDay  = dol_get_last_day($year, $month, false);

$filters = [
    'user_ids'      => $searchUsers,
    'socid'         => $searchSocID,
    'status'        => $searchStatus,
    'propal_status' => $searchPropalStatus,
];

// dol_get_last_day() already lands on 23:59:59 of the last day of the month
$monthFilters               = $filters;
$monthFilters['date_start'] = $firstDay;
$monthFilters['date_end']   = $lastDay;

$rows      = reedcrmInterventionFetchRows($monthFilters);
$rowsByDay = reedcrmInterventionGroupByDay($rows);

// A service line whose quantity asks for more dates than the ones already filled is still waiting
$unplanned = reedcrmInterventionFetchUnplanned($filters);

// The intervenants of the month, so the legend only shows the ones actually working.
$monthUsers = [];
foreach ($rows as $row) {
    $monthUsers[$row->fk_user_intervenant] = $row->user_label ?: $langs->transnoentities('InterventionNoUser');
}
ksort($monthUsers);

$doneCount = 0;
foreach ($rows as $row) {
    if ((int) $row->status === InterventionDate::STATUS_DONE) {
        $doneCount++;
    }
}

$unplannedCount = 0;
foreach ($unplanned as $unplannedLine) {
    $unplannedCount += (int) $unplannedLine->remaining;
}

$previousMonth = dol_get_prev_month($month, $year);
$nextMonth     = dol_get_next_month($month, $year);
$users         = reedcrmInterventionGetUsers();

/*
 * View.
 */
// saturne_header() already loads saturne.min.* and reedcrm.min.*
$title = $langs->trans('InterventionCalendar');

saturne_header(0, '', $title, '');

print load_fiche_titre($title, '', 'fontawesome_fa-calendar-alt_fas_#63ACC9');

// Nothing can be planned until the tag of the services is chosen : an empty page would look broken
if (reedcrmInterventionProductTagID() <= 0) {
    print info_admin($langs->trans('InterventionDateNoProductTagWarning'), 0, 0, '1', 'warning');
}

require __DIR__ . '/../core/tpl/reedcrm_intervention_calendar_filters.tpl.php';

if ($viewMode === 'list') {
    require __DIR__ . '/../core/tpl/reedcrm_intervention_calendar_list.tpl.php';
} else {
    require __DIR__ . '/../core/tpl/reedcrm_intervention_calendar_month.tpl.php';
}

require __DIR__ . '/../core/tpl/reedcrm_intervention_calendar_unplanned.tpl.php';

// The modal of the proposal card is reused here, a chip opens the dates of its own service line.
// The page already carries the reedcrm assets, the modal must not load them a second time.
$object                        = null;
$interventionModalLoadAssets   = false;
require __DIR__ . '/../core/tpl/reedcrm_intervention_date_modal.tpl.php';

llxFooter();
$db->close();
