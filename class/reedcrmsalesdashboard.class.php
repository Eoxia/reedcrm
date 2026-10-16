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
 * \file    class/reedcrmsalesdashboard.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmSalesDashboard
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/reedcrmsalesindicators.class.php';
require_once __DIR__ . '/calllist.class.php';
require_once __DIR__ . '/calllistline.class.php';

/**
 * Class for ReedcrmSalesDashboard
 *
 * The dashboard of the sales team answers one question first: how far am I from my goals, today and over the
 * period. It then shows the pipeline to work on and the actions late, so the effort left is something to do
 * rather than a figure to look at.
 *
 * A sales representative reads their own figures. With the right on the whole team, the dashboard can be read for
 * any member or for the team, which then adds the board comparing the members.
 */
class ReedcrmSalesDashboard extends ReedcrmSalesIndicators
{
    /**
     * @var int Number of working days on the daily call graph
     */
    public const NB_DAYS_OF_CALLS = 20;

    /**
     * @var int Number of weeks on the weekly call graph
     */
    public const NB_WEEKS_OF_CALLS = 12;

    /**
     * @var int Hour the working day starts at, to know how much of today's goal is expected by now
     */
    public const WORKDAY_START_HOUR = 9;

    /**
     * @var int Hour the working day ends at
     */
    public const WORKDAY_END_HOUR = 18;

    /**
     * @var string Prefix of the filters of the dashboard in the user configuration
     */
    protected string $filterPrefix = 'sales';

    /**
     * @var bool Whether the user browsing the dashboard can read the figures of the whole team
     */
    protected bool $canReadTeam = false;

    /**
     * @var array<int,array<string,int>> To-do counters per user: due today, late, without date
     */
    protected array $todoPerUser = [];

    /**
     * @var array Compact rows of the to-do events due today or late, of the selected scope
     */
    protected array $todoRows = [];

    /**
     * @var array<int,int> Number of call list lines left to call, per user the list is assigned to
     */
    protected array $callListLinesPerUser = [];

    /**
     * Load dashboard info
     *
     * @return array
     * @throws Exception
     */
    public function load_dashboard(): array
    {
        global $langs, $user;

        $this->canReadTeam = $user->hasRight('reedcrm', 'dashboard', 'sales_all');
        $this->loadFilters('month', (int) $user->id);
        // Without the right on the team, a sales representative only ever reads their own figures
        if (!$this->canReadTeam) {
            $this->filterUserId = (int) $user->id;
        }

        $monthStart  = dol_get_first_day((int) dol_print_date($this->now, '%Y'), (int) dol_print_date($this->now, '%m'));
        $graphsStart = min(dol_time_plus_duree($this->getWeekStart($this->now), -(self::NB_WEEKS_OF_CALLS - 1), 'w'), $this->getWorkingDays()[0] ?? $this->now);

        $this->loadCalls((int) min($this->periodStart, $graphsStart));
        $this->loadOpportunities();
        $this->loadProposals((int) min($this->periodStart, dol_time_plus_duree($monthStart, -(self::NB_MONTHS_OF_FLOW - 1), 'm')));
        $this->loadTodos();
        $this->loadCallListLines();

        $array = [
            'widgets'        => array_merge(
                $this->getTodayWidget(),
                $this->getGoalsWidget(),
                $this->getPipelineWidget(),
                $this->getConversionWidget()
            ),
            'graphs'         => [],
            'lists'          => [],
            'disabledGraphs' => [],
            'graphsFilters'  => $this->getGraphsFilters()
        ];

        // Each entry names the method building it, so a hidden graph costs a lookup instead of its whole computation
        $graphs = [
            'SalesCallsPerDay'             => 'getCallsPerDay',
            'SalesCallsPerWeek'            => 'getCallsPerWeek',
            'SalesSignaturesPerMonth'      => 'getSignaturesPerMonth',
            'SalesFunnel'                  => 'getFunnel',
            'SalesOpenOpportunitiesStatus' => 'getOpenOpportunitiesByStatus'
        ];

        $lists = [
            'SalesTodoList'               => 'getTodoList',
            'SalesOpportunitiesToFollowUp' => 'getOpportunitiesToFollowUpList',
            'SalesPendingProposals'       => 'getPendingProposalsList'
        ];
        // Comparing the members only makes sense for the whole team, read by somebody allowed to
        if ($this->canReadTeam && $this->filterUserId <= 0) {
            $lists = ['SalesTeamBoard' => 'getTeamBoardList'] + $lists;
        }

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
     * Get the dashboard filters
     *
     * @return array
     */
    protected function getGraphsFilters(): array
    {
        global $langs, $user;

        $filters = [
            'salesPeriod' => [
                'title'        => $langs->transnoentities('SalesPeriodFilter'),
                'type'         => 'selectarray',
                'filter'       => 'salesPeriod',
                'values'       => $this->getPeriodValues(),
                'currentValue' => $this->period
            ]
        ];

        if ($this->canReadTeam) {
            $members = [];
            foreach ($this->getMemberIds(true) as $memberId) {
                $members[$memberId] = $this->getUser($memberId)->getFullName($langs);
            }
            // The user browsing the dashboard is always offered, even before their first call
            if (!isset($members[$user->id])) {
                $members[$user->id] = $user->getFullName($langs);
            }
            asort($members);

            $filters['salesUser'] = [
                'title'        => $langs->transnoentities('SalesUserFilter'),
                'type'         => 'selectarray',
                'filter'       => 'salesUser',
                'values'       => [0 => $langs->transnoentities('SalesWholeTeam')] + $members,
                'currentValue' => $this->filterUserId
            ];
        }

        return $filters;
    }

    /**
     * Load the to-do events of the team: due today, late, or waiting without a date like the relaunches
     *
     * The events Dolibarr logs by itself are left out, they are nothing anybody has to do. The rows of the list
     * are read for the selected scope only, the counters for everybody so the team board can show them.
     *
     * @return void
     */
    protected function loadTodos(): void
    {
        $todayStart = "'" . $this->db->idate(dol_get_first_hour($this->now)) . "'";
        $todayEnd   = "'" . $this->db->idate(dol_get_last_hour($this->now)) . "'";

        $from  = ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $from .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm AS ca ON ca.id = a.fk_action';
        $where  = ' WHERE a.entity IN (' . getEntity('agenda') . ')';
        $where .= ' AND a.percent >= 0 AND a.percent < 100';
        $where .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
        $where .= ' AND (a.datep IS NULL OR a.datep <= ' . $todayEnd . ')';

        $sql  = 'SELECT a.fk_user_action,';
        $sql .= ' SUM(CASE WHEN a.datep >= ' . $todayStart . ' THEN 1 ELSE 0 END) AS nb_today,';
        $sql .= ' SUM(CASE WHEN a.datep < ' . $todayStart . ' THEN 1 ELSE 0 END) AS nb_late,';
        $sql .= ' SUM(CASE WHEN a.datep IS NULL THEN 1 ELSE 0 END) AS nb_undated';
        $sql .= $from . $where;
        $sql .= ' GROUP BY a.fk_user_action';

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $this->todoPerUser[(int) $obj->fk_user_action] = ['today' => (int) $obj->nb_today, 'late' => (int) $obj->nb_late, 'undated' => (int) $obj->nb_undated];
            }
            $this->db->free($resql);
        } else {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }

