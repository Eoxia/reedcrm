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
 * \file    core/tpl/reedcrm_intervention_calendar_unplanned.tpl.php
 * \ingroup reedcrm
 * \brief   Interventions left without a date : the rows waiting to land on the calendar.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $langs;

if (empty($unplanned)) {
    return;
}
?>

<div class="reedcrm-intervention-unplanned">
    <?php print load_fiche_titre($langs->trans('InterventionToPlanList', count($unplanned)), '', ''); ?>

    <div class="div-table-responsive">
        <table class="noborder centpercent">
            <tr class="liste_titre">
                <td><?php echo dol_escape_htmltag($langs->trans('ThirdParty')); ?></td>
                <td><?php echo dol_escape_htmltag($langs->trans('Service')); ?></td>
                <td class="center"><?php echo dol_escape_htmltag($langs->trans('Quantity')); ?></td>
                <td class="center"><?php echo dol_escape_htmltag($langs->trans('InterventionPlannedCount')); ?></td>
                <td class="center"><?php echo dol_escape_htmltag($langs->trans('InterventionRemainingCount')); ?></td>
                <td><?php echo dol_escape_htmltag($langs->trans('Propal')); ?></td>
                <td class="right"><?php echo dol_escape_htmltag($langs->trans('InterventionPlanAction')); ?></td>
            </tr>

            <?php foreach ($unplanned as $row) { ?>
                <tr class="oddeven">
                    <td><?php echo $row->fk_soc > 0 ? '<a href="' . dol_escape_htmltag(DOL_URL_ROOT . '/societe/card.php?socid=' . (int) $row->fk_soc) . '">' . dol_escape_htmltag($row->socname) . '</a>' : ''; ?></td>
                    <td><?php echo dol_escape_htmltag($row->line_label); ?></td>
                    <td class="center"><?php echo dol_escape_htmltag(price2num($row->qty, 'MS')); ?></td>
                    <td class="center"><?php echo (int) $row->planned . ' / ' . (int) $row->expected; ?></td>
                    <td class="center"><span class="reedcrm-intervention-remaining"><?php echo (int) $row->remaining; ?></span></td>
                    <td class="nowraponall"><a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/comm/propal/card.php?id=' . (int) $row->element_id); ?>"><?php echo dol_escape_htmltag($row->propal_ref); ?></a></td>
                    <td class="right">
                        <span class="reedcrm-intervention-trigger reedcrm-intervention-trigger-inline" data-line-id="<?php echo (int) $row->fk_element_line; ?>">
                            <i class="fas fa-calendar-plus"></i>&nbsp;<?php echo dol_escape_htmltag($langs->trans('InterventionPlanAction')); ?>
                        </span>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>
