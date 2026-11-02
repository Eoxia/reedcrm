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
 * \file    class/reedcrmsalesindicators.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmSalesIndicators
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';

// Load Saturne libraries
dol_include_once('/saturne/class/saturnedashboard.class.php');

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_function.lib.php';
require_once __DIR__ . '/../lib/reedcrm_sales_goals.lib.php';

/**
 * Class for ReedcrmSalesIndicators
 *
 * Shared ground of the sales and direction dashboards: both read the same calls, opportunities and proposals, cut
 * them on the same calendar periods and measure them against the same goals, they only differ in the questions they
 * answer. A sales representative wants to know how far they are from today's goal, the management wants to know
 * where the revenue comes from.
 *
 * Each figure is attributed to a user the way Dolibarr attributes it in its own statistics:
 * - a call to the owner of the event (fk_user_action), the person who had the conversation,
 * - an opportunity to its sales representative, or to its creator when it has none,
 * - a proposal and an invoice to their author (fk_user_author).
 */
abstract class ReedcrmSalesIndicators
{
    /**
     * @var string Code of the phone call event type, the one every ReedCRM call screen records
     */
    public const CALL_EVENT_CODE = 'AC_TEL';

    /**
     * @var int Number of months covered by the monthly graphs
     */
    public const NB_MONTHS_OF_FLOW = 12;

    /**
     * @var int Number of days without any event after which an open opportunity has to be followed up
     */
    public const STALE_OPPORTUNITY_DAYS = 30;

    /**
     * @var int Number of rows of the dashboard lists
     */
    public const LIST_LIMIT = 10;

    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Prefix of the filters of the dashboard in the user configuration, every dashboard keeps its own
     */
    protected string $filterPrefix = 'sales';

    /**
     * @var string Value of the period filter
     */
    protected string $period = 'month';

    /**
     * @var int Timestamp the period starts at
     */
    protected int $periodStart = 0;

    /**
     * @var int Timestamp the period ends at, never later than now so a period in progress is measured "to date"
     */
    protected int $periodEnd = 0;

    /**
     * @var int Timestamp the whole period ends at, later than now for a period in progress
     */
    protected int $periodFullEnd = 0;

    /**
     * @var int Timestamp the comparison period starts at, the same span one period earlier
     */
    protected int $comparisonStart = 0;

    /**
     * @var int Timestamp the comparison period ends at
     */
    protected int $comparisonEnd = 0;

    /**
     * @var int Id of the sales representative the dashboard is restricted to, 0 for the whole team
     */
    protected int $filterUserId = 0;

    /**
     * @var int Timestamp the dashboard is built at, kept so every age is measured against the same instant
     */
    protected int $now = 0;

    /**
     * @var User[] Users already fetched, the same user shows up in most of the indicators
     */
    protected array $userCache = [];

    /**
     * @var array|null Ids of the disabled users, read once on first use, null while they have not been read
     */
    protected ?array $disabledUsers = null;

    /**
     * @var int[]|null Ids of the members of the sales team group, null while it has not been read
     */
    protected ?array $teamUserIds = null;

    /**
     * @var int[]|null Ids of the members the team figures add up, null while they have not been worked out
     */
    protected ?array $memberIds = null;

    /**
     * @var array<string,string>|null Code of each opportunity status, keyed by status id, null while not read
     */
    protected ?array $leadStatusCodes = null;

    /**
     * @var array<int,array<string,int>> Number of calls made, keyed by user id then by day (Y-m-d)
     */
    protected array $callsPerUserDay = [];

    /**
     * @var array<int,array<string,int>> Number of incoming calls, keyed by user id then by day (Y-m-d)
     */
    protected array $incomingPerUserDay = [];

    /**
     * @var array Compact rows of the opportunities, keyed by project id
     */
    protected array $opportunities = [];

    /**
     * @var array Compact rows of the proposals, keyed by proposal id
     */
    protected array $proposals = [];

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
     * Load dashboard info
     *
     * @return array
     */
    abstract public function load_dashboard(): array;

    /**
     * Get the periods the dashboard can be restricted to
     *
     * The periods are calendar ones because the goals are: a sales representative is asked for so many signed
     * proposals in the month, not in the last 30 days.
     *
     * @return array Period key mapped to its label
     */
    protected function getPeriodValues(): array
    {
        global $langs;

        return [
            'week'            => $langs->transnoentities('SalesPeriodWeek'),
            'month'           => $langs->transnoentities('SalesPeriodMonth'),
            'previousmonth'   => $langs->transnoentities('SalesPeriodPreviousMonth'),
            'quarter'         => $langs->transnoentities('SalesPeriodQuarter'),
            'previousquarter' => $langs->transnoentities('SalesPeriodPreviousQuarter'),
            'year'            => $langs->transnoentities('SalesPeriodYear'),
            'previousyear'    => $langs->transnoentities('SalesPeriodPreviousYear'),
            '12months'        => $langs->transnoentities('SalesPeriodLastTwelveMonths')
        ];
    }

