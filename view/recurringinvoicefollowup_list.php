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
 * \file    view/recurringinvoicefollowup_list.php
 * \ingroup reedcrm
 * \brief   Recurring invoice follow-up: yearly chart, monthly dashboard and follow-up list.
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../class/recurringinvoicefollowup.class.php';
require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs();

// Get parameters.
$action      = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'recurringinvoicefollowuplist';
$optioncss   = GETPOST('optioncss', 'aZ');

// Search criteria.
$search_ref        = GETPOST('search_ref', 'alpha');
$search_fk_soc     = GETPOSTINT('search_fk_soc');
$search_prestation = GETPOST('search_prestation', 'alpha');
// Month board : YYYY-MM, defaults to the current month.
$search_month = GETPOST('search_month', 'alpha');
if (!preg_match('/^\d{4}-\d{2}$/', $search_month)) {
    $search_month = dol_print_date(dol_now(), '%Y-%m');
}
// Direct month/year selectors take precedence over the search_month param.
$searchYear     = GETPOSTINT('search_year');
$searchMonthNum = GETPOSTINT('search_monthnum');
if ($searchYear >= 2000 && $searchMonthNum >= 1 && $searchMonthNum <= 12) {
    $search_month = sprintf('%04d-%02d', $searchYear, $searchMonthNum);
}
$monthYear       = (int) substr($search_month, 0, 4);
$monthMonth      = (int) substr($search_month, 5, 2);
$periodStart     = dol_get_first_day($monthYear, $monthMonth);
$periodEnd       = dol_get_last_day($monthYear, $monthMonth);
$todayMonthStart = dol_get_first_day((int) dol_print_date(dol_now(), '%Y'), (int) dol_print_date(dol_now(), '%m'));

// Sort / pagination.
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($sortfield)) {
    $sortfield = 't.period';
}
if (empty($sortorder)) {
    $sortorder = 'DESC';
}
$limit  = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;
if (empty($page) || $page < 0) {
    $page   = 0;
    $offset = 0;
}

// Initialize technical objects.
$object      = new RecurringInvoiceFollowup($db);
$form        = new Form($db);
$formcompany = new FormCompany($db);

$hookmanager->initHooks(['recurringinvoicefollowuplist']);

// Security check.
$permissiontoread = $user->hasRight('reedcrm', 'followup', 'read');
$permissiontoadd  = $user->hasRight('reedcrm', 'followup', 'write');

saturne_check_access($permissiontoread);

// Purge search criteria.
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_ref        = '';
    $search_fk_soc     = '';
    $search_prestation = '';
}

/*
 * View.
 */
$title = $langs->trans('RecurringInvoiceFollowupMenu');

// Build the SQL request.
$sql  = 'SELECT t.rowid, t.ref, t.status, t.fk_soc, t.fk_facture_rec, t.period, t.prestation, t.montant_ttc,';
$sql .= ' t.facture_creee, t.facture_envoyee, t.facture_payee, t.paiement_ok, t.date_relance, t.date_maj_du, t.next_maj_du,';
$sql .= ' t.besoin, s.nom as thirdparty_name, fr.titre as frec_titre';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture_rec as fr ON fr.rowid = t.fk_facture_rec';
$sql .= ' WHERE t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
$sql .= " AND ((t.period >= '" . $db->idate($periodStart) . "' AND t.period <= '" . $db->idate($periodEnd) . "')";
$sql .= " OR (t.period < '" . $db->idate($todayMonthStart) . "' AND t.facture_payee = 0 AND t.status = 1))";
if (dol_strlen($search_ref)) {
    $sql .= natural_search('t.ref', $search_ref);
}
if ($search_fk_soc > 0) {
    $sql .= ' AND t.fk_soc = ' . ((int) $search_fk_soc);
}
if (dol_strlen($search_prestation)) {
    $sql .= natural_search('t.prestation', $search_prestation);
}

// Count total records.
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
    $resql            = $db->query($sql);
    $nbtotalofrecords = $resql ? $db->num_rows($resql) : 0;
    if (($page * $limit) > $nbtotalofrecords) {
        $page   = 0;
        $offset = 0;
    }
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
    $sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}
$num = $db->num_rows($resql);

saturne_header(0, '', $title, '');

