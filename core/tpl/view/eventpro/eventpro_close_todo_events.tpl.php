<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/view/eventpro/eventpro_close_todo_events.tpl.php
 * \ingroup reedcrm
 * \brief   Relaunches still to do of the object, under the complete event form (desktop card, its modal and the
 *          App): the user ticks the ones whose subject the exchange dealt with, and they are closed when the event
 *          is added (add_event, eventpro_actions.tpl.php). Expects $object, $db, $user and $langs.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

require_once __DIR__ . '/../../../../lib/reedcrm_relaunch.lib.php';

[$closeProjectId, $closeSocid] = reedcrm_get_relaunch_object_scope($object);
$closableRows = reedcrm_get_relaunch_closable_rows($db, $user, $closeProjectId, $closeSocid);
if (empty($closableRows)) {
    return;
}

// Kept ticked when the form comes back with an error
$closeTicked   = array_map('intval', GETPOST('close_event_ids', 'array:int'));
$relaunchTypes = reedcrm_get_relaunch_types();
$now           = dol_now();

$overdueCount = 0;
foreach ($closableRows as $row) {
    if (!empty($row['datep']) && $row['datep'] < $now) {
        $overdueCount++;
    }
}

// Folded by default, the counters on the line that unfolds it: logging an exchange quickly does not have to scroll past
// the list. It comes back unfolded with the lines it had ticked.
$closeOpen      = !empty($closeTicked);
$closeTickedNb  = count(array_intersect($closeTicked, array_column($closableRows, 'id')));
$closeTickedTpl = $langs->trans('RelaunchCloseTodoTicked', '%s');

// No label in the block: the theme boxes every label of the form like a field. A click on a line ticks its box
// (eventpro.js), and each box is named by the text of its line. The outer div is the cell of the grid of the form,
// whose padding the theme sets.
?>
<div>
<div class="eventpro-close-todo<?php echo $closeOpen ? ' is-open' : ''; ?>" data-ticked-label="<?php echo dol_escape_htmltag($closeTickedTpl); ?>">
    <button type="button" class="eventpro-close-todo__toggle" aria-expanded="<?php echo $closeOpen ? 'true' : 'false'; ?>" aria-controls="eventpro-close-todo-body">
        <i class="fas fa-check-double eventpro-close-todo__picto"></i>
        <span class="eventpro-close-todo__title"><?php echo $langs->trans('RelaunchCloseTodoTitle'); ?></span>
        <span class="eventpro-close-todo__badges">
            <span class="eventpro-close-todo__count"><?php echo count($closableRows); ?></span>
            <?php if ($overdueCount > 0) : ?>
                <span class="eventpro-close-todo__overdue"><i class="fas fa-exclamation"></i><?php echo $langs->trans('RelaunchCloseTodoOverdue', $overdueCount); ?></span>
            <?php endif; ?>
            <span class="eventpro-close-todo__ticked"<?php echo $closeTickedNb > 0 ? '' : ' hidden'; ?>><?php echo str_replace('%s', (string) $closeTickedNb, $closeTickedTpl); ?></span>
        </span>
        <i class="fas fa-chevron-down eventpro-close-todo__chevron"></i>
    </button>
    <div class="eventpro-close-todo__body" id="eventpro-close-todo-body"<?php echo $closeOpen ? '' : ' hidden'; ?>>
    <div class="eventpro-close-todo__tools">
        <span class="eventpro-close-todo__help"><?php echo $langs->trans('RelaunchCloseTodoHelp'); ?></span>
        <?php if (count($closableRows) > 1) : ?>
            <span class="eventpro-close-todo__all">
                <input type="checkbox" class="eventpro-close-todo-all" aria-labelledby="eventpro-close-todo-all-label">
                <span id="eventpro-close-todo-all-label"><?php echo $langs->trans('RelaunchCloseTodoAll'); ?></span>
            </span>
        <?php endif; ?>
    </div>
    <div class="eventpro-close-todo__list">
        <?php foreach ($closableRows as $row) :
            $state   = reedcrm_get_relaunch_row_state($row, $now);
            $overdue = !empty($row['datep']) && $row['datep'] < $now;
            $type    = $relaunchTypes[$row['type_key']] ?? $relaunchTypes['other'];
            $what    = $row['label'] !== '' ? $row['label'] : dol_trunc(dol_string_nohtmltag($row['note']), 60);
            $ticked  = in_array((int) $row['id'], $closeTicked, true); ?>
            <div class="eventpro-close-todo__item<?php echo $overdue ? ' is-overdue' : ''; ?><?php echo $ticked ? ' is-checked' : ''; ?>">
                <input type="checkbox" class="eventpro-close-todo-event" name="close_event_ids[]" value="<?php echo (int) $row['id']; ?>"
                       aria-labelledby="eventpro-close-todo-label-<?php echo (int) $row['id']; ?>"<?php echo $ticked ? ' checked' : ''; ?>>
                <i class="fas fa-<?php echo dol_escape_htmltag($type['picto']); ?> reedcrm-relaunch-type-picto reedcrm-relaunch-type-<?php echo dol_escape_htmltag($row['type_key']); ?>"
                   title="<?php echo dol_escape_htmltag($langs->trans('RelaunchType' . ucfirst($row['type_key']))); ?>"></i>
                <span class="eventpro-close-todo__what">
                    <span class="eventpro-close-todo__label" id="eventpro-close-todo-label-<?php echo (int) $row['id']; ?>"><?php echo dol_escape_htmltag($what !== '' ? $what : $langs->trans('RelaunchNoTitle')); ?></span>
                    <?php if ($row['contact_name'] !== '') : ?>
                        <span class="eventpro-close-todo__who"><?php echo dol_escape_htmltag($row['contact_name']); ?></span>
                    <?php endif; ?>
                </span>
                <?php if (!empty($row['datep'])) : ?>
                    <span class="eventpro-close-todo__when" title="<?php echo dol_escape_htmltag(dol_print_date($row['datep'], 'dayhour', 'tzuserrel')); ?>">
                        <?php if ($overdue) : ?>
                            <?php echo $state['late_days'] > 0 ? $langs->trans('RelaunchLateBy', $state['late_days']) : $langs->trans('RelaunchOverdue'); ?>
                        <?php else : ?>
                            <?php echo reedcrm_get_relaunch_day_label((int) $row['datep']) . ' ' . dol_print_date($row['datep'], '%H:%M', 'tzuserrel'); ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    </div>
</div>
</div>
