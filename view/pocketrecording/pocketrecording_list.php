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
 * \file    view/pocketrecording/pocketrecording_list.php
 * \ingroup reedcrm
 * \brief   List of the Pocket recordings, standalone or restricted to a linked object.
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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../../class/pocketrecording.class.php';
require_once __DIR__ . '/../../lib/reedcrm_pocketrecording.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$action     = GETPOST('action', 'aZ09') ?: 'view';
$massaction = GETPOST('massaction', 'alpha');
$confirm    = GETPOST('confirm', 'alpha');
$optioncss  = GETPOST('optioncss', 'aZ09');

// The tab is opened with the link name of the element (fromtype=commande), which is not always
// the key the metadata array is indexed with (order)
$fromType = GETPOST('fromtype', 'alpha');
$fromId   = GETPOSTINT('fromid');

// Search criteria
$search_all    = trim(GETPOST('search_all', 'alphanohtml'));
$search_status = GETPOST('search_status', 'intcomma');
$search_folder = GETPOST('search_folder', 'alphanohtml');
$search_date_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear'));
$search_date_end   = dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear'));

// Pagination
$limit     = GETPOSTINT('limit') ?: $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 't.recording_date';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;
}
$offset = $limit * $page;

// Initialize technical objects
$object       = new PocketRecording($db);
$thirdpartyStatic = new Societe($db);
$form         = new Form($db);
$contextpage  = 'pocketrecordinglist';
$hookmanager->initHooks([$contextpage]);

// Security check
$permissiontoread   = $user->hasRight('reedcrm', 'pocketrecording', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'pocketrecording', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'pocketrecording', 'delete');

saturne_check_access($permissiontoread);

// Purge search criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $search_all        = '';
    $search_status     = '';
    $search_folder     = '';
    $search_date_start = '';
    $search_date_end   = '';
}

/*
 * Actions
 */

