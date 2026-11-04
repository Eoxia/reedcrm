<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    admin/pocket.php
 * \ingroup reedcrm
 * \brief   ReedCRM Pocket config page: API key, imported folder and linkable objects.
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm.lib.php';
require_once __DIR__ . '/../lib/reedcrm_pocketrecording.lib.php';
require_once __DIR__ . '/../class/pocketaccount.class.php';
require_once __DIR__ . '/../class/pocketcron.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissiontoread = $user->hasRight('reedcrm', 'adminpage', 'read');

saturne_check_access($permissiontoread);

$form = new Form($db);

/*
 * Actions
 */

// The shared account: the one of the users who did not set a key of their own
$pocketAccount = PocketAccount::getShared($db);

if ($permissiontoread && reedcrm_pocket_process_account_action($action, $pocketAccount, $user)) {
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Daily reminder to file the calls, empty time to disable it
if ($action == 'set_pocket_tidy_reminder' && $permissiontoread) {
    $tidyReminderTime = GETPOST('pocket_tidy_reminder_time', 'alphanohtml');

    if ($tidyReminderTime === '') {
        dolibarr_del_const($db, PocketCron::TIDY_REMINDER_TIME, $conf->entity);
        setEventMessage($langs->trans('SavedConfig'));
    } elseif (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $tidyReminderTime)) {
        dolibarr_set_const($db, PocketCron::TIDY_REMINDER_TIME, $tidyReminderTime, 'chaine', 0, '', $conf->entity);

        // The time is the one the admin reads on their clock, the server may well run in UTC
        $browserTimezone = (string) ($_SESSION['dol_tz_string'] ?? '');
        if (in_array($browserTimezone, DateTimeZone::listIdentifiers(), true)) {
            dolibarr_set_const($db, PocketCron::TIDY_REMINDER_TZ, $browserTimezone, 'chaine', 0, '', $conf->entity);
        } else {
            dolibarr_del_const($db, PocketCron::TIDY_REMINDER_TZ, $conf->entity);
        }

        setEventMessage($langs->trans('SavedConfig'));
    } else {
        setEventMessages($langs->trans('ErrorBadValueForParameter', dol_escape_htmltag($tidyReminderTime), $langs->transnoentities('PocketTidyReminderTime')), [], 'errors');
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Linked objects actions
if (in_array($action, ['toggle_link', 'toggle_all_links', 'clean_unused_links']) && $user->admin) {
    $db->begin();

    if ($action == 'toggle_link') {
        $objectType = GETPOST('objecttype', 'aZ09');
        $value      = GETPOSTINT('value');

        $linkableObjects = reedcrm_pocket_get_linkable_objects();
        if (isset($linkableObjects[$objectType])) {
            dolibarr_set_const($db, REEDCRM_POCKET_LINK_CONST_PREFIX . strtoupper($objectType), $value, 'integer', 0, '', $conf->entity);
        }
    } elseif ($action == 'toggle_all_links') {
        $value = GETPOSTINT('value');

        foreach (array_keys(reedcrm_pocket_get_linkable_objects()) as $objectType) {
            dolibarr_set_const($db, REEDCRM_POCKET_LINK_CONST_PREFIX . strtoupper($objectType), $value, 'integer', 0, '', $conf->entity);
        }
    }

    // clean_unused_links has no branch of its own: realigning on the constants is its whole job.
    $report = reedcrm_pocket_sync_linked_objects();

    if ($report['errors'] > 0) {
        $db->rollback();
        setEventMessages($langs->trans('LinkedObjectSyncError'), [], 'errors');
    } else {
        $db->commit();
        setEventMessage($langs->trans('LinkedObjectSyncDone', $report['tabs'], $report['hooks'], 0, 0));
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title    = $langs->trans('ModuleSetup', 'ReedCRM');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'reedcrm_color@reedcrm');

// Configuration header
$head = reedcrm_admin_prepare_head();
print dol_get_fiche_head($head, 'pocket', $title, -1, 'reedcrm_color@reedcrm');

$pocketFormUrl = $_SERVER['PHP_SELF'];
require_once __DIR__ . '/../core/tpl/reedcrm_pocket_account_setup.tpl.php';

// Personal accounts, set by each user on the Pocket tab of their user card
print load_fiche_titre($langs->trans('PocketPersonalAccounts'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('User') . '</td>';
print '<td>' . $langs->trans('PocketFolder') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

$personalAccounts = PocketAccount::getAllPersonal($db);
if (empty($personalAccounts)) {
    print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">' . $langs->trans('PocketNoPersonalAccount') . '</span></td></tr>';
}

$accountOwner = new User($db);
foreach ($personalAccounts as $personalAccount) {
    if ($accountOwner->fetch($personalAccount->fk_user) <= 0) {
        continue;
    }

    print '<tr class="oddeven">';
    print '<td>' . $accountOwner->getNomUrl(-1) . '</td>';
    print '<td>' . ($personalAccount->folderId !== '' ? dol_escape_htmltag($personalAccount->folderLabel ?: $personalAccount->folderId) : '<span class="opacitymedium">' . $langs->trans('PocketFolderMissing') . '</span>') . '</td>';
    print '<td class="center"><a class="button" href="' . dol_buildpath('/reedcrm/view/pocketrecording/pocketrecording_user.php', 1) . '?id=' . $accountOwner->id . '">' . $langs->trans('Modify') . '</a></td>';
    print '</tr>';
}

print '</table>';

require_once __DIR__ . '/../core/tpl/reedcrm_pocket_tidy_reminder_setup.tpl.php';

// Linkable elements, driven by the REEDCRM_POCKET_LINK_* constants
$linkableObjects            = reedcrm_pocket_get_linkable_objects();
$enabledObjectTypes         = reedcrm_pocket_get_enabled_linked_object_types();
$linkedObjectUsage          = reedcrm_pocket_get_linked_object_usage();
$linkedObjectExtraFieldName = REEDCRM_POCKET_LINK_ELEMENT_TYPE;

require_once dol_buildpath('/saturne/core/tpl/admin/object/linked_object_view.tpl.php');

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
