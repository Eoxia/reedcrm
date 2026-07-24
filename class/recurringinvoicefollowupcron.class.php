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
 * \file    class/recurringinvoicefollowupcron.class.php
 * \ingroup reedcrm
 * \brief   Cron jobs for the recurring invoice follow-up: monthly generation, invoice sync and reminders.
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once __DIR__ . '/recurringinvoicefollowup.class.php';
require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

/**
 * Class holding the recurring invoice follow-up scheduled jobs.
 */
class RecurringInvoiceFollowupCron
{
    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * @var string Output produced by the last job run (read by the cron manager).
     */
    public string $output = '';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Job: ensure exactly ONE follow-up annotation per active recurring invoice. The board is now read
     * live from the invoice templates, so the annotation only carries metadata (prestation, SAV time)
     * and the Document Unique cycle — it must never be duplicated. Idempotent: one row per template.
     *
     * @return int 0 if OK, < 0 if KO.
     */
    public function generateMonthlyFollowups(): int
    {
        global $langs, $user;

        $langs->loadLangs(['reedcrm@reedcrm']);

        $created = 0;
        $skipped = 0;

        $sql  = 'SELECT fr.rowid, fr.titre, fr.fk_soc, fr.total_ttc, fr.date_when';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec as fr';
        $sql .= ' WHERE fr.entity IN (' . getEntity('facturerec') . ')';
        $sql .= ' AND fr.suspended = 0 AND fr.frequency > 0 AND fr.fk_soc > 0';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->output = $this->db->lasterror();
            return -1;
        }

        while ($fr = $this->db->fetch_object($resql)) {
            // One annotation per template, regardless of period: never create a second row for the
            // same recurring invoice (the list reads the templates live, the annotation is metadata).
            $sqlCheck  = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup';
            $sqlCheck .= ' WHERE fk_facture_rec = ' . ((int) $fr->rowid);
            $sqlCheck .= ' AND entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
            $resqlCheck = $this->db->query($sqlCheck);
            if ($resqlCheck && $this->db->num_rows($resqlCheck) > 0) {
                $skipped++;
                continue;
            }

            // Anchor the annotation on the template's next due date (fallback: now).
            $when = !empty($fr->date_when) ? $this->db->jdate($fr->date_when) : dol_now();

            $followup                 = new RecurringInvoiceFollowup($this->db);
            $followup->fk_soc         = (int) $fr->fk_soc;
            $followup->fk_facture_rec = (int) $fr->rowid;
            $followup->period         = $when;
            $followup->prestation     = reedcrmFollowupGuessPrestation((string) $fr->titre);
            $followup->montant_ttc    = (float) $fr->total_ttc;
            $followup->temps_sav      = reedcrmFollowupSavSecondsForPrestation($followup->prestation);
            $followup->status         = $followup::STATUS_ACTIVE;

            if ($followup->create($user) > 0) {
                $created++;
            }
        }

        $this->output = $langs->transnoentities('FollowupCronGenerated', $created, $skipped);

