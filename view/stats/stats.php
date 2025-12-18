<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    view/stats/stats.php
 * \ingroup reedcrm
 * \brief   Statistics page with sales funnel graph
 */

// Load ReedCRM environment
if (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} elseif (file_exists('../../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../../class/reedcrmdashboard.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$action = GETPOST('action', 'aZ09');

// Initialize technical objects
$dashboard = new ReedcrmDashboard($db);
$form      = new Form($db);

// Security check - Protection if external user
$permissionToRead = $user->rights->reedcrm->read;
saturne_check_access($permissionToRead);

// Get date filters from URL
$dateStartDay = GETPOST('salesfunnel_date_startday', 'int');
$dateStartMonth = GETPOST('salesfunnel_date_startmonth', 'int');
$dateStartYear = GETPOST('salesfunnel_date_startyear', 'int');
$dateEndDay = GETPOST('salesfunnel_date_endday', 'int');
$dateEndMonth = GETPOST('salesfunnel_date_endmonth', 'int');
$dateEndYear = GETPOST('salesfunnel_date_endyear', 'int');

// Build timestamps from GET parameters for selectDate
// Par défaut : date début = il y a une semaine, date fin = aujourd'hui
$now = dol_now();
$dateStartTimestamp = -1;
$dateEndTimestamp = -1;

if ($dateStartDay > 0 && $dateStartMonth > 0 && $dateStartYear > 0) {
    $dateStartTimestamp = dol_mktime(0, 0, 0, $dateStartMonth, $dateStartDay, $dateStartYear);
} else {
    // Par défaut : il y a une semaine
    $dateStartTimestamp = $now - (7 * 24 * 3600);
}

if ($dateEndDay > 0 && $dateEndMonth > 0 && $dateEndYear > 0) {
    $dateEndTimestamp = dol_mktime(23, 59, 59, $dateEndMonth, $dateEndDay, $dateEndYear);
} else {
    // Par défaut : aujourd'hui
    $dateEndTimestamp = $now;
}

/*
 * View
 */

$title   = $langs->transnoentities('Statistics');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

// Load CSS
print '<link rel="stylesheet" href="' . dol_buildpath('/custom/reedcrm/css/stats.css', 1) . '">';

print '<div class="reedcrm-stats-container">';

print '<div class="reedcrm-stats-header">';
print '<h1>' . $langs->transnoentities('Statistics') . '</h1>';
print '<p>' . $langs->transnoentities('SalesFunnel') . '</p>';
print '</div>';

// Filters section with collapse/expand
print '<div class="reedcrm-stats-filters-wrapper">';
print '<div class="reedcrm-stats-filters-header" id="filters-header" style="cursor: pointer;">';
print '<h3><i class="fas fa-chevron-down"></i> ' . $langs->trans('Filter') . '</h3>';
print '</div>';

print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" name="statsform" id="statsform" class="reedcrm-stats-filters">';

print '<div class="reedcrm-stats-filters-content">';

print '<div class="reedcrm-stats-filter-group">';
print '<label>' . $langs->transnoentities('DateStart') . '</label>';
print '<div class="date-selector-wrapper">';
print $form->selectDate($dateStartTimestamp, 'salesfunnel_date_start', 0, 0, 1, 'form', 1, 0, 0, '', '', '', 1);
print '</div>';
print '</div>';

print '<div class="reedcrm-stats-filter-group">';
print '<label>' . $langs->transnoentities('DateEnd') . '</label>';
print '<div class="date-selector-wrapper">';
print $form->selectDate($dateEndTimestamp, 'salesfunnel_date_end', 0, 0, 0, 'form', 1, 0, 0, '', '', '', 1);
print '</div>';
print '</div>';

print '<div class="reedcrm-stats-filter-group">';
print '<label>&nbsp;</label>';
print '<button type="submit" class="reedcrm-stats-filter-btn">';
print $langs->transnoentities('Filter');
print '</button>';
print '</div>';

print '</div>';
print '</form>';
print '</div>';


// Get sales funnel data with default dates
$funnelData = $dashboard->getSalesFunnel($dateStartTimestamp, $dateEndTimestamp);

if (!empty($funnelData) && !empty($funnelData['custom_html'])) {
    print '<div class="reedcrm-stats-graph-container">';
    print '<div class="reedcrm-stats-graph-title">' . $funnelData['title'] . '</div>';
    print '<div class="reedcrm-stats-funnel-container">';
    print $funnelData['custom_html'];
    print '</div>';
    print '</div>';
} else {
    print '<div class="reedcrm-stats-graph-container">';
    print '<div class="reedcrm-stats-graph-title">' . (!empty($funnelData['title']) ? $funnelData['title'] : $langs->transnoentities('SalesFunnel')) . '</div>';
    print '<div class="reedcrm-stats-no-data">';
    print '<i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i><br>';
    print $langs->trans('NoData');
    print '</div>';
    print '</div>';
}

// ---------------------------------------------------------------------
// Graph - Statuts des propales ouvertes (brouillon / validée / signée)
// ---------------------------------------------------------------------

print '<div class="reedcrm-stats-graph-container">';
print '<div class="reedcrm-stats-graph-title">' . $langs->transnoentities('PropalsOpenStatus') . '</div>';

// Statuts ouverts : brouillon (0), validée (1), signée (2)
$openStatuses = array(Propal::STATUS_DRAFT, Propal::STATUS_VALIDATED, Propal::STATUS_SIGNED);

$sql = "SELECT fk_statut, COUNT(*) as nb";
$sql .= " FROM " . MAIN_DB_PREFIX . "propal";
$sql .= " WHERE entity IN (" . getEntity('propal') . ")";
$sql .= " AND fk_statut IN (" . implode(',', $openStatuses) . ")";
$sql .= " GROUP BY fk_statut";

$resql = $db->query($sql);
$dataseries = array();
$colorseries = array();
$totalopen = 0;

if ($resql) {
    $propalstatic = new Propal($db);
    include DOL_DOCUMENT_ROOT . '/theme/' . $conf->theme . '/theme_vars.inc.php';

    while ($obj = $db->fetch_object($resql)) {
        $label = $propalstatic->LibStatut((int) $obj->fk_statut, 1, 0, 1);
        $dataseries[] = array($label, (int) $obj->nb);
        $totalopen += (int) $obj->nb;

        if ((int) $obj->fk_statut === Propal::STATUS_DRAFT) {
            $colorseries[$obj->fk_statut] = '-' . $badgeStatus0;
        } elseif ((int) $obj->fk_statut === Propal::STATUS_VALIDATED) {
            $colorseries[$obj->fk_statut] = $badgeStatus1;
        } elseif ((int) $obj->fk_statut === Propal::STATUS_SIGNED) {
            $colorseries[$obj->fk_statut] = $badgeStatus6;
        }
    }
    $db->free($resql);
}

if (!empty($dataseries) && $totalopen > 0) {
    $dolgraph = new DolGraph();
    $dolgraph->SetData($dataseries);
    if (!empty($colorseries)) {
        $dolgraph->SetDataColor(array_values($colorseries));
    }
    $dolgraph->setShowLegend(2);
    $dolgraph->setShowPercent(1);
    $dolgraph->SetType(array('pie'));
    $dolgraph->setHeight('250');
    $dolgraph->draw('reedcrm-propals-open-status');

    print '<div class="center">';
    print $dolgraph->show(0);
    print '</div>';
} else {
    print '<div class="reedcrm-stats-no-data">';
    print '<i class="fas fa-chart-pie" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i><br>';
    print $langs->trans('NoData');
    print '</div>';
}

print '</div>';
// ---------------------------------------------------------------------

print '</div>';

// End of page
llxFooter();
$db->close();
