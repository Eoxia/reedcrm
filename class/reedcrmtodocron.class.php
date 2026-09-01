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
 * \file    class/reedcrmtodocron.class.php
 * \ingroup reedcrm
 * \brief   Cron jobs feeding the relaunch backlogs of the todo board
 */

// dol_time_plus_duree() is not loaded by default, the cron runner would fatal on it
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

require_once __DIR__ . '/../lib/reedcrm_todo.lib.php';

/**
 * Class raising the relaunch events of the todo board.
 *
 * A proposal still waiting for its answer, and an invoice still waiting for its payment,
 * both become an event to do once the delay of the configuration has passed.
 */
class ReedcrmTodoCron
{
    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Last output from end job execution
     */
    public string $output = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Job: raise an event for every proposal validated for more than the configured delay
     * and still waiting for its answer (neither signed nor refused).
     *
     * @return int 0 if OK, < 0 if KO
     */
    public function createProposalRelaunchEvents(): int
    {
        global $conf, $langs;

        $langs->loadLangs(['reedcrm@reedcrm', 'agenda', 'propal']);

        $days      = getDolGlobalInt('REEDCRM_TODO_PROPAL_RELAUNCH_DAYS', 30);
        $threshold = dol_time_plus_duree(dol_now(), -$days, 'd');

        // fk_statut = 1 is the validated proposal: a signed one is 2, a refused one 3
        $sql  = 'SELECT p.rowid, p.ref, p.fk_soc, p.fk_projet, p.total_ttc, p.fk_user_author, p.fk_user_valid,';
        $sql .= ' COALESCE(p.date_valid, p.datep) as date_reference, s.nom as soc_name';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal as p';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
        $sql .= ' WHERE p.entity IN (' . getEntity('propal') . ')';
        $sql .= ' AND p.fk_statut = 1';
        $sql .= " AND COALESCE(p.date_valid, p.datep) <= '" . $this->db->idate($threshold) . "'";
        $sql .= ' ORDER BY p.rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->output = $this->db->lasterror();
            dol_syslog(__METHOD__ . ': ' . $this->db->lasterror(), LOG_ERR);
            return -1;
        }

        $created = 0;
        while ($row = $this->db->fetch_object($resql)) {
            if ($this->relaunchExists('propal', (int) $row->rowid, REEDCRM_TODO_CODE_PROPAL_RELAUNCH, $days)) {
                continue;
            }

            $referenceDate = $this->db->jdate($row->date_reference);
            $label         = $langs->transnoentities('TodoPropalRelaunchLabel', $row->ref);
            if (!empty($row->soc_name)) {
                $label .= ' - ' . $row->soc_name;
            }
            $note = $langs->transnoentities(
                'TodoPropalRelaunchNote',
                $referenceDate ? dol_print_date($referenceDate, 'day') : '?',
                price($row->total_ttc, 0, $langs, 1, -1, -1, $conf->currency)
            );

            if ($this->createRelaunchEvent(REEDCRM_TODO_CODE_PROPAL_RELAUNCH, 'propal', $row, $label, $note)) {
                $created++;
            }
        }
        $this->db->free($resql);

        $this->output = $langs->transnoentities('TodoPropalRelaunchCronResult', $created);

