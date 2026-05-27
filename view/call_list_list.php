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
 * \file    view/call_list_list.php
 * \ingroup reedcrm
 * \brief   List of call lists
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once __DIR__ . '/../class/calllist.class.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs();

$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'call_list_list';
$backtopage  = GETPOST('backtopage', 'alpha');
$sortfield   = GETPOST('sortfield', 'aZ09comma');
$sortorder   = GETPOST('sortorder', 'aZ09comma');
$page        = GETPOSTINT('page');
$limit       = $conf->liste_limit;
$offset      = $page * $limit;

if (empty($sortfield)) {
    $sortfield = 't.rowid';
}
if (empty($sortorder)) {
    $sortorder = 'DESC';
}

$object = new CallList($db);

$permissiontoread   = $user->hasRight('reedcrm', 'call_list', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'call_list', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'call_list', 'delete');

saturne_check_access($permissiontoread);

$hookmanager->initHooks(['call_list_list', 'reedcrmglobal', 'globallist']);

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if ($action === 'delete' && $permissiontodelete) {
    $objectToDelete = new CallList($db);
    if ($objectToDelete->fetch(GETPOSTINT('id')) > 0) {
        $objectToDelete->delete($user);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title   = $langs->trans('CallLists');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

$newCardButton = '';
if ($permissiontoadd) {
    $newCardButton = '<a href="' . dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?action=create" class="butAction">';
    $newCardButton .= $langs->trans('NewCallList');
    $newCardButton .= '</a>';
}

print load_fiche_titre($title, $newCardButton, 'fontawesome_fa-phone_fas_#63ACC9');

// Fetch list
$sql  = 'SELECT t.rowid, t.ref, t.label, t.status, t.date_start, t.date_end, t.fk_user_assign';
$sql .= ' FROM ' . $db->prefix() . 'reedcrm_call_list AS t';
$sql .= ' WHERE t.entity IN (' . getEntity($object->element) . ')';
$sql .= ' AND t.status >= 0';
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit, $offset);

$resql = $db->query($sql);

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Ref') . '</td>';
print '<td>' . $langs->trans('Label') . '</td>';
print '<td>' . $langs->trans('DateStart') . '</td>';
print '<td>' . $langs->trans('DateEnd') . '</td>';
print '<td>' . $langs->trans('AssignedTo') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
if ($permissiontodelete) {
    print '<td></td>';
}
print '</tr>';

if ($resql && $db->num_rows($resql) > 0) {
    $userStatic = new User($db);

    while ($obj = $db->fetch_object($resql)) {
        $callList = new CallList($db);
        $callList->fetch($obj->rowid);

        print '<tr class="oddeven">';
        print '<td>' . $callList->getNomUrl(1) . '</td>';
        print '<td>' . dol_escape_htmltag($callList->label) . '</td>';
        print '<td>' . dol_print_date($callList->date_start, 'day') . '</td>';
        print '<td>' . dol_print_date($callList->date_end, 'day') . '</td>';

        print '<td>';
        if (!empty($callList->fk_user_assign)) {
            $userStatic->fetch($callList->fk_user_assign);
            print $userStatic->getNomUrl(1);
        }
        print '</td>';

        print '<td class="center">' . $callList->getLibStatut(5) . '</td>';

        if ($permissiontodelete) {
            print '<td class="center">';
            print '<a href="' . $_SERVER['PHP_SELF'] . '?action=delete&id=' . $callList->id . '&token=' . newToken() . '">';
            print img_delete();
            print '</a>';
            print '</td>';
        }

        print '</tr>';
    }
} else {
    print '<tr><td colspan="' . ($permissiontodelete ? 7 : 6) . '"><div class="opacitymedium">' . $langs->trans('None') . '</div></td></tr>';
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
