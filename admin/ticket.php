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
 * \file    admin/ticket.php
 * \ingroup reedcrm
 * \brief   ReedCRM ticket config page.
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

/*
 * Actions
 */

if ($action == 'set_config') {
    $ticketTimeTaskPrefix = GETPOST('ticket_time_task_prefix', 'alpha');
    $ticketTimeDefaultMinutes = GETPOSTINT('ticket_time_default_minutes');

    if (!empty($ticketTimeTaskPrefix)) {
        dolibarr_set_const($db, 'REEDCRM_TICKET_TIME_TASK_PREFIX', $ticketTimeTaskPrefix, 'chaine', 0, '', $conf->entity);
    } else {
        dolibarr_del_const($db, 'REEDCRM_TICKET_TIME_TASK_PREFIX', $conf->entity);
    }

    if ($ticketTimeDefaultMinutes > 0) {
        dolibarr_set_const($db, 'REEDCRM_TICKET_TIME_DEFAULT_MINUTES', $ticketTimeDefaultMinutes, 'integer', 0, '', $conf->entity);
    } else {
        dolibarr_del_const($db, 'REEDCRM_TICKET_TIME_DEFAULT_MINUTES', $conf->entity);
    }

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


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
print dol_get_fiche_head($head, 'ticket', $title, -1, 'reedcrm_color@reedcrm');

print load_fiche_titre($langs->trans('Configs', $langs->trans('Ticket')), '', '');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_config">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('TicketTimeTaskPrefix');
print '</td><td>';
print $langs->transnoentities('TicketTimeTaskPrefixDescription');
print '</td>';
print '<td><input type="text" id="ticket_time_task_prefix" name="ticket_time_task_prefix" value="' . getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps') . '"></td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('TicketTimeDefaultMinutes');
print '</td><td>';
print $langs->transnoentities('TicketTimeDefaultMinutesDescription');
print '</td>';
print '<td><input type="number" id="ticket_time_default_minutes" name="ticket_time_default_minutes" min="1" value="' . getDolGlobalInt('REEDCRM_TICKET_TIME_DEFAULT_MINUTES', 15) . '"></td>';
print '</tr>';

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';
