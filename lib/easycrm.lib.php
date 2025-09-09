<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    lib/easycrm.lib.php
 * \ingroup easycrm
 * \brief   Library files with common functions for EasyCRM
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function easycrm_admin_prepare_head(): array
{
    // Global variables definitions
    global $conf, $langs;

    // Load translation files required by the page
    saturne_load_langs(['products']);

    // Initialize values
    $h = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/easycrm/admin/setup.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cog pictofixedwidth"></i>' . $langs->trans('ModuleSettings') : '<i class="fas fa-cog"></i>';
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/pwa.php', 1). '?module_name=EasyCRM&start_url=' . dol_buildpath('custom/easycrm/view/frontend/quickcreation.php?source=pwa', 3);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-mobile pictofixedwidth"></i>' . $langs->trans('PWA') : '<i class="fas fa-mobile"></i>';
    $head[$h][2] = 'pwa';
    $h++;

    $head[$h][0] = dol_buildpath('/easycrm/admin/call_notifications.php', 1) . '?module_name=EasyCRM';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fab fa-bell pictofixedwidth"></i>' . $langs->trans('CallNotifications') : '<i class="fab fa-bell"></i>';
    $head[$h][2] = 'notifications';
    $h++;

    $head[$h][0] = dol_buildpath('/easycrm/admin/product.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cube pictofixedwidth"></i>' . $langs->trans('Product') : '<i class="fas fa-cube"></i>';
    $head[$h][2] = 'product';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/about.php', 1) . '?module_name=EasyCRM';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fab fa-readme pictofixedwidth"></i>' . $langs->trans('About') : '<i class="fab fa-readme"></i>';
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'easycrm@easycrm');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'easycrm@easycrm', 'remove');

    return $head;
}
