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
 * \file    view/opportunity_stats.php
 * \ingroup reedcrm
 * \brief   Monthly count of the opportunities carrying a given tag, one row per year.
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

// Load ReedCRM libraries.
require_once __DIR__ . '/../lib/reedcrm_opportunity_stats.lib.php';

global $conf, $db, $form, $hookmanager, $langs, $user;

saturne_load_langs(['projects', 'categories', 'other']);

$action = GETPOST('action', 'aZ09') ?: 'view';

$hookmanager->initHooks(['opportunitystats']);

// Security check.
$permissiontoread = $user->hasRight('reedcrm', 'read') && $user->hasRight('projet', 'lire');
saturne_check_access($permissiontoread);

/*
 * Filters.
 */
$projectTags = reedcrmOpportunityStatsGetProjectTags($db);

$searchTagID    = GETPOSTINT('search_tag');
$searchYears    = GETPOSTINT('search_years');
$searchDateType = GETPOST('search_date_type', 'aZ09');

if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha')) {
    $searchTagID    = 0;
    $searchYears    = 0;
    $searchDateType = '';
}

// A tag that no longer exists would silently return an empty table
if (!isset($projectTags[$searchTagID])) {
    $searchTagID = 0;
}
if (!in_array($searchYears, REEDCRM_OPPORTUNITY_STATS_YEAR_SPANS, true)) {
    $searchYears = REEDCRM_OPPORTUNITY_STATS_YEAR_SPANS[0];
}
if (!in_array($searchDateType, REEDCRM_OPPORTUNITY_STATS_DATE_FIELDS, true)) {
    $searchDateType = REEDCRM_OPPORTUNITY_STATS_DATE_FIELDS[0];
}

$runningYear = (int) dol_print_date(dol_now(), '%Y');
$firstYear   = $runningYear - ($searchYears - 1);

$stats      = reedcrmOpportunityStatsGetMonthlyCounts($db, $searchTagID, $firstYear, $runningYear, $searchDateType);
$highlights = reedcrmOpportunityStatsGetHighlights($stats, $runningYear);

/*
 * View.
 */
// saturne_header() already loads saturne.min.* and reedcrm.min.*
$title = $langs->trans('OpportunityStatsTitle');

saturne_header(0, '', $title, '');

print load_fiche_titre($title, '', 'fontawesome_fa-chart-bar_fas_#63ACC9');

// Counting on the start date leaves out the opportunities that carry none, the reader has to know
if ($searchDateType === 'dateo') {
    print info_admin($langs->trans('OpportunityStatsDateStartWarning'), 0, 0, '1', 'warning');
}

require __DIR__ . '/../core/tpl/reedcrm_opportunity_stats_filters.tpl.php';
require __DIR__ . '/../core/tpl/reedcrm_opportunity_stats_summary.tpl.php';
require __DIR__ . '/../core/tpl/reedcrm_opportunity_stats_table.tpl.php';

llxFooter();
$db->close();