// Attaching a recording is only offered from the tab of a business object: there the user has the
// context (this ticket, this project) that the recording card lacks
if ($action == 'link_recording' && $permissiontoadd && $fromId > 0 && !empty($fromType)) {
    $recordingToLink = new PocketRecording($db);

    if ($recordingToLink->fetch(GETPOSTINT('fk_pocketrecording')) > 0) {
        if (reedcrm_pocket_link_recording($recordingToLink, $fromType, $fromId) > 0) {
            setEventMessage($langs->trans('PocketRecordingLinked'));
        } else {
            setEventMessages($langs->trans('PocketRecordingLinkFailed'), [], 'errors');
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?fromtype=' . urlencode($fromType) . '&fromid=' . $fromId);
    exit;
}

if ($action == 'unlink_recording' && $permissiontoadd && $fromId > 0 && !empty($fromType)) {
    $recordingToUnlink = new PocketRecording($db);

    if ($recordingToUnlink->fetch(GETPOSTINT('id')) > 0) {
        if (reedcrm_pocket_unlink_recording($recordingToUnlink, $fromType, $fromId) > 0) {
            setEventMessage($langs->trans('PocketRecordingUnlinked'));
        } else {
            setEventMessages($langs->trans('PocketRecordingLinkFailed'), [], 'errors');
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?fromtype=' . urlencode($fromType) . '&fromid=' . $fromId);
    exit;
}

if ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontodelete) {
    $recordingToDelete = new PocketRecording($db);
    if ($recordingToDelete->fetch(GETPOSTINT('id')) > 0) {
        if ($recordingToDelete->delete($user) > 0) {
            setEventMessage($langs->trans('RecordDeleted'));
        } else {
            setEventMessages($recordingToDelete->error, $recordingToDelete->errors, 'errors');
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . ($fromId > 0 ? '?fromtype=' . urlencode($fromType) . '&fromid=' . $fromId : ''));
    exit;
}

/*
 * Data
 */

$sql  = 'SELECT t.rowid, t.ref, t.label, t.status, t.duration, t.language, t.recording_date,';
$sql .= ' t.pocket_folder_label, t.pocket_tags, t.pocket_state, t.fk_soc';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_pocket_recording as t';
$sql .= ' WHERE t.entity IN (' . getEntity($object->element) . ')';

// Restriction to the object the tab was opened from: a recording is linked either way round,
// so both directions of llx_element_element are tested.
if ($fromId > 0 && !empty($fromType)) {
    $sql .= " AND EXISTS (SELECT ee.rowid FROM " . MAIN_DB_PREFIX . "element_element as ee";
    $sql .= " WHERE (ee.sourcetype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND ee.fk_source = t.rowid AND ee.targettype = '" . $db->escape($fromType) . "' AND ee.fk_target = " . ((int) $fromId) . ')';
    $sql .= " OR (ee.targettype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND ee.fk_target = t.rowid AND ee.sourcetype = '" . $db->escape($fromType) . "' AND ee.fk_source = " . ((int) $fromId) . '))';
}

if ($search_all !== '') {
    $sql .= natural_search(['t.ref', 't.label', 't.pocket_tags', 't.transcript', 't.summary'], $search_all);
}
if ($search_status !== '' && $search_status != '-1') {
    $sql .= natural_search('t.status', $search_status, 2);
}
if ($search_folder !== '') {
    $sql .= natural_search('t.pocket_folder_label', $search_folder);
}
if (!empty($search_date_start)) {
    $sql .= " AND t.recording_date >= '" . $db->idate($search_date_start) . "'";
}
if (!empty($search_date_end)) {
    $sql .= " AND t.recording_date <= '" . $db->idate($search_date_end) . "'";
}

$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
    $resqlCount = $db->query($sql);
    if ($resqlCount) {
        $nbtotalofrecords = $db->num_rows($resqlCount);
        $db->free($resqlCount);
    }
    if (($page * $limit) > $nbtotalofrecords) {
        $page   = 0;
        $offset = 0;
    }
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}
$num = $db->num_rows($resql);

/*
 * View
 */

$title    = $langs->trans('PocketRecordings');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $help_url);

// Reached through a tab: show the tab strip and the banner of the object the recordings belong to,
// so the user can navigate back to its other tabs instead of landing on an orphan list
if ($fromId > 0 && !empty($fromType)) {
    $fromMetadata = reedcrm_pocket_get_object_metadata_from_link_name($fromType);
    if (!empty($fromMetadata['object']) && $fromMetadata['object']->fetch($fromId) > 0) {
        $fromObject = $fromMetadata['object'];

        // saturne_get_fiche_head() calls <element>_prepare_head(), which lives in the lib of the
        // host object and is not loaded by the metadata
        if (!empty($fromMetadata['lib_path'])) {
            dol_include_once('/' . $fromMetadata['lib_path']);
        }

        $headElement = $fromObject->element == 'contrat' ? 'contract' : ($fromObject->element == 'project_task' ? 'task' : $fromObject->element);
        if (function_exists($headElement . '_prepare_head') || function_exists($headElement . 'PrepareHead')) {
            saturne_get_fiche_head($fromObject, 'pocketrecording', $langs->trans($fromMetadata['langs'] ?? ucfirst($fromType)));
        }

        // The host object is reached through its own path: fromtype is a link name, not a directory
        $backUrl  = $fromMetadata['list_url'] ?: $fromType . '/list.php';
        $linkBack = '<a href="' . dol_buildpath($backUrl . '?restore_lastsearch_values=1', 1) . '">' . $langs->trans('BackToList') . '</a>';
        saturne_banner_tab($fromObject, 'fromtype=' . $fromType . '&fromid', $linkBack, 1, 'rowid', 'ref');
    }
}

$moreUrlParameters = ($fromId > 0 ? '&fromtype=' . urlencode($fromType) . '&fromid=' . $fromId : '');

// Attach form, only on the tab of a business object. It lives outside the list form on purpose:
// nesting forms is invalid HTML and the browser drops the inner one.
if ($fromId > 0 && !empty($fromType) && $permissiontoadd) {
    $linkableRecordings = reedcrm_pocket_get_linkable_recordings($fromType, $fromId);

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="link_recording">';
    print '<input type="hidden" name="fromtype" value="' . dol_escape_htmltag($fromType) . '">';
    print '<input type="hidden" name="fromid" value="' . $fromId . '">';
    print '<div class="reedcrm-pocket-link-form">';
    if (empty($linkableRecordings)) {
        print '<span class="opacitymedium">' . $langs->trans('PocketNoRecordingToLink') . '</span>';
    } else {
        print '<span>' . $langs->trans('PocketLinkRecording') . '</span>';
        print $form->selectarray('fk_pocketrecording', $linkableRecordings, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1);
        print '<input type="submit" class="button smallpaddingimp" value="' . $langs->trans('Add') . '">';
    }
    print '</div>';
    print '</form>';
}

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="fromtype" value="' . dol_escape_htmltag($fromType) . '">';
print '<input type="hidden" name="fromid" value="' . $fromId . '">';

$newCardButton = '';
if ($user->hasRight('reedcrm', 'adminpage', 'read')) {
    $newCardButton = dolGetButtonTitle($langs->trans('PocketSyncNow'), '', 'fa fa-sync', dol_buildpath('/custom/reedcrm/admin/pocket.php', 1) . '?action=sync_pocket_recordings&token=' . newToken());
}

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $moreUrlParameters, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'fa-microphone', 0, $newCardButton, '', $limit, 0, 0, 1);

if ($search_all !== '') {
    print '<div class="divsearchfieldfilter">' . $langs->trans('Search') . ': ' . dol_escape_htmltag($search_all) . '</div>';
}

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">';

// Search line
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_all" value="' . dol_escape_htmltag($search_all) . '"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">';
print $form->selectDate($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0);
print $form->selectDate($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0);
print '</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_folder" value="' . dol_escape_htmltag($search_folder) . '"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre center">';
print $form->selectarray('search_status', $object->fields['status']['arrayofkeyval'], $search_status, 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100', 1);
print '</td>';
print '<td class="liste_titre center maxwidthsearch">';
print $form->showFilterButtons();
print '</td>';
print '</tr>';

// Title line
print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 't.ref', '', $moreUrlParameters, '', $sortfield, $sortorder);
print_liste_field_titre('Label', $_SERVER['PHP_SELF'], 't.label', '', $moreUrlParameters, '', $sortfield, $sortorder);
print_liste_field_titre('PocketRecordingDate', $_SERVER['PHP_SELF'], 't.recording_date', '', $moreUrlParameters, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('Duration', $_SERVER['PHP_SELF'], 't.duration', '', $moreUrlParameters, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('PocketFolder', $_SERVER['PHP_SELF'], 't.pocket_folder_label', '', $moreUrlParameters, '', $sortfield, $sortorder);
print_liste_field_titre('PocketTags', $_SERVER['PHP_SELF'], 't.pocket_tags', '', $moreUrlParameters, '', $sortfield, $sortorder);
print_liste_field_titre('ThirdParty', $_SERVER['PHP_SELF'], 't.fk_soc', '', $moreUrlParameters, '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 't.status', '', $moreUrlParameters, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', '', '', '', '', 'center maxwidthsearch ');
print '</tr>';

$i = 0;
while ($i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);
    if (!$obj) {
        break;
    }

    $object->id             = $obj->rowid;
    $object->ref            = $obj->ref;
    $object->label          = $obj->label;
    $object->status         = $obj->status;
    $object->recording_date = $db->jdate($obj->recording_date);

    print '<tr class="oddeven">';
    print '<td class="nowraponall">' . $object->getNomUrl(1) . '</td>';
    print '<td class="tdoverflowmax300" title="' . dol_escape_htmltag($obj->label) . '">' . dol_escape_htmltag($obj->label) . '</td>';
    print '<td class="center nowraponall">' . dol_print_date($db->jdate($obj->recording_date), 'dayhour') . '</td>';
    print '<td class="right">' . reedcrm_pocket_format_duration((int) $obj->duration) . '</td>';
    print '<td class="tdoverflowmax150">' . dol_escape_htmltag($obj->pocket_folder_label) . '</td>';
    print '<td class="tdoverflowmax150">' . dol_escape_htmltag($obj->pocket_tags) . '</td>';
    print '<td class="tdoverflowmax150">';
    if ($obj->fk_soc > 0 && $thirdpartyStatic->fetch($obj->fk_soc) > 0) {
        print $thirdpartyStatic->getNomUrl(1);
    }
    print '</td>';
    print '<td class="center">' . $object->getLibStatut(5) . '</td>';
    print '<td class="center">';
    if ($fromId > 0 && !empty($fromType) && $permissiontoadd) {
        $unlinkUrl = $_SERVER['PHP_SELF'] . '?action=unlink_recording&id=' . $object->id . '&fromtype=' . urlencode($fromType) . '&fromid=' . $fromId . '&token=' . newToken();
        print '<a href="' . $unlinkUrl . '" title="' . dol_escape_htmltag($langs->trans('PocketUnlinkRecording')) . '">' . img_picto($langs->trans('PocketUnlinkRecording'), 'unlink') . '</a>';
    }
    print '</td>';
    print '</tr>';

    $i++;
}

if ($num == 0) {
    print '<tr><td colspan="9" class="opacitymedium center">' . $langs->trans('NoRecordFound') . '</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

$db->free($resql);

llxFooter();
$db->close();
