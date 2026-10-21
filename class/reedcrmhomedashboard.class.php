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
 * \file    class/reedcrmhomedashboard.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmHomeDashboard
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/reedcrmdirectiondashboard.class.php';
require_once __DIR__ . '/reedcrmdashboard.class.php';

/**
 * Class for ReedcrmHomeDashboard
 *
 * The home page of the module answers in one screen the questions asked every morning: how much was billed, signed
 * and collected, what the pipeline holds, what was done lately and when each product last sold. Every block links to
 * the list holding the same records: the home page is where the work starts, not where it ends.
 *
 * The figures cover the whole company for the people allowed to read the dashboards of the team, and only their own
 * records for the others, with the attribution rules of the steering dashboards.
 */
class ReedcrmHomeDashboard extends ReedcrmDirectionDashboard
{
    /**
     * @var string[] Periods the home page offers, the calendar month first
     */
    public const HOME_PERIODS = ['month', 'quarter', 'year'];

    /**
     * @var int Number of rows of the short lists (signed proposals, payments)
     */
    public const NB_SHORT_LIST = 5;

    /**
     * @var int Number of events of the last actions
     */
    public const NB_LAST_EVENTS = 8;

    /**
     * @var int Number of products of the last sale lists
     */
    public const NB_PRODUCTS = 8;

    /**
     * @var string[] Types of event a person does by hand, the automatic and module ones being left out
     */
    public const MANUAL_EVENT_TYPES = ['system', 'user'];

    /**
     * @var string Prefix of the filters of the dashboard in the user configuration
     */
    protected string $filterPrefix = 'home';

    /**
     * @var string Scope of the figures: 'all' for the whole company, 'me' for the records of the current user
     */
    protected string $scope = 'me';

    /**
     * @var bool True when the current user may read the figures of the whole company
     */
    protected bool $canReadTeam = false;

    /**
     * Set the period and the scope the home page shows
     *
     * Someone who may not read the dashboards of the team only ever reads their own records, whatever the URL asks.
     *
     * @param  string $period Period key, one of HOME_PERIODS, the month otherwise
     * @param  string $scope  'all' for the whole company, 'me' for the records of the current user
     * @return void
     */
    public function setFilters(string $period, string $scope): void
    {
        global $user;

        $this->canReadTeam = $user->hasRight('reedcrm', 'dashboard', 'direction') || $user->hasRight('reedcrm', 'dashboard', 'sales_all');

        $this->setPeriod(in_array($period, self::HOME_PERIODS) ? $period : 'month');

        $this->scope        = ($this->canReadTeam && $scope !== 'me') ? 'all' : 'me';
        $this->filterUserId = $this->scope === 'me' ? (int) $user->id : 0;
    }

    /**
     * Load dashboard info
     *
     * Each block is only computed when the user may read the records behind it.
     *
     * @return array Data of the home page, read by core/tpl/index/reedcrm_home_dashboard.tpl.php
     * @throws Exception
     */
    public function load_dashboard(): array
    {
        global $user;

        if (empty($this->periodStart)) {
            $this->setFilters('month', 'all');
        }

        $canReadInvoices  = $user->hasRight('facture', 'lire');
        $canReadProposals = $user->hasRight('propal', 'lire');
        $canReadProjects  = $user->hasRight('projet', 'lire');

        $data = [
            'period'          => $this->period,
            'scope'           => $this->scope,
            'canReadTeam'     => $this->canReadTeam,
            'periodStart'     => $this->periodStart,
            'periodEnd'       => $this->periodEnd,
            'comparisonStart' => $this->comparisonStart,
            'comparisonEnd'   => $this->comparisonEnd,
            'kpis'            => [],
            'revenueChart'    => null,
            'pipeline'        => null,
            'signed'          => null,
            'payments'        => null,
            'reminders'       => (new ReedcrmDashboard($this->db))->getUpcomingCallReminders(getDolGlobalInt('REEDCRM_DASHBOARD_UPCOMING_REMINDERS_LIMIT', 5)),
            'lastEvents'      => null,
            'products'        => null
        ];

        if ($canReadInvoices) {
            $data['kpis']['revenue'] = $this->getRevenueKpi();
            $data['revenueChart']    = $this->getRevenueChart();
        }

        if ($canReadProposals) {
            $this->loadProposals((int) min($this->periodStart, $this->comparisonStart));

            $data['kpis']['signed'] = $this->getSignedKpi();
            $data['signed']         = $this->getSignedList();
        }

        if ($canReadProjects) {
            $this->loadOpportunities();

            $data['pipeline']         = $this->getPipeline();
            $data['kpis']['pipeline'] = $this->getPipelineKpi($data['pipeline']);
        }

        if ($canReadInvoices) {
            $this->loadUnpaidInvoices();

            $data['payments']     = $this->getPayments();
            $data['kpis']['cash'] = $this->getCashKpi($data['payments']);
        }

        if ($user->hasRight('agenda', 'myactions', 'read')) {
            $data['lastEvents'] = $this->getLastDoneEvents();
        }

        if ($user->hasRight('produit', 'lire') || $user->hasRight('service', 'lire')) {
            $data['products'] = $this->getProductSales();
        }

        return $data;
    }

