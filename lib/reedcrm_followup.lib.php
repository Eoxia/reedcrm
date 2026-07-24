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
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

/**
 * \file    lib/reedcrm_followup.lib.php
 * \ingroup reedcrm
 * \brief   Library of helper functions for the recurring invoice follow-up feature.
 */

/**
 * Guess the subscription tier from a recurring invoice title.
 *
 * @param  string $title Recurring invoice title.
 * @return string        Prestation key matching RecurringInvoiceFollowup::fields['prestation'].
 */
function reedcrmFollowupGuessPrestation(string $title): string
{
    $normalized = dol_strtolower($title);

    if (strpos($normalized, 'tpe') !== false) {
        return 'tpe';
    }
    if (strpos($normalized, 'plus') !== false || strpos($normalized, 'company +') !== false) {
        return 'company_plus';
    }
    if (strpos($normalized, 'unlimited') !== false) {
        return 'unlimited';
    }
    if (strpos($normalized, 'pme') !== false || strpos($normalized, 'small') !== false) {
        return 'small_company';
    }

    return 'company';
}

/**
 * Included monthly support time (SAV) per subscription tier, in seconds.
 * Based on the DigiRisk pricing grid: Small Company 15 min, Company 30 min,
 * Company Plus & Unlimited 60 min. TPE has startup-only assistance, so 0 recurring.
 *
 * @param  string $prestation Prestation key.
 * @return int                Included support time in seconds.
 */
function reedcrmFollowupSavSecondsForPrestation(string $prestation): int
{
    switch ($prestation) {
        case 'small_company':
            return 15 * 60;
        case 'company':
            return 30 * 60;
        case 'company_plus':
        case 'unlimited':
            return 60 * 60;
        default:
            return 0;
    }
}

/**
 * Compute the operational status of a follow-up from its billing booleans (mirrors the class helper, SQL-side data).
 *
 * @param  object $row Database row (facture_creee, facture_envoyee, facture_payee, paiement_ok, date_relance).
 * @param  int    $now Current timestamp.
 * @return string      Status code: paid | late | tobill | tosend | awaiting.
 */
function reedcrmFollowupStatusCode(object $row, int $now): string
{
    if (!empty($row->facture_payee)) {
        return 'paid';
    }
    $relanceReached = !empty($row->date_relance) && (int) $row->date_relance <= $now;
    if (empty($row->paiement_ok) && $relanceReached) {
        return 'late';
    }
    if (empty($row->facture_creee)) {
        return 'tobill';
    }
    if (empty($row->facture_envoyee)) {
        return 'tosend';
    }

    return 'awaiting';
}

/**
 * List the Document Unique audits due in a given month, read from the stored llx_reedcrm_du_audit table.
 *
 * Audits are seeded from invoiced DU audit services (product ref "DU_AU%") but are fully editable:
 * a client shows in the month of its planned next audit date. For the current month, audits overdue
 * within the last 12 months are also returned so recently-missed audits are not lost.
 *
 * @param  DoliDB $db             Database handler.
 * @param  int    $periodStart    First-day-of-month timestamp.
 * @param  int    $periodEnd      Last-day-of-month timestamp.
 * @param  bool   $includeOverdue Also return anniversaries already overdue (only meaningful for the current month).
 * @return array<int,array<string,mixed>> Rows: id, fk_soc, thirdparty, last_audit, next_audit, service, status, source, overdue.
 */
