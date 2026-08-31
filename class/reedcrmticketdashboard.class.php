<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/reedcrmticketdashboard.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmTicketDashboard
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

// Load Saturne libraries
require_once __DIR__ . '/../../saturne/class/saturnedashboard.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_function.lib.php';

/**
 * Class for ReedcrmTicketDashboard
 *
 * The dashboard splits its indicators in two families, and every label says which one it belongs to:
 * - flow indicators (created, closed, messages, logged time) only count what happened inside the selected period,
 * - stock indicators (backlog, age, unassigned) always describe the tickets open right now, a backlog has no period.
 */
class ReedcrmTicketDashboard
{
    /**
     * @var int Number of months covered by the monthly graphs
     */
    public const NB_MONTHS_OF_FLOW = 12;

    /**
     * @var int[] Age limits, in days, cutting the open ticket backlog into buckets
     */
    public const BACKLOG_AGE_LIMITS = [1, 3, 7, 30];

    /**
     * @var int[] Duration limits, in days, cutting the resolution times into buckets
     */
    public const RESOLUTION_TIME_LIMITS = [1, 3, 7, 30];

    /**
     * @var int Number of days without any message after which an open ticket is considered dormant
     */
    public const STALE_TICKET_DAYS = 15;

    /**
     * @var string[] Codes of the ticket messages the requester can see, an internal note is not an answer
     */
    public const PUBLIC_MESSAGE_CODES = ['TICKET_MSG', 'TICKET_MSG_SENTBYMAIL'];

    /**
     * @var string[] Codes of every message exchanged on a ticket, private notes included
     */
    public const ALL_MESSAGE_CODES = ['TICKET_MSG', 'TICKET_MSG_PRIVATE', 'TICKET_MSG_SENTBYMAIL'];

    /**
     * @var string Criteria restricting the native ticket list to the open tickets, as the dashboard counters do
     */
    public const OPEN_TICKETS_FILTER = 'search_fk_statut%5B%5D=openall';

    /**
     * @var int[] Statuses of a ticket nobody works on anymore
     */
    public const DONE_STATUS = [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELED];

    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var int Timestamp the period starts at, 0 when the whole history is selected
     */
    protected int $periodStart = 0;

    /**
     * @var string Value of the period filter, in days, 0 meaning the whole history
     */
    protected string $period = '365';

    /**
     * @var int Id of the assignee the dashboard is restricted to, 0 for every assignee
     */
    protected int $filterUserId = 0;

    /**
     * @var int Timestamp the dashboard is built at, kept so every age is measured against the same instant
     */
    protected int $now = 0;

    /**
     * @var array Compact rows of the tickets the dashboard works on, keyed by ticket id
     */
    protected array $tickets = [];

    /**
     * @var array Message counters per ticket id: first internal answer, last message and volumes
     */
    protected array $ticketMessages = [];

    /**
     * @var array Raw message rows, kept to count the exchanges of each user over the period
     */
    protected array $messages = [];

    /**
     * @var array Logged time rows: ticket id, user id, duration and date
     */
    protected array $timeEntries = [];

    /**
     * @var array Total logged time and number of entries per ticket id
     */
    protected array $ticketTime = [];

