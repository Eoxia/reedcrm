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
 * \file    class/reedcrmdirectiondashboard.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmDirectionDashboard
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/reedcrmsalesindicators.class.php';
require_once __DIR__ . '/../lib/reedcrm_propal_unbilled.lib.php';

/**
 * Class for ReedcrmDirectionDashboard
 *
 * The dashboard of the management answers where the revenue comes from and where it goes: what was billed and how
 * much of it recurs, what the pipeline promises, what the customers still owe, and how the sales team performs
 * against its goals. It always describes the whole company, the sales dashboard being the one to read a single
 * sales representative.
 *
 * The monthly graphs follow the fiscal year and put the last three of them side by side, as the proposal statistics
 * of Dolibarr do, so a month is compared with the same month of the previous years.
 */
class ReedcrmDirectionDashboard extends ReedcrmSalesIndicators
{
    /**
     * @var int Number of fiscal years the yearly comparisons put side by side
     */
    public const NB_FISCAL_YEARS = 3;

    /**
     * @var int[] Limits, in days of delay, cutting the unpaid invoices into age buckets
     */
    public const UNPAID_AGE_LIMITS = [30, 60, 90];

    /**
     * @var int[] Types of invoice making the revenue: standard, replacement, credit note and situation
     */
    public const REVENUE_INVOICE_TYPES = [Facture::TYPE_STANDARD, Facture::TYPE_REPLACEMENT, Facture::TYPE_CREDIT_NOTE, Facture::TYPE_SITUATION];

    /**
     * @var int[] Types of invoice a customer owes money on: standard, replacement, deposit and situation
     */
    public const RECEIVABLE_INVOICE_TYPES = [Facture::TYPE_STANDARD, Facture::TYPE_REPLACEMENT, Facture::TYPE_DEPOSIT, Facture::TYPE_SITUATION];

    /**
     * @var string[] Colors of the fiscal years, the running one in the strongest
     */
    public const FISCAL_YEAR_COLORS = ['#B9A7D6', '#4BA3C7', '#F4B942'];

    /**
     * @var string Prefix of the filters of the dashboard in the user configuration
     */
    protected string $filterPrefix = 'direction';

    /**
     * @var array<string,array<string,float>> Revenue per month (Y-m): total, recurring part and number of invoices
     */
    protected array $revenuePerMonth = [];

    /**
     * @var array Compact rows of the active recurring invoice templates, keyed by template id
     */
    protected array $recurringTemplates = [];

    /**
     * @var array Compact rows of the invoices a customer still owes money on, keyed by invoice id
     */
    protected array $unpaidInvoices = [];

    /**
     * @var array<string,float> Outstanding amount at the end of each of the last months, keyed by Y-m
     */
    protected array $outstandingPerMonth = [];

    /**
     * @var array<int,int> Date of the first signature of each customer, keyed by third party id
     */
    protected array $firstSignatures = [];

    /**
     * Load dashboard info
     *
     * @return array
     * @throws Exception
     */
    public function load_dashboard(): array
    {
        global $langs;

        // The management reads the whole company by default, or one sales representative
        $this->loadFilters('year', 0);

        $oldestFiscalStart = $this->getFiscalYearStart(self::NB_FISCAL_YEARS - 1);
        $windowStart       = (int) min($oldestFiscalStart, $this->comparisonStart);

        $this->loadCalls((int) min($this->periodStart, $this->comparisonStart));
        $this->loadOpportunities();
        $this->loadProposals($windowStart);

        // The person filter offers the whole team: the members are worked out before the records of the others go
        $members = [];
        foreach ($this->getMemberIds(true) as $memberId) {
            $members[$memberId] = $this->getUser($memberId)->getFullName($langs);
        }
        asort($members);

        // Every figure of the page follows the person, the records of the others are dropped once and for all
        if ($this->filterUserId > 0) {
            $this->opportunities = array_filter($this->opportunities, function ($opportunity) {
                return $opportunity->fk_user == $this->filterUserId;
            });
            $this->proposals = array_filter($this->proposals, function ($proposal) {
                return $proposal->fk_user == $this->filterUserId;
            });
        }

        $this->loadRevenue($windowStart);
        $this->loadRecurringTemplates();
        $this->loadUnpaidInvoices();
        $this->loadOutstandingHistory();
        $this->loadFirstSignatures();

        $array = [
            'widgets'        => array_merge(
                $this->getRevenueWidget(),
                $this->getRecurringWidget(),
                $this->getPipelineWidget(),
                $this->getReceivablesWidget(),
                $this->getTeamWidget()
            ),
            'graphs'         => [],
            'lists'          => [],
            'disabledGraphs' => [],
            'graphsFilters'  => [
                'directionPeriod' => [
                    'title'        => $langs->transnoentities('SalesPeriodFilter'),
                    'type'         => 'selectarray',
                    'filter'       => 'directionPeriod',
                    'values'       => $this->getPeriodValues(),
                    'currentValue' => $this->period
                ],
                'directionUser'   => [
                    'title'        => $langs->transnoentities('SalesUserFilter'),
                    'type'         => 'selectarray',
                    'filter'       => 'directionUser',
                    'values'       => [0 => $langs->transnoentities('DirectionWholeCompany')] + $members,
                    'currentValue' => $this->filterUserId
                ]
            ]
        ];

        // Each entry names the method building it, so a hidden graph costs a lookup instead of its whole computation
        $graphs = [
            'DirectionRevenuePerMonth'       => 'getRevenuePerMonth',
            'DirectionRecurringRevenueShare' => 'getRecurringRevenueShare',
            'DirectionSignedPerMonth'        => 'getSignedPerMonth',
            'DirectionOpportunitiesPerMonth' => 'getOpportunitiesPerMonth',
            'DirectionPipelineFunnel'        => 'getPipelineFunnel',
            'DirectionPipelineByStage'       => 'getPipelineByStage',
            'DirectionOpportunitySources'    => 'getOpportunitySources',
            'DirectionLostReasons'           => 'getLostReasons',
            'DirectionUnpaidAge'             => 'getUnpaidAge',
            'DirectionOutstandingEvolution'  => 'getOutstandingEvolution',
            'DirectionSignedPerUser'         => 'getSignedPerUser'
        ];

        $lists = [
            'DirectionTeamPerformance'   => 'getTeamPerformanceList',
            'DirectionTopOpportunities'  => 'getTopOpportunitiesList',
            'DirectionRecurringFollowUp' => 'getRecurringFollowUpList',
            'DirectionUnpaidInvoices'    => 'getUnpaidInvoicesList'
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

        // Every title names the person the figures belong to, a screenshot of a single card stays unambiguous
        if ($this->filterUserId > 0) {
            $suffix = ' - ' . $this->getUser($this->filterUserId)->getFullName($langs);
            foreach (['widgets', 'graphs', 'lists'] as $type) {
                foreach ($array[$type] as $key => $item) {
                    $array[$type][$key]['title'] .= $suffix;
                }
            }
        }

        return $array;
    }

    /**
     * Get the join resolving the sales representative of a third party, the first one when it has several
     *
     * @param  string $socColumn Column holding the id of the third party, with its alias
     * @return string            SQL fragment starting with LEFT JOIN, exposing the user as sc.fk_user
     */
    protected function getSocietySalesRepJoin(string $socColumn): string
    {
        $sql  = ' LEFT JOIN (SELECT fk_soc, MIN(fk_user) AS fk_user FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux GROUP BY fk_soc) AS sc';
        $sql .= ' ON sc.fk_soc = ' . $socColumn;

        return $sql;
    }

    /**
     * Get the joins and the expression resolving the sales representative of an invoice aliased "f"
     *
     * An invoice belongs to its internal contact SALESREPINTERNAL, then to the sales representative of its third
     * party, then to its author as in the Dolibarr statistics.
     *
     * @return array{0: string, 1: string} SQL joins, then the SQL expression of the user id
     */
    protected function getInvoiceSalesRepSql(): array
    {
        return [
            $this->getSalesRepJoin('facture', 'f.rowid', ['SALESREPINTERNAL']) . $this->getSocietySalesRepJoin('f.fk_soc'),
            'COALESCE(sr.rep0, sc.fk_user, f.fk_user_author)'
        ];
    }

    /**
     * Get the SQL condition restricting a query to the selected person
     *
     * @param  string $userExpression SQL expression of the user a record belongs to
     * @return string                 SQL condition starting with AND, empty for the whole company
     */
    protected function getScopeSql(string $userExpression): string
    {
        return $this->filterUserId > 0 ? ' AND ' . $userExpression . ' = ' . $this->filterUserId : '';
    }

    /**
     * Load the revenue billed per month, with the part billed from a recurring invoice template
     *
     * The revenue is the amount excluding tax of the validated invoices, credit notes deducted, as in the turnover
     * of Dolibarr. A deposit is left out: the final invoice bills the same amount again.
     *
     * @param  int  $from Timestamp of the first month to load
     * @return void
     */
    protected function loadRevenue(int $from): void
    {
        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        $sql  = "SELECT DATE_FORMAT(f.datef, '%Y-%m') AS month, SUM(f.total_ht) AS total,";
        $sql .= ' SUM(CASE WHEN f.fk_fac_rec_source > 0 THEN f.total_ht ELSE 0 END) AS recurring, COUNT(f.rowid) AS nb';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= ' AND f.fk_statut IN (' . Facture::STATUS_VALIDATED . ', ' . Facture::STATUS_CLOSED . ')';
        $sql .= ' AND f.type IN (' . implode(',', self::REVENUE_INVOICE_TYPES) . ')';
        $sql .= " AND f.datef >= '" . $this->db->idate($from) . "'";
        $sql .= " GROUP BY DATE_FORMAT(f.datef, '%Y-%m')";

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $this->revenuePerMonth[$obj->month] = ['total' => (float) $obj->total, 'recurring' => (float) $obj->recurring, 'nb' => (int) $obj->nb];
        }
        $this->db->free($resql);
    }