function reedcrmFollowupGetAuditsForMonth(DoliDB $db, int $periodStart, int $periodEnd, bool $includeOverdue = false): array
{
    $audits = [];

    // Lower bound: for the current month, reach back 12 months to catch recently-missed audits.
    $lowerBound = $includeOverdue ? dol_time_plus_duree($periodStart, -12, 'm') : $periodStart;

    $sql  = 'SELECT a.rowid, a.fk_soc, a.last_audit_date, a.next_audit_date, a.note, a.montant, a.status, a.source, a.proposal_sent_date, a.fk_propal, a.fk_user_assign,';
    $sql .= ' pr.rowid as propal_rowid, pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut, pr.datep as propal_date,';
    $sql .= ' fa.rowid as facture_rowid, fa.ref as facture_ref, fa.total_ttc as facture_ttc, fa.paye as facture_paye, fa.datef as facture_date,';
    $sql .= ' s.nom as thirdparty_name, s.address, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    // Derive the renewal quote: the client's latest DU_AU proposal dated after the last audit.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = (';
    $sql .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
    $sql .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1)';
    // Derive the renewal invoice: the client's latest DU_AU invoice dated after the last audit.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = (';
    $sql .= '   SELECT f2.rowid FROM ' . MAIN_DB_PREFIX . 'facture f2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd2 ON fd2.fk_facture = f2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prodf ON prodf.rowid = fd2.fk_product AND prodf.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE f2.fk_soc = a.fk_soc AND f2.type <> 2 AND f2.entity IN (' . getEntity('facture') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR f2.datef > a.last_audit_date)';
    $sql .= '   ORDER BY f2.datef DESC, f2.rowid DESC LIMIT 1)';
    $sql .= ' WHERE a.entity IN (' . getEntity('reedcrm_du_audit') . ')';
    $sql .= " AND a.next_audit_date >= '" . $db->idate($lowerBound) . "'";
    $sql .= " AND a.next_audit_date <= '" . $db->idate($periodEnd) . "'";
    $sql .= ' ORDER BY a.next_audit_date ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $nextAudit = $db->jdate($obj->next_audit_date);
            $location  = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $audits[]  = [
                'id'           => (int) $obj->rowid,
                'fk_soc'       => (int) $obj->fk_soc,
                'thirdparty'   => $obj->thirdparty_name,
                'last_audit'   => !empty($obj->last_audit_date) ? $db->jdate($obj->last_audit_date) : 0,
                'next_audit'   => $nextAudit,
                'service'      => $obj->note,
                'montant'      => $obj->montant !== null ? (float) $obj->montant : null,
                'status'       => (int) $obj->status,
                'source'       => $obj->source,
                'proposal_sent' => !empty($obj->proposal_sent_date) ? $db->jdate($obj->proposal_sent_date) : 0,
                'propal_id'    => (int) $obj->propal_rowid,
                'propal_ref'   => $obj->propal_ref,
                'propal_ttc'   => $obj->propal_ttc !== null ? (float) $obj->propal_ttc : null,
                'propal_statut' => $obj->propal_statut !== null ? (int) $obj->propal_statut : null,
                'propal_date'  => !empty($obj->propal_date) ? $db->jdate($obj->propal_date) : 0,
                'facture_id'   => (int) $obj->facture_rowid,
                'facture_ref'  => $obj->facture_ref,
                'facture_ttc'  => $obj->facture_ttc !== null ? (float) $obj->facture_ttc : null,
                'facture_paye' => (int) $obj->facture_paye,
                'facture_date' => !empty($obj->facture_date) ? $db->jdate($obj->facture_date) : 0,
                'assigned'     => (int) $obj->fk_user_assign,
                'overdue'      => $nextAudit < $periodStart,
                'location'     => $location,
                'address'      => trim(($obj->address ?? '') . ' ' . $location),
            ];
        }
    }

    return $audits;
}

/**
 * List ALL overdue Document Unique audits (next audit date already passed, not done yet), globally.
 *
 * @param  DoliDB $db Database handler.
 * @return array<int,array<string,mixed>> Rows as in reedcrmFollowupGetAuditsForMonth() plus 'days_late'.
 */
