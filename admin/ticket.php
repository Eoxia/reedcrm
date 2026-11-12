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
    
    $ticketTimeTaskSuffix = GETPOST('ticket_time_task_suffix', 'alpha');
    if (!empty($ticketTimeTaskSuffix)) {
        dolibarr_set_const($db, 'REEDCRM_TICKET_TIME_TASK_SUFFIX', $ticketTimeTaskSuffix, 'chaine', 0, '', $conf->entity);
    } else {
        dolibarr_set_const($db, 'REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref', 'chaine', 0, '', $conf->entity);
    }

    $ticketTimeTitleMaxLength = GETPOSTINT('ticket_time_title_max_length');
    if ($ticketTimeTitleMaxLength > 0) {
        dolibarr_set_const($db, 'REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', $ticketTimeTitleMaxLength, 'integer', 0, '', $conf->entity);
    } else {
        dolibarr_del_const($db, 'REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', $conf->entity);
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

// Tickets and money (#1242) : the empty values fall back on the defaults of ReedcrmTicketMoney
if ($action == 'set_money_config') {
    $products = implode(',', array_filter(array_map('trim', explode(',', GETPOST('ticket_money_subscription_products', 'alphanohtml'))), function ($ref) {
        return $ref !== '';
    }));
    dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_SUBSCRIPTION_PRODUCTS', $products, 'chaine', 0, '', $conf->entity);
    $productRef = trim(GETPOST('ticket_money_product_ref', 'alphanohtml'));
    if ($productRef !== '') {
        dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_PRODUCT_REF', $productRef, 'chaine', 0, '', $conf->entity);
    } else {
        dolibarr_del_const($db, 'REEDCRM_TICKET_MONEY_PRODUCT_REF', $conf->entity);
    }
    dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_ROUNDING_MINUTES', max(1, GETPOSTINT('ticket_money_rounding_minutes')), 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_DORMANT_DAYS', max(0, GETPOSTINT('ticket_money_dormant_days')), 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_OVERDUE_GRACE_DAYS', max(0, GETPOSTINT('ticket_money_overdue_grace_days')), 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_MENU', GETPOSTINT('ticket_money_menu') ? 1 : 0, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'REEDCRM_TICKET_MONEY_BANNER', GETPOSTINT('ticket_money_banner') ? 1 : 0, 'integer', 0, '', $conf->entity);

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
print $langs->trans('TicketTimeTaskSuffix');
print '</td><td>';
print $langs->transnoentities('TicketTimeTaskSuffixDescription');
print '</td>';
print '<td>';
$options = array(
    'ticket_ref' => $langs->trans('TicketTimeTaskSuffixTicketRef'),
    'project_ref' => $langs->trans('TicketTimeTaskSuffixProjectRef'),
    'project_label' => $langs->trans('TicketTimeTaskSuffixProjectLabel'),
    'none' => $langs->trans('TicketTimeTaskSuffixNone')
);
print $form->selectarray('ticket_time_task_suffix', $options, getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref'));
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('TicketTimeTitleMaxLength');
print '</td><td>';
print $langs->transnoentities('TicketTimeTitleMaxLengthDescription');
print '</td>';
print '<td><input type="number" id="ticket_time_title_max_length" name="ticket_time_title_max_length" min="1" max="255" value="' . getDolGlobalInt('REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', 200) . '"></td>';
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

print load_fiche_titre($langs->trans('TicketMoneyConfig'), '', '');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_money_config">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

$moneySettings = [
    ['TicketMoneySubscriptionProducts', '<input type="text" class="minwidth300" name="ticket_money_subscription_products" value="' . dol_escape_htmltag(getDolGlobalString('REEDCRM_TICKET_MONEY_SUBSCRIPTION_PRODUCTS', 'D2,D3,D4,D41,D5')) . '">'],
    ['TicketMoneyProductRef', '<input type="text" class="minwidth200" name="ticket_money_product_ref" value="' . dol_escape_htmltag(getDolGlobalString('REEDCRM_TICKET_MONEY_PRODUCT_REF', 'AT-Technique-1H')) . '">'],
    ['TicketMoneyRoundingMinutes', '<input type="number" class="width75" min="1" name="ticket_money_rounding_minutes" value="' . getDolGlobalInt('REEDCRM_TICKET_MONEY_ROUNDING_MINUTES', 15) . '">'],
    ['TicketMoneyDormantDays', '<input type="number" class="width75" min="0" name="ticket_money_dormant_days" value="' . getDolGlobalInt('REEDCRM_TICKET_MONEY_DORMANT_DAYS', 90) . '">'],
    ['TicketMoneyOverdueGraceDays', '<input type="number" class="width75" min="0" name="ticket_money_overdue_grace_days" value="' . getDolGlobalInt('REEDCRM_TICKET_MONEY_OVERDUE_GRACE_DAYS') . '">'],
    ['TicketMoneyMenu', $form->selectyesno('ticket_money_menu', getDolGlobalInt('REEDCRM_TICKET_MONEY_MENU', 1), 1)],
    ['TicketMoneyBanner', $form->selectyesno('ticket_money_banner', getDolGlobalInt('REEDCRM_TICKET_MONEY_BANNER', 1), 1)],
];
foreach ($moneySettings as $setting) {
    print '<tr class="oddeven"><td>' . $langs->trans($setting[0]) . '</td>';
    print '<td>' . $langs->transnoentities($setting[0] . 'Description') . '</td>';
    print '<td>' . $setting[1] . '</td></tr>';
}

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';