    /**
     * Get the revenue billed over the period, compared with the previous one
     *
     * @return array Tile of the revenue
     */
    protected function getRevenueKpi(): array
    {
        global $langs;

        $revenue  = $this->sumBilled($this->periodStart, $this->periodEnd);
        $previous = $this->sumBilled($this->comparisonStart, $this->comparisonEnd);

        // The month and the quarter are read against the fiscal year they belong to, the year against its months
        if ($this->period === 'year') {
            $nbMonths = max(1, (int) dol_print_date($this->periodEnd, '%m') - (int) dol_print_date($this->periodStart, '%m') + 1 + 12 * ((int) dol_print_date($this->periodEnd, '%Y') - (int) dol_print_date($this->periodStart, '%Y')));
            $detail   = $langs->transnoentities('HomeRevenueMonthlyAverage', $this->formatAmount($revenue / $nbMonths));
        } else {
            $fiscalStart = $this->getFiscalYearStart();
            $detail      = $langs->transnoentities('HomeRevenueFiscalYear', $this->getFiscalYearLabel(), $this->formatAmount($this->sumBilled($fiscalStart, $this->now)));
        }

        return [
            'title'     => $langs->transnoentities('HomeRevenue'),
            'icon'      => 'fas fa-euro-sign',
            'tone'      => 'revenue',
            'value'     => $this->formatAmount($revenue),
            'evolution' => $this->getEvolution($revenue, $previous),
            'detail'    => $detail,
            'url'       => DOL_URL_ROOT . '/compta/facture/list.php?' . $this->getDateRangeQuery('search_date_start', 'search_date_end')
        ];
    }

    /**
     * Get the proposals signed over the period, compared with the previous one
     *
     * @return array Tile of the signed proposals
     */
    protected function getSignedKpi(): array
    {
        global $langs;

        $signed   = $this->getSignedProposals($this->periodStart, $this->periodEnd);
        $previous = $this->getSignedProposals($this->comparisonStart, $this->comparisonEnd);
        $pending  = $this->getPendingProposals();

        $amount = array_sum(array_column($signed, 'amount'));

        return [
            'title'     => $langs->transnoentities('HomeSignedProposals'),
            'icon'      => 'fas fa-file-signature',
            'tone'      => 'signed',
            'value'     => $this->formatAmount($amount),
            'evolution' => $this->getEvolution($amount, array_sum(array_column($previous, 'amount'))),
            'detail'    => $langs->transnoentities('HomeSignedDetail', (string) count($signed), (string) count($pending), $this->formatAmount(array_sum(array_column($pending, 'amount')))),
            'url'       => DOL_URL_ROOT . '/comm/propal/list.php?search_status=2,4&' . $this->getDateRangeQuery('search_date_signature_start', 'search_date_signature_end')
        ];
    }

    /**
     * Get the open pipeline as a tile
     *
     * @param  array $pipeline Pipeline per stage, from getPipeline()
     * @return array           Tile of the pipeline
     */
    protected function getPipelineKpi(array $pipeline): array
    {
        global $langs;

        return [
            'title'     => $langs->transnoentities('HomePipeline'),
            'icon'      => 'fas fa-filter',
            'tone'      => 'pipeline',
            'value'     => $this->formatAmount($pipeline['amount']),
            'evolution' => '',
            'detail'    => $langs->transnoentities('HomePipelineDetail', $this->formatAmount($pipeline['weighted']), (string) $pipeline['nb']),
            'url'       => DOL_URL_ROOT . '/projet/list.php?search_opp_status=openedopp'
        ];
    }