function reedcrmFollowupGetOverdueAudits(DoliDB $db): array
{
    $now    = dol_now();
    $audits = [];

    $sql  = 'SELECT a.rowid, a.fk_soc, a.last_audit_date, a.next_audit_date, a.note, a.montant, a.status, a.source, a.proposal_sent_date, a.fk_propal, a.fk_user_assign,';
    $sql .= ' pr.rowid as propal_rowid, pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut, pr.datep as propal_date,';
    $sql .= ' fa.rowid as facture_rowid, fa.ref as facture_ref, fa.total_ttc as facture_ttc, fa.paye as facture_paye, fa.datef as facture_date,';
    $sql .= ' s.nom as thirdparty_name, s.address, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    // Derive the renewal quote: the client's latest DU_AU proposal dated after the last audit.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = (';
    $sql .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
    $sql .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1)';
    // Derive the renewal invoice: the client's latest DU_AU invoice dated after the last audit.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = (';
    $sql .= '   SELECT f2.rowid FROM ' . MAIN_DB_PREFIX . 'facture f2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd2 ON fd2.fk_facture = f2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prodf ON prodf.rowid = fd2.fk_product AND prodf.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE f2.fk_soc = a.fk_soc AND f2.type <> 2 AND f2.entity IN (' . getEntity('facture') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR f2.datef > a.last_audit_date)';
    $sql .= '   ORDER BY f2.datef DESC, f2.rowid DESC LIMIT 1)';
    $sql .= ' WHERE a.entity IN (' . getEntity('reedcrm_du_audit') . ')';
    $sql .= ' AND a.status <> 2'; // 2 = done
    $sql .= " AND a.next_audit_date < '" . $db->idate($now) . "'";
    // Keep clients that are still active thirdparties (skip only closed/churned ones).
    $sql .= ' AND s.status = 1';
    // Ignore very old audits (next audit due before 2016).
    $sql .= " AND a.next_audit_date >= '2016-01-01'";
    $sql .= ' ORDER BY a.next_audit_date ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $nextAudit = $db->jdate($obj->next_audit_date);
            $location  = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $audits[]  = [
                'id'         => (int) $obj->rowid,
                'fk_soc'     => (int) $obj->fk_soc,
                'thirdparty' => $obj->thirdparty_name,
                'last_audit' => !empty($obj->last_audit_date) ? $db->jdate($obj->last_audit_date) : 0,
                'next_audit' => $nextAudit,
                'service'    => $obj->note,
                'montant'    => $obj->montant !== null ? (float) $obj->montant : null,
                'status'     => (int) $obj->status,
                'source'     => $obj->source,
                'proposal_sent' => !empty($obj->proposal_sent_date) ? $db->jdate($obj->proposal_sent_date) : 0,
                'propal_id'  => (int) $obj->propal_rowid,
                'propal_ref' => $obj->propal_ref,
                'propal_ttc' => $obj->propal_ttc !== null ? (float) $obj->propal_ttc : null,
                'propal_statut' => $obj->propal_statut !== null ? (int) $obj->propal_statut : null,
                'propal_date' => !empty($obj->propal_date) ? $db->jdate($obj->propal_date) : 0,
                'facture_id'  => (int) $obj->facture_rowid,
                'facture_ref' => $obj->facture_ref,
                'facture_ttc' => $obj->facture_ttc !== null ? (float) $obj->facture_ttc : null,
                'facture_paye' => (int) $obj->facture_paye,
                'facture_date' => !empty($obj->facture_date) ? $db->jdate($obj->facture_date) : 0,
                'assigned'   => (int) $obj->fk_user_assign,
                'overdue'    => true,
                'location'   => $location,
                'address'    => trim(($obj->address ?? '') . ' ' . $location),
                'days_late'  => (int) floor(($now - $nextAudit) / 86400),
            ];
        }
    }

    return $audits;
}

/**
 * List ALL overdue recurring-invoice follow-ups: renewal month already passed and invoice not paid yet.
 *
 * @param  DoliDB $db                Database handler.
 * @param  int    $currentMonthStart First-day-of-current-month timestamp.
 * @return array<int,array<string,mixed>> Rows: id, ref, fk_soc, thirdparty, prestation, montant_ttc, period, code, label, days_late.
 */
function reedcrmFollowupGetOverdueFollowups(DoliDB $db, int $currentMonthStart): array
{
    $now  = dol_now();
    $rows = [];

    $sql  = 'SELECT t.rowid, t.ref, t.fk_soc, t.prestation, t.montant_ttc, t.period,';
    $sql .= ' t.facture_creee, t.facture_envoyee, t.facture_payee, t.paiement_ok, t.date_relance, s.nom as thirdparty_name';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
    $sql .= ' WHERE t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
    $sql .= ' AND t.status = 1 AND t.facture_payee = 0';
    $sql .= " AND t.period < '" . $db->idate($currentMonthStart) . "'";
    $sql .= ' ORDER BY t.period ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $period = $db->jdate($obj->period);
            $code   = reedcrmFollowupStatusCode($obj, $now);
            $rows[] = [
                'id'          => (int) $obj->rowid,
                'ref'         => $obj->ref,
                'fk_soc'      => (int) $obj->fk_soc,
                'thirdparty'  => $obj->thirdparty_name,
                'prestation'  => $obj->prestation,
                'montant_ttc' => (float) $obj->montant_ttc,
                'period'      => $period,
                'code'        => $code,
                'days_late'   => (int) floor(($now - $period) / 86400),
            ];
        }
    }

    return $rows;
}

/**
 * Build the "to process this month" dashboard data for the recurring invoice follow-up.
 *
 * @param  DoliDB $db          Database handler.
 * @param  int    $periodStart First-day-of-month timestamp of the wanted period.
 * @param  int    $periodEnd   Last-day-of-month timestamp of the wanted period.
 * @return array<string,mixed> Dashboard data (counts, amounts, du alerts, rows to process).
 */
