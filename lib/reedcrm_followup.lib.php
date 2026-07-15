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
    $sql .= ' pr.rowid as propal_rowid, pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut,';
    $sql .= ' s.nom as thirdparty_name, s.address, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    // Derive the renewal quote: the client's latest DU_AU proposal dated after the last audit.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = (';
    $sql .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_AU%'";
    $sql .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
    $sql .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1)';
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
    $sql .= ' pr.rowid as propal_rowid, pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut,';
    $sql .= ' s.nom as thirdparty_name, s.address, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    // Derive the renewal quote: the client's latest DU_AU proposal dated after the last audit.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = (';
    $sql .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_AU%'";
    $sql .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
    $sql .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1)';
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
