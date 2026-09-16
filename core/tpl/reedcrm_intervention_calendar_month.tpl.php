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
 * \file    core/tpl/reedcrm_intervention_calendar_month.tpl.php
 * \ingroup reedcrm
 * \brief   Month grid of the intervention calendar, one chip per intervention.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $langs;

// The week starts where the user asked it to, the grid is padded with the days around the month
$startWeekDay   = getDolGlobalInt('MAIN_START_WEEK', 1);
$firstDayOfWeek = (int) dol_print_date($firstDay, '%w');
$offset         = ($firstDayOfWeek - $startWeekDay + 7) % 7;
$gridStart      = dol_time_plus_duree($firstDay, -$offset, 'd');
$daysInMonth    = (int) dol_print_date($lastDay, '%d');
$totalCells     = (int) (ceil(($offset + $daysInMonth) / 7) * 7);
$todayKey       = dol_print_date($now, '%Y-%m-%d');
?>

<div class="reedcrm-calendar">
    <div class="reedcrm-calendar-head">
        <?php for ($weekDay = 0; $weekDay < 7; $weekDay++) { ?>
            <div class="reedcrm-calendar-head-day"><?php echo dol_escape_htmltag($langs->trans('Day' . (($startWeekDay + $weekDay) % 7))); ?></div>
        <?php } ?>
    </div>

    <div class="reedcrm-calendar-body">
        <?php for ($cell = 0; $cell < $totalCells; $cell++) {
            $day      = dol_time_plus_duree($gridStart, $cell, 'd');
            $dayKey   = dol_print_date($day, '%Y-%m-%d');
            $dayRows  = $rowsByDay[$dayKey] ?? [];
            $inMonth  = (int) dol_print_date($day, '%m') === (int) $month;
            $isWeekend = in_array((int) dol_print_date($day, '%w'), [0, 6], true);

            $cellClass  = 'reedcrm-calendar-day';
            $cellClass .= $inMonth ? '' : ' reedcrm-calendar-day-out';
            $cellClass .= $isWeekend ? ' reedcrm-calendar-day-weekend' : '';
            $cellClass .= $dayKey === $todayKey ? ' reedcrm-calendar-day-today' : '';
            ?>
            <div class="<?php echo $cellClass; ?>">
                <div class="reedcrm-calendar-day-head">
                    <span class="reedcrm-calendar-day-number"><?php echo (int) dol_print_date($day, '%d'); ?></span>
                    <?php if (count($dayRows) > 1) { ?>
                        <span class="reedcrm-calendar-day-count"><?php echo count($dayRows); ?></span>
                    <?php } ?>
                </div>

                <?php foreach ($dayRows as $dayRow) {
                    $userLabel = $dayRow->user_label ?: $langs->trans('InterventionNoUser');

                    $tooltip  = dol_print_date($dayRow->timestamp, 'dayhour');
                    $tooltip .= ' - ' . ($dayRow->socname ?: $langs->trans('ThirdParty'));
                    $tooltip .= ' - ' . $dayRow->line_label;
                    $tooltip .= ' - ' . $userLabel . ' - ' . $dayRow->propal_ref;
                    $tooltip .= !empty($dayRow->location) ? ' - ' . $dayRow->location : '';
                    ?>
                    <div class="reedcrm-intervention-chip reedcrm-intervention-trigger<?php echo (int) $dayRow->status === InterventionDate::STATUS_DONE ? ' reedcrm-intervention-chip-done' : ''; ?>"
                         data-line-id="<?php echo (int) $dayRow->fk_element_line; ?>"
                         style="border-left-color:<?php echo dol_escape_htmltag($dayRow->color); ?>"
                         title="<?php echo dol_escape_htmltag($tooltip); ?>">
                        <span class="reedcrm-chip-head">
                            <span class="reedcrm-chip-time"><?php echo dol_escape_htmltag(dol_print_date($dayRow->timestamp, '%H:%M')); ?></span>
                            <a class="reedcrm-chip-link" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/comm/propal/card.php?id=' . (int) $dayRow->element_id); ?>" title="<?php echo dol_escape_htmltag($dayRow->propal_ref); ?>"><i class="fas fa-external-link-alt"></i></a>
                        </span>
                        <span class="reedcrm-chip-soc"><?php echo dol_escape_htmltag($dayRow->socname ?: '-'); ?></span>
                        <span class="reedcrm-chip-label"><?php echo dol_escape_htmltag($dayRow->line_label); ?></span>
                        <?php if (!empty($dayRow->location)) { ?>
                            <span class="reedcrm-chip-location"><i class="fas fa-map-marker-alt"></i><?php echo dol_escape_htmltag($dayRow->location); ?></span>
                        <?php } ?>
                        <span class="reedcrm-chip-user<?php echo empty($dayRow->user_label) ? ' reedcrm-chip-user-none' : ''; ?>">
                            <?php echo reedcrmInterventionUserAvatar($dayRow); ?>
                            <span class="reedcrm-chip-user-name"><?php echo dol_escape_htmltag($userLabel); ?></span>
                        </span>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
