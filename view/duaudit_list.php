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
 * \file    view/duaudit_list.php
 * \ingroup reedcrm
 * \brief   Client DU follow-up: DU audit charts, audits of the month and overdue audits.
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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../class/duaudit.class.php';
require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs();

// Parameters.
$action       = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
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
$monthYear   = (int) substr($search_month, 0, 4);
$monthMonth  = (int) substr($search_month, 5, 2);
$periodStart = dol_get_first_day($monthYear, $monthMonth);
$periodEnd   = dol_get_last_day($monthYear, $monthMonth);

$formcompany = new FormCompany($db);
$form        = new Form($db);
$propalStatic = new Propal($db);
$hookmanager->initHooks(['duauditlist']);

// Security check (reuse the followup permissions).
$permissiontoread   = $user->hasRight('reedcrm', 'followup', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'followup', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'followup', 'delete');

saturne_check_access($permissiontoread);

/*
 * Actions on DU audits.
 */
if ($action === 'addaudit' && $permissiontoadd) {
    $auditSoc  = GETPOSTINT('audit_fk_soc');
    $auditDate = dol_stringtotime(GETPOST('audit_date', 'alpha'));
    if ($auditSoc > 0 && $auditDate) {
        // One audit per client: update the existing one if any, otherwise create it.
        $existingId  = 0;
        $resqlExists = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit WHERE fk_soc = ' . ((int) $auditSoc) . ' AND entity IN (' . getEntity('reedcrm_du_audit') . ') LIMIT 1');
        if ($resqlExists && $objExists = $db->fetch_object($resqlExists)) {
            $existingId = (int) $objExists->rowid;
        }
        $audit = new DuAudit($db);
        if ($existingId > 0) {
            $audit->fetch($existingId);
        }
        $audit->fk_soc          = $auditSoc;
        $audit->next_audit_date = $auditDate;
        $audit->status          = DuAudit::STATUS_TODO;
        $noteInput              = GETPOST('audit_note', 'alphanohtml');
        if ($noteInput !== '') {
            $audit->note = $noteInput;
        }
        $montantInput = price2num(GETPOST('audit_montant', 'alpha'));
        if ($montantInput !== '' && (float) $montantInput != 0) {
            $audit->montant = (float) $montantInput;
        }
        if ($existingId > 0) {
            $result = $audit->update($user);
        } else {
            $audit->source = 'manual';
            $result        = $audit->create($user);
        }
        if ($result > 0) {
            setEventMessages($langs->trans('FollowupAuditAdded'), []);
        } else {
            setEventMessages($audit->error, $audit->errors, 'errors');
        }
    }
}
if ($action === 'auditmove' && $permissiontoadd) {
    $auditId   = GETPOSTINT('audit_id');
    $auditDate = dol_stringtotime(GETPOST('audit_date', 'alpha'));
    $audit     = new DuAudit($db);
    if ($auditId > 0 && $auditDate && $audit->fetch($auditId) > 0) {
        $audit->next_audit_date = $auditDate;
        $audit->update($user);
    }
}
if ($action === 'auditassign' && $permissiontoadd) {
    // Assign the DU audit to a user (0 = unassign) so amounts can be tracked per person.
    $auditId = GETPOSTINT('audit_id');
    $audit   = new DuAudit($db);
    if ($auditId > 0 && $audit->fetch($auditId) > 0) {
        $audit->fk_user_assign = GETPOSTINT('assign_user') ?: null;
        $audit->update($user);
    }
}
if ($action === 'auditrenew' && $permissiontoadd) {
    // Roll a client's DU line forward to the next yearly cycle. If a newer DU_AU invoice exists,
    // rebase on it (new amount + date); otherwise just advance the planned date by one year.
    $auditId = GETPOSTINT('audit_id');
    $audit   = new DuAudit($db);
    if ($auditId > 0 && $audit->fetch($auditId) > 0) {
        $sqlInv  = 'SELECT f.rowid, f.datef, SUM(fd.total_ttc) as tot FROM ' . MAIN_DB_PREFIX . 'facture as f';
        $sqlInv .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet as fd ON fd.fk_facture = f.rowid';
        $sqlInv .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = fd.fk_product';
        $sqlInv .= " WHERE p.ref LIKE 'DU\_AU%' AND f.type <> 2 AND f.fk_soc = " . ((int) $audit->fk_soc);
        $sqlInv .= ' AND f.datef IS NOT NULL AND f.entity IN (' . getEntity('facture') . ')';
        $sqlInv .= ' GROUP BY f.rowid, f.datef ORDER BY f.datef DESC' . $db->plimit(1);
        $resqlInv = $db->query($sqlInv);
        $rolled   = false;
        if ($resqlInv && $inv = $db->fetch_object($resqlInv)) {
            $invDate = $db->jdate($inv->datef);
            $curLast = !empty($audit->last_audit_date) ? (int) $audit->last_audit_date : 0;
            if ($invDate > $curLast) {
                $audit->last_audit_date    = $invDate;
                $audit->montant            = (float) $inv->tot;
                $audit->next_audit_date    = dol_time_plus_duree($invDate, 1, 'y');
                $audit->fk_facture_source  = (int) $inv->rowid;
                $audit->source             = 'invoice';
                $audit->status             = DuAudit::STATUS_TODO;
                $audit->proposal_sent_date = null; // new cycle needs a new proposal
                $rolled                    = true;
            }
        }
        if ($rolled) {
            if ($audit->update($user) > 0) {
                setEventMessages($langs->trans('FollowupAuditRenewedOn', dol_print_date($audit->next_audit_date, '%B %Y')), []);
            } else {
                setEventMessages($audit->error, $audit->errors, 'errors');
            }
        } else {
            // No DU_AU invoice newer than the current cycle: don't silently jump a year ahead.
            setEventMessages($langs->trans('FollowupAuditRenewNoInvoice'), null, 'warnings');
        }
    }
}
if (($action === 'auditdone' || $action === 'auditdelete') && $permissiontoadd) {
    $auditId = GETPOSTINT('audit_id');
    $audit   = new DuAudit($db);
    if ($auditId > 0 && $audit->fetch($auditId) > 0) {
        if ($action === 'auditdone') {
            // Record the REAL audit completion date (physical audit, not the billing date) and anchor
            // the next cycle on it: next audit = real date + 1 year. The line then rolls forward.
            $doneInput = GETPOST('audit_done_date', 'alpha');
            $doneDate  = $doneInput ? dol_stringtotime($doneInput) : dol_now();
            $audit->date_done       = $doneDate;
            $audit->last_audit_date = $doneDate;
            $audit->next_audit_date = dol_time_plus_duree($doneDate, 1, 'y');
            $audit->status          = DuAudit::STATUS_TODO;
            if ($audit->update($user) > 0) {
                setEventMessages($langs->trans('FollowupAuditDoneRolled', dol_print_date($doneDate, 'day'), dol_print_date($audit->next_audit_date, 'day')), []);
            } else {
                setEventMessages($audit->error, $audit->errors, 'errors');
            }
        } elseif ($permissiontodelete) {
            $audit->delete($user);
        }
    }
}

