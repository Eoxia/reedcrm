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
 * \file    procard.php
 * \ingroup reedcrm
 * \brief   Page to manage commercial actions linked to a third party or a project
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Get parameters to know from which object we come from
$fromType = GETPOST('from_type', 'aZ09');
if (empty($fromType)) {
    setEventMessages('NoFromType', null, 'errors');
    accessforbidden();
}

$objectMetadata = saturne_get_objects_metadata($fromType);

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
if (isModEnabled('ticket')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formticket.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/ticket.lib.php';
    require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
}
if (isModEnabled('fckeditor')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
}

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_eventpro.lib.php';
require_once __DIR__ . '/../lib/reedcrm_function.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$id         = GETPOSTINT('from_id');
$action     = GETPOST('action', 'aZ09');
$currentTab = GETPOSTISSET('tab') ? GETPOST('tab', 'aZ09') : 'note';
$isModal    = GETPOSTINT('modal');

// Initialize objects
$object     = $objectMetadata['object'];
$actionComm = new ActionComm($db);
$category   = new Categorie($db);

// Initialize view objects
$form        = new Form($db);
$formProject = new FormProjets($db);
$formActions = new FormActions($db);
$formTicket  = isModEnabled('ticket') ? new FormTicket($db) : null; // FormTicket class is only loaded when the Ticket module is enabled (see require above)

$hookmanager->initHooks([$object->element . 'eventpro', 'globalcard']); // Note that conf->hooks_modules contains array

// Load object
require_once DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php';

if ($object instanceof Societe) {
    $object->thirdparty = $object;
}

// Permissions
$permissiontoread   = $user->hasRight('reedcrm', 'eventpro', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'eventpro', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'eventpro', 'delete');

// Security check
saturne_check_access($permissiontoread);

/*
*  Actions
*/

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    // create_contact / add_event / create_ticket, shared with the mobile App form (view/frontend/pwa_relaunch.php)
    require __DIR__ . '/../core/tpl/view/eventpro/eventpro_actions.tpl.php';
}

/*
* View
*/

if ($isModal) {
    // Modal mode: output only the form template without header/banner/footer
    if (empty($action)) {
        // The right-hand button of the relaunch widget asks for the short reminder form (#873),
        // the left-hand one for the complete event form.
        if (GETPOST('mode', 'aZ09') === 'reminder') {
            require_once __DIR__ . '/../lib/reedcrm_relaunch.lib.php';
            require_once __DIR__ . '/../core/tpl/view/eventpro/view_eventpro_reminder.tpl.php';
        } else {
            require_once __DIR__ . '/../core/tpl/view/eventpro/view_eventpro_actioncomm.tpl.php';
        }
    }
    $db->close();
    exit;
}

$title   = $langs->transnoentities('ReedCRM');
$helpUrl = 'FR:Module_ReedCRM';
$moreCSS = [
    '/custom/reedcrm/css/reedcrm.min.css',
    '/custom/reedcrm/css/temp.css'
];

saturne_header(0, '', $title, $helpUrl, '', 0, 0, [], $moreCSS, '', 'mod-reedcrm-' . $object->element . 'template-pwa page-list bodyforlist');

if (empty($action)) {
    saturne_get_fiche_head($object, 'event', $title);
    saturne_banner_tab($object);

    // ReedCRM: opportunity chain bar (projects only)
    if ($object->element === 'project') {
        require_once __DIR__ . '/../lib/reedcrm.lib.php';
        $reedcrmChainDocs = reedcrm_get_pwa_projects_documents([$object->id]);
        $chainBarDocs     = $reedcrmChainDocs[$object->id] ?? [];
        print reedcrm_chain_bar_styles();
        include __DIR__ . '/../core/tpl/frontend/reedcrm_opportunity_chain_bar.tpl.php';
    }

    print '<div class="fichecenter">';

    print '<div class="fichehalfleft">';
    require_once __DIR__ . '/../core/tpl/view/eventpro/view_eventpro_actioncomm.tpl.php';
    print '</div>';

    if (isset($object->thirdparty)) {
        print '<div class="fichehalfright">';
        print showEventProInfos($object);
        print '</div>';
    }

    print '</div>';
}

// End of page
llxFooter();
$db->close();