$prevMonth  = dol_print_date(dol_time_plus_duree($periodStart, -1, 'm'), '%Y-%m');
$nextMonth  = dol_print_date(dol_time_plus_duree($periodStart, 1, 'm'), '%Y-%m');
$monthLabel = dol_print_date($periodStart, '%B %Y');
$navBase    = $_SERVER['PHP_SELF'] . '?search_month=';

print '<style>
.rcf-dash{margin:0 0 14px}
.rcf-nav{display:flex;align-items:center;gap:12px;margin-bottom:12px;font-size:1.1em}
.rcf-nav .rcf-month{font-weight:bold;text-transform:capitalize;min-width:150px;text-align:center}
.rcf-nav a{text-decoration:none;padding:4px 10px;border:1px solid var(--colortopbordertitle1,#ccc);border-radius:7px}
.rcf-nav a:hover{border-color:#2f6f9f;color:#2f6f9f}
.rcf-nav select{padding:5px 9px;border:1px solid var(--colortopbordertitle1,#ccc);border-radius:7px;background:var(--colorbacklinepair2,#fff);font-size:.95em;font-weight:600;color:inherit;cursor:pointer}
.rcf-nav select:hover{border-color:#2f6f9f}
.rcf-datesel{display:inline-flex;gap:6px;align-items:center}
.rcf-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:10px}
.rcf-tile{border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;padding:10px 12px;background:var(--colorbacklinepair2,#fff);position:relative;overflow:hidden}
.rcf-tile:before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:#2f6f9f}
.rcf-tile.warn:before{background:#c8871a}.rcf-tile.crit:before{background:#cf4257}.rcf-tile.good:before{background:#2e9e6c}
.rcf-tile .k{font-size:.82em;color:#777;font-weight:600}
.rcf-tile .v{font-size:1.7em;font-weight:800;line-height:1.1}
.rcf-tile.warn .v{color:#c8871a}.rcf-tile.crit .v{color:#cf4257}.rcf-tile.good .v{color:#2e9e6c}
.rcf-chartbox{border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;padding:12px 14px;background:var(--colorbacklinepair2,#fff);overflow:hidden;margin:8px 0 14px}
.rcf-charttitle{font-weight:600;font-size:.9em;color:#555;margin-bottom:8px}
.rcf-canvaswrap{position:relative;height:230px;width:100%}
.rcf-canvaswrap canvas{max-height:230px}
</style>';

// Month navigation.
$monthNamesFull = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
$yNow = (int) dol_print_date(dol_now(), '%Y');
print '<div class="rcf-nav">';
print '<a href="' . $navBase . $prevMonth . '" title="' . $langs->trans('Previous') . '">&#8592;</a>';
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" class="rcf-datesel">';
print '<select name="search_monthnum" onchange="this.form.submit()">';
foreach ($monthNamesFull as $mn => $mlabel) {
    print '<option value="' . $mn . '"' . ($mn == $monthMonth ? ' selected' : '') . '>' . $mlabel . '</option>';
}
print '</select>';
print '<select name="search_year" onchange="this.form.submit()">';
for ($y = $yNow - 2; $y <= $yNow + 4; $y++) {
    print '<option value="' . $y . '"' . ($y == $monthYear ? ' selected' : '') . '>' . $y . '</option>';
}
print '</select>';
print '</form>';
print '<a href="' . $navBase . $nextMonth . '" title="' . $langs->trans('Next') . '">&#8594;</a>';
print '</div>';

/*
 * Chart on top: recurring-invoice amount over the 12 months of the browsed year.
 */
$chartYear = $monthYear;
$faByMonth = array_fill(1, 12, 0.0);
$sqlChartFa  = 'SELECT MONTH(period) as m, SUM(montant_ttc) as tot FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup';
$sqlChartFa .= ' WHERE entity IN (' . getEntity('reedcrm_facturerec_followup') . ') AND status = 1 AND YEAR(period) = ' . $chartYear . ' GROUP BY m';
$resChartFa  = $db->query($sqlChartFa);
if ($resChartFa) {
    while ($o = $db->fetch_object($resChartFa)) {
        $faByMonth[(int) $o->m] = (float) $o->tot;
    }
}
$monthLabels = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

print '<div class="rcf-chartbox"><div class="rcf-charttitle">' . $langs->trans('FollowupChartFaAmount') . ' — ' . $chartYear . '</div><div class="rcf-canvaswrap"><canvas id="rcfChartFa"></canvas></div></div>';
print '<script src="' . DOL_URL_ROOT . '/includes/nnnick/chartjs/dist/chart.min.js"></script>';
print '<script>
(function() {
    if (typeof Chart === "undefined") { return; }
    var eur = function(v){ return v.toLocaleString("fr-FR") + " €"; };
    new Chart(document.getElementById("rcfChartFa"), {
        type: "bar",
        data: { labels: ' . json_encode($monthLabels) . ', datasets: [{ label: "' . dol_escape_js($langs->transnoentities('FollowupChartFaAmount')) . '", data: ' . json_encode(array_map('round', array_values($faByMonth))) . ', backgroundColor: "#2f6f9f", borderRadius: 4, maxBarThickness: 34 }] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c){ return eur(c.parsed.y); } } } },
            scales: { y: { beginAtZero: true, grid: { color: "rgba(120,130,150,.15)" }, ticks: { callback: eur } }, x: { grid: { display: false } } } }
    });
})();
</script>';

/*
 * Monthly dashboard.
 */
$dash = reedcrmFollowupGetDashboardData($db, $periodStart, $periodEnd);
print '<div class="rcf-dash"><div class="rcf-tiles">';
printf('<div class="rcf-tile warn"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupStatusToBill'), $dash['counts']['tobill']);
printf('<div class="rcf-tile warn"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupStatusToSend'), $dash['counts']['tosend']);
printf('<div class="rcf-tile crit"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupStatusLate'), $dash['counts']['late']);
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%d / %d</div></div>', $langs->trans('FollowupStatusPaid'), $dash['counts']['paid'], $dash['counts']['total']);
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupAmountTTC'), price($dash['montant_ttc'], 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupSavTime'), ($dash['temps_sav'] > 0 ? convertSecondToTime($dash['temps_sav'], 'allhourmin') : '0'));
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupSalesAmount'), price($dash['montant_pr'], 0, $langs, 1, -1, 0, $conf->currency));
print '</div></div>';

// Search parameters kept across pages.
$param = '&search_month=' . urlencode($search_month);
if (!empty($optioncss)) {
    $param .= '&optioncss=' . urlencode($optioncss);
}
if (dol_strlen($search_ref)) {
    $param .= '&search_ref=' . urlencode($search_ref);
}
if ($search_fk_soc > 0) {
    $param .= '&search_fk_soc=' . urlencode((string) $search_fk_soc);
}
if (dol_strlen($search_prestation)) {
    $param .= '&search_prestation=' . urlencode($search_prestation);
}

$newCardButton = '';
if ($permissiontoadd) {
    $newCardButton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/reedcrm/view/recurringinvoicefollowup_card.php', 1) . '?action=create');
}

print '<form method="POST" id="searchFormList" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'object_' . $object->picto, 0, $newCardButton, '', $limit, 0, 0, 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">';

// Search filter row.
print '<tr class="liste_titre">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '"></td>';
print '<td class="liste_titre">' . $formcompany->select_company($search_fk_soc, 'search_fk_soc', '', $langs->trans('All'), 0, 0, [], 0, 'minwidth150 maxwidth200') . '</td>';
print '<td class="liste_titre">' . $form->selectarray('search_prestation', $object->fields['prestation']['arrayofkeyval'], $search_prestation, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150') . '</td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center maxwidthsearch">';
print $form->showFilterButtons();
print '</td>';
print '</tr>';

// Title row.
print '<tr class="liste_titre">';
print getTitleFieldOfList($langs->trans('Ref'), 0, $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('ThirdParty'), 0, $_SERVER['PHP_SELF'], 't.fk_soc', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupSubscription'), 0, $_SERVER['PHP_SELF'], 't.prestation', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupAmountTTC'), 0, $_SERVER['PHP_SELF'], 't.montant_ttc', '', $param, 'class="right"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('Period'), 0, $_SERVER['PHP_SELF'], 't.period', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupInvoiceCreated'), 0, $_SERVER['PHP_SELF'], 't.facture_creee', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupInvoicePaid'), 0, $_SERVER['PHP_SELF'], 't.facture_payee', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupRelanceDate'), 0, $_SERVER['PHP_SELF'], 't.date_relance', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupDuUpdateBilledDate'), 0, $_SERVER['PHP_SELF'], 't.date_maj_du', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupDuNextUpdate'), 0, $_SERVER['PHP_SELF'], 't.next_maj_du', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('Status'), 0, $_SERVER['PHP_SELF'], '', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList('', 0, $_SERVER['PHP_SELF'], '', '', $param, 'class="center maxwidthsearch"', $sortfield, $sortorder);
print '</tr>';

// Data rows.
$i           = 0;
$totalTtc    = 0;
$cardUrlBase = dol_buildpath('/reedcrm/view/recurringinvoicefollowup_card.php', 1);
while ($i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);
    if (!$obj) {
        break;
    }
    $object->setVarsFromFetchObj($obj);
    $followupStatus = $object->getFollowupStatus();
    $totalTtc      += (float) $obj->montant_ttc;
    $cardUrl        = $cardUrlBase . '?id=' . $obj->rowid;

    print '<tr class="oddeven">';
    if (!empty($obj->fk_facture_rec) && dol_strlen($obj->frec_titre) > 0) {
        print '<td class="tdoverflowmax200"><a href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . ((int) $obj->fk_facture_rec) . '" title="' . dol_escape_htmltag($obj->frec_titre) . '">' . img_object('', 'bill') . ' ' . dol_escape_htmltag($obj->frec_titre) . '</a></td>';
    } else {
        print '<td><a href="' . $cardUrl . '">' . dol_escape_htmltag($obj->ref) . '</a></td>';
    }
    print '<td class="tdoverflowmax150">' . dol_escape_htmltag($obj->thirdparty_name) . '</td>';
    print '<td>' . dol_escape_htmltag(isset($object->fields['prestation']['arrayofkeyval'][$obj->prestation]) ? $langs->trans($object->fields['prestation']['arrayofkeyval'][$obj->prestation]) : $obj->prestation) . '</td>';
    print '<td class="right">' . (dol_strlen($obj->montant_ttc) ? price($obj->montant_ttc, 0, $langs, 1, -1, -1, $conf->currency) : '') . '</td>';
    $periodTs    = !empty($obj->period) ? $db->jdate($obj->period) : 0;
    $isFaOverdue = ($periodTs && $periodTs < $todayMonthStart && empty($obj->facture_payee));
    print '<td class="center nowraponall">' . ($periodTs ? dol_print_date($periodTs, '%m/%Y') : '');
    if ($isFaOverdue) {
        print ' <span style="color:#cf4257;font-weight:bold" title="' . dol_escape_htmltag($langs->trans('FollowupLate')) . '"><i class="fas fa-exclamation-triangle"></i> ' . ((int) floor((dol_now() - $periodTs) / 86400)) . $langs->trans('FollowupDaysLateShort') . '</span>';
    }
    print '</td>';
    print '<td class="center">' . yn($obj->facture_creee) . '</td>';
    print '<td class="center">' . yn($obj->facture_payee) . '</td>';
    print '<td class="center">' . (!empty($obj->date_relance) ? dol_print_date($db->jdate($obj->date_relance), 'day') : '') . '</td>';
    print '<td class="center">' . (!empty($obj->date_maj_du) ? dol_print_date($db->jdate($obj->date_maj_du), 'day') : '') . '</td>';
    print '<td class="center">' . (!empty($obj->next_maj_du) ? dol_print_date($db->jdate($obj->next_maj_du), 'day') : '') . '</td>';
    print '<td class="center">' . dolGetStatus($followupStatus['label'], $followupStatus['label'], '', $followupStatus['badge'], 3) . '</td>';
    print '<td class="center nowraponall">';
    if (!empty($obj->fk_facture_rec)) {
        print '<a class="paddingright" href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . ((int) $obj->fk_facture_rec) . '" title="' . dol_escape_htmltag($langs->trans('FollowupGenerateInvoice')) . '"><i class="fas fa-file-invoice-dollar" style="color:#2f6f9f"></i></a>';
    }
    print '<a class="editfielda" href="' . $cardUrl . '&action=edit&token=' . newToken() . '">' . img_edit() . '</a>';
    print '</td>';
    print '</tr>';
    $i++;
}

if ($num > 0) {
    print '<tr class="liste_total">';
    print '<td>' . $langs->trans('Total') . '</td>';
    print '<td></td><td></td>';
    print '<td class="right">' . price($totalTtc, 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
    print '<td colspan="8"></td>';
    print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

// End of page.
llxFooter();
$db->close();
