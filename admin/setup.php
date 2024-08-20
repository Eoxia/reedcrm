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
 * \file    admin/setup.php
 * \ingroup easycrm
 * \brief   EasyCRM setup page.
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

if (isModEnabled('project')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
}
if (isModEnabled('societe')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
}
if (isModEnabled('agenda')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
}

require_once __DIR__ . '/../lib/easycrm.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin', 'categories']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize view objects
$form = new Form($db);
if (isModEnabled('project')) {
    $formproject = new FormProjets($db);
}
if (isModEnabled('societe')) {
    $formcompany = new FormCompany($db);
}
if (isModEnabled('agenda')) {
    $formactions = new FormActions($db);
}

// Security check - Protection if external user
$permissiontoread = $user->rights->easycrm->adminpage->read;
saturne_check_access($permissiontoread);

/*
 * Actions
 */

if ($action == 'set_config') {
    $projectOpportunityStatus = GETPOST('project_opportunity_status');
    $projectOpportunityAmount = GETPOST('project_opportunity_amount');
    $client                   = GETPOST('client');
    $taskLabel                = GETPOST('task_label');
    $timespent                = GETPOST('timespent');
    $typeEvent                = GETPOST('actioncode');
    $statusEvent              = (GETPOST('status') == 'NA' ? -1 : GETPOST('status'));

    if (!empty($projectOpportunityStatus)) {
        dolibarr_set_const($db, 'EASYCRM_PROJECT_OPPORTUNITY_STATUS_VALUE', $projectOpportunityStatus, 'integer', 0, '', $conf->entity);
    }
    if ($projectOpportunityAmount >= 0) {
        dolibarr_set_const($db, 'EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VALUE', $projectOpportunityAmount, 'integer', 0, '', $conf->entity);
    }
    if (!empty($client)) {
        dolibarr_set_const($db, 'EASYCRM_THIRDPARTY_CLIENT_VALUE', $client, 'integer', 0, '', $conf->entity);
    }
    if (!empty($taskLabel)) {
        dolibarr_set_const($db, 'EASYCRM_TASK_LABEL_VALUE', $taskLabel, 'chaine', 0, '', $conf->entity);
    }
    if ($timespent >= 0) {
        dolibarr_set_const($db, 'EASYCRM_TASK_TIMESPENT_VALUE', $timespent, 'chaine', 0, '', $conf->entity);
    }
    if (!empty($typeEvent)) {
        dolibarr_set_const($db, 'EASYCRM_EVENT_TYPE_CODE_VALUE', $typeEvent, 'chaine', 0, '', $conf->entity);
    }
    dolibarr_set_const($db, 'EASYCRM_EVENT_STATUS_VALUE', $statusEvent, 'integer', 0, '', $conf->entity);

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


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
print dol_get_fiche_head($head, 'settings', $title, -1, 'easycrm_color@easycrm');

print load_fiche_titre($langs->trans('Configs', $langs->trans('QuickCreations')), '', '');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="quickcreation_data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_config">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Visible') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

// THIRDPARTY
print '<tr class="oddeven"><td colspan="4" class="center"><div class="titre inline-block">' . $langs->trans('Configs', $langs->trans('QuickThirdPartyCreations')) . '</div></td></tr>';

// ProspectCustomer
print '<tr class="oddeven"><td>';
print $langs->trans('ProspectCustomer');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('ProspectCustomer'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_THIRDPARTY_CLIENT_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_THIRDPARTY_CLIENT_VISIBLE > 0 && isModEnabled('societe')) {
    print '<td>' . $formcompany->selectProspectCustomerType($conf->global->EASYCRM_THIRDPARTY_CLIENT_VALUE, 'client', 'customerprospect', 'form', 'minwidth200') . '</td>';
} else {
    print '<td></td>';
}
print '</tr>';

// ThirdPartyName
print '<tr class="oddeven"><td>';
print $langs->trans('ThirdPartyName');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('ThirdPartyName'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_THIRDPARTY_NAME_VISIBLE');
print '</td></td><td></td></tr>';

// Phone
print '<tr class="oddeven"><td>';
print $langs->trans('Phone');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Phone'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_THIRDPARTY_PHONE_VISIBLE');
print '</td></td><td></td></tr>';

// Email
print '<tr class="oddeven"><td>';
print $langs->trans('Email');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Email'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_THIRDPARTY_EMAIL_VISIBLE');
print '</td></td><td></td></tr>';

