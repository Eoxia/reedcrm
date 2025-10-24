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

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$id         = GETPOSTINT('from_id');
$action     = GETPOST('action', 'aZ09');
$currentTab = GETPOSTISSET('tab') ? GETPOST('tab', 'aZ09') : 'note';

// Initialize objects
$object     = $objectMetadata['object'];
$actionComm = new ActionComm($db);
$category   = new Categorie($db);

// Initialize view objects
$form        = new Form($db);
$formProject = new FormProjets($db);
$formActions = new FormActions($db);
$formTicket  = new FormTicket($db);

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
    // Action to add commercial relaunch event
    if ($action == 'add_event') {
        $actionComm->socid             = GETPOSTINT('socid');
        $actionComm->socpeopleassigned = [GETPOSTINT('contactid') => GETPOSTINT('contactid')];
        $actionComm->type_code         = GETPOST('actioncode', 'aZ09');

        $datep = dol_mktime(GETPOSTINT('event_hour') - 2, GETPOSTINT('event_min'), 0, GETPOSTINT('event_month'), GETPOSTINT('event_day'), GETPOSTINT('event_year'));
        if ($datep > 0) {
            $actionComm->datep = $datep;
        } else {
            $actionComm->datep = dol_now();
        }

        $actionComm->fk_project   = GETPOST('project_id', 'int');
        $actionComm->userownerid  = $user->id;
        $actionComm->userassigned = [$user->id => ['id' => $user->id]];

        $actionComm->label        = GETPOST('title');
        $actionComm->note_private = GETPOST('description', 'restricthtml');

        $result = $actionComm->create($user);

        $category->fetch(getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG'));
        $category->add_type($actionComm, 'actioncomm');

        if ($result > 0) {
            setEventMessages($langs->trans('EventCreated'), null);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=note');
            exit;
        } else {
            setEventMessages($actionComm->error, $actionComm->errors, 'errors');
        }
    }

    // Action to create ticket
    if ($action == 'create_ticket' && isModEnabled('ticket')) {

        $ticket = new Ticket($db);

        // Get form data
        $ticket->ref = $ticket->getDefaultRef($user);
        $ticket->subject = GETPOST('ticket_subject', 'alphanohtml');
        $ticket->message = GETPOST('ticket_message', 'restricthtml');
        $ticket->fk_project = GETPOST('project_id');
        $ticket->fk_soc = GETPOST('ticket_socid', 'int') ?: $societe->id;
        $ticket->fk_user_assign = GETPOST('ticket_user_assign', 'int');
        $ticket->type_code = GETPOST('ticket_type', 'aZ09');
        $ticket->category_code = GETPOST('ticket_category', 'aZ09');
        $ticket->timing = GETPOST('ticket_timing', 'int');
        $ticket->status = 0; // New ticket

        // Handle date start
        $date_start = GETPOST('ticket_date_start', 'int');
        if ($date_start > 0) {
            $ticket->datec = $date_start;
        } else {
            $ticket->datec = dol_now();
        }

        // Set contact if selected
        $contactid = GETPOST('ticket_contact_id', 'int');
        if ($contactid > 0) {
            $ticket->context['contactid'] = $contactid;
        }

        // Disable email notifications for ticket creation
        $ticket->context['disableticketemail'] = 1;

        // Create the ticket
        $result = $ticket->create($user);

        if ($result > 0) {
            setEventMessages($langs->trans("TicketCreated"), null, 'mesgs');
            // Redirect to avoid resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType);
            exit;
        } else {
            setEventMessages($ticket->error, $ticket->errors, 'errors');
        }
    }
}

/*
* View
*/

$title   = $langs->transnoentities('ReedCRM');
$helpUrl = 'FR:Module_ReedCRM';
$moreCSS = ['/custom/reedcrm/css/temp.css'];

saturne_header(0, '', $title, $helpUrl, '', 0, 0, [], $moreCSS, '', 'mod-reedcrm-' . $object->element . 'template-pwa page-list bodyforlist');

if (empty($action)) {
    saturne_get_fiche_head($object, 'event', $title);
    saturne_banner_tab($object);

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    print '<div class="fichehalfleft">';
    print '<div class="div-table-responsive-no-min">';

    require_once __DIR__ . '/../core/tpl/view/eventpro/view_eventpro_actioncomm.tpl.php';

    print '</div>';
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
