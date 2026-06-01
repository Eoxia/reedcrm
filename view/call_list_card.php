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
 * \file    view/call_list_card.php
 * \ingroup reedcrm
 * \brief   Card view for CallList (CRUD + lines + PDF + ActionComm)
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

require_once __DIR__ . '/../class/calllist.class.php';
require_once __DIR__ . '/../class/calllistline.class.php';
require_once __DIR__ . '/../lib/reedcrm_call_list.lib.php';
require_once __DIR__ . '/../../saturne/lib/documents.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs();

$id         = GETPOSTINT('id');
$ref        = GETPOST('ref', 'alpha');
$action     = GETPOST('action', 'aZ09');
$show       = GETPOST('show', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$cancel     = GETPOST('cancel', 'aZ09');

$object     = new CallList($db);
$lineObject = new CallListLine($db);
$form       = new Form($db);

$hookmanager->initHooks(['call_list_card', 'reedcrmglobal', 'globalcard']);

$permissiontoread   = $user->hasRight('reedcrm', 'call_list', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'call_list', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'call_list', 'delete');

saturne_check_access($permissiontoread);

if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
}

/*
 * Actions
 */

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    if ($cancel) {
        if (!empty($backtopage)) {
            header('Location: ' . $backtopage);
            exit;
        }
        header('Location: ' . dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id);
        exit;
    }

    // Create
    if ($action === 'add' && $permissiontoadd) {
        $object->label          = GETPOST('label', 'alphanohtml');
        $object->note_public    = GETPOST('note_public', 'restricthtml');
        $object->note_private   = GETPOST('note_private', 'restricthtml');
        $object->fk_user_assign = GETPOSTINT('fk_user_assign');
        $object->date_start     = dol_mktime(0, 0, 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear'));
        $object->date_end       = dol_mktime(0, 0, 0, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), GETPOSTINT('date_endyear'));
        $object->status         = CallList::STATUS_DRAFT;

        $result = $object->create($user);

        if ($result > 0) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    // Edit — save update
    if ($action === 'update' && $permissiontoadd) {
        $object->label          = GETPOST('label', 'alphanohtml');
        $object->fk_user_assign = GETPOSTINT('fk_user_assign');
        $object->date_start     = dol_mktime(0, 0, 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear'));
        $object->date_end       = dol_mktime(0, 0, 0, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), GETPOSTINT('date_endyear'));

        $result = $object->update($user);

        if ($result > 0) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    // Update notes
    if ($action === 'update_notes' && $permissiontoadd) {
        $object->note_public  = GETPOST('note_public', 'restricthtml');
        $object->note_private = GETPOST('note_private', 'restricthtml');

        $result = $object->update($user);

        if ($result > 0) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&show=notes');
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    // Validate (draft → active)
    if ($action === 'confirm_validate' && GETPOST('confirm') === 'yes' && $permissiontoadd) {
        $object->status = CallList::STATUS_ACTIVE;
        $object->update($user);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }

    // Archive (active → archived)
    if ($action === 'confirm_archive' && GETPOST('confirm') === 'yes' && $permissiontoadd) {
        $object->status = CallList::STATUS_ARCHIVED;
        $object->update($user);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }

    // Delete
    if ($action === 'confirm_delete' && GETPOST('confirm') === 'yes' && $permissiontodelete) {
        $object->delete($user);
        header('Location: ' . dol_buildpath('/custom/reedcrm/view/call_list_list.php', 1));
        exit;
    }

    // Add line
    if ($action === 'add_line' && $permissiontoadd) {
        $lineObject->fk_call_list = $object->id;
        $lineObject->element_type = GETPOST('element_type', 'aZ09');
        $lineObject->element_id   = $lineObject->element_type === 'project' ? GETPOSTINT('project_id') : GETPOSTINT('propal_id');
        $lineObject->fk_contact   = GETPOSTINT('fk_contact');
        $lineObject->status       = CallListLine::STATUS_TO_CALL;
        $lineObject->note         = GETPOST('line_note', 'restricthtml');

        if (!empty($lineObject->element_type) && $lineObject->element_id > 0) {
            if ($lineObject->existsByElement($object->id, $lineObject->element_type, $lineObject->element_id)) {
                setEventMessages($langs->trans('CallListLineDuplicate'), null, 'warnings');
            } else {
                $lineObject->create($user);
            }
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }

    // Update line status
    if ($action === 'update_line_status' && $permissiontoadd) {
        $lineId     = GETPOSTINT('line_id');
        $lineStatus = GETPOSTINT('line_status');

        $lineToUpdate = new CallListLine($db);
        if ($lineToUpdate->fetch($lineId) > 0 && $lineToUpdate->fk_call_list == $object->id) {
            $lineToUpdate->status = $lineStatus;
            $lineToUpdate->update($user);
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }

    // Delete line
    if ($action === 'delete_line' && $permissiontodelete) {
        $lineId       = GETPOSTINT('line_id');
        $lineToDelete = new CallListLine($db);

        if ($lineToDelete->fetch($lineId) > 0 && $lineToDelete->fk_call_list == $object->id) {
            $lineToDelete->delete($user);
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }

    // Generate PDF
    if ($action === 'builddoc' && $permissiontoadd) {
        require_once __DIR__ . '/../core/modules/reedcrm/call_list/doc/pdf_calllist_standard.modules.php';

        $pdfModule = new pdf_calllist_standard($db);
        $result    = $pdfModule->write_file($object, $langs);

        if ($result > 0) {
            setEventMessages($langs->trans('FileGenerated'), null);
        } else {
            setEventMessages($pdfModule->error, null, 'errors');
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }

    // Remove PDF file
    if ($action === 'remove_file' && $permissiontodelete) {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

        $fileToRemove = GETPOST('file', 'alpha');
        $baseDir      = $conf->reedcrm->multidir_output[$conf->entity];

        if (!empty($fileToRemove)) {
            $fullPath = $baseDir . '/' . $fileToRemove;
            if (file_exists($fullPath)) {
                dol_delete_file($fullPath);
            }
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
        exit;
    }
}

/*
 * View
 */

$title   = $langs->trans('CallList');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

// Create form
if ($action === 'create') {
    print load_fiche_titre($langs->trans('NewCallList'), '', 'fontawesome_fa-phone_fas_#63ACC9');
    print dol_get_fiche_head();

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="add">';

    print '<table class="border centpercent tableforfieldcreate">';

    print '<tr><td class="fieldrequired titlefieldcreate">' . $langs->trans('Label') . '</td>';
    print '<td><input type="text" name="label" class="flat minwidth300" autofocus required></td></tr>';

    print '<tr><td>' . $langs->trans('AssignedTo') . '</td>';
    print '<td>' . $form->select_dolusers($user->id, 'fk_user_assign', 1) . '</td></tr>';

    print '<tr><td>' . $langs->trans('DateStart') . '</td>';
    print '<td>' . $form->selectDate('', 'date_start', 0, 0, 1, '', 1, 1) . '</td></tr>';

    print '<tr><td>' . $langs->trans('DateEnd') . '</td>';
    print '<td>' . $form->selectDate('', 'date_end', 0, 0, 1, '', 1, 1) . '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Create', 'Cancel');
    print '</form>';

    llxFooter();
    $db->close();
    exit;
}

// Edit form
if ($action === 'edit' && $object->id > 0) {
    $head = call_list_prepare_head($object);
    print dol_get_fiche_head($head, 'card', $title, -1, 'fontawesome_fa-phone_fas_#63ACC9');

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update">';

    print '<table class="border centpercent tableforfieldedit">';

    print '<tr><td class="fieldrequired titlefieldcreate">' . $langs->trans('Label') . '</td>';
    print '<td><input type="text" name="label" class="flat minwidth300" value="' . dol_escape_htmltag($object->label) . '" required></td></tr>';

    print '<tr><td>' . $langs->trans('AssignedTo') . '</td>';
    print '<td>' . $form->select_dolusers($object->fk_user_assign, 'fk_user_assign', 1) . '</td></tr>';

    print '<tr><td>' . $langs->trans('DateStart') . '</td>';
    print '<td>' . $form->selectDate($object->date_start, 'date_start', 0, 0, 1, '', 1, 1) . '</td></tr>';

    print '<tr><td>' . $langs->trans('DateEnd') . '</td>';
    print '<td>' . $form->selectDate($object->date_end, 'date_end', 0, 0, 1, '', 1, 1) . '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Save', 'Cancel');
    print '</form>';

    llxFooter();
    $db->close();
    exit;
}

// Notes tab
if ($show === 'notes' && $object->id > 0) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';

    $head = call_list_prepare_head($object);
    print dol_get_fiche_head($head, 'notes', $title, -1, 'fontawesome_fa-phone_fas_#63ACC9');

    $morehtml = '<a href="' . dol_buildpath('/custom/reedcrm/view/call_list_list.php', 1) . '">' . $langs->trans('BackToList') . '</a>';
    saturne_banner_tab($object, 'ref', $morehtml, 1, 'ref', 'ref', '', false);

    print '<div class="fichecenter">';

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_notes">';

    print '<table class="border centpercent tableforfieldcreate">';

    print '<tr class="field_note_public"><td class="titlefieldcreate">' . $langs->trans('NotePublic') . '</td><td>';
    $doleditor = new DolEditor('note_public', $object->note_public, '', 180, 'dolibarr_notes', 'In', false, true, 1, ROWS_6, '90%');
    $doleditor->Create();
    print '</td></tr>';

    if ($permissiontoadd) {
        print '<tr class="field_note_private"><td>' . $langs->trans('NotePrivate') . '</td><td>';
        $doleditor = new DolEditor('note_private', $object->note_private, '', 180, 'dolibarr_notes', 'In', false, true, 1, ROWS_6, '90%');
        $doleditor->Create();
        print '</td></tr>';
    }

    print '</table>';

    print '</div>';

    print dol_get_fiche_end();

    if ($permissiontoadd) {
        print '<div class="center"><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('Save')) . '"></div>';
    }

    print '</form>';

    llxFooter();
    $db->close();
    exit;
}

// View card
if ($object->id > 0) {
    $head = call_list_prepare_head($object);
    print dol_get_fiche_head($head, 'card', $title, -1, 'fontawesome_fa-phone_fas_#63ACC9');

    $morehtml = '<a href="' . dol_buildpath('/custom/reedcrm/view/call_list_list.php', 1) . '">' . $langs->trans('BackToList') . '</a>';
    saturne_banner_tab($object, 'ref', $morehtml, 1, 'ref', 'ref', '', false);

    // Confirm dialogs
    if ($action === 'delete') {
        print $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeleteCallList'), $langs->trans('ConfirmDeleteCallList'), 'confirm_delete', '', 0, 1);
    }

    if ($action === 'validate') {
        print $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ValidateCallList'), $langs->trans('ConfirmValidateCallList'), 'confirm_validate', '', 0, 1);
    }

    if ($action === 'archive') {
        print $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ArchiveCallList'), $langs->trans('ConfirmArchiveCallList'), 'confirm_archive', '', 0, 1);
    }

    print '<div class="fichecenter">';

    // Main fields table
    print '<div class="fichehalfleft">';
    print '<table class="border centpercent tableforfield">';

    print '<tr><td class="titlefield">' . $langs->trans('Label') . '</td>';
    print '<td>' . dol_escape_htmltag($object->label) . '</td></tr>';

    print '<tr><td>' . $langs->trans('AssignedTo') . '</td><td>';
    if (!empty($object->fk_user_assign)) {
        $userAssign = new User($db);
        $userAssign->fetch($object->fk_user_assign);
        print $userAssign->getNomUrl(1);
    }
    print '</td></tr>';

    print '<tr><td>' . $langs->trans('DateStart') . '</td>';
    print '<td>' . dol_print_date($object->date_start, 'day') . '</td></tr>';

    print '<tr><td>' . $langs->trans('DateEnd') . '</td>';
    print '<td>' . dol_print_date($object->date_end, 'day') . '</td></tr>';

    print '</table>';
    print '</div>';

    print '<div class="fichehalfright">';
    print '<div class="underbanner clearboth"></div>';
    print '</div>';

    print '</div>';

    print dol_get_fiche_end();

    // Action buttons
    print '<div class="tabsAction">';
    if ($permissiontoadd) {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=edit" class="butAction">' . $langs->trans('Modify') . '</a>';
    }
    if ($permissiontoadd && $object->status == CallList::STATUS_DRAFT) {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=validate" class="butAction">' . $langs->trans('Validate') . '</a>';
    }
    if ($permissiontoadd && $object->status == CallList::STATUS_ACTIVE) {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=archive" class="butAction">' . $langs->trans('Archive') . '</a>';
    }
    if ($permissiontodelete) {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete" class="butActionDelete">' . $langs->trans('Delete') . '</a>';
    }
    print '</div>';

    // =====================================================================
    // Call list lines
    // =====================================================================
    print load_fiche_titre($langs->trans('CallListLines'), '', 'fontawesome_fa-phone_fas_#63ACC9');

    $lines   = $lineObject->fetchAllByCallList($object->id);
    $colspan = $permissiontodelete ? 7 : 6;
    $ajaxUrl = $permissiontoadd ? dol_buildpath('/custom/reedcrm/ajax/get_element_primary_contact.php', 1) : '';

    // Build propal array for native selectarray() (key = rowid, value = label)
    $propalsArray = [];
    if ($permissiontoadd && isModEnabled('propale')) {
        $sql  = 'SELECT p.rowid, p.ref, s.nom AS socnom';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal AS p';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = p.fk_soc';
        $sql .= ' WHERE p.fk_statut >= 0 AND p.entity IN (' . getEntity('propal') . ')';
        $sql .= ' ORDER BY p.rowid DESC LIMIT 200';
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $propalsArray[$obj->rowid] = $obj->ref . ' — ' . $obj->socnom;
            }
            $db->free($resql);
        }
    }

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans('Lastname') . '</td>';
    print '<td>' . $langs->trans('Firstname') . '</td>';
    print '<td>' . $langs->trans('Phone') . '</td>';
    print '<td>' . $langs->trans('Source') . '</td>';
    print '<td>' . $langs->trans('Status') . '</td>';
    print '<td>' . $langs->trans('Note') . '</td>';
    if ($permissiontodelete) {
        print '<td class="center"></td>';
    }
    print '</tr>';

    $contact = new Contact($db);

    if (!empty($lines)) {
        foreach ($lines as $line) {
            $lastname   = '';
            $firstname  = '';
            $phone      = '';
            $sourceLink = '';

            if (!empty($line->fk_contact)) {
                $contact->fetch($line->fk_contact);
                $lastname  = dol_escape_htmltag($contact->lastname);
                $firstname = dol_escape_htmltag($contact->firstname);
                $phone     = dol_escape_htmltag($contact->phone_pro ?: $contact->phone_mobile ?: '');
            }

            if ($line->element_type === 'propal' && isModEnabled('propale')) {
                require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
                $propal = new Propal($db);
                if ($propal->fetch($line->element_id) > 0) {
                    $sourceLink = $propal->getNomUrl(1);
                }
            } elseif ($line->element_type === 'project' && isModEnabled('projet')) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                $project = new Project($db);
                if ($project->fetch($line->element_id) > 0) {
                    $sourceLink = $project->getNomUrl(1);
                }
            }

            print '<tr class="oddeven">';
            print '<td>' . $lastname . '</td>';
            print '<td>' . $firstname . '</td>';
            print '<td>' . ($phone ? '<a href="tel:' . dol_escape_htmltag($phone) . '">' . $phone . '</a>' : '') . '</td>';
            print '<td>' . $sourceLink . '</td>';
            print '<td class="nowraponall">';
            if ($permissiontoadd) {
                print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" style="margin:0">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="update_line_status">';
                print '<input type="hidden" name="line_id" value="' . $line->id . '">';
                $statusOptions = [
                    CallListLine::STATUS_TO_CALL   => $langs->trans('CallListLineStatus0'),
                    CallListLine::STATUS_CALLED    => $langs->trans('CallListLineStatus1'),
                    CallListLine::STATUS_NO_ANSWER => $langs->trans('CallListLineStatus2'),
                    CallListLine::STATUS_CALLBACK  => $langs->trans('CallListLineStatus3'),
                ];
                print '<select name="line_status" class="flat" onchange="this.form.submit()">';
                foreach ($statusOptions as $val => $lbl) {
                    print '<option value="' . $val . '"' . ($line->status == $val ? ' selected' : '') . '>' . dol_escape_htmltag($lbl) . '</option>';
                }
                print '</select>';
                print '</form>';
            } else {
                print $line->getLibStatut(5);
            }
            print '</td>';
            print '<td>' . dol_escape_htmltag($line->note) . '</td>';

            if ($permissiontodelete) {
                print '<td class="center">';
                print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete_line&line_id=' . $line->id . '&token=' . newToken() . '">';
                print img_delete();
                print '</a>';
                print '</td>';
            }
            print '</tr>';
        }
    } else {
        print '<tr><td colspan="' . $colspan . '"><div class="opacitymedium">' . $langs->trans('NoCallListLine') . '</div></td></tr>';
    }

    // Add line row — native Dolibarr selectors, inside the same table
    if ($permissiontoadd) {
        print '<tr class="liste_titre"><td colspan="' . $colspan . '">' . $langs->trans('AddCallListLine') . '</td></tr>';
        print '<tr><td colspan="' . $colspan . '">';
        print '<form id="add-call-line-form" method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="add_line">';
        print '<input type="hidden" name="fk_contact" id="fk_contact_hidden" value="">';
        print '<table class="nobordernopadding call-list-add-form">';

        // Label row
        print '<tr>';
        print '<td class="nowraponall opacitymedium">' . $langs->trans('SelectElementType') . '</td>';
        print '<td class="nowraponall opacitymedium">' . $langs->trans('SelectElement') . '</td>';
        print '<td></td>';
        print '<td></td>';
        print '</tr>';

        // Input row — all inputs on the same baseline
        print '<tr>';

        // Type select
        print '<td class="valignmiddle nowraponall">';
        print '<select name="element_type" id="call_line_element_type" class="flat">';
        if (isModEnabled('propale')) {
            print '<option value="propal">' . $langs->trans('ElementTypePropal') . '</option>';
        }
        if (isModEnabled('projet')) {
            print '<option value="project">' . $langs->trans('ElementTypeProject') . '</option>';
        }
        print '</select>';
        print '</td>';

        // Element select — two native Dolibarr selectors, shown/hidden by JS
        print '<td class="valignmiddle nowraponall">';

        if (isModEnabled('propale')) {
            $propalVisible = true;
            print '<div id="call-line-propal-wrap">';
            print $form->selectarray('propal_id', $propalsArray, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 flat');
            print '</div>';
        } else {
            $propalVisible = false;
        }

        if (isModEnabled('projet')) {
            require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
            $formprojet = new FormProjets($db);
            print '<div id="call-line-project-wrap"' . ($propalVisible ? ' class="hidden"' : '') . '>';
            $formprojet->select_projects(-1, '', 'project_id', 0, 0, 1, 0, 0, 0, 'minwidth200 flat');
            print ajax_combobox('project_id');
            print '</div>';
        }

        print '</td>';

        // Contact info
        print '<td class="valignmiddle" id="call_line_contact_info">';
        print '<span class="opacitymedium">' . $langs->trans('ContactToCall') . ' : —</span>';
        print '</td>';

        // Submit
        print '<td class="valignmiddle nowraponall"><input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('Add')) . '"></td>';

        print '</tr>';
        print '</table>';
        print '</form>';
        print '</td></tr>';
    }

    print '</table>';

    if ($permissiontoadd) {
        print '<div id="call-list-data" class="hidden"'
            . ' data-ajax-url="' . dol_escape_htmltag($ajaxUrl) . '"'
            . ' data-label-contact="' . dol_escape_htmltag($langs->trans('ContactToCall')) . '"'
            . ' data-label-no-contact="' . dol_escape_htmltag($langs->trans('NoContact')) . '"'
            . ' data-label-no-phone="' . dol_escape_htmltag($langs->trans('ContactNoPhone')) . '"'
            . '></div>';

        print '<script src="' . dol_buildpath('/custom/reedcrm/js/modules/call_list.js', 1) . '"></script>';
    }

    // =====================================================================
    // PDF documents (left) + ActionComm (right)
    // =====================================================================
    print '<br>';
    print '<div class="fichecenter">';

    print '<div class="fichehalfleft">';
    $dir       = $conf->reedcrm->multidir_output[$conf->entity] . '/call_list';
    $urlsource = $_SERVER['PHP_SELF'] . '?id=' . $object->id;
    print saturne_show_documents('reedcrm:CallList', 'call_list', $dir, $urlsource, $permissiontoadd, $permissiontodelete, getDolGlobalString('REEDCRM_CALL_LIST_GENERATE_DOCUMENTS_ADDON', 'pdf_calllist_standard'), 1, 0, 0, 0, '', '', '', '', '', $object, 0, 'remove_file');
    print '</div>';

    print '<div class="fichehalfright">';
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
    $formActions = new FormActions($db);
    $formActions->showactions($object, $object->element, 0, 1, '', 10);
    print '</div>';

    print '</div>';
}

llxFooter();
$db->close();
