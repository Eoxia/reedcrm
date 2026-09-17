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
    // Date the line is really planned on: the appointment agreed with the client when there is one,
    // the theoretical yearly date otherwise.
    $effectiveDate = 'COALESCE(a.date_rdv, a.next_audit_date)';

    $sql  = 'SELECT a.rowid, a.fk_soc, a.last_audit_date, a.next_audit_date, a.date_rdv, a.date_done, a.note, a.montant, a.status, a.source, a.proposal_sent_date, a.fk_propal, a.fk_facture, a.fk_intervention_date, a.fk_user_assign,';
    $sql .= ' pr.rowid as propal_rowid, pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut, pr.datep as propal_date,';
    $sql .= ' fa.rowid as facture_rowid, fa.ref as facture_ref, fa.total_ttc as facture_ttc, fa.paye as facture_paye, fa.fk_statut as facture_statut, fa.datef as facture_date, idt.rowid as intervention_rowid, idt.date_intervention as intervention_date, idt.fk_user_intervenant as intervention_user,';
    $sql .= ' s.nom as thirdparty_name, s.address, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    // Quote of the cycle: the derived renewal quote (client's latest DU_AU proposal dated after
    // the last audit) and, when the client has none, the quote hand-linked on the line.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = COALESCE((';
    $sql .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
    $sql .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1), a.fk_propal)';
    // Invoice of the cycle: the derived renewal invoice, falling back to the hand-linked one.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = COALESCE((';
    $sql .= '   SELECT f2.rowid FROM ' . MAIN_DB_PREFIX . 'facture f2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd2 ON fd2.fk_facture = f2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prodf ON prodf.rowid = fd2.fk_product AND prodf.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE f2.fk_soc = a.fk_soc AND f2.type <> 2 AND f2.entity IN (' . getEntity('facture') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR f2.datef > a.last_audit_date)';
    $sql .= '   ORDER BY f2.datef DESC, f2.rowid DESC LIMIT 1), (';
    // Nothing found by date? The quote of the cycle may have been billed before the audit took
    // place: follow the link Dolibarr keeps between a proposal and the invoice made from it.
    $sql .= '   SELECT ee.fk_target FROM ' . MAIN_DB_PREFIX . 'element_element ee';
    $sql .= "   WHERE ee.sourcetype = 'propal' AND ee.targettype = 'facture' AND ee.fk_source = pr.rowid";
    $sql .= '   ORDER BY ee.rowid DESC LIMIT 1), a.fk_facture)';
    // Intervention date planned for the appointment, when one has been agreed with the client.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'reedcrm_intervention_date as idt ON idt.rowid = a.fk_intervention_date';
    $sql .= ' WHERE a.entity IN (' . getEntity('reedcrm_du_audit') . ')';
    // Saturne deletes softly: a line put in the bin must not stay on the board.
    $sql .= ' AND a.status >= 0';
    // A line sits in the month of its appointment once one is agreed, otherwise in the month of its
    // theoretical yearly date. Audits carried out during the month stay too (marking one done rolls
    // its next date a year ahead), so the month's work never disappears from the board.
    $sql .= " AND ((" . $effectiveDate . " >= '" . $db->idate($lowerBound) . "' AND " . $effectiveDate . " <= '" . $db->idate($periodEnd) . "')";
    $sql .= " OR (a.date_done >= '" . $db->idate($periodStart) . "' AND a.date_done <= '" . $db->idate($periodEnd) . "'))";
    $sql .= ' ORDER BY ' . $effectiveDate . ' ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $nextAudit = $db->jdate($obj->next_audit_date);
            $effective = !empty($obj->date_rdv) ? $db->jdate($obj->date_rdv) : $nextAudit;
            $location  = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $audits[]  = [
                'id'           => (int) $obj->rowid,
                'fk_soc'       => (int) $obj->fk_soc,
                'thirdparty'   => $obj->thirdparty_name,
                'last_audit'   => !empty($obj->last_audit_date) ? $db->jdate($obj->last_audit_date) : 0,
                'next_audit'   => $nextAudit,
                'date_rdv'     => !empty($obj->date_rdv) ? $db->jdate($obj->date_rdv) : 0,
                'effective'    => $effective,
                'intervention_id'   => (int) $obj->intervention_rowid,
                'intervention_date' => !empty($obj->intervention_date) ? $db->jdate($obj->intervention_date) : 0,
                'intervention_user' => (int) $obj->intervention_user,
                'date_done'    => !empty($obj->date_done) ? $db->jdate($obj->date_done) : 0,
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
                'facture_statut' => $obj->facture_statut !== null ? (int) $obj->facture_statut : null,
                'facture_date' => !empty($obj->facture_date) ? $db->jdate($obj->facture_date) : 0,
                'assigned'     => (int) $obj->fk_user_assign,
                'overdue'      => $effective < $periodStart,
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
    $now           = dol_now();
    $audits        = [];
    $effectiveDate = 'COALESCE(a.date_rdv, a.next_audit_date)';

    $sql  = 'SELECT a.rowid, a.fk_soc, a.last_audit_date, a.next_audit_date, a.date_rdv, a.date_done, a.note, a.montant, a.status, a.source, a.proposal_sent_date, a.fk_propal, a.fk_facture, a.fk_intervention_date, a.fk_user_assign,';
    $sql .= ' pr.rowid as propal_rowid, pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut, pr.datep as propal_date,';
    $sql .= ' fa.rowid as facture_rowid, fa.ref as facture_ref, fa.total_ttc as facture_ttc, fa.paye as facture_paye, fa.fk_statut as facture_statut, fa.datef as facture_date, idt.rowid as intervention_rowid, idt.date_intervention as intervention_date, idt.fk_user_intervenant as intervention_user,';
    $sql .= ' s.nom as thirdparty_name, s.address, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    // Quote of the cycle: the derived renewal quote, falling back to the hand-linked one.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = COALESCE((';
    $sql .= '   SELECT p2.rowid FROM ' . MAIN_DB_PREFIX . 'propal p2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.fk_propal = p2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE p2.fk_soc = a.fk_soc AND p2.entity IN (' . getEntity('propal') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR p2.datep > a.last_audit_date)';
    $sql .= '   ORDER BY p2.datep DESC, p2.rowid DESC LIMIT 1), a.fk_propal)';
    // Invoice of the cycle: the derived renewal invoice, falling back to the hand-linked one.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = COALESCE((';
    $sql .= '   SELECT f2.rowid FROM ' . MAIN_DB_PREFIX . 'facture f2';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet fd2 ON fd2.fk_facture = f2.rowid';
    $sql .= '   INNER JOIN ' . MAIN_DB_PREFIX . "product prodf ON prodf.rowid = fd2.fk_product AND prodf.ref LIKE 'DU\_A%'";
    $sql .= '   WHERE f2.fk_soc = a.fk_soc AND f2.type <> 2 AND f2.entity IN (' . getEntity('facture') . ')';
    $sql .= '   AND (a.last_audit_date IS NULL OR f2.datef > a.last_audit_date)';
    $sql .= '   ORDER BY f2.datef DESC, f2.rowid DESC LIMIT 1), (';
    // Nothing found by date? The quote of the cycle may have been billed before the audit took
    // place: follow the link Dolibarr keeps between a proposal and the invoice made from it.
    $sql .= '   SELECT ee.fk_target FROM ' . MAIN_DB_PREFIX . 'element_element ee';
    $sql .= "   WHERE ee.sourcetype = 'propal' AND ee.targettype = 'facture' AND ee.fk_source = pr.rowid";
    $sql .= '   ORDER BY ee.rowid DESC LIMIT 1), a.fk_facture)';
    // Intervention date planned for the appointment, when one has been agreed with the client.
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'reedcrm_intervention_date as idt ON idt.rowid = a.fk_intervention_date';
    $sql .= ' WHERE a.entity IN (' . getEntity('reedcrm_du_audit') . ')';
    $sql .= ' AND a.status >= 0';
    $sql .= ' AND a.status <> 2'; // 2 = done
    // Late against the date that really counts: an audit whose appointment is booked ahead is not late.
    $sql .= ' AND ' . $effectiveDate . " < '" . $db->idate($now) . "'";
    // Keep clients that are still active thirdparties (skip only closed/churned ones).
    $sql .= ' AND s.status = 1';
    // Ignore very old audits (next audit due before 2016).
    $sql .= ' AND ' . $effectiveDate . " >= '2016-01-01'";
    $sql .= ' ORDER BY ' . $effectiveDate . ' ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $nextAudit = $db->jdate($obj->next_audit_date);
            $effective = !empty($obj->date_rdv) ? $db->jdate($obj->date_rdv) : $nextAudit;
            $location  = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $audits[]  = [
                'id'         => (int) $obj->rowid,
                'fk_soc'     => (int) $obj->fk_soc,
                'thirdparty' => $obj->thirdparty_name,
                'last_audit' => !empty($obj->last_audit_date) ? $db->jdate($obj->last_audit_date) : 0,
                'next_audit' => $nextAudit,
                'date_rdv'   => !empty($obj->date_rdv) ? $db->jdate($obj->date_rdv) : 0,
                'effective'  => $effective,
                'intervention_id'   => (int) $obj->intervention_rowid,
                'intervention_date' => !empty($obj->intervention_date) ? $db->jdate($obj->intervention_date) : 0,
                'intervention_user' => (int) $obj->intervention_user,
                'date_done'  => !empty($obj->date_done) ? $db->jdate($obj->date_done) : 0,
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
                'facture_statut' => $obj->facture_statut !== null ? (int) $obj->facture_statut : null,
                'facture_date' => !empty($obj->facture_date) ? $db->jdate($obj->facture_date) : 0,
                'assigned'   => (int) $obj->fk_user_assign,
                'overdue'    => true,
                'location'   => $location,
                'address'    => trim(($obj->address ?? '') . ' ' . $location),
                'days_late'  => (int) floor(($now - $effective) / 86400),
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
    $sql .= reedcrmFollowupMonthJoinsSql($db, $periodStart, $periodEnd);
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
 * Joins bringing, for one month, the stored annotation and the invoice really generated from each
 * recurring template. Both expose the aliases the callers select from: t (annotation) and fa (invoice).
 *
 * They used to be correlated subqueries evaluated once per template row. llx_facture carries no index
 * on fk_fac_rec_source, and wrapping datef in MONTH()/YEAR() also ruled out the datef index, so the
 * invoice table was fully scanned once per template: seconds of query time on a base with a few hundred
 * templates, and the page runs this shape three times (list, count, dashboard). They are pre-aggregated
 * once here instead, with the month expressed as a date range so the datef index applies.
 *
 * @param  DoliDB $db          Database handler.
 * @param  int    $periodStart First-day-of-month timestamp.
 * @param  int    $periodEnd   Last-day-of-month timestamp.
 * @return string              SQL LEFT JOIN clauses, to append after the facture_rec table aliased fr.
 */
function reedcrmFollowupMonthJoinsSql(DoliDB $db, int $periodStart, int $periodEnd): string
{
    // At most ONE annotation per template (old data may hold several rows per template) — avoids row
    // duplication. MAX(rowid) is the latest one, what the previous per-row subquery already returned.
    $sql  = ' LEFT JOIN (SELECT MAX(t9.rowid) as tid, t9.fk_facture_rec FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t9';
    $sql .= '  WHERE t9.entity IN (' . getEntity('reedcrm_facturerec_followup') . ') GROUP BY t9.fk_facture_rec) as tlast ON tlast.fk_facture_rec = fr.rowid';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t ON t.rowid = tlast.tid';
    // The invoice actually generated from this template within the browsed month (if any) — tells when
    // it was really billed on past and current months. A template bills once a month, so at most one row.
    $sql .= ' LEFT JOIN (SELECT MAX(f9.rowid) as fid, f9.fk_fac_rec_source FROM ' . MAIN_DB_PREFIX . 'facture as f9';
    $sql .= '  WHERE f9.type <> 2 AND f9.fk_fac_rec_source > 0 AND f9.entity IN (' . getEntity('facture') . ')';
    $sql .= "  AND f9.datef >= '" . $db->idate($periodStart) . "' AND f9.datef <= '" . $db->idate($periodEnd) . "'";
    $sql .= ' GROUP BY f9.fk_fac_rec_source) as falast ON falast.fk_fac_rec_source = fr.rowid';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = falast.fid';

    return $sql;
}

/**
 * Billing progress of a whole year, month by month: what has really been invoiced, and what is still
 * expected. Follows the very same rule as the monthly list, so the chart always agrees with the tiles.
 *
 * DONE = invoices really generated from a recurring template and dated in the month.
 * TODO = active templates whose next generation falls in that month of the year with no invoice
 *        generated for them that month. On a past month that is late billing; on a month to come it is
 *        simply what remains to be done.
 *
 * @param  DoliDB $db   Database handler.
 * @param  int    $year Year to browse.
 * @return array<int,array<string,mixed>> 1..12 => done_nb, done_amount, todo_nb, todo_amount.
 */
function reedcrmFollowupGetYearBillingProgress(DoliDB $db, int $year): array
{
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = ['done_nb' => 0, 'done_amount' => 0.0, 'todo_nb' => 0, 'todo_amount' => 0.0];
    }
    // Date range rather than YEAR(datef), so the datef index can be used.
    $yearStart = "'" . $db->idate(dol_get_first_day($year, 1)) . "'";
    $yearEnd   = "'" . $db->idate(dol_get_last_day($year, 12)) . "'";

    // Really billed: the invoices generated from a template, per month.
    $sqlDone  = 'SELECT MONTH(f.datef) as m, COUNT(*) as nb, SUM(f.total_ttc) as tot';
    $sqlDone .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
    $sqlDone .= ' WHERE f.type <> 2 AND f.fk_fac_rec_source > 0 AND f.entity IN (' . getEntity('facture') . ')';
    $sqlDone .= ' AND f.datef >= ' . $yearStart . ' AND f.datef <= ' . $yearEnd . ' GROUP BY m';
    $resDone  = $db->query($sqlDone);
    if ($resDone) {
        while ($obj = $db->fetch_object($resDone)) {
            $m = (int) $obj->m;
            if ($m >= 1 && $m <= 12) {
                $months[$m]['done_nb']     = (int) $obj->nb;
                $months[$m]['done_amount'] = (float) $obj->tot;
            }
        }
    }

    // Still expected: templates due that month with no invoice generated for them that month.
    $sqlTodo  = 'SELECT MONTH(fr.date_when) as m, COUNT(*) as nb, SUM(fr.total_ttc) as tot';
    $sqlTodo .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
    $sqlTodo .= ' LEFT JOIN (SELECT MAX(f9.rowid) as fid, f9.fk_fac_rec_source, MONTH(f9.datef) as fm';
    $sqlTodo .= '  FROM ' . MAIN_DB_PREFIX . 'facture as f9';
    $sqlTodo .= '  WHERE f9.type <> 2 AND f9.fk_fac_rec_source > 0 AND f9.entity IN (' . getEntity('facture') . ')';
    $sqlTodo .= '  AND f9.datef >= ' . $yearStart . ' AND f9.datef <= ' . $yearEnd;
    $sqlTodo .= '  GROUP BY f9.fk_fac_rec_source, MONTH(f9.datef)) as fl';
    $sqlTodo .= '  ON fl.fk_fac_rec_source = fr.rowid AND fl.fm = MONTH(fr.date_when)';
    $sqlTodo .= ' WHERE fr.suspended = 0 AND YEAR(fr.date_when) = ' . $year . ' AND fl.fid IS NULL';
    $sqlTodo .= reedcrmFollowupPortfolioSql();
    $sqlTodo .= ' GROUP BY m';
    $resTodo  = $db->query($sqlTodo);
    if ($resTodo) {
        while ($obj = $db->fetch_object($resTodo)) {
            $m = (int) $obj->m;
            if ($m >= 1 && $m <= 12) {
                $months[$m]['todo_nb']     = (int) $obj->nb;
                $months[$m]['todo_amount'] = (float) $obj->tot;
            }
        }
    }

    return $months;
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

/**
 * List the documents a DU audit line can be linked to for a given client: its commercial proposals
 * and its customer invoices, most recent first. Used when adding an audit by hand so the line stays
 * attached to something real instead of being free text.
 *
 * @param  DoliDB $db    Database handler.
 * @param  int    $socid Thirdparty ID.
 * @param  int    $limit Max documents per type.
 * @return array{propals:array<int,array<string,mixed>>,factures:array<int,array<string,mixed>>} Documents.
 */
function reedcrmFollowupGetLinkableDocs(DoliDB $db, int $socid, int $limit = 30): array
{
    $docs = ['propals' => [], 'factures' => []];
    if ($socid <= 0) {
        return $docs;
    }

    $queries = [
        'propals'  => 'SELECT p.rowid, p.ref, p.datep as doc_date, p.total_ttc, p.fk_statut as statut FROM ' . MAIN_DB_PREFIX . 'propal as p'
            . ' WHERE p.fk_soc = ' . $socid . ' AND p.entity IN (' . getEntity('propal') . ')'
            . ' ORDER BY p.datep DESC, p.rowid DESC' . $db->plimit($limit),
        'factures' => 'SELECT f.rowid, f.ref, f.datef as doc_date, f.total_ttc, f.fk_statut as statut FROM ' . MAIN_DB_PREFIX . 'facture as f'
            . ' WHERE f.fk_soc = ' . $socid . ' AND f.type <> 2 AND f.entity IN (' . getEntity('facture') . ')'
            . ' ORDER BY f.datef DESC, f.rowid DESC' . $db->plimit($limit),
    ];

    foreach ($queries as $key => $sql) {
        $resql = $db->query($sql);
        if (!$resql) {
            continue;
        }
        while ($obj = $db->fetch_object($resql)) {
            $docs[$key][] = [
                'id'        => (int) $obj->rowid,
                'ref'       => (string) $obj->ref,
                'date'      => !empty($obj->doc_date) ? $db->jdate($obj->doc_date) : 0,
                'total_ttc' => $obj->total_ttc !== null ? (float) $obj->total_ttc : null,
                'statut'    => (int) $obj->statut,
            ];
        }
    }

    return $docs;
}

/**
 * Load one document a DU audit can be linked to, making sure it really belongs to the given client.
 *
 * @param  DoliDB $db    Database handler.
 * @param  string $type  'propal' or 'facture'.
 * @param  int    $docId Document ID (0 = none).
 * @param  int    $socid Thirdparty the document must belong to.
 * @return array<string,mixed>|null Document (id, ref, date, total_ttc) or null when absent/mismatched.
 */
function reedcrmFollowupFetchLinkableDoc(DoliDB $db, string $type, int $docId, int $socid): ?array
{
    if ($docId <= 0 || $socid <= 0 || !in_array($type, ['propal', 'facture'], true)) {
        return null;
    }

    if ($type === 'propal') {
        $sql = 'SELECT rowid, ref, datep as doc_date, total_ttc FROM ' . MAIN_DB_PREFIX . 'propal';
        $sql .= ' WHERE rowid = ' . $docId . ' AND fk_soc = ' . $socid . ' AND entity IN (' . getEntity('propal') . ')';
    } else {
        $sql = 'SELECT rowid, ref, datef as doc_date, total_ttc FROM ' . MAIN_DB_PREFIX . 'facture';
        $sql .= ' WHERE rowid = ' . $docId . ' AND fk_soc = ' . $socid . ' AND entity IN (' . getEntity('facture') . ')';
    }

    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        return [
            'id'         => (int) $obj->rowid,
            'ref'        => (string) $obj->ref,
            'date'       => !empty($obj->doc_date) ? $db->jdate($obj->doc_date) : 0,
            'total_ttc'  => $obj->total_ttc !== null ? (float) $obj->total_ttc : null,
            // What the document sells, to fill the object of a follow-up line without typing it.
            'line_label' => reedcrmFollowupFirstLineLabel($db, $type, $docId),
        ];
    }

    return null;
}