    /**
     * Read the dashboard filters from the user configuration
     *
     * @param  string $defaultPeriod Period selected when the user never picked one
     * @param  int    $defaultUserId Sales representative selected when the user never picked one, 0 for the team
     * @return void
     */
    protected function loadFilters(string $defaultPeriod, int $defaultUserId): void
    {
        $dashboardConfig = json_decode(getDolUserString('REEDCRM_DASHBOARD_CONFIG'));

        $periodFilter = $this->filterPrefix . 'Period';
        $userFilter   = $this->filterPrefix . 'User';

        $period = isset($dashboardConfig->filters->$periodFilter) ? (string) $dashboardConfig->filters->$periodFilter : $defaultPeriod;
        $this->setPeriod(array_key_exists($period, $this->getPeriodValues()) ? $period : $defaultPeriod);

        $filterUserId = isset($dashboardConfig->filters->$userFilter) ? (int) $dashboardConfig->filters->$userFilter : $defaultUserId;
        // A user disabled since the filter was saved is not offered anymore, the dashboard falls back on the default
        $this->filterUserId = ($filterUserId == 0 || $this->isActiveUser($filterUserId)) ? $filterUserId : $defaultUserId;
    }

    /**
     * Set the period and its comparison period
     *
     * The comparison period has the same span, one period earlier, except for the running year which is compared
     * with the same dates of the previous year: comparing ten months to a whole year would always look like a drop.
     *
     * @param  string $period Period key, one of getPeriodValues()
     * @return void
     */
    protected function setPeriod(string $period): void
    {
        $year         = (int) dol_print_date($this->now, '%Y');
        $month        = (int) dol_print_date($this->now, '%m');
        $monthStart   = dol_get_first_day($year, $month);
        $quarterStart = dol_get_first_day($year, intdiv($month - 1, 3) * 3 + 1);
        $spanUnit     = 'm';

        switch ($period) {
            case 'week':
                $start    = $this->getWeekStart($this->now);
                $span     = 7;
                $spanUnit = 'd';
                break;
            case 'previousmonth':
                $start = dol_time_plus_duree($monthStart, -1, 'm');
                $span  = 1;
                break;
            case 'quarter':
                $start = $quarterStart;
                $span  = 3;
                break;
            case 'previousquarter':
                $start = dol_time_plus_duree($quarterStart, -3, 'm');
                $span  = 3;
                break;
            case 'year':
                // The year is the fiscal one: the management reviews its figures on the closing of the accounts
                $start = $this->getFiscalYearStart();
                $span  = 12;
                break;
            case 'previousyear':
                $start = $this->getFiscalYearStart(1);
                $span  = 12;
                break;
            case '12months':
                $start = dol_time_plus_duree($monthStart, -11, 'm');
                $span  = 12;
                break;
            default:
                $period = 'month';
                $start  = $monthStart;
                $span   = 1;
                break;
        }

        $this->period        = $period;
        $this->periodStart   = (int) $start;
        $this->periodFullEnd = (int) dol_get_last_hour(dol_time_plus_duree(dol_time_plus_duree($start, $span, $spanUnit), -1, 'd'));
        $this->periodEnd     = min($this->periodFullEnd, $this->now);

        // The comparison stops as far into its period as the selected one has gone into its own
        $this->comparisonStart = (int) dol_time_plus_duree($this->periodStart, -$span, $spanUnit);
        $this->comparisonEnd   = (int) dol_time_plus_duree($this->periodEnd, -$span, $spanUnit);
    }

    /**
     * Get the first day of the week a date belongs to
     *
     * @param  int $date Timestamp
     * @return int       Timestamp of the first hour of the first day of the week
     */
    protected function getWeekStart(int $date): int
    {
        $startWeekDay = getDolGlobalInt('MAIN_START_WEEK', 1);
        $weekDay      = (int) dol_print_date($date, '%w', 'tzserver');

        return (int) dol_time_plus_duree(dol_get_first_hour($date), -(($weekDay - $startWeekDay + 7) % 7), 'd');
    }

    /**
     * Get the first day of a fiscal year, the one the company setup starts its accounting year on
     *
     * @param  int $offset Number of fiscal years to go back, 0 for the running one
     * @return int         Timestamp of the first hour of the fiscal year
     */
    protected function getFiscalYearStart(int $offset = 0): int
    {
        $startMonth = getDolGlobalInt('SOCIETE_FISCAL_MONTH_START', 1);
        if ($startMonth < 1 || $startMonth > 12) {
            $startMonth = 1;
        }

        $year = (int) dol_print_date($this->now, '%Y');
        if ((int) dol_print_date($this->now, '%m') < $startMonth) {
            $year--;
        }

        return (int) dol_get_first_day($year - $offset, $startMonth);
    }

