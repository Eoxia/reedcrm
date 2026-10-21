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
 * \file    lib/reedcrm_opportunity_stats.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the monthly count of the opportunities of a tag.
 */

/**
 * Date of the project the months are counted on.
 *
 * A project imported from a web form is created the day of the import but carries the day the lead arrived in
 * its start date, so the two answer different questions and the page lets the reader pick.
 */
const REEDCRM_OPPORTUNITY_STATS_DATE_FIELDS = ['datec', 'dateo'];

/**
 * Number of past years the page can look back on, the first one being the default.
 */
const REEDCRM_OPPORTUNITY_STATS_YEAR_SPANS = [2, 3, 5, 10];

/**
 * Tags a project can carry, to fill the tag selector of the page.
 *
 * @param  DoliDB           $db Database handler
 * @return array<int,string>    Label of each project category, keyed by rowid
 */
function reedcrmOpportunityStatsGetProjectTags(DoliDB $db): array
{
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

    $category = new Categorie($db);
    $typeID   = $category->MAP_ID[Categorie::TYPE_PROJECT] ?? 0;
    $tags     = [];

    $sql  = 'SELECT rowid, label FROM ' . MAIN_DB_PREFIX . 'categorie';
    $sql .= ' WHERE type = ' . (int) $typeID;
    $sql .= ' AND entity IN (' . getEntity('category') . ')';
    $sql .= ' ORDER BY label';

    $resql = $db->query($sql);
    if (!$resql) {
        return $tags;
    }

    while ($obj = $db->fetch_object($resql)) {
        $tags[(int) $obj->rowid] = $obj->label;
    }
    $db->free($resql);

    return $tags;
}

/**
 * Count the opportunities of a tag, month by month, over a range of years.
 *
 * One row per year, twelve counts plus the total, the average and the evolution against the year before, the way
 * a spreadsheet would lay it out. The average divides by the months actually elapsed, so the running year is not
 * dragged down by the months it has not lived yet.
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $tagID     Rowid of the project category, 0 for every opportunity whatever its tags
 * @param  int    $firstYear First year of the range
 * @param  int    $lastYear  Last year of the range, usually the running one
 * @param  string $dateField Date the months are counted on, one of REEDCRM_OPPORTUNITY_STATS_DATE_FIELDS
 * @return array             ['years' => [year => ['months' => [1..12 => int], 'total' => int,
 *                           'months_counted' => int, 'average' => float, 'previous_total' => int,
 *                           'delta' => float|null]], 'max' => int]
 */
function reedcrmOpportunityStatsGetMonthlyCounts(DoliDB $db, int $tagID, int $firstYear, int $lastYear, string $dateField): array
{
    if (!in_array($dateField, REEDCRM_OPPORTUNITY_STATS_DATE_FIELDS, true)) {
        $dateField = REEDCRM_OPPORTUNITY_STATS_DATE_FIELDS[0];
    }

    $now         = dol_now();
    $runningYear = (int) dol_print_date($now, '%Y');
    $stats       = ['years' => [], 'max' => 0];

    // The year below the range is counted too, only to give the oldest displayed row something to compare with
    $countedFrom = $firstYear - 1;
    $years       = [];

    for ($year = $lastYear; $year >= $countedFrom; $year--) {
        $years[$year] = [
            'months'         => array_fill(1, 12, 0),
            'total'          => 0,
            'months_counted' => $year === $runningYear ? (int) dol_print_date($now, '%m') : 12,
            'average'        => 0.0,
            'previous_total' => 0,
            'delta'          => null,
        ];
    }

    // A date range rather than YEAR() on the column, so the index of the date stays usable
    $rangeStart = dol_mktime(0, 0, 0, 1, 1, $countedFrom, 'tzserver');
    $rangeEnd   = dol_mktime(23, 59, 59, 12, 31, $lastYear, 'tzserver');

    $sql  = 'SELECT YEAR(p.' . $dateField . ') as y, MONTH(p.' . $dateField . ') as m, COUNT(DISTINCT p.rowid) as nb';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet as p';
    if ($tagID > 0) {
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_project as cp ON cp.fk_project = p.rowid';
        $sql .= ' AND cp.fk_categorie = ' . $tagID;
    }
    $sql .= ' WHERE p.entity IN (' . getEntity('project') . ')';
    $sql .= ' AND p.usage_opportunity = 1';
    $sql .= " AND p." . $dateField . " >= '" . $db->idate($rangeStart) . "'";
    $sql .= " AND p." . $dateField . " <= '" . $db->idate($rangeEnd) . "'";
    $sql .= ' GROUP BY y, m';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrmOpportunityStatsGetMonthlyCounts ' . $db->lasterror(), LOG_ERR);
        return $stats;
    }

    while ($obj = $db->fetch_object($resql)) {
        $year  = (int) $obj->y;
        $month = (int) $obj->m;
        if (!isset($years[$year]) || $month < 1 || $month > 12) {
            continue;
        }

        $count = (int) $obj->nb;

        $years[$year]['months'][$month] = $count;
        $years[$year]['total']         += $count;
    }
    $db->free($resql);

    foreach ($years as $year => $row) {
        $years[$year]['average'] = $row['months_counted'] > 0
            ? $row['total'] / $row['months_counted']
            : 0.0;

        // Same window on both years, otherwise a year in progress would always look like a collapse next to a full one
        if (!isset($years[$year - 1])) {
            continue;
        }

        $previousTotal = 0;
        for ($month = 1; $month <= $row['months_counted']; $month++) {
            $previousTotal += (int) $years[$year - 1]['months'][$month];
        }

        $years[$year]['previous_total'] = $previousTotal;
        if ($previousTotal > 0) {
            $years[$year]['delta'] = (($row['total'] - $previousTotal) / $previousTotal) * 100;
        }
    }

    // The extra year has played its part, and the heat scale must not be set by a year nobody sees
    unset($years[$countedFrom]);
    $stats['years'] = $years;

    foreach ($years as $row) {
        $stats['max'] = max($stats['max'], ...array_values($row['months']));
    }

    return $stats;
}

/**
 * The few figures the head of the page puts forward, read off the counts already gathered.
 *
 * The comparison with the previous year is not among them: it belongs to the table, where it is given year by
 * year next to the total it comments.
 *
 * @param  array $stats       Return of reedcrmOpportunityStatsGetMonthlyCounts()
 * @param  int   $runningYear Year the page considers as running
 * @return array              ['total' => int, 'average' => float, 'best_month' => int, 'best_count' => int]
 */
function reedcrmOpportunityStatsGetHighlights(array $stats, int $runningYear): array
{
    $current = $stats['years'][$runningYear] ?? null;
    if ($current === null) {
        return ['total' => 0, 'average' => 0.0, 'best_month' => 0, 'best_count' => 0];
    }

    $bestMonth = 0;
    $bestCount = 0;
    foreach ($current['months'] as $month => $count) {
        if ($count > $bestCount) {
            $bestMonth = $month;
            $bestCount = $count;
        }
    }

    return [
        'total'      => $current['total'],
        'average'    => $current['average'],
        'best_month' => $bestMonth,
        'best_count' => $bestCount,
    ];
}