function reedcrmFollowupGetDashboardData(DoliDB $db, int $periodStart, int $periodEnd): array
{
    $now  = dol_now();
    $data = [
        'counts'      => ['tobill' => 0, 'tosend' => 0, 'awaiting' => 0, 'paid' => 0, 'late' => 0, 'total' => 0],
        'montant_ttc' => 0,
        'temps_sav'   => 0,
        'montant_pr'  => 0,
        'du_alerts'   => [],
        'to_process'  => [],
    ];

    // Current month follow-ups.
    $sql  = 'SELECT t.rowid, t.ref, t.fk_soc, t.prestation, t.montant_ttc, t.montant_pr, t.temps_sav,';
    $sql .= ' t.facture_creee, t.facture_envoyee, t.facture_payee, t.paiement_ok, t.date_relance, s.nom as thirdparty_name';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
    $sql .= ' WHERE t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
    $sql .= " AND t.status = 1";
    $sql .= " AND t.period >= '" . $db->idate($periodStart) . "'";
    $sql .= " AND t.period <= '" . $db->idate($periodEnd) . "'";
    $sql .= ' ORDER BY t.montant_ttc DESC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $code = reedcrmFollowupStatusCode($obj, $now);
            $data['counts'][$code]++;
            $data['counts']['total']++;
            $data['montant_ttc'] += (float) $obj->montant_ttc;
            $data['montant_pr']  += (float) $obj->montant_pr;
            $data['temps_sav']   += (int) $obj->temps_sav;

            if (in_array($code, ['tobill', 'tosend', 'late'])) {
                $data['to_process'][] = [
                    'id'          => (int) $obj->rowid,
                    'ref'         => $obj->ref,
                    'thirdparty'  => $obj->thirdparty_name,
                    'prestation'  => $obj->prestation,
                    'montant_ttc' => (float) $obj->montant_ttc,
                    'code'        => $code,
                ];
            }
        }
    }

    // Document Unique renewals due (anniversary within the alert offset window, regardless of month).
    $offsetMonths = (int) getDolGlobalInt('REEDCRM_DU_ALERT_OFFSET_MONTHS', 1);
    $windowEnd    = dol_time_plus_duree($now, $offsetMonths, 'm');

    $sqlDu  = 'SELECT t.rowid, t.ref, t.fk_soc, t.next_maj_du, s.nom as thirdparty_name';
    $sqlDu .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
    $sqlDu .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
    $sqlDu .= ' WHERE t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
    $sqlDu .= ' AND t.status = 1 AND t.next_maj_du IS NOT NULL';
    $sqlDu .= " AND t.next_maj_du <= '" . $db->idate($windowEnd) . "'";
    $sqlDu .= ' ORDER BY t.next_maj_du ASC';

    $resqlDu = $db->query($sqlDu);
    if ($resqlDu) {
        while ($obj = $db->fetch_object($resqlDu)) {
            $data['du_alerts'][] = [
                'id'          => (int) $obj->rowid,
                'ref'         => $obj->ref,
                'thirdparty'  => $obj->thirdparty_name,
                'next_maj_du' => $db->jdate($obj->next_maj_du),
            ];
        }
    }

    return $data;
}

/**
 * List active thirdparties that use Digirisk but have NO active recurring invoice (subscription).
 * "Uses Digirisk" = invoiced for a Digirisk tier (products D1..D5/D41) OR has an active project whose
 * title/notes reference a *.digirisk.com instance. Helps spot subscription gaps.
 *
 * @param  DoliDB $db Database handler.
 * @return array<int,array<string,mixed>> Rows: fk_soc, thirdparty, location, last_tier, last_date, instance, project_id.
 */