        return 0;
    }

    /**
     * Job: pull the real billing reality from Dolibarr invoices for every active follow-up linked to
     * a recurring invoice. Fills the last billing date, the current-cycle created/sent/paid flags,
     * and anchors the yearly Document Unique cycle on the real last billing date when not set manually.
     *
     * @return int 0 if OK, < 0 if KO.
     */
    public function syncInvoiceStatus(): int
    {
        global $langs, $user;

        $langs->loadLangs(['reedcrm@reedcrm']);

        $updated = 0;

        $sql  = 'SELECT t.rowid, t.fk_facture_rec, t.period, t.date_maj_du FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
        $sql .= ' WHERE t.status = 1 AND t.fk_facture_rec > 0';
        $sql .= ' AND t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->output = $this->db->lasterror();
            return -1;
        }

        while ($row = $this->db->fetch_object($resql)) {
            $periodStart = $this->db->jdate($row->period);

            // Most recent real invoice generated from this recurring template (any date).
            $sqlInv  = 'SELECT f.rowid, f.datef, f.total_ttc, f.paye, f.fk_statut FROM ' . MAIN_DB_PREFIX . 'facture as f';
            $sqlInv .= ' WHERE f.fk_fac_rec_source = ' . ((int) $row->fk_facture_rec);
            $sqlInv .= ' ORDER BY f.datef DESC';
            $sqlInv .= $this->db->plimit(1);

            $resqlInv = $this->db->query($sqlInv);
            if (!$resqlInv || !($inv = $this->db->fetch_object($resqlInv))) {
                continue;
            }

            $followup = new RecurringInvoiceFollowup($this->db);
            if ($followup->fetch((int) $row->rowid) <= 0) {
                continue;
            }

            $lastBillingDate = $this->db->jdate($inv->datef);

            // Last real billing date, for context.
            $followup->date_derniere_facture = $lastBillingDate;

            // Current-cycle billing status: is the last invoice within the tracked month/cycle or later ?
            if ($lastBillingDate >= $periodStart) {
                $paid = ($inv->paye == 1 || $inv->fk_statut == 2) ? 1 : 0;

                $followup->facture_creee   = 1;
                $followup->facture_envoyee = ($inv->fk_statut >= 1) ? 1 : 0;
                $followup->facture_payee   = $paid;
                if ($paid) {
                    $followup->paiement_ok = 1;
                }
                $followup->montant_ttc = (float) $inv->total_ttc;
            }

            // Anchor the DU yearly cycle on the real last billing date when not set manually.
            if (empty($followup->date_maj_du)) {
                $followup->date_maj_du = $lastBillingDate;
            }

            if ($followup->update($user) > 0) {
                $updated++;
            }
        }

        $this->output = $langs->transnoentities('FollowupCronSynced', $updated);

        return 0;
    }

    /**
     * Job: create agenda reminders for the yearly Document Unique renewals and for pending payment reminders.
     *
     * @return int 0 if OK, < 0 if KO.
     */
    public function createReminders(): int
    {
        global $langs;

        $langs->loadLangs(['reedcrm@reedcrm', 'agenda']);

        $now     = dol_now();
        $offset  = (int) getDolGlobalInt('REEDCRM_DU_ALERT_OFFSET_MONTHS', 1);
        $created = 0;

        // Document Unique renewals whose anniversary falls within the alert window.
        $windowEnd = dol_time_plus_duree($now, $offset, 'm');

        // Renewals whose anniversary is within the alert window, including any already overdue.
        $sqlDu  = 'SELECT t.rowid, t.ref, t.fk_soc, t.next_maj_du FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
        $sqlDu .= ' WHERE t.status = 1 AND t.next_maj_du IS NOT NULL';
        $sqlDu .= ' AND t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
        $sqlDu .= " AND t.next_maj_du <= '" . $this->db->idate($windowEnd) . "'";

        $resqlDu = $this->db->query($sqlDu);
        if ($resqlDu) {
            while ($row = $this->db->fetch_object($resqlDu)) {
                $nextDu    = $this->db->jdate($row->next_maj_du);
                $alertDate = dol_time_plus_duree($nextDu, -$offset, 'm');
                if ($this->reminderExists((int) $row->rowid, $alertDate)) {
                    continue;
                }
                $label = $langs->transnoentities('FollowupDuReminderLabel', $row->ref) . ' (' . dol_print_date($nextDu, 'day') . ')';
                if ($this->createEvent((int) $row->rowid, (int) $row->fk_soc, $label, $alertDate)) {
                    $created++;
                }
            }
        }

        // Payment reminders whose reminder date has been reached and invoice not paid.
        $sqlRelance  = 'SELECT t.rowid, t.ref, t.fk_soc, t.date_relance FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup as t';
        $sqlRelance .= ' WHERE t.status = 1 AND t.facture_payee = 0 AND t.date_relance IS NOT NULL';
        $sqlRelance .= ' AND t.entity IN (' . getEntity('reedcrm_facturerec_followup') . ')';
        $sqlRelance .= " AND t.date_relance <= '" . $this->db->idate($now) . "'";

        $resqlRelance = $this->db->query($sqlRelance);
        if ($resqlRelance) {
            while ($row = $this->db->fetch_object($resqlRelance)) {
                $relanceDate = $this->db->jdate($row->date_relance);
                if ($this->reminderExists((int) $row->rowid, $relanceDate)) {
                    continue;
                }
                $label = $langs->transnoentities('FollowupRelanceReminderLabel', $row->ref);
                if ($this->createEvent((int) $row->rowid, (int) $row->fk_soc, $label, $relanceDate)) {
                    $created++;
                }
            }
        }

        $this->output = $langs->transnoentities('FollowupCronReminders', $created);

        return 0;
    }

    /**
     * Job: create or refresh DU audits from the invoiced Document Unique services (product ref
     * "DU_A%": DU_AU audits + DU_AC/DU_Accompagnement setup, which also start the yearly cycle).
     * One audit per client (the latest audit invoice). A newer audit invoice refreshes the cycle;
     * manual date moves for the current cycle are preserved (only a strictly newer invoice updates them).
     *
     * @return int 0 if OK, < 0 if KO.
     */
    public function syncDuAudits(): int
    {
        global $langs, $user;

        $langs->loadLangs(['reedcrm@reedcrm']);
        require_once __DIR__ . '/duaudit.class.php';

        $created = 0;
        $updated = 0;
        $seen    = [];

        $sql  = 'SELECT f.fk_soc, f.rowid as fac, f.datef,';
        $sql .= "  SUBSTRING_INDEX(GROUP_CONCAT(p.label ORDER BY fd.rowid SEPARATOR '\\n'), '\\n', 1) as label,";
        $sql .= '  SUM(fd.total_ttc) as line_ttc';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet as fd ON fd.fk_facture = f.rowid';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = fd.fk_product';
        $sql .= " WHERE p.ref LIKE 'DU\_A%'"; // DU_AU (audit) + DU_AC/DU_Accompagnement (mise en place)
        $sql .= ' AND f.type <> 2'; // exclude credit notes (avoirs)
        $sql .= ' AND f.datef IS NOT NULL AND f.fk_soc > 0 AND f.entity IN (' . getEntity('facture') . ')';
        $sql .= ' GROUP BY f.fk_soc, f.rowid, f.datef';
        $sql .= ' ORDER BY f.fk_soc, f.datef DESC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->output = $this->db->lasterror();
            return -1;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            if (isset($seen[$obj->fk_soc])) {
                continue; // keep only the latest audit invoice per client
            }
            $seen[$obj->fk_soc] = 1;

            $lastAudit = $this->db->jdate($obj->datef);

            $sqlCheck   = 'SELECT rowid, last_audit_date, status FROM ' . MAIN_DB_PREFIX . 'reedcrm_du_audit';
            $sqlCheck  .= ' WHERE fk_soc = ' . ((int) $obj->fk_soc) . ' AND entity IN (' . getEntity('reedcrm_du_audit') . ')';
            $resqlCheck = $this->db->query($sqlCheck);

            if ($resqlCheck && $existing = $this->db->fetch_object($resqlCheck)) {
                if ((int) $existing->status === DuAudit::STATUS_DONE) {
                    continue; // never reopen an audit already marked done
                }
                $existingLast = !empty($existing->last_audit_date) ? $this->db->jdate($existing->last_audit_date) : 0;
                if ($lastAudit <= $existingLast) {
                    continue; // nothing newer -> preserve current cycle (incl. manual date moves)
                }
                $audit = new DuAudit($this->db);
                if ($audit->fetch((int) $existing->rowid) > 0) {
                    $audit->last_audit_date   = $lastAudit;
                    $audit->next_audit_date   = dol_time_plus_duree($lastAudit, 1, 'y');
                    $audit->fk_facture_source = (int) $obj->fac;
                    $audit->source            = 'invoice';
                    $audit->note              = $obj->label;
                    $audit->montant           = (float) $obj->line_ttc;
                    $audit->status            = DuAudit::STATUS_TODO;
                    if ($audit->update($user) > 0) {
                        $updated++;
                    }
                }
            } else {
                $audit                    = new DuAudit($this->db);
                $audit->fk_soc            = (int) $obj->fk_soc;
                $audit->last_audit_date   = $lastAudit;
                $audit->next_audit_date   = dol_time_plus_duree($lastAudit, 1, 'y');
                $audit->fk_facture_source = (int) $obj->fac;
                $audit->source            = 'invoice';
                $audit->note              = $obj->label;
                $audit->montant           = (float) $obj->line_ttc;
                $audit->status            = DuAudit::STATUS_TODO;
                if ($audit->create($user) > 0) {
                    $created++;
                }
            }
        }

        $this->output = $langs->transnoentities('FollowupCronAuditsSynced', $created, $updated);

        return 0;
    }

    /**
     * Tell whether an agenda reminder already exists for a follow-up at a given date (idempotency guard).
     *
     * @param  int $followupId Follow-up ID.
     * @param  int $date       Reminder timestamp.
     * @return bool            True if an event already exists.
     */
    protected function reminderExists(int $followupId, int $date): bool
    {
        $sql  = 'SELECT COUNT(*) as nb FROM ' . MAIN_DB_PREFIX . 'actioncomm';
        $sql .= " WHERE fk_element = " . $followupId . " AND elementtype = 'recurringinvoicefollowup@reedcrm'";
        $sql .= " AND datep = '" . $this->db->idate($date) . "'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj && $obj->nb > 0;
        }

        return false;
    }

    /**
     * Create an agenda event (to-do) linked to a follow-up.
     *
     * @param  int    $followupId Follow-up ID.
     * @param  int    $socid      Thirdparty ID.
     * @param  string $label      Event label.
     * @param  int    $datep      Event date timestamp.
     * @return bool               True on success.
     */
    protected function createEvent(int $followupId, int $socid, string $label, int $datep): bool
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $event              = new ActionComm($this->db);
        $event->type_code   = 'AC_OTH';
        $event->label       = $label;
        $event->datep       = $datep;
        $event->datef       = $datep;
        $event->percentage  = -1;
        $event->userownerid = $user->id > 0 ? $user->id : 1;
        $event->socid       = $socid > 0 ? $socid : 0;
        $event->fk_element  = $followupId;
        $event->elementtype = 'recurringinvoicefollowup@reedcrm';

        return $event->create($user) > 0;
    }
}
