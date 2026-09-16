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
 * \file    core/tpl/reedcrm_intervention_calendar_list.tpl.php
 * \ingroup reedcrm
 * \brief   List view of the interventions of the month.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $db, $langs;

$interventionDate = new InterventionDate($db);
?>

<div class="div-table-responsive">
    <table class="noborder centpercent">
        <tr class="liste_titre">
            <td><?php echo dol_escape_htmltag($langs->trans('Date')); ?></td>
            <td><?php echo dol_escape_htmltag($langs->trans('InterventionUser')); ?></td>
            <td><?php echo dol_escape_htmltag($langs->trans('ThirdParty')); ?></td>
            <td><?php echo dol_escape_htmltag($langs->trans('Service')); ?></td>
            <td><?php echo dol_escape_htmltag($langs->trans('InterventionLocation')); ?></td>
            <td><?php echo dol_escape_htmltag($langs->trans('Propal')); ?></td>
            <td><?php echo dol_escape_htmltag($langs->trans('Note')); ?></td>
            <td class="right"><?php echo dol_escape_htmltag($langs->trans('Status')); ?></td>
        </tr>

        <?php if (empty($rows)) { ?>
            <tr class="oddeven"><td colspan="8" class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('InterventionNoneThisMonth')); ?></td></tr>
        <?php } ?>

        <?php foreach ($rows as $row) { ?>
            <tr class="oddeven">
                <td class="nowraponall"><?php echo dol_print_date($row->timestamp, 'dayhour'); ?></td>
                <td class="nowraponall">
                    <span class="reedcrm-intervention-user-cell">
                        <?php echo reedcrmInterventionUserAvatar($row); ?>
                        <?php echo dol_escape_htmltag($row->user_label ?: $langs->trans('InterventionNoUser')); ?>
                    </span>
                </td>
                <td><?php echo $row->fk_soc > 0 ? '<a href="' . dol_escape_htmltag(DOL_URL_ROOT . '/societe/card.php?socid=' . (int) $row->fk_soc) . '">' . dol_escape_htmltag($row->socname) . '</a>' : ''; ?></td>
                <td>
                    <span class="reedcrm-intervention-trigger reedcrm-intervention-trigger-inline" data-line-id="<?php echo (int) $row->fk_element_line; ?>" title="<?php echo dol_escape_htmltag($langs->trans('InterventionDatePlanTooltip')); ?>">
                        <i class="fas fa-calendar-alt"></i>&nbsp;<?php echo dol_escape_htmltag($row->line_label); ?>
                    </span>
                    <span class="opacitymedium">&nbsp;<?php echo dol_escape_htmltag('#' . $row->position); ?></span>
                </td>
                <td class="tdoverflowmax200"><?php echo dol_escape_htmltag($row->location); ?></td>
                <td class="nowraponall"><a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/comm/propal/card.php?id=' . (int) $row->element_id); ?>"><?php echo dol_escape_htmltag($row->propal_ref); ?></a></td>
                <td class="tdoverflowmax200"><?php echo dol_escape_htmltag($row->note); ?></td>
                <td class="right nowraponall">
                    <?php
                    $interventionDate->status = (int) $row->status;
                    echo $interventionDate->getLibStatut(3);
                    if ($row->fk_actioncomm > 0) {
                        echo '&nbsp;<a href="' . dol_escape_htmltag(DOL_URL_ROOT . '/comm/action/card.php?id=' . (int) $row->fk_actioncomm) . '" title="' . dol_escape_htmltag($langs->trans('InterventionSeeEvent')) . '"><i class="fas fa-calendar-check"></i></a>';
                    }
                    ?>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>