    /**
     * Load the recurring invoice templates still generating invoices
     *
     * @return void
     */
    protected function loadRecurringTemplates(): void
    {
        $sql  = 'SELECT fr.rowid, fr.titre, fr.fk_soc, s.nom AS soc_name, fr.total_ht, fr.frequency, fr.unit_frequency,';
        $sql .= ' fr.date_when, fr.date_last_gen, fr.nb_gen_done, fr.nb_gen_max, fr.datec';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec AS fr';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = fr.fk_soc';
        $sql .= $this->getSocietySalesRepJoin('fr.fk_soc');
        $sql .= ' WHERE fr.entity IN (' . getEntity('facturerec') . ')';
        // A template has no sales contact of its own: it belongs to the sales representative of its customer
        $sql .= $this->getScopeSql('COALESCE(sc.fk_user, fr.fk_user_author)');
        $sql .= ' AND fr.suspended = 0 AND fr.frequency > 0';
        $sql .= ' AND (fr.nb_gen_max IS NULL OR fr.nb_gen_max = 0 OR fr.nb_gen_done < fr.nb_gen_max)';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $template = (object) [
                'id'            => (int) $obj->rowid,
                'title'         => (string) $obj->titre,
                'fk_soc'        => (int) $obj->fk_soc,
                'soc_name'      => (string) $obj->soc_name,
                'amount'        => (float) $obj->total_ht,
                'frequency'     => (int) $obj->frequency,
                'unit'          => (string) $obj->unit_frequency,
                'date_when'     => (int) $this->db->jdate($obj->date_when),
                'date_last_gen' => (int) $this->db->jdate($obj->date_last_gen),
                'nb_gen_done'   => (int) $obj->nb_gen_done,
                'nb_gen_max'    => (int) $obj->nb_gen_max,
                'datec'         => (int) $this->db->jdate($obj->datec)
            ];
            $template->monthly = $template->amount * $this->getMonthlyFactor($template->frequency, $template->unit);

            $this->recurringTemplates[$template->id] = $template;
        }
        $this->db->free($resql);
    }

    /**
     * Get the factor turning the amount of a recurring invoice into a monthly amount
     *
     * @param  int    $frequency Number of units between two invoices
     * @param  string $unit      Unit of the frequency: d (day), w (week), m (month) or y (year)
     * @return float             Factor to apply to the amount of one invoice
     */
    protected function getMonthlyFactor(int $frequency, string $unit): float
    {
        if ($frequency <= 0) {
            return 0;
        }

        switch ($unit) {
            case 'd':
                return 365.25 / 12 / $frequency;
            case 'w':
                return 52.1775 / 12 / $frequency;
            case 'y':
                return 1 / (12 * $frequency);
            default:
                return 1 / $frequency;
        }
    }

    /**
     * Load the invoices a customer still owes money on, with what is left to pay
     *
     * What is left to pay deducts the payments and the credit notes or deposits already applied to the invoice,
     * the way Dolibarr computes it on the invoice card.
     *
     * @return void
     */
    protected function loadUnpaidInvoices(): void
    {
        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        $sql  = 'SELECT f.rowid, f.ref, f.fk_soc, s.nom AS soc_name, f.datef, f.date_lim_reglement, f.total_ttc, f.type,';
        $sql .= ' COALESCE(pf.paid, 0) AS paid, COALESCE(re.credited, 0) AS credited';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = f.fk_soc';
        $sql .= ' LEFT JOIN (SELECT fk_facture, SUM(amount) AS paid FROM ' . MAIN_DB_PREFIX . 'paiement_facture GROUP BY fk_facture) AS pf ON pf.fk_facture = f.rowid';
        $sql .= ' LEFT JOIN (SELECT fk_facture, SUM(amount_ttc) AS credited FROM ' . MAIN_DB_PREFIX . 'societe_remise_except WHERE fk_facture IS NOT NULL GROUP BY fk_facture) AS re ON re.fk_facture = f.rowid';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= ' AND f.fk_statut = ' . Facture::STATUS_VALIDATED . ' AND f.paye = 0';
        $sql .= ' AND f.type IN (' . implode(',', self::RECEIVABLE_INVOICE_TYPES) . ')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $remaining = (float) $obj->total_ttc - (float) $obj->paid - (float) $obj->credited;
            if ($remaining <= 0) {
                continue;
            }

            $this->unpaidInvoices[(int) $obj->rowid] = (object) [
                'id'        => (int) $obj->rowid,
                'ref'       => $obj->ref,
                'type'      => (int) $obj->type,
                'fk_soc'    => (int) $obj->fk_soc,
                'soc_name'  => (string) $obj->soc_name,
                'datef'     => (int) $this->db->jdate($obj->datef),
                'date_due'  => (int) $this->db->jdate($obj->date_lim_reglement),
                'remaining' => $remaining
            ];
        }
        $this->db->free($resql);
    }

    /**
     * Load the outstanding amount at the end of each of the last months
     *
     * The outstanding of a month end is what the invoices issued by then still lacked: their amount minus the
     * payments received by then. An invoice classified paid or abandoned stops counting from its closing date,
     * the remaining amount of an abandoned one being written off rather than owed.
     *
     * @return void
     */
    protected function loadOutstandingHistory(): void
    {
        $months      = $this->getMonthBuckets();
        $windowStart = reset($months)['start'];

        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        // An invoice weighs on the history when it is still unpaid or was closed inside the window
        $sql  = 'SELECT f.rowid, f.datef, f.total_ttc, f.fk_statut, f.paye, f.date_closing, f.tms';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= ' AND f.fk_statut IN (' . Facture::STATUS_VALIDATED . ', ' . Facture::STATUS_CLOSED . ', ' . Facture::STATUS_ABANDONED . ')';
        $sql .= ' AND f.type IN (' . implode(',', self::RECEIVABLE_INVOICE_TYPES) . ')';
        $sql .= " AND f.datef <= '" . $this->db->idate($this->now) . "'";
        $sql .= ' AND ((f.fk_statut = ' . Facture::STATUS_VALIDATED . ' AND f.paye = 0)';
        $sql .= " OR COALESCE(f.date_closing, f.tms) >= '" . $this->db->idate($windowStart) . "')";

        $invoices = [];
        $resql    = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }
        while ($obj = $this->db->fetch_object($resql)) {
            $open = (int) $obj->fk_statut == Facture::STATUS_VALIDATED && empty($obj->paye);

            $invoices[(int) $obj->rowid] = [
                'datef'    => (int) $this->db->jdate($obj->datef),
                'total'    => (float) $obj->total_ttc,
                'closed'   => $open ? 0 : (int) $this->db->jdate($obj->date_closing ?: $obj->tms),
                'payments' => []
            ];
        }
        $this->db->free($resql);

        if (empty($invoices)) {
            return;
        }

        $sql  = 'SELECT pf.fk_facture, pf.amount, p.datep';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture AS pf';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement AS p ON p.rowid = pf.fk_paiement';
        $sql .= ' WHERE pf.fk_facture IN (' . implode(',', array_keys($invoices)) . ')';

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $invoices[(int) $obj->fk_facture]['payments'][] = ['date' => (int) $this->db->jdate($obj->datep), 'amount' => (float) $obj->amount];
            }
            $this->db->free($resql);
        }

        foreach ($months as $key => $month) {
            $monthEnd = min($month['end'], $this->now);
            $total    = 0;
            foreach ($invoices as $invoice) {
                if ($invoice['datef'] > $monthEnd || (!empty($invoice['closed']) && $invoice['closed'] <= $monthEnd)) {
                    continue;
                }
                $remaining = $invoice['total'];
                foreach ($invoice['payments'] as $payment) {
                    if ($payment['date'] <= $monthEnd) {
                        $remaining -= $payment['amount'];
                    }
                }
                $total += max(0, $remaining);
            }
            $this->outstandingPerMonth[$key] = $total;
        }
    }

    /**
     * Load the date each customer signed their first proposal on, to tell a new customer from a returning one
     *
     * A new customer is credited to the sales representative of that first proposal: the one who won it.
     *
     * @return void
     */
    protected function loadFirstSignatures(): void
    {
        $sql  = 'SELECT pr.fk_soc, COALESCE(pr.date_signature, pr.date_cloture) AS date_signed,';
        $sql .= ' COALESCE(sr.rep0, sr.rep1, pr.fk_user_author) AS fk_user';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal AS pr';
        $sql .= $this->getSalesRepJoin('propal', 'pr.rowid', ['SALESREPINTERNAL', 'SALESREPFOLL']);
        $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ')';
        $sql .= ' AND pr.fk_statut IN (' . Propal::STATUS_SIGNED . ', ' . Propal::STATUS_BILLED . ') AND pr.fk_soc > 0';
        $sql .= ' ORDER BY COALESCE(pr.date_signature, pr.date_cloture) ASC, pr.rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
            return;
        }

        $seen = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $socId = (int) $obj->fk_soc;
            if (isset($seen[$socId])) {
                continue;
            }
            $seen[$socId] = 1;
            if ($this->isInScope((int) $obj->fk_user)) {
                $this->firstSignatures[$socId] = (int) $this->db->jdate($obj->date_signed);
            }
        }
        $this->db->free($resql);
    }

    /**
     * Get the revenue of the period, compared with the same span one period earlier
     *
     * @return array Widget of the billed and signed revenue
     */
    protected function getRevenueWidget(): array
    {
        global $form, $langs, $user;

        $revenue           = $this->sumRevenue($this->periodStart, $this->periodEnd);
        $revenueComparison = $this->sumRevenue($this->comparisonStart, $this->comparisonEnd);

        $signed           = $this->getSignedProposals($this->periodStart, $this->periodEnd);
        $signedComparison = $this->getSignedProposals($this->comparisonStart, $this->comparisonEnd);
        $signedAmount     = array_sum(array_column($signed, 'amount'));
        $comparisonAmount = array_sum(array_column($signedComparison, 'amount'));

        $unbilled = $this->getSignedUnbilled();

        // Widget title parameters
        $array['title']      = $langs->transnoentities('DirectionRevenue', dol_strtolower($this->getPeriodValues()[$this->period]));
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-euro-sign';
        $array['pictoColor'] = '#0D8AFF';

        $array['label']       = [
            $form->textwithpicto($langs->transnoentities('DirectionBilledRevenue'), $langs->transnoentities('DirectionBilledRevenueDescription')),
            $form->textwithpicto($langs->transnoentities('DirectionRecurringPart'), $langs->transnoentities('DirectionRecurringPartDescription')),
            $langs->transnoentities('DirectionSignedRevenue'),
            $form->textwithpicto($langs->transnoentities('SalesProposalsSigned'), $langs->transnoentities('SalesProposalsSignedDescription')),
            $langs->transnoentities('SalesAverageSignedDeal'),
            $form->textwithpicto($langs->transnoentities('DirectionSignedUnbilled'), $langs->transnoentities('DirectionSignedUnbilledDescription'))
        ];
        $array['content']     = [
            $this->formatAmount($revenue['total']),
            $this->formatRate($revenue['recurring'], $revenue['total']),
            $this->formatAmount($signedAmount),
            count($signed),
            !empty($signed) ? $this->formatAmount($signedAmount / count($signed)) : $langs->transnoentities('NoData'),
            $this->formatAmount($unbilled['amount'])
        ];
        $array['moreContent'] = [
            $this->getEvolution($revenue['total'], $revenueComparison['total']),
            $revenue['total'] > 0 ? ' <span class="opacitymedium">(' . $this->formatAmount($revenue['recurring']) . ')</span>' : '',
            $this->getEvolution($signedAmount, $comparisonAmount),
            $this->getEvolution(count($signed), count($signedComparison)),
            '',
            ' <span class="opacitymedium">(' . $unbilled['nb'] . ')</span>' . ($unbilled['nb'] > 0 && $user->hasRight('reedcrm', 'followup', 'read') ? $this->getListLink(dol_buildpath('/reedcrm/view/signedunbilled_list.php', 1)) : '')
        ];

        return ['directionRevenue' => $array];
    }

    /**
     * Get the recurring revenue the active templates bring, brought back to a month whatever their frequency
     *
     * @return array Widget of the recurring billing
     */
    protected function getRecurringWidget(): array
    {
        global $form, $langs, $user;

        $todayStart = dol_get_first_hour($this->now);
        $soonLimit  = dol_time_plus_duree($todayStart, 30, 'd');

        $monthly    = 0;
        $customers  = [];
        $late       = 0;
        $soonAmount = 0;
        $soonNb     = 0;
        $newMonthly = 0;
        $newNb      = 0;
        foreach ($this->recurringTemplates as $template) {
            $monthly += $template->monthly;
            if ($template->fk_soc > 0) {
                $customers[$template->fk_soc] = 1;
            }
            if ($template->date_when > 0 && $template->date_when < $todayStart) {
                $late++;
            } elseif ($template->date_when > 0 && $template->date_when <= $soonLimit) {
                $soonNb++;
                $soonAmount += $template->amount;
            }
            if ($this->isInPeriod($template->datec)) {
                $newNb++;
                $newMonthly += $template->monthly;
            }
        }

        // Widget title parameters
        $array['title']      = $langs->transnoentities('DirectionRecurring');
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-sync-alt';
        $array['pictoColor'] = '#32E592';

        $array['label']       = [
            $form->textwithpicto($langs->transnoentities('DirectionMonthlyRecurringRevenue'), $langs->transnoentities('DirectionMonthlyRecurringRevenueDescription')),
            $langs->transnoentities('DirectionAnnualRecurringRevenue'),
            $langs->transnoentities('DirectionActiveSubscriptions'),
            $langs->transnoentities('DirectionRecurringToBill', 30),
            $form->textwithpicto($langs->transnoentities('DirectionRecurringLate'), $langs->transnoentities('DirectionRecurringLateDescription')),
            $langs->transnoentities('DirectionNewSubscriptions')
        ];
        $array['content']     = [
            $this->formatAmount($monthly),
            $this->formatAmount($monthly * 12),
            count($this->recurringTemplates),
            $this->formatAmount($soonAmount),
            $late,
            $newNb
        ];
        $array['moreContent'] = [
            '',
            '',
            ' <span class="opacitymedium">(' . $langs->transnoentities('DirectionNbCustomers', count($customers)) . ')</span>' . $this->getListLink(DOL_URL_ROOT . '/compta/facture/invoicetemplate_list.php'),
            ' <span class="opacitymedium">(' . $soonNb . ')</span>',
            $late > 0 && $user->hasRight('reedcrm', 'followup', 'read') ? $this->getListLink(dol_buildpath('/reedcrm/view/billinggaps_list.php', 1)) : '',
            $newNb > 0 ? ' <span class="opacitymedium">(+' . $this->formatAmount($newMonthly) . ' / ' . $langs->transnoentities('Month') . ')</span>' : ''
        ];

        return ['directionRecurring' => $array];
    }

    /**
     * Get what the pipeline promises and how the decisions of the period went
     *
     * @return array Widget of the pipeline
     */
    protected function getPipelineWidget(): array
    {
        global $form, $langs;

        $nbOpen     = 0;
        $openAmount = 0;
        $weighted   = 0;
        $won        = 0;
        $lost       = 0;
        foreach ($this->opportunities as $opportunity) {
            if ($opportunity->open) {
                $nbOpen++;
                $openAmount += $opportunity->amount;
                $weighted   += $opportunity->amount * $opportunity->percent / 100;
            }
            if ($this->isInPeriod($opportunity->decision_date)) {
                if ($opportunity->won) {
                    $won++;
                } elseif ($opportunity->lost) {
                    $lost++;
                }
            }
        }

        $created           = count($this->getCreatedOpportunities($this->periodStart, $this->periodEnd));
        $createdComparison = count($this->getCreatedOpportunities($this->comparisonStart, $this->comparisonEnd));

        $signed  = $this->getSignedProposals($this->periodStart, $this->periodEnd);
        $refused = array_filter($this->proposals, function ($proposal) {
            return !empty($proposal->date_refused) && $this->isInPeriod($proposal->date_refused);
        });

        $signatureDelays = [];
        foreach ($signed as $proposal) {
            if ($proposal->date_valid > 0 && $proposal->date_signed >= $proposal->date_valid) {
                $signatureDelays[] = $proposal->date_signed - $proposal->date_valid;
            }
        }

        // Widget title parameters
        $array['title']      = $langs->transnoentities('SalesPipeline');
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-funnel-dollar';
        $array['pictoColor'] = '#E9A00D';

        $array['label']       = [
            $langs->transnoentities('SalesOpenOpportunities'),
            $form->textwithpicto($langs->transnoentities('SalesWeightedPipeline'), $langs->transnoentities('SalesWeightedPipelineDescription')),
            $langs->transnoentities('SalesOpportunitiesCreated'),
            $form->textwithpicto($langs->transnoentities('SalesWinRate'), $langs->transnoentities('SalesWinRateDescription')),
            $form->textwithpicto($langs->transnoentities('SalesSignatureRate'), $langs->transnoentities('SalesSignatureRateDescription')),
            $langs->transnoentities('SalesMeanSignatureDelay')
        ];
        $array['content']     = [
            $nbOpen,
            $this->formatAmount($weighted),
            $created,
            $this->formatRate($won, $won + $lost),
            $this->formatRate(count($signed), count($signed) + count($refused)),
            !empty($signatureDelays) ? $langs->transnoentities('SalesNbDays', round(array_sum($signatureDelays) / count($signatureDelays) / 86400)) : $langs->transnoentities('NoData')
        ];
        $array['moreContent'] = [
            ' <span class="opacitymedium">(' . $this->formatAmount($openAmount) . ')</span>' . $this->getListLink(DOL_URL_ROOT . '/projet/list.php?search_opp_status=openedopp'),
            '',
            $this->getEvolution($created, $createdComparison),
            ($won + $lost) > 0 ? ' <span class="opacitymedium">(' . $won . ' / ' . ($won + $lost) . ')</span>' : '',
            (count($signed) + count($refused)) > 0 ? ' <span class="opacitymedium">(' . count($signed) . ' / ' . (count($signed) + count($refused)) . ')</span>' : '',
            ''
        ];

        return ['directionPipeline' => $array];
    }

    /**
     * Get what the customers still owe and how late they are
     *
     * @return array Widget of the receivables
     */
    protected function getReceivablesWidget(): array
    {
        global $form, $langs;

        $todayStart = dol_get_first_hour($this->now);

        $outstanding = 0;
        $overdue     = 0;
        $nbOverdue   = 0;
        $nbVeryLate  = 0;
        foreach ($this->unpaidInvoices as $invoice) {
            $outstanding += $invoice->remaining;
            if ($invoice->date_due > 0 && $invoice->date_due < $todayStart) {
                $overdue += $invoice->remaining;
                $nbOverdue++;
                if ($invoice->date_due < $todayStart - self::UNPAID_AGE_LIMITS[0] * 86400) {
                    $nbVeryLate++;
                }
            }
        }

        $payments = $this->getPaymentsOfPeriod();

        // Widget title parameters
        $array['title']      = $langs->transnoentities('DirectionReceivables');
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-hand-holding-usd';
        $array['pictoColor'] = '#A1467E';

        $array['label']       = [
            $form->textwithpicto($langs->transnoentities('DirectionOutstanding'), $langs->transnoentities('DirectionOutstandingDescription')),
            $langs->transnoentities('DirectionOverdue'),
            $langs->transnoentities('DirectionInvoicesVeryLate', self::UNPAID_AGE_LIMITS[0]),
            $langs->transnoentities('DirectionCollected'),
            $form->textwithpicto($langs->transnoentities('DirectionMeanPaymentDelay'), $langs->transnoentities('DirectionMeanPaymentDelayDescription'))
        ];
        $array['content']     = [
            $this->formatAmount($outstanding),
            $this->formatAmount($overdue),
            $nbVeryLate,
            $this->formatAmount($payments['amount']),
            $payments['delay'] > 0 ? $langs->transnoentities('SalesNbDays', $payments['delay']) : $langs->transnoentities('NoData')
        ];
        $array['moreContent'] = [
            ' <span class="opacitymedium">(' . count($this->unpaidInvoices) . ')</span>' . (!empty($this->unpaidInvoices) ? $this->getListLink(DOL_URL_ROOT . '/compta/facture/list.php?search_status=' . Facture::STATUS_VALIDATED) : ''),
            ' <span class="opacitymedium">(' . $nbOverdue . ' - ' . $this->formatRate($overdue, $outstanding) . ')</span>',
            '',
            '',
            ''
        ];

        return ['directionReceivables' => $array];
    }

    /**
     * Get how the sales team performs against its goals over the period
     *
     * @return array Widget of the activity and the results of the team
     */
    protected function getTeamWidget(): array
    {
        global $form, $langs;

        $memberIds   = $this->getMemberIds();
        $goals       = $this->getScopeGoals($memberIds);
        $workingDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodFullEnd);
        $elapsedDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodEnd);
        $pace        = $this->getPeriodElapsedRatio();
        $nbMonths    = $this->getPeriodNbMonths();

        $calls           = $this->countCalls($this->periodStart, $this->periodEnd);
        $callsComparison = $this->countCalls($this->comparisonStart, $this->comparisonEnd);
        $nbSigned        = count($this->getSignedProposals($this->periodStart, $this->periodEnd));

        $newCustomers           = 0;
        $newCustomersComparison = 0;
        foreach ($this->firstSignatures as $firstSignature) {
            if ($this->isInPeriod($firstSignature)) {
                $newCustomers++;
            } elseif ($this->isInComparison($firstSignature)) {
                $newCustomersComparison++;
            }
        }

        // Widget title parameters
        $array['title']      = $langs->transnoentities('DirectionSalesTeam');
        $array['widgetName'] = $array['title'];
        $array['picto']      = 'fas fa-users';
        $array['pictoColor'] = '#63ACC9';

        $array['label']       = [
            $form->textwithpicto($langs->transnoentities('DirectionSalesRepresentatives'), $langs->transnoentities('DirectionSalesRepresentativesDescription')),
            $form->textwithpicto($langs->transnoentities('SalesCallsMade'), $langs->transnoentities('SalesCallsMadeDescription')),
            $langs->transnoentities('DirectionCallsPerDayPerRepresentative'),
            $form->textwithpicto($langs->transnoentities('SalesProposalsSigned'), $langs->transnoentities('SalesProposalsSignedDescription')),
            $form->textwithpicto($langs->transnoentities('DirectionNewCustomers'), $langs->transnoentities('DirectionNewCustomersDescription'))
        ];
        $array['content']     = [
            count($memberIds),
            $calls,
            $this->formatNumber(($elapsedDays > 0 && !empty($memberIds)) ? $calls / $elapsedDays / count($memberIds) : 0),
            $nbSigned,
            $newCustomers
        ];
        $array['moreContent'] = [
            '',
            $this->getGoalProgress($calls, $goals['calls_per_day'] * $workingDays, $pace) . $this->getEvolution($calls, $callsComparison),
            '',
            $this->getGoalProgress($nbSigned, $goals['signed_per_month'] * $nbMonths, $pace),
            $this->getEvolution($newCustomers, $newCustomersComparison)
        ];

        return ['directionTeam' => $array];
    }

    /**
     * Get the revenue billed each month of the last fiscal years, a series per year
     *
     * @return array Graph of the revenue per month
     */
    protected function getRevenuePerMonth(): array
    {
        global $langs;

        $array = $this->getFiscalYearsGraph('DirectionRevenuePerMonth', 'fontawesome_fa-file-invoice-dollar_fas_#3bbfa8');

        $datasetLinks = [];
        foreach ($this->getFiscalYearsMonths() as $yearIndex => $months) {
            foreach ($months as $monthIndex => $month) {
                $array['data'][$monthIndex][$yearIndex + 1] = round($this->revenuePerMonth[$month['key']]['total'] ?? 0);
                $datasetLinks[$yearIndex][]                 = DOL_URL_ROOT . '/compta/facture/list.php?' . reedcrm_get_date_range_filter('search_date', $month['start'], $month['end']);
            }
        }
        $array['title'] = $langs->transnoentities('DirectionRevenuePerMonth');

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['datasetLinks' => $datasetLinks]);

        return $array;
    }

    /**
     * Get the share of the revenue billed from recurring templates over the last months
     *
     * @return array Graph of the recurring and the one-off revenue per month
     */
    protected function getRecurringRevenueShare(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionRecurringRevenueShare');
        $array['name']  = 'DirectionRecurringRevenueShare';
        $array['picto'] = 'fontawesome_fa-sync-alt_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;

        $array['labels'] = [
            ['label' => $langs->transnoentities('DirectionRecurringRevenue'), 'color' => '#32E592'],
            ['label' => $langs->transnoentities('DirectionOneOffRevenue'), 'color' => '#0D8AFF']
        ];

        foreach ($this->getMonthBuckets() as $key => $month) {
            $recurring       = $this->revenuePerMonth[$key]['recurring'] ?? 0;
            $array['data'][] = [$month['label'], round($recurring), round(($this->revenuePerMonth[$key]['total'] ?? 0) - $recurring)];
        }

        return $array;
    }

    /**
     * Get the amount signed each month of the last fiscal years, a series per year
     *
     * @return array Graph of the signed amount per month
     */
    protected function getSignedPerMonth(): array
    {
        global $langs;

        $array = $this->getFiscalYearsGraph('DirectionSignedPerMonth', 'fontawesome_fa-file-signature_fas_#3bbfa8');

        $datasetLinks = [];
        foreach ($this->getFiscalYearsMonths() as $yearIndex => $months) {
            foreach ($months as $monthIndex => $month) {
                $array['data'][$monthIndex][$yearIndex + 1] = round(array_sum(array_column($this->getSignedProposals($month['start'], $month['end']), 'amount')));
                $datasetLinks[$yearIndex][]                 = DOL_URL_ROOT . '/comm/propal/list.php?search_status=' . Propal::STATUS_SIGNED . ',' . Propal::STATUS_BILLED . '&' . reedcrm_get_date_range_filter('search_date_signature', $month['start'], $month['end']);
            }
        }
        $array['title'] = $langs->transnoentities('DirectionSignedPerMonth');

        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['datasetLinks' => $datasetLinks]);

        return $array;
    }

    /**
     * Get the number of opportunities created each month of the last fiscal years, a series per year
     *
     * @return array Graph of the opportunities created per month
     */
    protected function getOpportunitiesPerMonth(): array
    {
        global $langs;

        $array = $this->getFiscalYearsGraph('DirectionOpportunitiesPerMonth', 'fontawesome_fa-project-diagram_fas_#3bbfa8');

        foreach ($this->getFiscalYearsMonths() as $yearIndex => $months) {
            foreach ($months as $monthIndex => $month) {
                $array['data'][$monthIndex][$yearIndex + 1] = count($this->getCreatedOpportunities($month['start'], $month['end']));
            }
        }
        $array['title'] = $langs->transnoentities('DirectionOpportunitiesPerMonth');

        return $array;
    }

    /**
     * Get the funnel from the gross pipeline to what was signed over the period
     *
     * @return array Graph of the gross pipeline, the weighted pipeline and the signed amount
     */
    protected function getPipelineFunnel(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionPipelineFunnel');
        $array['name']  = 'DirectionPipelineFunnel';
        $array['picto'] = 'fontawesome_fa-filter_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'horizontalbars';
        $array['showlegend'] = 0;
        $array['dataset']    = 2;

        $array['labels'] = [['label' => $langs->transnoentities('AmountHT'), 'color' => '#0D8AFF']];

        $gross    = 0;
        $weighted = 0;
        foreach ($this->opportunities as $opportunity) {
            if ($opportunity->open) {
                $gross    += $opportunity->amount;
                $weighted += $opportunity->amount * $opportunity->percent / 100;
            }
        }
        $signed = array_sum(array_column($this->getSignedProposals($this->periodStart, $this->periodEnd), 'amount'));

        // Each step carries its share of the gross pipeline, the reading the funnel is drawn for
        $steps = [
            'DirectionGrossPipeline' => $gross,
            'SalesWeightedPipeline'  => $weighted,
            'DirectionSignedRevenue' => $signed
        ];
        foreach ($steps as $label => $amount) {
            $share           = $gross > 0 ? ' (' . round($amount / $gross * 100) . ' %)' : '';
            $array['data'][] = [$langs->transnoentities($label) . $share, round($amount)];
        }

        return $array;
    }

    /**
     * Get the open pipeline per stage of the opportunities
     *
     * @return array Graph of the gross amount of the open opportunities per status
     */
    protected function getPipelineByStage(): array
    {
        global $conf, $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionPipelineByStage');
        $array['name']  = 'DirectionPipelineByStage';
        $array['picto'] = 'fontawesome_fa-chart-pie_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'pie';
        $array['showlegend'] = ($conf->browser->layout ?? '') == 'phone' ? 1 : 2;
        $array['dataset']    = 1;

        $array['labels'] = [];
        $array['data']   = [];
        foreach ($this->opportunities as $opportunity) {
            if (!$opportunity->open) {
                continue;
            }
            $statusId = $opportunity->fk_opp_status;
            if (!isset($array['data'][$statusId])) {
                $code                       = $this->getLeadStatusCode($statusId);
                $array['labels'][$statusId] = ['label' => !empty($code) ? $langs->transnoentities('OppStatus' . $code) : $langs->transnoentities('NoStatus'), 'color' => SaturneDashboard::getColorRange(count($array['data']) * 3)];
                $array['data'][$statusId]   = 0;
            }
            $array['data'][$statusId] += round($opportunity->amount);
        }

        // A stage holding no amount draws no slice, it would only clutter the legend
        foreach ($array['data'] as $statusId => $amount) {
            if ($amount <= 0) {
                unset($array['data'][$statusId], $array['labels'][$statusId]);
            }
        }

        return $array;
    }

    /**
     * Get the opportunities created and won per origin over the period
     *
     * @return array Graph of the opportunities per origin
     */
    protected function getOpportunitySources(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionOpportunitySources');
        $array['name']  = 'DirectionOpportunitySources';
        $array['picto'] = 'fontawesome_fa-bullhorn_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = 3;

        $array['labels'] = [
            ['label' => $langs->transnoentities('SalesOpportunitiesCreated'), 'color' => '#0D8AFF'],
            ['label' => $langs->transnoentities('DirectionOpportunitiesWon'), 'color' => '#32E592']
        ];

        $origins   = $this->getDictionaryLabels('c_input_reason', 'DemandReasonType');
        $perOrigin = [];
        foreach ($this->opportunities as $opportunity) {
            $created = $this->isInPeriod($opportunity->datec);
            $won     = $opportunity->won && $this->isInPeriod($opportunity->decision_date);
            if (!$created && !$won) {
                continue;
            }
            $origin = isset($origins[$opportunity->origin]) ? $opportunity->origin : 0;
            if (!isset($perOrigin[$origin])) {
                $perOrigin[$origin] = ['created' => 0, 'won' => 0];
            }
            $perOrigin[$origin]['created'] += $created ? 1 : 0;
            $perOrigin[$origin]['won']     += $won ? 1 : 0;
        }
        uasort($perOrigin, function ($first, $second) {
            return $second['created'] <=> $first['created'];
        });

        foreach ($perOrigin as $origin => $data) {
            $array['data'][] = [$origins[$origin] ?? $langs->transnoentities('DirectionUnknownOrigin'), $data['created'], $data['won']];
        }

        return $array;
    }

    /**
     * Get the reasons the opportunities and the proposals were lost for over the period
     *
     * @return array Graph of the lost opportunities and refused proposals per reason
     */
    protected function getLostReasons(): array
    {
        global $conf, $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionLostReasons');
        $array['name']  = 'DirectionLostReasons';
        $array['picto'] = 'fontawesome_fa-thumbs-down_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'pie';
        $array['showlegend'] = ($conf->browser->layout ?? '') == 'phone' ? 1 : 2;
        $array['dataset']    = 1;

        $reasons = $this->getDictionaryLabels('c_refusal_reason');

        $perReason = [];
        foreach ($this->opportunities as $opportunity) {
            if ($opportunity->lost && $this->isInPeriod($opportunity->decision_date)) {
                $reason             = isset($reasons[$opportunity->refusal]) ? $opportunity->refusal : 0;
                $perReason[$reason] = ($perReason[$reason] ?? 0) + 1;
            }
        }

        // A refused proposal carries its own reason, it counts even when its opportunity is still open
        $sql  = 'SELECT pe.commrefusal, COUNT(pr.rowid) AS nb';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal AS pr';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal_extrafields AS pe ON pe.fk_object = pr.rowid';
        $sql .= $this->getSalesRepJoin('propal', 'pr.rowid', ['SALESREPINTERNAL', 'SALESREPFOLL']);
        $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ')';
        $sql .= $this->getScopeSql('COALESCE(sr.rep0, sr.rep1, pr.fk_user_author)');
        $sql .= ' AND pr.fk_statut = ' . Propal::STATUS_NOTSIGNED;
        $sql .= " AND pr.date_cloture >= '" . $this->db->idate($this->periodStart) . "'";
        $sql .= " AND pr.date_cloture <= '" . $this->db->idate($this->periodEnd) . "'";
        $sql .= ' GROUP BY pe.commrefusal';

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $reason             = isset($reasons[(int) $obj->commrefusal]) ? (int) $obj->commrefusal : 0;
                $perReason[$reason] = ($perReason[$reason] ?? 0) + (int) $obj->nb;
            }
            $this->db->free($resql);
        } else {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }

        arsort($perReason);

        $array['labels'] = [];
        $array['data']   = [];
        $colorIndex      = 0;
        foreach ($perReason as $reason => $nb) {
            $array['labels'][$reason] = ['label' => $reasons[$reason] ?? $langs->transnoentities('DirectionNoReason'), 'color' => $reason == 0 ? '#999999' : SaturneDashboard::getColorRange($colorIndex++ * 2)];
            $array['data'][$reason]   = $nb;
        }

        return $array;
    }

    /**
     * Get the amount the customers owe per delay of payment
     *
     * @return array Graph of the outstanding amount per age bucket
     */
    protected function getUnpaidAge(): array
    {
        global $conf, $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionUnpaidAge');
        $array['name']  = 'DirectionUnpaidAge';
        $array['picto'] = 'fontawesome_fa-hourglass-half_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'pie';
        $array['showlegend'] = ($conf->browser->layout ?? '') == 'phone' ? 1 : 2;
        $array['dataset']    = 1;

        // The buckets go from the invoices not due yet to the most late ones, from green to red
        $array['labels'] = [0 => ['label' => $langs->transnoentities('DirectionNotDueYet'), 'color' => '#32E592']];
        $colors          = ['#F4B942', '#FF9800', '#F44336', '#9C27B0'];
        $previousLimit   = 0;
        foreach (self::UNPAID_AGE_LIMITS as $index => $limit) {
            $array['labels'][$index + 1] = ['label' => $langs->transnoentities('DurationBetweenDays', $previousLimit + 1, $limit), 'color' => $colors[$index]];
            $previousLimit               = $limit;
        }
        $array['labels'][count(self::UNPAID_AGE_LIMITS) + 1] = ['label' => $langs->transnoentities('DurationOverDays', $previousLimit), 'color' => $colors[count(self::UNPAID_AGE_LIMITS)]];

        $array['data'] = array_fill(0, count($array['labels']), 0);

        $todayStart = dol_get_first_hour($this->now);
        foreach ($this->unpaidInvoices as $invoice) {
            $bucket = 0;
            if ($invoice->date_due > 0 && $invoice->date_due < $todayStart) {
                $delay  = (int) floor(($todayStart - $invoice->date_due) / 86400);
                $bucket = count(self::UNPAID_AGE_LIMITS) + 1;
                foreach (self::UNPAID_AGE_LIMITS as $index => $limit) {
                    if ($delay <= $limit) {
                        $bucket = $index + 1;
                        break;
                    }
                }
            }
            $array['data'][$bucket] += round($invoice->remaining);
        }

        return $array;
    }

    /**
     * Get the outstanding amount at the end of each of the last months
     *
     * @return array Graph of the evolution of the outstanding amount
     */
    protected function getOutstandingEvolution(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionOutstandingEvolution');
        $array['name']  = 'DirectionOutstandingEvolution';
        $array['picto'] = 'fontawesome_fa-chart-line_fas_#3bbfa8';

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'lines';
        $array['showlegend'] = 1;
        $array['dataset']    = 2;

        $array['labels'] = [['label' => $langs->transnoentities('DirectionOutstanding'), 'color' => '#A1467E']];

        foreach ($this->getMonthBuckets() as $key => $month) {
            $array['data'][] = [$month['label'], round($this->outstandingPerMonth[$key] ?? 0)];
        }

        return $array;
    }

    /**
     * Get the proposals and the amount each member of the team signed over the period
     *
     * @return array Graph of the signatures per sales representative
     */
    protected function getSignedPerUser(): array
    {
        global $langs;

        // Graph title parameters
        $array['title'] = $langs->transnoentities('DirectionSignedPerUser');
        $array['name']  = 'DirectionSignedPerUser';
        $array['picto'] = 'fontawesome_fa-user-tie_fas_#3bbfa8';

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

        $nbMonths = $this->getPeriodNbMonths();
        $links    = [];
        foreach ($this->getMemberIds() as $memberId) {
            $signed = $this->getSignedProposals($this->periodStart, $this->periodEnd, $memberId);

            $array['data'][] = [$this->getUser($memberId)->getFullName($langs), count($signed), reedcrm_sales_goals_get($memberId)['signed_per_month'] * $nbMonths, round(array_sum(array_column($signed, 'amount')))];
            $links[]         = DOL_URL_ROOT . '/comm/propal/list.php?search_status=' . Propal::STATUS_SIGNED . ',' . Propal::STATUS_BILLED . '&' . reedcrm_get_date_range_filter('search_date_signature', $this->periodStart, $this->periodEnd);
        }

        // An amount dwarfs a number of proposals on a shared scale, so it gets its own Y axis
        $array['morehtmlright'] = SaturneDashboard::getGraphOptionsInput(['links' => $links, 'secondAxisDataset' => 2]);

        return $array;
    }

    /**
     * Get the performance of each member of the team against their goals over the period
     *
     * @return array List of the members with their activity, their pipeline and their results
     */
    protected function getTeamPerformanceList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('DirectionTeamPerformance');
        $array['name']  = 'DirectionTeamPerformance';
        $array['picto'] = 'fontawesome_fa-users_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'           => 'User',
            'Calls'         => 'SalesCallsMade',
            'CallsPerDay'   => 'SalesCallsPerWorkingDay',
            'Opportunities' => 'SalesOpportunitiesCreated',
            'Issued'        => 'SalesProposalsIssued',
            'Signed'        => 'SalesProposalsSigned',
            'SignedAmount'  => 'SalesSignedAmount',
            'SignatureRate' => 'SalesSignatureRate',
            'Weighted'      => 'SalesWeightedPipeline',
            'WithoutAction' => 'SalesOpportunitiesWithoutAction'
        ];

        $workingDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodFullEnd);
        $elapsedDays = reedcrm_sales_count_working_days($this->periodStart, $this->periodEnd);
        $nbMonths    = $this->getPeriodNbMonths();
        $pace        = $this->getPeriodElapsedRatio();

        $rows = [];
        foreach ($this->getMemberIds() as $memberId) {
            $signed  = $this->getSignedProposals($this->periodStart, $this->periodEnd, $memberId);
            $issued  = 0;
            $refused = 0;
            foreach ($this->proposals as $proposal) {
                if ($proposal->fk_user != $memberId) {
                    continue;
                }
                $issued  += $this->isInPeriod($proposal->date_valid) ? 1 : 0;
                $refused += (!empty($proposal->date_refused) && $this->isInPeriod($proposal->date_refused)) ? 1 : 0;
            }

            $created       = 0;
            $weighted      = 0;
            $withoutAction = 0;
            foreach ($this->opportunities as $opportunity) {
                if ($opportunity->fk_user != $memberId) {
                    continue;
                }
                $created += $this->isInPeriod($opportunity->datec) ? 1 : 0;
                if ($opportunity->open) {
                    $weighted      += $opportunity->amount * $opportunity->percent / 100;
                    $withoutAction += empty($opportunity->nb_todo) ? 1 : 0;
                }
            }

            $rows[$memberId] = [
                'calls'         => $this->countCalls($this->periodStart, $this->periodEnd, $memberId),
                'created'       => $created,
                'issued'        => $issued,
                'signed'        => $signed,
                'refused'       => $refused,
                'weighted'      => $weighted,
                'withoutAction' => $withoutAction,
                'goals'         => reedcrm_sales_goals_get($memberId)
            ];
        }

        // The biggest signed amount first, the figure the management ranks a sales team on
        uasort($rows, function ($first, $second) {
            return array_sum(array_column($second['signed'], 'amount')) <=> array_sum(array_column($first['signed'], 'amount'));
        });

        $data = [];
        foreach ($rows as $memberId => $row) {
            $nbSigned = count($row['signed']);

            $data[$memberId] = [
                'Ref'           => ['value' => $this->getUser($memberId)->getNomUrl(-1), 'morecss' => 'left'],
                'Calls'         => ['value' => $row['calls'] . $this->getGoalProgress($row['calls'], $row['goals']['calls_per_day'] * $workingDays, $pace)],
                'CallsPerDay'   => ['value' => $this->formatNumber($elapsedDays > 0 ? $row['calls'] / $elapsedDays : 0)],
                'Opportunities' => ['value' => $row['created']],
                'Issued'        => ['value' => $row['issued']],
                'Signed'        => ['value' => $nbSigned . $this->getGoalProgress($nbSigned, $row['goals']['signed_per_month'] * $nbMonths, $pace)],
                'SignedAmount'  => ['value' => $this->formatAmount(array_sum(array_column($row['signed'], 'amount')))],
                'SignatureRate' => ['value' => $this->formatRate(count($row['signed']), count($row['signed']) + $row['refused'])],
                'Weighted'      => ['value' => $this->formatAmount($row['weighted'])],
                'WithoutAction' => ['value' => $row['withoutAction'] > 0 ? '<span class="badge badge-status1">' . $row['withoutAction'] . '</span>' : '0']
            ];
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the open opportunities weighing the most in the pipeline
     *
     * @return array List of the biggest open opportunities
     */
    protected function getTopOpportunitiesList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('DirectionTopOpportunities');
        $array['name']  = 'DirectionTopOpportunities';
        $array['picto'] = 'fontawesome_fa-trophy_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'          => 'Ref',
            'Label'        => 'Label',
            'ThirdParty'   => 'ThirdParty',
            'Status'       => 'OpportunityStatus',
            'Amount'       => 'OpportunityAmount',
            'Percent'      => 'OpportunityProbability',
            'Weighted'     => 'SalesWeightedPipeline',
            'NextAction'   => 'DirectionNextAction',
            'LastActivity' => 'LastActivity',
            'SalesRep'     => 'SalesRepresentative'
        ];

        $opportunities = array_filter($this->opportunities, function ($opportunity) {
            return $opportunity->open && $opportunity->amount > 0;
        });
        uasort($opportunities, function ($first, $second) {
            return ($second->amount * $second->percent) <=> ($first->amount * $first->percent);
        });

        $data = [];
        foreach (array_slice($opportunities, 0, self::LIST_LIMIT, true) as $opportunity) {
            $row = $this->buildOpportunityRow($opportunity);

            // The cells are printed in the order of the labels, the two extra ones are inserted where they belong
            $data[$opportunity->id] = [
                'Ref'          => $row['Ref'],
                'Label'        => $row['Label'],
                'ThirdParty'   => $row['ThirdParty'],
                'Status'       => $row['Status'],
                'Amount'       => $row['Amount'],
                'Percent'      => $row['Percent'],
                'Weighted'     => ['value' => $this->formatAmount($opportunity->amount * $opportunity->percent / 100)],
                'NextAction'   => ['value' => $opportunity->next_event > 0 ? dol_print_date($opportunity->next_event, 'day') : '<span class="badge badge-status1">' . $langs->transnoentities('None') . '</span>'],
                'LastActivity' => $row['LastActivity'],
                'SalesRep'     => $row['SalesRep']
            ];
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the follow-up of the recurring billing: every active template with its next invoice, the late first
     *
     * @return array List of the active recurring invoice templates
     */
    protected function getRecurringFollowUpList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('DirectionRecurringFollowUp');
        $array['name']  = 'DirectionRecurringFollowUp';
        $array['picto'] = 'fontawesome_fa-sync-alt_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'        => 'DirectionRecurringTemplate',
            'ThirdParty' => 'ThirdParty',
            'Frequency'  => 'Frequency',
            'Amount'     => 'AmountHT',
            'Monthly'    => 'DirectionMonthlyAmount',
            'NextDate'   => 'DirectionNextInvoice',
            'LastDate'   => 'DirectionLastInvoice',
            'Generated'  => 'DirectionInvoicesGenerated'
        ];

        $templates = $this->recurringTemplates;
        uasort($templates, function ($first, $second) {
            return ($first->date_when ?: PHP_INT_MAX) <=> ($second->date_when ?: PHP_INT_MAX);
        });

        $todayStart = dol_get_first_hour($this->now);
        $units      = ['d' => 'Days', 'w' => 'Weeks', 'm' => 'Months', 'y' => 'Years'];

        $data = [];
        foreach ($templates as $template) {
            $templateObject        = new FactureRec($this->db);
            $templateObject->id    = $template->id;
            $templateObject->ref   = $template->title;
            $templateObject->title = $template->title;

            if (empty($template->date_when)) {
                $nextDate = '-';
            } elseif ($template->date_when < $todayStart) {
                $nextDate = '<span class="badge badge-status8">' . dol_print_date($template->date_when, 'day') . '</span>';
            } else {
                $nextDate = dol_print_date($template->date_when, 'day');
            }

            $data[$template->id] = [
                'Ref'        => ['value' => $templateObject->getNomUrl(1), 'morecss' => 'left'],
                'ThirdParty' => ['value' => $this->getSocietyLink($template->fk_soc, $template->soc_name), 'morecss' => 'left'],
                'Frequency'  => ['value' => $template->frequency . ' ' . dol_strtolower($langs->transnoentities($units[$template->unit] ?? 'Months'))],
                'Amount'     => ['value' => $this->formatAmount($template->amount)],
                'Monthly'    => ['value' => $this->formatAmount($template->monthly)],
                'NextDate'   => ['value' => $nextDate],
                'LastDate'   => ['value' => $template->date_last_gen > 0 ? dol_print_date($template->date_last_gen, 'day') : '-'],
                'Generated'  => ['value' => $template->nb_gen_done . ($template->nb_gen_max > 0 ? ' / ' . $template->nb_gen_max : '')]
            ];
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the unpaid invoices weighing the most, the most late first
     *
     * @return array List of the unpaid invoices
     */
    protected function getUnpaidInvoicesList(): array
    {
        global $langs;

        // List title parameters
        $array['title'] = $langs->transnoentities('DirectionUnpaidInvoices');
        $array['name']  = 'DirectionUnpaidInvoices';
        $array['picto'] = 'fontawesome_fa-exclamation-triangle_fas_#3bbfa8';

        // List parameters
        $array['type']   = 'list';
        $array['labels'] = [
            'Ref'        => 'Ref',
            'ThirdParty' => 'ThirdParty',
            'Date'       => 'DateInvoice',
            'DueDate'    => 'DateDue',
            'Remaining'  => 'RemainderToPay',
            'Delay'      => 'SalesLateBy'
        ];

        $invoices = $this->unpaidInvoices;
        uasort($invoices, function ($first, $second) {
            return ($first->date_due ?: PHP_INT_MAX) <=> ($second->date_due ?: PHP_INT_MAX);
        });

        $todayStart = dol_get_first_hour($this->now);

        $data = [];
        foreach (array_slice($invoices, 0, self::LIST_LIMIT, true) as $invoice) {
            $invoiceObject            = new Facture($this->db);
            $invoiceObject->id        = $invoice->id;
            $invoiceObject->ref       = $invoice->ref;
            $invoiceObject->type      = $invoice->type;
            $invoiceObject->statut    = Facture::STATUS_VALIDATED;
            $invoiceObject->status    = Facture::STATUS_VALIDATED;

            $late = $invoice->date_due > 0 && $invoice->date_due < $todayStart;

            $data[$invoice->id] = [
                'Ref'        => ['value' => $invoiceObject->getNomUrl(1), 'morecss' => 'left'],
                'ThirdParty' => ['value' => $this->getSocietyLink($invoice->fk_soc, $invoice->soc_name), 'morecss' => 'left'],
                'Date'       => ['value' => dol_print_date($invoice->datef, 'day')],
                'DueDate'    => ['value' => $invoice->date_due > 0 ? dol_print_date($invoice->date_due, 'day') : '-'],
                'Remaining'  => ['value' => price($invoice->remaining, 0, $langs, 1, -1, -1, 'auto')],
                'Delay'      => ['value' => $late ? '<span class="badge badge-status8">' . $langs->transnoentities('SalesNbDays', (int) floor(($todayStart - $invoice->date_due) / 86400)) . '</span>' : '<span class="opacitymedium">' . $langs->transnoentities('DirectionNotDueYet') . '</span>']
            ];
        }

        $array['data'] = $data;

        return $array;
    }

    /**
     * Get the frame of a graph putting the months of the last fiscal years side by side
     *
     * @param  string $name  Name of the graph
     * @param  string $picto Picto of the graph
     * @return array         Graph parameters, labels and one row per month holding a 0 per fiscal year
     */
    protected function getFiscalYearsGraph(string $name, string $picto): array
    {
        $array['name']  = $name;
        $array['picto'] = $picto;

        // Graph parameters
        $array['width']      = '100%';
        $array['height']     = 300;
        $array['type']       = 'bar';
        $array['showlegend'] = 1;
        $array['dataset']    = self::NB_FISCAL_YEARS + 1;

        // The oldest year first, as in the proposal statistics of Dolibarr
        $array['labels'] = [];
        for ($offset = self::NB_FISCAL_YEARS - 1; $offset >= 0; $offset--) {
            $array['labels'][] = ['label' => $this->getFiscalYearLabel($offset), 'color' => self::FISCAL_YEAR_COLORS[self::NB_FISCAL_YEARS - 1 - $offset]];
        }

        $fiscalStart = $this->getFiscalYearStart();
        for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) {
            $array['data'][$monthIndex] = array_merge([dol_print_date(dol_time_plus_duree($fiscalStart, $monthIndex, 'm'), '%b')], array_fill(0, self::NB_FISCAL_YEARS, 0));
        }

        return $array;
    }

    /**
     * Get the months of the last fiscal years, the oldest year first
     *
     * @return array For each fiscal year, its twelve months holding their key (Y-m) and their bounds
     */
    protected function getFiscalYearsMonths(): array
    {
        $years = [];
        for ($offset = self::NB_FISCAL_YEARS - 1; $offset >= 0; $offset--) {
            $fiscalStart = $this->getFiscalYearStart($offset);

            $months = [];
            for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) {
                $monthStart = dol_time_plus_duree($fiscalStart, $monthIndex, 'm');
                $months[]   = [
                    'key'   => dol_print_date($monthStart, '%Y-%m'),
                    'start' => $monthStart,
                    'end'   => dol_get_last_day((int) dol_print_date($monthStart, '%Y'), (int) dol_print_date($monthStart, '%m'))
                ];
            }
            $years[] = $months;
        }

        return $years;
    }

    /**
     * Sum the revenue billed between two dates
     *
     * @param  int   $start Timestamp the range starts at
     * @param  int   $end   Timestamp the range ends at
     * @return array        Total and recurring part of the revenue
     */
    protected function sumRevenue(int $start, int $end): array
    {
        // The revenue is loaded per month: a range is measured on the whole months it touches
        $firstMonth = dol_print_date($start, '%Y-%m');
        $lastMonth  = dol_print_date($end, '%Y-%m');

        $sum = ['total' => 0, 'recurring' => 0];
        foreach ($this->revenuePerMonth as $month => $revenue) {
            if ($month >= $firstMonth && $month <= $lastMonth) {
                $sum['total']     += $revenue['total'];
                $sum['recurring'] += $revenue['recurring'];
            }
        }

        return $sum;
    }

    /**
     * Get the payments received over the period and the delay the invoices they settled took to be paid
     *
     * @return array Amount collected and mean payment delay in days of the invoices fully paid over the period
     */
    protected function getPaymentsOfPeriod(): array
    {
        $result = ['amount' => 0, 'delay' => 0];

        [$salesRepJoin, $salesRepSql] = $this->getInvoiceSalesRepSql();

        $sql  = 'SELECT SUM(pf.amount) AS amount';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture AS pf';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement AS p ON p.rowid = pf.fk_paiement';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture AS f ON f.rowid = pf.fk_facture';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= " AND p.datep >= '" . $this->db->idate($this->periodStart) . "'";
        $sql .= " AND p.datep <= '" . $this->db->idate($this->periodEnd) . "'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj              = $this->db->fetch_object($resql);
            $result['amount'] = (float) ($obj->amount ?? 0);
            $this->db->free($resql);
        }

        // The delay of an invoice runs from its date to the payment that settled it, the last one
        $sql  = 'SELECT f.rowid, f.datef, MAX(p.datep) AS last_payment';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture AS f';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement_facture AS pf ON pf.fk_facture = f.rowid';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement AS p ON p.rowid = pf.fk_paiement';
        $sql .= $salesRepJoin;
        $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
        $sql .= $this->getScopeSql($salesRepSql);
        $sql .= ' AND f.paye = 1 AND f.type IN (' . implode(',', self::RECEIVABLE_INVOICE_TYPES) . ')';
        $sql .= ' GROUP BY f.rowid, f.datef';
        $sql .= " HAVING MAX(p.datep) >= '" . $this->db->idate($this->periodStart) . "'";
        $sql .= " AND MAX(p.datep) <= '" . $this->db->idate($this->periodEnd) . "'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $delays = [];
            while ($obj = $this->db->fetch_object($resql)) {
                $delays[] = max(0, (int) $this->db->jdate($obj->last_payment) - (int) $this->db->jdate($obj->datef));
            }
            $this->db->free($resql);
            if (!empty($delays)) {
                $result['delay'] = (int) round(array_sum($delays) / count($delays) / 86400);
            }
        }

        return $result;
    }

    /**
     * Get the signed proposals nothing billed yet, with the rule of the "not billed" tag of the proposals
     *
     * @return array Number and amount excluding tax of the signed proposals left to bill
     */
    protected function getSignedUnbilled(): array
    {
        $result = ['nb' => 0, 'amount' => 0];

        $sql  = 'SELECT COUNT(pr.rowid) AS nb, SUM(pr.total_ht) AS amount';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal AS pr';
        $sql .= $this->getSalesRepJoin('propal', 'pr.rowid', ['SALESREPINTERNAL', 'SALESREPFOLL']);
        $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ')';
        $sql .= $this->getScopeSql('COALESCE(sr.rep0, sr.rep1, pr.fk_user_author)');
        $sql .= ' AND ' . reedcrmPropalUnbilledSql();

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj    = $this->db->fetch_object($resql);
            $result = ['nb' => (int) ($obj->nb ?? 0), 'amount' => (float) ($obj->amount ?? 0)];
            $this->db->free($resql);
        } else {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }

        return $result;
    }

    /**
     * Get the translated labels of a dictionary, keyed by rowid
     *
     * @param  string $table       Dictionary table, without its prefix
     * @param  string $transPrefix Prefix of the translation key built on the code, empty to translate the label
     * @return array<int,string>   Label of each active entry
     */
    protected function getDictionaryLabels(string $table, string $transPrefix = ''): array
    {
        global $langs;

        $labels = [];

        $sql  = 'SELECT rowid, label' . (!empty($transPrefix) ? ', code' : '') . ' FROM ' . MAIN_DB_PREFIX . $table;
        $sql .= ' WHERE active = 1';

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $key   = !empty($transPrefix) ? $transPrefix . $obj->code : (string) $obj->label;
                $label = $langs->transnoentities($key);

                $labels[(int) $obj->rowid] = $label !== $key ? $label : (string) $obj->label;
            }
            $this->db->free($resql);
        }

        return $labels;
    }
}