    /**
     * Get the label of a fiscal year, its first calendar year, followed by the second one when it spans two
     *
     * @param  int    $offset Number of fiscal years to go back, 0 for the running one
     * @return string         Label of the fiscal year, 2026 or 2026-2027
     */
    protected function getFiscalYearLabel(int $offset = 0): string
    {
        $start     = $this->getFiscalYearStart($offset);
        $firstYear = (int) dol_print_date($start, '%Y');

        return getDolGlobalInt('SOCIETE_FISCAL_MONTH_START', 1) > 1 ? $firstYear . '-' . ($firstYear + 1) : (string) $firstYear;
    }

    /**
     * Get the number of months the selected period covers, the factor of the monthly goals
     *
     * A week is a share of its month, on the working days: a monthly goal is not asked for in full in one week.
     *
     * @return float Number of calendar months of the period
     */
    protected function getPeriodNbMonths(): float
    {
        switch ($this->period) {
            case 'week':
                $monthStart = dol_get_first_day((int) dol_print_date($this->periodStart, '%Y'), (int) dol_print_date($this->periodStart, '%m'));
                $monthDays  = reedcrm_sales_count_working_days($monthStart, dol_get_last_day((int) dol_print_date($monthStart, '%Y'), (int) dol_print_date($monthStart, '%m')));
                return $monthDays > 0 ? reedcrm_sales_count_working_days($this->periodStart, $this->periodFullEnd) / $monthDays : 0;
            case 'quarter':
            case 'previousquarter':
                return 3;
            case 'year':
            case 'previousyear':
            case '12months':
                return 12;
            default:
                return 1;
        }
    }

    /**
     * Get the share of the period already elapsed, to tell an achievement late from one simply in progress
     *
     * @return float Ratio between 0 and 1 of the working days elapsed over the working days of the period
     */
    protected function getPeriodElapsedRatio(): float
    {
        $total = reedcrm_sales_count_working_days($this->periodStart, $this->periodFullEnd);
        if ($total <= 0) {
            return 1;
        }

        return min(1, reedcrm_sales_count_working_days($this->periodStart, $this->periodEnd) / $total);
    }

    /**
     * Check whether a date falls inside the selected period
     *
     * @param  int  $date Timestamp to check
     * @return bool       True when the date is inside the period
     */
    protected function isInPeriod(int $date): bool
    {
        return $date >= $this->periodStart && $date <= $this->periodEnd;
    }

    /**
     * Check whether a date falls inside the comparison period
     *
     * @param  int  $date Timestamp to check
     * @return bool       True when the date is inside the comparison period
     */
    protected function isInComparison(int $date): bool
    {
        return $date >= $this->comparisonStart && $date <= $this->comparisonEnd;
    }

    /**
     * Check whether a figure attributed to a user belongs to the selected scope
     *
     * @param  int  $userId Id of the user the figure is attributed to
     * @return bool         True when the whole team is selected or when the user is the selected one
     */
    protected function isInScope(int $userId): bool
    {
        return $this->filterUserId <= 0 || $userId == $this->filterUserId;
    }

    /**
     * Get the members of the sales team group, empty when the setup names no group
     *
     * @return int[] Ids of the members
     */
    protected function getTeamUserIds(): array
    {
        if (!isset($this->teamUserIds)) {
            $this->teamUserIds = reedcrm_sales_team_get_user_ids($this->db);
        }

        return $this->teamUserIds;
    }

    /**
     * Check whether a user is part of the sales team the people indicators describe
     *
     * Without a team group, anybody still enabled is: the dashboards then list whoever has a sales activity.
     *
     * @param  int  $userId Id of the user
     * @return bool         True when the user belongs to the team
     */
    protected function isTeamMember(int $userId): bool
    {
        if (!$this->isActiveUser($userId)) {
            return false;
        }

        $teamUserIds = $this->getTeamUserIds();

        return empty($teamUserIds) || in_array($userId, $teamUserIds, true);
    }

