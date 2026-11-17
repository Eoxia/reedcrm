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
 * \brief   Card of the time planned and spent on a project: the totals of the year in the header, one column of bars
 *          underneath (planned in blue, spent in green up to the planned time, in red beyond), one year at a time.
 *          Rendered by the printCommonFooter hook on the project card and by ajax/save_time_spent.php (inherits their scope):
 *          the JS moves it at the top right of the card. Nothing is shown before time is planned or spent.
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

$timeTableCurrentYear  = (int) dol_print_date(dol_now(), '%Y');
$timeTableCurrentMonth = (int) dol_print_date(dol_now(), '%m');
$timeTableShownYear    = isset($timeTableYears[$timeTableCurrentYear]) ? $timeTableCurrentYear : max(array_keys($timeTableYears));
?>
<div id="reedcrm-project-time" class="reedcrm-project-time" hidden>
    <div class="reedcrm-project-time-header">
        <?php // Short title, so that the totals fit on its line ?>
        <span class="reedcrm-project-time-title" title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimeTitle')); ?>"><i class="far fa-clock"></i> <?php echo $langs->trans('ReedCRMProjectTimeTitleShort'); ?></span>
        <span class="reedcrm-project-time-years">
            <button type="button" class="reedcrm-project-time-year-nav" data-step="-1" title="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>"><i class="fas fa-chevron-left"></i></button>
            <span class="reedcrm-project-time-year"><?php echo $timeTableShownYear; ?></span>
            <button type="button" class="reedcrm-project-time-year-nav" data-step="1" title="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>"><i class="fas fa-chevron-right"></i></button>
        </span>
        <?php foreach ($timeTableYears as $timeTableYear => $timeTableMonths) {
            $timeTableHidden = $timeTableYear === $timeTableShownYear ? '' : ' hidden';
            $timeTableTotals = reedcrm_timespent_get_year_totals($timeTableMonths); ?>
            <?php // Beside the title to save a line, the square of each total doubling as the legend of the bars ?>
            <span class="reedcrm-project-time-kpis" data-year="<?php echo $timeTableYear; ?>"<?php echo $timeTableHidden; ?>>
                <span class="reedcrm-project-time-kpi reedcrm-project-time-kpi-planned classfortooltip" title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimePlannedHelp')); ?>">
                    <?php echo $langs->trans('ReedCRMProjectTimePlannedShort'); ?> <strong><?php echo reedcrm_timespent_format_hours_html($timeTableTotals['planned']); ?></strong>
                </span>
                <span class="reedcrm-project-time-kpi reedcrm-project-time-kpi-spent" title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimeSpent') . ' - ' . $langs->trans('ReedCRMProjectTimeYearTotal') . ' - ' . $langs->trans('ReedCRMProjectTimeMonthlyAverage', reedcrm_timespent_format_hours_short($timeTableTotals['average']))); ?>">
                    <?php echo $langs->trans('ReedCRMProjectTimeSpentShort'); ?> <strong><?php echo reedcrm_timespent_format_hours_html($timeTableTotals['spent']); ?></strong>
                </span>
                <?php if (round($timeTableTotals['over'] / 60) > 0) { ?>
                    <span class="reedcrm-project-time-kpi reedcrm-project-time-kpi-over" title="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimeOver')); ?>">
                        <?php echo $langs->trans('ReedCRMProjectTimeOverShort'); ?> <strong><?php echo reedcrm_timespent_format_hours_html($timeTableTotals['over']); ?></strong>
                    </span>
                <?php } ?>
            </span>
        <?php } ?>
    </div>

    <?php foreach ($timeTableYears as $timeTableYear => $timeTableMonths) {
        $timeTableMax = max(max($timeTableMonths['planned']), max($timeTableMonths['spent'])); ?>
        <div class="reedcrm-project-time-chart" data-year="<?php echo $timeTableYear; ?>" role="img" aria-label="<?php echo dol_escape_htmltag($langs->trans('ReedCRMProjectTimeChartTitle')); ?>"<?php echo $timeTableYear === $timeTableShownYear ? '' : ' hidden'; ?>>
            <?php for ($month = 1; $month <= 12; $month++) {
                $timeTablePlannedMonth = $timeTableMonths['planned'][$month];
                $timeTableSpentMonth   = $timeTableMonths['spent'][$month];
                $timeTableBars         = reedcrm_timespent_get_month_bars($timeTablePlannedMonth, $timeTableSpentMonth, $timeTableMax);
                $timeTableMonthLabel   = $langs->trans('MonthShort' . sprintf('%02d', $month));
                // Plain text, escaped once when printed: trans() would turn the "û" of "Août" into an entity, which the
                // translation of the tooltip would encode a second time
                $timeTableTooltipArgs  = [$langs->transnoentitiesnoconv('MonthShort' . sprintf('%02d', $month)), reedcrm_timespent_format_hours($timeTablePlannedMonth), reedcrm_timespent_format_hours($timeTableSpentMonth)];
                $timeTableTooltip      = $timeTableBars['over'] > 0 ? $langs->transnoentitiesnoconv('ReedCRMProjectTimeBarOverTitle', ...array_merge($timeTableTooltipArgs, [reedcrm_timespent_format_hours($timeTableSpentMonth - $timeTablePlannedMonth)])) : $langs->transnoentitiesnoconv('ReedCRMProjectTimeBarTitle', ...$timeTableTooltipArgs);
                $timeTableMonthClasses = ['reedcrm-project-time-month'];
                if ($timeTableYear === $timeTableCurrentYear && $month === $timeTableCurrentMonth) {
                    $timeTableMonthClasses[] = 'reedcrm-project-time-current';
                }
                if ($timeTableBars['over'] > 0) {
                    $timeTableMonthClasses[] = 'reedcrm-project-time-month-over';
                } ?>
                <div class="<?php echo implode(' ', $timeTableMonthClasses); ?>" title="<?php echo dol_escape_htmltag($timeTableTooltip); ?>">
                    <?php // One bar: the planned time in light blue, the time spent over it in blue, what goes beyond in red on top ?>
                    <span class="reedcrm-project-time-track">
                        <span class="reedcrm-project-time-bar-planned reedcrm-project-time-h<?php echo $timeTableBars['planned']; ?>"></span>
                        <span class="reedcrm-project-time-bar-spent reedcrm-project-time-h<?php echo $timeTableBars['within']; ?>"></span>
                        <?php if ($timeTableBars['over'] > 0) { ?>
                            <span class="reedcrm-project-time-bar-over reedcrm-project-time-h<?php echo $timeTableBars['over']; ?> reedcrm-project-time-b<?php echo $timeTableBars['within']; ?>"></span>
                        <?php } ?>
                        <?php if (round($timeTableSpentMonth / 60) > 0) { ?>
                            <span class="reedcrm-project-time-value reedcrm-project-time-b<?php echo max($timeTableBars['planned'], $timeTableBars['within'] + $timeTableBars['over']); ?>"><?php echo reedcrm_timespent_format_hours_short($timeTableSpentMonth); ?></span>
                        <?php } ?>
                    </span>
                    <span class="reedcrm-project-time-label"><?php echo $timeTableMonthLabel; ?></span>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
