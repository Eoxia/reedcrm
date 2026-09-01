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
 * \file    core/tpl/todo/todo_kanban_card.tpl.php
 * \ingroup reedcrm
 * \brief   Single card of the todo board (rendered live or deferred for lazy-load)
 *
 * Variables expected from calling TPL:
 * - $t                 array     One enriched event of reedcrmTodoGetEvents()
 * - $todoColumns       array     Columns from reedcrmTodoGetKanbanColumns()
 * - $todoUsers         array     Selectable users
 * - $permissionToWrite bool      Whether the user may change the event
 * - $langs             Translate Translation object
 */

// Colour of the column the event sits in, shared by the owner chip and the progress bar
$cardColumn = reedcrmTodoGetColumnForEvent($todoColumns, $t);
$cardColor  = !empty($cardColumn) ? $cardColumn['color'] : '#999999';

// An event carrying no percentage has no progress to show
$hasPercent  = ((int) $t['percent'] >= 0);
$percentText = $hasPercent ? $t['percent'] . '%' : $langs->trans('StatusNotApplicable');

$ownerId       = !empty($t['owner']) ? $t['owner']['id'] : 0;
$ownerFullname = !empty($t['owner']) ? $t['owner']['fullname'] : '';
$ownerInitials = !empty($t['owner']) ? $t['owner']['initials'] : '';

