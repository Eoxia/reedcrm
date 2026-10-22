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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    view/pocketrecording/pocketrecording_user.php
 * \ingroup reedcrm
 * \brief   Pocket tab of the user card: the personal Pocket account of the user, key and folder.
 */

// Load ReedCRM environment
if (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} elseif (file_exists('../../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../../class/pocketaccount.class.php';
require_once __DIR__ . '/../../lib/reedcrm_pocketrecording.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['users']);

// Get parameters
$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new User($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
    accessforbidden($langs->trans('ErrorRecordNotFound'));
}

// Security check - Own account for whoever uses Pocket, the account of someone else for those
// allowed to edit the users, as on the display setup tab of the user card
$permissiontoadd = $user->hasRight('reedcrm', 'pocketrecording', 'write')
    && ($user->id == $object->id || $user->admin || $user->hasRight('user', 'user', 'write'));

saturne_check_access($permissiontoadd);

$form          = new Form($db);
$pocketAccount = PocketAccount::getPersonal($db, (int) $object->id);
$pocketFormUrl = $_SERVER['PHP_SELF'] . '?id=' . $object->id;

/*
 * Actions
 */

if (reedcrm_pocket_process_account_action($action, $pocketAccount, $user)) {
    header('Location: ' . $pocketFormUrl);
    exit;
}

/*
 * View
 */

$title    = $langs->trans('User') . ' - ' . $langs->trans('Pocket');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $help_url);

$head = user_prepare_head($object);
print dol_get_fiche_head($head, 'pocket', $langs->trans('User'), -1, 'user');

$canReadUsers = $user->hasRight('user', 'user', 'read') || $user->admin;
$linkBack     = $canReadUsers ? '<a href="' . DOL_URL_ROOT . '/user/list.php?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>' : '';
dol_banner_tab($object, 'id', $linkBack, $canReadUsers);

print '<div class="underbanner clearboth"></div>';

// Without a key of their own, the user works with the shared account: said here, otherwise the
// empty form reads as "Pocket is not available to this user"
if (!$pocketAccount->hasApiKey()) {
    $sharedAccount = PocketAccount::getShared($db);
    if ($sharedAccount->isConfigured()) {
        print info_admin($langs->trans('PocketUsesSharedAccount', dol_escape_htmltag($sharedAccount->folderLabel ?: $sharedAccount->folderId)), 0, 0, 'info', '');
    } else {
        print info_admin($langs->trans('PocketNoSharedAccount'), 0, 0, 'warning', '');
    }
}

require_once __DIR__ . '/../../core/tpl/reedcrm_pocket_account_setup.tpl.php';

print dol_get_fiche_end();

llxFooter();
$db->close();