/**
 * Label of the first line of a quote or an invoice: its description, the product label otherwise.
 *
 * @param  DoliDB $db    Database handler.
 * @param  string $type  'propal' or 'facture'.
 * @param  int    $docId Document ID.
 * @return string        Label, empty string when the document has no line.
 */
function reedcrmFollowupFirstLineLabel(DoliDB $db, string $type, int $docId): string
{
    $lineTable = $type === 'propal' ? 'propaldet' : 'facturedet';
    $parentKey = $type === 'propal' ? 'fk_propal' : 'fk_facture';

    $sql  = 'SELECT d.label as line_label, d.description, p.label as product_label FROM ' . MAIN_DB_PREFIX . $lineTable . ' as d';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = d.fk_product';
    $sql .= ' WHERE d.' . $parentKey . ' = ' . $docId . ' AND d.product_type <> 9';
    $sql .= ' ORDER BY d.rang ASC, d.rowid ASC' . $db->plimit(1);

    $resql = $db->query($sql);
    if (!$resql || !($obj = $db->fetch_object($resql))) {
        return '';
    }

    // A line description is a whole paragraph on these documents: the short labels come first, and
    // the description is only a last resort, cut short.
    foreach ([$obj->line_label, $obj->product_label, $obj->description] as $candidate) {
        $label = trim(html_entity_decode(dol_string_nohtmltag((string) $candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($label !== '') {
            return dol_trunc($label, 80, 'right', 'UTF-8', 1);
        }
    }

    return '';
}

/**
 * Find the DU service line a client's audit appointment can be planned on: the DU_AU line of the
 * renewal quote (the latest one dated after the last audit), falling back to the quote hand-linked
 * on the audit line.
 *
 * @param  DoliDB  $db    Database handler.
 * @param  DuAudit $audit Audit to look a quote up for.
 * @return array{propal_id:int,line_id:int}|null Quote and line ids, null when the client has no DU quote.
 */
function reedcrmFollowupFindDuProposalLine(DoliDB $db, DuAudit $audit): ?array
{
    $socid = (int) $audit->fk_soc;
    if ($socid <= 0) {
        return null;
    }
    $lastAudit = !empty($audit->last_audit_date)
        ? (is_numeric($audit->last_audit_date) ? (int) $audit->last_audit_date : (int) dol_stringtotime($audit->last_audit_date))
        : 0;

    $base  = 'SELECT p.rowid as propal_id, pd.rowid as line_id FROM ' . MAIN_DB_PREFIX . 'propal as p';
    $base .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet as pd ON pd.fk_propal = p.rowid';
    $base .= ' INNER JOIN ' . MAIN_DB_PREFIX . "product as prod ON prod.rowid = pd.fk_product AND prod.ref LIKE 'DU\_A%'";

    // The renewal quote first, then any DU quote hand-linked on the audit.
    $candidates = [
        $base . ' WHERE p.fk_soc = ' . $socid . ' AND p.entity IN (' . getEntity('propal') . ')'
            . ($lastAudit > 0 ? " AND p.datep > '" . $db->idate($lastAudit) . "'" : '')
            . ' ORDER BY p.datep DESC, p.rowid DESC, pd.rowid ASC' . $db->plimit(1),
    ];
    if (!empty($audit->fk_propal)) {
        $candidates[] = $base . ' WHERE p.rowid = ' . ((int) $audit->fk_propal)
            . ' ORDER BY pd.rowid ASC' . $db->plimit(1);
    }

    foreach ($candidates as $sql) {
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            return ['propal_id' => (int) $obj->propal_id, 'line_id' => (int) $obj->line_id];
        }
    }

    return null;
}

/**
 * Plan — or move — the intervention date backing a DU audit appointment, on the DU line of the
 * client's quote, so it shows up in the ReedCRM intervention calendar (and in the agenda).
 *
 * Only called when a real date has been agreed with the client: the theoretical yearly date never
 * plans anything, so the calendar only holds appointments that really exist.
 *
 * @param  DoliDB  $db      Database handler.
 * @param  User    $user    User doing the action.
 * @param  DuAudit $audit   Audit whose appointment is being set.
 * @param  int     $rdvDate Appointment timestamp.
 * @return int              Intervention date ID, 0 when the feature is off or forbidden,
 *                          -2 when the client has no DU quote line to plan on, -1 on error.
 */
function reedcrmFollowupSyncAuditIntervention(DoliDB $db, User $user, DuAudit $audit, int $rdvDate): int
{
    global $conf, $langs;

    require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
    require_once __DIR__ . '/../class/interventiondate.class.php';
    require_once __DIR__ . '/reedcrm_interventiondate.lib.php';

    if (!reedcrmInterventionIsEnabled() || !$user->hasRight('reedcrm', 'followup', 'write')) {
        return 0;
    }

    $target = reedcrmFollowupFindDuProposalLine($db, $audit);
    if ($target === null) {
        return -2;
    }

    $propal = new Propal($db);
    if ($propal->fetch($target['propal_id']) <= 0) {
        return -1;
    }

    // Reuse the line's first slot: planning from here or from the calendar writes the same row.
    $interventionDate = new InterventionDate($db);
    $existingDates    = $interventionDate->fetchAllByLine('propal', $target['line_id']);
    $record           = $existingDates[1] ?? new InterventionDate($db);

    $thirdparty = new Societe($db);
    $thirdparty->fetch((int) $audit->fk_soc);

    $record->entity              = $conf->entity;
    $record->element_type        = 'propal';
    $record->element_id          = (int) $propal->id;
    $record->fk_element_line     = $target['line_id'];
    $record->position            = 1;
    $record->date_intervention   = $rdvDate;
    // A DU audit is a day on site, not the generic one-hour slot.
    $record->duration            = getDolGlobalInt('REEDCRM_DU_AUDIT_RDV_DURATION', 7 * 60);
    $record->fk_user_intervenant = (int) $audit->fk_user_assign;
    $record->location            = dol_trunc(trim(($thirdparty->zip ? $thirdparty->zip . ' ' : '') . (string) $thirdparty->town), 255, 'right', 'UTF-8', 1);
    $record->status              = InterventionDate::STATUS_PLANNED;

    $label = $langs->transnoentities('FollowupInterventionLabel');
    if ($label === 'FollowupInterventionLabel') {
        $label = 'Audit du Document Unique'; // the module only ships fr_FR
    }
    if (empty($record->note)) {
        $record->note = $label;
    }

    // The event mirrors the date: written before the row so the row keeps its event id.
    if ($record->syncEvent($user, $propal, $label) < 0) {
        return -1;
    }
    $result = empty($record->id) ? $record->create($user) : $record->update($user);

    return $result > 0 ? (int) $record->id : -1;
}

/**
 * Remove the intervention date (and its agenda event) planned for a DU audit appointment.
 *
 * @param  DoliDB $db     Database handler.
 * @param  User   $user   User doing the action.
 * @param  int    $dateId Intervention date ID.
 * @return int            > 0 if OK, 0 if nothing to do, < 0 on error.
 */
function reedcrmFollowupDeleteAuditIntervention(DoliDB $db, User $user, int $dateId): int
{
    require_once __DIR__ . '/../class/interventiondate.class.php';

    if ($dateId <= 0) {
        return 0;
    }
    $record = new InterventionDate($db);
    if ($record->fetch($dateId) <= 0) {
        return 0;
    }

    return $record->delete($user);
}

/**
 * Mark the intervention date booked for a DU audit as carried out, so the calendar shows the slot
 * as done instead of still planned.
 *
 * @param  DoliDB $db     Database handler.
 * @param  User   $user   User doing the action.
 * @param  int    $dateId Intervention date ID.
 * @return int            > 0 if OK, 0 if nothing to do, < 0 on error.
 */
function reedcrmFollowupMarkAuditInterventionDone(DoliDB $db, User $user, int $dateId): int
{
    require_once __DIR__ . '/../class/interventiondate.class.php';

    if ($dateId <= 0) {
        return 0;
    }
    $record = new InterventionDate($db);
    if ($record->fetch($dateId) <= 0 || (int) $record->status === InterventionDate::STATUS_DONE) {
        return 0;
    }
    $record->status = InterventionDate::STATUS_DONE;

    return $record->update($user);
}

/**
 * List the hand-added client engagements (support, training, sprint…) of a month: those planned in
 * it, plus those carried out during it, same rule as the DU audits board.
 *
 * @param  DoliDB $db          Database handler.
 * @param  int    $periodStart First-day-of-month timestamp.
 * @param  int    $periodEnd   Last-day-of-month timestamp.
 * @return array<int,array<string,mixed>> Rows ready to render.
 */
function reedcrmTrackingGetForMonth(DoliDB $db, int $periodStart, int $periodEnd): array
{
    $rows          = [];
    $effectiveDate = 'COALESCE(t.date_rdv, t.date_planned)';

    $sql  = 'SELECT t.rowid, t.type, t.fk_soc, t.date_planned, t.date_rdv, t.date_done, t.label, t.montant, t.status,';
    $sql .= ' t.fk_user_assign, t.fk_propal, t.fk_facture, t.fk_intervention_date,';
    $sql .= ' pr.ref as propal_ref, pr.total_ttc as propal_ttc, pr.fk_statut as propal_statut,';
    $sql .= ' fa.ref as facture_ref, fa.total_ttc as facture_ttc, fa.paye as facture_paye, fa.fk_statut as facture_statut,';
    $sql .= ' idt.date_intervention, s.nom as thirdparty_name, s.zip, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_client_tracking as t';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = t.fk_propal';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as fa ON fa.rowid = t.fk_facture';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'reedcrm_intervention_date as idt ON idt.rowid = t.fk_intervention_date';
    $sql .= ' WHERE t.entity IN (' . getEntity('reedcrm_client_tracking') . ')';
    $sql .= ' AND t.status >= 0';
    $sql .= ' AND ((' . $effectiveDate . " >= '" . $db->idate($periodStart) . "' AND " . $effectiveDate . " <= '" . $db->idate($periodEnd) . "')";
    $sql .= " OR (t.date_done >= '" . $db->idate($periodStart) . "' AND t.date_done <= '" . $db->idate($periodEnd) . "'))";
    $sql .= ' ORDER BY ' . $effectiveDate . ' ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $planned  = $db->jdate($obj->date_planned);
            $rdv      = !empty($obj->date_rdv) ? $db->jdate($obj->date_rdv) : 0;
            $location = trim(($obj->zip ? $obj->zip . ' ' : '') . ($obj->town ?? ''));
            $rows[]   = [
                'id'                => (int) $obj->rowid,
                'type'              => (string) $obj->type,
                'fk_soc'            => (int) $obj->fk_soc,
                'thirdparty'        => $obj->thirdparty_name,
                'location'          => $location,
                'planned'           => $planned,
                'date_rdv'          => $rdv,
                'effective'         => $rdv ?: $planned,
                'date_done'         => !empty($obj->date_done) ? $db->jdate($obj->date_done) : 0,
                'label'             => (string) $obj->label,
                'montant'           => $obj->montant !== null ? (float) $obj->montant : null,
                'status'            => (int) $obj->status,
                'assigned'          => (int) $obj->fk_user_assign,
                'propal_id'         => (int) $obj->fk_propal,
                'propal_ref'        => $obj->propal_ref,
                'propal_ttc'        => $obj->propal_ttc !== null ? (float) $obj->propal_ttc : null,
                'propal_statut'     => $obj->propal_statut !== null ? (int) $obj->propal_statut : null,
                'facture_id'        => (int) $obj->fk_facture,
                'facture_ref'       => $obj->facture_ref,
                'facture_paye'      => (int) $obj->facture_paye,
                'facture_statut'    => $obj->facture_statut !== null ? (int) $obj->facture_statut : null,
                'intervention_id'   => (int) $obj->fk_intervention_date,
                'intervention_date' => !empty($obj->date_intervention) ? $db->jdate($obj->date_intervention) : 0,
            ];
        }
    }

    return $rows;
}

