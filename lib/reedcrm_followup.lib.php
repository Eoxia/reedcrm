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
        // done / todo = traceability of the month's billing run: an invoice was really generated, or not.
        'counts'      => ['tobill' => 0, 'tosend' => 0, 'awaiting' => 0, 'paid' => 0, 'late' => 0, 'done' => 0, 'todo' => 0, 'total' => 0],
        'montant_ttc' => 0,
        'temps_sav'   => 0,
        'montant_pr'  => 0,
        'du_alerts'   => [],
        'to_process'  => [],
    ];

    // Current month = active recurring templates (factures modèles) due this month, read live.
    // Manual annotations + billing sync come from the stored follow-up (t) when it exists.
    $browsedMonth = (int) dol_print_date($periodStart, '%m');
    $browsedYear  = (int) dol_print_date($periodStart, '%Y');
    $sql  = 'SELECT fr.rowid as frec_id, fr.titre as frec_titre, fr.fk_soc, fr.total_ttc as montant_ttc,';
    $sql .= ' t.prestation, t.montant_pr, t.temps_sav, t.facture_creee, t.facture_envoyee, t.facture_payee, t.paiement_ok, t.date_relance,';
    $sql .= ' fa.datef as gen_date, fa.paye as gen_paye,';
    $sql .= ' s.nom as thirdparty_name';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
    // At most ONE annotation per template (old data may hold several rows per template) — avoids row duplication.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t ON t.rowid = (SELECT t9.rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup t9';
    $sql .= '   WHERE t9.fk_facture_rec = fr.rowid AND t9.entity IN (' . getEntity('reedcrm_facturerec_followup') . ') ORDER BY t9.rowid DESC' . $db->plimit(1) . ')';
    // The invoice actually generated from this template in the browsed month+year (done or not).
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = (SELECT f9.rowid FROM ' . MAIN_DB_PREFIX . 'facture f9';
    $sql .= '   WHERE f9.fk_fac_rec_source = fr.rowid AND f9.type <> 2 AND f9.entity IN (' . getEntity('facture') . ')';
    $sql .= '   AND MONTH(f9.datef) = ' . $browsedMonth . ' AND YEAR(f9.datef) = ' . $browsedYear . ' ORDER BY f9.datef DESC' . $db->plimit(1) . ')';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = fr.fk_soc';
    $sql .= ' WHERE fr.entity IN (' . getEntity('facturerec') . ') AND fr.frequency > 0 AND fr.fk_soc > 0';
    // A template belongs to the browsed month either because its next generation falls in that month
    // (still to bill, like the native "Factures modèles" filter), or because an invoice was really
    // generated from it that month. The second branch keeps the traceability of finished months: once
    // the invoices are generated, date_when has already moved to the next period.
    $sql .= ' AND ((fr.suspended = 0 AND MONTH(fr.date_when) = ' . $browsedMonth . ' AND YEAR(fr.date_when) = ' . $browsedYear . ') OR fa.rowid IS NOT NULL)';
    $sql .= ' ORDER BY fr.total_ttc DESC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $prestation = !empty($obj->prestation) ? $obj->prestation : reedcrmFollowupGuessPrestation((string) $obj->frec_titre);
            $tempsSav   = $obj->temps_sav !== null ? (int) $obj->temps_sav : reedcrmFollowupSavSecondsForPrestation($prestation);
            // Billing status from the invoice really generated this month (done), else the annotation.
            if (!empty($obj->gen_date)) {
                $obj->facture_creee = 1;
                $obj->facture_payee = (int) $obj->gen_paye;
                $data['counts']['done']++;
            } else {
                $data['counts']['todo']++;
            }
            $code       = reedcrmFollowupStatusCode($obj, $now);
            $data['counts'][$code]++;
            $data['counts']['total']++;
            $data['montant_ttc'] += (float) $obj->montant_ttc;
            $data['montant_pr']  += (float) $obj->montant_pr;
            $data['temps_sav']   += $tempsSav;

            if (in_array($code, ['tobill', 'tosend', 'late'])) {
                $data['to_process'][] = [
                    'id'          => (int) $obj->frec_id,
                    'ref'         => $obj->frec_titre,
                    'thirdparty'  => $obj->thirdparty_name,
                    'prestation'  => $prestation,
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
    $sqlDu .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture_rec as fr ON fr.rowid = t.fk_facture_rec AND fr.suspended = 0';
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
 * List Document Unique proposals that are SIGNED but were never invoiced (no linked invoice),
 * i.e. signed revenue still to bill. A DU proposal = a proposal with a "DU_A%" product line.
 *
 * @param  DoliDB $db Database handler.
 * @return array<int,array<string,mixed>> Rows: propal_id, ref, fk_soc, thirdparty, location, date, total_ttc.
 */
function reedcrmFollowupGetSignedUnbilledDuProposals(DoliDB $db): array
{
    $rows = [];

    $sql  = 'SELECT pr.rowid as propal_id, pr.ref, pr.datep, pr.total_ttc, s.rowid as fk_soc, s.nom as thirdparty_name, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal as pr';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = pr.fk_soc';
    // A Digirisk/DU product = a DU service (DU_A%) or a Digirisk SaaS tier (D1..D5/D41).
    $prodProp = "(p.ref LIKE 'DU\_A%' OR p.ref IN ('D1','D2','D3','D4','D41','D5'))";
    $prodFact = "(pf.ref LIKE 'DU\_A%' OR pf.ref IN ('D1','D2','D3','D4','D41','D5'))";

    $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ') AND pr.fk_statut = 2'; // 2 = signed
    // Only the last 3 years: older signed quotes cannot realistically be invoiced anymore.
    $sql .= ' AND pr.datep >= DATE_SUB(NOW(), INTERVAL 3 YEAR)';
    $sql .= ' AND EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'propaldet pd INNER JOIN ' . MAIN_DB_PREFIX . 'product p ON p.rowid = pd.fk_product AND ' . $prodProp . ' WHERE pd.fk_propal = pr.rowid)';
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "element_element ee WHERE ee.fk_source = pr.rowid AND ee.sourcetype = 'propal' AND ee.targettype = 'facture')";
    // Not billed for real: no Digirisk/DU invoice on/after the quote date (catches billing via a
    // recurring template or an independent invoice, where no propal->facture link exists).
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'facture f INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd ON fd.fk_facture = f.rowid INNER JOIN ' . MAIN_DB_PREFIX . 'product pf ON pf.rowid = fd.fk_product AND ' . $prodFact . ' WHERE f.fk_soc = pr.fk_soc AND f.type <> 2 AND f.entity IN (' . getEntity('facture') . ') AND f.datef >= pr.datep)';
    $sql .= ' ORDER BY pr.datep DESC, pr.rowid DESC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $location = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $rows[]   = [
                'propal_id'  => (int) $obj->propal_id,
                'ref'        => $obj->ref,
                'fk_soc'     => (int) $obj->fk_soc,
                'thirdparty' => $obj->thirdparty_name,
                'location'   => $location,
                'date'       => !empty($obj->datep) ? $db->jdate($obj->datep) : 0,
                'total_ttc'  => $obj->total_ttc !== null ? (float) $obj->total_ttc : null,
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

/**
 * Signed proposals with no invoice linked, cross-checked against the customer invoices to detect
 * the ones that WERE actually billed, just outside the quote -> invoice link (invoice created from
 * scratch, from the order, from the contract...).
 *
 * Each returned row carries a "match" telling how sure we are it was already billed:
 *  - 'chain'    : an invoice exists through the order or the contract created from this quote (billed).
 *  - 'amount'   : an invoice of the same customer, dated after the signature, has the very same amount.
 *  - 'products' : invoices of the same customer, dated after the signature, carry the same products.
 *  - ''         : nothing found, really still to invoice.
 *
 * @param  DoliDB $db     Database handler.
 * @param  int    $months Only quotes signed within the last X months (older ones cannot be billed anymore).
 * @param  int    $limit  Max rows.
 * @return array<int,array<string,mixed>> Rows: id, ref, fk_soc, thirdparty, date_sig, total_ht, total_ttc,
 *                                        fk_user, project_ref, match, match_invoices, client_nb, client_total.
 */
function reedcrmSignedUnbilledGetProposals(DoliDB $db, int $months = 24, int $limit = 500): array
{
    $months  = max(1, $months);
    $entProp = getEntity('propal');
    $entFact = getEntity('facture');
    $rows    = [];

    // 1. Candidates: signed quotes (status 2) with no invoice linked. Dolibarr stores the link either
    // way round depending on how the invoice was created, so both directions must be excluded.
    $sql  = 'SELECT pr.rowid as id, pr.ref, pr.datep, pr.date_signature, pr.total_ht, pr.total_ttc,';
    $sql .= ' pr.fk_user_signature, pr.fk_user_author, pr.fk_projet, p.ref as project_ref,';
    $sql .= ' s.rowid as fk_soc, s.nom as thirdparty_name, s.zip, s.town, s.status as soc_status';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal as pr';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = pr.fk_soc';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = pr.fk_projet';
    $sql .= ' WHERE pr.entity IN (' . $entProp . ') AND pr.fk_statut = 2'; // 2 = signed
    $sql .= ' AND COALESCE(pr.date_signature, pr.datep) >= DATE_SUB(NOW(), INTERVAL ' . $months . ' MONTH)';
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "element_element ee WHERE ee.fk_source = pr.rowid AND ee.sourcetype = 'propal' AND ee.targettype = 'facture')";
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "element_element er WHERE er.fk_target = pr.rowid AND er.targettype = 'propal' AND er.sourcetype = 'facture')";
    $sql .= ' ORDER BY COALESCE(pr.date_signature, pr.datep) DESC, pr.rowid DESC' . $db->plimit($limit);

    $resql = $db->query($sql);
    if (!$resql) {
        return $rows;
    }
    $socIds = [];
    while ($obj = $db->fetch_object($resql)) {
        $dateSig      = !empty($obj->date_signature) ? $db->jdate($obj->date_signature) : (!empty($obj->datep) ? $db->jdate($obj->datep) : 0);
        $location     = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
        $id           = (int) $obj->id;
        $socIds[]     = (int) $obj->fk_soc;
        $rows[$id]    = [
            'id'             => $id,
            'ref'            => $obj->ref,
            'fk_soc'         => (int) $obj->fk_soc,
            'thirdparty'     => $obj->thirdparty_name,
            'soc_status'     => (int) $obj->soc_status,
            'location'       => $location,
            'date_sig'       => $dateSig,
            'date_prop'      => !empty($obj->datep) ? $db->jdate($obj->datep) : 0,
            'total_ht'       => (float) $obj->total_ht,
            'total_ttc'      => (float) $obj->total_ttc,
            'fk_user'        => (int) ($obj->fk_user_signature ?: $obj->fk_user_author),
            'project_ref'    => $obj->project_ref,
            'match'          => '',
            'match_invoices' => [],
            'client_nb'      => 0,
            'client_total'   => 0.0,
        ];
    }
    if (empty($rows)) {
        return $rows;
    }
    $propalIds = implode(',', array_map('intval', array_keys($rows)));
    $socIdsIn  = implode(',', array_unique(array_map('intval', $socIds)));

    // 2. Billed through the order or the contract born from this quote (propal -> commande|contrat -> facture).
    $sqlChain  = 'SELECT e1.fk_source as propal_id, e1.targettype as via, f.rowid as invoice_id, f.ref, f.datef, f.total_ttc, f.fk_statut, f.type';
    $sqlChain .= ' FROM ' . MAIN_DB_PREFIX . 'element_element as e1';
    $sqlChain .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'element_element as e2 ON e2.fk_source = e1.fk_target AND e2.sourcetype = e1.targettype';
    $sqlChain .= " AND e2.targettype = 'facture'";
    $sqlChain .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = e2.fk_target';
    $sqlChain .= " WHERE e1.sourcetype = 'propal' AND e1.targettype IN ('commande', 'contrat') AND e1.fk_source IN (" . $propalIds . ')';
    $resChain  = $db->query($sqlChain);
    if ($resChain) {
        while ($o = $db->fetch_object($resChain)) {
            $pid = (int) $o->propal_id;
            if (!isset($rows[$pid])) {
                continue;
            }
            $rows[$pid]['match']            = 'chain';
            $rows[$pid]['match_invoices'][] = ['id' => (int) $o->invoice_id, 'ref' => $o->ref, 'date' => !empty($o->datef) ? $db->jdate($o->datef) : 0, 'total_ttc' => (float) $o->total_ttc, 'status' => (int) $o->fk_statut, 'via' => $o->via];
        }
    }

    // 3. All the invoices of those customers, to look for a separate billing (same amount / same products).
    $invoices = [];
    $sqlInv   = 'SELECT f.rowid as id, f.ref, f.fk_soc, f.datef, f.total_ht, f.total_ttc, f.fk_statut, f.type';
    $sqlInv  .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
    $sqlInv  .= ' WHERE f.entity IN (' . $entFact . ') AND f.fk_soc IN (' . $socIdsIn . ')';
    $sqlInv  .= ' AND f.type <> 2 AND f.fk_statut <> 3'; // no credit note, no abandoned invoice
    $sqlInv  .= ' AND f.datef >= DATE_SUB(NOW(), INTERVAL ' . ($months + 6) . ' MONTH)';
    $resInv   = $db->query($sqlInv);
    if ($resInv) {
        while ($o = $db->fetch_object($resInv)) {
            $invoices[(int) $o->fk_soc][] = ['id' => (int) $o->id, 'ref' => $o->ref, 'date' => !empty($o->datef) ? $db->jdate($o->datef) : 0, 'total_ht' => (float) $o->total_ht, 'total_ttc' => (float) $o->total_ttc, 'status' => (int) $o->fk_statut, 'type' => (int) $o->type];
        }
    }

    // 4. Same products invoiced to the same customer after the signature (partial or reworded billing).
    $prodTotal = [];
    $resPt     = $db->query('SELECT fk_propal, COUNT(DISTINCT fk_product) as nb FROM ' . MAIN_DB_PREFIX . 'propaldet WHERE fk_propal IN (' . $propalIds . ') AND fk_product > 0 GROUP BY fk_propal');
    if ($resPt) {
        while ($o = $db->fetch_object($resPt)) {
            $prodTotal[(int) $o->fk_propal] = (int) $o->nb;
        }
    }
    $prodMatch = [];
    $sqlProd   = 'SELECT pd.fk_propal as propal_id, f.rowid as invoice_id, f.ref, f.datef, f.total_ttc, f.fk_statut, COUNT(DISTINCT pd.fk_product) as nbmatch';
    $sqlProd  .= ' FROM ' . MAIN_DB_PREFIX . 'propaldet as pd';
    $sqlProd  .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = pd.fk_propal';
    $sqlProd  .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.fk_soc = pr.fk_soc AND f.entity IN (' . $entFact . ') AND f.type <> 2 AND f.fk_statut <> 3';
    $sqlProd  .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet as fd ON fd.fk_facture = f.rowid AND fd.fk_product = pd.fk_product';
    $sqlProd  .= ' WHERE pd.fk_propal IN (' . $propalIds . ') AND pd.fk_product > 0';
    $sqlProd  .= ' AND f.datef >= DATE_SUB(COALESCE(pr.date_signature, pr.datep), INTERVAL 15 DAY)';
    $sqlProd  .= ' GROUP BY pd.fk_propal, f.rowid, f.ref, f.datef, f.total_ttc, f.fk_statut';
    $resProd   = $db->query($sqlProd);
    if ($resProd) {
        while ($o = $db->fetch_object($resProd)) {
            $prodMatch[(int) $o->propal_id][] = ['id' => (int) $o->invoice_id, 'ref' => $o->ref, 'date' => !empty($o->datef) ? $db->jdate($o->datef) : 0, 'total_ttc' => (float) $o->total_ttc, 'status' => (int) $o->fk_statut, 'nbmatch' => (int) $o->nbmatch];
        }
    }

    // 5. Score every candidate.
    foreach ($rows as $id => $row) {
        $sig       = $row['date_sig'];
        $window    = $sig ? $sig - (30 * 86400) : 0; // 30-day tolerance before the signature
        $socInvs   = $invoices[$row['fk_soc']] ?? [];
        $sameAmt   = [];
        $clientNb  = 0;
        $clientTot = 0.0;
        foreach ($socInvs as $inv) {
            if ($sig && $inv['date'] >= $sig) {
                $clientNb++;
                $clientTot += $inv['total_ttc'];
            }
            if ($inv['date'] < $window) {
                continue;
            }
            if ((abs($inv['total_ttc'] - $row['total_ttc']) < 0.01 && $row['total_ttc'] != 0)
                || (abs($inv['total_ht'] - $row['total_ht']) < 0.01 && $row['total_ht'] != 0)) {
                $sameAmt[] = $inv;
            }
        }
        $rows[$id]['client_nb']    = $clientNb;
        $rows[$id]['client_total'] = $clientTot;

        if ($row['match'] === 'chain') {
            continue; // already the strongest signal
        }
        if (!empty($sameAmt)) {
            $rows[$id]['match']          = 'amount';
            $rows[$id]['match_invoices'] = $sameAmt;
            continue;
        }
        // Same products billed after the signature: only meaningful if most of the quote lines are covered.
        $nbProd = $prodTotal[$id] ?? 0;
        if ($nbProd > 0 && !empty($prodMatch[$id])) {
            $best = 0;
            foreach ($prodMatch[$id] as $pm) {
                $best = max($best, $pm['nbmatch']);
            }
            if ($best / $nbProd >= 0.5) {
                $rows[$id]['match']          = 'products';
                $rows[$id]['match_invoices'] = $prodMatch[$id];
            }
        }
    }

    return $rows;
}

/**
 * SQL expression giving the month a recurring template left the portfolio.
 *
 * A stopped subscription has no dedicated "stop date" in Dolibarr: the last successful generation
 * (date_last_gen) is the real last month billed, so it is the reference. When a template was suspended
 * before ever generating anything, fall back on the last modification date (tms), which is when it was
 * suspended in practice.
 *
 * @return string SQL expression.
 */
function reedcrmFollowupExitDateSql(): string
{
    return 'COALESCE(fr.date_last_gen, fr.tms)';
}

/**
 * Common WHERE conditions selecting the recurring templates that make up the subscription portfolio.
 *
 * @return string SQL conditions (starting with AND).
 */
function reedcrmFollowupPortfolioSql(): string
{
    return ' AND fr.entity IN (' . getEntity('facturerec') . ') AND fr.frequency > 0 AND fr.fk_soc > 0';
}

/**
 * Monthly "entries / exits" of the recurring subscription portfolio over a year.
 *
 * ENTRY  = a recurring template created during the month (fr.datec): a new subscription was signed.
 * EXIT   = a suspended template whose billing stopped during the month (see reedcrmFollowupExitDateSql()):
 *          a lost client / stopped subscription.
 * Amounts are the template TTC amount, i.e. the recurring revenue gained or lost each month.
 *
 * @param  DoliDB $db   Database handler.
 * @param  int    $year Year to browse.
 * @return array<string,mixed> months: 1..12 => [in_nb, in_amount, out_nb, out_amount, net_nb, net_amount],
 *                             totals: same keys summed over the year.
 */
function reedcrmFollowupGetRecurringMovementsByMonth(DoliDB $db, int $year): array
{
    $data = ['months' => [], 'totals' => ['in_nb' => 0, 'in_amount' => 0.0, 'out_nb' => 0, 'out_amount' => 0.0, 'net_nb' => 0, 'net_amount' => 0.0]];
    for ($m = 1; $m <= 12; $m++) {
        $data['months'][$m] = ['in_nb' => 0, 'in_amount' => 0.0, 'out_nb' => 0, 'out_amount' => 0.0, 'net_nb' => 0, 'net_amount' => 0.0];
    }

    // Entries: templates created during the year.
    $sqlIn  = 'SELECT MONTH(fr.datec) as m, COUNT(*) as nb, SUM(fr.total_ttc) as tot';
    $sqlIn .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
    $sqlIn .= ' WHERE YEAR(fr.datec) = ' . $year . reedcrmFollowupPortfolioSql();
    $sqlIn .= ' GROUP BY m';
    $resIn  = $db->query($sqlIn);
    if ($resIn) {
        while ($obj = $db->fetch_object($resIn)) {
            $m = (int) $obj->m;
            if ($m >= 1 && $m <= 12) {
                $data['months'][$m]['in_nb']     = (int) $obj->nb;
                $data['months'][$m]['in_amount'] = (float) $obj->tot;
            }
        }
    }

    // Exits: suspended templates whose billing stopped during the year.
    $exitDate = reedcrmFollowupExitDateSql();
    $sqlOut   = 'SELECT MONTH(' . $exitDate . ') as m, COUNT(*) as nb, SUM(fr.total_ttc) as tot';
    $sqlOut  .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
    $sqlOut  .= ' WHERE fr.suspended = 1 AND YEAR(' . $exitDate . ') = ' . $year . reedcrmFollowupPortfolioSql();
    $sqlOut  .= ' GROUP BY m';
    $resOut   = $db->query($sqlOut);
    if ($resOut) {
        while ($obj = $db->fetch_object($resOut)) {
            $m = (int) $obj->m;
            if ($m >= 1 && $m <= 12) {
                $data['months'][$m]['out_nb']     = (int) $obj->nb;
                $data['months'][$m]['out_amount'] = (float) $obj->tot;
            }
        }
    }

    foreach ($data['months'] as $m => $row) {
        $data['months'][$m]['net_nb']     = $row['in_nb'] - $row['out_nb'];
        $data['months'][$m]['net_amount'] = $row['in_amount'] - $row['out_amount'];
        foreach (['in_nb', 'in_amount', 'out_nb', 'out_amount'] as $k) {
            $data['totals'][$k] += $row[$k];
        }
    }
    $data['totals']['net_nb']     = $data['totals']['in_nb'] - $data['totals']['out_nb'];
    $data['totals']['net_amount'] = $data['totals']['in_amount'] - $data['totals']['out_amount'];

    return $data;
}

/**
 * Detail of the entries / exits of the recurring subscription portfolio for one month.
 *
 * @param  DoliDB $db    Database handler.
 * @param  int    $year  Year of the browsed month.
 * @param  int    $month Month number (1..12).
 * @return array{in:array<int,array<string,mixed>>,out:array<int,array<string,mixed>>} Detail rows:
 *                       frec_id, titre, fk_soc, thirdparty, montant_ttc, date, prestation, suspended, nb_gen_done.
 */
function reedcrmFollowupGetRecurringMovementsForMonth(DoliDB $db, int $year, int $month): array
{
    $movements = ['in' => [], 'out' => []];
    $exitDate  = reedcrmFollowupExitDateSql();

    $select  = 'SELECT fr.rowid as frec_id, fr.titre, fr.total_ttc, fr.suspended, fr.nb_gen_done, fr.fk_soc,';
    $select .= ' s.nom as thirdparty_name, s.status as soc_status';
    $from    = ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
    $from   .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = fr.fk_soc';

    $queries = [
        'in'  => $select . ', fr.datec as mvt_date' . $from . ' WHERE YEAR(fr.datec) = ' . $year . ' AND MONTH(fr.datec) = ' . $month . reedcrmFollowupPortfolioSql(),
        'out' => $select . ', ' . $exitDate . ' as mvt_date' . $from . ' WHERE fr.suspended = 1 AND YEAR(' . $exitDate . ') = ' . $year . ' AND MONTH(' . $exitDate . ') = ' . $month . reedcrmFollowupPortfolioSql(),
    ];

    foreach ($queries as $way => $sql) {
        $resql = $db->query($sql . ' ORDER BY fr.total_ttc DESC');
        if (!$resql) {
            continue;
        }
        while ($obj = $db->fetch_object($resql)) {
            $movements[$way][] = [
                'frec_id'     => (int) $obj->frec_id,
                'titre'       => (string) $obj->titre,
                'fk_soc'      => (int) $obj->fk_soc,
                'thirdparty'  => (string) $obj->thirdparty_name,
                'soc_status'  => (int) $obj->soc_status,
                'montant_ttc' => (float) $obj->total_ttc,
                'date'        => !empty($obj->mvt_date) ? $db->jdate($obj->mvt_date) : 0,
                'prestation'  => reedcrmFollowupGuessPrestation((string) $obj->titre),
                'suspended'   => (int) $obj->suspended,
                'nb_gen_done' => (int) $obj->nb_gen_done,
            ];
        }
    }

    return $movements;
}
