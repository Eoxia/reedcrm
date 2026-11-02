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
 * \file    core/tpl/reedcrm_opportunity_stats_table.tpl.php
 * \ingroup reedcrm
 * \brief   Opportunities of a tag counted month by month, one row per year.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $langs;

$monthShort   = monthArray($langs, 1);
$monthLong    = monthArray($langs, 0);
$runningMonth = (int) dol_print_date(dol_now(), '%m');

/**
 * Shade a count against the busiest month of the table, on five steps.
 *
 * The scale is relative: what matters to the eye is which months stand out from the others, not the absolute
 * figure, which the cell already carries.
 *
 * @param  int $count Count of the cell
 * @param  int $max   Busiest month of the whole table
 * @return int        0 for an empty cell, 1 to 5 otherwise
 */
$heatLevel = function (int $count, int $max): int {
    if ($count <= 0 || $max <= 0) {
        return 0;
    }

    return (int) max(1, ceil(($count / $max) * 5));
};
?>

<div class="reedcrm-oppstats-tablewrap">
    <table class="reedcrm-oppstats-table">
        <thead>
            <tr>
                <th class="reedcrm-oppstats-th-year"><?php echo dol_escape_htmltag($langs->trans('Year')); ?></th>
                <th class="reedcrm-oppstats-th-sum"><?php echo dol_escape_htmltag($langs->trans('Total')); ?></th>
                <th class="reedcrm-oppstats-th-sum reedcrm-oppstats-avg"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsAverage')); ?></th>
                <?php for ($month = 1; $month <= 12; $month++) { ?>
                    <th class="reedcrm-oppstats-month" title="<?php echo dol_escape_htmltag($monthLong[$month]); ?>">
                        <?php echo dol_escape_htmltag($monthShort[$month]); ?>
                    </th>
                <?php } ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($stats['years'] as $year => $row) { ?>
                <tr<?php echo $year === $runningYear ? ' class="reedcrm-oppstats-running"' : ''; ?>>
                    <th scope="row" class="reedcrm-oppstats-th-year"><?php echo (int) $year; ?></th>
                    <td class="reedcrm-oppstats-sum"><?php echo (int) $row['total']; ?></td>
                    <?php
                    // The year before the oldest row is counted but never shown, so every row but a year without
                    // any opportunity before it carries a comparison
                    $badgeClass = 'reedcrm-oppstats-badge';
                    $badgeTitle = $langs->transnoentities('OpportunityStatsNoComparison');
                    $badgeText  = '&mdash;';

                    if ($row['delta'] !== null) {
                        $badgeClass .= $row['delta'] > 0 ? ' up' : ($row['delta'] < 0 ? ' down' : ' flat');
                        $badgeText   = dol_escape_htmltag(($row['delta'] > 0 ? '+' : '') . price($row['delta'], 0, $langs, 1, -1, 1) . ' %');
                        $badgeTitle  = $langs->transnoentities('OpportunityStatsVersusYear', $year - 1)
                            . ' : ' . $langs->transnoentities('OpportunityStatsSameWindow', $row['previous_total']);
                    }
                    ?>
                    <?php // No whitespace between the two spans, it would widen the gap the stylesheet sets ?>
                    <td class="reedcrm-oppstats-sum reedcrm-oppstats-avg"><span class="reedcrm-oppstats-avg-value"><?php echo price($row['average'], 0, $langs, 1, -1, 2); ?></span><span class="<?php echo $badgeClass; ?>" title="<?php echo dol_escape_htmltag($badgeTitle); ?>"><?php echo $badgeText; ?></span></td>

                    <?php for ($month = 1; $month <= 12; $month++) {
                        // A month the running year has not reached yet stays blank, an empty past month reads 0
                        $isAhead = $year === $runningYear && $month > $runningMonth;
                        $count   = (int) $row['months'][$month];
                        $classes = 'reedcrm-oppstats-month';
                        if ($isAhead) {
                            $classes .= ' reedcrm-oppstats-ahead';
                        } else {
                            $classes .= ' reedcrm-oppstats-heat-' . $heatLevel($count, (int) $stats['max']);
                        }
                        $cellTitle = $monthLong[$month] . ' ' . $year;
                        ?>
                        <td class="<?php echo $classes; ?>" title="<?php echo dol_escape_htmltag($cellTitle); ?>">
                            <?php echo $isAhead ? '' : $count; ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<p class="reedcrm-oppstats-note">
    <?php echo dol_escape_htmltag($langs->trans('OpportunityStatsAverageHint')); ?>
</p>
