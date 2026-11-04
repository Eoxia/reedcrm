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
 * \file    view/sales_dashboard.php
 * \ingroup reedcrm
 * \brief   Page with the dashboard of the sales team: daily activity against the goals, pipeline and follow-ups
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Saturne libraries
dol_include_once('/saturne/class/saturnedashboard.class.php');

// Load ReedCRM libraries
require_once __DIR__ . '/../class/reedcrmsalesdashboard.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $moduleName, $moduleNameLowerCase, $user;

// Load translation files required by the page
saturne_load_langs(['projects', 'propal', 'agenda', 'commercial']);

// Get parameters
$action = GETPOST('action', 'aZ09');

// Initialize technical objects
$dashboard = new SaturneDashboard($db, $moduleNameLowerCase);
$object    = null;

// The CSV export of a graph writes in the temp directory of the module, dashboard_actions.tpl.php reads that name
$upload_dir = $conf->$moduleNameLowerCase->multidir_output[$conf->entity ?? 1];

$hookmanager->initHooks([$moduleNameLowerCase . 'salesdashboard', 'globalcard']);

// Security check - Protection if external user
$permissionToRead = $user->hasRight($moduleNameLowerCase, 'dashboard', 'sales');
saturne_check_access($permissionToRead);

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    // Actions adddashboardinfo, closedashboardinfo, dashboardfilter, generate_csv
    require_once dol_buildpath('/saturne/core/tpl/actions/dashboard_actions.tpl.php');
}

/*
 * View
 */

$title   = $langs->transnoentities('SalesDashboard');
$helpUrl = 'FR:Module_' . $moduleName;

// The body class lets the wide lists of the dashboard scroll in their own box instead of stretching the page
saturne_header(0, '', $title, $helpUrl, '', 0, 0, [], [], '', 'reedcrm-steering-dashboard');

$morehtmlright  = '<a class="butAction" href="' . dol_buildpath('/reedcrm/view/todo_list.php', 1) . '">' . $langs->transnoentities('TodoBoard') . '</a>';
$morehtmlright .= '<a class="butAction" href="' . dol_buildpath('/reedcrm/view/quickcreation.php', 1) . '">' . $langs->transnoentities('QuickCreation') . '</a>';

print load_fiche_titre($title, $morehtmlright, 'fontawesome_fa-chart-line_fas_#63ACC9');

print '<div class="fichecenter">';

$dashboard->show_dashboard(['LoadSalesDashboard' => 1]);

print '</div>';

// End of page
llxFooter();
$db->close();
