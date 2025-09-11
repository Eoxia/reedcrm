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
 * \file    admin/call_notifications.php
 * \ingroup easycrm
 * \brief   EasyCRM call notifications configuration page
 */

// Load Dolibarr environment
if (file_exists(__DIR__ . '/../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../saturne/saturne.main.inc.php';
} elseif (file_exists(__DIR__ . '/../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}

global $db, $langs, $user, $conf;

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once __DIR__ . '/../lib/easycrm_function.lib.php';
require_once __DIR__ . '/../lib/easycrm.lib.php';

// Translations
$langs->loadLangs(array("admin", "easycrm@easycrm"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */

if ($action == 'updateconfig') {
    $call_notifications_disabled = GETPOST('EASYCRM_CALL_NOTIFICATIONS_DISABLED', 'alpha') ? 1 : 0;
    $call_check_frequency = GETPOSTINT('EASYCRM_CALL_CHECK_FREQUENCY');
    $auto_open_contact = GETPOST('EASYCRM_AUTO_OPEN_CONTACT', 'alpha') ? 1 : 0;
    $open_in_new_tab = GETPOST('EASYCRM_OPEN_IN_NEW_TAB', 'alpha') ? 1 : 0;
    $keyyo_token = GETPOST('EASY_CRM_KEYYO_EXPECTED_TOKEN', 'alpha');

    // Validate frequency
    if ($call_check_frequency < 2) $call_check_frequency = 2;
    if ($call_check_frequency > 60) $call_check_frequency = 60;

    dolibarr_set_const($db, 'EASYCRM_CALL_NOTIFICATIONS_DISABLED', $call_notifications_disabled, 'int', 0, '', $conf->entity);
    dolibarr_set_const($db, 'EASYCRM_CALL_CHECK_FREQUENCY', $call_check_frequency, 'int', 0, '', $conf->entity);
    dolibarr_set_const($db, 'EASYCRM_AUTO_OPEN_CONTACT', $auto_open_contact, 'int', 0, '', $conf->entity);
    dolibarr_set_const($db, 'EASYCRM_OPEN_IN_NEW_TAB', $open_in_new_tab, 'int', 0, '', $conf->entity);
    dolibarr_set_const($db, 'EASY_CRM_KEYYO_EXPECTED_TOKEN', $keyyo_token, 'chaine', 0, '', $conf->entity);

    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

/*
 * View
 */

$title = "EasyCRM - " . $langs->trans('CallNotifications');
$help_url = 'EN:Module_EasyCRM|FR:Module_EasyCRM|ES:Módulo_EasyCRM';
saturne_header(0,'', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'easycrm_color@easycrm');

// Configuration header
$head = easycrm_admin_prepare_head();
print dol_get_fiche_head($head, 'notifications', $title, -1, 'easycrm_color@easycrm');

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($page_name, $linkback, 'title_setup');

// Configuration form
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="updateconfig">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Parameter") . '</td>';
print '<td>' . $langs->trans("Description") . '</td>';
print '<td class="center">' . $langs->trans("Value") . '</td>';
print '</tr>';

// Call notifications disabled
print '<tr class="oddeven">';
print '<td>' . $langs->trans('CallNotificationsDisabled') . '</td>';
print '<td>' . $langs->trans('Disable call notifications globally for all users') . '</td>';
print '<td class="center">';
print '<input type="checkbox" name="EASYCRM_CALL_NOTIFICATIONS_DISABLED" value="1"' . (getDolGlobalInt('EASYCRM_CALL_NOTIFICATIONS_DISABLED') ? ' checked' : '') . '>';
print '</td></tr>';

// Call check frequency
print '<tr class="oddeven">';
print '<td>' . $langs->trans('CallCheckFrequency') . '</td>';
print '<td>' . $langs->trans('How often to check for new calls (in seconds, min: 2, max: 60)') . '</td>';
print '<td class="center">';
print '<input type="number" name="EASYCRM_CALL_CHECK_FREQUENCY" min="2" max="60" value="' . getDolGlobalInt('EASYCRM_CALL_CHECK_FREQUENCY', 5) . '">';
print '</td></tr>';

// Auto open contact
print '<tr class="oddeven">';
print '<td>' . $langs->trans('AutoOpenContact') . '</td>';
print '<td>' . $langs->trans('Automatically open contact card when receiving a call notification') . '</td>';
print '<td class="center">';
print '<input type="checkbox" name="EASYCRM_AUTO_OPEN_CONTACT" value="1"' . (getDolGlobalInt('EASYCRM_AUTO_OPEN_CONTACT') ? ' checked' : '') . '>';
print '</td></tr>';

// Open in new tab
print '<tr class="oddeven">';
print '<td>' . $langs->trans('OpenInNewTab') . '</td>';
print '<td>' . $langs->trans('Open contact card in a new tab instead of current window') . '</td>';
print '<td class="center">';
print '<input type="checkbox" name="EASYCRM_OPEN_IN_NEW_TAB" value="1"' . (getDolGlobalInt('EASYCRM_OPEN_IN_NEW_TAB', 1) ? ' checked' : '') . '>';
print '</td></tr>';

// Keyyo token
print '<tr class="oddeven">';
print '<td>' . $langs->trans('KeyyoWebhookToken') . '</td>';
print '<td>' . $langs->trans('Security token for Keyyo webhook (leave empty to disable token check)') . '</td>';
print '<td class="center">';
print '<input type="text" name="EASY_CRM_KEYYO_EXPECTED_TOKEN" value="' . getDolGlobalString('EASY_CRM_KEYYO_EXPECTED_TOKEN') . '" size="30">';
print '</td></tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="' . $langs->trans("Save") . '">';
print '</div>';

print '</form>';

// Information section
print '<br>';
print load_fiche_titre($langs->trans('Information'), '', 'info');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">' . $langs->trans("WebhookConfiguration") . '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><strong>' . $langs->trans('WebhookURL') . '</strong></td>';
print '<td>' . dol_buildpath('/custom/easycrm/webhook/keyyo_webhook.php', 2);
if (getDolGlobalString('EASY_CRM_KEYYO_EXPECTED_TOKEN')) {
    print '?token=' . getDolGlobalString('EASY_CRM_KEYYO_EXPECTED_TOKEN');
}
print '</td></tr>';

print '<tr class="oddeven">';
print '<td><strong>' . $langs->trans('Method') . '</strong></td>';
print '<td>POST ou GET</td></tr>';

print '<tr class="oddeven">';
print '<td><strong>' . $langs->trans('Parameters') . '</strong></td>';
print '<td>caller (numéro appelant), callee (numéro appelé)</td></tr>';

print '</table>';

// End of page
llxFooter();
