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
 * \file    core/tpl/todo/todo_toolbar.tpl.php
 * \ingroup reedcrm
 * \brief   Top-right toolbar of the todo board, pulled up onto the tab row
 *
 * Variables expected from calling PHP:
 * - $permissionToWrite bool      Whether the user may create an event
 * - $langs             Translate Translation object
 */
?>

<div class="todo-toolbar">
    <?php if ($permissionToWrite) : ?>
        <a class="todo-toolbar-btn" href="<?php echo DOL_URL_ROOT . '/comm/action/card.php?action=create&backtopage=' . urlencode($_SERVER['PHP_SELF']); ?>">
            <i class="fas fa-plus"></i>
            <span><?php echo $langs->trans('AddAction'); ?></span>
        </a>
    <?php endif; ?>
</div>
