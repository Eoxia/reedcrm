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
 * \file    core/tpl/todo/todo_list_kanban.tpl.php
 * \ingroup reedcrm
 * \brief   Kanban board of the todo tab, one column per agenda event status
 *
 * Variables expected from calling PHP:
 * - $todoColumns       array     Columns from reedcrmTodoGetKanbanColumns()
 * - $todoPage          array     First page of cards per column key
 * - $todoCounts        array     Total number of events per column key
 * - $todoUsers         array     Selectable users (owner and assigned users selectors)
 * - $permissionToWrite bool      Whether the user may change an event
 * - $langs             Translate Translation object
 */
?>

<div class="todo-settings-wrapper">
    <button type="button" class="todo-settings-btn" id="todoSettingsBtn" title="<?php echo dol_escape_htmltag($langs->trans('Settings')); ?>">
        <i class="fas fa-cog"></i>
    </button>
    <div class="todo-settings-popover" id="todoSettingsPopover">
        <div class="tds-row">
            <label><i class="fas fa-arrows-alt-h"></i> <?php echo $langs->trans('TodoColumnWidth'); ?></label>
            <input type="range" id="todoColWidth" min="260" max="500" value="350" step="10">
            <span class="tds-val" id="todoColWidthVal">350px</span>
        </div>
        <div class="tds-row">
            <label><i class="fas fa-columns"></i> <?php echo $langs->trans('TodoColumnGap'); ?></label>
            <input type="range" id="todoColGap" min="8" max="50" value="26" step="2">
            <span class="tds-val" id="todoColGapVal">26px</span>
        </div>
        <?php // A column masked from its own menu has no other way back than this list ?>
        <div class="tds-row tds-row-columns">
            <label><i class="fas fa-eye"></i> <?php echo $langs->trans('TodoVisibleColumns'); ?></label>
            <div class="tds-columns">
                <?php foreach ($todoColumns as $todoColumn) : ?>
                    <label class="tds-column-toggle">
                        <input type="checkbox" class="todo-column-toggle" value="<?php echo dol_escape_htmltag($todoColumn['key']); ?>" checked>
                        <span><?php echo dol_escape_htmltag($todoColumn['label']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php // Column menu, same popover-body as the saturne lists : rendered once, the JS module
      // moves it to the body on the first open and positions it under the clicked caret ?>
<div class="saturne-col-filter-popover todo-column-popover" id="todoColumnPopover">
    <div class="popover-body">
        <div class="popover-action action-sort-asc">
            <i class="fas fa-arrow-up"></i> <?php echo $langs->trans('TodoSortDateAsc'); ?>
        </div>
        <div class="popover-action action-sort-desc">
            <i class="fas fa-arrow-down"></i> <?php echo $langs->trans('TodoSortDateDesc'); ?>
        </div>
        <div class="popover-divider"></div>
        <div class="popover-action is-disabled" title="<?php echo dol_escape_htmltag($langs->trans('TodoComingSoon')); ?>">
            <i class="fas fa-layer-group"></i> <?php echo $langs->trans('TodoGroup'); ?>
        </div>
        <div class="popover-action is-disabled" title="<?php echo dol_escape_htmltag($langs->trans('TodoComingSoon')); ?>">
            <i class="fas fa-snowflake"></i> <?php echo $langs->trans('TodoFreeze'); ?>
        </div>
        <div class="popover-divider"></div>
        <div class="popover-action action-hide">
            <i class="fas fa-eye-slash"></i> <?php echo $langs->trans('TodoHideColumn'); ?>
        </div>
    </div>
</div>

<?php if ($permissionToWrite) : ?>
    <?php // Rendered once for the whole board: the JS module clones it into the owner and
          // assigned users dropdowns when one is opened, so a card carries no user list ?>
    <div class="todo-user-options-template" id="todoUserOptions">
        <div class="todo-user-option todo-user-option-none" data-value="0" data-initial="?" data-search="<?php echo dol_escape_htmltag(strtolower($langs->trans('TodoUnassigned'))); ?>">
            <?php echo dol_escape_htmltag($langs->trans('TodoUnassigned')); ?>
        </div>
        <?php foreach ($todoUsers as $selectableUser) : ?>
            <div class="todo-user-option" data-value="<?php echo $selectableUser['id']; ?>" data-initial="<?php echo dol_escape_htmltag($selectableUser['initials']); ?>" data-search="<?php echo dol_escape_htmltag(strtolower($selectableUser['fullname'])); ?>">
                <?php echo dol_escape_htmltag($selectableUser['fullname']); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php // The labels the JS module writes back into repainted cards and emptied columns ?>
<div class="todo-board" data-token="<?php echo newToken(); ?>" data-editable="<?php echo $permissionToWrite ? 1 : 0; ?>"
     data-na-label="<?php echo dol_escape_htmltag($langs->trans('StatusNotApplicable')); ?>"
     data-empty-label="<?php echo dol_escape_htmltag($langs->trans('TodoNoEvent')); ?>">
    <?php foreach ($todoColumns as $columnDefinition) :
        $columnKey       = $columnDefinition['key'];
        $columnCards     = $todoPage[$columnKey] ?? [];
        $columnTotal     = (int) ($todoCounts[$columnKey] ?? 0);
        $columnRemaining = max(0, $columnTotal - count($columnCards));
    ?>
        <?php // A relaunch backlog is keyed on the code of its events, not on a percentage:
              // it takes no drop, a card leaves it by being marked done or not applicable ?>
        <div class="todo-column" data-column="<?php echo dol_escape_htmltag($columnKey); ?>"
             <?php if ($columnDefinition['min'] !== null) : ?>
                 data-percent-min="<?php echo (int) $columnDefinition['min']; ?>"
                 data-percent-max="<?php echo (int) $columnDefinition['max']; ?>"
             <?php endif; ?>
             <?php if (!empty($columnDefinition['code'])) : ?>
                 data-code="<?php echo dol_escape_htmltag($columnDefinition['code']); ?>"
             <?php endif; ?>
             data-color="<?php echo dol_escape_htmltag($columnDefinition['color']); ?>">
            <div class="todo-column-header" style="border-top: 3px solid <?php echo dol_escape_htmltag($columnDefinition['color']); ?>">
                <span class="todo-column-icon"><i class="fas <?php echo dol_escape_htmltag($columnDefinition['icon']); ?>"></i></span>
                <?php // Clicking the title sorts the cards of the column on their date. Both arrows
                      // stay up, the way in force is the one the stylesheet darkens ?>
                <button type="button" class="todo-column-sort" data-column="<?php echo dol_escape_htmltag($columnKey); ?>" data-direction=""
                        title="<?php echo dol_escape_htmltag($langs->trans('TodoSortByDate')); ?>">
                    <span class="todo-column-title"><?php echo dol_escape_htmltag($columnDefinition['label']); ?></span>
                    <span class="todo-column-sort-icon">
                        <i class="fas fa-sort-up"></i>
                        <i class="fas fa-sort-down"></i>
                    </span>
                </button>
                <span class="todo-column-count"><?php echo (int) $columnTotal; ?></span>
                <button type="button" class="todo-column-menu" data-column="<?php echo dol_escape_htmltag($columnKey); ?>"
                        title="<?php echo dol_escape_htmltag($langs->trans('TodoColumnOptions')); ?>">
                    <i class="fas fa-caret-down"></i>
                </button>
            </div>
            <div class="todo-column-body todo-sortable" data-column="<?php echo dol_escape_htmltag($columnKey); ?>">
                <?php if (empty($columnCards)) : ?>
                    <div class="todo-empty"><?php echo $langs->trans('TodoNoEvent'); ?></div>
                <?php endif; ?>
                <?php foreach ($columnCards as $t) {
                    require __DIR__ . '/todo_kanban_card.tpl.php';
                } ?>
                <?php // The rest of the column stays on the server: the button goes and gets the
                      // next page, so a board of thousands of events weighs the same as a small one ?>
                <button type="button" class="todo-load-more<?php echo $columnRemaining > 0 ? '' : ' todo-load-more-hidden'; ?>"
                        data-column="<?php echo dol_escape_htmltag($columnKey); ?>"
                        data-offset="<?php echo count($columnCards); ?>"
                        data-remaining="<?php echo (int) $columnRemaining; ?>"
                        data-label="<?php echo dol_escape_htmltag($langs->trans('TodoLoadMore', '%s')); ?>">
                    <i class="fas fa-chevron-down"></i> <span class="todo-load-more-text"><?php echo $langs->trans('TodoLoadMore', $columnRemaining); ?></span>
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
