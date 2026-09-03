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
// Billing run of the month: '' = all, 'todo' = no invoice generated yet, 'done' = invoice generated.
$search_done = GETPOST('search_done', 'alpha');
if (!in_array($search_done, ['todo', 'done'], true)) {
    $search_done = '';
}
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
    $sortfield = 'fr.date_when';
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

// Dismiss (hide) a client from the "Digirisk without subscription" list.
if ($action === 'digidismiss' && $permissiontoadd) {
    $hideSocid = GETPOSTINT('hide_socid');
    if ($hideSocid > 0) {
        $db->query('INSERT IGNORE INTO ' . MAIN_DB_PREFIX . 'reedcrm_digirisk_dismissed (entity, fk_soc, date_creation, fk_user_creat) VALUES (' . ((int) $conf->entity) . ', ' . $hideSocid . ", '" . $db->idate(dol_now()) . "', " . ((int) $user->id) . ')');
        setEventMessages($langs->trans('FollowupDigiriskDismissed'), []);
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?search_month=' . urlencode(GETPOST('search_month', 'alpha')));
    exit;
}

// Purge search criteria.
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_ref        = '';
    $search_fk_soc     = '';
    $search_prestation = '';
    $search_done       = '';
}

/*
 * View.
 */
$title = $langs->trans('RecurringInvoiceFollowupMenu');

// Build the SQL request. Source = active recurring templates (factures modèles), read live so the
// list is always up to date; the stored follow-up (t) only carries manual annotations + billing sync.
if (!in_array($sortfield, ['fr.date_when', 'fr.titre', 'fr.fk_soc', 'fr.total_ttc', 't.prestation', 't.facture_creee', 't.facture_payee', 't.date_relance', 't.date_maj_du', 't.next_maj_du', 'fa.ref', 'fa.datef'], true)) {
    $sortfield = 'fr.date_when';
}
$sqlFields  = 'SELECT fr.rowid as frec_id, fr.titre as frec_titre, fr.total_ttc as montant_ttc, fr.date_when as period, fr.fk_soc, fr.suspended,';
$sqlFields .= ' t.rowid as followup_id, t.prestation, t.facture_creee, t.facture_envoyee, t.facture_payee, t.paiement_ok, t.date_relance, t.date_maj_du, t.next_maj_du, t.besoin,';
$sqlFields .= ' fa.rowid as gen_facture_id, fa.ref as gen_facture_ref, fa.datef as gen_date, fa.paye as gen_paye, fa.fk_statut as gen_statut, fa.total_ttc as gen_total_ttc,';
$sqlFields .= ' s.nom as thirdparty_name';

$sqlFrom  = ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
$sqlFrom .= reedcrmFollowupMonthJoinsSql($db, $periodStart, $periodEnd);
$sqlFrom .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = fr.fk_soc';

$sqlWhere = ' WHERE fr.entity IN (' . getEntity('facturerec') . ') AND fr.frequency > 0 AND fr.fk_soc > 0';
// A template belongs to the browsed month for one of two reasons:
//  - its real next generation date (date_when) falls in that month AND year, exactly like the native
//    "Factures modèles" filter — so the date shown always matches the template card: still to bill;
//  - an invoice was really generated from it that month (fa) — already billed. This second branch keeps
//    the traceability of finished months: once generated, date_when has moved on to the next period, and
//    the template may even have been suspended since.
$sqlWhere .= ' AND ((fr.suspended = 0 AND MONTH(fr.date_when) = ' . ((int) $monthMonth) . ' AND YEAR(fr.date_when) = ' . ((int) $monthYear) . ') OR fa.rowid IS NOT NULL)';
if (dol_strlen($search_ref)) {
    $sqlWhere .= natural_search('fr.titre', $search_ref);
}
if ($search_fk_soc > 0) {
    $sqlWhere .= ' AND fr.fk_soc = ' . ((int) $search_fk_soc);
}
if (dol_strlen($search_prestation)) {
    $sqlWhere .= natural_search('t.prestation', $search_prestation);
}
if ($search_done === 'done') {
    $sqlWhere .= ' AND fa.rowid IS NOT NULL';
} elseif ($search_done === 'todo') {
    $sqlWhere .= ' AND fa.rowid IS NULL';
}

