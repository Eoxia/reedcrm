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

    // Optional link to a real document (quote or invoice) so a hand-added line is not orphaned.
    // Both are re-checked against the selected client before being stored.
    $linkedPropal  = reedcrmFollowupFetchLinkableDoc($db, 'propal', GETPOSTINT('audit_fk_propal'), $auditSoc);
    $linkedFacture = reedcrmFollowupFetchLinkableDoc($db, 'facture', GETPOSTINT('audit_fk_facture'), $auditSoc);
    // No date typed: plan the audit in the month being browsed (today when that is the current month),
    // so a line added from this board always lands where the user is looking.
    if (!$auditDate) {
        $nowTs     = dol_now();
        $auditDate = ($nowTs >= $periodStart && $nowTs <= $periodEnd) ? $nowTs : $periodStart;
    }

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
        } else {
            // Amount left blank: take it from the linked document (invoice first), drafts at 0 aside.
            $docAmount = $linkedFacture ? (float) $linkedFacture['total_ttc'] : ($linkedPropal ? (float) $linkedPropal['total_ttc'] : 0);
            if ($docAmount > 0) {
                $audit->montant = $docAmount;
            }
        }
        if ($linkedPropal) {
            $audit->fk_propal = $linkedPropal['id'];
        }
        if ($linkedFacture) {
            // The invoice both links the line and dates the cycle it closes.
            $audit->fk_facture        = $linkedFacture['id'];
            $audit->fk_facture_source = $linkedFacture['id'];
            $audit->last_audit_date   = $linkedFacture['date'];
        }
        if ($existingId > 0) {
            $result = $audit->update($user);
        } else {
            $audit->source = 'manual';
            $result        = $audit->create($user);
        }
        if ($result > 0) {
            setEventMessages($langs->trans('FollowupAuditAdded'), []);
            // The line may belong to another month (date typed by hand, or client already followed
            // with another date): jump to that month so it is never added out of sight.
            $auditMonth = dol_print_date($auditDate, '%Y-%m');
            if ($auditMonth !== $search_month) {
                setEventMessages($langs->trans('FollowupAuditAddedOtherMonth', dol_print_date($auditDate, '%B %Y')), null, 'warnings');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?search_month=' . urlencode($auditMonth));
                exit;
            }
        } else {
            setEventMessages($audit->error, $audit->errors, 'errors');
        }
    } else {
        setEventMessages($langs->trans('FollowupAuditAddNoThirdParty'), null, 'errors');
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
if ($action === 'auditrdv' && $permissiontoadd) {
    // Real date agreed with the client. This — and only this — books an intervention: the yearly
    // theoretical date never creates one, so the intervention list stays free of fictitious dates.
    $auditId = GETPOSTINT('audit_id');
    $rdvDate = dol_stringtotime(GETPOST('audit_rdv_date', 'alpha'));
    $audit   = new DuAudit($db);
    if ($auditId > 0 && $audit->fetch($auditId) > 0) {
        if (!$rdvDate) {
            // Appointment cancelled: the line goes back to its theoretical date. The intervention
            // already created is left alone (it is a real document) but is no longer followed here.
            $audit->date_rdv     = null;
            $audit->fk_fichinter = null;
            if ($audit->update($user) > 0) {
                setEventMessages($langs->trans('FollowupAuditRdvCleared'), []);
            } else {
                setEventMessages($audit->error, $audit->errors, 'errors');
            }
        } else {
            $interventionId = reedcrmFollowupSyncAuditIntervention($db, $user, $audit, $rdvDate);
            $audit->date_rdv = $rdvDate;
            if ($interventionId > 0) {
                $audit->fk_fichinter = $interventionId;
            }
            if ($audit->update($user) > 0) {
                if ($interventionId > 0) {
                    setEventMessages($langs->trans('FollowupAuditRdvPlanned', dol_print_date($rdvDate, 'day')), []);
                } elseif ($interventionId === 0) {
                    // No right on interventions (or module off): the date is kept anyway.
                    setEventMessages($langs->trans('FollowupAuditRdvNoIntervention'), null, 'warnings');
                } else {
                    setEventMessages($langs->trans('FollowupAuditRdvInterventionFailed'), null, 'errors');
                }
            } else {
                setEventMessages($audit->error, $audit->errors, 'errors');
            }
        }
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
        $sqlInv .= " WHERE p.ref LIKE 'DU\_A%' AND f.type <> 2 AND f.fk_soc = " . ((int) $audit->fk_soc);
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
                $audit->fk_propal          = null; // drop the previous cycle's manual links
                $audit->fk_facture         = null;
                $audit->date_rdv           = null; // the new cycle has no appointment yet
                $audit->fk_fichinter       = null;
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
            $audit->date_rdv        = null; // the appointment has happened, date_done records it
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
    print implode($sep, ['Tiers', 'Localisation', 'Derniere facture DU', 'Prochain audit prevu', 'RDV client', 'Intervention', 'Retard (jours)', 'Service', 'Montant', 'Assigne a', 'Devis', 'Montant devis', 'Statut']) . "\n";
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
            dol_print_date($r['next_audit'], 'day'),
            !empty($r['date_rdv']) ? dol_print_date($r['date_rdv'], 'day') : '',
            !empty($r['fichinter_ref']) ? $r['fichinter_ref'] : '',
            $r['days_late'], $r['service'],
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
.rcf-tile.warn:before{background:#c8871a}.rcf-tile.crit:before{background:#cf4257}.rcf-tile.good:before{background:#2e9e6c}.rcf-tile.rdv:before{background:#0b7285}
.rcf-tile.rdv .v{color:#0b7285}
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
/* Audit carried out during the browsed month: the line stays in the table, flagged green. */
.rcf-donerow>td{background:rgba(46,158,108,.12) !important}
.rcf-donerow>td:first-child{box-shadow:inset 3px 0 0 #2e9e6c}
.rcf-donetag{color:#2e9e6c;font-weight:700}
/* Forecast date (theoretical, from last year) kept quiet, real appointment made loud. */
.rcf-planned input[type=date]{opacity:.7;font-size:.9em}
.rcf-planned-tag{font-size:.72em;text-transform:uppercase;letter-spacing:.04em;color:#8a8a8a;margin-top:2px}
.rcf-rdvcell input[type=date]{font-size:.9em}
.rcf-rdvcell.set input[type=date]{border:1px solid #0b7285;border-radius:5px;color:#0b7285;font-weight:700}
.rcf-rdvtag{color:#0b7285;font-weight:700}
.rcf-interlink{display:inline-block;margin-top:3px;font-size:.85em;color:#0b7285}
/* "Link a quote / an invoice" pickers of the add-an-audit line. */
.rcf-doclink{display:flex;flex-direction:column;gap:6px;min-width:250px}
.rcf-doclink-row{display:flex;align-items:center;gap:7px;text-align:left}
.rcf-doclink-row>i{flex:0 0 16px;text-align:center;font-size:.95em;opacity:.8}
.rcf-doclink-row.pr>i{color:#2f6f9f}
.rcf-doclink-row.fa>i{color:#2e9e6c}
.rcf-doclink-row .select2-container,select.rcf-docsel{flex:1 1 auto;min-width:0}
select.rcf-docsel{padding:5px 9px;border:1px solid var(--colortopbordertitle1,#ccc);border-radius:7px;background:var(--colorbacklinepair2,#fff);color:inherit;font-size:.9em;max-width:100%}
select.rcf-docsel:focus{border-color:#2f6f9f;outline:none}
.rcf-doclink-row .select2-selection--single{border-radius:7px !important;height:29px !important}
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
$sqlChart .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as prc ON prc.rowid = COALESCE((';
$sqlChart .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
$sqlChart .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
$sqlChart .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_A%'";
$sqlChart .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
$sqlChart .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
$sqlChart .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1), a.fk_propal)';
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
$auditRdvCount    = 0;
$auditPrTotal     = 0;
$auditTotMontant  = 0;
$auditDoneMontant = 0;
$nowStat          = dol_now();
foreach ($audits as $auditStat) {
    // Done = closed line, or audit actually carried out during the browsed month.
    $doneThisMonth = !empty($auditStat['date_done']) && $auditStat['date_done'] >= $periodStart && $auditStat['date_done'] <= $periodEnd;
    if ($auditStat['status'] == DuAudit::STATUS_DONE || $doneThisMonth) {
        $auditDone++;
        $auditDoneMontant += (float) $auditStat['montant'];
    } elseif ($auditStat['effective'] < $nowStat) {
        $auditOverdueM++;
    } else {
        $auditToPrepare++;
    }
    if (!empty($auditStat['date_rdv']) && !$doneThisMonth) {
        $auditRdvCount++;
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
printf('<div class="rcf-tile rdv"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupAuditRdvCount'), $auditRdvCount);
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupAuditDoneCount'), $auditDone);
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupProposalSentCount'), $auditPrSent);
printf('<div class="rcf-tile good"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupAuditInvoicedAmount'), price($auditDoneMontant, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile"><div class="k">%s</div><div class="v">%s</div></div>', $langs->trans('FollowupAuditTotalAmount'), price($auditTotMontant, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rcf-tile crit"><div class="k">%s</div><div class="v">%d</div></div>', $langs->trans('FollowupDuLostCount'), $lostGlobal);
print '</div></div>';

$thirdpartyStatic = new Societe($db);
$assignUserCache  = [];

// Shared renderer for one audit row.
$printAuditRow = function (array $audit, bool $showDaysLate) use (&$thirdpartyStatic, &$assignUserCache, $form, $propalStatic, $langs, $conf, $selfMonth, $permissiontoadd, $permissiontodelete, $periodStart, $periodEnd) {
    $isDone = ($audit['status'] == DuAudit::STATUS_DONE);
    // An audit carried out during the browsed month keeps its line in the table (its next date has
    // already rolled one year ahead) so the month's work stays visible instead of vanishing.
    $doneInMonth              = !empty($audit['date_done']) && $audit['date_done'] >= $periodStart && $audit['date_done'] <= $periodEnd;
    $thirdpartyStatic->id     = $audit['fk_soc'];
    $thirdpartyStatic->name   = $audit['thirdparty'];
    $thirdpartyStatic->status = 1;

    print '<tr class="oddeven' . ($isDone ? ' opacitymedium' : '') . ($doneInMonth ? ' rcf-donerow' : '') . '">';
    print '<td class="tdoverflowmax200">' . $thirdpartyStatic->getNomUrl(1) . '</td>';
    print '<td class="tdoverflowmax150" title="' . dol_escape_htmltag($audit['address']) . '">';
    print $audit['location'] !== '' ? '<i class="fas fa-map-marker-alt paddingright opacitymedium"></i>' . dol_escape_htmltag($audit['location']) : '<span class="opacitymedium">-</span>';
    print '</td>';
    print '<td class="center">' . (!empty($audit['last_audit']) ? dol_print_date($audit['last_audit'], 'day') : '') . '</td>';
    // Theoretical yearly date (last audit + 1 year): a forecast, kept discreet on purpose.
    print '<td class="center nowraponall rcf-planned">';
    if ($doneInMonth) {
        print '<span class="rcf-donetag"><i class="fas fa-check-circle paddingright"></i>' . $langs->trans('FollowupAuditDoneOn', dol_print_date($audit['date_done'], 'day')) . '</span><br>';
    }
    print '<form method="POST" action="' . $selfMonth . '" class="inline-block">';
    print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditmove"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
    print '<input type="date" name="audit_date" value="' . dol_print_date($audit['next_audit'], '%Y-%m-%d') . '" class="maxwidth150">';
    print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupAuditMove')) . '"><i class="fas fa-arrows-alt-h"></i></button>';
    print '</form>';
    print '<div class="rcf-planned-tag">' . $langs->trans('FollowupAuditPlannedTag') . '</div>';
    print '</td>';
    // Date really agreed with the client: filling it books the intervention.
    print '<td class="center nowraponall rcf-rdvcell' . (!empty($audit['date_rdv']) ? ' set' : '') . '">';
    if ($permissiontoadd) {
        print '<form method="POST" action="' . $selfMonth . '" class="inline-block">';
        print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditrdv"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
        print '<input type="date" name="audit_rdv_date" value="' . (!empty($audit['date_rdv']) ? dol_print_date($audit['date_rdv'], '%Y-%m-%d') : '') . '" class="maxwidth150">';
        print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans(!empty($audit['date_rdv']) ? 'FollowupAuditRdvMove' : 'FollowupAuditRdvPlan')) . '"><i class="fas fa-calendar-check"></i></button>';
        print '</form>';
    } elseif (!empty($audit['date_rdv'])) {
        print '<span class="rcf-rdvtag">' . dol_print_date($audit['date_rdv'], 'day') . '</span>';
    } else {
        print '<span class="opacitymedium">-</span>';
    }
    if (!empty($audit['fichinter_id'])) {
        print '<div><a href="' . DOL_URL_ROOT . '/fichinter/card.php?id=' . ((int) $audit['fichinter_id']) . '" target="_blank" rel="noopener" class="rcf-interlink"><i class="fas fa-tools paddingright"></i>' . dol_escape_htmltag($audit['fichinter_ref']) . '</a></div>';
    }
    print '</td>';
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
    if ($doneInMonth) {
        // Audit really carried out this month: this is the state that matters, it wins over the docs.
        $stateColor = '#2e9e6c'; $stateLabel = $langs->trans('FollowupAuditDoneOn', dol_print_date($audit['date_done'], 'day'));
    } elseif ($isDone) {
        $stateColor = '#6c757d'; $stateLabel = $langs->trans('FollowupAuditDone');
    } elseif (!empty($audit['date_rdv'])) {
        // A date agreed with the client outranks the commercial documents: it is the firm one.
        $stateColor = '#0b7285'; $stateLabel = $langs->trans('FollowupAuditRdvOn', dol_print_date($audit['date_rdv'], 'day'));
        $stateTitle = (string) $audit['fichinter_ref'];
    } elseif (!$staleDocs && !empty($audit['facture_id']) && !empty($audit['facture_paye'])) {
        $stateColor = '#2e9e6c'; $stateLabel = $langs->trans('FollowupAuditPaid');       $stateTitle = (string) $audit['facture_ref'];
    } elseif (!$staleDocs && !empty($audit['facture_id'])) {
        $stateColor = '#17a2b8'; $stateLabel = $langs->trans('FollowupAuditInvoiced');   $stateTitle = (string) $audit['facture_ref'];
    } elseif (!$staleDocs && !empty($audit['propal_id']) && (int) $audit['propal_statut'] === 2) {
        $stateColor = '#6f42c1'; $stateLabel = $langs->trans('FollowupAuditPrSigned');   $stateTitle = (string) $audit['propal_ref'];
    } elseif (!$staleDocs && !empty($audit['propal_id'])) {
        $stateColor = '#2f6f9f'; $stateLabel = $langs->trans('FollowupProposalSent');    $stateTitle = (string) $audit['propal_ref'];
    } elseif ($audit['effective'] < dol_now()) {
        $stateColor = '#cf4257'; $stateLabel = $langs->trans('FollowupAuditOverdue');
    } else {
        $stateColor = '#c8871a'; $stateLabel = $langs->trans('FollowupAuditToPrepare');
    }
    print '<span class="rcf-statedot" style="background:' . $stateColor . '"' . ($stateTitle !== '' ? ' title="' . dol_escape_htmltag($stateTitle) . '"' : '') . '></span> ' . $stateLabel;
    // The invoice behind the state is reachable in one click (also true of a hand-linked one).
    if (!empty($audit['facture_id']) && !empty($audit['facture_ref'])) {
        print '<br><a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . ((int) $audit['facture_id']) . '" target="_blank" rel="noopener" class="opacitymedium"><i class="fas fa-file-invoice-dollar paddingright"></i>' . dol_escape_htmltag($audit['facture_ref']) . '</a>';
    }
    if ($audit['source'] === 'manual') {
        print ' <span class="badge badge-secondary" title="' . dol_escape_htmltag($langs->trans('FollowupAuditManual')) . '">M</span>';
    }
    print '</td>';
    print '<td class="center nowraponall">';
    if ($permissiontoadd && !$isDone) {
        // Create the yearly renewal quote (Dolibarr proposal) for this client.
        print '<a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/comm/propal/card.php?action=create&socid=' . (int) $audit['fk_soc'] . '" title="' . dol_escape_htmltag($langs->trans('FollowupCreateProposal')) . '"><i class="fas fa-file-invoice"></i></a> ';
        // Roll the line forward to the next yearly cycle (only if a newer DU_AU invoice exists).
        // Already done this month: the cycle has just rolled, no second roll offered here.
        if (!$doneInMonth) {
            print '<form method="POST" action="' . $selfMonth . '" class="inline-block" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('FollowupAuditRenewConfirm')) . '\');">';
            print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditrenew"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
            print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans('FollowupAuditRenew')) . '"><i class="fas fa-redo"></i></button></form> ';
        }
        // Mark done / correct the real date: re-submitting simply re-anchors the cycle on the new date.
        print '<form method="POST" action="' . $selfMonth . '" class="inline-block" title="' . dol_escape_htmltag($langs->trans($doneInMonth ? 'FollowupAuditCorrectDoneDate' : 'FollowupAuditRealDate')) . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="auditdone"><input type="hidden" name="audit_id" value="' . $audit['id'] . '">';
        print '<input type="date" name="audit_done_date" value="' . dol_print_date($audit['date_done'] ?: dol_now(), '%Y-%m-%d') . '" class="maxwidth130">';
        print '<button type="submit" class="button smallpaddingimp" title="' . dol_escape_htmltag($langs->trans($doneInMonth ? 'FollowupAuditCorrectDoneDate' : 'FollowupAuditMarkDone')) . '"><i class="fas fa-check"></i></button></form> ';
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
print '<th class="center">' . $langs->trans('FollowupLastDuInvoice') . '</th><th class="center">' . $langs->trans('FollowupNextAuditPlanned') . '</th><th class="center">' . $langs->trans('FollowupAuditRdv') . '</th>';
print '<th>' . $langs->trans('Service') . '</th><th class="right">' . $langs->trans('FollowupAmount') . '</th>';
print '<th class="center">' . $langs->trans('FollowupAssignedTo') . '</th>';
print '<th class="center">' . $langs->trans('FollowupProposalSent') . '</th>';
print '<th class="center">' . $langs->trans('Status') . '</th><th class="center maxwidthsearch"></th>';
print '</tr>';
if (empty($audits)) {
    print '<tr class="oddeven"><td colspan="11" class="opacitymedium center">' . $langs->trans('FollowupNoAuditThisMonth') . '</td></tr>';
} else {
    foreach ($audits as $audit) {
        $printAuditRow($audit, false);
    }
    print '<tr class="liste_total"><td colspan="6">' . $langs->trans('Total') . '</td><td class="right">' . price($auditTotMontant, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td></td><td class="center nowraponall">' . ($auditPrTotal > 0 ? price($auditPrTotal, 0, $langs, 1, -1, 0, $conf->currency) : '') . '</td><td colspan="2"></td></tr>';
}
if ($permissiontoadd) {
    print '<tr class="oddeven">';
    print '<form method="POST" action="' . $selfMonth . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="action" value="addaudit">';
    print '<td>' . $formcompany->select_company(0, 'audit_fk_soc', '', $langs->trans('SelectThirdParty'), 0, 0, [], 0, 'minwidth150 maxwidth250') . '</td>';
    print '<td></td><td></td>';
    print '<td class="center"><input type="date" name="audit_date" class="maxwidth150"></td>';
    print '<td class="center opacitymedium">' . $langs->trans('FollowupAuditRdvLater') . '</td>';
    print '<td><input type="text" name="audit_note" class="maxwidth200" placeholder="' . dol_escape_htmltag($langs->trans('Note')) . '"></td>';
    print '<td class="right"><input type="text" name="audit_montant" class="maxwidth75 right" placeholder="0"></td>';
    print '<td></td>';
    // Optional link to an existing quote / invoice of the client: a hand-added line stays attached to
    // a real document (both lists are loaded on the fly once a client is picked).
    print '<td class="center" colspan="2"><div class="rcf-doclink">';
    print '<div class="rcf-doclink-row pr"><i class="fas fa-file-signature" title="' . dol_escape_htmltag($langs->trans('FollowupLinkProposal')) . '"></i>';
    print '<select name="audit_fk_propal" id="audit_fk_propal" class="rcf-docsel"><option value="0">' . dol_escape_htmltag($langs->trans('FollowupLinkPickClient')) . '</option></select></div>';
    print '<div class="rcf-doclink-row fa"><i class="fas fa-file-invoice-dollar" title="' . dol_escape_htmltag($langs->trans('FollowupLinkInvoice')) . '"></i>';
    print '<select name="audit_fk_facture" id="audit_fk_facture" class="rcf-docsel"><option value="0">' . dol_escape_htmltag($langs->trans('FollowupLinkPickClient')) . '</option></select></div>';
    print '</div></td>';
    print '<td class="center"><button type="submit" class="button button-add smallpaddingimp"><i class="fas fa-plus paddingright"></i>' . $langs->trans('FollowupAuditAdd') . '</button></td>';
    print '</form></tr>';
}
print '</table></div>';

if ($permissiontoadd) {
    // Same searchable dropdowns as the rest of Dolibarr for the two document pickers.
    print ajax_combobox('audit_fk_propal', [], 0, 0, 'resolve', '0');
    print ajax_combobox('audit_fk_facture', [], 0, 0, 'resolve', '0');
    // Fill the quote/invoice pickers of the "add an audit" line with the documents of the chosen client.
    print '<script>
    $(document).ready(function() {
        var url = "' . dol_escape_js(dol_buildpath('/custom/reedcrm/ajax/get_du_audit_documents.php', 1)) . '";
        var labels = {
            pick: ' . json_encode($langs->transnoentities('FollowupLinkPickClient')) . ',
            none: ' . json_encode($langs->transnoentities('FollowupLinkNoDocument')) . ',
            propal: ' . json_encode($langs->transnoentities('FollowupLinkProposal')) . ',
            facture: ' . json_encode($langs->transnoentities('FollowupLinkInvoice')) . '
        };
        var fill = function(id, rows, emptyLabel) {
            var $sel = $("#" + id).empty();
            $sel.append($("<option>").val(0).text(emptyLabel));
            $.each(rows, function(i, row) {
                $sel.append($("<option>").val(row.id).text(row.label));
            });
            // Let select2 redraw the freshly rebuilt option list.
            $sel.val(0).trigger("change");
        };
        $("#audit_fk_soc").on("change", function() {
            var socid = parseInt($(this).val(), 10) || 0;
            if (!socid) {
                fill("audit_fk_propal", [], labels.pick);
                fill("audit_fk_facture", [], labels.pick);
                return;
            }
            $.getJSON(url, { socid: socid }, function(data) {
                if (!data || !data.success) { return; }
                var propals = data.propals || [], factures = data.factures || [];
                fill("audit_fk_propal", propals, propals.length ? labels.propal : labels.none);
                fill("audit_fk_facture", factures, factures.length ? labels.facture : labels.none);
            });
        });
    });
    </script>';
}

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
print '<th class="center">' . $langs->trans('FollowupLastDuInvoice') . '</th><th class="center">' . $langs->trans('FollowupNextAuditPlanned') . '</th><th class="center">' . $langs->trans('FollowupAuditRdv') . '</th>';
print '<th class="center">' . $langs->trans('FollowupLate') . '</th>';
print '<th>' . $langs->trans('Service') . '</th><th class="right">' . $langs->trans('FollowupAmount') . '</th>';
print '<th class="center">' . $langs->trans('FollowupAssignedTo') . '</th>';
print '<th class="center">' . $langs->trans('FollowupProposalSent') . '</th>';
print '<th class="center">' . $langs->trans('Status') . '</th><th class="center maxwidthsearch"></th>';
print '</tr>';
if (empty($overdueAudits)) {
    print '<tr class="oddeven"><td colspan="12" class="center opacitymedium">' . $langs->trans('FollowupNoOverdue') . '</td></tr>';
} else {
    $overdueTotMontant = 0;
    foreach ($overdueAudits as $audit) {
        $printAuditRow($audit, true);
        $overdueTotMontant += (float) $audit['montant'];
    }
    print '<tr class="liste_total"><td colspan="7">' . $langs->trans('Total') . '</td><td class="right">' . price($overdueTotMontant, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td colspan="4"></td></tr>';
}
print '</table></div>';

// --- DU proposals signed but not invoiced (signed revenue still to bill) ---
$signedUnbilled = reedcrmFollowupGetSignedUnbilledDuProposals($db);
print '<br>';
$signedTot = 0;
foreach ($signedUnbilled as $sp) {
    $signedTot += (float) $sp['total_ttc'];
}
print load_fiche_titre('<i class="fas fa-file-signature paddingright" style="color:#6f42c1"></i>' . $langs->trans('FollowupSignedUnbilled') . ' <span class="badge">' . count($signedUnbilled) . '</span>', '', '');
print '<div class="div-table-responsive"><table class="tagtable liste">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans('ThirdParty') . '</th><th>' . $langs->trans('FollowupLocation') . '</th>';
print '<th>' . $langs->trans('FollowupProposal') . '</th><th class="center">' . $langs->trans('Date') . '</th>';
print '<th class="right">' . $langs->trans('AmountTTC') . '</th><th class="center maxwidthsearch"></th>';
print '</tr>';
if (empty($signedUnbilled)) {
    print '<tr class="oddeven"><td colspan="6" class="center opacitymedium">' . $langs->trans('FollowupSignedUnbilledEmpty') . '</td></tr>';
} else {
    foreach ($signedUnbilled as $sp) {
        $thirdpartyStatic->id     = $sp['fk_soc'];
        $thirdpartyStatic->name   = $sp['thirdparty'];
        $thirdpartyStatic->status = 1;
        print '<tr class="oddeven">';
        print '<td class="tdoverflowmax200">' . $thirdpartyStatic->getNomUrl(1) . '</td>';
        print '<td class="tdoverflowmax150">' . ($sp['location'] !== '' ? '<i class="fas fa-map-marker-alt paddingright opacitymedium"></i>' . dol_escape_htmltag($sp['location']) : '<span class="opacitymedium">-</span>') . '</td>';
        print '<td class="nowraponall"><a href="' . DOL_URL_ROOT . '/comm/propal/card.php?id=' . ((int) $sp['propal_id']) . '" target="_blank" rel="noopener"><i class="fas fa-file-invoice paddingright opacitymedium"></i>' . dol_escape_htmltag($sp['ref']) . '</a></td>';
        print '<td class="center nowraponall">' . (!empty($sp['date']) ? dol_print_date($sp['date'], 'day') : '') . '</td>';
        print '<td class="right nowraponall">' . ($sp['total_ttc'] !== null ? price($sp['total_ttc'], 0, $langs, 1, -1, -1, $conf->currency) : '') . '</td>';
        print '<td class="center"><a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/compta/facture/card.php?action=create&origin=propal&originid=' . ((int) $sp['propal_id']) . '&socid=' . ((int) $sp['fk_soc']) . '" title="' . dol_escape_htmltag($langs->trans('FollowupSignedUnbilledInvoice')) . '"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('FollowupSignedUnbilledInvoice') . '</a></td>';
        print '</tr>';
    }
    print '<tr class="liste_total"><td colspan="4">' . $langs->trans('Total') . '</td><td class="right">' . price($signedTot, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