/**
 * Plan — or move — the intervention date of a hand-added engagement, on the first line of the quote
 * it is linked to, so it shows up in the intervention calendar like the DU audits do.
 *
 * @param  DoliDB         $db      Database handler.
 * @param  User           $user    User doing the action.
 * @param  ClientTracking $row     Engagement being planned.
 * @param  int            $rdvDate Appointment timestamp.
 * @return int                     Intervention date ID, 0 when the feature is off or forbidden,
 *                                 -2 when no quote is linked, -1 on error.
 */
function reedcrmTrackingSyncIntervention(DoliDB $db, User $user, ClientTracking $row, int $rdvDate): int
{
    global $conf, $langs;

    require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
    require_once __DIR__ . '/../class/interventiondate.class.php';
    require_once __DIR__ . '/reedcrm_interventiondate.lib.php';

    if (!reedcrmInterventionIsEnabled() || !$user->hasRight('reedcrm', 'followup', 'write')) {
        return 0;
    }
    // The calendar hangs off quote lines: without a linked quote there is nothing to plan on.
    if (empty($row->fk_propal)) {
        return -2;
    }

    $propal = new Propal($db);
    if ($propal->fetch((int) $row->fk_propal) <= 0) {
        return -1;
    }
    $propal->fetch_lines();
    if (empty($propal->lines)) {
        return -2;
    }
    $lineId = (int) $propal->lines[0]->id;

    $interventionDate = new InterventionDate($db);
    $existingDates    = $interventionDate->fetchAllByLine('propal', $lineId);
    $record           = $existingDates[1] ?? new InterventionDate($db);

    $thirdparty = new Societe($db);
    $thirdparty->fetch((int) $row->fk_soc);

    $label = trim((string) $row->label) !== '' ? (string) $row->label : ClientTracking::typeLabel((string) $row->type);

    $record->entity              = $conf->entity;
    $record->element_type        = 'propal';
    $record->element_id          = (int) $propal->id;
    $record->fk_element_line     = $lineId;
    $record->position            = 1;
    $record->date_intervention   = $rdvDate;
    $record->duration            = reedcrmInterventionDefaultDuration();
    $record->fk_user_intervenant = (int) $row->fk_user_assign;
    $record->location            = dol_trunc(trim(($thirdparty->zip ? $thirdparty->zip . ' ' : '') . (string) $thirdparty->town), 255, 'right', 'UTF-8', 1);
    $record->status              = InterventionDate::STATUS_PLANNED;
    if (empty($record->note)) {
        $record->note = $label;
    }

    if ($record->syncEvent($user, $propal, $label) < 0) {
        return -1;
    }
    $result = empty($record->id) ? $record->create($user) : $record->update($user);

    return $result > 0 ? (int) $record->id : -1;
}

/**
 * Read the document picked in the single quote/invoice dropdown of an add line, whose value carries
 * the kind it points at ("propal:12", "facture:34").
 *
 * @param  DoliDB $db     Database handler.
 * @param  string $picked Posted value.
 * @param  int    $socid  Thirdparty the document must belong to.
 * @return array{0:?array<string,mixed>,1:?array<string,mixed>} Quote and invoice, at most one set.
 */
function reedcrmFollowupPickedDoc(DoliDB $db, string $picked, int $socid): array
{
    if (!preg_match('/^(propal|facture):(\d+)$/', $picked, $parts)) {
        return [null, null];
    }
    $doc = reedcrmFollowupFetchLinkableDoc($db, $parts[1], (int) $parts[2], $socid);

    return $parts[1] === 'propal' ? [$doc, null] : [null, $doc];
}
