<?php
/* Copyright (C) 2024 EVARISK <technique@evarisk.com>
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
 * \file    admin/product.php
 * \ingroup easycrm
 * \brief   EasyCRM product config page.
 */

// Load EasyCRM environment
if (file_exists('../easycrm.main.inc.php')) {
    require_once __DIR__ . '/../easycrm.main.inc.php';
} elseif (file_exists('../../easycrm.main.inc.php')) {
    require_once __DIR__ . '/../../easycrm.main.inc.php';
} else {
    die('Include of easycrm main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

require_once __DIR__ . '/../lib/easycrm.lib.php';

// Global variables definitions
global $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissiontoread = $user->hasRight('easycrm','adminpage','read');

saturne_check_access($permissiontoread);

/*
 * View
 */

$title    = $langs->trans('ModuleSetup', 'EasyCRM');
$help_url = 'FR:Module_EasyCRM';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'easycrm_color@easycrm');

// Configuration header
$head = easycrm_admin_prepare_head();
print dol_get_fiche_head($head, 'product', $title, -1, 'easycrm_color@easycrm');

$constArray['easycrm'] = [
    'controldocument' => [
        'name'        => 'ProductKitConf',
        'description' => 'ProductKitConfDesc',
        'code'        => 'EASYCRM_PRODUCTKIT_DESC_ADD_LINE_PROPAL'
    ]
];

require_once __DIR__ . '/../../saturne/core/tpl/admin/object/object_const_view.tpl.php';
