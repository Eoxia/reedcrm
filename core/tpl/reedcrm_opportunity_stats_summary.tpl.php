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
 * \file    core/tpl/reedcrm_opportunity_stats_summary.tpl.php
 * \ingroup reedcrm
 * \brief   Figures of the running year put forward above the monthly table.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $langs;

$monthNames = monthArray($langs, 0);
?>

<div class="reedcrm-oppstats-tiles">
    <div class="reedcrm-oppstats-tile">
        <span class="reedcrm-oppstats-tile-key"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsTotalYear', $runningYear)); ?></span>
        <span class="reedcrm-oppstats-tile-value"><?php echo (int) $highlights['total']; ?></span>
    </div>

    <div class="reedcrm-oppstats-tile">
        <span class="reedcrm-oppstats-tile-key"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsPerMonth')); ?></span>
        <span class="reedcrm-oppstats-tile-value"><?php echo price($highlights['average'], 0, $langs, 1, -1, 2); ?></span>
    </div>

    <div class="reedcrm-oppstats-tile">
        <span class="reedcrm-oppstats-tile-key"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsBestMonth')); ?></span>
        <span class="reedcrm-oppstats-tile-value">
            <?php echo $highlights['best_count'] > 0
                ? dol_escape_htmltag($monthNames[$highlights['best_month']]) . ' <small>' . (int) $highlights['best_count'] . '</small>'
                : '&mdash;'; ?>
        </span>
    </div>
</div>
