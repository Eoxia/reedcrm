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
 * \file    class/reedcrmticketmoney.class.php
 * \ingroup reedcrm
 * \brief   Money side of the tickets: support credit of the subscriptions, overdue invoices of the customer,
 *          time left to bill, and the single next action that brings the money in.
 *
 * Rules (Eoxia/reedcrm#1242):
 * - a subscription invoice (products of REEDCRM_TICKET_MONEY_SUBSCRIPTION_PRODUCTS) opens a support credit equal to
 *   the duration of the product, valid one year from the date of the invoice ;
 * - the time logged on the tickets of the customer uses that credit in chronological order, the rest is billable ;
 * - a customer with overdue invoices has no credit at all : until they pay, every minute is billable ;
 * - the billable time is billed with the product of REEDCRM_TICKET_MONEY_PRODUCT_REF, rounded up per ticket to
 *   REEDCRM_TICKET_MONEY_ROUNDING_MINUTES.
 *
 * Time spent is read from the task "<REEDCRM_TICKET_TIME_TASK_PREFIX> <ticket ref>" of the project of the ticket, the
 * one the ticket card and the PWA log time on. Only the "ticket_ref" suffix gives one task per ticket.
 */

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

/**
 * Class ReedcrmTicketMoney
 */
class ReedcrmTicketMoney
{
    /**
     * Next actions, by priority : the first one that applies is the one the list and the card offer
     */
    public const ACTION_ATTACH_THIRDPARTY = 'attach_thirdparty';
    public const ACTION_RELAUNCH          = 'relaunch';
    public const ACTION_BILL              = 'bill';
    public const ACTION_QUOTE             = 'quote';
    public const ACTION_ATTACH_PROJECT    = 'attach_project';
    public const ACTION_LOG_TIME          = 'log_time';
    public const ACTION_CLOSE             = 'close';
    public const ACTION_NONE              = 'none';

    /**
     * Section of the tickets handled anyway while their customer owes money : not an action, they keep theirs
     */
    public const SECTION_RESERVE = 'reserve';

    /**
     * Billing modes of a ticket, stored in the extrafield reedcrm_billing (empty = auto)
     */
    public const BILLING_AUTO     = 'auto';
    public const BILLING_BILLABLE = 'billable';
    public const BILLING_FREE     = 'free';

    /**
     * Statuses of a ticket that is over
     */
    public const DONE_STATUS = [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELED];

    /**
     * Ticket type of the commercial requests
     */
    public const COMMERCIAL_TYPE = 'COM';

    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var string[] Error messages
     */
    public $errors = [];

    /**
     * @var array<int,stdClass> Tickets loaded, by id, with their money figures
     */
    public $tickets = [];

    /**
     * @var array<int,stdClass> Customers of the tickets, by id : overdue invoices and support credit
     */
    public $societies = [];

    /**
     * @var int[] Ids of the tickets the caller asked for (the others are loaded for the credit of their customer only)
     */
    protected $targetIds = [];

    /**
     * @var array<int,array<int,stdClass>> Time entries by ticket id
     */
    protected $timeEntries = [];

    /**
     * @var array<string,mixed>|false|null Billing product (id, ref, label, price, tva_tx, type), false when not found, null before the first read
     */
    protected $product = null;

    /**
     * @var array<int,float> Hourly rate (HT) of each customer, by customer id
     */
    protected $rates = [];

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
     * Is the time of every ticket in its own task ? Only the "ticket_ref" suffix gives one task per ticket
     *
     * @return bool
     */
    public static function isTimePerTicket(): bool
    {
        return getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref') === 'ticket_ref';
    }

    /**
     * Rounding step of the billed time, in seconds
     *
     * @return int
     */
    public static function getRoundingSeconds(): int
    {
        return max(1, getDolGlobalInt('REEDCRM_TICKET_MONEY_ROUNDING_MINUTES', 15)) * 60;
    }

    /**
     * Refs of the subscription products that open a support credit
     *
     * @return string[]
     */
    public static function getSubscriptionProductRefs(): array
    {
        $refs = array_map('trim', explode(',', getDolGlobalString('REEDCRM_TICKET_MONEY_SUBSCRIPTION_PRODUCTS', 'D2,D3,D4,D41,D5')));

        return array_values(array_filter($refs, function ($ref) {
            return $ref !== '';
        }));
    }

    /**
     * Convert a product duration ("3h", "180i", "1d") to seconds. Only minutes, hours and days make a credit
     *
     * @param  string $duration Duration as stored in llx_product.duration
     * @return int              Seconds, 0 when the unit is not a working time
     */
    public static function durationToSeconds(string $duration): int
    {
        if (!preg_match('/^\s*([0-9.]+)\s*([ihd])\s*$/i', $duration, $matches)) {
            return 0;
        }
        $value = (float) $matches[1];
        switch (strtolower($matches[2])) {
            case 'i':
                return (int) round($value * 60);
            case 'h':
                return (int) round($value * 3600);
            default:
                // A day of support is a working day, not 24 hours
                return (int) round($value * getDolGlobalInt('MAIN_DURATION_OF_WORKDAY', 7 * 3600));
        }
    }

    /**
     * Round a billable time up to the rounding step
     *
     * @param  int $seconds Billable time
     * @return int          Billed time, in seconds
     */
    public static function roundBillable(int $seconds): int
    {
        if ($seconds <= 0) {
            return 0;
        }
        $step = self::getRoundingSeconds();

        return (int) (ceil($seconds / $step) * $step);
    }

    /**
     * Load the tickets and compute their money figures
     *
     * Options :
     * - ticket_ids        int[] Load these tickets only (the card, a billing action)
     * - with_closed_to_bill int   1 (default) to add the closed tickets that still hold time to bill
     *
     * Every ticket of the customers concerned is read too : the credit of a customer is shared by all its tickets.
     *
     * @param  array<string,mixed> $options Options
     * @return int                          Negative if KO, number of target tickets if OK
     */
    public function load(array $options = []): int
    {
        $this->tickets     = [];
        $this->societies   = [];
        $this->timeEntries = [];
        $this->targetIds   = [];

        $ticketIds = array_filter(array_map('intval', $options['ticket_ids'] ?? []));

        // 1. Target tickets : the open ones, plus the closed ones still holding unbilled time
        $sql  = 'SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'ticket AS t';
        $sql .= ' WHERE t.entity IN (' . getEntity('ticket') . ')';
        if (!empty($ticketIds)) {
            $sql .= ' AND t.rowid IN (' . implode(',', $ticketIds) . ')';
        } else {
            $sql .= ' AND (t.fk_statut NOT IN (' . implode(',', self::DONE_STATUS) . ')';
            if (($options['with_closed_to_bill'] ?? 1) && self::isTimePerTicket()) {
                $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'projet_task AS pt';
                $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "element_time AS et ON et.elementtype = 'task' AND et.fk_element = pt.rowid";
                $sql .= ' WHERE pt.fk_projet = t.fk_project AND pt.label = CONCAT(' . $this->getTaskPrefixSql() . ', t.ref)';
                $sql .= ' AND (et.invoice_id IS NULL OR et.invoice_id = 0))';
            }
            $sql .= ')';
        }
        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $this->targetIds[] = (int) $obj->rowid;
        }
        $this->db->free($resql);

        if (empty($this->targetIds)) {
            return 0;
        }

        // 2. Customers of the targets, then every ticket of these customers (the credit is per customer)
        $sql  = 'SELECT DISTINCT t.fk_soc FROM ' . MAIN_DB_PREFIX . 'ticket AS t';
        $sql .= ' WHERE t.rowid IN (' . implode(',', $this->targetIds) . ') AND t.fk_soc > 0';
        $socIds = [];
        $resql  = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $socIds[] = (int) $obj->fk_soc;
        }
        $this->db->free($resql);

        if ($this->loadTickets($socIds) < 0 || $this->loadTimeEntries() < 0 || $this->loadSocieties($socIds) < 0) {
            return -1;
        }
        if ($this->loadSubscriptions($socIds) < 0 || $this->loadOverdueInvoices($socIds) < 0) {
            return -1;
        }
        if ($this->loadLinkedProposals() < 0 || $this->loadLastActivity() < 0) {
            return -1;
        }

        $this->computeCredit();
        foreach ($this->tickets as $ticket) {
            $this->computeTicket($ticket);
        }

        return count($this->targetIds);
    }

    /**
     * Tickets the caller asked for, with their figures, the most money at stake first
     *
     * @return stdClass[]
     */
    public function getTargets(): array
    {
        $rows = [];
        foreach ($this->targetIds as $id) {
            if (isset($this->tickets[$id])) {
                $rows[] = $this->tickets[$id];
            }
        }
        usort($rows, function ($first, $second) {
            return [$second->stake, $first->action_rank, $first->datec] <=> [$first->stake, $second->action_rank, $second->datec];
        });

        return $rows;
    }

    /**
     * One ticket with its figures
     *
     * @param  int $ticketId Ticket id
     * @return stdClass|null
     */
    public function getTicket(int $ticketId): ?stdClass
    {
        return $this->tickets[$ticketId] ?? null;
    }

    /**
     * Customer of a ticket with its figures
     *
     * @param  int $socId Customer id
     * @return stdClass|null
     */
    public function getSociety(int $socId): ?stdClass
    {
        return $this->societies[$socId] ?? null;
    }

    /**
     * Figures of the tiles on top of the list, for a set of tickets
     *
     * @param  stdClass[] $rows Tickets (getTargets(), filtered)
     * @return array<string,array<string,float|int>>
     */
    public function getSummary(array $rows): array
    {
        $summary = [
            self::ACTION_BILL     => ['nb' => 0, 'amount' => 0.0],
            self::ACTION_RELAUNCH => ['nb' => 0, 'amount' => 0.0],
            self::ACTION_QUOTE    => ['nb' => 0, 'amount' => 0.0],
            'unbillable'          => ['nb' => 0, 'amount' => 0.0],
            self::ACTION_CLOSE    => ['nb' => 0, 'amount' => 0.0],
        ];
        $overdueSocs = [];
        foreach ($rows as $row) {
            if ($row->amount_to_bill > 0) {
                $summary[self::ACTION_BILL]['nb']++;
                $summary[self::ACTION_BILL]['amount'] += $row->amount_to_bill;
            }
            if ($row->fk_soc > 0 && $this->societies[$row->fk_soc]->overdue > 0 && !isset($overdueSocs[$row->fk_soc])) {
                $overdueSocs[$row->fk_soc] = 1;
                $summary[self::ACTION_RELAUNCH]['nb']++;
                $summary[self::ACTION_RELAUNCH]['amount'] += $this->societies[$row->fk_soc]->overdue;
            }
            if ($row->action === self::ACTION_QUOTE) {
                $summary[self::ACTION_QUOTE]['nb']++;
            }
            if (in_array($row->action, [self::ACTION_ATTACH_THIRDPARTY, self::ACTION_ATTACH_PROJECT], true)) {
                $summary['unbillable']['nb']++;
            }
            if ($row->action === self::ACTION_CLOSE) {
                $summary[self::ACTION_CLOSE]['nb']++;
            }
        }

        return $summary;
    }

    /**
     * Billing product and its default price
     *
     * @return array<string,mixed>|false Product (id, ref, label, price, tva_tx, type), false when not found
     */
    public function getProduct()
    {
        if ($this->product !== null) {
            return $this->product;
        }

        $this->product = false;
        $ref           = getDolGlobalString('REEDCRM_TICKET_MONEY_PRODUCT_REF', 'AT-Technique-1H');
        $sql           = 'SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type FROM ' . MAIN_DB_PREFIX . 'product AS p';
        $sql          .= " WHERE p.ref = '" . $this->db->escape($ref) . "' AND p.entity IN (" . getEntity('product') . ')';
        $resql         = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            $this->product = ['id' => (int) $obj->rowid, 'ref' => $obj->ref, 'label' => $obj->label, 'price' => (float) $obj->price, 'tva_tx' => (float) $obj->tva_tx, 'type' => (int) $obj->fk_product_type];
        }

        return $this->product;
    }

    /**
     * Bill the time left to bill of some tickets : one DRAFT invoice per customer, one line per ticket.
     * The billed time entries are marked invoiced (element_time.invoice_id), as the core does from a project.
     *
     * @param  User  $user      User who bills
     * @param  int[] $ticketIds Tickets to bill
     * @return int[]            Ids of the draft invoices created, by customer id ; see $this->errors
     */
    public function billTickets(User $user, array $ticketIds): array
    {
        global $langs, $mysoc;

        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $this->errors = [];
        $invoiceIds   = [];

        $product = $this->getProduct();
        if (empty($product)) {
            $this->errors[] = $langs->trans('TicketMoneyNoProduct', getDolGlobalString('REEDCRM_TICKET_MONEY_PRODUCT_REF', 'AT-Technique-1H'));
            return [];
        }
        if ($this->load(['ticket_ids' => $ticketIds]) < 0) {
            return [];
        }

        // Tickets to bill, grouped by customer
        $bySoc = [];
        foreach ($this->getTargets() as $ticket) {
            if ($ticket->fk_soc > 0 && $ticket->time_to_bill > 0) {
                $bySoc[$ticket->fk_soc][] = $ticket;
            }
        }
        if (empty($bySoc)) {
            $this->errors[] = $langs->trans('TicketMoneyNothingToBill');
            return [];
        }

        $productStatic = new Product($this->db);
        $productStatic->fetch($product['id']);

        foreach ($bySoc as $socId => $tickets) {
            $society = new Societe($this->db);
            $society->fetch($socId);

            $this->db->begin();

            // One project only on the invoice : the one shared by all its tickets, if any
            $projectIds = array_unique(array_map(function ($ticket) {
                return (int) $ticket->fk_project;
            }, $tickets));

            $invoice                    = new Facture($this->db);
            $invoice->socid             = $socId;
            $invoice->type              = Facture::TYPE_STANDARD;
            $invoice->date              = dol_now();
            $invoice->fk_project        = count($projectIds) === 1 ? reset($projectIds) : 0;
            $invoice->cond_reglement_id = !empty($society->cond_reglement_id) ? $society->cond_reglement_id : 1;
            $invoice->mode_reglement_id = !empty($society->mode_reglement_id) ? $society->mode_reglement_id : 0;
            $invoice->fk_account        = !empty($society->fk_account) ? $society->fk_account : 0;
            $invoice->note_private      = $langs->transnoentities('TicketMoneyInvoiceNote', implode(', ', array_map(function ($ticket) {
                return $ticket->ref;
            }, $tickets)));

            $invoiceId = $invoice->create($user);
            if ($invoiceId <= 0) {
                $this->db->rollback();
                $this->errors[] = $society->name . ' : ' . $invoice->error . ' ' . implode(', ', $invoice->errors);
                continue;
            }

            $price = $productStatic->getSellPrice($mysoc, $society);
            $error = 0;
            foreach ($tickets as $ticket) {
                $billedSeconds = self::roundBillable($ticket->time_to_bill);
                $qty           = round($billedSeconds / 3600, 4);
                $lineId        = $invoice->addline(
                    $this->getLineDescription($ticket, $billedSeconds),
                    (float) $price['pu_ht'],
                    $qty,
                    (float) $price['tva_tx'],
                    0,
                    0,
                    $product['id'],
                    0,
                    '',
                    '',
                    0,
                    0,
                    0,
                    'HT',
                    0,
                    $product['type']
                );
                if ($lineId <= 0) {
                    $error++;
                    $this->errors[] = $ticket->ref . ' : ' . $invoice->error;
                    break;
                }
                if ($this->markInvoiced($ticket, $invoiceId, $lineId) < 0) {
                    $error++;
                    break;
                }
                $invoice->add_object_linked('ticket', $ticket->id);
            }

            if ($error) {
                $this->db->rollback();
                continue;
            }
            $this->db->commit();
            $invoiceIds[$socId] = $invoiceId;
        }

        return $invoiceIds;
    }

    /**
     * Create a DRAFT proposal for the customer of a ticket, linked to the ticket and its project
     *
     * @param  User $user     User who creates it
     * @param  int  $ticketId Ticket id
     * @return int            Negative if KO, id of the proposal if OK
     */
    public function createProposal(User $user, int $ticketId): int
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $ticket = new Ticket($this->db);
        if ($ticket->fetch($ticketId) <= 0 || empty($ticket->fk_soc)) {
            $this->error = $langs->trans('TicketMoneyNoThirdparty');
            return -1;
        }
        $society = new Societe($this->db);
        $society->fetch($ticket->fk_soc);

        $proposal                    = new Propal($this->db);
        $proposal->socid             = $ticket->fk_soc;
        $proposal->fk_project        = (int) $ticket->fk_project;
        $proposal->date              = dol_now();
        $proposal->duree_validite    = getDolGlobalInt('PROPALE_VALIDITY_DURATION', 15);
        $proposal->cond_reglement_id = !empty($society->cond_reglement_id) ? $society->cond_reglement_id : 0;
        $proposal->mode_reglement_id = !empty($society->mode_reglement_id) ? $society->mode_reglement_id : 0;
        $proposal->note_private      = $langs->transnoentities('TicketMoneyProposalNote', $ticket->ref, $ticket->subject);

        $this->db->begin();
        $proposalId = $proposal->create($user);
        if ($proposalId <= 0 || $proposal->add_object_linked('ticket', $ticket->id) <= 0) {
            $this->db->rollback();
            $this->error = $proposal->error;
            return -1;
        }
        $this->db->commit();

        return $proposalId;
    }

    /**
     * Set the billing mode of a ticket (extrafield reedcrm_billing), with the reason of a free ticket
     *
     * @param  User   $user     User
     * @param  Ticket $ticket   Ticket
     * @param  string $mode     One of the BILLING_* constants
     * @param  string $note     Reason, kept for a free ticket only
     * @return int              Negative if KO, positive if OK
     */
    public function setBillingMode(User $user, Ticket $ticket, string $mode, string $note = ''): int
    {
        if (!in_array($mode, [self::BILLING_AUTO, self::BILLING_BILLABLE, self::BILLING_FREE], true)) {
            return -1;
        }
        $ticket->fetch_optionals();
        $ticket->array_options['options_reedcrm_billing']      = $mode === self::BILLING_AUTO ? '' : $mode;
        $ticket->array_options['options_reedcrm_billing_note'] = $mode === self::BILLING_FREE ? dol_trunc($note, 255, 'right', 'UTF-8', 1) : '';

        $result = $ticket->insertExtraFields('', $user);
        if ($result < 0) {
            $this->error = $ticket->error;
        }

        return $result;
    }

    /**
     * Put a ticket under reserve, or take it out : the customer owes money, the ticket is handled anyway for a while
     *
     * @param  User   $user   User
     * @param  Ticket $ticket Ticket
     * @param  bool   $on     True to put it under reserve from now, false to take it out
     * @return int            Negative if KO, positive if OK
     */
    public function setReserve(User $user, Ticket $ticket, bool $on): int
    {
        $ticket->fetch_optionals();
        $ticket->array_options['options_reedcrm_reserve'] = $on ? dol_now() : '';

        $result = $ticket->insertExtraFields('', $user);
        if ($result < 0) {
            $this->error = $ticket->error;
        }

        return $result;
    }

    /**
     * Is the ticket handled under reserve right now : reserve still valid and customer still owing money
     *
     * @param  stdClass $ticket Ticket with its figures
     * @return bool
     */
    public function isInReserve(stdClass $ticket): bool
    {
        $society = $ticket->fk_soc > 0 ? ($this->societies[$ticket->fk_soc] ?? null) : null;

        return $ticket->reserved && $society !== null && $society->overdue > 0;
    }

    /**
     * How long a ticket stays under reserve before it goes back to the overdue invoices to chase
     *
     * @return int Days
     */
    public static function getReserveDays(): int
    {
        return max(1, getDolGlobalInt('REEDCRM_TICKET_MONEY_RESERVE_DAYS', 30));
    }

    /**
     * Read the tickets : the targets, plus every ticket of their customers
     *
     * @param  int[] $socIds Customers of the targets
     * @return int           Negative if KO, positive if OK
     */
    protected function loadTickets(array $socIds): int
    {
        $sql  = 'SELECT t.rowid, t.ref, t.track_id, t.subject, t.fk_soc, t.fk_project, t.fk_user_assign, t.fk_statut, t.type_code,';
        $sql .= ' t.datec, t.date_close, s.nom AS soc_name, s.status AS soc_status, p.ref AS project_ref';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'ticket AS t';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = t.fk_soc';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet AS p ON p.rowid = t.fk_project';
        $sql .= ' WHERE t.entity IN (' . getEntity('ticket') . ')';
        $sql .= ' AND (t.rowid IN (' . implode(',', $this->targetIds) . ')';
        if (!empty($socIds)) {
            $sql .= ' OR t.fk_soc IN (' . implode(',', $socIds) . ')';
        }
        $sql .= ')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $this->tickets[(int) $obj->rowid] = (object) [
                'id'               => (int) $obj->rowid,
                'ref'              => $obj->ref,
                'track_id'         => $obj->track_id,
                'subject'          => $obj->subject,
                'fk_soc'           => (int) $obj->fk_soc,
                'soc_name'         => (string) $obj->soc_name,
                'soc_status'       => (int) $obj->soc_status,
                'fk_project'       => (int) $obj->fk_project,
                'project_ref'      => (string) $obj->project_ref,
                'fk_user_assign'   => (int) $obj->fk_user_assign,
                'status'           => (int) $obj->fk_statut,
                'type_code'        => (string) $obj->type_code,
                'datec'            => (int) $this->db->jdate($obj->datec),
                'date_close'       => (int) $this->db->jdate($obj->date_close),
                'is_open'          => !in_array((int) $obj->fk_statut, self::DONE_STATUS, true),
                'billing'          => self::BILLING_AUTO,
                'billing_note'     => '',
                'reserve_date'     => 0,
                'reserve_until'    => 0,
                'reserved'         => false,
                'time_total'       => 0,
                'time_included'    => 0,
                'time_to_bill'     => 0,
                'time_invoiced'    => 0,
                'time_free'        => 0,
                'amount_to_bill'   => 0.0,
                'invoice_ids'      => [],
                'proposal_ids'     => [],
                'last_activity'    => 0,
                'action'           => self::ACTION_NONE,
                'action_rank'      => 99,
                'stake'            => 0.0,
            ];
        }
        $this->db->free($resql);

        // Billing mode : read apart, the columns only exist once the module has been reactivated
        $sql   = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'ticket_extrafields WHERE fk_object IN (' . implode(',', array_keys($this->tickets)) . ')';
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $ticket = $this->tickets[(int) $obj->fk_object] ?? null;
                if ($ticket === null) {
                    continue;
                }
                if (in_array($obj->reedcrm_billing ?? '', [self::BILLING_BILLABLE, self::BILLING_FREE], true)) {
                    $ticket->billing      = $obj->reedcrm_billing;
                    $ticket->billing_note = (string) ($obj->reedcrm_billing_note ?? '');
                }
                if (!empty($obj->reedcrm_reserve)) {
                    $ticket->reserve_date  = (int) $this->db->jdate($obj->reedcrm_reserve);
                    $ticket->reserve_until = $ticket->reserve_date + self::getReserveDays() * 86400;
                    $ticket->reserved      = dol_now() < $ticket->reserve_until;
                }
            }
            $this->db->free($resql);
        }

        return 1;
    }

    /**
     * Read the time logged on the tickets, in their "ticket_tps <ref>" task
     *
     * @return int Negative if KO, positive if OK
     */
    protected function loadTimeEntries(): int
    {
        if (empty($this->tickets) || !self::isTimePerTicket()) {
            return 1;
        }

        $sql  = 'SELECT t.rowid AS fk_ticket, et.rowid, et.element_duration, COALESCE(et.element_datehour, et.element_date) AS datehour,';
        $sql .= ' et.fk_user, et.invoice_id, et.note';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'ticket AS t';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task AS pt ON pt.fk_projet = t.fk_project AND pt.label = CONCAT(' . $this->getTaskPrefixSql() . ', t.ref)';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "element_time AS et ON et.elementtype = 'task' AND et.fk_element = pt.rowid";
        $sql .= ' WHERE t.rowid IN (' . implode(',', array_keys($this->tickets)) . ')';
        $sql .= ' ORDER BY datehour ASC, et.rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $ticketId = (int) $obj->fk_ticket;

            $this->timeEntries[$ticketId][] = (object) [
                'id'         => (int) $obj->rowid,
                'fk_ticket'  => $ticketId,
                'duration'   => (int) $obj->element_duration,
                'date'       => (int) $this->db->jdate($obj->datehour),
                'fk_user'    => (int) $obj->fk_user,
                'invoice_id' => (int) $obj->invoice_id,
                'note'       => (string) $obj->note,
                'included'   => 0,
                'to_bill'    => 0,
            ];
            if ((int) $obj->invoice_id > 0) {
                $this->tickets[$ticketId]->invoice_ids[(int) $obj->invoice_id] = (int) $obj->invoice_id;
            }
        }
        $this->db->free($resql);

        return 1;
    }

    /**
     * Prepare the customers
     *
     * @param  int[] $socIds Customers
     * @return int           Negative if KO, positive if OK
     */
    protected function loadSocieties(array $socIds): int
    {
        foreach ($socIds as $socId) {
            $this->societies[$socId] = (object) [
                'id'             => $socId,
                'overdue'        => 0.0,
                'overdue_count'  => 0,
                'overdue_oldest' => 0,
                'periods'        => [],
                'period'         => null,
                'frozen'         => false,
            ];
        }

        return 1;
    }

    /**
     * Read the subscription invoices of the last two years : each one opens a credit for one year
     *
     * @param  int[] $socIds Customers
     * @return int           Negative if KO, positive if OK
     */
    protected function loadSubscriptions(array $socIds): int
    {
        $refs = self::getSubscriptionProductRefs();
        if (empty($socIds) || empty($refs)) {
            return 1;
        }

        $refsSql = implode(',', array_map(function ($ref) {
            return "'" . $this->db->escape($ref) . "'";
        }, $refs));

        $sql  = 'SELECT f.rowid, f.ref, f.fk_soc, f.datef, p.ref AS product_ref, p.duration';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facturedet AS fd ON fd.fk_facture = f.rowid';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product AS p ON p.rowid = fd.fk_product';
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= ' AND f.fk_soc IN (' . implode(',', $socIds) . ')';
        $sql .= ' AND f.fk_statut IN (' . Facture::STATUS_VALIDATED . ',' . Facture::STATUS_CLOSED . ')';
        $sql .= ' AND f.type IN (' . Facture::TYPE_STANDARD . ',' . Facture::TYPE_REPLACEMENT . ')';
        $sql .= ' AND p.ref IN (' . $refsSql . ')';
        $sql .= " AND f.datef >= '" . $this->db->idate(dol_time_plus_duree(dol_now(), -2, 'y')) . "'";
        $sql .= ' ORDER BY f.datef ASC, f.rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        $periods = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $seconds  = self::durationToSeconds((string) $obj->duration);
            $invoiceId = (int) $obj->rowid;
            // One credit per invoice : the biggest subscription on it, a second line is not a second year of support
            if (!isset($periods[$invoiceId]) || $seconds > $periods[$invoiceId]->credit) {
                $start               = (int) $this->db->jdate($obj->datef);
                $periods[$invoiceId] = (object) [
                    'invoice_id'  => $invoiceId,
                    'invoice_ref' => $obj->ref,
                    'fk_soc'      => (int) $obj->fk_soc,
                    'product_ref' => $obj->product_ref,
                    'start'       => $start,
                    'end'         => dol_time_plus_duree($start, 1, 'y'),
                    'credit'      => $seconds,
                    'used'        => 0,
                ];
            }
        }
        $this->db->free($resql);

        foreach ($periods as $period) {
            $this->societies[$period->fk_soc]->periods[] = $period;
        }

        return 1;
    }

    /**
     * Read the overdue invoices of the customers, net of the payments and the credit notes used
     *
     * @param  int[] $socIds Customers
     * @return int           Negative if KO, positive if OK
     */
    protected function loadOverdueInvoices(array $socIds): int
    {
        if (empty($socIds)) {
            return 1;
        }

        $limit = dol_time_plus_duree(dol_get_first_hour(dol_now()), -getDolGlobalInt('REEDCRM_TICKET_MONEY_OVERDUE_GRACE_DAYS'), 'd');

        $sql  = 'SELECT f.rowid, f.fk_soc, f.total_ttc, f.date_lim_reglement,';
        $sql .= ' (SELECT COALESCE(SUM(pf.amount), 0) FROM ' . MAIN_DB_PREFIX . 'paiement_facture AS pf WHERE pf.fk_facture = f.rowid) AS paid,';
        $sql .= ' (SELECT COALESCE(SUM(re.amount_ttc), 0) FROM ' . MAIN_DB_PREFIX . 'societe_remise_except AS re WHERE re.fk_facture = f.rowid) AS credited';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= ' AND f.fk_soc IN (' . implode(',', $socIds) . ')';
        $sql .= ' AND f.fk_statut = ' . Facture::STATUS_VALIDATED . ' AND f.paye = 0';
        $sql .= ' AND f.type IN (' . Facture::TYPE_STANDARD . ',' . Facture::TYPE_REPLACEMENT . ',' . Facture::TYPE_DEPOSIT . ')';
        $sql .= " AND f.date_lim_reglement < '" . $this->db->idate($limit) . "'";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $remain = (float) $obj->total_ttc - (float) $obj->paid - (float) $obj->credited;
            if ($remain < 0.01) {
                continue;
            }
            $society = $this->societies[(int) $obj->fk_soc];
            $dueDate = (int) $this->db->jdate($obj->date_lim_reglement);

            $society->overdue += $remain;
            $society->overdue_count++;
            if (empty($society->overdue_oldest) || $dueDate < $society->overdue_oldest) {
                $society->overdue_oldest = $dueDate;
            }
        }
        $this->db->free($resql);

        foreach ($this->societies as $society) {
            $society->frozen = $society->overdue > 0;
        }

        return 1;
    }

    /**
     * Read the proposals and invoices linked to the target tickets
     *
     * @return int Negative if KO, positive if OK
     */
    protected function loadLinkedProposals(): int
    {
        $ids  = implode(',', $this->targetIds);
        $sql  = 'SELECT ee.fk_source, ee.sourcetype, ee.fk_target, ee.targettype FROM ' . MAIN_DB_PREFIX . 'element_element AS ee';
        $sql .= " WHERE (ee.targettype = 'ticket' AND ee.fk_target IN (" . $ids . ") AND ee.sourcetype IN ('propal', 'facture'))";
        $sql .= " OR (ee.sourcetype = 'ticket' AND ee.fk_source IN (" . $ids . ") AND ee.targettype IN ('propal', 'facture'))";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            if ($obj->targettype === 'ticket') {
                $ticketId = (int) $obj->fk_target;
                $type     = $obj->sourcetype;
                $id       = (int) $obj->fk_source;
            } else {
                $ticketId = (int) $obj->fk_source;
                $type     = $obj->targettype;
                $id       = (int) $obj->fk_target;
            }
            if (!isset($this->tickets[$ticketId])) {
                continue;
            }
            if ($type === 'propal') {
                $this->tickets[$ticketId]->proposal_ids[$id] = $id;
            } else {
                $this->tickets[$ticketId]->invoice_ids[$id] = $id;
            }
        }
        $this->db->free($resql);

        return 1;
    }

    /**
     * Read the date of the last event of the target tickets (message, time, status change)
     *
     * @return int Negative if KO, positive if OK
     */
    protected function loadLastActivity(): int
    {
        $sql  = 'SELECT a.fk_element, MAX(a.datep) AS last_date FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= " WHERE a.elementtype = 'ticket' AND a.fk_element IN (" . implode(',', $this->targetIds) . ')';
        $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';
        $sql .= ' GROUP BY a.fk_element';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $this->setDbError(__METHOD__);
        }
        while ($obj = $this->db->fetch_object($resql)) {
            if (isset($this->tickets[(int) $obj->fk_element])) {
                $this->tickets[(int) $obj->fk_element]->last_activity = (int) $this->db->jdate($obj->last_date);
            }
        }
        $this->db->free($resql);

        return 1;
    }

    /**
     * Spread the time of every customer over its credit periods, oldest first
     *
     * @return void
     */
    protected function computeCredit(): void
    {
        // Every time entry of the customer, whatever the ticket, in chronological order
        $entriesBySoc = [];
        foreach ($this->timeEntries as $ticketId => $entries) {
            $socId = $this->tickets[$ticketId]->fk_soc;
            foreach ($entries as $entry) {
                $entriesBySoc[$socId][] = $entry;
            }
        }

        foreach ($entriesBySoc as $socId => $entries) {
            usort($entries, function ($first, $second) {
                return [$first->date, $first->id] <=> [$second->date, $second->id];
            });
            $society = $this->societies[$socId] ?? null;

            foreach ($entries as $entry) {
                $ticket = $this->tickets[$entry->fk_ticket];
                // Already billed, or not to bill at all : the credit is left for the rest
                if ($entry->invoice_id > 0 || $ticket->billing === self::BILLING_FREE) {
                    continue;
                }
                if ($ticket->billing === self::BILLING_BILLABLE || $society === null || $society->frozen) {
                    $entry->to_bill = $entry->duration;
                    continue;
                }
                $period = $this->getPeriodAt($society, $entry->date);
                if ($period === null) {
                    $entry->to_bill = $entry->duration;
                    continue;
                }
                $left            = max(0, $period->credit - $period->used);
                $entry->included = min($left, $entry->duration);
                $entry->to_bill  = $entry->duration - $entry->included;
                $period->used   += $entry->included;
            }
        }

        // Current period of every customer, the one the card and the list show
        foreach ($this->societies as $society) {
            $society->period = $this->getPeriodAt($society, dol_now());
        }
    }

    /**
     * Credit period covering a date : the latest subscription invoice of the year before
     *
     * @param  stdClass $society Customer
     * @param  int      $date    Date
     * @return stdClass|null
     */
    protected function getPeriodAt(stdClass $society, int $date): ?stdClass
    {
        $found = null;
        foreach ($society->periods as $period) {
            if ($period->start <= $date && $date < $period->end) {
                $found = $period;
            }
        }

        return $found;
    }

    /**
     * Totals of a ticket, and the next action to take on it
     *
     * @param  stdClass $ticket Ticket
     * @return void
     */
    protected function computeTicket(stdClass $ticket): void
    {
        foreach ($this->timeEntries[$ticket->id] ?? [] as $entry) {
            $ticket->time_total += $entry->duration;
            if ($entry->invoice_id > 0) {
                $ticket->time_invoiced += $entry->duration;
            } elseif ($ticket->billing === self::BILLING_FREE) {
                $ticket->time_free += $entry->duration;
            } else {
                $ticket->time_included += $entry->included;
                $ticket->time_to_bill  += $entry->to_bill;
            }
        }
        if ($ticket->time_to_bill > 0 && $ticket->fk_soc > 0) {
            $ticket->amount_to_bill = round(self::roundBillable($ticket->time_to_bill) / 3600 * $this->getRate($ticket->fk_soc), 2);
        }

        $society     = $ticket->fk_soc > 0 ? ($this->societies[$ticket->fk_soc] ?? null) : null;
        $dormantDays = getDolGlobalInt('REEDCRM_TICKET_MONEY_DORMANT_DAYS', 90);
        $lastMove    = max($ticket->datec, $ticket->last_activity);

        $rank = 0;
        if ($ticket->fk_soc <= 0) {
            $ticket->action = self::ACTION_ATTACH_THIRDPARTY;
        } elseif ($society !== null && $society->overdue > 0 && !$ticket->reserved) {
            $rank           = 1;
            $ticket->action = self::ACTION_RELAUNCH;
            $ticket->stake  = $society->overdue;
        } elseif ($ticket->amount_to_bill > 0) {
            $rank           = 2;
            $ticket->action = self::ACTION_BILL;
            $ticket->stake  = $ticket->amount_to_bill;
        } elseif ($ticket->is_open && $ticket->type_code === self::COMMERCIAL_TYPE && empty($ticket->proposal_ids)) {
            $rank           = 3;
            $ticket->action = self::ACTION_QUOTE;
        } elseif ($ticket->is_open && $ticket->fk_project <= 0) {
            $rank           = 4;
            $ticket->action = self::ACTION_ATTACH_PROJECT;
        } elseif ($ticket->is_open && $dormantDays > 0 && $lastMove < dol_now() - $dormantDays * 86400) {
            $rank           = 6;
            $ticket->action = self::ACTION_CLOSE;
        } elseif ($ticket->is_open && $ticket->time_total <= 0) {
            $rank           = 5;
            $ticket->action = self::ACTION_LOG_TIME;
        } else {
            $rank           = 7;
            $ticket->action = self::ACTION_NONE;
        }
        $ticket->action_rank = $rank;
    }

    /**
     * Hourly rate (HT) of a customer, from the billing product and the price rules of the customer
     *
     * @param  int   $socId Customer id
     * @return float        Rate, 0 when the billing product is missing
     */
    protected function getRate(int $socId): float
    {
        global $mysoc;

        if (isset($this->rates[$socId])) {
            return $this->rates[$socId];
        }
        $product = $this->getProduct();
        if (empty($product)) {
            return $this->rates[$socId] = 0.0;
        }
        $rate = $product['price'];
        // Customer prices and price levels only change the rate when the instance uses them
        if (getDolGlobalString('PRODUIT_MULTIPRICES') || getDolGlobalString('PRODUIT_CUSTOMER_PRICES') || getDolGlobalString('PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES')) {
            require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

            $productStatic = new Product($this->db);
            $society       = new Societe($this->db);
            if ($productStatic->fetch($product['id']) > 0 && $society->fetch($socId) > 0) {
                $price = $productStatic->getSellPrice($mysoc, $society);
                $rate  = (float) ($price['pu_ht'] ?? $rate);
            }
        }

        return $this->rates[$socId] = (float) $rate;
    }

    /**
     * Description of the invoice line of a ticket : what was done, and how the time was counted
     *
     * @param  stdClass $ticket        Ticket
     * @param  int      $billedSeconds Time billed, rounded
     * @return string
     */
    protected function getLineDescription(stdClass $ticket, int $billedSeconds): string
    {
        global $langs;

        $desc = $langs->transnoentities('TicketMoneyLineTitle', $ticket->ref, $ticket->subject);
        foreach ($this->timeEntries[$ticket->id] ?? [] as $entry) {
            if ($entry->to_bill <= 0) {
                continue;
            }
            $note  = trim(dol_string_nohtmltag((string) $entry->note));
            $desc .= "\n" . dol_print_date($entry->date, 'day') . ' - ' . self::formatDuration($entry->to_bill) . ($note !== '' ? ' - ' . dol_trunc($note, 120) : '');
        }
        $desc .= "\n" . $langs->transnoentities('TicketMoneyLineCount', self::formatDuration($ticket->time_total), self::formatDuration($ticket->time_included + $ticket->time_invoiced + $ticket->time_free), self::formatDuration($billedSeconds), getDolGlobalInt('REEDCRM_TICKET_MONEY_ROUNDING_MINUTES', 15));

        return $desc;
    }

    /**
     * Mark the billed time entries of a ticket as invoiced. An entry partly covered by the credit is split first :
     * the covered part stays not invoiced so it keeps using the credit, the rest goes on the invoice.
     *
     * @param  stdClass $ticket    Ticket
     * @param  int      $invoiceId Invoice id
     * @param  int      $lineId    Invoice line id
     * @return int                 Negative if KO, positive if OK
     */
    protected function markInvoiced(stdClass $ticket, int $invoiceId, int $lineId): int
    {
        foreach ($this->timeEntries[$ticket->id] ?? [] as $entry) {
            if ($entry->to_bill <= 0 || $entry->invoice_id > 0) {
                continue;
            }
            if ($entry->included > 0) {
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'element_time SET element_duration = ' . ((int) $entry->included) . ' WHERE rowid = ' . ((int) $entry->id);
                if (!$this->db->query($sql)) {
                    return $this->setDbError(__METHOD__);
                }
                $sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'element_time (fk_element, elementtype, element_date, element_datehour, element_date_withhour,';
                $sql .= ' element_duration, fk_product, fk_user, thm, invoice_id, invoice_line_id, import_key, datec, note)';
                $sql .= ' SELECT fk_element, elementtype, element_date, element_datehour, element_date_withhour, ' . ((int) $entry->to_bill) . ',';
                $sql .= ' fk_product, fk_user, thm, ' . $invoiceId . ', ' . $lineId . ', import_key, datec, note';
                $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time WHERE rowid = ' . ((int) $entry->id);
            } else {
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'element_time SET invoice_id = ' . $invoiceId . ', invoice_line_id = ' . $lineId . ' WHERE rowid = ' . ((int) $entry->id);
            }
            if (!$this->db->query($sql)) {
                return $this->setDbError(__METHOD__);
            }
        }

        return 1;
    }

    /**
     * "2 h 15" style duration
     *
     * @param  int    $seconds Duration
     * @return string
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 min';
        }
        $minutes = (int) round($seconds / 60);
        $hours   = intdiv($minutes, 60);
        $minutes = $minutes % 60;
        if ($hours === 0) {
            return $minutes . ' min';
        }

        return $hours . ' h' . ($minutes > 0 ? ' ' . sprintf('%02d', $minutes) : '');
    }

    /**
     * SQL literal of the prefix of the time task label, followed by its space
     *
     * @return string
     */
    protected function getTaskPrefixSql(): string
    {
        return "'" . $this->db->escape(getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps')) . " '";
    }

    /**
     * Keep the database error and log it
     *
     * @param  string $method Method that failed
     * @return int            -1
     */
    protected function setDbError(string $method): int
    {
        $this->error    = $this->db->lasterror();
        $this->errors[] = $this->error;
        dol_syslog($method . ' ' . $this->error, LOG_ERR);

        return -1;
    }
}