// Full day events are picked on a plain date, the others carry an hour
$dateInputType = !empty($t['fullday']) ? 'date' : 'datetime-local';
?>
<div class="todo-card<?php echo !empty($t['late']) ? ' todo-card-late' : ''; ?>" data-event-id="<?php echo $t['id']; ?>" data-percent="<?php echo $t['percent']; ?>" data-fullday="<?php echo (int) $t['fullday']; ?>" data-event-code="<?php echo dol_escape_htmltag($t['code']); ?>">

    <!-- Header: type of event + reference + late flag -->
    <div class="todo-card-header">
        <span class="todo-card-type" <?php echo !empty($t['type_color']) ? 'style="background: ' . dol_escape_htmltag($t['type_color']) . '"' : ''; ?>
              title="<?php echo dol_escape_htmltag($t['type_label']); ?>">
            <i class="fas <?php echo dol_escape_htmltag($t['type_picto']); ?>"></i>
            <span class="todo-card-type-label"><?php echo dol_escape_htmltag($t['type_label']); ?></span>
        </span>
        <a href="<?php echo $t['url']; ?>" class="todo-card-ref" target="_blank">
            <i class="fas fa-external-link-alt"></i> <?php echo dol_escape_htmltag($t['ref']); ?>
        </a>
        <?php if (!empty($t['late'])) : ?>
            <span class="todo-card-late-badge" title="<?php echo dol_escape_htmltag($langs->trans('TodoLateEvent')); ?>">
                <i class="fas fa-exclamation-circle"></i> <?php echo $langs->trans('TodoLateEvent'); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Label -->
    <div class="todo-card-label<?php echo $permissionToWrite ? ' todo-editable-label' : ''; ?>"><?php echo dol_escape_htmltag($t['label']); ?></div>

    <!-- Third party, project and, for a relaunch, the proposal or the invoice it was raised on -->
    <?php if (!empty($t['soc_id']) || !empty($t['project_id']) || !empty($t['origin'])) : ?>
        <div class="todo-card-links">
            <?php if (!empty($t['origin'])) : ?>
                <a class="todo-link-badge todo-link-origin" target="_blank" href="<?php echo $t['origin']['url']; ?>">
                    <i class="fas <?php echo $t['origin']['type'] == 'propal' ? 'fa-file-signature' : 'fa-file-invoice-dollar'; ?>"></i>
                    <?php echo dol_escape_htmltag($t['origin']['ref']); ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($t['soc_id'])) : ?>
                <a class="todo-link-badge todo-link-soc" target="_blank"
                   href="<?php echo DOL_URL_ROOT . '/societe/card.php?socid=' . $t['soc_id']; ?>"
                   title="<?php echo dol_escape_htmltag($t['soc_name']); ?>">
                    <i class="fas fa-building"></i> <?php echo dol_escape_htmltag($t['soc_name']); ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($t['project_id'])) : ?>
                <a class="todo-link-badge todo-link-project" target="_blank"
                   href="<?php echo DOL_URL_ROOT . '/projet/card.php?id=' . $t['project_id']; ?>"
                   title="<?php echo dol_escape_htmltag($t['project_ref'] . ' - ' . $t['project_title']); ?>">
                    <i class="fas fa-project-diagram"></i> <?php echo dol_escape_htmltag($t['project_title'] ?: $t['project_ref']); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- People: [owner] | [assigned users] [+] -->
    <div class="todo-card-people">
        <div class="todo-owner-wrapper" data-event-id="<?php echo $t['id']; ?>" data-current-user="<?php echo $ownerId; ?>">
            <span class="todo-initial todo-initial-owner <?php echo empty($ownerInitials) ? 'todo-initial-empty' : ''; ?>"
                  title="<?php echo dol_escape_htmltag($ownerFullname ?: $langs->trans('TodoUnassigned')); ?>"
                  style="background: <?php echo dol_escape_htmltag($cardColor); ?>">
                <?php echo dol_escape_htmltag($ownerInitials ?: '?'); ?>
            </span>
            <?php // The list of users is rendered once for the whole board, the JS module fills the dropdown on opening ?>
            <?php if ($permissionToWrite) : ?>
                <div class="todo-owner-dropdown" data-event-id="<?php echo $t['id']; ?>">
                    <input type="text" class="todo-owner-search" placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?>..." autocomplete="off">
                    <div class="todo-user-options"></div>
                </div>
            <?php endif; ?>
        </div>

        <span class="todo-separator">|</span>

        <?php foreach ($t['assigned'] as $assignedUser) : ?>
            <span class="todo-initial-wrapper" data-event-id="<?php echo $t['id']; ?>" data-user-id="<?php echo $assignedUser['id']; ?>">
                <span class="todo-initial todo-initial-assigned" title="<?php echo dol_escape_htmltag($assignedUser['fullname']); ?>">
                    <?php echo dol_escape_htmltag($assignedUser['initials']); ?>
                </span>
                <?php if ($permissionToWrite) : ?>
                    <span class="todo-remove-assigned" title="<?php echo dol_escape_htmltag($langs->trans('TodoRemoveAssigned')); ?>">&times;</span>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>

        <?php if ($permissionToWrite) : ?>
            <div class="todo-add-assigned-wrapper">
                <button type="button" class="todo-add-assigned-btn" title="<?php echo dol_escape_htmltag($langs->trans('TodoAddAssigned')); ?>">
                    <i class="fas fa-user-plus"></i>
                </button>
                <div class="todo-assigned-dropdown" data-event-id="<?php echo $t['id']; ?>">
                    <input type="text" class="todo-assigned-search" placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?>..." autocomplete="off">
                    <div class="todo-user-options"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dates row -->
    <div class="todo-dates-row">
        <span class="todo-date todo-date-start<?php echo $permissionToWrite ? ' todo-editable-date' : ''; ?>"
              data-event-id="<?php echo $t['id']; ?>" data-field="date_start"
              data-raw="<?php echo dol_escape_htmltag($t['date_start']); ?>" data-input-type="<?php echo $dateInputType; ?>"
              title="<?php echo dol_escape_htmltag($langs->trans('DateActionStart')); ?>">
            <i class="fas fa-calendar-plus"></i> <span class="todo-date-value"><?php echo !empty($t['date_start_fmt']) ? dol_escape_htmltag($t['date_start_fmt']) : '-'; ?></span>
        </span>
        <span class="todo-date todo-date-end<?php echo $permissionToWrite ? ' todo-editable-date' : ''; ?>"
              data-event-id="<?php echo $t['id']; ?>" data-field="date_end"
              data-raw="<?php echo dol_escape_htmltag($t['date_end']); ?>" data-input-type="<?php echo $dateInputType; ?>"
              title="<?php echo dol_escape_htmltag($langs->trans('DateActionEnd')); ?>">
            <i class="fas fa-calendar-check"></i> <span class="todo-date-value"><?php echo !empty($t['date_end_fmt']) ? dol_escape_htmltag($t['date_end_fmt']) : '-'; ?></span>
        </span>
    </div>

    <!-- Percentage of the event -->
    <div class="todo-card-progress">
        <div class="todo-progress-bar<?php echo $permissionToWrite ? ' todo-editable-progress' : ''; ?>">
            <div class="todo-progress-fill" style="width: <?php echo $hasPercent ? (int) $t['percent'] : 0; ?>%; background: <?php echo dol_escape_htmltag($cardColor); ?>"></div>
        </div>
        <span class="todo-progress-text"><?php echo dol_escape_htmltag($percentText); ?></span>
    </div>

    <!-- Location and private note -->
    <?php if (!empty($t['location']) || !empty($t['note'])) : ?>
        <div class="todo-card-footer">
            <?php if (!empty($t['location'])) : ?>
                <span class="todo-card-location" title="<?php echo dol_escape_htmltag($langs->trans('Location')); ?>">
                    <i class="fas fa-map-marker-alt"></i> <?php echo dol_escape_htmltag($t['location']); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($t['note'])) : ?>
                <span class="todo-card-note"><i class="fas fa-align-left"></i> <?php echo dol_escape_htmltag($t['note']); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
