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
require_once __DIR__ . '/../class/pocketapi.class.php';
require_once __DIR__ . '/../class/pocketsync.class.php';

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

if ($action == 'set_pocket_api_key' && $permissiontoread) {
    // The key is only rewritten when the field carries something: an untouched masked field must
    // never wipe a working key.
    $apiKey = trim(GETPOST('pocket_api_key', 'alphanohtml'));
    if ($apiKey !== '') {
        dolibarr_set_const($db, 'REEDCRM_POCKET_API_KEY', $apiKey, 'chaine', 0, '', $conf->entity);
        setEventMessage($langs->trans('SavedConfig'));
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'del_pocket_api_key' && $permissiontoread) {
    dolibarr_del_const($db, 'REEDCRM_POCKET_API_KEY', $conf->entity);
    dolibarr_del_const($db, 'REEDCRM_POCKET_FOLDER_ID', $conf->entity);
    dolibarr_del_const($db, 'REEDCRM_POCKET_FOLDER_LABEL', $conf->entity);
    setEventMessage($langs->trans('SavedConfig'));

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'set_pocket_folder' && $permissiontoread) {
    $folderId = GETPOST('pocket_folder_id', 'alphanohtml');

    // The label is denormalised so the recordings list and the sync stay readable even when the
    // API is unreachable.
    $folderLabel = '';
    if ($folderId !== '') {
        $pocketApi = new PocketApi($db);
        foreach ((array) $pocketApi->getFolders() as $folder) {
            if ($folder['id'] === $folderId) {
                $folderLabel = $folder['label'];
                break;
            }
        }
    }

    dolibarr_set_const($db, 'REEDCRM_POCKET_FOLDER_ID', $folderId, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'REEDCRM_POCKET_FOLDER_LABEL', $folderLabel, 'chaine', 0, '', $conf->entity);
    setEventMessage($langs->trans('SavedConfig'));

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'test_pocket_connection' && $permissiontoread) {
    $pocketApi = new PocketApi($db);
    $folders   = $pocketApi->getFolders();

    if ($folders === null) {
        setEventMessages($langs->trans('PocketConnectionFailed', $pocketApi->error), [], 'errors');
    } else {
        setEventMessage($langs->trans('PocketConnectionSucceeded', count($folders)));
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'sync_pocket_recordings' && $permissiontoread) {
    $pocketSync = new PocketSync($db);
    $report     = $pocketSync->syncRecordings($user);

    if (!empty($pocketSync->error)) {
        setEventMessages($pocketSync->error, [], 'errors');
    } else {
        setEventMessage($langs->trans('PocketSyncReport', $report['created'], $report['updated'], $report['skipped'], $report['errors']));
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

$apiKey      = getDolGlobalString('REEDCRM_POCKET_API_KEY');
$folderId    = getDolGlobalString('REEDCRM_POCKET_FOLDER_ID');
$folderLabel = getDolGlobalString('REEDCRM_POCKET_FOLDER_LABEL');

// Connection
print load_fiche_titre($langs->trans('PocketConnection'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_pocket_api_key">';
print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketApiKey'), $langs->trans('PocketApiKeyHelp')) . '</td>';
print '<td>';
// The stored key is never sent back to the browser, only the hint of the one in place.
print '<input type="password" class="minwidth300" name="pocket_api_key" autocomplete="new-password" placeholder="' . ($apiKey !== '' ? dol_escape_htmltag(dol_trunc($apiKey, 10, 'right', 'UTF-8', 1)) : 'pk_...') . '">';
print '</td>';
print '<td class="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Save') . '">';
if ($apiKey !== '') {
    print ' <a class="button butActionDelete" href="' . $_SERVER['PHP_SELF'] . '?action=del_pocket_api_key&token=' . newToken() . '">' . $langs->trans('Delete') . '</a>';
}
print '</td>';
print '</tr>';
print '</form>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('PocketConnectionState') . '</td>';
print '<td>';
print $apiKey !== '' ? '<span class="badge badge-status4 badge-status">' . $langs->trans('PocketApiKeySet') . '</span>' : '<span class="opacitymedium">' . $langs->trans('PocketApiKeyMissing') . '</span>';
print '</td>';
print '<td class="center">';
if ($apiKey !== '') {
    print '<a class="button" href="' . $_SERVER['PHP_SELF'] . '?action=test_pocket_connection&token=' . newToken() . '">' . $langs->trans('PocketTestConnection') . '</a>';
} else {
    print '<span class="butActionRefused classfortooltip">' . $langs->trans('PocketTestConnection') . '</span>';
}
print '</td>';
print '</tr>';

print '</table>';

// Imported folder
print load_fiche_titre($langs->trans('PocketFolderTitle'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_pocket_folder">';
print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketFolder'), $langs->trans('PocketFolderHelp')) . '</td>';
print '<td>';

if ($apiKey === '') {
    print '<span class="opacitymedium">' . $langs->trans('PocketApiKeyMissing') . '</span>';
} else {
    $pocketApi = new PocketApi($db);
    $folders   = $pocketApi->getFolders();

    if ($folders === null) {
        // The API is down or the key is wrong: keep the configured folder visible instead of
        // silently offering an empty selector that would clear it on save.
        print '<span class="error">' . dol_escape_htmltag($pocketApi->error) . '</span>';
        if ($folderId !== '') {
            print '<br><span class="opacitymedium">' . dol_escape_htmltag($folderLabel ?: $folderId) . '</span>';
        }
    } else {
        $folderOptions = ['' => $langs->trans('PocketNoFolderSelected')];
        foreach ($folders as $folder) {
            $folderOptions[$folder['id']] = str_repeat('&nbsp;&nbsp;', $folder['depth']) . $folder['label'];
        }
        print $form->selectarray('pocket_folder_id', $folderOptions, $folderId, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300');
    }
}

print '</td>';
print '<td class="center">';
print '<input type="submit" class="button"' . ($apiKey === '' ? ' disabled' : '') . ' value="' . $langs->trans('Save') . '">';
print '</td>';
print '</tr>';
print '</form>';

print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketManualSync'), $langs->trans('PocketManualSyncHelp')) . '</td>';
print '<td>';
print $folderId !== '' ? dol_escape_htmltag($folderLabel ?: $folderId) : '<span class="opacitymedium">' . $langs->trans('PocketFolderMissing') . '</span>';
print '</td>';
print '<td class="center">';
if ($apiKey !== '' && $folderId !== '') {
    print '<a class="button" href="' . $_SERVER['PHP_SELF'] . '?action=sync_pocket_recordings&token=' . newToken() . '">' . $langs->trans('PocketSyncNow') . '</a>';
} else {
    print '<span class="butActionRefused classfortooltip">' . $langs->trans('PocketSyncNow') . '</span>';
}
print '</td>';
print '</tr>';

print '</table>';

// Linkable elements, driven by the REEDCRM_POCKET_LINK_* constants
$linkableObjects            = reedcrm_pocket_get_linkable_objects();
$enabledObjectTypes         = reedcrm_pocket_get_enabled_linked_object_types();
$linkedObjectUsage          = reedcrm_pocket_get_linked_object_usage();
$linkedObjectExtraFieldName = REEDCRM_POCKET_LINK_ELEMENT_TYPE;

require_once __DIR__ . '/../../saturne/core/tpl/admin/object/linked_object_view.tpl.php';

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