// CSV export of the overdue DU audits.
if ($action === 'exportoverdueaudits' && $permissiontoread) {
    $exportRows = reedcrmFollowupGetOverdueAudits($db);
    $sep        = ';';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audits_du_en_retard_' . dol_print_date(dol_now(), 'dayxcard') . '.csv"');
    print "\xEF\xBB\xBF";
    print implode($sep, ['Tiers', 'Localisation', 'Derniere facture DU', 'Prochain audit du', 'Retard (jours)', 'Service', 'Montant', 'Assigne a', 'Devis', 'Montant devis', 'Statut']) . "\n";
    $csvUserCache = [];
    foreach ($exportRows as $r) {
        $assignName = '';
        if (!empty($r['assigned'])) {
            if (!isset($csvUserCache[$r['assigned']])) {
                $cu = new User($db);
                $cu->fetch($r['assigned']);
                $csvUserCache[$r['assigned']] = dolGetFirstLastname($cu->firstname, $cu->lastname);
            }
            $assignName = $csvUserCache[$r['assigned']];
        }
        $cells = [
            $r['thirdparty'], $r['location'],
            !empty($r['last_audit']) ? dol_print_date($r['last_audit'], 'day') : '',
            dol_print_date($r['next_audit'], 'day'), $r['days_late'], $r['service'],
            $r['montant'] !== null ? $r['montant'] : '', $assignName,
            !empty($r['propal_ref']) ? $r['propal_ref'] : '',
            $r['propal_ttc'] !== null ? $r['propal_ttc'] : '',
            $langs->transnoentities('FollowupAuditToPrepare'),
        ];
        print implode($sep, array_map(function ($v) {
            return '"' . str_replace('"', '""', (string) $v) . '"';
        }, $cells)) . "\n";
    }
    exit;
}