function reedcrmFollowupGetDigiriskWithoutSubscription(DoliDB $db): array
{
    $rows    = [];
    $tiers   = "'D1','D2','D3','D4','D41','D5'";
    $entSoc  = getEntity('facture');
    $entProj = getEntity('project');
    $projGrp = "(pj.title LIKE '%digirisk.com%' OR pj.note_public LIKE '%digirisk.com%' OR pj.note_private LIKE '%digirisk.com%')";

    // A client "uses Digirisk" if invoiced for a tier product OR has an active digirisk.com project.
    $tierExists = 'EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'facture f INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd ON fd.fk_facture = f.rowid'
        . ' INNER JOIN ' . MAIN_DB_PREFIX . 'product p ON p.rowid = fd.fk_product AND p.ref IN (' . $tiers . ')'
        . ' WHERE f.fk_soc = s.rowid AND f.type <> 2 AND f.entity IN (' . $entSoc . '))';
    // A real Digirisk instance = an open delivery project (not an opportunity) OR a WON opportunity —
    // never an open sales opportunity (Prospection/Proposal/etc.), which is just pipeline.
    $projReal   = '(pj.usage_opportunity = 0 OR pj.fk_opp_status = 6)';
    $projExists = 'EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'projet pj WHERE pj.fk_soc = s.rowid AND pj.fk_statut = 1 AND pj.entity IN (' . $entProj . ') AND ' . $projGrp . ' AND ' . $projReal . ')';

    $sql  = 'SELECT s.rowid as fk_soc, s.nom as thirdparty_name, s.zip, s.town,';
    $sql .= ' (SELECT p2.label FROM ' . MAIN_DB_PREFIX . 'facture f2 INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd2 ON fd2.fk_facture = f2.rowid INNER JOIN ' . MAIN_DB_PREFIX . 'product p2 ON p2.rowid = fd2.fk_product AND p2.ref IN (' . $tiers . ') WHERE f2.fk_soc = s.rowid AND f2.type <> 2 AND f2.entity IN (' . $entSoc . ') ORDER BY f2.datef DESC, fd2.rowid DESC LIMIT 1) as last_tier,';
    $sql .= ' (SELECT MAX(f3.datef) FROM ' . MAIN_DB_PREFIX . 'facture f3 INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd3 ON fd3.fk_facture = f3.rowid INNER JOIN ' . MAIN_DB_PREFIX . 'product p3 ON p3.rowid = fd3.fk_product AND p3.ref IN (' . $tiers . ') WHERE f3.fk_soc = s.rowid AND f3.type <> 2 AND f3.entity IN (' . $entSoc . ')) as last_date,';
    $sql .= ' (SELECT pj2.rowid FROM ' . MAIN_DB_PREFIX . "projet pj2 WHERE pj2.fk_soc = s.rowid AND pj2.fk_statut = 1 AND pj2.entity IN (" . $entProj . ") AND (pj2.title LIKE '%digirisk.com%' OR pj2.note_public LIKE '%digirisk.com%' OR pj2.note_private LIKE '%digirisk.com%') AND (pj2.usage_opportunity = 0 OR pj2.fk_opp_status = 6) ORDER BY pj2.rowid DESC LIMIT 1) as project_id,";
    $sql .= ' (SELECT pj3.title FROM ' . MAIN_DB_PREFIX . "projet pj3 WHERE pj3.fk_soc = s.rowid AND pj3.fk_statut = 1 AND pj3.entity IN (" . $entProj . ") AND (pj3.title LIKE '%digirisk.com%' OR pj3.note_public LIKE '%digirisk.com%' OR pj3.note_private LIKE '%digirisk.com%') AND (pj3.usage_opportunity = 0 OR pj3.fk_opp_status = 6) ORDER BY pj3.rowid DESC LIMIT 1) as instance";
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
    // Real customers only: flagged as customer (client 1/3) OR already invoiced at least once
    // (a prospect that has invoices is a de facto customer whose flag was never updated).
    $sql .= ' WHERE s.status = 1';
    $sql .= ' AND (s.client IN (1, 3) OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'facture fbill WHERE fbill.fk_soc = s.rowid AND fbill.entity IN (' . $entSoc . ')))';
    // Exclude any client that already has a recurring invoice, even a deactivated (suspended) one:
    // a paused subscription is a deliberate choice, not a "no subscription" gap.
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'facture_rec fr WHERE fr.fk_soc = s.rowid)';
    // Exclude clients manually dismissed from this list.
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'reedcrm_digirisk_dismissed d WHERE d.fk_soc = s.rowid AND d.entity IN (' . getEntity('reedcrm_du_audit') . '))';
    $sql .= ' AND (' . $tierExists . ' OR ' . $projExists . ')';
    $sql .= ' ORDER BY last_date DESC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $location = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $rows[]   = [
                'fk_soc'     => (int) $obj->fk_soc,
                'thirdparty' => $obj->thirdparty_name,
                'location'   => $location,
                'last_tier'  => $obj->last_tier,
                'last_date'  => !empty($obj->last_date) ? $db->jdate($obj->last_date) : 0,
                'instance'   => $obj->instance,
                'project_id' => (int) $obj->project_id,
            ];
        }
    }

    return $rows;
}