    /**
     * @var User[] Users already fetched, the same user shows up in most of the indicators
     */
    protected array $userCache = [];

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db  = $db;
        $this->now = dol_now();
    }

    /**
     * Load dashboard info ticket
     *
     * @return array
     * @throws Exception
     */
    public function load_dashboard(): array
    {
        global $langs;

        $this->loadFilters();
        $this->loadTickets();
        $this->loadMessages();
        $this->loadTimeSpent();

        $array = [
            'widgets'        => array_merge(
                $this->getTicketFlowWidget(),
                $this->getTicketDelayWidget(),
                $this->getTicketTimeSpentWidget(),
                $this->getTicketPeopleWidget()
            ),
            'graphs'         => [],
            'lists'          => [],
            'disabledGraphs' => [],
            'graphsFilters'  => $this->getGraphsFilters()
        ];

        // Each entry names the method building it, so a hidden graph costs a lookup instead of its whole computation
        $graphs = [
            'TicketRepartitionPerUser'          => 'getTicketRepartitionPerUser',
            'TicketTimeSpentPerUser'            => 'getTicketTimeSpentPerUser',
            'TicketMessagesPerUser'             => 'getTicketMessagesPerUser',
            'TicketsCreatedVersusClosedByMonth' => 'getTicketsCreatedVersusClosedByMonth',
            'TicketTimeSpentByMonth'            => 'getTicketTimeSpentByMonth',
            'TicketOpenByStatus'                => 'getTicketOpenByStatus',
            'TicketBacklogAge'                  => 'getTicketBacklogAge',
            'TicketResolutionTimeRepartition'   => 'getTicketResolutionTimeRepartition',
            'TicketBySeverity'                  => 'getTicketBySeverity',
            'TicketByType'                      => 'getTicketByType',
            'TicketCreationByWeekday'           => 'getTicketCreationByWeekday',
            'TicketCreationByHour'              => 'getTicketCreationByHour',
            'TopSocietyWithMostOpenTickets'     => 'getTopSocietyWithMostOpenTickets'
        ];

        $lists = [
            'TicketWorkloadPerUserList' => 'getTicketWorkloadPerUserList',
            'TicketOldestOpenList'      => 'getTicketOldestOpenList',
            'TicketStaleList'           => 'getTicketStaleList'
        ];

        $dashboardConfig = json_decode(getDolUserString('REEDCRM_DASHBOARD_CONFIG'));

        foreach ($graphs as $name => $method) {
            if (empty($dashboardConfig->graphs->$name->hide)) {
                $array['graphs'][] = $this->$method();
            } else {
                $array['disabledGraphs'][$name] = $langs->transnoentities($name);
            }
        }

        foreach ($lists as $name => $method) {
            if (empty($dashboardConfig->graphs->$name->hide)) {
                $array['lists'][] = $this->$method();
            } else {
                $array['disabledGraphs'][$name] = $langs->transnoentities($name);
            }
        }

        return $array;
    }

    /**
     * Read the dashboard filters from the user configuration
     *
     * @return void
     */
    protected function loadFilters(): void
    {
        $dashboardConfig = json_decode(getDolUserString('REEDCRM_DASHBOARD_CONFIG'));

        $period = isset($dashboardConfig->filters->ticketPeriod) ? (string) $dashboardConfig->filters->ticketPeriod : '365';
        if (!array_key_exists($period, $this->getPeriodValues())) {
            $period = '365';
        }
        $this->period      = $period;
        $this->periodStart = empty((int) $period) ? 0 : dol_get_first_hour($this->now - (int) $period * 86400);

        $this->filterUserId = isset($dashboardConfig->filters->ticketUser) ? (int) $dashboardConfig->filters->ticketUser : 0;
    }

    /**
     * Get the periods the dashboard can be restricted to
     *
     * @return array Number of days, 0 for the whole history, mapped to its label
     */
    protected function getPeriodValues(): array
    {
        global $langs;

        // The shared dashboard filter sorts its options on their label, descending: the month counts are padded
        // so that sort puts the longest period first instead of scattering the periods over the list
        return [
            '30'  => $langs->transnoentities('TicketPeriodLastMonth'),
            '90'  => $langs->transnoentities('TicketPeriodLastMonths', '03'),
            '180' => $langs->transnoentities('TicketPeriodLastMonths', '06'),
            '365' => $langs->transnoentities('TicketPeriodLastMonths', '12'),
            '730' => $langs->transnoentities('TicketPeriodLastMonths', '24'),
            '0'   => $langs->transnoentities('TicketPeriodAll')
        ];
    }

    /**
     * Get the dashboard filters
     *
     * @return array
     */
    protected function getGraphsFilters(): array
    {
        global $langs;

        // The assignee list is built from the tickets themselves, a user with no ticket has nothing to show
        $assignees = [];
        foreach ($this->tickets as $ticket) {
            if ($ticket->fk_user_assign > 0) {
                $assignees[$ticket->fk_user_assign] = $this->getUser($ticket->fk_user_assign)->getFullName($langs);
            }
        }
        asort($assignees);

        return [
            'ticketPeriod' => [
                'title'        => $langs->transnoentities('TicketPeriodFilter'),
                'type'         => 'selectarray',
                'filter'       => 'ticketPeriod',
                'values'       => $this->getPeriodValues(),
                'currentValue' => $this->period
            ],
            'ticketUser'   => [
                'title'        => $langs->transnoentities('TicketAssigneeFilter'),
                'type'         => 'selectarray',
                'filter'       => 'ticketUser',
                'values'       => [0 => $langs->transnoentities('TicketAllAssignees')] + $assignees,
                'currentValue' => $this->filterUserId
            ]
        ];
    }

    /**
     * Load the tickets the dashboard works on
     *
     * A ticket is kept when it is still open, whatever its age, or when it was created or closed inside the
     * period: those are the only tickets the indicators of the dashboard look at.
     *
     * @return void
     */
    protected function loadTickets(): void
    {
        $sql  = 'SELECT t.rowid, t.ref, t.track_id, t.subject, t.fk_soc, t.fk_project, t.fk_user_create, t.fk_user_assign,';
        $sql .= ' t.fk_statut, t.progress, t.severity_code, t.type_code, t.datec, t.date_read, t.date_close,';
        $sql .= ' s.nom AS societe_name, s.status AS societe_status';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'ticket AS t';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = t.fk_soc';
        $sql .= ' WHERE t.entity IN (' . getEntity('ticket') . ')';
        if (!empty($this->periodStart)) {
            $periodStart = "'" . $this->db->idate($this->periodStart) . "'";
            $sql        .= ' AND (t.fk_statut NOT IN (' . implode(',', self::DONE_STATUS) . ')';
            $sql        .= ' OR t.datec >= ' . $periodStart;
            $sql        .= ' OR t.date_close >= ' . $periodStart . ')';
        }
        if ($this->filterUserId > 0) {
            $sql .= ' AND t.fk_user_assign = ' . $this->filterUserId;
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $obj->rowid          = (int) $obj->rowid;
            $obj->fk_soc         = (int) $obj->fk_soc;
            $obj->fk_user_create = (int) $obj->fk_user_create;
            $obj->fk_user_assign = (int) $obj->fk_user_assign;
            $obj->fk_statut      = (int) $obj->fk_statut;
            $obj->datec          = (int) $this->db->jdate($obj->datec);
            $obj->date_read      = (int) $this->db->jdate($obj->date_read);
            $obj->date_close     = (int) $this->db->jdate($obj->date_close);

            $this->tickets[$obj->rowid] = $obj;
        }
        $this->db->free($resql);
    }

    /**
     * Load the messages exchanged on the tickets
     *
     * @return void
     */
    protected function loadMessages(): void
    {
        if (empty($this->tickets)) {
            return;
        }

        $sql  = 'SELECT a.fk_element, a.code, a.datep, a.fk_user_author';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= " WHERE a.elementtype = 'ticket'";
        $sql .= " AND a.code IN ('" . implode("','", self::ALL_MESSAGE_CODES) . "')";
        $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';
        $sql .= ' AND a.fk_element IN (' . implode(',', array_keys($this->tickets)) . ')';
        $sql .= ' ORDER BY a.datep ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $ticketId = (int) $obj->fk_element;
            $date     = (int) $this->db->jdate($obj->datep);
            $userId   = (int) $obj->fk_user_author;

            if (!isset($this->ticketMessages[$ticketId])) {
                $this->ticketMessages[$ticketId] = ['first_answer' => 0, 'last_message' => 0, 'nb' => 0, 'nb_public' => 0];
            }
            $this->ticketMessages[$ticketId]['nb']++;
            if (in_array($obj->code, self::PUBLIC_MESSAGE_CODES)) {
                $this->ticketMessages[$ticketId]['nb_public']++;
                // Only a message written by an internal user is an answer, a requester message is another question
                if (empty($this->ticketMessages[$ticketId]['first_answer']) && $userId > 0 && $date > $this->tickets[$ticketId]->datec) {
                    $this->ticketMessages[$ticketId]['first_answer'] = $date;
                }
            }
            if ($date > $this->ticketMessages[$ticketId]['last_message']) {
                $this->ticketMessages[$ticketId]['last_message'] = $date;
            }

            $this->messages[] = (object) ['fk_ticket' => $ticketId, 'code' => $obj->code, 'date' => $date, 'fk_user' => $userId];
        }
        $this->db->free($resql);
    }

    /**
     * Load the time logged on the tickets
     *
     * ReedCRM logs the time of a ticket on a task of its project named after the ticket, so the time entries are
     * reached through that task. Only the ticket reference suffix names one task per ticket: with the other
     * suffixes the task is shared by every ticket of the project, so the entries still feed the user and month
     * totals but cannot be attributed to a ticket.
     *
     * @return void
     */
    protected function loadTimeSpent(): void
    {
        $prefix     = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps');
        $suffixType = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref');
        $prefixSql  = "'" . $this->db->escape($prefix) . " '";

        $sql = 'SELECT et.fk_user, et.element_duration AS duration, COALESCE(et.element_datehour, et.element_date) AS datehour,';
        if ($suffixType === 'ticket_ref') {
            $sql .= ' t.rowid AS fk_ticket';
            $sql .= ' FROM ' . MAIN_DB_PREFIX . 'ticket AS t';
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task AS pt ON pt.fk_projet = t.fk_project AND pt.label = CONCAT(' . $prefixSql . ', t.ref)';
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "element_time AS et ON et.elementtype = 'task' AND et.fk_element = pt.rowid";
            $sql .= ' WHERE t.entity IN (' . getEntity('ticket') . ')';
        } else {
            if ($suffixType === 'project_ref') {
                $labelSql = 'CONCAT(' . $prefixSql . ', p.ref)';
            } elseif ($suffixType === 'project_label') {
                $labelSql = 'CONCAT(' . $prefixSql . ', p.title)';
            } else {
                $labelSql = "'" . $this->db->escape($prefix) . "'";
            }
            $sql .= ' 0 AS fk_ticket';
            $sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet AS p';
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task AS pt ON pt.fk_projet = p.rowid AND pt.label = ' . $labelSql;
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "element_time AS et ON et.elementtype = 'task' AND et.fk_element = pt.rowid";
            $sql .= ' WHERE p.entity IN (' . getEntity('project') . ')';
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $ticketId = (int) $obj->fk_ticket;
            // The assignee filter narrows the ticket set, the time of a ticket left out is not the subject anymore
            if ($ticketId > 0 && !isset($this->tickets[$ticketId])) {
                continue;
            }

            $entry = (object) [
                'fk_ticket' => $ticketId,
                'fk_user'   => (int) $obj->fk_user,
                'duration'  => (float) $obj->duration,
                'date'      => (int) $this->db->jdate($obj->datehour)
            ];
            $this->timeEntries[] = $entry;

            if ($ticketId > 0) {
                if (!isset($this->ticketTime[$ticketId])) {
                    $this->ticketTime[$ticketId] = ['duration' => 0, 'nb' => 0];
                }
                $this->ticketTime[$ticketId]['duration'] += $entry->duration;
                $this->ticketTime[$ticketId]['nb']++;
            }
        }
        $this->db->free($resql);
    }

    /**
     * Get the ticket flow of the period and the backlog it leaves behind
     *
     * @return array Widget of the created, closed and still open tickets
     */
    protected function getTicketFlowWidget(): array
    {
        global $form, $langs;

        // Widget title parameters
        $array['title']      = $langs->transnoentities('TicketFlow');
        $array['widgetName'] = 'TicketFlow';
        $array['picto']      = 'fas fa-ticket-alt';
        $array['pictoColor'] = '#0D8AFF';

        // Widget labels parameters
        $array['label'] = [
            $langs->transnoentities('TicketsCreatedOverPeriod'),
            $langs->transnoentities('TicketsClosedOverPeriod'),
            $form->textwithpicto($langs->transnoentities('TicketBalanceOverPeriod'), $langs->transnoentities('TicketBalanceOverPeriodDescription')),
            $langs->transnoentities('NbOfOpenedTicket'),
            $langs->transnoentities('TicketsUnassigned'),
            $langs->transnoentities('TicketsNeverRead')
        ];

        $created    = 0;
        $closed     = 0;
        $open       = 0;
        $unassigned = 0;
        $neverRead  = 0;
        foreach ($this->tickets as $ticket) {
            if ($this->isInPeriod($ticket->datec)) {
                $created++;
            }
            if (!empty($ticket->date_close) && $this->isInPeriod($ticket->date_close)) {
                $closed++;
            }
            if (!$this->isOpenTicket($ticket)) {
                continue;
            }
            $open++;
            if ($ticket->fk_user_assign <= 0) {
                $unassigned++;
            }
            if (empty($ticket->date_read)) {
                $neverRead++;
            }
        }

        $balance = $created - $closed;

        // Widget content parameters
        $array['content'] = [$created, $closed, ($balance > 0 ? '+' : '') . $balance, $open, $unassigned, $neverRead];

        $array['moreContent'] = [
            '',
            '',
            '',
            $this->getTicketListLink(self::OPEN_TICKETS_FILTER),
            $unassigned > 0 ? $this->getTicketListLink(self::OPEN_TICKETS_FILTER . '&search_fk_user_assign=-1') : '',
            $neverRead > 0 ? $this->getTicketListLink('search_fk_statut%5B%5D=' . Ticket::STATUS_NOT_READ) : ''
        ];

        return ['ticketFlow' => $array];
    }

    /**
     * Get the delays measured on the tickets
     *
     * @return array Widget of the response, resolution and backlog delays
     */
    protected function getTicketDelayWidget(): array
    {
        global $form, $langs;

        // Widget title parameters
        $array['title']      = $langs->transnoentities('TicketDelays');
        $array['widgetName'] = 'TicketDelays';
        $array['picto']      = 'fas fa-stopwatch';
        $array['pictoColor'] = '#E9A00D';

        // Widget labels parameters
        $array['label'] = [
            $form->textwithpicto($langs->transnoentities('MeanTakeOverTime'), $langs->transnoentities('MeanTakeOverTimeDescription')),
            $form->textwithpicto($langs->transnoentities('MeanFirstResponseTime'), $langs->transnoentities('MeanFirstResponseTimeDescription')),
            $langs->transnoentities('MedianFirstResponseTime'),
            $form->textwithpicto($langs->transnoentities('MeanResolutionTime'), $langs->transnoentities('MeanResolutionTimeDescription')),
            $langs->transnoentities('MedianResolutionTime'),
            $form->textwithpicto($langs->transnoentities('MeanBacklogAge'), $langs->transnoentities('MeanBacklogAgeDescription')),
            $langs->transnoentities('OldestOpenTicket')
        ];

        $takeOverTimes      = [];
        $firstResponseTimes = [];
        $resolutionTimes    = [];
        $backlogAges        = [];
        $oldestTicket       = null;
        foreach ($this->tickets as $ticket) {
            if (!empty($ticket->date_read) && $ticket->date_read > $ticket->datec && $this->isInPeriod($ticket->datec)) {
                $takeOverTimes[] = $ticket->date_read - $ticket->datec;
            }

            $firstAnswer = $this->ticketMessages[$ticket->rowid]['first_answer'] ?? 0;
            if (!empty($firstAnswer) && $this->isInPeriod($ticket->datec)) {
                $firstResponseTimes[] = $firstAnswer - $ticket->datec;
            }

            // The resolution time is measured on the tickets closed inside the period, the flow of the period
            if (!empty($ticket->date_close) && $this->isInPeriod($ticket->date_close) && $ticket->date_close > $ticket->datec) {
                $resolutionTimes[] = $ticket->date_close - $ticket->datec;
            }

            if ($this->isOpenTicket($ticket)) {
                $backlogAges[] = $this->now - $ticket->datec;
                if (empty($oldestTicket) || $ticket->datec < $oldestTicket->datec) {
                    $oldestTicket = $ticket;
                }
            }
        }

        // Widget content parameters
        $array['content'] = [
            $this->formatDelay($this->mean($takeOverTimes)),
            $this->formatDelay($this->mean($firstResponseTimes)),
            $this->formatDelay($this->median($firstResponseTimes)),
            $this->formatDelay($this->mean($resolutionTimes)),
            $this->formatDelay($this->median($resolutionTimes)),
            $this->formatDelay($this->mean($backlogAges)),
            !empty($oldestTicket) ? $this->formatDelay($this->now - $oldestTicket->datec) : $langs->transnoentities('NoData')
        ];

        $array['moreContent'] = [
            !empty($takeOverTimes) ? ' <span class="opacitymedium">(' . count($takeOverTimes) . ')</span>' : '',
            !empty($firstResponseTimes) ? ' <span class="opacitymedium">(' . count($firstResponseTimes) . ')</span>' : '',
            '',
            !empty($resolutionTimes) ? ' <span class="opacitymedium">(' . count($resolutionTimes) . ')</span>' : '',
            '',
            !empty($backlogAges) ? ' <span class="opacitymedium">(' . count($backlogAges) . ')</span>' : '',
            !empty($oldestTicket) ? ' ' . $this->getTicketObject($oldestTicket)->getNomUrl(1) : ''
        ];

        return ['ticketDelays' => $array];
    }

    /**
     * Get the time logged on the tickets over the period
     *
     * @return array Widget of the logged time volumes
     */
    protected function getTicketTimeSpentWidget(): array
    {
        global $form, $langs;

        // Widget title parameters
        $array['title']      = $langs->transnoentities('TicketTimeSpent');
        $array['widgetName'] = 'TicketTimeSpent';
        $array['picto']      = 'fas fa-hourglass-half';
        $array['pictoColor'] = '#32E592';

        // Widget labels parameters
        $array['label'] = [
            $form->textwithpicto($langs->transnoentities('TotalTimeSpentOverPeriod'), $langs->transnoentities('TotalTimeSpentOverPeriodDescription')),
            $langs->transnoentities('NbOfTimeEntries'),
            $langs->transnoentities('MeanTimePerEntry'),
            $langs->transnoentities('MeanTimePerTicket'),
            $form->textwithpicto($langs->transnoentities('NbOfTicketsWithTime'), $langs->transnoentities('NbOfTicketsWithTimeDescription')),
            $langs->transnoentities('NbOfTimeContributors')
        ];

        $total        = 0;
        $nbEntries    = 0;
        $contributors = [];
        $ticketIds    = [];
        foreach ($this->timeEntries as $entry) {
            if (!$this->isInPeriod($entry->date)) {
                continue;
            }
            $total += $entry->duration;
            $nbEntries++;
            if ($entry->fk_user > 0) {
                $contributors[$entry->fk_user] = 1;
            }
            if ($entry->fk_ticket > 0) {
                $ticketIds[$entry->fk_ticket] = 1;
            }
        }

        // Widget content parameters
        $array['content'] = [
            $nbEntries > 0 ? convertSecondToTime((int) round($total), 'allhourmin') : $langs->transnoentities('NoData'),
            $nbEntries,
            $nbEntries > 0 ? convertSecondToTime((int) round($total / $nbEntries), 'allhourmin') : $langs->transnoentities('NoData'),
            !empty($ticketIds) ? convertSecondToTime((int) round($total / count($ticketIds)), 'allhourmin') : $langs->transnoentities('NoData'),
            count($ticketIds) . ' / ' . count($this->tickets),
            count($contributors)
        ];

        return ['ticketTimeSpent' => $array];
    }

    /**
     * Get the people working on the tickets
     *
     * @return array Widget of the assignees, the exchanges and the third parties
     */
    protected function getTicketPeopleWidget(): array
    {
        global $form, $langs;

        // Widget title parameters
        $array['title']      = $langs->transnoentities('TicketPeople');
        $array['widgetName'] = 'TicketPeople';
        $array['picto']      = 'fas fa-users';
        $array['pictoColor'] = '#A1467E';

        // Widget labels parameters
        $array['label'] = [
            $form->textwithpicto($langs->transnoentities('NbOfAssignees'), $langs->transnoentities('NbOfAssigneesDescription')),
            $langs->transnoentities('MeanOpenTicketPerAssignee'),
            $langs->transnoentities('MostLoadedAssignee'),
            $langs->transnoentities('NbOfExchangesOverPeriod'),
            $langs->transnoentities('MeanExchangePerTicket'),
            $langs->transnoentities('NbOfConcernedSocieties')
        ];

        $openPerUser = [];
        $societies   = [];
        $nbOpen      = 0;
        foreach ($this->tickets as $ticket) {
            if (!$this->isOpenTicket($ticket)) {
                continue;
            }
            $nbOpen++;
            if ($ticket->fk_soc > 0) {
                $societies[$ticket->fk_soc] = 1;
            }
            if ($ticket->fk_user_assign > 0) {
                if (!isset($openPerUser[$ticket->fk_user_assign])) {
                    $openPerUser[$ticket->fk_user_assign] = 0;
                }
                $openPerUser[$ticket->fk_user_assign]++;
            }
        }
        arsort($openPerUser);

        $nbExchanges = 0;
        foreach ($this->messages as $message) {
            if ($this->isInPeriod($message->date)) {
                $nbExchanges++;
            }
        }

        $mostLoadedUserId = empty($openPerUser) ? 0 : (int) array_key_first($openPerUser);

        // Widget content parameters
        $array['content'] = [
            count($openPerUser),
            count($openPerUser) > 0 ? round(array_sum($openPerUser) / count($openPerUser), 1) : 0,
            $mostLoadedUserId > 0 ? $this->getUser($mostLoadedUserId)->getFullName($langs) : $langs->transnoentities('NoData'),
            $nbExchanges,
            count($this->tickets) > 0 ? round(count($this->messages) / count($this->tickets), 1) : 0,
            count($societies)
        ];

        $array['moreContent'] = [
            '',
            '',
            $mostLoadedUserId > 0 ? ' <span class="badge badge-status4">' . $openPerUser[$mostLoadedUserId] . '</span>' : '',
            '',
            '',
            ''
        ];

        return ['ticketPeople' => $array];
    }

    /**
     * Get the ticket repartition per assignee with the resolution time of each of them
     *
     * @return array Graph of open and closed tickets per assignee, and the ticket list link of each bar
     */
    protected function getTicketRepartitionPerUser(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketRepartitionPerUser');
        $array['name']  = 'TicketRepartitionPerUser';
        $array['picto'] = 'fontawesome_fa-user-clock_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 4;
        $array['moreCSS']    = 'grid-2';

        // The resolution time series holds days, the unit is carried by the legend as the bars only show raw values
        $array['labels'] = [
            ['label' => $langs->transnoentities('NbOfOpenedTicket'), 'color' => '#0D8AFF'],
            ['label' => $langs->transnoentities('TicketsClosedOverPeriod'), 'color' => '#32E592'],
            ['label' => $langs->transnoentities('MeanResolutionTime') . ' (' . $langs->transnoentities('DurationDays') . ')', 'color' => '#E9A00D']
        ];

        $perUser = [];
        foreach ($this->tickets as $ticket) {
            // An unassigned ticket stores -1 as well as null, and both must stay out of the graph
            if ($ticket->fk_user_assign <= 0) {
                continue;
            }
            $userId = $ticket->fk_user_assign;
            if (!isset($perUser[$userId])) {
                $perUser[$userId] = ['open' => 0, 'closed' => 0, 'resolutionTimes' => []];
            }
            if ($this->isOpenTicket($ticket)) {
                $perUser[$userId]['open']++;
            }
            if (!empty($ticket->date_close) && $this->isInPeriod($ticket->date_close)) {
                $perUser[$userId]['closed']++;
                if ($ticket->date_close > $ticket->datec) {
                    $perUser[$userId]['resolutionTimes'][] = $ticket->date_close - $ticket->datec;
                }
            }
        }

        uasort($perUser, function ($first, $second) {
            return ($second['open'] + $second['closed']) - ($first['open'] + $first['closed']);
        });

        $links = [];
        foreach ($perUser as $userId => $data) {
            $array['data'][] = [
                $this->getUser($userId)->getFullName($langs),
                $data['open'],
                $data['closed'],
                round($this->mean($data['resolutionTimes']) / 86400, 1)
            ];
            $links[] = $this->getTicketListUrl('search_fk_user_assign=' . $userId);
        }

        // A resolution time in days dwarfs a ticket count on a shared scale, so it gets its own Y axis
        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links, 'secondAxisDataset' => 2]);

        return $array;
    }

    /**
     * Get the time logged by each user over the period
     *
     * @return array Graph of the logged time and the number of entries per user
     */
    protected function getTicketTimeSpentPerUser(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketTimeSpentPerUser');
        $array['name']  = 'TicketTimeSpentPerUser';
        $array['picto'] = 'fontawesome_fa-user-clock_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [
            ['label' => $langs->transnoentities('TimeSpentHours'), 'color' => '#32E592'],
            ['label' => $langs->transnoentities('NbOfTimeEntries'), 'color' => '#0D8AFF']
        ];

        $perUser = [];
        foreach ($this->timeEntries as $entry) {
            if ($entry->fk_user <= 0 || !$this->isInPeriod($entry->date)) {
                continue;
            }
            if (!isset($perUser[$entry->fk_user])) {
                $perUser[$entry->fk_user] = ['duration' => 0, 'nb' => 0];
            }
            $perUser[$entry->fk_user]['duration'] += $entry->duration;
            $perUser[$entry->fk_user]['nb']++;
        }

        uasort($perUser, function ($first, $second) {
            return $second['duration'] <=> $first['duration'];
        });

        foreach ($perUser as $userId => $data) {
            $array['data'][] = [$this->getUser($userId)->getFullName($langs), round($data['duration'] / 3600, 1), $data['nb']];
        }

        return $array;
    }

    /**
     * Get the messages written by each user over the period
     *
     * @return array Graph of the public answers and the private notes per user
     */
    protected function getTicketMessagesPerUser(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketMessagesPerUser');
        $array['name']  = 'TicketMessagesPerUser';
        $array['picto'] = 'fontawesome_fa-comments_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [
            ['label' => $langs->transnoentities('TicketPublicMessages'), 'color' => '#0D8AFF'],
            ['label' => $langs->transnoentities('TicketPrivateMessages'), 'color' => '#A1467E']
        ];

        $perUser = [];
        foreach ($this->messages as $message) {
            if ($message->fk_user <= 0 || !$this->isInPeriod($message->date)) {
                continue;
            }
            if (!isset($perUser[$message->fk_user])) {
                $perUser[$message->fk_user] = ['public' => 0, 'private' => 0];
            }
            if (in_array($message->code, self::PUBLIC_MESSAGE_CODES)) {
                $perUser[$message->fk_user]['public']++;
            } else {
                $perUser[$message->fk_user]['private']++;
            }
        }

        uasort($perUser, function ($first, $second) {
            return ($second['public'] + $second['private']) - ($first['public'] + $first['private']);
        });

        foreach ($perUser as $userId => $data) {
            $array['data'][] = [$this->getUser($userId)->getFullName($langs), $data['public'], $data['private']];
        }

        return $array;
    }

    /**
     * Get the number of tickets created and closed for each of the last months
     *
     * @return array Graph of created versus closed tickets per month, and the ticket list link of each bar
     */
    protected function getTicketsCreatedVersusClosedByMonth(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketsCreatedVersusClosedByMonth', self::NB_MONTHS_OF_FLOW);
        $array['name']  = 'TicketsCreatedVersusClosedByMonth';
        $array['picto'] = 'fontawesome_fa-exchange-alt_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [
            ['label' => $langs->transnoentities('TicketsCreated'), 'color' => '#0D8AFF'],
            ['label' => $langs->transnoentities('TicketsClosed'), 'color' => '#32E592']
        ];

        $months = $this->getMonthBuckets();
        foreach ($this->tickets as $ticket) {
            $createdMonth = dol_print_date($ticket->datec, '%Y-%m');
            if (isset($months[$createdMonth])) {
                $months[$createdMonth]['created']++;
            }
            if (!empty($ticket->date_close)) {
                $closedMonth = dol_print_date($ticket->date_close, '%Y-%m');
                if (isset($months[$closedMonth])) {
                    $months[$closedMonth]['closed']++;
                }
            }
        }

        $createdLinks = [];
        $closedLinks  = [];
        foreach ($months as $month) {
            $array['data'][] = [$month['label'], $month['created'] ?? 0, $month['closed'] ?? 0];
            $createdLinks[]  = $this->getTicketListUrl(reedcrm_get_date_range_filter('search_date', $month['start'], $month['end']));
            $closedLinks[]   = $this->getTicketListUrl(reedcrm_get_date_range_filter('search_dateclose', $month['start'], $month['end']));
        }

        // Each series filters on a date of its own, so the links are declared per dataset
        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['datasetLinks' => [$createdLinks, $closedLinks]]);

        return $array;
    }

    /**
     * Get the time logged on the tickets for each of the last months
     *
     * @return array Graph of the logged time per month
     */
    protected function getTicketTimeSpentByMonth(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketTimeSpentByMonth', self::NB_MONTHS_OF_FLOW);
        $array['name']  = 'TicketTimeSpentByMonth';
        $array['picto'] = 'fontawesome_fa-hourglass-half_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [
            ['label' => $langs->transnoentities('TimeSpentHours'), 'color' => '#32E592'],
            ['label' => $langs->transnoentities('NbOfTimeEntries'), 'color' => '#0D8AFF']
        ];

        $months = $this->getMonthBuckets();
        foreach ($this->timeEntries as $entry) {
            $month = dol_print_date($entry->date, '%Y-%m');
            if (!isset($months[$month])) {
                continue;
            }
            $months[$month]['duration'] = ($months[$month]['duration'] ?? 0) + $entry->duration;
            $months[$month]['nb']       = ($months[$month]['nb'] ?? 0) + 1;
        }

        foreach ($months as $month) {
            $array['data'][] = [$month['label'], round(($month['duration'] ?? 0) / 3600, 1), $month['nb'] ?? 0];
        }

        return $array;
    }

    /**
     * Get the repartition of the open tickets over their status
     *
     * @return array Graph of open tickets per status, and the ticket list link of each slice
     */
    protected function getTicketOpenByStatus(): array
    {
        global $conf, $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketOpenByStatus');
        $array['name']  = 'TicketOpenByStatus';
        $array['picto'] = 'fontawesome_fa-chart-pie_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'pie';
        $array['showlegend'] = ($conf->browser->layout ?? '') == 'phone' ? 1 : 2;
        $array['dataset']    = 1;
        $array['moreCSS']    = 'grid-2';

        // labelStatusShort is filled by the constructor, no ticket has to be fetched to read the status labels
        $statusLabels = (new Ticket($this->db))->labelStatusShort;

        $perStatus = [];
        foreach ($this->tickets as $ticket) {
            if (!$this->isOpenTicket($ticket)) {
                continue;
            }
            $perStatus[$ticket->fk_statut] = ($perStatus[$ticket->fk_statut] ?? 0) + 1;
        }

        // Follow the workflow order of the statuses rather than the order the tickets came in
        ksort($perStatus);

        $links = [];
        foreach ($perStatus as $status => $nbTicket) {
            $array['labels'][] = [
                'label' => $langs->transnoentities($statusLabels[$status] ?? 'Unknown'),
                'color' => SaturneDashboard::getColorRange($status)
            ];
            $array['data'][] = $nbTicket;
            $links[]         = $this->getTicketListUrl('search_fk_statut%5B%5D=' . $status);
        }

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links]);

        return $array;
    }

    /**
     * Get the age of the open ticket backlog, split into buckets
     *
     * The buckets are cut on day boundaries because the native ticket list filters dates by day: a bar and the
     * list it opens then hold the same tickets. The age is counted from the creation date for the same reason.
     *
     * @return array Graph of open tickets per age bucket, and the ticket list link of each bar
     */
    protected function getTicketBacklogAge(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketBacklogAge');
        $array['name']  = 'TicketBacklogAge';
        $array['picto'] = 'fontawesome_fa-hourglass-half_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 2;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [['label' => $langs->transnoentities('NbOfOpenedTicket'), 'color' => '#E9A00D']];

        $buckets = $this->getDayBuckets(self::BACKLOG_AGE_LIMITS);
        foreach ($this->tickets as $ticket) {
            if (!$this->isOpenTicket($ticket)) {
                continue;
            }
            foreach ($buckets as $key => $bucket) {
                if ((empty($bucket['from']) || $ticket->datec >= $bucket['from']) && (empty($bucket['to']) || $ticket->datec < $bucket['to'])) {
                    $buckets[$key]['nb']++;
                    break;
                }
            }
        }

        $links = [];
        foreach ($buckets as $bucket) {
            $array['data'][] = [$bucket['label'], $bucket['nb']];
            // The list bound covers the whole day, so the exclusive upper bound of the bucket is the day before
            $links[] = $this->getTicketListUrl(self::OPEN_TICKETS_FILTER . '&' . reedcrm_get_date_range_filter('search_date', $bucket['from'], empty($bucket['to']) ? $this->now : $bucket['to'] - 86400));
        }

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links]);

        return $array;
    }

    /**
     * Get the repartition of the resolution times of the tickets closed over the period
     *
     * @return array Graph of the closed tickets per resolution time bucket
     */
    protected function getTicketResolutionTimeRepartition(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketResolutionTimeRepartition');
        $array['name']  = 'TicketResolutionTimeRepartition';
        $array['picto'] = 'fontawesome_fa-stopwatch_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 2;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [['label' => $langs->transnoentities('TicketsClosedOverPeriod'), 'color' => '#32E592']];

        // The buckets are read as durations here, so they are built on a duration axis instead of a date axis
        $buckets       = [];
        $previousLimit = 0;
        foreach (self::RESOLUTION_TIME_LIMITS as $nbDays) {
            $buckets[] = [
                'label' => empty($previousLimit) ? $langs->transnoentities('DurationUnderDays', $nbDays) : $langs->transnoentities('DurationBetweenDays', $previousLimit, $nbDays),
                'from'  => $previousLimit * 86400,
                'to'    => $nbDays * 86400,
                'nb'    => 0
            ];
            $previousLimit = $nbDays;
        }
        $buckets[] = [
            'label' => $langs->transnoentities('DurationOverDays', $previousLimit),
            'from'  => $previousLimit * 86400,
            'to'    => 0,
            'nb'    => 0
        ];

        foreach ($this->tickets as $ticket) {
            if (empty($ticket->date_close) || !$this->isInPeriod($ticket->date_close) || $ticket->date_close <= $ticket->datec) {
                continue;
            }
            $duration = $ticket->date_close - $ticket->datec;
            foreach ($buckets as $key => $bucket) {
                if ($duration >= $bucket['from'] && (empty($bucket['to']) || $duration < $bucket['to'])) {
                    $buckets[$key]['nb']++;
                    break;
                }
            }
        }

        foreach ($buckets as $bucket) {
            $array['data'][] = [$bucket['label'], $bucket['nb']];
        }

        return $array;
    }

    /**
     * Get the repartition of the open tickets over their severity
     *
     * @return array Graph of open tickets per severity, and the ticket list link of each slice
     */
    protected function getTicketBySeverity(): array
    {
        return $this->getOpenTicketsByDictionary('TicketBySeverity', 'severity_code', 'c_ticket_severity', 'search_fk_severity');
    }

    /**
     * Get the repartition of the open tickets over their type
     *
     * @return array Graph of open tickets per type, and the ticket list link of each slice
     */
    protected function getTicketByType(): array
    {
        return $this->getOpenTicketsByDictionary('TicketByType', 'type_code', 'c_ticket_type', 'search_fk_type');
    }

    /**
     * Get the repartition of the open tickets over a ticket dictionary
     *
     * @param  string $name       Name of the graph, also its translation key
     * @param  string $field      Field of the ticket holding the dictionary code
     * @param  string $table      Dictionary table holding the labels
     * @param  string $listFilter Criteria of the native ticket list filtering on that dictionary
     * @return array              Graph of open tickets per dictionary entry, and the ticket list link of each slice
     */
    protected function getOpenTicketsByDictionary(string $name, string $field, string $table, string $listFilter): array
    {
        global $conf, $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities($name);
        $array['name']  = $name;
        $array['picto'] = 'fontawesome_fa-chart-pie_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'pie';
        $array['showlegend'] = ($conf->browser->layout ?? '') == 'phone' ? 1 : 2;
        $array['dataset']    = 1;
        $array['moreCSS']    = 'grid-2';

        $labels = [];
        $sql    = 'SELECT code, label FROM ' . MAIN_DB_PREFIX . $table;
        $resql  = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $labels[$obj->code] = $obj->label;
            }
            $this->db->free($resql);
        }

        $perCode = [];
        foreach ($this->tickets as $ticket) {
            if (!$this->isOpenTicket($ticket)) {
                continue;
            }
            $code           = empty($ticket->$field) ? '' : $ticket->$field;
            $perCode[$code] = ($perCode[$code] ?? 0) + 1;
        }
        arsort($perCode);

        $index = 0;
        $links = [];
        foreach ($perCode as $code => $nbTicket) {
            $array['labels'][] = [
                'label' => empty($code) ? $langs->transnoentities('Undefined') : $langs->transnoentities($labels[$code] ?? $code),
                'color' => empty($code) ? '#999999' : SaturneDashboard::getColorRange($index)
            ];
            $array['data'][] = $nbTicket;
            $links[]         = empty($code) ? '' : $this->getTicketListUrl(self::OPEN_TICKETS_FILTER . '&' . $listFilter . '=' . urlencode($code));
            $index++;
        }

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links]);

        return $array;
    }

    /**
     * Get the number of tickets created on each day of the week
     *
     * @return array Graph of the created tickets per weekday
     */
    protected function getTicketCreationByWeekday(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketCreationByWeekday');
        $array['name']  = 'TicketCreationByWeekday';
        $array['picto'] = 'fontawesome_fa-calendar-week_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 2;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [['label' => $langs->transnoentities('TicketsCreatedOverPeriod'), 'color' => '#0D8AFF']];

        // Monday first, the week of the service desk rather than the week of the calendar, and dol_getdate
        // numbers the days from Sunday, so its own numbering keys the counters
        $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
        $perDay   = array_fill_keys(array_keys($weekdays), 0);
        foreach ($this->tickets as $ticket) {
            if (!$this->isInPeriod($ticket->datec)) {
                continue;
            }
            $perDay[(int) dol_getdate($ticket->datec)['wday']]++;
        }

        foreach ($weekdays as $key => $weekday) {
            $array['data'][] = [$langs->transnoentities($weekday), $perDay[$key]];
        }

        return $array;
    }

    /**
     * Get the number of tickets created on each hour of the day
     *
     * @return array Graph of the created tickets per hour
     */
    protected function getTicketCreationByHour(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TicketCreationByHour');
        $array['name']  = 'TicketCreationByHour';
        $array['picto'] = 'fontawesome_fa-clock_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 2;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [['label' => $langs->transnoentities('TicketsCreatedOverPeriod'), 'color' => '#0D8AFF']];

        $perHour = array_fill(0, 24, 0);
        foreach ($this->tickets as $ticket) {
            if (!$this->isInPeriod($ticket->datec)) {
                continue;
            }
            $perHour[(int) dol_print_date($ticket->datec, '%H')]++;
        }

        foreach ($perHour as $hour => $nbTicket) {
            $array['data'][] = [sprintf('%02dh', $hour), $nbTicket];
        }

        return $array;
    }

    /**
     * Get the third parties holding the most open tickets
     *
     * @return array Graph of open tickets per third party, and the ticket list link of each bar
     */
    protected function getTopSocietyWithMostOpenTickets(): array
    {
        global $langs;

        $limit = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

        // Graph title parameters
        $array['title'] = $langs->transnoentities('TopSocietyWithMostOpenTickets', $limit);
        $array['name']  = 'TopSocietyWithMostOpenTickets';
        $array['picto'] = 'fontawesome_fa-building_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;
        $array['moreCSS']    = 'grid-2';

        $array['labels'] = [
            ['label' => $langs->transnoentities('NbOfOpenedTicket'), 'color' => '#A1467E'],
            ['label' => $langs->transnoentities('TimeSpentHours'), 'color' => '#32E592']
        ];

        $perSociety = [];
        foreach ($this->tickets as $ticket) {
            if (!$this->isOpenTicket($ticket) || $ticket->fk_soc <= 0) {
                continue;
            }
            if (!isset($perSociety[$ticket->fk_soc])) {
                $perSociety[$ticket->fk_soc] = ['name' => $ticket->societe_name, 'nb' => 0, 'duration' => 0];
            }
            $perSociety[$ticket->fk_soc]['nb']++;
            $perSociety[$ticket->fk_soc]['duration'] += $this->ticketTime[$ticket->rowid]['duration'] ?? 0;
        }

        uasort($perSociety, function ($first, $second) {
            return $second['nb'] - $first['nb'];
        });
        $perSociety = array_slice($perSociety, 0, $limit, true);

        $links = [];
        foreach ($perSociety as $societyId => $data) {
            $array['data'][] = [$data['name'], $data['nb'], round($data['duration'] / 3600, 1)];
            $links[]         = $this->getTicketListUrl(self::OPEN_TICKETS_FILTER . '&search_fk_soc=' . $societyId);
        }

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links]);

        return $array;
    }

    /**
     * Get the workload of every person having tickets assigned
     *
     * @return array List of the assignees with their volumes, their delays and their logged time
     */
    protected function getTicketWorkloadPerUserList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('TicketWorkloadPerUserList');
        $array['name']  = 'TicketWorkloadPerUserList';
        $array['picto'] = 'fontawesome_fa-users_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'                     => 'User',
            'NbOfOpenedTicket'        => 'NbOfOpenedTicket',
            'TicketsClosedOverPeriod' => 'TicketsClosedOverPeriod',
            'TicketsUnread'           => 'TicketsUnread',
            'MeanFirstResponseTime'   => 'MeanFirstResponseTime',
            'MeanResolutionTime'      => 'MeanResolutionTime',
            'TimeSpent'               => 'TimeSpentOnAssignedTickets',
            'MeanTimePerTicket'       => 'MeanTimePerTicket',
            'NbOfExchanges'           => 'NbOfExchanges',
            'LastActivity'            => 'LastActivity'
        ];

        $perUser = [];
        foreach ($this->tickets as $ticket) {
            if ($ticket->fk_user_assign <= 0) {
                continue;
            }
            $userId = $ticket->fk_user_assign;
            if (!isset($perUser[$userId])) {
                $perUser[$userId] = ['open' => 0, 'closed' => 0, 'unread' => 0, 'firstResponses' => [], 'resolutions' => [], 'duration' => 0, 'nbTicketWithTime' => 0, 'nbExchanges' => 0, 'lastActivity' => 0];
            }

            if ($this->isOpenTicket($ticket)) {
                $perUser[$userId]['open']++;
                if (empty($ticket->date_read)) {
                    $perUser[$userId]['unread']++;
                }
            }
            if (!empty($ticket->date_close) && $this->isInPeriod($ticket->date_close)) {
                $perUser[$userId]['closed']++;
                if ($ticket->date_close > $ticket->datec) {
                    $perUser[$userId]['resolutions'][] = $ticket->date_close - $ticket->datec;
                }
            }

            $firstAnswer = $this->ticketMessages[$ticket->rowid]['first_answer'] ?? 0;
            if (!empty($firstAnswer) && $this->isInPeriod($ticket->datec)) {
                $perUser[$userId]['firstResponses'][] = $firstAnswer - $ticket->datec;
            }

            if (!empty($this->ticketTime[$ticket->rowid])) {
                $perUser[$userId]['duration'] += $this->ticketTime[$ticket->rowid]['duration'];
                $perUser[$userId]['nbTicketWithTime']++;
            }
        }

        // The exchanges and the last activity describe the person, not the ticket: they are counted on the author
        foreach ($this->messages as $message) {
            if ($message->fk_user <= 0 || !isset($perUser[$message->fk_user])) {
                continue;
            }
            if ($this->isInPeriod($message->date)) {
                $perUser[$message->fk_user]['nbExchanges']++;
            }
            if ($message->date > $perUser[$message->fk_user]['lastActivity']) {
                $perUser[$message->fk_user]['lastActivity'] = $message->date;
            }
        }

        uasort($perUser, function ($first, $second) {
            return $second['open'] - $first['open'];
        });

        $data = [];
        foreach ($perUser as $userId => $line) {
            $data[$userId] = [
                'Ref'                     => ['value' => $this->getUser($userId)->getNomUrl(-1), 'morecss' => 'left'],
                'NbOfOpenedTicket'        => ['value' => $line['open'] > 0 ? '<a href="' . $this->getTicketListUrl(self::OPEN_TICKETS_FILTER . '&search_fk_user_assign=' . $userId) . '">' . $line['open'] . '</a>' : '0'],
                'TicketsClosedOverPeriod' => ['value' => $line['closed']],
                'TicketsUnread'           => ['value' => $line['unread']],
                'MeanFirstResponseTime'   => ['value' => $this->formatDelay($this->mean($line['firstResponses']))],
                'MeanResolutionTime'      => ['value' => $this->formatDelay($this->mean($line['resolutions']))],
                'TimeSpent'               => ['value' => $line['duration'] > 0 ? convertSecondToTime((int) round($line['duration']), 'allhourmin') : '-'],
                'MeanTimePerTicket'       => ['value' => $line['nbTicketWithTime'] > 0 ? convertSecondToTime((int) round($line['duration'] / $line['nbTicketWithTime']), 'allhourmin') : '-'],
                'NbOfExchanges'           => ['value' => $line['nbExchanges']],
                'LastActivity'            => ['value' => $line['lastActivity'] > 0 ? dol_print_date($line['lastActivity'], 'dayhour') : '-']
            ];
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the oldest open tickets
     *
     * @return array List of the oldest open tickets with their age, their owner and their logged time
     */
    protected function getTicketOldestOpenList(): array
    {
        global $langs;

        $limit = getDolGlobalInt('REEDCRM_TICKET_DASHBOARD_LIST_LIMIT', 10);

        // List title parameters
        $array['title'] = $langs->transnoentities('TicketOldestOpenList', $limit);
        $array['name']  = 'TicketOldestOpenList';
        $array['picto'] = 'fontawesome_fa-hourglass-end_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'          => 'Ref',
            'Subject'      => 'Subject',
            'ThirdParty'   => 'ThirdParty',
            'AssignedUser' => 'AssignedTo',
            'Status'       => 'Status',
            'Age'          => 'TicketAge',
            'LastMessage'  => 'LastMessage',
            'NbMessages'   => 'NbOfExchanges',
            'TimeSpent'    => 'TimeSpent'
        ];

        $openTickets = array_filter($this->tickets, function ($ticket) {
            return $this->isOpenTicket($ticket);
        });
        uasort($openTickets, function ($first, $second) {
            return $first->datec <=> $second->datec;
        });

        $array['data'] = $this->buildTicketListData(array_slice($openTickets, 0, $limit, true));

        return $array;
    }

    /**
     * Get the open tickets nobody wrote on for a while
     *
     * @return array List of the dormant open tickets
     */
    protected function getTicketStaleList(): array
    {
        global $langs;

        $limit     = getDolGlobalInt('REEDCRM_TICKET_DASHBOARD_LIST_LIMIT', 10);
        $staleDays = getDolGlobalInt('REEDCRM_TICKET_DASHBOARD_STALE_DAYS', self::STALE_TICKET_DAYS);

        // List title parameters
        $array['title'] = $langs->transnoentities('TicketStaleList', $limit, $staleDays);
        $array['name']  = 'TicketStaleList';
        $array['picto'] = 'fontawesome_fa-bed_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'          => 'Ref',
            'Subject'      => 'Subject',
            'ThirdParty'   => 'ThirdParty',
            'AssignedUser' => 'AssignedTo',
            'Status'       => 'Status',
            'Age'          => 'TicketAge',
            'LastMessage'  => 'LastMessage',
            'NbMessages'   => 'NbOfExchanges',
            'TimeSpent'    => 'TimeSpent'
        ];

        // A ticket with no message at all is dormant since its creation, the silence is what is measured
        $staleLimit   = $this->now - $staleDays * 86400;
        $staleTickets = array_filter($this->tickets, function ($ticket) use ($staleLimit) {
            if (!$this->isOpenTicket($ticket)) {
                return false;
            }
            $lastActivity = $this->ticketMessages[$ticket->rowid]['last_message'] ?? $ticket->datec;
            return $lastActivity < $staleLimit;
        });
        uasort($staleTickets, function ($first, $second) {
            $firstActivity  = $this->ticketMessages[$first->rowid]['last_message'] ?? $first->datec;
            $secondActivity = $this->ticketMessages[$second->rowid]['last_message'] ?? $second->datec;
            return $firstActivity <=> $secondActivity;
        });

        $array['data'] = $this->buildTicketListData(array_slice($staleTickets, 0, $limit, true));

        return $array;
    }

    /**
     * Build the rows shared by the ticket lists of the dashboard
     *
     * @param  array $tickets Tickets to render, keyed by ticket id
     * @return array          Rows of the dashboard list
     */
    protected function buildTicketListData(array $tickets): array
    {
        global $langs;

        $data = [];
        foreach ($tickets as $ticket) {
            $lastMessage = $this->ticketMessages[$ticket->rowid]['last_message'] ?? 0;

            $data[$ticket->rowid] = [
                'Ref'          => ['value' => $this->getTicketObject($ticket)->getNomUrl(1), 'morecss' => 'left'],
                'Subject'      => ['value' => dol_escape_htmltag(dol_trunc($ticket->subject, 60)), 'morecss' => 'left'],
                'ThirdParty'   => ['value' => $ticket->fk_soc > 0 ? $this->getSocietyObject($ticket)->getNomUrl(1) : '-', 'morecss' => 'left'],
                'AssignedUser' => ['value' => $ticket->fk_user_assign > 0 ? $this->getUser($ticket->fk_user_assign)->getNomUrl(-1) : '<span class="opacitymedium">' . $langs->transnoentities('TicketsUnassigned') . '</span>'],
                'Status'       => ['value' => $this->getTicketObject($ticket)->getLibStatut(3)],
                'Age'          => ['value' => $this->formatDelay($this->now - $ticket->datec)],
                'LastMessage'  => ['value' => $lastMessage > 0 ? dol_print_date($lastMessage, 'dayhour') : '<span class="opacitymedium">' . $langs->transnoentities('None') . '</span>'],
                'NbMessages'   => ['value' => $this->ticketMessages[$ticket->rowid]['nb'] ?? 0],
                'TimeSpent'    => ['value' => !empty($this->ticketTime[$ticket->rowid]) ? convertSecondToTime((int) round($this->ticketTime[$ticket->rowid]['duration']), 'allhourmin') : '-']
            ];
        }

        return $data;
    }

    /**
     * Get the last months, ready to receive the counters of the monthly graphs
     *
     * @return array Months keyed by year and month, holding their label and their bounds
     */
    protected function getMonthBuckets(): array
    {
        $currentMonthStart = dol_get_first_day((int) dol_print_date($this->now, '%Y'), (int) dol_print_date($this->now, '%m'));

        $months = [];
        for ($i = self::NB_MONTHS_OF_FLOW - 1; $i >= 0; $i--) {
            $monthStart = dol_time_plus_duree($currentMonthStart, -$i, 'm');

            $months[dol_print_date($monthStart, '%Y-%m')] = [
                'label'   => dol_print_date($monthStart, '%m/%Y'),
                'start'   => $monthStart,
                'end'     => dol_get_last_day((int) dol_print_date($monthStart, '%Y'), (int) dol_print_date($monthStart, '%m')),
                'created' => 0,
                'closed'  => 0
            ];
        }

        return $months;
    }

    /**
     * Get the age buckets cutting a set of dates on day boundaries
     *
     * @param  array $limits Age limits, in days, in ascending order
     * @return array         Buckets holding their label, their bounds and their counter
     */
    protected function getDayBuckets(array $limits): array
    {
        global $langs;

        $today   = dol_get_first_hour($this->now);
        $buckets = [];

        // The newest bound is included and the oldest excluded, so a date always falls in exactly one bucket
        $previousLimit = 0;
        foreach ($limits as $nbDays) {
            $buckets[] = [
                'label' => empty($previousLimit) ? $langs->transnoentities('DurationUnderDays', $nbDays) : $langs->transnoentities('DurationBetweenDays', $previousLimit, $nbDays),
                'from'  => $today - $nbDays * 86400,
                'to'    => empty($previousLimit) ? 0 : $today - $previousLimit * 86400,
                'nb'    => 0
            ];
            $previousLimit = $nbDays;
        }
        $buckets[] = [
            'label' => $langs->transnoentities('DurationOverDays', $previousLimit),
            'from'  => 0,
            'to'    => $today - $previousLimit * 86400,
            'nb'    => 0
        ];

        return $buckets;
    }

    /**
     * Get the ticket list URL a graph bar links to
     *
     * @param  string $searchFilter Search criteria of the native ticket list, already url encoded
     * @return string               Ticket list URL
     */
    protected function getTicketListUrl(string $searchFilter): string
    {
        return DOL_URL_ROOT . '/ticket/list.php?mainmenu=ticket&' . $searchFilter;
    }

    /**
     * Get the icon linking a widget counter to the ticket list holding the same tickets
     *
     * @param  string $searchFilter Search criteria of the native ticket list, already url encoded
     * @return string               Link to append to the counter
     */
    protected function getTicketListLink(string $searchFilter): string
    {
        global $langs;

        return ' <a href="' . $this->getTicketListUrl($searchFilter) . '">' . img_picto($langs->transnoentities('List'), 'fontawesome_list_fas_#3bbfa8_0.8em') . '</a>';
    }

    /**
     * Get a ticket object able to render itself, without fetching it again
     *
     * @param  stdClass $ticket Compact ticket row loaded by the dashboard
     * @return Ticket           Ticket holding the properties getNomUrl and getLibStatut need
     */
    protected function getTicketObject(stdClass $ticket): Ticket
    {
        $object            = new Ticket($this->db);
        $object->id        = $ticket->rowid;
        $object->ref       = $ticket->ref;
        $object->track_id  = $ticket->track_id;
        $object->subject   = $ticket->subject;
        $object->fk_statut = $ticket->fk_statut;
        // getLibStatut reads status while the rest of Dolibarr still reads fk_statut, both have to hold the status
        $object->status    = $ticket->fk_statut;
        $object->progress  = (int) $ticket->progress;

        return $object;
    }

    /**
     * Get a third party object able to render itself, without fetching it again
     *
     * @param  stdClass $ticket Compact ticket row loaded by the dashboard
     * @return Societe          Third party holding the properties getNomUrl needs
     */
    protected function getSocietyObject(stdClass $ticket): Societe
    {
        $object         = new Societe($this->db);
        $object->id     = $ticket->fk_soc;
        $object->name   = $ticket->societe_name;
        $object->status = (int) $ticket->societe_status;

        return $object;
    }

    /**
     * Get a user, fetched once for the whole dashboard
     *
     * @param  int  $userId Id of the user
     * @return User         Fetched user
     */
    protected function getUser(int $userId): User
    {
        if (!isset($this->userCache[$userId])) {
            $user = new User($this->db);
            $user->fetch($userId);
            $this->userCache[$userId] = $user;
        }

        return $this->userCache[$userId];
    }

    /**
     * Check whether a date falls inside the selected period
     *
     * @param  int  $date Timestamp to check
     * @return bool       True when the whole history is selected or when the date is recent enough
     */
    protected function isInPeriod(int $date): bool
    {
        return empty($this->periodStart) || $date >= $this->periodStart;
    }

    /**
     * Check if a ticket is still open
     *
     * @param  stdClass $ticket Compact ticket row loaded by the dashboard
     * @return bool             True when the ticket is neither closed nor canceled
     */
    protected function isOpenTicket(stdClass $ticket): bool
    {
        return !in_array($ticket->fk_statut, self::DONE_STATUS);
    }

    /**
     * Get the mean of a set of values
     *
     * @param  array $values Values to average
     * @return int           Rounded mean, 0 when there is nothing to average
     */
    protected function mean(array $values): int
    {
        return empty($values) ? 0 : (int) round(array_sum($values) / count($values));
    }

    /**
     * Get the median of a set of values
     *
     * The median is given alongside the mean because a single ticket forgotten for months moves the mean far more
     * than it moves the day to day experience of the team.
     *
     * @param  array $values Values to sort
     * @return int           Median value, 0 when there is nothing to sort
     */
    protected function median(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 ? (int) $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /**
     * Format a delay the way a service desk reads it, in days and hours
     *
     * @param  int    $seconds Delay to format
     * @return string          Formatted delay, or the no data label when there is no delay to show
     */
    protected function formatDelay(int $seconds): string
    {
        global $langs;

        return empty($seconds) ? $langs->transnoentities('NoData') : convertSecondToTime($seconds, 'all');
    }
}
