<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    view/frontend/quickcreation.php
 * \ingroup easycrm
 * \brief   Page to quick creation on frontend view
 */

// Load EasyCRM environment
if (file_exists('../easycrm.main.inc.php')) {
    require_once __DIR__ . '/../easycrm.main.inc.php';
} elseif (file_exists('../../easycrm.main.inc.php')) {
    require_once __DIR__ . '/../../easycrm.main.inc.php';
} else {
    die('Include of easycrm main fails');
}

// Load Dolibarr libraries
if (isModEnabled('project')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
    require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
}
if (isModEnabled('categorie')) {
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
}

// load EasyCRM libraries
require_once __DIR__ . '/../../class/geolocation.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['projects']);

// Get parameters
$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'quickcretion_frontend'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha');
$subaction   = GETPOST('subaction', 'alpha');

// Initialize technical objects
$geolocation = new Geolocation($db);
if (isModEnabled('project')) {
    $project = new Project($db);
    $task    = new Task($db);
}

// Initialize view objects
$form = new Form($db);
if (isModEnabled('project')) {
    $formProject = new FormProjets($db);
}

$hookmanager->initHooks(['easycrm_quickcreation_frontend']); // Note that conf->hooks_modules contains array

// Security check - Protection if external user
$permissionToRead       = $user->rights->easycrm->read;
$permissionToAddProject = $user->rights->projet->creer;
saturne_check_access($permissionToRead);

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $project, $action); // Note that $action and $project may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    $error = 0;

    // Actions add_img, add
    require_once __DIR__ . '/../../core/tpl/frontend/easycrm_quickcreation_actions_frontend.tpl.php';
}

/*
 * View
 */

$title    = $langs->trans('QuickCreation');
$help_url = 'FR:Module_EasyCRM';
$moreJS   = ['/custom/saturne/js/includes/signature-pad.min.js'];
$moreCSS  = ['/easycrm/css/pico.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

saturne_header(0, '', $title, $help_url, '', 0, 0, $moreJS, $moreCSS, '', 'quickcreation-frontend');

if (empty($permissionToAddProject)) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

print '<form class="quickcreation-form" method="POST" action="' . $_SERVER["PHP_SELF"] . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="add">';
if ($backtopage) {
    print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
}

require_once __DIR__ . '/../../core/tpl/frontend/easycrm_project_quickcreation_frontend.tpl.php';

print '</form>';

// End of page
llxFooter();
$db->close();
