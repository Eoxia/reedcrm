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
 * \file    view/contact.php
 * \ingroup reedcrm
 * \brief   Page of contacts of invoice rec provide by socid
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['bills']);

// Get parameters
$id         = (GETPOSTISSET('facid') ? GETPOST('facid', 'int') : GETPOST('id', 'int'));
$socid      = GETPOST('socid', 'int');
$ref        = GETPOST('ref', 'alpha');
$action     = (GETPOSTISSET('action') ? GETPOST('action', 'aZ09') : 'view');
$cancel     = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object      = new FactureRec($db);
$thirdparty  = new Societe($db);
$extrafields = new ExtraFields($db);

// Initialize view objects
$form         = new Form($db);
$formProjects = new FormProjets($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(['invoicereccontact', 'globalcard']);

if (empty($action) && empty($id) && empty($ref)) {
    $action = 'view';
}

if ($socid > 0) {
    $moreWhere = ' AND t.fk_soc = ' . $socid;
    $object->fetchCommon(0, null, $moreWhere);
    $id = $object->id;
}

// Load object
require_once DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be included, not include_once

// Security check - Protection if external user
$permissionToRead = $user->rights->societe->contact->lire;
$permissionToAdd  = $user->rights->facture->creer;
saturne_check_access($permissionToRead);

/*
 * Actions
 */

$parameters = ['id'=> $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    // Cancel
    if ($cancel && !empty($backtopage)) {
        header('Location: ' . $backtopage);
        exit;
    }
    if ($action == 'classin' && $permissionToAdd) {
        // Set project
        $object->setProject(GETPOST('projectid', 'int'));
    }
    if ($action == 'setref' && $permissionToAdd) {
        $result = $object->setValueFrom('titre', $ref, '', null, 'text', '', $user, 'BILLREC_MODIFY');
        if ($result > 0) {
            $object->title = $ref;
            $object->ref   = $ref;
        } elseif ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
            $langs->load('errors');
            setEventMessages($langs->trans('ErrorRefAlreadyExists', $ref), [], 'errors');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    // Selection of new fields
    require_once DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';
}

/*
 * View
 */

$title   = $langs->trans('ContactsAddresses');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0,'', $title, $helpUrl);

$head = invoice_rec_prepare_head($object);
print dol_get_fiche_head($head, 'contact', $title, 0, 'bill');

$linkBack = '<a href="' . DOL_URL_ROOT . '/compta/facture/invoicetemplate_list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

$moreHtmlRef = '';
if ($action != 'editref') {
    $moreHtmlRef .= $form->editfieldkey($object->ref, 'ref', $object->ref, $object, $permissionToAdd, '', '', 0, 2);
} else {
    $moreHtmlRef .= $form->editfieldval('', 'ref', $object->ref, $object, $permissionToAdd);
}

$moreHtmlRef .= '<div class="refidno">';

// Thirdparty
$moreHtmlRef .= $langs->trans('ThirdParty').' : ' . $object->thirdparty->getNomUrl(1);

// Project
if (isModEnabled('project')) {
    $langs->load('projects');
    $moreHtmlRef .= '<br>' . $langs->trans('Project') . ' ';
    if ($permissionToAdd) {
        if ($action != 'classify') {
            $moreHtmlRef .= '<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&token=' . newToken() . '&id=' . $object->id . '">'.img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
        }
        if ($action == 'classify') {
            $moreHtmlRef .= '<form method="post" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
            $moreHtmlRef .= '<input type="hidden" name="action" value="classin">';
            $moreHtmlRef .= '<input type="hidden" name="token" value="' . newToken() . '">';
            $moreHtmlRef .= $formProjects->select_projects($object->socid, $object->fk_project, 'projectid', 0, 0, 1, 0, 1, 0, 0, '', 1);
            $moreHtmlRef .= '<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
            $moreHtmlRef .= '</form>';
        } else {
            $moreHtmlRef .= $form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1, '', 'maxwidth300');
        }
    } else {
        if (!empty($object->fk_project)) {
            $proj = new Project($db);
            $proj->fetch($object->fk_project);
            $moreHtmlRef .= ' : ' . $proj->getNomUrl(1);
            if ($proj->title) {
                $moreHtmlRef .= ' - ' . $proj->title;
            }
        } else {
            $moreHtmlRef .= '';
        }
    }
}
$moreHtmlRef .= '</div>';

dol_banner_tab($object, 'ref', $linkBack, 1, 'title', 'none', $moreHtmlRef);

print dol_get_fiche_end();

print '<br>';

$thirdparty->fetch($object->socid);
show_contacts($conf, $langs, $db, $thirdparty, $_SERVER["PHP_SELF"] . '?socid=' . $thirdparty->id, 1);

// End of page
llxFooter();
$db->close();