    /**
     * Get the members of the team the dashboard describes
     *
     * The members are the ones of the sales team group. Without a group, they are the enabled users with a sales
     * activity the dashboard can see: a call over the loaded window, an open opportunity or a proposal.
     *
     * @param  bool  $all True to ignore the selected scope and get every member
     * @return int[]      Ids of the members
     */
    protected function getMemberIds(bool $all = false): array
    {
        if (!isset($this->memberIds)) {
            $memberIds = $this->getTeamUserIds();
            if (empty($memberIds)) {
                $activeIds = array_keys(array_filter($this->callsPerUserDay, function (array $days): bool {
                    return array_sum($days) > 0;
                }));
                foreach ($this->opportunities as $opportunity) {
                    if ($opportunity->open) {
                        $activeIds[] = $opportunity->fk_user;
                    }
                }
                foreach ($this->proposals as $proposal) {
                    $activeIds[] = $proposal->fk_user;
                }
                $memberIds = array_values(array_filter(array_unique($activeIds), function ($userId) {
                    return $this->isActiveUser((int) $userId);
                }));
            }
            $this->memberIds = array_map('intval', $memberIds);
        }

        if (!$all && $this->filterUserId > 0) {
            return [$this->filterUserId];
        }

        return $this->memberIds;
    }

    /**
     * Get the goals of the selected scope, the sum of the goals of the members for the whole team
     *
     * @param  int[]               $memberIds Ids of the members the team goal adds up, used when no user is selected
     * @return array<string,float>            Value of each goal, 0 when the goal is not set
     */
    protected function getScopeGoals(array $memberIds): array
    {
        if ($this->filterUserId > 0) {
            return reedcrm_sales_goals_get($this->filterUserId);
        }

        $goals = array_fill_keys(array_keys(REEDCRM_SALES_GOALS), 0.0);
        foreach ($memberIds as $memberId) {
            foreach (reedcrm_sales_goals_get($memberId) as $goal => $value) {
                $goals[$goal] += $value;
            }
        }

        return $goals;
    }

    /**
     * Get the join resolving the sales representative of a record from its internal contacts
     *
     * The contact types are tried in the given order, the first one set wins. The join exposes them as rep0,
     * rep1... on the alias "sr", ready to be passed to a COALESCE with the fallback column of the record.
     *
     * @param  string   $element  Element of the contact types (project, propal...)
     * @param  string   $idColumn Column holding the id of the record, with its alias
     * @param  string[] $codes    Codes of the internal contact types, by priority
     * @return string             SQL fragment starting with LEFT JOIN
     */
    protected function getSalesRepJoin(string $element, string $idColumn, array $codes): string
    {
        $columns = [];
        foreach (array_values($codes) as $index => $code) {
            $columns[] = "MIN(CASE WHEN tc.code = '" . $this->db->escape($code) . "' THEN ec.fk_socpeople END) AS rep" . $index;
        }

        $sql  = ' LEFT JOIN (SELECT ec.element_id, ' . implode(', ', $columns);
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_contact AS ec';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'c_type_contact AS tc ON tc.rowid = ec.fk_c_type_contact';
        $sql .= " WHERE tc.element = '" . $this->db->escape($element) . "' AND tc.source = 'internal'";
        $sql .= " AND tc.code IN ('" . implode("','", array_map([$this->db, 'escape'], $codes)) . "')";
        $sql .= ' GROUP BY ec.element_id) AS sr ON sr.element_id = ' . $idColumn;

        return $sql;
    }