// Count total records. COUNT(*), not a full fetch of every row just to call num_rows on it.
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
    $resqlCount       = $db->query('SELECT COUNT(*) as nb' . $sqlFrom . $sqlWhere);
    $objCount         = $resqlCount ? $db->fetch_object($resqlCount) : null;
    $nbtotalofrecords = $objCount ? (int) $objCount->nb : 0;
    if (($page * $limit) > $nbtotalofrecords) {
        $page   = 0;
        $offset = 0;
    }
}

$sql  = $sqlFields . $sqlFrom . $sqlWhere;
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
.rcf-run{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:12px;font-weight:700;font-size:.86em;white-space:nowrap;border:1px solid}
.rcf-run.done{background:#e6f5ee;color:#1b7048;border-color:#a9dcc5}
.rcf-run.todo{background:#fdf3e0;color:#8f5c0d;border-color:#f0d3a0}
.rcf-run.late{background:#fdeaed;color:#a02536;border-color:#f2b8c1}
.rcf-tilelink{display:block;text-decoration:none;color:inherit}
.rcf-tilelink:hover{border-color:#2f6f9f}
.rcf-tileon{box-shadow:0 0 0 2px #2f6f9f inset}
.rcf-mvt{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin:8px 0 14px}
.rcf-mvtbox{border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;overflow:hidden;background:var(--colorbacklinepair2,#fff)}
.rcf-mvthead{padding:9px 12px;font-weight:600;display:flex;justify-content:space-between;align-items:center;gap:10px;border-bottom:1px solid var(--colortopbordertitle1,#eee)}
.rcf-mvtbox.in .rcf-mvthead{color:#2e9e6c}.rcf-mvtbox.out .rcf-mvthead{color:#cf4257}
.rcf-mvthead .amt{font-size:1.05em}
.rcf-mvtbox table{width:100%;border-collapse:collapse}
.rcf-mvtbox td{padding:5px 12px;border-top:1px solid var(--colortopbordertitle1,#f0f0f0);font-size:.92em}
.rcf-mvtbox td.a{text-align:right;white-space:nowrap;font-weight:600}
.rcf-mvtempty{padding:12px;text-align:center}
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
// Annual recurring calendar: amount per billing month across all active templates (same every year).
$sqlChartFa  = 'SELECT MONTH(fr.date_when) as m, SUM(fr.total_ttc) as tot FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
$sqlChartFa .= ' WHERE fr.entity IN (' . getEntity('facturerec') . ') AND fr.suspended = 0 AND fr.frequency > 0 AND fr.fk_soc > 0 GROUP BY m';
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
// Billing run of the month: kept visible even once everything is generated (traceability).
printf('<a class="rcf-tile warn rcf-tilelink%s" href="%s"><div class="k">%s</div><div class="v">%d</div></a>', ($search_done === 'todo' ? ' rcf-tileon' : ''), $_SERVER['PHP_SELF'] . '?search_month=' . urlencode($search_month) . ($search_done === 'todo' ? '' : '&search_done=todo'), $langs->trans('FollowupRunToDo'), $dash['counts']['todo']);
printf('<a class="rcf-tile good rcf-tilelink%s" href="%s"><div class="k">%s</div><div class="v">%d / %d</div></a>', ($search_done === 'done' ? ' rcf-tileon' : ''), $_SERVER['PHP_SELF'] . '?search_month=' . urlencode($search_month) . ($search_done === 'done' ? '' : '&search_done=done'), $langs->trans('FollowupRunDone'), $dash['counts']['done'], $dash['counts']['total']);
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
if (dol_strlen($search_done)) {
    $param .= '&search_done=' . urlencode($search_done);
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
// Without this the browsed month is lost on every filter submit and the page silently falls back to
// the current month: the filtered result would then not be the month being looked at.
print '<input type="hidden" name="search_month" value="' . dol_escape_htmltag($search_month) . '">';

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
// Billing run filter: to do (no invoice generated yet) / done (invoice generated this month).
print '<td class="liste_titre center">' . $form->selectarray('search_done', ['todo' => $langs->trans('FollowupRunToDo'), 'done' => $langs->trans('FollowupRunDone')], $search_done, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100') . '</td>';
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
print getTitleFieldOfList($langs->trans('Ref'), 0, $_SERVER['PHP_SELF'], 'fr.titre', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('ThirdParty'), 0, $_SERVER['PHP_SELF'], 'fr.fk_soc', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupSubscription'), 0, $_SERVER['PHP_SELF'], 't.prestation', '', $param, '', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupAmountTTC'), 0, $_SERVER['PHP_SELF'], 'fr.total_ttc', '', $param, 'class="right"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('Period'), 0, $_SERVER['PHP_SELF'], 'fr.date_when', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupRunStatus'), 0, $_SERVER['PHP_SELF'], 'fa.datef', '', $param, 'class="center"', $sortfield, $sortorder, '', 0, $langs->trans('FollowupRunStatusHelp'));
print getTitleFieldOfList($langs->trans('FollowupGeneratedInvoice'), 0, $_SERVER['PHP_SELF'], 'fa.ref', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupInvoicePaid'), 0, $_SERVER['PHP_SELF'], 't.facture_payee', '', $param, 'class="center"', $sortfield, $sortorder);
print getTitleFieldOfList($langs->trans('FollowupRelanceDate'), 0, $_SERVER['PHP_SELF'], 't.date_relance', '', $param, 'class="center"', $sortfield, $sortorder);
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
    // Fill missing (unseeded) annotation defaults so the object helpers work on live rows.
    if (empty($obj->prestation)) {
        $obj->prestation = reedcrmFollowupGuessPrestation((string) $obj->frec_titre);
    }
    // When the invoice for the browsed month was really generated, derive billing status from it.
    $genTs = !empty($obj->gen_date) ? $db->jdate($obj->gen_date) : 0;
    if ($genTs) {
        $obj->facture_creee = 1;
        $obj->facture_payee = (int) $obj->gen_paye;
    }
    $object->setVarsFromFetchObj($obj);
    $followupStatus = $object->getFollowupStatus();
    $totalTtc      += (float) $obj->montant_ttc;
    $cardUrl        = $cardUrlBase . '?frec=' . ((int) $obj->frec_id);

    print '<tr class="oddeven">';
    print '<td class="tdoverflowmax200"><a href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . ((int) $obj->frec_id) . '" title="' . dol_escape_htmltag($obj->frec_titre) . '">' . img_object('', 'bill') . ' ' . dol_escape_htmltag($obj->frec_titre) . '</a>';
    // The template may have been suspended after this month was billed: say it so the line stays readable.
    if (!empty($obj->suspended)) {
        print ' <span class="badge badge-status8" title="' . dol_escape_htmltag($langs->trans('FollowupTemplateSuspendedHelp')) . '">' . $langs->trans('FollowupTemplateSuspended') . '</span>';
    }
    print '</td>';
    print '<td class="tdoverflowmax150">' . dol_escape_htmltag($obj->thirdparty_name) . '</td>';
    print '<td>' . dol_escape_htmltag(isset($object->fields['prestation']['arrayofkeyval'][$obj->prestation]) ? $langs->trans($object->fields['prestation']['arrayofkeyval'][$obj->prestation]) : $obj->prestation) . '</td>';
    print '<td class="right">' . (dol_strlen($obj->montant_ttc) ? price($obj->montant_ttc, 0, $langs, 1, -1, -1, $conf->currency) : '') . '</td>';
    // The real next generation date (date_when), which the filter already places in the browsed
    // month+year — so it always matches the template card. Fall back to the generated invoice date if any.
    $periodTs  = !empty($obj->period) ? $db->jdate($obj->period) : 0;
    $displayTs = $genTs ? $genTs : $periodTs;
    $isFaOverdue = (!$genTs && $displayTs && $displayTs < $todayMonthStart && empty($obj->facture_payee));
    print '<td class="center nowraponall">' . ($displayTs ? dol_print_date($displayTs, 'day') : '');
    if ($isFaOverdue) {
        print ' <span style="color:#cf4257;font-weight:bold" title="' . dol_escape_htmltag($langs->trans('FollowupLate')) . '"><i class="fas fa-exclamation-triangle"></i> ' . ((int) floor((dol_now() - $displayTs) / 86400)) . $langs->trans('FollowupDaysLateShort') . '</span>';
    }
    print '</td>';
    // "To do / done" for the month, spelled out so the month's progress is readable at a glance:
    // a finished month stays traceable instead of showing an empty list.
    print '<td class="center nowraponall">';
    if ($genTs) {
        print '<span class="rcf-run done" title="' . dol_escape_htmltag($langs->trans('FollowupRunDoneOn', dol_print_date($genTs, 'day'))) . '"><i class="fas fa-check-circle"></i>' . $langs->trans('FollowupRunDone') . '</span>';
    } elseif ($isFaOverdue) {
        print '<span class="rcf-run late" title="' . dol_escape_htmltag($langs->trans('FollowupRunLateHelp')) . '"><i class="fas fa-exclamation-triangle"></i>' . $langs->trans('FollowupRunLate') . '</span>';
    } else {
        print '<span class="rcf-run todo" title="' . dol_escape_htmltag($langs->trans('FollowupRunToDoHelp')) . '"><i class="far fa-clock"></i>' . $langs->trans('FollowupRunToDo') . '</span>';
    }
    print '</td>';
    // Invoice really generated from this template for the browsed month: the proof of the billing run.
    print '<td class="center tdoverflowmax125">';
    if (!empty($obj->gen_facture_id)) {
        print '<a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . ((int) $obj->gen_facture_id) . '" title="' . dol_escape_htmltag($obj->gen_facture_ref . ' - ' . price($obj->gen_total_ttc, 0, $langs, 1, -1, -1, $conf->currency)) . '">' . img_object('', 'bill') . ' ' . dol_escape_htmltag($obj->gen_facture_ref) . '</a>';
    } else {
        print '<span class="opacitymedium">-</span>';
    }
    print '</td>';
    print '<td class="center">' . yn($obj->facture_payee) . '</td>';
    print '<td class="center">' . (!empty($obj->date_relance) ? dol_print_date($db->jdate($obj->date_relance), 'day') : '') . '</td>';
    print '<td class="center">' . dolGetStatus($followupStatus['label'], $followupStatus['label'], '', $followupStatus['badge'], 3) . '</td>';
    print '<td class="center nowraponall">';
    print '<a class="paddingright" href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . ((int) $obj->frec_id) . '" title="' . dol_escape_htmltag($langs->trans('FollowupGenerateInvoice')) . '"><i class="fas fa-file-invoice-dollar" style="color:#2f6f9f"></i></a>';
    print '<a class="editfielda" href="' . $cardUrl . '&action=edit&token=' . newToken() . '">' . img_edit() . '</a>';
    print '</td>';
    print '</tr>';
    $i++;
}

if ($num == 0) {
    // Say why the list is empty: a blank table on a filtered month reads as a bug otherwise.
    $monthName = $monthNamesFull[$monthMonth] . ' ' . $monthYear;
    if ($search_done === 'todo') {
        $emptyMsg = $langs->trans('FollowupRunNothingToDo', $monthName, $dash['counts']['done']);
    } elseif ($search_done === 'done') {
        $emptyMsg = $langs->trans('FollowupRunNothingDone', $monthName);
    } else {
        $emptyMsg = $langs->trans('NoRecordFound');
    }
    print '<tr class="oddeven"><td colspan="11" class="center opacitymedium">' . $emptyMsg . '</td></tr>';
}

if ($num > 0) {
    print '<tr class="liste_total">';
    print '<td>' . $langs->trans('Total') . '</td>';
    print '<td></td><td></td>';
    print '<td class="right">' . price($totalTtc, 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
    print '<td colspan="7"></td>';
    print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

/*
 * Portfolio movements: entries (new subscriptions) and exits (stopped subscriptions) of the year,
 * with the detail of the browsed month. Answers "what did we gain / lose in recurring revenue".
 */
$mvtYear  = reedcrmFollowupGetRecurringMovementsByMonth($db, $monthYear);
$mvtMonth = reedcrmFollowupGetRecurringMovementsForMonth($db, $monthYear, $monthMonth);
$mvtCur   = $mvtYear['months'][$monthMonth];

print load_fiche_titre('<i class="fas fa-exchange-alt paddingright" style="color:#2f6f9f"></i>' . $langs->trans('FollowupMovementsTitle') . ' — ' . $monthYear, '', '');

print '<div class="rcf-dash"><div class="rcf-tiles">';
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupMovementsInMonth'), $mvtCur['in_nb']);
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupMovementsInAmount'), price($mvtCur['in_amount'], 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile crit"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupMovementsOutMonth'), $mvtCur['out_nb']);
printf('<div class="rcf-tile crit"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupMovementsOutAmount'), price($mvtCur['out_amount'], 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile%s"><div class="k">%s</div><div class="v">%s%s</div></div>', ($mvtCur['net_amount'] < 0 ? ' crit' : ($mvtCur['net_amount'] > 0 ? ' good' : '')), $langs->trans('FollowupMovementsNetMonth'), ($mvtCur['net_amount'] > 0 ? '+' : ''), price($mvtCur['net_amount'], 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile%s"><div class="k">%s</div><div class="v">%s%s</div></div>', ($mvtYear['totals']['net_amount'] < 0 ? ' crit' : ($mvtYear['totals']['net_amount'] > 0 ? ' good' : '')), $langs->trans('FollowupMovementsNetYear'), ($mvtYear['totals']['net_amount'] > 0 ? '+' : ''), price($mvtYear['totals']['net_amount'], 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">+%d / -%d</div></div>', $langs->trans('FollowupMovementsNetYearNb'), $mvtYear['totals']['in_nb'], $mvtYear['totals']['out_nb']);
print '</div></div>';

// Chart: entries up, exits down, cumulative net as a line.
$mvtIn      = [];
$mvtOut     = [];
$mvtCumul   = [];
$runningNet = 0.0;
for ($m = 1; $m <= 12; $m++) {
    $mvtIn[]     = round($mvtYear['months'][$m]['in_amount']);
    $mvtOut[]    = -round($mvtYear['months'][$m]['out_amount']);
    $runningNet += $mvtYear['months'][$m]['net_amount'];
    $mvtCumul[]  = round($runningNet);
}

print '<div class="rcf-chartbox"><div class="rcf-charttitle">' . $langs->trans('FollowupMovementsChartTitle') . '</div><div class="rcf-canvaswrap"><canvas id="rcfChartMvt"></canvas></div></div>';
print '<script>
(function() {
    if (typeof Chart === "undefined") { return; }
    var eur = function(v){ return v.toLocaleString("fr-FR") + " €"; };
    new Chart(document.getElementById("rcfChartMvt"), {
        type: "bar",
        data: { labels: ' . json_encode($monthLabels) . ', datasets: [
            { label: "' . dol_escape_js($langs->transnoentities('FollowupMovementsIn')) . '", data: ' . json_encode($mvtIn) . ', backgroundColor: "#2e9e6c", borderRadius: 4, maxBarThickness: 26 },
            { label: "' . dol_escape_js($langs->transnoentities('FollowupMovementsOut')) . '", data: ' . json_encode($mvtOut) . ', backgroundColor: "#cf4257", borderRadius: 4, maxBarThickness: 26 },
            { type: "line", label: "' . dol_escape_js($langs->transnoentities('FollowupMovementsCumul')) . '", data: ' . json_encode($mvtCumul) . ', borderColor: "#2f6f9f", backgroundColor: "#2f6f9f", borderWidth: 2, tension: .3, pointRadius: 3, fill: false }
        ] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: true, position: "bottom", labels: { boxWidth: 12, usePointStyle: true } },
                tooltip: { callbacks: { label: function(c){ return c.dataset.label + " : " + eur(Math.abs(c.parsed.y)); } } } },
            scales: { y: { grid: { color: "rgba(120,130,150,.15)" }, ticks: { callback: eur } }, x: { grid: { display: false } } } }
    });
})();
</script>';

// Detail of the browsed month: which subscriptions came in, which ones stopped.
print '<div class="rcf-mvt">';
foreach (['in' => 'FollowupMovementsInMonth', 'out' => 'FollowupMovementsOutMonth'] as $way => $labelKey) {
    $rowsMvt   = $mvtMonth[$way];
    $amountSum = 0.0;
    foreach ($rowsMvt as $r) {
        $amountSum += $r['montant_ttc'];
    }
    print '<div class="rcf-mvtbox ' . $way . '">';
    print '<div class="rcf-mvthead"><span>' . ($way === 'in' ? '<i class="fas fa-arrow-up paddingright"></i>' : '<i class="fas fa-arrow-down paddingright"></i>') . $langs->trans($labelKey) . ' <span class="badge">' . count($rowsMvt) . '</span></span>';
    print '<span class="amt">' . ($way === 'in' ? '+' : '-') . price($amountSum, 0, $langs, 1, -1, 0, $conf->currency) . '</span></div>';
    if (empty($rowsMvt)) {
        print '<div class="rcf-mvtempty opacitymedium">' . $langs->trans($way === 'in' ? 'FollowupMovementsNoIn' : 'FollowupMovementsNoOut') . '</div>';
    } else {
        $socMvt = new Societe($db);
        print '<table>';
        foreach ($rowsMvt as $r) {
            $socMvt->id     = $r['fk_soc'];
            $socMvt->name   = $r['thirdparty'];
            $socMvt->status = $r['soc_status'];
            print '<tr>';
            print '<td class="tdoverflowmax150">' . $socMvt->getNomUrl(1) . '</td>';
            print '<td class="tdoverflowmax200"><a href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . $r['frec_id'] . '" title="' . dol_escape_htmltag($r['titre']) . '">' . dol_escape_htmltag(dol_trunc($r['titre'], 34)) . '</a></td>';
            print '<td class="center nowraponall opacitymedium">' . ($r['date'] ? dol_print_date($r['date'], 'day') : '') . '</td>';
            print '<td class="a">' . price($r['montant_ttc'], 0, $langs, 1, -1, 0, $conf->currency) . '</td>';
            print '</tr>';
        }
        print '</table>';
    }
    print '</div>';
}
print '</div>';

// --- Digirisk users without an active recurring subscription ---
$digiNoSub = reedcrmFollowupGetDigiriskWithoutSubscription($db);
print '<br>';
print load_fiche_titre('<i class="fas fa-exclamation-triangle paddingright" style="color:#c8871a"></i>' . $langs->trans('FollowupDigiriskNoSub') . ' <span class="badge">' . count($digiNoSub) . '</span>', '', '');
print '<div class="div-table-responsive"><table class="tagtable liste">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('ThirdParty') . '</th><th>' . $langs->trans('FollowupLocation') . '</th>';
print '<th>' . $langs->trans('FollowupDigiriskInstance') . '</th>';
print '<th>' . $langs->trans('FollowupDigiriskLastTier') . '</th><th class="center">' . $langs->trans('FollowupDigiriskLastInvoice') . '</th>';
print '<th class="center maxwidthsearch"></th>';
print '</tr>';
if (empty($digiNoSub)) {
    print '<tr class="oddeven"><td colspan="6" class="center opacitymedium">' . $langs->trans('FollowupDigiriskNoSubEmpty') . '</td></tr>';
} else {
    $socStatic = new Societe($db);
    foreach ($digiNoSub as $c) {
        $socStatic->id     = $c['fk_soc'];
        $socStatic->name   = $c['thirdparty'];
        $socStatic->status = 1;
        print '<tr class="oddeven">';
        print '<td class="tdoverflowmax200">' . $socStatic->getNomUrl(1) . '</td>';
        print '<td class="tdoverflowmax150">' . ($c['location'] !== '' ? '<i class="fas fa-map-marker-alt paddingright opacitymedium"></i>' . dol_escape_htmltag($c['location']) : '<span class="opacitymedium">-</span>') . '</td>';
        print '<td class="tdoverflowmax250">';
        if (!empty($c['project_id']) && !empty($c['instance'])) {
            print '<a href="' . DOL_URL_ROOT . '/projet/card.php?id=' . ((int) $c['project_id']) . '" target="_blank" rel="noopener" title="' . dol_escape_htmltag($c['instance']) . '"><i class="fas fa-project-diagram paddingright opacitymedium"></i>' . dol_escape_htmltag($c['instance']) . '</a>';
        } else {
            print '<span class="opacitymedium">-</span>';
        }
        print '</td>';
        print '<td class="tdoverflowmax300" title="' . dol_escape_htmltag((string) $c['last_tier']) . '">' . ($c['last_tier'] !== null && $c['last_tier'] !== '' ? dol_escape_htmltag($c['last_tier']) : '<span class="opacitymedium">-</span>') . '</td>';
        print '<td class="center nowraponall">' . (!empty($c['last_date']) ? dol_print_date($c['last_date'], 'day') : '') . '</td>';
        print '<td class="center nowraponall">';
        print '<a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/compta/facture/list.php?socid=' . ((int) $c['fk_soc']) . '" title="' . dol_escape_htmltag($langs->trans('FollowupDigiriskCreateSubHelp')) . '"><i class="fas fa-sync-alt paddingright"></i>' . $langs->trans('FollowupDigiriskCreateSub') . '</a> ';
        if ($permissiontoadd) {
            print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" class="inline-block" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('FollowupDigiriskDismissConfirm')) . '\');">';
            print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="digidismiss"><input type="hidden" name="hide_socid" value="' . ((int) $c['fk_soc']) . '"><input type="hidden" name="search_month" value="' . dol_escape_htmltag($search_month) . '">';
            print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupDigiriskDismiss')) . '"><i class="fas fa-trash"></i></button>';
            print '</form>';
        }
        print '</td>';
        print '</tr>';
    }
}
print '</table></div>';

// End of page.
llxFooter();
$db->close();