/*
 * View.
 */
$title = $langs->trans('DuFollowupMenu');

$audits        = reedcrmFollowupGetAuditsForMonth($db, $periodStart, $periodEnd, false);
$overdueAudits = reedcrmFollowupGetOverdueAudits($db);

saturne_header(0, '', $title, '');

$prevMonth  = dol_print_date(dol_time_plus_duree($periodStart, -1, 'm'), '%Y-%m');
$nextMonth  = dol_print_date(dol_time_plus_duree($periodStart, 1, 'm'), '%Y-%m');
$monthLabel = dol_print_date($periodStart, '%B %Y');
$navBase    = $_SERVER['PHP_SELF'] . '?search_month=';
$selfMonth  = $_SERVER['PHP_SELF'] . '?search_month=' . urlencode($search_month);

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
.rcf-charts{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:8px 0 14px}
.rcf-chartbox{border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;padding:12px 14px;background:var(--colorbacklinepair2,#fff);overflow:hidden}
.rcf-charttitle{font-weight:600;font-size:.9em;color:#555;margin-bottom:8px}
.rcf-canvaswrap{position:relative;height:240px;width:100%}
.rcf-canvaswrap canvas{max-height:240px}
.rcf-prcheck{display:inline-flex;align-items:center;gap:5px;cursor:pointer}
.rcf-prcheck input{cursor:pointer;width:16px;height:16px;accent-color:#2e9e6c;vertical-align:middle}
.rcf-assignform{display:inline-flex;align-items:center;gap:3px}
.rcf-assignsel{min-width:110px}
.rcf-statedot{display:inline-block;width:9px;height:9px;border-radius:50%;vertical-align:middle;margin-right:5px}
@media (max-width:900px){.rcf-charts{grid-template-columns:1fr}}
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
 * DU charts (top): counts (to do / lost) and amounts (invoiced / lost), 12 months of the browsed year.
 */
$chartYear    = $monthYear;
$todoByMonth  = array_fill(1, 12, 0);
$lostByMonth  = array_fill(1, 12, 0);
$lostAmtMonth = array_fill(1, 12, 0.0);
$doneAmtMonth = array_fill(1, 12, 0.0);
$prAmtMonth   = array_fill(1, 12, 0.0);

$sqlChart  = 'SELECT MONTH(a.next_audit_date) as m,';
// A DU is "lost" only once it has not been updated for 3 years or more (last audit >= 3 years old);
// everything else still pending (upcoming or overdue by less than 3 years) counts as "to do".
$sqlChart .= ' SUM(CASE WHEN a.status <> 2 AND a.last_audit_date > DATE_SUB(NOW(), INTERVAL 3 YEAR) THEN 1 ELSE 0 END) as todo,';
$sqlChart .= ' SUM(CASE WHEN a.status <> 2 AND a.last_audit_date <= DATE_SUB(NOW(), INTERVAL 3 YEAR) THEN 1 ELSE 0 END) as lost,';
$sqlChart .= ' SUM(CASE WHEN a.status <> 2 AND a.last_audit_date <= DATE_SUB(NOW(), INTERVAL 3 YEAR) THEN a.montant ELSE 0 END) as lostamt,';
$sqlChart .= ' SUM(CASE WHEN a.status = 2 THEN a.montant ELSE 0 END) as doneamt,';
// Amount proposed to clients: the real total of the derived DU renewal quote (rows that have one).
$sqlChart .= ' SUM(CASE WHEN a.status <> 2 AND prc.rowid IS NOT NULL THEN prc.total_ttc ELSE 0 END) as pramt';
$sqlChart .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
$sqlChart .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as prc ON prc.rowid = (';
$sqlChart .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
$sqlChart .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
$sqlChart .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_AU%'";
$sqlChart .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
$sqlChart .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
$sqlChart .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1)';
$sqlChart .= ' WHERE a.entity IN (' . getEntity('reedcrm_du_audit') . ') AND s.status = 1 AND YEAR(a.next_audit_date) = ' . $chartYear . ' GROUP BY m';
$resChart  = $db->query($sqlChart);
if ($resChart) {
    while ($o = $db->fetch_object($resChart)) {
        $todoByMonth[(int) $o->m]  = (int) $o->todo;
        $lostByMonth[(int) $o->m]  = (int) $o->lost;
        $lostAmtMonth[(int) $o->m] = (float) $o->lostamt;
        $doneAmtMonth[(int) $o->m] = (float) $o->doneamt;
        $prAmtMonth[(int) $o->m]   = (float) $o->pramt;
    }
}

$monthLabels = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

print load_fiche_titre('<i class="fas fa-chart-bar paddingright"></i>' . $langs->trans('FollowupYearlyOverview') . ' — ' . $chartYear, '', '');
print '<div class="rcf-charts">';
print '<div class="rcf-chartbox"><div class="rcf-charttitle">' . $langs->trans('FollowupChartDu') . '</div><div class="rcf-canvaswrap"><canvas id="duChartCount"></canvas></div></div>';
print '<div class="rcf-chartbox"><div class="rcf-charttitle">' . $langs->trans('FollowupChartDuAmount') . '</div><div class="rcf-canvaswrap"><canvas id="duChartAmount"></canvas></div></div>';
print '</div>';

// Exact figures table.
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre"><th></th>';
foreach ($monthLabels as $ml) {
    print '<th class="center">' . $ml . '</th>';
}
print '</tr>';
print '<tr class="oddeven"><td class="strong">' . $langs->trans('FollowupChartToDo') . '</td>';
foreach (array_values($todoByMonth) as $v) {
    print '<td class="center">' . ($v ?: '-') . '</td>';
}
print '</tr><tr class="oddeven"><td class="strong" style="color:#cf4257">' . $langs->trans('FollowupChartLost') . '</td>';
foreach (array_values($lostByMonth) as $i => $v) {
    print '<td class="center">' . ($v ? $v . ' <span class="opacitymedium">(' . price(array_values($lostAmtMonth)[$i], 0, $langs, 1, -1, 0, $conf->currency) . ')</span>' : '-') . '</td>';
}
print '</tr><tr class="oddeven"><td class="strong" style="color:#c8871a">' . $langs->trans('FollowupChartProposed') . '</td>';
foreach (array_values($prAmtMonth) as $v) {
    print '<td class="center nowraponall">' . ($v > 0 ? price($v, 0, $langs, 1, -1, 0, $conf->currency) : '-') . '</td>';
}
print '</tr><tr class="oddeven"><td class="strong" style="color:#2e9e6c">' . $langs->trans('FollowupChartInvoiced') . '</td>';
foreach (array_values($doneAmtMonth) as $v) {
    print '<td class="center nowraponall">' . ($v > 0 ? price($v, 0, $langs, 1, -1, 0, $conf->currency) : '-') . '</td>';
}
print '</tr></table></div>';

print '<script src="' . DOL_URL_ROOT . '/includes/nnnick/chartjs/dist/chart.min.js"></script>';
print '<script>
(function() {
    if (typeof Chart === "undefined") { return; }
    var months = ' . json_encode($monthLabels) . ';
    var todoData = ' . json_encode(array_values($todoByMonth)) . ';
    var lostData = ' . json_encode(array_values($lostByMonth)) . ';
    var lostAmt = ' . json_encode(array_map('round', array_values($lostAmtMonth))) . ';
    var doneAmt = ' . json_encode(array_map('round', array_values($doneAmtMonth))) . ';
    var prAmt = ' . json_encode(array_map('round', array_values($prAmtMonth))) . ';
    var gridColor = "rgba(120,130,150,.15)";
    Chart.defaults.font.family = "inherit";
    var eur = function(v){ return v.toLocaleString("fr-FR") + " €"; };
    new Chart(document.getElementById("duChartCount"), {
        type: "bar",
        data: { labels: months, datasets: [
            { label: "' . dol_escape_js($langs->transnoentities('FollowupChartToDo')) . '", data: todoData, backgroundColor: "#2f6f9f", borderRadius: 4, maxBarThickness: 16 },
            { label: "' . dol_escape_js($langs->transnoentities('FollowupChartLost')) . '", data: lostData, backgroundColor: "#cf4257", borderRadius: 4, maxBarThickness: 16 }
        ] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: "top", align: "end" } },
            scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } }, x: { grid: { display: false } } } }
    });
    new Chart(document.getElementById("duChartAmount"), {
        type: "bar",
        data: { labels: months, datasets: [
            { label: "' . dol_escape_js($langs->transnoentities('FollowupChartProposed')) . '", data: prAmt, backgroundColor: "#c8871a", borderRadius: 4, maxBarThickness: 16 },
            { label: "' . dol_escape_js($langs->transnoentities('FollowupChartInvoiced')) . '", data: doneAmt, backgroundColor: "#2e9e6c", borderRadius: 4, maxBarThickness: 16 },
            { label: "' . dol_escape_js($langs->transnoentities('FollowupChartLost')) . '", data: lostAmt, backgroundColor: "#cf4257", borderRadius: 4, maxBarThickness: 16 }
        ] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: "top", align: "end" }, tooltip: { callbacks: { label: function(c){ return c.dataset.label + ": " + eur(c.parsed.y); } } } },
            scales: { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { callback: eur } }, x: { grid: { display: false } } } }
    });
})();
</script>';

