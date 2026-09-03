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
 * \file    core/tpl/todo/todo_filters.tpl.php
 * \ingroup reedcrm
 * \brief   Criteria bar of the todo board (user, period, type of event, text)
 *
 * Variables expected from calling PHP:
 * - $todoFilters array     Criteria from reedcrmTodoGetFilters()
 * - $todoCounts   array     Number of events per column
 * - $todoUsers   array     Selectable users
 * - $todoTypes   array     Selectable types of event
 * - $langs       Translate Translation object
 */

// Keep the menu highlighted while filtering
$menuMain = GETPOST('mainmenu', 'aZ09');
$menuLeft = GETPOST('leftmenu', 'aZ09');
$menuId   = GETPOSTINT('idmenu');
?>

<form class="todo-filter-bar" method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <?php // Tells the criteria of an untouched page from the ones deliberately emptied ?>
    <input type="hidden" name="filtered" value="1">
    <?php if (!empty($menuMain)) : ?>
        <input type="hidden" name="mainmenu" value="<?php echo dol_escape_htmltag($menuMain); ?>">
    <?php endif; ?>
    <?php if (!empty($menuLeft)) : ?>
        <input type="hidden" name="leftmenu" value="<?php echo dol_escape_htmltag($menuLeft); ?>">
    <?php endif; ?>
    <?php if ($menuId > 0) : ?>
        <input type="hidden" name="idmenu" value="<?php echo $menuId; ?>">
    <?php endif; ?>

    <div class="tdf-field">
        <label for="search_user"><i class="fas fa-user"></i> <?php echo $langs->trans('AffectedTo'); ?></label>
        <select class="flat tdf-select" id="search_user" name="search_user">
            <option value="0"><?php echo dol_escape_htmltag($langs->trans('TodoAllUsers')); ?></option>
            <?php foreach ($todoUsers as $todoUser) : ?>
                <option value="<?php echo $todoUser['id']; ?>" <?php echo ($todoFilters['user'] == $todoUser['id']) ? 'selected' : ''; ?>>
                    <?php echo dol_escape_htmltag($todoUser['fullname']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tdf-field">
        <label for="search_date_start"><i class="fas fa-calendar-plus"></i> <?php echo $langs->trans('DateActionStart'); ?></label>
        <input type="date" class="flat tdf-date" id="search_date_start" name="search_date_start"
               value="<?php echo !empty($todoFilters['date_start']) ? dol_print_date($todoFilters['date_start'], 'dayrfc') : ''; ?>">
    </div>

    <div class="tdf-field">
        <label for="search_date_end"><i class="fas fa-calendar-check"></i> <?php echo $langs->trans('DateActionEnd'); ?></label>
        <input type="date" class="flat tdf-date" id="search_date_end" name="search_date_end"
               value="<?php echo !empty($todoFilters['date_end']) ? dol_print_date($todoFilters['date_end'], 'dayrfc') : ''; ?>">
    </div>

    <div class="tdf-field">
        <label for="search_type"><i class="fas fa-tag"></i> <?php echo $langs->trans('ActionType'); ?></label>
        <select class="flat tdf-select" id="search_type" name="search_type">
            <option value="0"><?php echo dol_escape_htmltag($langs->trans('TodoAllTypes')); ?></option>
            <?php foreach ($todoTypes as $typeId => $typeLabel) : ?>
                <option value="<?php echo $typeId; ?>" <?php echo ($todoFilters['type'] == $typeId) ? 'selected' : ''; ?>>
                    <?php echo dol_escape_htmltag($typeLabel); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tdf-field">
        <label for="search_text"><i class="fas fa-search"></i> <?php echo $langs->trans('Search'); ?></label>
        <input type="text" class="flat tdf-text" id="search_text" name="search_text"
               value="<?php echo dol_escape_htmltag($todoFilters['search']); ?>"
               placeholder="<?php echo dol_escape_htmltag($langs->trans('TodoSearchPlaceholder')); ?>">
    </div>

    <?php // Unchecked boxes are not submitted: the hidden twin carries the "show them" choice ?>
    <input type="hidden" name="search_hide_auto" value="0">
    <label class="tdf-toggle" title="<?php echo dol_escape_htmltag($langs->trans('TodoFilterHideAutoHelp')); ?>">
        <input type="checkbox" name="search_hide_auto" value="1" <?php echo !empty($todoFilters['hide_auto']) ? 'checked' : ''; ?>>
        <?php echo $langs->trans('TodoFilterHideAuto'); ?>
    </label>

    <div class="tdf-actions">
        <button type="submit" class="tdf-btn tdf-btn-search">
            <i class="fas fa-search"></i> <?php echo $langs->trans('Search'); ?>
        </button>
        <a class="tdf-btn tdf-btn-reset" href="<?php echo $_SERVER['PHP_SELF']; ?>">
            <i class="fas fa-eraser"></i> <?php echo $langs->trans('RemoveFilter'); ?>
        </a>
        <span class="tdf-count">
            <i class="fas fa-clipboard-list"></i>
            <?php echo $langs->trans('TodoFilterCount', array_sum($todoCounts)); ?>
        </span>
    </div>
</form>