/**
 * Billing gap: signed proposals (any product) from the last 3 years with no invoice linked.
 *
 * @param  DoliDB $db    Database handler.
 * @param  int    $limit Max rows.
 * @return array<int,array<string,mixed>> Rows: id, ref, fk_soc, thirdparty, date, total_ttc.
 */
function reedcrmBillingGetSignedUnbilledProposals(DoliDB $db, int $limit = 500): array
{
    $rows = [];
    $sql  = 'SELECT pr.rowid as id, pr.ref, pr.datep, pr.total_ttc, s.rowid as fk_soc, s.nom as thirdparty_name';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal as pr INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = pr.fk_soc';
    $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ') AND pr.fk_statut = 2 AND pr.datep >= DATE_SUB(NOW(), INTERVAL 3 YEAR)';
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "element_element ee WHERE ee.fk_source = pr.rowid AND ee.sourcetype = 'propal' AND ee.targettype = 'facture')";
    $sql .= ' ORDER BY pr.datep DESC' . $db->plimit($limit);
    $resql = $db->query($sql);
    if ($resql) {
        while ($o = $db->fetch_object($resql)) {
            $rows[] = ['id' => (int) $o->id, 'ref' => $o->ref, 'fk_soc' => (int) $o->fk_soc, 'thirdparty' => $o->thirdparty_name, 'date' => !empty($o->datep) ? $db->jdate($o->datep) : 0, 'total_ttc' => (float) $o->total_ttc];
        }
    }
    return $rows;
}

/**
 * Billing gap: validated/ongoing customer orders from the last 3 years with no invoice linked.
 *
 * @param  DoliDB $db    Database handler.
 * @param  int    $limit Max rows.
 * @return array<int,array<string,mixed>> Rows: id, ref, fk_soc, thirdparty, date, total_ttc.
 */
function reedcrmBillingGetUnbilledOrders(DoliDB $db, int $limit = 500): array
{
    $rows = [];
    $sql  = 'SELECT c.rowid as id, c.ref, c.date_commande, c.total_ttc, s.rowid as fk_soc, s.nom as thirdparty_name';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'commande as c INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = c.fk_soc';
    $sql .= ' WHERE c.entity IN (' . getEntity('commande') . ') AND c.fk_statut IN (1, 2) AND c.date_commande >= DATE_SUB(NOW(), INTERVAL 3 YEAR)';
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "element_element ee WHERE ee.fk_source = c.rowid AND ee.sourcetype = 'commande' AND ee.targettype = 'facture')";
    $sql .= ' ORDER BY c.date_commande DESC' . $db->plimit($limit);
    $resql = $db->query($sql);
    if ($resql) {
        while ($o = $db->fetch_object($resql)) {
            $rows[] = ['id' => (int) $o->id, 'ref' => $o->ref, 'fk_soc' => (int) $o->fk_soc, 'thirdparty' => $o->thirdparty_name, 'date' => !empty($o->date_commande) ? $db->jdate($o->date_commande) : 0, 'total_ttc' => (float) $o->total_ttc];
        }
    }
    return $rows;
}

/**
 * Billing gap: active recurring invoice templates whose next generation date is already past
 * (an invoice should have been generated but was not).
 *
 * @param  DoliDB $db Database handler.
 * @return array<int,array<string,mixed>> Rows: id, ref, fk_soc, thirdparty, date_when, total_ttc.
 */
function reedcrmBillingGetOverdueRecurring(DoliDB $db): array
{
    $rows = [];
    $sql  = 'SELECT fr.rowid as id, fr.titre, fr.total_ttc, fr.date_when, s.rowid as fk_soc, s.nom as thirdparty_name';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = fr.fk_soc';
    $sql .= ' WHERE fr.entity IN (' . getEntity('facturerec') . ') AND fr.suspended = 0 AND fr.frequency > 0 AND fr.date_when IS NOT NULL AND fr.date_when < NOW()';
    $sql .= ' ORDER BY fr.date_when ASC';
    $resql = $db->query($sql);
    if ($resql) {
        while ($o = $db->fetch_object($resql)) {
            $rows[] = ['id' => (int) $o->id, 'titre' => $o->titre, 'fk_soc' => (int) $o->fk_soc, 'thirdparty' => $o->thirdparty_name, 'date_when' => !empty($o->date_when) ? $db->jdate($o->date_when) : 0, 'total_ttc' => (float) $o->total_ttc];
        }
    }
    return $rows;
}