/*
 * DU audit stats band (month).
 */
$auditToPrepare   = 0;
$auditOverdueM    = 0;
$auditDone        = 0;
$auditPrSent      = 0;
$auditPrTotal     = 0;
$auditTotMontant  = 0;
$auditDoneMontant = 0;
$nowStat          = dol_now();
foreach ($audits as $auditStat) {
    if ($auditStat['status'] == DuAudit::STATUS_DONE) {
        $auditDone++;
        $auditDoneMontant += (float) $auditStat['montant'];
    } elseif ($auditStat['next_audit'] < $nowStat) {
        $auditOverdueM++;
    } else {
        $auditToPrepare++;
    }
    if (!empty($auditStat['propal_id'])) {
        $auditPrSent++;
        $auditPrTotal += (float) $auditStat['propal_ttc'];
    }
    $auditTotMontant += (float) $auditStat['montant'];
}
// Lost DU: audits not updated for 3 years or more, globally (year-independent so it is always visible).
$lostGlobal = 0;
$sqlLost  = 'SELECT COUNT(*) as n FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
$sqlLost .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
$sqlLost .= ' WHERE a.entity IN (' . getEntity('reedcrm_du_audit') . ') AND s.status = 1';
$sqlLost .= ' AND a.status <> 2 AND a.last_audit_date <= DATE_SUB(NOW(), INTERVAL 3 YEAR)';
$resLost  = $db->query($sqlLost);
if ($resLost && $oLost = $db->fetch_object($resLost)) {
    $lostGlobal = (int) $oLost->n;
}

