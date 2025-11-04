<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    view/thirdparty_calls.php
 * \ingroup reedcrm
 * \brief   Page to show phone calls for a thirdparty
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array('agenda', 'companies', 'commercial', 'reedcrm@reedcrm'));

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'thirdpartycalls';
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Force actioncode to AC_TEL (phone calls only)
$actioncode = 'AC_TEL';

$search_rowid = GETPOST('search_rowid');
$search_agenda_label = GETPOST('search_agenda_label');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
    $sortfield = 'a.datep,a.id';
}
if (!$sortorder) {
    $sortorder = 'DESC,DESC';
}

// Initialize a technical object
$object = new Societe($db);

// Initialize a technical object to manage hooks of page
$hookmanager->initHooks(array('thirdpartycalls', 'globalcard'));

// Security check
$socid = GETPOSTINT('id');
if ($user->socid) {
    $socid = $user->socid;
}

$result = $object->fetch($socid);
if ($result <= 0) {
    accessforbidden('Third party not found');
}

$result = restrictedArea($user, 'societe', $socid, '&societe');

/*
 * Actions
 */

$parameters = array('id' => $socid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    // Cancel
    if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
        header("Location: ".$backtopage);
        exit;
    }

    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
        $search_agenda_label = '';
    }
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans("ThirdParty") . ' - ' . $langs->trans("KeyyoCalls");
if (getDolGlobalString('MAIN_HTML_TITLE') && preg_match('/thirdpartynameonly/', getDolGlobalString('MAIN_HTML_TITLE')) && $object->name) {
    $title = $object->name . " - " . $title;
}
llxHeader('', $title);

$head = societe_prepare_head($object);

print dol_get_fiche_head($head, 'keyyo', $langs->trans("ThirdParty"), -1, $object->picto);

$linkback = '<a href="' . DOL_URL_ROOT . '/societe/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

$morehtmlref = '';

dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom', $morehtmlref);

print '<div class="fichecenter">';

print '<div class="underbanner clearboth"></div>';

$object->info($socid);
dol_print_object_info($object, 1);

print '</div>';

print dol_get_fiche_end();

// Actions buttons
$objthirdparty = $object;
$objcon = new stdClass();

$out = '';
$permok = $user->hasRight('agenda', 'myactions', 'create');
if ((!empty($objthirdparty->id) || !empty($objcon->id)) && $permok) {
    if (is_object($objthirdparty) && get_class($objthirdparty) == 'Societe') {
        $out .= '&originid=' . $objthirdparty->id . ($objthirdparty->id > 0 ? '&socid=' . $objthirdparty->id : '') . '&backtopage=' . urlencode($_SERVER['PHP_SELF'] . ($objthirdparty->id > 0 ? '?socid=' . $objthirdparty->id : ''));
    }
    $out .= (!empty($objcon->id) ? '&contactid=' . $objcon->id : '');
    $out .= '&datep=' . dol_print_date(dol_now(), 'dayhourlog', 'tzuserrel');
}

$morehtmlright = '';

$messagingUrl = DOL_URL_ROOT . '/custom/reedcrm/view/thirdparty_calls.php?socid=' . $object->id;
$morehtmlright .= dolGetButtonTitle($langs->trans('ShowAsConversation'), '', 'fa fa-comments imgforviewmode', $messagingUrl, '', 1);
$messagingUrl = DOL_URL_ROOT . '/societe/agenda.php?socid=' . $object->id . '&actioncode=AC_TEL';
$morehtmlright .= dolGetButtonTitle($langs->trans('MessageListViewType'), '', 'fa fa-bars imgforviewmode', $messagingUrl, '', 2);

if (isModEnabled('agenda')) {
    if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
        $morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT . '/comm/action/card.php?action=create' . $out);
    }
}

if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
    print '<br>';

    $param = '&socid=' . urlencode((string)($socid));
    if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
        $param .= '&contextpage=' . urlencode($contextpage);
    }
    if ($limit > 0 && $limit != $conf->liste_limit) {
        $param .= '&limit=' . ((int)$limit);
    }

    // Try to know count of actioncomm from cache
    require_once DOL_DOCUMENT_ROOT . '/core/lib/memory.lib.php';
    $cachekey = 'count_events_thirdparty_' . $object->id . '_AC_TEL';
    $nbEvent = dol_getcache($cachekey);

    $titlelist = $langs->trans("KeyyoCalls") . (is_numeric($nbEvent) ? '<span class="opacitymedium colorblack paddingleft">(' . $nbEvent . ')</span>' : '');
    if (!empty($conf->dol_optimize_smallscreen)) {
        $titlelist = $langs->trans("Calls") . (is_numeric($nbEvent) ? '<span class="opacitymedium colorblack paddingleft">(' . $nbEvent . ')</span>' : '');
    }

    print_barre_liste($titlelist, 0, $_SERVER["PHP_SELF"], '', $sortfield, $sortorder, '', 0, -1, '', 0, $morehtmlright, '', 0, 1, 0);

    // List of all actions
    $filters = array();
    $filters['search_agenda_label'] = $search_agenda_label;
    $filters['search_rowid'] = $search_rowid;

    // Show actions with AC_TEL filter
    show_actions_messaging($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder);
}

// End of page
llxFooter();
$db->close();