    /**
     * Get the payments received over the period as a tile, with what the customers still owe
     *
     * @param  array $payments Payments of the period, from getPayments()
     * @return array           Tile of the collection
     */
    protected function getCashKpi(array $payments): array
    {
        global $langs;

        $outstanding = array_sum(array_column($this->unpaidInvoices, 'remaining'));

        return [
            'title'     => $langs->transnoentities('HomeCollected'),
            'icon'      => 'fas fa-hand-holding-usd',
            'tone'      => 'cash',
            'value'     => $this->formatAmount($payments['amount']),
            'evolution' => $this->getEvolution($payments['amount'], $this->sumPayments($this->comparisonStart, $this->comparisonEnd)),
            'detail'    => $langs->transnoentities('HomeCollectedDetail', $this->formatAmount($outstanding)),
            'alert'     => $payments['lateAmount'] > 0 ? $langs->transnoentities('HomeCollectedLate', $this->formatAmount($payments['lateAmount'])) : '',
            'url'       => DOL_URL_ROOT . '/compta/paiement/list.php?' . $this->getDateRangeQuery('search_date_start', 'search_date_end')
        ];
    }

    /**
     * Get the revenue of the last twelve months next to the same months one year earlier
     *
     * The scale is rounded up to a value its four grid lines divide evenly, so the axis reads round amounts.
     *
     * @return array Months with their revenue, the scale and the totals of both years
     */
    protected function getRevenueChart(): array
    {
        $months         = $this->getMonthBuckets();
        $previousMonths = $this->getMonthBuckets(self::NB_MONTHS_OF_FLOW, 12);

        $this->loadRevenue((int) reset($previousMonths)['start']);

        $rows = [];
        $max  = 0;
        foreach (array_keys($months) as $index => $monthKey) {
            $previousKey = array_keys($previousMonths)[$index];
            $current     = $this->revenuePerMonth[$monthKey]['total'] ?? 0;
            $previous    = $this->revenuePerMonth[$previousKey]['total'] ?? 0;

            $rows[] = [
                'label'             => dol_print_date($months[$monthKey]['start'], '%b'),
                'name'              => dol_print_date($months[$monthKey]['start'], '%B'),
                'title'             => dol_print_date($months[$monthKey]['start'], '%B %Y'),
                'previousTitle'     => dol_print_date($previousMonths[$previousKey]['start'], '%B %Y'),
                'current'           => $current,
                'previous'          => $previous,
                'currentFormatted'  => $this->formatAmount($current),
                'previousFormatted' => $this->formatAmount($previous),
                'evolution'         => $previous != 0 ? ($current >= $previous ? '+' : '') . round(($current - $previous) / abs($previous) * 100) . ' %' : ''
            ];
            $max = max($max, $current, $previous);
        }

        $step          = $this->getNiceStep($max / 4);
        $total         = array_sum(array_column($rows, 'current'));
        $previousTotal = array_sum(array_column($rows, 'previous'));

        $ticks = [];
        for ($i = 4; $i >= 0; $i--) {
            $ticks[] = $this->formatCompactAmount($step * $i);
        }

        return [
            'months'    => $rows,
            'scale'     => $step * 4,
            'ticks'     => $ticks,
            'total'     => $this->formatAmount($total),
            'evolution' => $this->getEvolutionOverYear($total, $previousTotal)
        ];
    }

    /**
     * Get the change of the last twelve months against the twelve before, as a colored badge
     *
     * The badge of the base class names the comparison period of the filter, which the rolling year ignores.
     *
     * @param  float  $value    Revenue of the last twelve months
     * @param  float  $previous Revenue of the same months one year earlier
     * @return string           HTML of the change, empty when there is nothing to compare with
     */
    protected function getEvolutionOverYear(float $value, float $previous): string
    {
        global $langs;

        if ($previous == 0) {
            return '';
        }

        $change = round(($value - $previous) / abs($previous) * 100);
        $state  = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');

        return '<span class="reedcrm-evolution reedcrm-evolution--' . $state . '" title="' . dol_escape_htmltag($langs->transnoentities('HomeRevenueChartCompared', $this->formatAmount($previous))) . '">' . ($change > 0 ? '+' : '') . $change . '&nbsp;%</span>';
    }

    /**
     * Format an amount for an axis, in thousands or millions past them
     *
     * @param  float  $amount Amount to format
     * @return string         Short amount with the currency symbol
     */
    protected function formatCompactAmount(float $amount): string
    {
        global $conf, $langs;

        $symbol = $langs->getCurrencySymbol($conf->currency);
        foreach ([1000000 => 'M', 1000 => 'k'] as $unit => $prefix) {
            if (abs($amount) >= $unit) {
                $value = round($amount / $unit, 1);
                return price($value, 0, $langs, 0, 0, floor($value) == $value ? 0 : 1) . '&nbsp;' . $prefix . $symbol;
            }
        }

        return price(round($amount), 0, $langs, 0, 0, 0) . '&nbsp;' . $symbol;
    }

