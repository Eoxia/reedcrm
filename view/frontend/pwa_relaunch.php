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
 * \file    view/frontend/pwa_relaunch.php
 * \ingroup reedcrm
 * \brief   Mobile App form to log a relaunch on an opportunity (customer note, email or ticket).
 *          Same handlers and same tab bodies as the desktop card (view/procard.php), single column layout.
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
if (isModEnabled('ticket')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formticket.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/ticket.lib.php';
    require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
}
if (isModEnabled('fckeditor')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
}

// Load ReedCRM libraries
require_once __DIR__ . '/../../lib/reedcrm_eventpro.lib.php';
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['projects', 'agenda', 'companies', 'mails', 'ticket']);

// Get parameters
$id         = GETPOSTINT('from_id') ?: GETPOSTINT('id');
$fromType   = GETPOST('from_type', 'aZ09') ?: 'project';
$action     = GETPOST('action', 'aZ09');
$currentTab = GETPOSTISSET('tab') ? GETPOST('tab', 'aZ09') : 'note';
$actionCode = GETPOST('actioncode', 'aZ09') ?: getDolGlobalString('REEDCRM_EVENT_TYPE_CODE_VALUE');
$callListId = GETPOSTINT('call_list_id');
$isModal    = 0;

// Initialize technical objects
$actionComm = new ActionComm($db);
$category   = new Categorie($db);

// Initialize view objects
$form        = new Form($db);
$formActions = new FormActions($db);
$formProject = new FormProjets($db);
$formTicket  = isModEnabled('ticket') ? new FormTicket($db) : null; // FormTicket class is only loaded when the Ticket module is enabled (see require above)

$hookmanager->initHooks(['projecteventpro', 'reedcrm_pwa_relaunch']); // Note that conf->hooks_modules contains array

// Permissions
$permissiontoread   = $user->hasRight('reedcrm', 'eventpro', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'eventpro', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'eventpro', 'delete');
saturne_check_access($permissiontoread);

// Only opportunities (projects) are exposed in the App for now; the desktop card handles the other types
if ($fromType !== 'project' || !isModEnabled('project')) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

$object = new Project($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
    accessforbidden($langs->trans('RecordNotFound'), 0);
    exit;
}
$object->fetch_optionals();
$object->fetch_thirdparty();

/*
 * Actions
 */

$backUrl  = dol_buildpath('/custom/reedcrm/view/frontend/pwa_opportunity.php', 1);
$backUrl .= '?from_id=' . (int) $object->id . '&from_type=project';
if ($callListId > 0) {
    $backUrl .= '&call_list_id=' . $callListId;
}

$eventProRedirectAfterEvent  = $backUrl;
$eventProRedirectAfterTicket = $backUrl;

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    // create_contact / add_event / create_ticket, shared with the desktop card (view/procard.php)
    require __DIR__ . '/../../core/tpl/view/eventpro/eventpro_actions.tpl.php';
}

/*
 * View
 */

$title   = $langs->trans('QuickEventCreation');
$helpUrl = 'FR:Module_ReedCRM';
$moreJS  = [
    '/custom/saturne/js/saturne.min.js',
    '/custom/reedcrm/js/reedcrm.min.js',
    '/custom/reedcrm/js/modules/eventpro.js'
];
$moreCSS = ['/custom/saturne/css/saturne.min.css', '/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, $helpUrl, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-relaunch');

$pwaHeaderCenterHtml  = '<a class="pwa-opp-back" href="' . dol_escape_htmltag($backUrl) . '">';
$pwaHeaderCenterHtml .= '<i class="fas fa-arrow-left"></i> ' . dol_escape_htmltag($langs->trans('BackToOpportunity'));
$pwaHeaderCenterHtml .= '</a>';
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

if (empty($permissiontoadd)) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

$project        = $object;
$oppContact     = reedcrm_get_project_contact_details($project);
$pwaHeadCompact = true;

print '<div class="pwa-relaunch-container">';

require __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_opportunity_head.tpl.php';

// Keep the picked event type when switching tabs, otherwise the shortcut the user came from is lost
$eventProTabExtraParams = '&actioncode=' . urlencode($actionCode) . ($callListId > 0 ? '&call_list_id=' . $callListId : '');

require __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_relaunch_form.tpl.php';

print '</div>';

// The reminder toggle is bound by eventpro.js only inside the desktop modal, so bind it here too
print '<script>
$(function () {
    $("#toggle_reminder").on("change", function () {
        $("#reminder_fields").slideToggle(200);
    });
    if (window.reedcrm && window.reedcrm.eventpro && window.reedcrm.eventpro.initAddContact) {
        window.reedcrm.eventpro.initAddContact();
    }
});
</script>';

// End of page
llxFooter();
$db->close();
