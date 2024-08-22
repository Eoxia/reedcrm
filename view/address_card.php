<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 *  \file       view/address.php
 *  \ingroup    easycrm
 *  \brief      Tab of address on generic element
 */

// Load EasyCRM environment
if (file_exists('../easycrm.main.inc.php')) {
	require_once __DIR__ . '/../easycrm.main.inc.php';
} elseif (file_exists('../../easycrm.main.inc.php')) {
	require_once __DIR__ . '/../../easycrm.main.inc.php';
} else {
	die('Include of easycrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

// Load Saturne librairies
require_once __DIR__ . '/../../saturne/lib/object.lib.php';

// Load EasyCRM librairies
require_once __DIR__ . '/../class/geolocation.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get create parameters
$contactName    = GETPOST('name');
$contactAddress = GETPOST('address_detail');

// Get parameters
$fromId      = GETPOST('from_id', 'int');
$contactID   = GETPOST('contact_id', 'int');
$objectType  = GETPOSTISSET('from_type') ? GETPOST('from_type', 'alpha') : GETPOST('object_type', 'alpha');
$ref         = GETPOST('ref', 'alpha');
$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : $objectType . 'address'; // To manage different context of search
$cancel      = GETPOST('cancel', 'aZ09');
$backtopage  = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$objectInfos  = saturne_get_objects_metadata($objectType);
$className    = $objectInfos['class_name'];
$objectLinked = new $className($db);
$contact      = new Contact($db);
$geolocation  = new Geolocation($db);
$project      = new Project($db);
$category     = new Categorie($db);

// Initialize view objects
$form        = new Form($db);
$formcompany = new FormCompany($db);

$hookmanager->initHooks([$objectType . 'address', $objectType . 'address', 'easycrmglobal', 'globalcard']); // Note that conf->hooks_modules contains array

$project->fetch($fromId ?? 0, $fromId > 0 ? '' : $ref);
$fromId = ($fromId > 0 ? $fromId : $project->id);

// Security check - Protection if external user
$permissiontoread   = $user->rights->easycrm->address->read;
$permissiontoadd    = $user->rights->easycrm->address->write;
$permissiontodelete = $user->rights->easycrm->address->delete;
saturne_check_access($permissiontoread);

/*
*  Actions
*/

$parameters = ['id' => $fromId];
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $objectLinked, $action); // Note that $action and $objectLinked may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Cancel
	if ($cancel && !empty($backtopage)) {
		header('Location: ' . $backtopage);
		exit;
	}

	// Action to add address
	if ($action == 'add_address' && $permissiontoadd && !$cancel) {
		if (empty($contactName) || empty($contactAddress)) {
			setEventMessages($langs->trans('EmptyValue'), [], 'errors');
			header('Location: ' . $_SERVER['PHP_SELF'] .  '?from_id=' . $fromId . '&action=create&from_type=' . $objectType . '&name=' . $contactName . '&address_detail=' . $contactAddress);
			exit;
		} else {
            $contact->lastname   = $contactName;
            $contact->address    = $contactAddress;
            $contact->fk_project = $fromId;

            $contactID = $contact->create($user);
            $_POST['contactid'] = $contactID;

            if ($contactID > 0) {
                $category->fetch(getDolGlobalInt('EASYCRM_ADDRESS_MAIN_CATEGORY'));
                $category->add_type($contact);
                $project->add_contact($contactID, 'PROJECTADDRESS');
                setEventMessages($langs->trans('ObjectCreated', $langs->trans('Address')), []);
			} else {
				setEventMessages($langs->trans('ErrorCreateAddress'), [], 'errors');
			}
            header('Location: ' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType);
            exit;
		}
	}

    if ($action == 'edit_address' && $permissiontoadd) {
        if ($contactID > 0) {
            $contact->fetch($contactID);
            $contact->lastname = $contactName;
            $contact->address  = $contactAddress;
            $contact->update($contactID, $user);

            $geolocation->fetch('', '', ' AND fk_element = ' . $contactID);
            $addressesList = $geolocation->getDataFromOSM($contact);
            if (!empty($addressesList)) {
                $address = $addressesList[0];

                $geolocation->latitude  = $address->lat;
                $geolocation->longitude = $address->lon;
                $geolocation->status    = Geolocation::STATUS_GEOLOCATED;
                if (empty($geolocation->id)) {
                    $geolocation->element_type = 'contact';
                    $geolocation->gis          = 'osm';
                    $geolocation->fk_element   = $contactID;
                    $geolocation->create($user);
                }
                setEventMessages($langs->trans('ObjectModified', $langs->trans('Address')), []);
            } else {
                $geolocation->status = Geolocation::STATUS_NOTFOUND;
                setEventMessages($langs->trans('ErrorUpdateAddress'), [], 'errors');
            }
            if ($geolocation->id > 0) {
                $geolocation->update($user);
                $contact->array_options['options_address_status'] = $geolocation->status;
                $contact->updateExtraField('address_status');
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType);
        exit;
    }

	// Action to delete address
	if ($action == 'delete_address' && $permissiontodelete) {
		if ($contactID > 0) {
			$contact->fetch($contactID);
			$result = $contact->delete($user);

			if ($result > 0) {
                $objectLinked->fetch($fromId);
                if ($objectLinked->array_options['options_' . $objectType . 'address'] == $contactID) {
                    $objectLinked->array_options['options_' . $objectType . 'address'] = 0;
                    $objectLinked->updateExtrafield($objectType . 'address');
                }
                $geolocation->fetch('', '', ' AND fk_element = ' . $contactID);
                $geolocation->delete($user, false, false);

                setEventMessages($langs->trans('ObjectDeleted', $langs->trans('Address')), []);
			} else {
				setEventMessages($langs->trans('ErrorDeleteAddress'), [], 'errors');
			}
			header('Location: ' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType);
            exit;
		}
	}

    if ($action == 'toggle_favorite') {
        $objectLinked->fetch($fromId);
        if (!empty($objectLinked) && $contactID > 0) {
            $objectLinked->array_options['options_' . $objectType . 'address'] = $objectLinked->array_options['options_' . $objectType . 'address'] == $contactID ? 0 : $contactID;
            $objectLinked->updateExtrafield($objectType . 'address');
        }
    }
}

/*
*	View
*/

$title   = $langs->trans('Address') . ' - ' . $langs->trans(ucfirst($objectType));
$helpUrl = 'FR:Module_EasyCRM';

saturne_header(0,'', $title, $helpUrl);

if ($action == 'create' && $fromId > 0) {
    $objectLinked->fetch($fromId);

    saturne_get_fiche_head($objectLinked, 'address', $title);

    print load_fiche_titre($langs->trans('NewAddress'), $backtopage, $contact->picto);

    print dol_get_fiche_head();

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType . '">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add_address">';
	if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    }

	print '<table class="border centpercent tableforfieldcreate address-table">';

	// Name -- Nom
	print '<tr><td class="fieldrequired">' . $langs->trans('Name') . '</td><td>';
	print '<input class="flat minwidth300 maxwidth300" type="text" size="36" name="name" id="name" value="' . $contactName . '">';
	print '</td></tr>';

    // Address -- Adresse
    print '<tr><td class="fieldrequired">' . $langs->trans('Address') . '</td><td>';
    $doleditor = new DolEditor('address_detail', GETPOST('description'), '', 90, 'dolibarr_details', '', false, true, 0, ROWS_3, '50%');
    $doleditor->Create();
    print '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Create', 'Cancel', [], false, 'wpeo-button');
} else if ($action == 'edit' && $fromId > 0 && $contactID > 0) {
    $objectLinked->fetch($fromId);
    $contact->fetch($contactID);

    saturne_get_fiche_head($objectLinked, 'address', $title);

    print load_fiche_titre($langs->trans('EditAddress'), $backtopage, $contact->picto);

    print dol_get_fiche_head();

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType . '">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="edit_address">';
    print '<input type="hidden" name="contact_id" value="' . $contactID . '">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    }

    print '<table class="border centpercent tableforfieldedit address-table">';

    // Name -- Nom
    print '<tr><td class="fieldrequired">' . $langs->trans('Name') . '</td><td>';
    print '<input class="flat minwidth300 maxwidth300" type="text" size="36" name="name" id="name" value="' . $contact->lastname . '">';
    print '</td></tr>';

    // Address -- Adresse
    print '<tr><td class="fieldrequired">' . $langs->trans('Address') . '</td><td>';
    $doleditor = new DolEditor('address_detail', $contact->address, '', 90, 'dolibarr_details', '', false, true, 0, ROWS_3, '50%');
    $doleditor->Create();
    print '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Edit', 'Cancel', [], false, 'wpeo-button');
} else if ($fromId > 0 || !empty($ref) && empty($action)) {
    $objectLinked->fetch($fromId);

    saturne_get_fiche_head($objectLinked, 'address', $title);

    $morehtml = '<a href="' . dol_buildpath('/' . $objectLinked->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $objectLinked->element . '">' . $langs->trans('BackToList') . '</a>';
    saturne_banner_tab($objectLinked, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($objectLinked->photo));

    $objectLinked->fetch_optionals();

    print '<div class="fichecenter">';

    print '<div class="addresses-container">';

    $parameters = ['contact' => $contact];
    $reshook    = $hookmanager->executeHooks('easycrmAddressType', $parameters, $objectLinked); // Note that $action and $objectLinked may have been modified by some hooks
    if ($reshook > 0) {
        $addresses = $hookmanager->resArray;
    } else {
        $contacts = $project->liste_contact();
        if (is_array($contacts) && !empty($contacts)) {
            foreach($contacts as $contactSingle) {
                if ($contactSingle['code'] == 'PROJECTADDRESS') {
                    $addresses[] = $contactSingle;
                }
            }
        }
	}

    print load_fiche_titre($langs->trans('AddressesList'), '', $contact->picto);

    require __DIR__ . '/../core/tpl/easycrm_address_table_view.tpl.php';

	print '</div>';

	print dol_get_fiche_end();
}

// End of page
llxFooter();
$db->close();