// Web
print '<tr class="oddeven"><td>';
print $langs->trans('Web');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Web'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_THIRDPARTY_WEB_VISIBLE');
print '</td></td><td></td></tr>';

// Commercial
print '<tr class="oddeven"><td>';
print $langs->trans('AllocateCommercial');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('AllocateCommercial'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_THIRDPARTY_COMMERCIAL_VISIBLE');
print '</td></td><td></td></tr>';

// Private note
print '<tr class="oddeven"><td>';
print $langs->trans('NotePrivate');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('NotePrivate'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_THIRDPARTY_PRIVATE_NOTE_VISIBLE');
print '</td></td><td></td></tr>';

// CustomersProspectsCategoriesShort
print '<tr class="oddeven"><td>';
print $langs->trans('CustomersProspectsCategoriesShort');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('CustomersProspectsCategoriesShort'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_THIRDPARTY_CATEGORIES_VISIBLE');
print '</td></td><td></td></tr>';

// CONTACT
print '<tr class="oddeven"><td colspan="4" class="center"><div class="titre inline-block">' . $langs->trans('Configs', $langs->trans('QuickContactCreations')) . '</div></td></tr>';

// Lastname
print '<tr class="oddeven"><td>';
print $langs->trans('Lastname');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Lastname'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_CONTACT_LASTNAME_VISIBLE');
print '</td></td><td></td></tr>';

// Firstname
print '<tr class="oddeven"><td>';
print $langs->trans('Firstname');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Firstname'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_CONTACT_FIRSTNAME_VISIBLE');
print '</td></td><td></td></tr>';

// Job
print '<tr class="oddeven"><td>';
print $langs->trans('PostOrFunction');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('PostOrFunction'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_CONTACT_JOB_VISIBLE');
print '</td></td><td></td></tr>';

// Phone pro
print '<tr class="oddeven"><td>';
print $langs->trans('PhonePro');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('PhonePro'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_CONTACT_PHONEPRO_VISIBLE');
print '</td></td><td></td></tr>';

// Email
print '<tr class="oddeven"><td>';
print $langs->trans('Email');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Email'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_CONTACT_EMAIL_VISIBLE');
print '</td></td><td></td></tr>';

// PROJECT
print '<tr class="oddeven"><td colspan="4" class="center"><div class="titre inline-block">' . $langs->trans('Configs', $langs->transnoentities('QuickProjectCreations')) . '</div></td></tr>';

// ProjectLabel
print '<tr class="oddeven"><td>';
print $langs->trans('ProjectLabel');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('ProjectLabel'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_PROJECT_LABEL_VISIBLE');
print '</td></td><td></td></tr>';

// OpportunityStatus
print '<tr class="oddeven"><td>';
print $langs->trans('OpportunityStatus');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('OpportunityStatus'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_PROJECT_OPPORTUNITY_STATUS_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_PROJECT_OPPORTUNITY_STATUS_VISIBLE > 0 && isModEnabled('project')) {
    print '<td>';
    print $formproject->selectOpportunityStatus('project_opportunity_status', $conf->global->EASYCRM_PROJECT_OPPORTUNITY_STATUS_VALUE, 1, 0, 0, 0, 'minwidth200', 0, 1);
    print '</td>';
}
print '</td></tr>';

// OpportunityAmount
print '<tr class="oddeven"><td>';
print $langs->trans('OpportunityAmount');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('OpportunityAmount'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) {
    print '<td><input type="number" name="project_opportunity_amount" class="minwidth200" value="' . $conf->global->EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VALUE . '"></td>';
} else {
    print '<td></td>';
}
print '</tr>';

// DateStart
print '<tr class="oddeven"><td>';
print $langs->trans('DateStart');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('DateStart'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_PROJECT_DATE_START_VISIBLE');
print '</td></td><td></td></tr>';

// Description
print '<tr class="oddeven"><td>';
print $langs->trans('Description');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Description'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_PROJECT_DESCRIPTION_VISIBLE');
print '</td></td><td></td></tr>';

// Extrafields
print '<tr class="oddeven"><td>';
print $langs->trans('Extrafields');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Extrafields'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_PROJECT_CATEGORIES_VISIBLE');
print '</td></td><td></td></tr>';

// Categories
print '<tr class="oddeven"><td>';
print $langs->trans('Categories');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Categories'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_PROJECT_CATEGORIES_VISIBLE');
print '</td></td><td></td></tr>';

// TASK
print '<tr class="oddeven"><td colspan="4" class="center"><div class="titre inline-block">' . $langs->trans('Configs', $langs->transnoentities('QuickTaskCreations')) . '</div></td></tr>';

// TaskLabel
print '<tr class="oddeven"><td>';
print $langs->trans('Label');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Label'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_TASK_LABEL_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_TASK_LABEL_VISIBLE > 0 && isModEnabled('project')) {
    print '<td><input type="text" id="task_label" name="task_label" value="' . $conf->global->EASYCRM_TASK_LABEL_VALUE . '"></td>';
} else {
    print '<td></td>';
}
print '</tr>';