        return 0;
    }

    /**
     * Job: raise an event for every validated invoice still unpaid more than the configured
     * delay after its due date (or after its invoice date when it carries no due date).
     *
     * @return int 0 if OK, < 0 if KO
     */
    public function createInvoiceRelaunchEvents(): int
    {
        global $conf, $langs;

        $langs->loadLangs(['reedcrm@reedcrm', 'agenda', 'bills']);

        $days      = getDolGlobalInt('REEDCRM_TODO_INVOICE_RELAUNCH_DAYS', 30);
        $threshold = dol_time_plus_duree(dol_now(), -$days, 'd');

        // fk_statut = 1 is the validated invoice, paye = 0 the unpaid one, type 2 a credit note
        $sql  = 'SELECT f.rowid, f.ref, f.fk_soc, f.fk_projet, f.total_ttc, f.fk_user_author, f.fk_user_valid,';
        $sql .= ' COALESCE(f.date_lim_reglement, f.datef) as date_reference, s.nom as soc_name';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = f.fk_soc';
        $sql .= ' WHERE f.entity IN (' . getEntity('facture') . ')';
        $sql .= ' AND f.fk_statut = 1 AND f.paye = 0 AND f.type <> 2';
        $sql .= " AND COALESCE(f.date_lim_reglement, f.datef) <= '" . $this->db->idate($threshold) . "'";
        $sql .= ' ORDER BY f.rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->output = $this->db->lasterror();
            dol_syslog(__METHOD__ . ': ' . $this->db->lasterror(), LOG_ERR);
            return -1;
        }

        $created = 0;
        while ($row = $this->db->fetch_object($resql)) {
            if ($this->relaunchExists('invoice', (int) $row->rowid, REEDCRM_TODO_CODE_INVOICE_RELAUNCH, $days)) {
                continue;
            }

            $referenceDate = $this->db->jdate($row->date_reference);
            $label         = $langs->transnoentities('TodoInvoiceRelaunchLabel', $row->ref);
            if (!empty($row->soc_name)) {
                $label .= ' - ' . $row->soc_name;
            }
            $note = $langs->transnoentities(
                'TodoInvoiceRelaunchNote',
                $referenceDate ? dol_print_date($referenceDate, 'day') : '?',
                price($row->total_ttc, 0, $langs, 1, -1, -1, $conf->currency)
            );

            if ($this->createRelaunchEvent(REEDCRM_TODO_CODE_INVOICE_RELAUNCH, 'invoice', $row, $label, $note)) {
                $created++;
            }
        }
        $this->db->free($resql);

        $this->output = $langs->transnoentities('TodoInvoiceRelaunchCronResult', $created);

        return 0;
    }

    /**
     * Tell whether an object already carries a relaunch that must not be doubled.
     *
     * A relaunch still to be done blocks any new one, and so does one raised less than the
     * delay ago: a relaunch closed or dropped today comes back on the board a delay later,
     * as long as the object is still waiting.
     *
     * @param  string $elementType Element type the event is linked to
     * @param  int    $elementId   Row ID of the proposal or the invoice
     * @param  string $code        Code carried by the relaunch events
     * @param  int    $days        Delay of the configuration, in days
     * @return bool                True when no new relaunch is due
     */
    protected function relaunchExists(string $elementType, int $elementId, string $code, int $days): bool
    {
        $since = dol_time_plus_duree(dol_now(), -$days, 'd');

        $sql  = 'SELECT COUNT(*) as nb FROM ' . MAIN_DB_PREFIX . 'actioncomm';
        $sql .= " WHERE elementtype = '" . $this->db->escape($elementType) . "'";
        $sql .= ' AND fk_element = ' . $elementId;
        $sql .= " AND code = '" . $this->db->escape($code) . "'";
        $sql .= ' AND entity IN (' . getEntity('agenda') . ')';
        $sql .= " AND ((percent >= 0 AND percent < 100) OR datec >= '" . $this->db->idate($since) . "')";

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ': ' . $this->db->lasterror(), LOG_ERR);
            // On a failed check, hold the creation back rather than risk a duplicate
            return true;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $obj && $obj->nb > 0;
    }

    /**
     * Create a relaunch event to do, without any date.
     *
     * ActionComm::create() always writes a start date, an event with none has to be saved
     * once before its date can be cleared. The board reads those dateless events as a plain
     * backlog: nothing to put in a calendar, only something waiting to be done.
     *
     * @param  string $code        Code carried by the event
     * @param  string $elementType Element type the event is linked to
     * @param  object $row         Row of the proposal or the invoice
     * @param  string $label       Label of the event
     * @param  string $note        Private note of the event
     * @return bool                True when the event was created
     */
    protected function createRelaunchEvent(string $code, string $elementType, $row, string $label, string $note): bool
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        // The one who validated the object owns the relaunch, the author otherwise
        $ownerId = (int) $row->fk_user_valid;
        if (empty($ownerId)) {
            $ownerId = (int) $row->fk_user_author;
        }
        if (empty($ownerId)) {
            $ownerId = ($user->id > 0 ? $user->id : 1);
        }

        $event               = new ActionComm($this->db);
        $event->type_code    = 'AC_OTH';
        $event->code         = $code;
        $event->label        = $label;
        $event->note_private = $note;
        $event->percentage   = 0;
        $event->userownerid  = $ownerId;
        $event->socid        = (int) $row->fk_soc;
        $event->fk_project   = (int) $row->fk_projet;
        $event->fk_element   = (int) $row->rowid;
        $event->elementtype  = $elementType;

        if ($event->create($user) <= 0) {
            dol_syslog(__METHOD__ . ': ' . $event->error . ' ' . implode(',', $event->errors), LOG_ERR);
            return false;
        }

        // Clear the start date create() had to write
        $event->datep = null;
        $event->datef = null;
        if ($event->update($user, 1) <= 0) {
            dol_syslog(__METHOD__ . ': could not clear the date of event ' . $event->id . ' - ' . $event->error, LOG_WARNING);
        }

        return true;
    }
}