print '<div class="rcf-dash"><div class="rcf-tiles">';
printf('<div class="rcf-tile warn"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupAuditToPrepareCount'), $auditToPrepare);
printf('<div class="rcf-tile crit"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupAuditOverdueCount'), $auditOverdueM);
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupAuditDoneCount'), $auditDone);
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupProposalSentCount'), $auditPrSent);
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupAuditInvoicedAmount'), price($auditDoneMontant, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupAuditTotalAmount'), price($auditTotMontant, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile crit"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupDuLostCount'), $lostGlobal);
print '</div></div>';

$thirdpartyStatic = new Societe($db);
$assignUserCache  = [];

// Shared renderer for one audit row.
$printAuditRow = function (array $audit, bool $showDaysLate) use (&$thirdpartyStatic, &$assignUserCache, $form, $propalStatic, $langs, $conf, $selfMonth, $permissiontoadd, $permissiontodelete) {
    $isDone                   = ($audit['status'] == DuAudit::STATUS_DONE);
    $thirdpartyStatic->id     = $audit['fk_soc'];
    $thirdpartyStatic->name   = $audit['thirdparty'];
    $thirdpartyStatic->status = 1;

    print '<tr class="oddeven' . ($isDone ? ' opacitymedium' : '') . '">';
    print '<td class="tdoverflowmax200">' . $thirdpartyStatic->getNomUrl(1) . '</td>';
    print '<td class="tdoverflowmax150" title="' . dol_escape_htmltag($audit['address']) . '">';
    print $audit['location'] !== '' ? '<i class="fas fa-map-marker-alt paddingright opacitymedium"></i>' . dol_escape_htmltag($audit['location']) : '<span class="opacitymedium">-</span>';
    print '</td>';
    print '<td class="center">' . (!empty($audit['last_audit']) ? dol_print_date($audit['last_audit'], 'day') : '') . '</td>';
    print '<td class="center nowraponall">';
    print '<form method="POST" action="' . $selfMonth . '" class="inline-block">';
    print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditmove"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
    print '<input type="date" name="audit_date" value="' . dol_print_date($audit['next_audit'], '%Y-%m-%d') . '" class="maxwidth150">';
    print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupAuditMove')) . '"><i class="fas fa-arrows-alt-h"></i></button>';
    print '</form></td>';
    if ($showDaysLate) {
        print '<td class="center"><span style="color:#cf4257;font-weight:bold">' . (int) $audit['days_late'] . ' ' . $langs->trans('FollowupDaysLate') . '</span></td>';
    }
    print '<td class="tdoverflowmax300" title="' . dol_escape_htmltag($audit['service']) . '">' . dol_escape_htmltag($audit['service']) . '</td>';
    print '<td class="right nowraponall">' . ($audit['montant'] !== null ? price($audit['montant'], 0, $langs, 1, -1, -1, $conf->currency) : '') . '</td>';
    // Assignee: who is in charge of this DU audit (drives the "amount per person" breakdown).
    print '<td class="center nowraponall">';
    if ($permissiontoadd) {
        print '<form method="POST" action="' . $selfMonth . '" class="inline-block rcf-assignform">';
        print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditassign"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
        print $form->select_dolusers($audit['assigned'] ?: '', 'assign_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth150 rcf-assignsel');
        print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupAssign')) . '"><i class="fas fa-user-check"></i></button>';
        print '</form>';
    } else {
        if (!empty($audit['assigned'])) {
            if (!isset($assignUserCache[$audit['assigned']])) {
                $u = new User($GLOBALS['db']);
                $u->fetch($audit['assigned']);
                $assignUserCache[$audit['assigned']] = $u;
            }
            print $assignUserCache[$audit['assigned']]->getNomUrl(-1);
        } else {
            print '<span class="opacitymedium">-</span>';
        }
    }
    print '</td>';
    // Commercial proposal (devis): auto-derived renewal quote, shown as a clickable link + its real amount.
    print '<td class="center nowraponall">';
    if (!empty($audit['propal_id']) && !empty($audit['propal_ref'])) {
        print '<a href="' . DOL_URL_ROOT . '/comm/propal/card.php?id=' . ((int) $audit['propal_id']) . '" target="_blank" rel="noopener"><i class="fas fa-file-invoice paddingright opacitymedium"></i>' . dol_escape_htmltag($audit['propal_ref']) . '</a>';
        if ($audit['propal_ttc'] !== null) {
            print ' <span class="opacitymedium">(' . price($audit['propal_ttc'], 0, $langs, 1, -1, 0, $conf->currency) . ')</span>';
        }
        print '<br>';
        if (!empty($audit['propal_date'])) {
            print '<span class="opacitymedium">' . dol_print_date($audit['propal_date'], 'day') . '</span> ';
        }
        if ($audit['propal_statut'] !== null) {
            print $propalStatic->LibStatut((int) $audit['propal_statut'], 5);
        }
    } else {
        print '<span class="opacitymedium">-</span>';
    }
    print '</td>';
    print '<td class="center nowraponall">';
    // Auto-derived state following the real quote AND invoice: Paid > Invoiced > Quote signed >
    // Quote sent > Overdue > To prepare. Nothing is lost after the audit is billed.
    // In the overdue table only, a quote/invoice older than 6 months is stale and no longer counts
    // as progress (an old proposal on a long-overdue audit is dead) -> show "En retard".
    $stateTitle = '';
    $staleDocs  = $showDaysLate && (max((int) $audit['facture_date'], (int) $audit['propal_date']) < dol_time_plus_duree(dol_now(), -6, 'm'));
    if ($isDone) {
        $stateColor = '#6c757d'; $stateLabel = $langs->trans('FollowupAuditDone');
    } elseif (!$staleDocs && !empty($audit['facture_id']) && !empty($audit['facture_paye'])) {
        $stateColor = '#2e9e6c'; $stateLabel = $langs->trans('FollowupAuditPaid');       $stateTitle = (string) $audit['facture_ref'];
    } elseif (!$staleDocs && !empty($audit['facture_id'])) {
        $stateColor = '#17a2b8'; $stateLabel = $langs->trans('FollowupAuditInvoiced');   $stateTitle = (string) $audit['facture_ref'];
    } elseif (!$staleDocs && !empty($audit['propal_id']) && (int) $audit['propal_statut'] === 2) {
        $stateColor = '#6f42c1'; $stateLabel = $langs->trans('FollowupAuditPrSigned');   $stateTitle = (string) $audit['propal_ref'];
    } elseif (!$staleDocs && !empty($audit['propal_id'])) {
        $stateColor = '#2f6f9f'; $stateLabel = $langs->trans('FollowupProposalSent');    $stateTitle = (string) $audit['propal_ref'];
    } elseif ($audit['next_audit'] < dol_now()) {
        $stateColor = '#cf4257'; $stateLabel = $langs->trans('FollowupAuditOverdue');
    } else {
        $stateColor = '#c8871a'; $stateLabel = $langs->trans('FollowupAuditToPrepare');
    }
    print '<span class="rcf-statedot" style="background:' . $stateColor . '"' . ($stateTitle !== '' ? ' title="' . dol_escape_htmltag($stateTitle) . '"' : '') . '></span> ' . $stateLabel;
    if ($audit['source'] === 'manual') {
        print ' <span class="badge badge-secondary" title="' . dol_escape_htmltag($langs->trans('FollowupAuditManual')) . '">M</span>';
    }
    print '</td>';
    print '<td class="center nowraponall">';
    if ($permissiontoadd && !$isDone) {
        // Create the yearly renewal quote (Dolibarr proposal) for this client.
        print '<a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/comm/propal/card.php?action=create&socid=' . (int) $audit['fk_soc'] . '" title="' . dol_escape_htmltag($langs->trans('FollowupCreateProposal')) . '"><i class="fas fa-file-invoice"></i></a> ';
        // Roll the line forward to the next yearly cycle (only if a newer DU_AU invoice exists).
        print '<form method="POST" action="' . $selfMonth . '" class="inline-block" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('FollowupAuditRenewConfirm')) . '\');">';
        print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditrenew"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
        print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupAuditRenew')) . '"><i class="fas fa-redo"></i></button></form> ';
        print '<form method="POST" action="' . $selfMonth . '" class="inline-block" title="' . dol_escape_htmltag($langs->trans('FollowupAuditRealDate')) . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditdone"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
        print '<input type="date" name="audit_done_date" value="' . dol_print_date(dol_now(), '%Y-%m-%d') . '" class="maxwidth130">';
        print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupAuditMarkDone')) . '"><i class="fas fa-check"></i></button></form> ';
    }
    if ($permissiontodelete) {
        print '<form method="POST" action="' . $selfMonth . '" class="inline-block" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('ConfirmDeleteObject')) . '\');">';
        print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditdelete"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
        print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('Delete')) . '"><i class="fas fa-trash"></i></button></form>';
    }
    print '</td></tr>';
};

