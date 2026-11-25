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
 * \file    core/tpl/view/eventpro/relaunch_list_panel.tpl.php
 * \ingroup reedcrm
 * \brief   Panel listing the relaunches of one bucket, as sketched in issue #873: one line per
 *          event, "date - type - qui - quoi" plus its status. Fed by ajax/get_relaunches_list.php
 *          with a scope, and rendered inside the panel that opens next to the two buttons.
 *          Expects $rows (reedcrm_get_relaunch_rows) and $scope, and $projectId / $socid, which the
 *          status toggle sends back to repaint the buttons of the same project or third party.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs, $user;

$relaunchTypes = reedcrm_get_relaunch_types();
$statusChoices = reedcrm_get_relaunch_status_choices();
$now           = dol_now();
$hasStatusMenu = false;
?>
<div class="reedcrm-relaunch-panel reedcrm-relaunch-panel-<?php echo dol_escape_htmltag($scope); ?>"
     data-project-id="<?php echo (int) ($projectId ?? 0); ?>" data-socid="<?php echo (int) ($socid ?? 0); ?>"
     data-status-url="<?php echo dol_escape_htmltag(dol_buildpath('/reedcrm/ajax/set_relaunch_status.php', 1)); ?>"
     data-status-error="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('RelaunchStatusError')); ?>">
    <?php if (empty($rows)) : ?>
        <div class="reedcrm-relaunch-panel-empty">
            <?php echo $langs->trans($scope === 'upcoming' ? 'RelaunchNoUpcoming' : 'RelaunchNoPast'); ?>
        </div>
    <?php else : ?>
        <table class="noborder centpercent reedcrm-relaunch-panel-table">
            <tr class="liste_titre">
                <td class="reedcrm-relaunch-panel-date"><?php echo $langs->trans('Date'); ?></td>
                <td class="center reedcrm-relaunch-panel-type"><?php echo $langs->trans('Type'); ?></td>
                <td class="reedcrm-relaunch-panel-who"><?php echo $langs->trans('RelaunchWho'); ?></td>
                <td class="reedcrm-relaunch-panel-what"><?php echo $langs->trans('RelaunchWhat'); ?></td>
                <td class="right reedcrm-relaunch-panel-status"><?php echo $langs->trans('Status'); ?></td>
            </tr>
            <?php foreach ($rows as $row) :
                $state = reedcrm_get_relaunch_row_state($row, $now);
                $type  = $relaunchTypes[$row['type_key']] ?? $relaunchTypes['other'];
                $who   = $row['contact_name'] !== '' ? $row['contact_name'] : $row['user_name'];
                $what  = $row['label'] !== '' ? $row['label'] : dol_trunc(dol_string_nohtmltag($row['note']), 60); ?>
                <tr class="oddeven<?php echo $state['late_days'] > 0 ? ' reedcrm-relaunch-line-overdue' : ''; ?>">
                    <td class="reedcrm-relaunch-panel-date nowrap">
                        <?php echo !empty($row['datep']) ? dol_print_date($row['datep'], 'dayhour') : ''; ?>
                    </td>
                    <td class="center reedcrm-relaunch-panel-type">
                        <i class="fas fa-<?php echo dol_escape_htmltag($type['picto']); ?> reedcrm-relaunch-type-picto reedcrm-relaunch-type-<?php echo dol_escape_htmltag($row['type_key']); ?>"
                           title="<?php echo dol_escape_htmltag($langs->trans('RelaunchType' . ucfirst($row['type_key']))); ?>"></i>
                    </td>
                    <td class="reedcrm-relaunch-panel-who"><?php echo dol_escape_htmltag($who); ?></td>
                    <td class="reedcrm-relaunch-panel-what">
                        <a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/comm/action/card.php?id=' . $row['id']); ?>">
                            <?php echo dol_escape_htmltag($what !== '' ? $what : $langs->trans('RelaunchNoTitle')); ?>
                        </a>
                        <?php if ($state['late_days'] > 0) : ?>
                            <span class="reedcrm-relaunch-late-days"><?php echo $langs->trans('RelaunchLateBy', $state['late_days']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="right reedcrm-relaunch-panel-status">
                        <?php // The badge opens the status selector, for the user allowed to edit the event ?>
                        <?php if (reedcrm_user_can_edit_relaunch($user, $row['author_id'], $row['user_id'])) :
                            $hasStatusMenu = true; ?>
                            <span class="badge <?php echo dol_escape_htmltag($state['css']); ?> reedcrm-relaunch-status" role="button" tabindex="0" aria-haspopup="menu"
                                  data-event-id="<?php echo (int) $row['id']; ?>" data-status="<?php echo reedcrm_get_relaunch_status_choice($row['percent']); ?>"
                                  title="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('RelaunchStatusChange')); ?>"><span class="reedcrm-relaunch-status-label"><?php echo $langs->trans($state['key']); ?></span><i class="fas fa-caret-down"></i></span>
                        <?php else : ?>
                            <span class="badge <?php echo dol_escape_htmltag($state['css']); ?>"><?php echo $langs->trans($state['key']); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($hasStatusMenu) : ?>
            <?php // One selector for the whole panel, opened under the badge clicked ?>
            <div class="reedcrm-relaunch-status-menu" role="menu">
                <?php foreach ($statusChoices as $statusKey => $statusChoice) : ?>
                    <span class="reedcrm-relaunch-status-option" role="menuitem" tabindex="-1" data-status="<?php echo (int) $statusKey; ?>">
                        <span class="badge <?php echo dol_escape_htmltag($statusChoice['css']); ?>"><?php echo $langs->trans($statusChoice['key']); ?></span>
                        <i class="fas fa-check"></i>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