    /**
     * Load the calls made, counted per user and per day
     *
     * A call counts once it happened: an AC_TEL event in the past that is done or not applicable, or that was
     * recorded after the fact, created after its own date. A call planned ahead stays a to-do until somebody marks
     * it done. The incoming calls Keyyo logs carry the caller in their extra parameters: they are no prospecting
     * effort and are counted apart.
     *
     * @param  int  $from Timestamp of the first day to load
     * @return void
     */
    protected function loadCalls(int $from): void
    {
        $sql  = 'SELECT a.fk_user_action, DATE(a.datep) AS day,';
        $sql .= " SUM(CASE WHEN a.extraparams LIKE '%\"caller\"%' THEN 0 ELSE 1 END) AS nb_outgoing,";
        $sql .= " SUM(CASE WHEN a.extraparams LIKE '%\"caller\"%' THEN 1 ELSE 0 END) AS nb_incoming";
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
        $sql .= " AND a.code = '" . self::CALL_EVENT_CODE . "'";
        $sql .= " AND a.datep >= '" . $this->db->idate($from) . "'";
        $sql .= " AND a.datep <= '" . $this->db->idate($this->now) . "'";
        $sql .= ' AND (a.percent IN (-1, 100) OR a.datec >= a.datep)';
        $sql .= ' GROUP BY a.fk_user_action, DATE(a.datep)';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $userId = (int) $obj->fk_user_action;
            $this->callsPerUserDay[$userId][$obj->day]  = (int) $obj->nb_outgoing;
            $this->incomingPerUserDay[$userId][$obj->day] = (int) $obj->nb_incoming;
        }
        $this->db->free($resql);
    }

    /**
     * Count the calls of the selected scope between two dates
     *
     * @param  int  $start    Timestamp of the first day
     * @param  int  $end      Timestamp of the last day
     * @param  int  $userId   Id of the user, -1 to follow the selected scope, 0 for everybody
     * @param  bool $incoming True to count the incoming calls instead of the calls made
     * @return int            Number of calls
     */
    protected function countCalls(int $start, int $end, int $userId = -1, bool $incoming = false): int
    {
        $firstDay = dol_print_date($start, '%Y-%m-%d', 'tzserver');
        $lastDay  = dol_print_date($end, '%Y-%m-%d', 'tzserver');
        $source   = $incoming ? $this->incomingPerUserDay : $this->callsPerUserDay;

        $nb = 0;
        foreach ($source as $callUserId => $days) {
            if ($userId > 0 ? $callUserId != $userId : ($userId < 0 && !$this->isInScope($callUserId))) {
                continue;
            }
            foreach ($days as $day => $nbCalls) {
                if ($day >= $firstDay && $day <= $lastDay) {
                    $nb += $nbCalls;
                }
            }
        }

        return $nb;
    }

    /**
     * Load the opportunities, whatever their status
     *
     * Winning an opportunity in ReedCRM unchecks "follow an opportunity" on the project and leaves it open, so the
     * won ones are read on their opportunity status as well. The sales representative is the internal contact
     * SALESREPINTERNAL, then the project leader, then the creator. The decision date is the one of the
     * "project closed" event ReedCRM writes when an opportunity is won or lost, the native closing date only
     * exists for the lost ones.
     *
     * @return void
     */
    protected function loadOpportunities(): void
    {
        $sql  = 'SELECT p.rowid, p.ref, p.title, p.fk_soc, s.nom AS soc_name, p.fk_statut, p.fk_opp_status, p.opp_amount, p.opp_percent,';
        $sql .= ' p.datec, p.date_close, p.tms, p.fk_user_creat, pe.opporigin, pe.opprefusal,';
        $sql .= ' COALESCE(sr.rep0, sr.rep1, p.fk_user_creat) AS fk_user';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet AS p';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_extrafields AS pe ON pe.fk_object = p.rowid';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = p.fk_soc';
        $sql .= $this->getSalesRepJoin('project', 'p.rowid', ['SALESREPINTERNAL', 'PROJECTLEADER']);
        $sql .= ' WHERE p.entity IN (' . getEntity('project') . ')';
        $sql .= ' AND (p.usage_opportunity = 1 OR p.fk_opp_status > 0)';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $code = $this->getLeadStatusCode((int) $obj->fk_opp_status);

            $opportunity = (object) [
                'id'            => (int) $obj->rowid,
                'ref'           => $obj->ref,
                'title'         => $obj->title,
                'fk_soc'        => (int) $obj->fk_soc,
                'soc_name'      => (string) $obj->soc_name,
                'fk_statut'     => (int) $obj->fk_statut,
                'fk_opp_status' => (int) $obj->fk_opp_status,
                'code'          => $code,
                'amount'        => (float) $obj->opp_amount,
                'percent'       => (float) $obj->opp_percent,
                'datec'         => (int) $this->db->jdate($obj->datec),
                'date_close'    => (int) $this->db->jdate($obj->date_close),
                'tms'           => (int) $this->db->jdate($obj->tms),
                'fk_user'       => (int) $obj->fk_user,
                'origin'        => (int) $obj->opporigin,
                'refusal'       => (int) $obj->opprefusal,
                'last_event'    => 0,
                'next_event'    => 0,
                'nb_todo'       => 0,
                'decision_date' => 0
            ];

            // The quick creations used to store the PENDING status for a 100 % opportunity: the percentage wins
            $opportunity->won  = $code === 'WON' || $opportunity->percent >= 100;
            $opportunity->lost = !$opportunity->won && ($code === 'LOST' || $opportunity->fk_statut == Project::STATUS_CLOSED);
            $opportunity->open = !$opportunity->won && !$opportunity->lost;

            $this->opportunities[$opportunity->id] = $opportunity;
        }
        $this->db->free($resql);

        $this->loadOpportunityEvents();
    }

    /**
     * Load the agenda of the opportunities: last event, next action and decision date
     *
     * @return void
     */
    protected function loadOpportunityEvents(): void
    {
        if (empty($this->opportunities)) {
            return;
        }

        $now = "'" . $this->db->idate($this->now) . "'";

        $sql  = 'SELECT a.fk_project,';
        $sql .= ' MAX(CASE WHEN a.datep <= ' . $now . ' THEN a.datep END) AS last_event,';
        $sql .= ' MIN(CASE WHEN a.datep > ' . $now . ' AND a.percent >= 0 AND a.percent < 100 THEN a.datep END) AS next_event,';
        $sql .= ' SUM(CASE WHEN a.percent >= 0 AND a.percent < 100 THEN 1 ELSE 0 END) AS nb_todo,';
        $sql .= " MAX(CASE WHEN ae.reedcrm_status_object = 'project_closed' THEN a.datep END) AS decision_date";
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'actioncomm_extrafields AS ae ON ae.fk_object = a.id';
        $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
        $sql .= ' AND a.fk_project IN (' . implode(',', array_keys($this->opportunities)) . ')';
        $sql .= ' GROUP BY a.fk_project';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $opportunity = $this->opportunities[(int) $obj->fk_project] ?? null;
            if ($opportunity === null) {
                continue;
            }
            $opportunity->last_event    = (int) $this->db->jdate($obj->last_event);
            $opportunity->next_event    = (int) $this->db->jdate($obj->next_event);
            $opportunity->nb_todo       = (int) $obj->nb_todo;
            $opportunity->decision_date = (int) $this->db->jdate($obj->decision_date);
        }
        $this->db->free($resql);

        // A won opportunity is never closed natively: without its closing event, its last change is the best guess
        foreach ($this->opportunities as $opportunity) {
            if ($opportunity->won && empty($opportunity->decision_date)) {
                $opportunity->decision_date = $opportunity->tms;
            } elseif ($opportunity->lost) {
                $opportunity->decision_date = $opportunity->date_close ?: ($opportunity->decision_date ?: $opportunity->tms);
            }
        }
    }

    /**
     * Load the proposals still pending or moved inside the window the dashboard looks at
     *
     * The sales representative is the internal contact SALESREPINTERNAL, then the one at the origin of the
     * proposal (SALESREPFOLL), then its author as in the Dolibarr statistics. A proposal is signed on its
     * signature date, the closing date standing in for the proposals signed before that date existed.
     *
     * @param  int  $from Timestamp from which the validated, signed or refused proposals are loaded
     * @return void
     */
    protected function loadProposals(int $from): void
    {
        $from = "'" . $this->db->idate($from) . "'";

        $sql  = 'SELECT pr.rowid, pr.ref, pr.fk_soc, s.nom AS soc_name, pr.fk_statut, pr.datec, pr.date_valid, pr.date_signature, pr.date_cloture,';
        $sql .= ' pr.total_ht, pr.fk_projet, COALESCE(sr.rep0, sr.rep1, pr.fk_user_author) AS fk_user';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal AS pr';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = pr.fk_soc';
        $sql .= $this->getSalesRepJoin('propal', 'pr.rowid', ['SALESREPINTERNAL', 'SALESREPFOLL']);
        $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ')';
        $sql .= ' AND pr.fk_statut > ' . Propal::STATUS_DRAFT;
        $sql .= ' AND (pr.fk_statut = ' . Propal::STATUS_VALIDATED . ' OR pr.date_valid >= ' . $from;
        $sql .= ' OR pr.date_signature >= ' . $from . ' OR pr.date_cloture >= ' . $from . ')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $status    = (int) $obj->fk_statut;
            $dateClose = (int) $this->db->jdate($obj->date_cloture);
            $signed    = in_array($status, [Propal::STATUS_SIGNED, Propal::STATUS_BILLED]);

            $this->proposals[(int) $obj->rowid] = (object) [
                'id'          => (int) $obj->rowid,
                'ref'         => $obj->ref,
                'fk_soc'      => (int) $obj->fk_soc,
                'soc_name'    => (string) $obj->soc_name,
                'status'      => $status,
                'datec'       => (int) $this->db->jdate($obj->datec),
                'date_valid'  => (int) $this->db->jdate($obj->date_valid),
                'date_signed' => $signed ? ((int) $this->db->jdate($obj->date_signature) ?: $dateClose) : 0,
                'date_refused' => $status == Propal::STATUS_NOTSIGNED ? $dateClose : 0,
                'amount'      => (float) $obj->total_ht,
                'fk_project'  => (int) $obj->fk_projet,
                'fk_user'     => (int) $obj->fk_user
            ];
        }
        $this->db->free($resql);
    }

    /**
     * Get the signed proposals of a user or of the selected scope between two dates
     *
     * @param  int   $start  Timestamp the range starts at
     * @param  int   $end    Timestamp the range ends at
     * @param  int   $userId Id of the user, -1 to follow the selected scope, 0 for everybody
     * @return array         Signed proposals
     */
    protected function getSignedProposals(int $start, int $end, int $userId = -1): array
    {
        return array_filter($this->proposals, function ($proposal) use ($start, $end, $userId) {
            if ($userId > 0 ? $proposal->fk_user != $userId : ($userId < 0 && !$this->isInScope($proposal->fk_user))) {
                return false;
            }
            return !empty($proposal->date_signed) && $proposal->date_signed >= $start && $proposal->date_signed <= $end;
        });
    }

    /**
     * Get the opportunities of the scope created between two dates
     *
     * @param  int   $start Timestamp the range starts at
     * @param  int   $end   Timestamp the range ends at
     * @return array        Opportunities, keyed by project id
     */
    protected function getCreatedOpportunities(int $start, int $end): array
    {
        return array_filter($this->opportunities, function ($opportunity) use ($start, $end) {
            return $this->isInScope($opportunity->fk_user) && $opportunity->datec >= $start && $opportunity->datec <= $end;
        });
    }

    /**
     * Get the proposals of the scope waiting for a signature
     *
     * @return array Validated proposals, keyed by proposal id
     */
    protected function getPendingProposals(): array
    {
        return array_filter($this->proposals, function ($proposal) {
            return $proposal->status == Propal::STATUS_VALIDATED && $this->isInScope($proposal->fk_user);
        });
    }

    /**
     * Build the row of an opportunity, shared by the opportunity lists
     *
     * @param  stdClass $opportunity Compact opportunity row
     * @return array                 Cells of the row
     */
    protected function buildOpportunityRow(stdClass $opportunity): array
    {
        global $langs;

        $project        = new Project($this->db);
        $project->id    = $opportunity->id;
        $project->ref   = $opportunity->ref;
        $project->title = $opportunity->title;

        return [
            'Ref'          => ['value' => $project->getNomUrl(1), 'morecss' => 'left'],
            'Label'        => ['value' => dol_escape_htmltag(dol_trunc($opportunity->title, 40)), 'morecss' => 'left'],
            'ThirdParty'   => ['value' => $this->getSocietyLink($opportunity->fk_soc, $opportunity->soc_name), 'morecss' => 'left'],
            'Status'       => ['value' => !empty($opportunity->code) ? $langs->transnoentities('OppStatus' . $opportunity->code) : '-'],
            'Amount'       => ['value' => $opportunity->amount > 0 ? $this->formatAmount($opportunity->amount) : '-'],
            'Percent'      => ['value' => $opportunity->percent > 0 ? round($opportunity->percent) . '&nbsp;%' : '-'],
            'LastActivity' => ['value' => $opportunity->last_event > 0 ? dol_print_date($opportunity->last_event, 'day') : '<span class="opacitymedium">' . $langs->transnoentities('None') . '</span>'],
            'SalesRep'     => ['value' => $opportunity->fk_user > 0 ? $this->getUser($opportunity->fk_user)->getNomUrl(-1) : '-']
        ];
    }

    /**
     * Get the code of an opportunity status, the codes being stable where the labels are translated
     *
     * @param  int    $statusId Id of the opportunity status
     * @return string           Code of the status (PROSP, QUAL, PROPO, NEGO, WON, LOST...), empty when unknown
     */
    protected function getLeadStatusCode(int $statusId): string
    {
        if (!isset($this->leadStatusCodes)) {
            $this->leadStatusCodes = [];

            $resql = $this->db->query('SELECT rowid, code FROM ' . MAIN_DB_PREFIX . 'c_lead_status');
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $this->leadStatusCodes[(int) $obj->rowid] = (string) $obj->code;
                }
                $this->db->free($resql);
            }
        }

        return $this->leadStatusCodes[$statusId] ?? '';
    }

    /**
     * Get the last months, ready to receive the counters of the monthly graphs
     *
     * @param  int   $nbMonths Number of months, the running one being the last
     * @param  int   $shift    Number of months to move every bucket back, 12 to get the same months one year earlier
     * @return array           Months keyed by year and month, holding their label and their bounds
     */
    protected function getMonthBuckets(int $nbMonths = self::NB_MONTHS_OF_FLOW, int $shift = 0): array
    {
        $currentMonthStart = dol_get_first_day((int) dol_print_date($this->now, '%Y'), (int) dol_print_date($this->now, '%m'));

        $months = [];
        for ($i = $nbMonths - 1; $i >= 0; $i--) {
            $monthStart = dol_time_plus_duree($currentMonthStart, -$i - $shift, 'm');

            $months[dol_print_date($monthStart, '%Y-%m')] = [
                'label' => dol_print_date($monthStart, '%m/%Y'),
                'start' => $monthStart,
                'end'   => dol_get_last_day((int) dol_print_date($monthStart, '%Y'), (int) dol_print_date($monthStart, '%m'))
            ];
        }

        return $months;
    }

    /**
     * Get the progress of a figure against its goal, as a bar followed by the goal
     *
     * A native progress element carries its own proportion, so the bar needs no inline width.
     *
     * @param  float  $value    Figure reached
     * @param  float  $goal     Goal of the figure, 0 when there is none
     * @param  float  $expected Share of the goal expected at this point of the period, 1 when the period is over
     * @param  bool   $isAmount True to format the goal as an amount
     * @return string           HTML of the goal and its progress bar, empty when there is no goal
     */
    protected function getGoalProgress(float $value, float $goal, float $expected = 1, bool $isAmount = false): string
    {
        global $langs;

        if ($goal <= 0) {
            return '';
        }

        $ratio = $value / $goal;
        // A figure ahead of the pace expected today is on track even when the goal is still far
        if ($ratio >= 1) {
            $state = 'reached';
        } elseif ($ratio >= $expected * 0.9) {
            $state = 'ontrack';
        } else {
            $state = 'late';
        }

        $html  = ' <span class="reedcrm-goal">';
        $html .= '<span class="reedcrm-goal__target opacitymedium">/ ' . ($isAmount ? $this->formatAmount($goal) : $this->formatNumber($goal)) . '</span>';
        $html .= '<progress class="reedcrm-goal__bar reedcrm-goal__bar--' . $state . '" max="100" value="' . min(100, (int) round($ratio * 100)) . '"';
        $html .= ' title="' . dol_escape_htmltag($langs->transnoentities('SalesGoalProgress' . ucfirst($state), round($ratio * 100) . ' %')) . '"></progress>';
        $html .= '</span>';

        return $html;
    }

    /**
     * Get the change of a figure against the comparison period, as a colored badge
     *
     * @param  float  $value    Figure of the period
     * @param  float  $previous Figure of the comparison period
     * @return string           HTML of the change, empty when there is nothing to compare with
     */
    protected function getEvolution(float $value, float $previous): string
    {
        global $langs;

        if ($previous == 0) {
            return '';
        }

        $change = round(($value - $previous) / abs($previous) * 100);
        $state  = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');

        return ' <span class="reedcrm-evolution reedcrm-evolution--' . $state . '" title="' . dol_escape_htmltag($langs->transnoentities('SalesComparedWith', dol_print_date($this->comparisonStart, 'day'), dol_print_date($this->comparisonEnd, 'day'))) . '">' . ($change > 0 ? '+' : '') . $change . '&nbsp;%</span>';
    }

    /**
     * Format an amount the way the dashboard shows it, rounded to the unit with the currency
     *
     * @param  float  $amount Amount to format
     * @return string         Formatted amount
     */
    protected function formatAmount(float $amount): string
    {
        global $conf, $langs;

        return price(round($amount), 0, $langs, 1, -1, 0, $conf->currency);
    }

    /**
     * Format a number with at most one decimal, the precision of an average of calls
     *
     * @param  float  $number Number to format
     * @return string         Formatted number
     */
    protected function formatNumber(float $number): string
    {
        global $langs;

        return price(round($number, 1), 0, $langs, 1, -1, floor($number) == $number ? 0 : 1);
    }

    /**
     * Format a ratio as a percentage
     *
     * @param  float  $numerator   Part of the whole
     * @param  float  $denominator Whole
     * @return string              Formatted percentage, the no data label when the whole is empty
     */
    protected function formatRate(float $numerator, float $denominator): string
    {
        global $langs;

        if ($denominator <= 0) {
            return $langs->transnoentities('NoData');
        }

        return round($numerator / $denominator * 100) . '&nbsp;%';
    }

    /**
     * Get the icon linking a widget counter to the list holding the same records
     *
     * @param  string $url URL of the list
     * @return string      Link to append to the counter
     */
    protected function getListLink(string $url): string
    {
        global $langs;

        return ' <a href="' . $url . '" target="_blank">' . img_picto($langs->transnoentities('List'), 'fontawesome_list_fas_#3bbfa8_0.8em') . '</a>';
    }

    /**
     * Check whether a user still works with the module
     *
     * @param  int  $userId Id of the user, 0 or -1 when the record carries none
     * @return bool         True when the user is set and not disabled
     */
    protected function isActiveUser(int $userId): bool
    {
        if (!isset($this->disabledUsers)) {
            $this->loadDisabledUsers();
        }

        return $userId > 0 && !isset($this->disabledUsers[$userId]);
    }

    /**
     * Load the users nobody can log in with anymore
     *
     * @return void
     */
    protected function loadDisabledUsers(): void
    {
        $this->disabledUsers = [];

        $sql  = 'SELECT u.rowid';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'user AS u';
        $sql .= ' WHERE u.entity IN (' . getEntity('user') . ')';
        $sql .= ' AND u.statut = 0';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $this->disabledUsers[(int) $obj->rowid] = 1;
        }
        $this->db->free($resql);
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
     * Get a third party object able to render itself, without fetching it again
     *
     * @param  int    $socId Id of the third party
     * @param  string $name  Name of the third party
     * @return string        Link to the card of the third party, a dash without third party
     */
    protected function getSocietyLink(int $socId, string $name): string
    {
        if ($socId <= 0) {
            return '-';
        }

        $society       = new Societe($this->db);
        $society->id   = $socId;
        $society->name = $name;

        return $society->getNomUrl(1, '', 24);
    }
}