// TimeSpent
print '<tr class="oddeven"><td>';
print $langs->trans('TimeSpent');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('TimeSpent'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_TASK_TIMESPENT_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_TASK_TIMESPENT_VISIBLE > 0) {
    print '<td><input type="number" id="timespent" name="timespent" value="' . $conf->global->EASYCRM_TASK_TIMESPENT_VALUE . '"></td>';
} else {
    print '<td></td>';
}
print '</tr>';

// EVENT
print '<tr class="oddeven"><td colspan="4" class="center"><div class="titre inline-block">' . $langs->trans('Configs', $langs->transnoentities('QuickEventCreations')) . '</div></td></tr>';

// Type
print '<tr class="oddeven"><td>';
print $langs->trans('Type');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Type'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_EVENT_TYPE_CODE_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_EVENT_TYPE_CODE_VISIBLE > 0) {
    print '<td>';
    print $formactions->select_type_actions($conf->global->EASYCRM_EVENT_TYPE_CODE_VALUE, 'actioncode', 'systemauto', 0, -1, 0, 1);
    print '</td>';
}
print '</td></tr>';

// Label
print '<tr class="oddeven"><td>';
print $langs->trans('Label');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Label'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_EVENT_LABEL_VISIBLE');
print '</td></td><td></td></tr>';

// Date start
print '<tr class="oddeven"><td>';
print $langs->trans('DateStart');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('DateStart'));
print '</td>';

print '<td class="center">';
//print ajax_constantonoff('EASYCRM_EVENT_DATE_START_VISIBLE');
print '</td></td><td></td></tr>';

// Date end
print '<tr class="oddeven"><td>';
print $langs->trans('DateEnd');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('DateEnd'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_EVENT_DATE_END_VISIBLE');
print '</td></td><td></td></tr>';

// Status
print '<tr class="oddeven"><td>';
print $langs->trans('Status');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Status'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_EVENT_STATUS_VISIBLE');
print '</td>';

if ($conf->global->EASYCRM_EVENT_STATUS_VISIBLE > 0) {
    $listofstatus = [
        'NA' => $langs->trans('ActionNotApplicable'),
        0    => $langs->trans('ActionsToDoShort'),
        50   => $langs->trans('ActionRunningShort'),
        100  => $langs->trans('ActionDoneShort')
    ];
    print '<td>' . $form->selectarray('status', $listofstatus, (GETPOSTISSET('status') ? GETPOST('status') : $conf->global->EASYCRM_EVENT_STATUS_VALUE), 0, 0, 0, '', 0, 0, 0, '', 'maxwidth200 widthcentpercentminusx') . '</td>';
} else {
    print '<td></td>';
}
print '</tr>';

// Description
print '<tr class="oddeven"><td>';
print $langs->trans('Description');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->trans('Description'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_EVENT_DESCRIPTION_VISIBLE');
print '</td></td><td></td></tr>';

// Categories
print '<tr class="oddeven"><td>';
print $langs->trans('Categories');
print '</td><td>';
print $langs->trans('ObjectVisibleDescription', $langs->transnoentities('Categories'));
print '</td>';

print '<td class="center">';
print ajax_constantonoff('EASYCRM_EVENT_CATEGORIES_VISIBLE');
print '</td></td><td></td></tr>';




print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

$db->close();
llxFooter();
