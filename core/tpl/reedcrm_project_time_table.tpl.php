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
 * \file    core/tpl/reedcrm_project_time_table.tpl.php
 * \ingroup reedcrm
 * \brief   Monthly table of the time planned and spent on a project, under a mini chart of both, one table per year.
 *          Rendered by the printCommonFooter hook on the project card and by ajax/save_time_spent.php (inherits their scope):
 *          the JS moves it at the bottom of the card. Nothing is shown before time is planned or spent.
 *          Expects: $object (Project), $db, $langs.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

require_once __DIR__ . '/../../lib/reedcrm_timespent.lib.php';

$timeTableYears = reedcrm_timespent_get_project_monthly($db, $object);
if (empty($timeTableYears)) {
    return;
}

$timeTableCurrentYear = (int) dol_print_date(dol_now(), '%Y');
$timeTableShownYear   = isset($timeTableYears[$timeTableCurrentYear]) ? $timeTableCurrentYear : max(array_keys($timeTableYears));
$timeTableRows        = ['planned' => 'ReedCRMProjectTimePlanned', 'spent' => 'ReedCRMProjectTimeSpent', 'gap' => 'ReedCRMProjectTimeGap'];
?>
<div id="reedcrm-project-time" class="reedcrm-project-time" hidden>
    <div class="reedcrm-project-time-header">
        <span class="reedcrm-project-time-title"><i class="far fa-clock"></i> <?php echo $langs->trans('ReedCRMProjectTimeTitle'); ?></span>
        <span class="reedcrm-project-time-years">
            <button type="button" class="reedcrm-project-time-year-nav" data-step="-1" title="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>"><i class="fas fa-chevron-left"></i></button>
            <span class="reedcrm-project-time-year"><?php echo $timeTableShownYear; ?></span>
            <button type="button" class="reedcrm-project-time-year-nav" data-step="1" title="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>"><i class="fas fa-chevron-right"></i></button>
        </span>
        <span class="reedcrm-project-time-legend">
            <span class="reedcrm-project-time-legend-planned classfortooltip" title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimePlannedHelp')); ?>"><?php echo $langs->trans('ReedCRMProjectTimePlannedShort'); ?></span>
            <span class="reedcrm-project-time-legend-spent"><?php echo $langs->trans('ReedCRMProjectTimeSpentShort'); ?></span>
        </span>
    </div>

    <?php foreach ($timeTableYears as $timeTableYear => $timeTableMonths) {
        $timeTableMonths['gap'] = [];
        $timeTableActive        = 0;
        for ($month = 1; $month <= 12; $month++) {
            $timeTableMonths['gap'][$month] = $timeTableMonths['spent'][$month] - $timeTableMonths['planned'][$month];
            $timeTableActive += ($timeTableMonths['planned'][$month] > 0 || $timeTableMonths['spent'][$month] > 0) ? 1 : 0;
        }
        $timeTableAxisMax = reedcrm_timespent_chart_axis_max($timeTableMonths); ?>
        <div class="reedcrm-project-time-scroll" data-year="<?php echo $timeTableYear; ?>"<?php echo $timeTableYear === $timeTableShownYear ? '' : ' hidden'; ?>>
            <table class="reedcrm-project-time-table">
                <colgroup>
                    <col class="reedcrm-project-time-col-label">
                    <?php for ($month = 1; $month <= 12; $month++) { ?>
                        <col>
                    <?php } ?>
                    <col class="reedcrm-project-time-col-sum">
                    <col class="reedcrm-project-time-col-sum">
                </colgroup>
                <tbody>
                    <?php // The chart spans the month columns, so that each pair of bars stands above the figures of its month ?>
                    <tr class="reedcrm-project-time-chart-row">
                        <td class="reedcrm-project-time-chart-axis"><span><?php echo reedcrm_timespent_format_hours($timeTableAxisMax); ?></span><span>0h</span></td>
                        <td colspan="12"><?php echo reedcrm_timespent_render_project_chart($timeTableMonths, $timeTableAxisMax); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="reedcrm-project-time-months">
                        <th></th>
                        <?php for ($month = 1; $month <= 12; $month++) { ?>
                            <th><?php echo $langs->trans('MonthShort' . sprintf('%02d', $month)); ?></th>
                        <?php } ?>
                        <th title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimeYearTotal')); ?>"><?php echo $langs->trans('Total'); ?></th>
                        <th title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimeMonthlyAverage')); ?>"><?php echo $langs->trans('ReedCRMProjectTimeMonthlyAverageShort'); ?></th>
                    </tr>
                    <?php foreach ($timeTableRows as $timeTableRow => $timeTableLabel) {
                        $timeTableTotal = array_sum($timeTableMonths[$timeTableRow]);
                        $timeTableCells = array_merge(array_values($timeTableMonths[$timeTableRow]), [$timeTableTotal, $timeTableTotal / max(1, $timeTableActive)]); ?>
                        <tr class="reedcrm-project-time-row-<?php echo $timeTableRow; ?>">
                            <td title="<?php echo dol_escape_htmltag($langs->trans($timeTableLabel)); ?>"><?php echo $langs->trans($timeTableLabel . 'Short'); ?></td>
                            <?php foreach ($timeTableCells as $timeTableIndex => $timeTableSeconds) {
                                $timeTableClasses = $timeTableIndex >= 12 ? ['reedcrm-project-time-sum'] : [];
                                if ($timeTableRow === 'gap' && round($timeTableSeconds / 60) != 0) {
                                    $timeTableClasses[] = $timeTableSeconds > 0 ? 'reedcrm-project-time-over' : 'reedcrm-project-time-under';
                                } ?>
                                <?php if (round($timeTableSeconds / 60) != 0) { ?>
                                    <td<?php echo $timeTableClasses ? ' class="' . implode(' ', $timeTableClasses) . '"' : ''; ?> title="<?php echo reedcrm_timespent_format_hours($timeTableSeconds, $timeTableRow === 'gap'); ?>"><?php echo reedcrm_timespent_format_hours_short($timeTableSeconds, $timeTableRow === 'gap'); ?></td>
                                <?php } else { ?>
                                    <td<?php echo $timeTableClasses ? ' class="' . implode(' ', $timeTableClasses) . '"' : ''; ?>><span class="opacitymedium">-</span></td>
                                <?php } ?>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
