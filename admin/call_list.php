<?php
/* Copyright (C) 2024-2025 EVARISK <technique@evarisk.com>
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
 * \file    admin/call_list.php
 * \ingroup reedcrm
 * \brief   ReedCRM call list config page.
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

require_once __DIR__ . '/../lib/reedcrm.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin', 'ticket']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissiontoread = $user->hasRight('reedcrm','adminpage','read');

saturne_check_access($permissiontoread);

$moduleName = 'ReedCRM';
$moduleNameLowerCase = 'reedcrm';
$documentParentType = 'calllist';
$documentType = 'calllist';
$objectModSubdir = 'call_list';

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once __DIR__ . '/../class/calllist.class.php';
$object = new CallList($db);

require_once __DIR__ . '/../../saturne/core/tpl/actions/admin_conf_actions.tpl.php';

$modelName = GETPOST('model_name', 'alpha');
$type      = GETPOST('type', 'alpha');
$label     = GETPOST('label', 'alpha');
$const     = GETPOST('const', 'alpha');

if ($action == 'set' && $permissiontoread) {
    addDocumentModel($modelName, $type, $label, $const);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
} elseif ($action == 'del' && $permissiontoread) {
    delDocumentModel($modelName, $type);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
} elseif ($action == 'setdoc' && $permissiontoread) {
    $confName = dol_strtoupper($moduleName . '_' . $documentParentType) . '_DEFAULT_MODEL';
    dolibarr_set_const($db, $confName, $modelName, 'chaine', 0, '', $conf->entity);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'set_config') {
    // Other settings if needed
    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ensure Form object is available
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
$form = new Form($db);

/*
 * View
 */

$title    = $langs->trans('ModuleSetup', 'ReedCRM');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'reedcrm_color@reedcrm');

// Configuration header
$head = reedcrm_admin_prepare_head();
print dol_get_fiche_head($head, 'call_list', $title, -1, 'reedcrm_color@reedcrm');

print load_fiche_titre($langs->trans('Configs', $langs->trans('CallList')), '', '');

// Numbering Module
unset($documentPath);
unset($objectModSubdir);
$filelist = [];
require_once __DIR__ . '/../../saturne/core/tpl/admin/object/object_numbering_module_view.tpl.php';

// Document Model
$dir = dol_buildpath('/custom/reedcrm/core/modules/reedcrm/call_list/doc/');
$filelist = [];
if (is_dir($dir)) {
    $handle = opendir($dir);
    if (is_resource($handle)) {
        while (($file = readdir($handle)) !== false) {
            $filelist[] = $file;
        }
        closedir($handle);
    }
}
require_once __DIR__ . '/../../saturne/core/tpl/admin/object/object_document_model_view.tpl.php';

// Automations on call list line status change (PWA status buttons)
print load_fiche_titre($langs->trans('Config'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '</tr>';

// Create ActionComm on call list status button click
print '<tr class="oddeven"><td>';
print $langs->transnoentities('CallListStatusCreateActioncomm');
print '</td><td>';
print $langs->transnoentities('CallListStatusCreateActioncommDesc');
print '</td><td class="center">';
print ajax_constantonoff('REEDCRM_CALL_LIST_STATUS_CREATE_ACTIONCOMM');
print '</td></tr>';

// Create commercial task on call list status button click
print '<tr class="oddeven"><td>';
print $langs->transnoentities('CallListStatusCreateTask');
print '</td><td>';
print $langs->transnoentities('CallListStatusCreateTaskDesc');
print '</td><td class="center">';
print ajax_constantonoff('REEDCRM_CALL_LIST_STATUS_CREATE_TASK');
print '</td></tr>';

print '</table>';