    /**
     * Get the open pipeline per stage, with the opportunities won over the period
     *
     * @return array Stages in the order of the dictionary, totals of the open pipeline and the won opportunities
     */
    protected function getPipeline(): array
    {
        global $langs;

        $stages = [];
        $resql  = $this->db->query('SELECT rowid, code, label FROM ' . MAIN_DB_PREFIX . "c_lead_status WHERE active = 1 AND code NOT IN ('WON', 'LOST') ORDER BY position, rowid");
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $label = $langs->transnoentities('OppStatus' . $obj->code);

                $stages[(int) $obj->rowid] = [
                    'label'    => $label !== 'OppStatus' . $obj->code ? $label : (string) $obj->label,
                    'nb'       => 0,
                    'amount'   => 0,
                    'weighted' => 0,
                    'url'      => DOL_URL_ROOT . '/projet/list.php?search_opp_status=' . ((int) $obj->rowid)
                ];
            }
            $this->db->free($resql);
        }

        $pipeline = ['stages' => [], 'nb' => 0, 'amount' => 0, 'weighted' => 0, 'max' => 0, 'wonNb' => 0, 'wonAmount' => 0];
        $noStage  = ['label' => $langs->transnoentities('HomePipelineNoStage'), 'nb' => 0, 'amount' => 0, 'weighted' => 0, 'url' => DOL_URL_ROOT . '/projet/list.php?search_opp_status=none'];

        foreach ($this->opportunities as $opportunity) {
            if (!$this->isInScope($opportunity->fk_user)) {
                continue;
            }

            if ($opportunity->won && $this->isInPeriod($opportunity->decision_date)) {
                $pipeline['wonNb']++;
                $pipeline['wonAmount'] += $opportunity->amount;
            }

            if (!$opportunity->open) {
                continue;
            }

            // An opportunity without a known stage goes in a last row, added once one needs it
            $stageId = isset($stages[$opportunity->fk_opp_status]) ? $opportunity->fk_opp_status : 0;
            if (!isset($stages[$stageId])) {
                $stages[$stageId] = $noStage;
            }

            $weighted = $opportunity->amount * $opportunity->percent / 100;
            $stages[$stageId]['nb']++;
            $stages[$stageId]['amount']   += $opportunity->amount;
            $stages[$stageId]['weighted'] += $weighted;

            $pipeline['nb']++;
            $pipeline['amount']   += $opportunity->amount;
            $pipeline['weighted'] += $weighted;
        }

        foreach ($stages as $stage) {
            $pipeline['max'] = max($pipeline['max'], $stage['amount']);
        }
        $pipeline['stages'] = $stages;

        return $pipeline;
    }

    /**
     * Get the last proposals signed over the period
     *
     * @return array Rows of the signed proposals, the latest first, and the link to all of them
     */
    protected function getSignedList(): array
    {
        $signed = $this->getSignedProposals($this->periodStart, $this->periodEnd);
        uasort($signed, function ($first, $second) {
            return $second->date_signed <=> $first->date_signed ?: $second->id <=> $first->id;
        });

        $rows = [];
        foreach (array_slice($signed, 0, self::NB_SHORT_LIST) as $proposal) {
            $propal      = new Propal($this->db);
            $propal->id  = $proposal->id;
            $propal->ref = $proposal->ref;

            $rows[] = [
                'link'       => $propal->getNomUrl(1),
                'thirdparty' => $this->getSocietyLink($proposal->fk_soc, $proposal->soc_name),
                'date'       => $proposal->date_signed,
                'amount'     => $this->formatAmount($proposal->amount),
                'user'       => $proposal->fk_user > 0 ? $this->getUser($proposal->fk_user) : null
            ];
        }

        return ['rows' => $rows, 'nb' => count($signed), 'url' => DOL_URL_ROOT . '/comm/propal/list.php?search_status=2,4&' . $this->getDateRangeQuery('search_date_signature_start', 'search_date_signature_end')];
    }

    /**
     * Get the payments received over the period, the latest first
     *
     * A payment settling several invoices is shown once, on the third party of its first invoice.
     *
     * @return array Amount collected, rows of the last payments, and the late invoices left to collect
     */
    protected function getPayments(): array
    {
        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        $sql  = 'SELECT p.rowid, p.ref, p.datep, SUM(pf.amount) AS amount, MIN(f.fk_soc) AS fk_soc, MIN(s.nom) AS soc_name,';
        $sql .= ' MIN(f.rowid) AS fk_facture, MIN(f.ref) AS facture_ref, COUNT(DISTINCT f.rowid) AS nb_invoices';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement AS p';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement_facture AS pf ON pf.fk_paiement = p.rowid';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture AS f ON f.rowid = pf.fk_facture';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = f.fk_soc';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= " AND p.datep >= '" . $this->db->idate($this->periodStart) . "'";
        $sql .= " AND p.datep <= '" . $this->db->idate($this->periodEnd) . "'";
        $sql .= ' GROUP BY p.rowid, p.ref, p.datep';
        $sql .= ' ORDER BY p.datep DESC, p.rowid DESC';

        $payments = ['amount' => 0, 'nb' => 0, 'rows' => [], 'lateNb' => 0, 'lateAmount' => 0];

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return $payments;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $payments['amount'] += (float) $obj->amount;
            $payments['nb']++;

            if (count($payments['rows']) < self::NB_SHORT_LIST) {
                $invoice      = new Facture($this->db);
                $invoice->id  = (int) $obj->fk_facture;
                $invoice->ref = $obj->facture_ref;

                $payments['rows'][] = [
                    'thirdparty' => $this->getSocietyLink((int) $obj->fk_soc, (string) $obj->soc_name),
                    'invoice'    => $invoice->getNomUrl(1) . ((int) $obj->nb_invoices > 1 ? ' <span class="opacitymedium">+' . ((int) $obj->nb_invoices - 1) . '</span>' : ''),
                    'date'       => (int) $this->db->jdate($obj->datep),
                    'amount'     => $this->formatAmount((float) $obj->amount),
                    'url'        => DOL_URL_ROOT . '/compta/paiement/card.php?id=' . ((int) $obj->rowid)
                ];
            }
        }
        $this->db->free($resql);

        foreach ($this->unpaidInvoices as $invoice) {
            if (!empty($invoice->date_due) && $invoice->date_due < $this->now) {
                $payments['lateNb']++;
                $payments['lateAmount'] += $invoice->remaining;
            }
        }
        $payments['lateUrl'] = DOL_URL_ROOT . '/compta/facture/list.php?search_status=1&search_option=late';
        $payments['url']     = DOL_URL_ROOT . '/compta/paiement/list.php?' . $this->getDateRangeQuery('search_date_start', 'search_date_end');

        return $payments;
    }

    /**
     * Get the last events done by hand: calls, emails, meetings
     *
     * Someone who may not read the agenda of the others only sees the events they own or wrote.
     *
     * @return array Rows of the events, the latest first
     */
    protected function getLastDoneEvents(): array
    {
        global $langs, $user;

        $sql  = 'SELECT a.id, a.label, a.datep, a.fulldayevent, a.fk_soc, s.nom AS soc_name, a.fk_project, pj.ref AS project_ref, pj.title AS project_title,';
        $sql .= ' a.fk_user_action, c.code AS type_code';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm AS c ON c.id = a.fk_action';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = a.fk_soc';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet AS pj ON pj.rowid = a.fk_project';
        $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
        $sql .= " AND c.type IN ('" . implode("','", self::MANUAL_EVENT_TYPES) . "')";
        $sql .= " AND a.datep <= '" . $this->db->idate($this->now) . "'";
        // Done, or not applicable as a call is once it happened; a to-do left in the past is not an action done
        $sql .= ' AND (a.percent = 100 OR a.percent = -1)';
        if (!$user->hasRight('agenda', 'allactions', 'read')) {
            $sql .= ' AND (a.fk_user_action = ' . ((int) $user->id) . ' OR a.fk_user_author = ' . ((int) $user->id) . ')';
        }
        if ($this->filterUserId > 0) {
            $sql .= ' AND a.fk_user_action = ' . $this->filterUserId;
        }
        $sql .= ' ORDER BY a.datep DESC, a.id DESC';
        $sql .= $this->db->plimit(self::NB_LAST_EVENTS);

        $events = [];

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return $events;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $typeLabel = $langs->transnoentities('Action' . $obj->type_code);
            $typeLabel = $typeLabel !== 'Action' . $obj->type_code ? $typeLabel : (string) $obj->type_code;

            $project = '';
            if ($obj->fk_project > 0) {
                $projectObject        = new Project($this->db);
                $projectObject->id    = (int) $obj->fk_project;
                $projectObject->ref   = $obj->project_ref;
                $projectObject->title = $obj->project_title;
                $project              = $projectObject->getNomUrl(1);
            }

            $events[] = [
                // An event saved without a label is named after its type, its link would be empty otherwise
                'label'      => dol_strlen(trim((string) $obj->label)) > 0 ? (string) $obj->label : $typeLabel,
                'url'        => DOL_URL_ROOT . '/comm/action/card.php?id=' . ((int) $obj->id),
                'type'       => $typeLabel,
                'icon'       => $this->getEventIcon((string) $obj->type_code),
                'date'       => $this->formatEventDate((int) $this->db->jdate($obj->datep), !empty($obj->fulldayevent)),
                'thirdparty' => $obj->fk_soc > 0 ? $this->getSocietyLink((int) $obj->fk_soc, (string) $obj->soc_name) : '',
                'project'    => $project,
                'user'       => $obj->fk_user_action > 0 ? $this->getUser((int) $obj->fk_user_action) : null
            ];
        }
        $this->db->free($resql);

        return $events;
    }

    /**
     * Get the last sale of each product, and the products in stock nobody bought for months
     *
     * A sale is a validated order or invoice line, as for the Dolibarr statistics of a product. The dormant products
     * use the delay set in the product setup of the module.
     *
     * @return array|null Rows of the last sales, rows and number of the dormant products, null without sales to read
     */
    protected function getProductSales(): ?array
    {
        global $user;

        $canReadInvoices = $user->hasRight('facture', 'lire');
        $canReadOrders   = isModEnabled('order') && $user->hasRight('commande', 'lire');
        if (!$canReadInvoices && !$canReadOrders) {
            return null;
        }

        $sources = [];
        if ($canReadInvoices) {
            $sql  = "SELECT d.fk_product, f.datef AS date_sale, 'invoice' AS doc_type, f.rowid AS doc_id, f.ref AS doc_ref, f.fk_soc, d.qty";
            $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facturedet AS d';
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture AS f ON f.rowid = d.fk_facture';
            $sql .= ' WHERE d.fk_product > 0 AND f.fk_statut > ' . Facture::STATUS_DRAFT;
            $sql .= ' AND f.type IN (' . Facture::TYPE_STANDARD . ', ' . Facture::TYPE_REPLACEMENT . ', ' . Facture::TYPE_SITUATION . ')';
            $sql .= ' AND f.entity IN (' . getEntity('invoice') . ')';
            $sources[] = $sql;
        }
        if ($canReadOrders) {
            $sql  = "SELECT d.fk_product, c.date_commande AS date_sale, 'order' AS doc_type, c.rowid AS doc_id, c.ref AS doc_ref, c.fk_soc, d.qty";
            $sql .= ' FROM ' . MAIN_DB_PREFIX . 'commandedet AS d';
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'commande AS c ON c.rowid = d.fk_commande';
            $sql .= ' WHERE d.fk_product > 0 AND c.fk_statut > ' . Commande::STATUS_DRAFT;
            $sql .= ' AND c.entity IN (' . getEntity('commande') . ')';
            $sources[] = $sql;
        }
        $union = '(' . implode(') UNION ALL (', $sources) . ')';

        $products = ['recent' => [], 'dormant' => [], 'nbDormant' => 0, 'dormantMonths' => getDolGlobalInt('REEDCRM_DASHBOARD_PRODUCT_INACTIVE_MONTHS', 6), 'hasStock' => false];

        // The products sold last first, then their last line: a product billed every month would otherwise fill the list
        $sql  = 'SELECT l.fk_product, MAX(l.date_sale) AS last_sale';
        $sql .= ' FROM (' . $union . ') AS l';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product AS p ON p.rowid = l.fk_product';
        $sql .= ' WHERE p.entity IN (' . getEntity('product') . ')';
        $sql .= ' GROUP BY l.fk_product';
        $sql .= ' ORDER BY last_sale DESC, l.fk_product DESC';
        $sql .= $this->db->plimit(self::NB_PRODUCTS);

        $productIds = [];
        $resql      = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $productIds[] = (int) $obj->fk_product;
            }
            $this->db->free($resql);
        } else {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }

        $sql  = 'SELECT l.fk_product, l.date_sale, l.doc_type, l.doc_id, l.doc_ref, l.qty, l.fk_soc, s.nom AS soc_name,';
        $sql .= ' p.ref AS product_ref, p.label AS product_label, p.fk_product_type, p.entity AS product_entity';
        $sql .= ' FROM (' . $union . ') AS l';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product AS p ON p.rowid = l.fk_product';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = l.fk_soc';
        $sql .= ' WHERE l.fk_product IN (' . (!empty($productIds) ? implode(',', $productIds) : '0') . ')';
        $sql .= ' ORDER BY l.date_sale DESC, l.doc_id DESC';

        $resql = !empty($productIds) ? $this->db->query($sql) : false;
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                if (isset($products['recent'][(int) $obj->fk_product])) {
                    continue;
                }

                $document      = $obj->doc_type === 'order' ? new Commande($this->db) : new Facture($this->db);
                $document->id  = (int) $obj->doc_id;
                $document->ref = $obj->doc_ref;

                $dateSale = (int) $this->db->jdate($obj->date_sale);

                $products['recent'][(int) $obj->fk_product] = [
                    'product'    => $this->getProductLink($obj),
                    'date'       => $dateSale,
                    'days'       => (int) floor(($this->now - $dateSale) / 86400),
                    'document'   => $document->getNomUrl(1),
                    'thirdparty' => $this->getSocietyLink((int) $obj->fk_soc, (string) $obj->soc_name),
                    'qty'        => (float) $obj->qty
                ];
            }
            $this->db->free($resql);
        } elseif (!empty($productIds)) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }

        if (isModEnabled('stock') && $user->hasRight('stock', 'lire')) {
            $products['hasStock'] = true;
            $this->loadDormantProducts($products, $union);
        }

        return $products;
    }

    /**
     * Load the products for sale still in stock that no order nor invoice took for the configured delay
     *
     * @param  array  $products Product data the dormant rows and their number are added to
     * @param  string $union    SQL union of the sale lines
     * @return void
     */
    protected function loadDormantProducts(array &$products, string $union): void
    {
        $limit = dol_time_plus_duree($this->now, -$products['dormantMonths'], 'm');

        $sql  = 'SELECT p.rowid AS fk_product, p.ref AS product_ref, p.label AS product_label, p.fk_product_type, p.entity AS product_entity,';
        $sql .= ' st.stock, ls.last_sale';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'product AS p';
        $sql .= ' INNER JOIN (SELECT ps.fk_product, SUM(ps.reel) AS stock FROM ' . MAIN_DB_PREFIX . 'product_stock AS ps';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'entrepot AS e ON e.rowid = ps.fk_entrepot';
        $sql .= ' WHERE e.entity IN (' . getEntity('stock') . ') GROUP BY ps.fk_product HAVING SUM(ps.reel) > 0) AS st ON st.fk_product = p.rowid';
        $sql .= ' LEFT JOIN (SELECT fk_product, MAX(date_sale) AS last_sale FROM (' . $union . ') AS l GROUP BY fk_product) AS ls ON ls.fk_product = p.rowid';
        $sql .= ' WHERE p.entity IN (' . getEntity('product') . ') AND p.tosell = 1';
        $sql .= " AND (ls.last_sale IS NULL OR ls.last_sale < '" . $this->db->idate($limit) . "')";
        // The longest asleep first: never sold, then the oldest sale
        $sql .= ' ORDER BY ls.last_sale IS NOT NULL, ls.last_sale ASC, st.stock DESC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $products['nbDormant']++;
            if (count($products['dormant']) >= self::NB_PRODUCTS) {
                continue;
            }

            $lastSale = (int) $this->db->jdate($obj->last_sale);

            $products['dormant'][] = [
                'product' => $this->getProductLink($obj),
                'date'    => $lastSale,
                'days'    => $lastSale > 0 ? (int) floor(($this->now - $lastSale) / 86400) : 0,
                'stock'   => (float) $obj->stock
            ];
        }
        $this->db->free($resql);
    }

    /**
     * Sum the revenue billed between two dates, with the rules of the revenue of the direction dashboard
     *
     * @param  int   $start Timestamp the range starts at
     * @param  int   $end   Timestamp the range ends at
     * @return float        Revenue excluding tax
     */
    protected function sumBilled(int $start, int $end): float
    {
        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        $sql  = 'SELECT SUM(f.total_ht) AS total';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= ' AND f.fk_statut IN (' . Facture::STATUS_VALIDATED . ', ' . Facture::STATUS_CLOSED . ')';
        $sql .= ' AND f.type IN (' . implode(',', self::REVENUE_INVOICE_TYPES) . ')';
        $sql .= " AND f.datef >= '" . $this->db->idate($start) . "'";
        $sql .= " AND f.datef <= '" . $this->db->idate($end) . "'";

        return $this->fetchSum($sql);
    }

    /**
     * Sum the payments received between two dates
     *
     * @param  int   $start Timestamp the range starts at
     * @param  int   $end   Timestamp the range ends at
     * @return float        Amount collected, taxes included
     */
    protected function sumPayments(int $start, int $end): float
    {
        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        $sql  = 'SELECT SUM(pf.amount) AS total';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture AS pf';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement AS p ON p.rowid = pf.fk_paiement';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture AS f ON f.rowid = pf.fk_facture';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= " AND p.datep >= '" . $this->db->idate($start) . "'";
        $sql .= " AND p.datep <= '" . $this->db->idate($end) . "'";

        return $this->fetchSum($sql);
    }

    /**
     * Run a query returning a single total
     *
     * @param  string $sql Query selecting a column named total
     * @return float       Total, 0 when the query fails or finds nothing
     */
    protected function fetchSum(string $sql): float
    {
        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return 0;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return (float) ($obj->total ?? 0);
    }

    /**
     * Get the query string restricting a native list to the selected period
     *
     * @param  string $startParam Name of the start date filter of the list, without its day/month/year suffix
     * @param  string $endParam   Name of the end date filter of the list, without its day/month/year suffix
     * @return string             Query string, without its leading question mark
     */
    protected function getDateRangeQuery(string $startParam, string $endParam): string
    {
        $params = [];
        foreach ([$startParam => $this->periodStart, $endParam => $this->periodEnd] as $param => $date) {
            $params[$param . 'day']   = (int) dol_print_date($date, '%d');
            $params[$param . 'month'] = (int) dol_print_date($date, '%m');
            $params[$param . 'year']  = (int) dol_print_date($date, '%Y');
        }

        return http_build_query($params);
    }

    /**
     * Get a step of a scale rounded to 1, 2, 2.5 or 5 times a power of ten
     *
     * @param  float $rawStep Step the values would need
     * @return float          Rounded step, at least 1
     */
    protected function getNiceStep(float $rawStep): float
    {
        if ($rawStep <= 0) {
            return 1;
        }

        $magnitude = pow(10, floor(log10($rawStep)));
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            if ($rawStep <= $factor * $magnitude) {
                return $factor * $magnitude;
            }
        }

        return 10 * $magnitude;
    }

    /**
     * Get the Font Awesome icon of an event type
     *
     * @param  string $typeCode Code of the event type
     * @return string           Icon classes
     */
    protected function getEventIcon(string $typeCode): string
    {
        if (strpos($typeCode, 'AC_TEL') === 0 || $typeCode === 'AC_FAX') {
            return 'fas fa-phone-alt';
        }
        if (strpos($typeCode, 'AC_EMAIL') === 0) {
            return 'fas fa-envelope';
        }
        if ($typeCode === 'AC_RDV') {
            return 'fas fa-handshake';
        }
        if ($typeCode === 'AC_INT') {
            return 'fas fa-tools';
        }

        return 'fas fa-check';
    }

    /**
     * Format the date of an event relatively to today, the hour left out for a full day event
     *
     * @param  int    $date       Timestamp of the event
     * @param  bool   $isFullDay  True for an event lasting the whole day
     * @return string             Today or yesterday with the hour, the date otherwise
     */
    protected function formatEventDate(int $date, bool $isFullDay): string
    {
        global $langs;

        $day   = dol_print_date($date, '%Y-%m-%d', 'tzuserrel');
        $today = dol_print_date($this->now, '%Y-%m-%d', 'tzuserrel');
        $hour  = $isFullDay ? '' : ' ' . dol_print_date($date, '%H:%M', 'tzuserrel');

        if ($day === $today) {
            return $langs->transnoentities('Today') . $hour;
        }
        if ($day === dol_print_date(dol_time_plus_duree($this->now, -1, 'd'), '%Y-%m-%d', 'tzuserrel')) {
            return $langs->transnoentities('Yesterday') . $hour;
        }

        return dol_print_date($date, 'day', 'tzuserrel') . $hour;
    }

    /**
     * Get the link to a product card from a row holding its ref, label, type and entity
     *
     * @param  stdClass $obj Row of the product
     * @return string        Link to the card of the product
     */
    protected function getProductLink(stdClass $obj): string
    {
        $product         = new Product($this->db);
        $product->id     = (int) $obj->fk_product;
        $product->ref    = $obj->product_ref;
        $product->label  = $obj->product_label;
        $product->type   = (int) $obj->fk_product_type;
        $product->entity = (int) $obj->product_entity;

        return $product->getNomUrl(1, '', 0, -1, 1) . ' <span class="opacitymedium">' . dol_escape_htmltag(dol_trunc((string) $obj->product_label, 40)) . '</span>';
    }
}