// --- Audits of the month ---
print '<br>';
print load_fiche_titre('<i class="fas fa-clipboard-check paddingright"></i>' . $langs->trans('FollowupAuditsOfMonth'), '', '');
print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('ThirdParty') . '</th><th>' . $langs->trans('FollowupLocation') . '</th>';
print '<th class="center">' . $langs->trans('FollowupLastDuInvoice') . '</th><th class="center">' . $langs->trans('FollowupNextAudit') . '</th>';
print '<th>' . $langs->trans('Service') . '</th><th class="right">' . $langs->trans('FollowupAmount') . '</th>';
print '<th class="center">' . $langs->trans('FollowupAssignedTo') . '</th>';
print '<th class="center">' . $langs->trans('FollowupProposalSent') . '</th>';
print '<th class="center">' . $langs->trans('Status') . '</th><th class="center maxwidthsearch"></th>';
print '</tr>';
if (empty($audits)) {
    print '<tr class="oddeven"><td colspan="10" class="opacitymedium center">' . $langs->trans('FollowupNoAuditThisMonth') . '</td></tr>';
} else {
    foreach ($audits as $audit) {
        $printAuditRow($audit, false);
    }
    print '<tr class="liste_total"><td colspan="5">' . $langs->trans('Total') . '</td><td class="right">' . price($auditTotMontant, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td></td><td class="center nowraponall">' . ($auditPrTotal > 0 ? price($auditPrTotal, 0, $langs, 1, -1, 0, $conf->currency) : '') . '</td><td colspan="2"></td></tr>';
}
if ($permissiontoadd) {
    print '<tr class="oddeven">';
    print '<form method="POST" action="' . $selfMonth . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="addaudit">';
    print '<td>' . $formcompany->select_company(0, 'audit_fk_soc', '', $langs->trans('SelectThirdParty'), 0, 0, [], 0, 'minwidth150 maxwidth250') . '</td>';
    print '<td></td><td></td>';
    print '<td class="center"><input type="date" name="audit_date" class="maxwidth150"></td>';
    print '<td><input type="text" name="audit_note" class="maxwidth200" placeholder="' . dol_escape_htmltag($langs->trans('Note')) . '"></td>';
    print '<td class="right"><input type="text" name="audit_montant" class="maxwidth75 right" placeholder="0"></td>';
    print '<td class="center" colspan="4"><button type="submit" class="button button-add smallpaddingimp"><i class="fas fa-plus paddingright"></i>' . $langs->trans('FollowupAuditAdd') . '</button></td>';
    print '</form></tr>';
}
print '</table></div>';

// --- Proposal amount per assignee for the BROWSED MONTH (from the month's audits that have a DU quote) ---
$byUser = [];
foreach ($audits as $auditRow) {
    if (empty($auditRow['assigned']) || empty($auditRow['propal_id'])) {
        continue;
    }
    $uid = (int) $auditRow['assigned'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = ['nb' => 0, 'tot' => 0.0];
    }
    $byUser[$uid]['nb']++;
    $byUser[$uid]['tot'] += (float) $auditRow['propal_ttc'];
}
uasort($byUser, function ($x, $y) {
    return $y['tot'] <=> $x['tot'];
});
if (!empty($byUser)) {
    print '<br>';
    print load_fiche_titre('<i class="fas fa-user-tag paddingright"></i>' . $langs->trans('FollowupAmountPerPerson'), '', '');
    print '<div class="div-table-responsive"><table class="tagtable liste">';
    print '<tr class="liste_titre"><th>' . $langs->trans('FollowupAssignedTo') . '</th><th class="center">' . $langs->trans('FollowupProposalSentCount') . '</th><th class="right">' . $langs->trans('FollowupProposalAmount') . '</th></tr>';
    foreach ($byUser as $uid => $agg) {
        if (!isset($assignUserCache[$uid])) {
            $u = new User($db);
            $u->fetch($uid);
            $assignUserCache[$uid] = $u;
        }
        print '<tr class="oddeven"><td>' . $assignUserCache[$uid]->getNomUrl(-1) . '</td>';
        print '<td class="center">' . (int) $agg['nb'] . '</td>';
        print '<td class="right nowraponall">' . price((float) $agg['tot'], 0, $langs, 1, -1, -1, $conf->currency) . '</td></tr>';
    }
    print '</table></div>';
}

// --- Overdue audits ---
print '<br>';
$overdueCsvButton = dolGetButtonTitle($langs->trans('ExportCsv'), '', 'fa fa-file-csv', $selfMonth . '&action=exportoverdueaudits&token=' . newToken());
print load_fiche_titre('<i class="fas fa-exclamation-triangle paddingright" style="color:#cf4257"></i>' . $langs->trans('FollowupAuditsOverdue') . ' <span class="badge">' . count($overdueAudits) . '</span>', $overdueCsvButton, '');
print '<div class="div-table-responsive"><table class="tagtable liste">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('ThirdParty') . '</th><th>' . $langs->trans('FollowupLocation') . '</th>';
print '<th class="center">' . $langs->trans('FollowupLastDuInvoice') . '</th><th class="center">' . $langs->trans('FollowupNextAudit') . '</th>';
print '<th class="center">' . $langs->trans('FollowupLate') . '</th>';
print '<th>' . $langs->trans('Service') . '</th><th class="right">' . $langs->trans('FollowupAmount') . '</th>';
print '<th class="center">' . $langs->trans('FollowupAssignedTo') . '</th>';
print '<th class="center">' . $langs->trans('FollowupProposalSent') . '</th>';
print '<th class="center">' . $langs->trans('Status') . '</th><th class="center maxwidthsearch"></th>';
print '</tr>';
if (empty($overdueAudits)) {
    print '<tr class="oddeven"><td colspan="11" class="center opacitymedium">' . $langs->trans('FollowupNoOverdue') . '</td></tr>';
} else {
    $overdueTotMontant = 0;
    foreach ($overdueAudits as $audit) {
        $printAuditRow($audit, true);
        $overdueTotMontant += (float) $audit['montant'];
    }
    print '<tr class="liste_total"><td colspan="6">' . $langs->trans('Total') . '</td><td class="right">' . price($overdueTotMontant, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td colspan="4"></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