        // Today's actions come first, then the late ones from the most recent: the oldest are rarely the most urgent
        $sql  = 'SELECT a.id, a.ref, a.label, a.datep, a.code, a.percent, a.fk_user_action, a.fk_soc, s.nom AS soc_name,';
        $sql .= ' a.fk_project, p.ref AS project_ref, p.title AS project_title, ca.code AS type_code, ca.libelle AS type_label';
        $sql .= $from;
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = a.fk_soc';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet AS p ON p.rowid = a.fk_project';
        $sql .= $where;
        $sql .= ' AND a.datep IS NOT NULL';
        if ($this->filterUserId > 0) {
            $sql .= ' AND a.fk_user_action = ' . $this->filterUserId;
        }
        $sql .= ' ORDER BY CASE WHEN a.datep >= ' . $todayStart . ' THEN 0 ELSE 1 END, a.datep DESC';
        $sql .= $this->db->plimit(self::LIST_LIMIT);

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $obj->datep          = (int) $this->db->jdate($obj->datep);
                $obj->fk_user_action = (int) $obj->fk_user_action;
                $this->todoRows[]    = $obj;
            }
            $this->db->free($resql);
        } else {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }
    }

    /**
     * Load the number of call list lines still to call, or to call back, per user the list is assigned to
     *
     * @return void
     */
    protected function loadCallListLines(): void
    {
        $sql  = 'SELECT cl.fk_user_assign, COUNT(l.rowid) AS nb';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_call_list AS cl';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'reedcrm_call_list_line AS l ON l.fk_call_list = cl.rowid';
        $sql .= ' WHERE cl.entity IN (' . getEntity('call_list') . ')';
        $sql .= ' AND cl.status = ' . CallList::STATUS_ACTIVE;
        $sql .= ' AND l.status IN (' . CallListLine::STATUS_TO_CALL . ', ' . CallListLine::STATUS_CALLBACK . ')';
        $sql .= ' GROUP BY cl.fk_user_assign';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $this->callListLinesPerUser[(int) $obj->fk_user_assign] = (int) $obj->nb;
        }
        $this->db->free($resql);
    }

    /**
     * Get the activity of today and of the running week against the call goal
     *
     * @return array Widget of the calls made and of the actions due
     */
    protected function getTodayWidget(): array
    {
        global $form, $langs;

        $goals      = $this->getScopeGoals($this->getMemberIds());
        $todayStart = dol_get_first_hour($this->now);
        $weekStart  = $this->getWeekStart($this->now);
        $weekEnd    = dol_time_plus_duree($weekStart, 6, 'd');

        // On a day off nobody is asked for any call, the counter is shown alone
        $todayGoal    = reedcrm_sales_is_working_day($this->now) ? $goals['calls_per_day'] : 0;
        $weekDays     = reedcrm_sales_count_working_days($weekStart, $weekEnd);
        $weekElapsed  = reedcrm_sales_count_working_days($weekStart, $this->now);
        $weekGoal     = $goals['calls_per_day'] * $weekDays;
        $todayPace    = $this->getWorkdayElapsedRatio();
        $weekPace     = $weekDays > 0 ? max(0, $weekElapsed - 1 + $todayPace) / $weekDays : 1;

        $todo = ['today' => 0, 'late' => 0, 'undated' => 0];
        foreach ($this->todoPerUser as $userId => $counters) {
            if ($this->isInScope($userId)) {
                foreach ($counters as $key => $value) {
                    $todo[$key] += $value;
                }
            }
        }

        $callListLines = 0;
        foreach ($this->callListLinesPerUser as $userId => $nb) {
            if ($this->isInScope($userId)) {
                $callListLines += $nb;
            }
        }

        $callsToday = $this->countCalls($todayStart, $this->now);
        $callsWeek  = $this->countCalls($weekStart, $this->now);

        // Widget title parameters
        $array['title']      = $langs->transnoentities('SalesToday') . $this->getScopeSuffix();
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-phone-alt';
        $array['pictoColor'] = '#0D8AFF';

        // Widget labels parameters
        $array['label'] = [
            $form->textwithpicto($langs->transnoentities('SalesCallsMadeToday'), $langs->transnoentities('SalesCallsMadeDescription')),
            $langs->transnoentities('SalesCallsMadeThisWeek'),
            $form->textwithpicto($langs->transnoentities('SalesIncomingCallsToday'), $langs->transnoentities('SalesIncomingCallsDescription')),
            $langs->transnoentities('SalesActionsDueToday'),
            $langs->transnoentities('SalesActionsLate'),
            $form->textwithpicto($langs->transnoentities('SalesRelaunchesWaiting'), $langs->transnoentities('SalesRelaunchesWaitingDescription')),
            $langs->transnoentities('SalesCallListLinesToCall')
        ];

        $todoUrl = dol_buildpath('/reedcrm/view/todo_list.php', 1);

        // Widget content parameters
        $array['content'] = [
            $callsToday,
            $callsWeek,
            $this->countCalls($todayStart, $this->now, -1, true),
            $todo['today'],
            $todo['late'],
            $todo['undated'],
            $callListLines
        ];
        $array['moreContent'] = [
            $this->getGoalProgress($callsToday, $todayGoal, $todayPace),
            $this->getGoalProgress($callsWeek, $weekGoal, $weekPace),
            '',
            $todo['today'] > 0 ? $this->getListLink($todoUrl) : '',
            $todo['late'] > 0 ? $this->getListLink($todoUrl) : '',
            $todo['undated'] > 0 ? $this->getListLink($todoUrl) : '',
            $callListLines > 0 ? $this->getListLink(dol_buildpath('/saturne/view/saturne_list.php?object_type=call_list', 1)) : ''
        ];

        return ['salesToday' => $array];
    }

    /**
     * Get the figures of the period against their goals, and the effort left to reach them
     *
     * @return array Widget of the goals of the period
     */
    protected function getGoalsWidget(): array
    {
        global $form, $langs;

        $goals       = $this->getScopeGoals($this->getMemberIds());
        $nbMonths    = $this->getPeriodNbMonths();
        $workingDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodFullEnd);
        $elapsedDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodEnd);
        $pace        = $this->getPeriodElapsedRatio();

        $calls         = $this->countCalls($this->periodStart, $this->periodEnd);
        $signed        = $this->getSignedProposals($this->periodStart, $this->periodEnd);
        $nbSigned      = count($signed);
        $signedAmount  = array_sum(array_column($signed, 'amount'));
        $opportunities = count($this->getCreatedOpportunities($this->periodStart, $this->periodEnd));

        $callsGoal         = $goals['calls_per_day'] * $workingDays;
        $signedGoal        = $goals['signed_per_month'] * $nbMonths;
        $signedAmountGoal  = $goals['signed_amount_per_month'] * $nbMonths;
        $opportunitiesGoal = $goals['opportunities_per_month'] * $nbMonths;

        // Widget title parameters
        $array['title']      = $langs->transnoentities('SalesGoalsOfPeriod', dol_strtolower($this->getPeriodValues()[$this->period])) . $this->getScopeSuffix();
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-bullseye';
        $array['pictoColor'] = '#E9A00D';

        $array['label']       = [
            $langs->transnoentities('SalesCallsMade'),
            $form->textwithpicto($langs->transnoentities('SalesCallsPerWorkingDay'), $langs->transnoentities('SalesCallsPerWorkingDayDescription')),
            $form->textwithpicto($langs->transnoentities('SalesProposalsSigned'), $langs->transnoentities('SalesProposalsSignedDescription')),
            $langs->transnoentities('SalesSignedAmount'),
            $langs->transnoentities('SalesOpportunitiesCreated')
        ];
        $array['content']     = [
            $calls,
            $this->formatNumber($elapsedDays > 0 ? $calls / $elapsedDays : 0),
            $nbSigned,
            $this->formatAmount($signedAmount),
            $opportunities
        ];
        $array['moreContent'] = [
            $this->getGoalProgress($calls, $callsGoal, $pace),
            $goals['calls_per_day'] > 0 ? ' <span class="opacitymedium">/ ' . $this->formatNumber($goals['calls_per_day']) . '</span>' : '',
            $this->getGoalProgress($nbSigned, $signedGoal, $pace),
            $this->getGoalProgress($signedAmount, $signedAmountGoal, $pace, true),
            $this->getGoalProgress($opportunities, $opportunitiesGoal, $pace)
        ];

        // The effort left only means something while the period is running and a goal is still out of reach
        $remainingDays = $this->periodFullEnd > $this->now ? reedcrm_sales_count_working_days($this->now, $this->periodFullEnd) : 0;
        if ($remainingDays > 0 && ($callsGoal > $calls || $signedGoal > $nbSigned)) {
            $effort = [];
            if ($callsGoal > $calls) {
                $effort[] = $langs->transnoentities('SalesEffortCallsPerDay', $this->formatNumber(ceil(($callsGoal - $calls) / $remainingDays)));
            }
            if ($signedGoal > $nbSigned) {
                // A share of a monthly goal asks for a whole proposal, nobody signs 0.7 of one
                $effort[] = $langs->transnoentities('SalesEffortProposals', $this->formatNumber(ceil($signedGoal - $nbSigned)));
            }

            $array['label'][]       = $form->textwithpicto($langs->transnoentities('SalesEffortLeft'), $langs->transnoentities('SalesEffortLeftDescription', $remainingDays));
            $array['content'][]     = implode(' · ', $effort);
            $array['moreContent'][] = ' <span class="opacitymedium">(' . $langs->transnoentities('SalesWorkingDaysLeft', $remainingDays) . ')</span>';
        }

        return ['salesGoals' => $array];
    }

    /**
     * Get the pipeline the scope has to work on right now
     *
     * @return array Widget of the open opportunities and of the proposals waiting for a signature
     */
    protected function getPipelineWidget(): array
    {
        global $form, $langs;

        $staleLimit = $this->now - self::STALE_OPPORTUNITY_DAYS * 86400;

        $nbOpen        = 0;
        $openAmount    = 0;
        $weighted      = 0;
        $withoutAction = 0;
        $stale         = 0;
        foreach ($this->opportunities as $opportunity) {
            if (!$opportunity->open || !$this->isInScope($opportunity->fk_user)) {
                continue;
            }
            $nbOpen++;
            $openAmount += $opportunity->amount;
            $weighted   += $opportunity->amount * $opportunity->percent / 100;
            if (empty($opportunity->nb_todo)) {
                $withoutAction++;
            }
            if (max($opportunity->last_event, $opportunity->datec) < $staleLimit) {
                $stale++;
            }
        }

        $pending = $this->getPendingProposals();

        // Widget title parameters
        $array['title']      = $langs->transnoentities('SalesPipeline') . $this->getScopeSuffix();
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-funnel-dollar';
        $array['pictoColor'] = '#32E592';

        $array['label']       = [
            $langs->transnoentities('SalesOpenOpportunities'),
            $form->textwithpicto($langs->transnoentities('SalesWeightedPipeline'), $langs->transnoentities('SalesWeightedPipelineDescription')),
            $langs->transnoentities('SalesProposalsAwaitingSignature'),
            $form->textwithpicto($langs->transnoentities('SalesOpportunitiesWithoutAction'), $langs->transnoentities('SalesOpportunitiesWithoutActionDescription')),
            $langs->transnoentities('SalesStaleOpportunities', self::STALE_OPPORTUNITY_DAYS)
        ];
        $array['content']     = [
            $nbOpen,
            $this->formatAmount($weighted),
            count($pending),
            $withoutAction,
            $stale
        ];
        $array['moreContent'] = [
            ' <span class="opacitymedium">(' . $this->formatAmount($openAmount) . ')</span>' . $this->getListLink(DOL_URL_ROOT . '/projet/list.php?search_opp_status=openedopp'),
            '',
            ' <span class="opacitymedium">(' . $this->formatAmount(array_sum(array_column($pending, 'amount'))) . ')</span>' . (!empty($pending) ? $this->getListLink(DOL_URL_ROOT . '/comm/propal/list.php?search_status=' . Propal::STATUS_VALIDATED) : ''),
            '',
            ''
        ];

        return ['salesPipeline' => $array];
    }

    /**
     * Get how the effort of the period turned into business
     *
     * @return array Widget of the conversion rates of the period
     */
    protected function getConversionWidget(): array
    {
        global $form, $langs;

        $calls   = $this->countCalls($this->periodStart, $this->periodEnd);
        $created = $this->getCreatedOpportunities($this->periodStart, $this->periodEnd);

        // An opportunity turns into a proposal when a proposal points to its project
        $projectsWithProposal = [];
        foreach ($this->proposals as $proposal) {
            if ($proposal->fk_project > 0) {
                $projectsWithProposal[$proposal->fk_project] = 1;
            }
        }
        $createdWithProposal = count(array_intersect_key($created, $projectsWithProposal));

        $won  = 0;
        $lost = 0;
        foreach ($this->opportunities as $opportunity) {
            if (!$this->isInScope($opportunity->fk_user) || !$this->isInPeriod($opportunity->decision_date)) {
                continue;
            }
            if ($opportunity->won) {
                $won++;
            } elseif ($opportunity->lost) {
                $lost++;
            }
        }

        $signed  = $this->getSignedProposals($this->periodStart, $this->periodEnd);
        $refused = array_filter($this->proposals, function ($proposal) {
            return $this->isInScope($proposal->fk_user) && !empty($proposal->date_refused) && $this->isInPeriod($proposal->date_refused);
        });

        $signatureDelays = [];
        foreach ($signed as $proposal) {
            if ($proposal->date_valid > 0 && $proposal->date_signed >= $proposal->date_valid) {
                $signatureDelays[] = $proposal->date_signed - $proposal->date_valid;
            }
        }

        // Widget title parameters
        $array['title']      = $langs->transnoentities('SalesConversion') . $this->getScopeSuffix();
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-exchange-alt';
        $array['pictoColor'] = '#A1467E';

        $array['label']   = [
            $form->textwithpicto($langs->transnoentities('SalesCallsPerOpportunity'), $langs->transnoentities('SalesCallsPerOpportunityDescription')),
            $form->textwithpicto($langs->transnoentities('SalesOpportunityToProposalRate'), $langs->transnoentities('SalesOpportunityToProposalRateDescription')),
            $form->textwithpicto($langs->transnoentities('SalesSignatureRate'), $langs->transnoentities('SalesSignatureRateDescription')),
            $form->textwithpicto($langs->transnoentities('SalesWinRate'), $langs->transnoentities('SalesWinRateDescription')),
            $langs->transnoentities('SalesMeanSignatureDelay'),
            $langs->transnoentities('SalesAverageSignedDeal')
        ];
        $array['content'] = [
            !empty($created) ? $this->formatNumber($calls / count($created)) : $langs->transnoentities('NoData'),
            $this->formatRate($createdWithProposal, count($created)),
            $this->formatRate(count($signed), count($signed) + count($refused)),
            $this->formatRate($won, $won + $lost),
            !empty($signatureDelays) ? $langs->transnoentities('SalesNbDays', round(array_sum($signatureDelays) / count($signatureDelays) / 86400)) : $langs->transnoentities('NoData'),
            !empty($signed) ? $this->formatAmount(array_sum(array_column($signed, 'amount')) / count($signed)) : $langs->transnoentities('NoData')
        ];
        $array['moreContent'] = [
            !empty($created) ? ' <span class="opacitymedium">(' . $calls . ' / ' . count($created) . ')</span>' : '',
            !empty($created) ? ' <span class="opacitymedium">(' . $createdWithProposal . ' / ' . count($created) . ')</span>' : '',
            (count($signed) + count($refused)) > 0 ? ' <span class="opacitymedium">(' . count($signed) . ' / ' . (count($signed) + count($refused)) . ')</span>' : '',
            ($won + $lost) > 0 ? ' <span class="opacitymedium">(' . $won . ' / ' . ($won + $lost) . ')</span>' : '',
            '',
            ''
        ];

        return ['salesConversion' => $array];
    }

    /**
     * Get the calls made on each of the last working days against the daily goal
     *
     * @return array Graph of the calls per working day
     */
    protected function getCallsPerDay(): array
    {
        global $langs;

        $goals = $this->getScopeGoals($this->getMemberIds());

        // Graph title parameters
        $array['title'] = $langs->transnoentities('SalesCallsPerDay') . $this->getScopeSuffix();
        $array['name']  = 'SalesCallsPerDay';
        $array['picto'] = 'fontawesome_fa-phone-alt_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'lines';
        $array['showlegend'] = 1;
        $array['dataset']    = $goals['calls_per_day'] > 0 ? 3 : 2;

        $array['labels'] = [['label' => $langs->transnoentities('SalesCallsMade'), 'color' => '#0D8AFF']];
        if ($goals['calls_per_day'] > 0) {
            $array['labels'][] = ['label' => $langs->transnoentities('SalesGoal'), 'color' => '#E9A00D'];
        }

        foreach ($this->getWorkingDays() as $day) {
            $row = [dol_print_date($day, '%d/%m'), $this->countCalls($day, $day)];
            if ($goals['calls_per_day'] > 0) {
                $row[] = $goals['calls_per_day'];
            }
            $array['data'][] = $row;
        }

        return $array;
    }

    /**
     * Get the calls made on each of the last weeks against the goal of the week
     *
     * @return array Graph of the calls per week
     */
    protected function getCallsPerWeek(): array
    {
        global $langs;

        $goals = $this->getScopeGoals($this->getMemberIds());

        // Graph title parameters
        $array['title'] = $langs->transnoentities('SalesCallsPerWeek') . $this->getScopeSuffix();
        $array['name']  = 'SalesCallsPerWeek';
        $array['picto'] = 'fontawesome_fa-calendar-week_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = $goals['calls_per_day'] > 0 ? 3 : 2;

        $array['labels'] = [['label' => $langs->transnoentities('SalesCallsMade'), 'color' => '#0D8AFF']];
        if ($goals['calls_per_day'] > 0) {
            $array['labels'][] = ['label' => $langs->transnoentities('SalesGoal'), 'color' => '#E9A00D'];
        }

        $currentWeekStart = $this->getWeekStart($this->now);
        for ($i = self::NB_WEEKS_OF_CALLS - 1; $i >= 0; $i--) {
            $weekStart = dol_time_plus_duree($currentWeekStart, -$i, 'w');
            $weekEnd   = dol_get_last_hour(dol_time_plus_duree($weekStart, 6, 'd'));

            $row = [$langs->transnoentities('SalesWeekOf', dol_print_date($weekStart, '%d/%m')), $this->countCalls($weekStart, $weekEnd)];
            if ($goals['calls_per_day'] > 0) {
                $row[] = $goals['calls_per_day'] * reedcrm_sales_count_working_days($weekStart, $weekEnd);
            }
            $array['data'][] = $row;
        }

        return $array;
    }

    /**
     * Get the proposals signed on each of the last months against the monthly goal, with the amount they signed
     *
     * @return array Graph of the signatures per month
     */
    protected function getSignaturesPerMonth(): array
    {
        global $langs;

        $goals = $this->getScopeGoals($this->getMemberIds());

        // Graph title parameters
        $array['title'] = $langs->transnoentities('SalesSignaturesPerMonth') . $this->getScopeSuffix();
        $array['name']  = 'SalesSignaturesPerMonth';
        $array['picto'] = 'fontawesome_fa-file-signature_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 4;

        $array['labels'] = [
            ['label' => $langs->transnoentities('SalesProposalsSigned'), 'color' => '#32E592'],
            ['label' => $langs->transnoentities('SalesGoal'), 'color' => '#E9A00D'],
            ['label' => $langs->transnoentities('SalesSignedAmount'), 'color' => '#A1467E']
        ];

        $links = [];
        foreach ($this->getMonthBuckets() as $month) {
            $signed = $this->getSignedProposals($month['start'], $month['end']);

            $array['data'][] = [$month['label'], count($signed), $goals['signed_per_month'], round(array_sum(array_column($signed, 'amount')))];
            $links[]         = DOL_URL_ROOT . '/comm/propal/list.php?search_status=' . Propal::STATUS_SIGNED . ',' . Propal::STATUS_BILLED . '&' . reedcrm_get_date_range_filter('search_date_signature', $month['start'], $month['end']);
        }

        // An amount dwarfs a number of proposals on a shared scale, so it gets its own Y axis
        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links, 'secondAxisDataset' => 2]);

        return $array;
    }

    /**
     * Get the sales funnel of the period, from the calls to the signatures
     *
     * @return array Graph of the volume of each step of the funnel
     */
    protected function getFunnel(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('SalesFunnel') . $this->getScopeSuffix();
        $array['name']  = 'SalesFunnel';
        $array['picto'] = 'fontawesome_fa-filter_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'horizontalbars';
        $array['showlegend'] = 0;
        $array['dataset']    = 2;

        $array['labels'] = [['label' => $langs->transnoentities('Number'), 'color' => '#0D8AFF']];

        $issued = array_filter($this->proposals, function ($proposal) {
            return $this->isInScope($proposal->fk_user) && $this->isInPeriod($proposal->date_valid);
        });

        $array['data'] = [
            [$langs->transnoentities('SalesCallsMade'), $this->countCalls($this->periodStart, $this->periodEnd)],
            [$langs->transnoentities('SalesOpportunitiesCreated'), count($this->getCreatedOpportunities($this->periodStart, $this->periodEnd))],
            [$langs->transnoentities('SalesProposalsIssued'), count($issued)],
            [$langs->transnoentities('SalesProposalsSigned'), count($this->getSignedProposals($this->periodStart, $this->periodEnd))]
        ];

        return $array;
    }

    /**
     * Get the open opportunities per status, with the amount each status weighs
     *
     * @return array Graph of the open opportunities per status, and the project list link of each bar
     */
    protected function getOpenOpportunitiesByStatus(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('SalesOpenOpportunitiesStatus') . $this->getScopeSuffix();
        $array['name']  = 'SalesOpenOpportunitiesStatus';
        $array['picto'] = 'fontawesome_fa-project-diagram_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;

        $array['labels'] = [
            ['label' => $langs->transnoentities('NbOfOpportunities'), 'color' => '#0D8AFF'],
            ['label' => $langs->transnoentities('SalesWeightedPipeline'), 'color' => '#32E592']
        ];

        $perStatus = [];
        foreach ($this->opportunities as $opportunity) {
            if (!$opportunity->open || !$this->isInScope($opportunity->fk_user)) {
                continue;
            }
            if (!isset($perStatus[$opportunity->fk_opp_status])) {
                $perStatus[$opportunity->fk_opp_status] = ['nb' => 0, 'weighted' => 0];
            }
            $perStatus[$opportunity->fk_opp_status]['nb']++;
            $perStatus[$opportunity->fk_opp_status]['weighted'] += $opportunity->amount * $opportunity->percent / 100;
        }
        ksort($perStatus);

        $links = [];
        foreach ($perStatus as $statusId => $data) {
            $code            = $this->getLeadStatusCode((int) $statusId);
            $label           = !empty($code) ? $langs->transnoentities('OppStatus' . $code) : $langs->transnoentities('NoStatus');
            $array['data'][] = [$label, $data['nb'], round($data['weighted'])];
            $links[]         = DOL_URL_ROOT . '/projet/list.php?search_opp_status=' . ($statusId > 0 ? $statusId : 'none');
        }

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links, 'secondAxisDataset' => 1]);

        return $array;
    }

    /**
     * Get the board comparing the members of the team on the figures their goals are set on
     *
     * @return array List of the members with their activity and their results
     */
    protected function getTeamBoardList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('SalesTeamBoard');
        $array['name']  = 'SalesTeamBoard';
        $array['picto'] = 'fontawesome_fa-users_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'           => 'User',
            'CallsToday'    => 'SalesCallsMadeToday',
            'CallsWeek'     => 'SalesCallsMadeThisWeek',
            'CallsPeriod'   => 'SalesCallsOfPeriod',
            'CallsPerDay'   => 'SalesCallsPerWorkingDay',
            'Signed'        => 'SalesProposalsSigned',
            'SignedAmount'  => 'SalesSignedAmount',
            'Opportunities' => 'SalesOpportunitiesCreated',
            'Weighted'      => 'SalesWeightedPipeline',
            'Late'          => 'SalesActionsLate'
        ];

        $todayStart  = dol_get_first_hour($this->now);
        $weekStart   = $this->getWeekStart($this->now);
        $workingDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodFullEnd);
        $elapsedDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodEnd);
        $nbMonths    = $this->getPeriodNbMonths();
        $pace        = $this->getPeriodElapsedRatio();
        $todayGoal   = reedcrm_sales_is_working_day($this->now);

        $rows = [];
        foreach ($this->getMemberIds(true) as $memberId) {
            $goals  = reedcrm_sales_goals_get($memberId);
            $signed = $this->getSignedProposals($this->periodStart, $this->periodEnd, $memberId);

            $weighted = 0;
            foreach ($this->opportunities as $opportunity) {
                if ($opportunity->open && $opportunity->fk_user == $memberId) {
                    $weighted += $opportunity->amount * $opportunity->percent / 100;
                }
            }
            $nbCreated = 0;
            foreach ($this->opportunities as $opportunity) {
                if ($opportunity->fk_user == $memberId && $this->isInPeriod($opportunity->datec)) {
                    $nbCreated++;
                }
            }

            $rows[$memberId] = [
                'calls'    => $this->countCalls($this->periodStart, $this->periodEnd, $memberId),
                'today'    => $this->countCalls($todayStart, $this->now, $memberId),
                'week'     => $this->countCalls($weekStart, $this->now, $memberId),
                'nbSigned' => count($signed),
                'amount'   => array_sum(array_column($signed, 'amount')),
                'created'  => $nbCreated,
                'weighted' => $weighted,
                'late'     => $this->todoPerUser[$memberId]['late'] ?? 0,
                'goals'    => $goals
            ];
        }

        // The member closest to their call goal comes first: a raw count would favour whoever is asked for more
        uasort($rows, function ($first, $second) use ($workingDays) {
            $firstRatio  = $first['goals']['calls_per_day'] > 0 ? $first['calls'] / ($first['goals']['calls_per_day'] * max(1, $workingDays)) : $first['calls'];
            $secondRatio = $second['goals']['calls_per_day'] > 0 ? $second['calls'] / ($second['goals']['calls_per_day'] * max(1, $workingDays)) : $second['calls'];
            return $secondRatio <=> $firstRatio;
        });

        $data = [];
        foreach ($rows as $memberId => $row) {
            $data[$memberId] = [
                'Ref'           => ['value' => $this->getUser($memberId)->getNomUrl(-1), 'morecss' => 'left'],
                'CallsToday'    => ['value' => $row['today'] . ($todayGoal ? $this->getGoalProgress($row['today'], $row['goals']['calls_per_day'], $this->getWorkdayElapsedRatio()) : '')],
                'CallsWeek'     => ['value' => $row['week']],
                'CallsPeriod'   => ['value' => $row['calls'] . $this->getGoalProgress($row['calls'], $row['goals']['calls_per_day'] * $workingDays, $pace)],
                'CallsPerDay'   => ['value' => $this->formatNumber($elapsedDays > 0 ? $row['calls'] / $elapsedDays : 0)],
                'Signed'        => ['value' => $row['nbSigned'] . $this->getGoalProgress($row['nbSigned'], $row['goals']['signed_per_month'] * $nbMonths, $pace)],
                'SignedAmount'  => ['value' => $this->formatAmount($row['amount'])],
                'Opportunities' => ['value' => $row['created']],
                'Weighted'      => ['value' => $this->formatAmount($row['weighted'])],
                'Late'          => ['value' => $row['late'] > 0 ? '<span class="badge badge-status8">' . $row['late'] . '</span>' : '0']
            ];
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the actions due today and the late ones
     *
     * @return array List of the to-do events of the scope
     */
    protected function getTodoList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('SalesTodoList') . $this->getScopeSuffix();
        $array['name']  = 'SalesTodoList';
        $array['picto'] = 'fontawesome_fa-tasks_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'        => 'Event',
            'Type'       => 'Type',
            'Date'       => 'Date',
            'ThirdParty' => 'ThirdParty',
            'Project'    => 'Project',
            'Owner'      => 'Owner',
            'Delay'      => 'SalesLateBy'
        ];
        if ($this->filterUserId > 0) {
            unset($array['labels']['Owner']);
        }

        $todayStart = dol_get_first_hour($this->now);

        $data = [];
        foreach ($this->todoRows as $todo) {
            $event        = new ActionComm($this->db);
            $event->id    = (int) $todo->id;
            $event->ref   = $todo->ref;
            $event->label = $todo->label;
            $event->type_code = $todo->type_code;

            $project = '-';
            if ($todo->fk_project > 0) {
                $projectObject        = new Project($this->db);
                $projectObject->id    = (int) $todo->fk_project;
                $projectObject->ref   = $todo->project_ref;
                $projectObject->title = $todo->project_title;
                $project              = $projectObject->getNomUrl(1);
            }

            $data[$todo->id] = [
                'Ref'        => ['value' => $event->getNomUrl(0, 40), 'morecss' => 'left'],
                'Type'       => ['value' => dol_escape_htmltag($langs->transnoentities('Action' . $todo->type_code) !== 'Action' . $todo->type_code ? $langs->transnoentities('Action' . $todo->type_code) : (string) $todo->type_label)],
                'Date'       => ['value' => dol_print_date($todo->datep, 'dayhour')],
                'ThirdParty' => ['value' => $this->getSocietyLink((int) $todo->fk_soc, (string) $todo->soc_name), 'morecss' => 'left'],
                'Project'    => ['value' => $project, 'morecss' => 'left'],
                'Owner'      => ['value' => $todo->fk_user_action > 0 ? $this->getUser($todo->fk_user_action)->getNomUrl(-1) : '-'],
                'Delay'      => ['value' => $todo->datep < $todayStart ? '<span class="badge badge-status8">' . $langs->transnoentities('SalesNbDays', (int) floor(($todayStart - $todo->datep) / 86400) + 1) . '</span>' : '<span class="badge badge-status4">' . $langs->transnoentities('Today') . '</span>']
            ];
            if ($this->filterUserId > 0) {
                unset($data[$todo->id]['Owner']);
            }
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the open opportunities nobody plans anything on, the biggest first
     *
     * @return array List of the opportunities to follow up
     */
    protected function getOpportunitiesToFollowUpList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('SalesOpportunitiesToFollowUp') . $this->getScopeSuffix();
        $array['name']  = 'SalesOpportunitiesToFollowUp';
        $array['picto'] = 'fontawesome_fa-bell_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'          => 'Ref',
            'Label'        => 'Label',
            'ThirdParty'   => 'ThirdParty',
            'Status'       => 'OpportunityStatus',
            'Amount'       => 'OpportunityAmount',
            'Percent'      => 'OpportunityProbability',
            'LastActivity' => 'LastActivity',
            'SalesRep'     => 'SalesRepresentative'
        ];
        if ($this->filterUserId > 0) {
            unset($array['labels']['SalesRep']);
        }

        $opportunities = array_filter($this->opportunities, function ($opportunity) {
            return $opportunity->open && empty($opportunity->nb_todo) && $this->isInScope($opportunity->fk_user);
        });
        uasort($opportunities, function ($first, $second) {
            return ($second->amount * max($second->percent, 1)) <=> ($first->amount * max($first->percent, 1));
        });

        $data = [];
        foreach (array_slice($opportunities, 0, self::LIST_LIMIT, true) as $opportunity) {
            $data[$opportunity->id] = $this->buildOpportunityRow($opportunity);
            if ($this->filterUserId > 0) {
                unset($data[$opportunity->id]['SalesRep']);
            }
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the proposals waiting for a signature, the oldest first
     *
     * @return array List of the pending proposals
     */
    protected function getPendingProposalsList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('SalesPendingProposals') . $this->getScopeSuffix();
        $array['name']  = 'SalesPendingProposals';
        $array['picto'] = 'fontawesome_fa-file-contract_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'        => 'Ref',
            'ThirdParty' => 'ThirdParty',
            'Amount'     => 'AmountHT',
            'DateValid'  => 'DateValidation',
            'Age'        => 'SalesWaitingFor',
            'SalesRep'   => 'SalesRepresentative'
        ];
        if ($this->filterUserId > 0) {
            unset($array['labels']['SalesRep']);
        }

        $pending = $this->getPendingProposals();
        uasort($pending, function ($first, $second) {
            return $first->date_valid <=> $second->date_valid;
        });

        $data = [];
        foreach (array_slice($pending, 0, self::LIST_LIMIT, true) as $proposal) {
            $propal         = new Propal($this->db);
            $propal->id     = $proposal->id;
            $propal->ref    = $proposal->ref;
            $propal->statut = $proposal->status;
            $propal->status = $proposal->status;

            $data[$proposal->id] = [
                'Ref'        => ['value' => $propal->getNomUrl(1), 'morecss' => 'left'],
                'ThirdParty' => ['value' => $this->getSocietyLink($proposal->fk_soc, $proposal->soc_name), 'morecss' => 'left'],
                'Amount'     => ['value' => $this->formatAmount($proposal->amount)],
                'DateValid'  => ['value' => $proposal->date_valid > 0 ? dol_print_date($proposal->date_valid, 'day') : '-'],
                'Age'        => ['value' => $proposal->date_valid > 0 ? $langs->transnoentities('SalesNbDays', (int) floor(($this->now - $proposal->date_valid) / 86400)) : '-'],
                'SalesRep'   => ['value' => $proposal->fk_user > 0 ? $this->getUser($proposal->fk_user)->getNomUrl(-1) : '-']
            ];
            if ($this->filterUserId > 0) {
                unset($data[$proposal->id]['SalesRep']);
            }
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the last working days, the oldest first
     *
     * @return int[] Timestamps of the first hour of each working day, today included when it is one
     */
    protected function getWorkingDays(): array
    {
        $days = [];
        $day  = dol_get_first_hour($this->now);
        // A year of days off at most is looked at, the loop cannot spin forever on a misconfigured calendar
        for ($i = 0; $i < 366 && count($days) < self::NB_DAYS_OF_CALLS; $i++) {
            if (reedcrm_sales_is_working_day($day)) {
                $days[] = $day;
            }
            $day = dol_time_plus_duree($day, -1, 'd');
        }

        return array_reverse($days);
    }

    /**
     * Get the share of today's working hours already elapsed
     *
     * @return float Ratio between 0 and 1
     */
    protected function getWorkdayElapsedRatio(): float
    {
        $todayStart = dol_get_first_hour($this->now);
        $dayStart   = $todayStart + self::WORKDAY_START_HOUR * 3600;
        $dayEnd     = $todayStart + self::WORKDAY_END_HOUR * 3600;

        return max(0, min(1, ($this->now - $dayStart) / ($dayEnd - $dayStart)));
    }

    /**
     * Get the suffix naming the person the figures belong to, when it is not the user browsing the dashboard
     *
     * @return string Suffix to append to a title, empty for the user's own figures
     */
    protected function getScopeSuffix(): string
    {
        global $langs, $user;

        if ($this->filterUserId <= 0) {
            return ' - ' . $langs->transnoentities('SalesWholeTeam');
        }
        if ($this->filterUserId != $user->id) {
            return ' - ' . $this->getUser($this->filterUserId)->getFullName($langs);
        }

        return '';
    }
}
