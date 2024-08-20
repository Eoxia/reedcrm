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

// Load Saturne librairies
require_once __DIR__ . '/../../saturne/lib/object.lib.php';

// Load EasyCRM librairies
require_once __DIR__ . '/../class/address.class.php';
require_once __DIR__ . '/../class/geolocation.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get create parameters
$addressName    = GETPOST('name');
$addressType    = GETPOST('address_type');
$addressCountry = GETPOST('fk_country', 'int');
$addressRegion  = GETPOST('fk_region', 'int');
$addressState   = GETPOST('fk_state', 'int');
$addressTown    = GETPOST('town');
$addressZip     = GETPOST('zip');
$addressAddress = GETPOST('address');

// Get parameters
$fromId      = GETPOST('from_id', 'int');
$objectType  = GETPOST('from_type', 'alpha');
$ref         = GETPOST('ref', 'alpha');
$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : $objectType . 'address'; // To manage different context of search
$cancel      = GETPOST('cancel', 'aZ09');
$backtopage  = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$objectInfos  = saturne_get_objects_metadata($objectType);
$className    = $objectInfos['class_name'];
$objectLinked = new $className($db);
$object       = new Address($db);
$geolocation  = new Geolocation($db);

// Initialize view objects
$form        = new Form($db);
$formcompany = new FormCompany($db);

$hookmanager->initHooks([$objectType . 'address', $objectType . 'address', 'easycrmglobal', 'globalcard']); // Note that conf->hooks_modules contains array

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
		if (empty($addressName) || empty($addressType) || empty($addressCountry) || empty($addressTown)) {
			setEventMessages($langs->trans('EmptyValue'), [], 'errors');
			header('Location: ' . $_SERVER['PHP_SELF'] .  '?from_id=' . $fromId . '&action=create&from_type=' . $objectType . '&name=' . $addressName . '&address_type=' . $addressType . '&fk_country=' . $addressCountry . '&fk_region=' . $addressRegion . '&fk_state=' . $addressState . '&address_type=' . $addressType . '&town=' . $addressTown . '&zip=' . $addressZip . '&address=' . $addressAddress);
			exit;
		} else {
            $object->ref           = $object->getNextNumRef();
			$object->name          = $addressName;
			$object->type          = $addressType;
			$object->fk_country    = $addressCountry;
			$object->fk_region     = $addressRegion;
			$object->fk_department = $addressState;
			$object->town          = $addressTown;
			$object->zip           = $addressZip;
			$object->address       = $addressAddress;
			$object->element_type  = $objectType;
			$object->element_id    = $fromId;

			$result = $object->create($user);

			if ($result > 0) {
				if ($object->status == $object::STATUS_NOT_FOUND) {
					setEventMessages($langs->trans('CouldntFindDataOnOSM'), [], 'errors');
				} else if ($object->status == $object::STATUS_ACTIVE) {
                    $geolocation->latitude     = $object->latitude;
                    $geolocation->longitude    = $object->longitude;
                    $geolocation->element_type = $object->element_type;
                    $geolocation->fk_element   = $fromId;
                    $geolocation->fk_address   = $result;
                    $geolocation->create($user);
					setEventMessages($langs->trans('DataSuccessfullyRetrieved'), []);
				}
				setEventMessages($langs->trans('AddressCreated'), []);
			} else {
				setEventMessages($langs->trans('ErrorCreateAddress'), [], 'errors');
			}
            header('Location: ' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType);
		}
	}

	// Action to delete address
	if ($action == 'delete_address' && $permissiontodelete) {
		$addressID = GETPOST('addressID');

		if ($addressID > 0) {
			$object->fetch($addressID);

			$result = $object->delete($user);

			if ($result > 0) {
                $objectLinked->fetch($fromId);
                if ($objectLinked->array_options['options_projectaddress'] == $addressID) {
                    $objectLinked->array_options['options_projectaddress'] = 0;
                    $objectLinked->updateExtrafield('projectaddress');
                }

                $geolocations = $geolocation->fetch('', '', ' AND fk_address = ' . $addressID);
                $geolocation->delete($user, false, false);

                setEventMessages($langs->trans('AddressDeleted'), []);
			} else {
				setEventMessages($langs->trans('ErrorDeleteAddress'), [], 'errors');
			}
			header('Location: ' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType);
		}
	}

    if ($action == 'toggle_favorite') {
        $favoriteAddressId = GETPOST('favorite_id');

        $objectLinked->fetch($fromId);
        if (!empty($objectLinked) && $favoriteAddressId > 0) {
            $objectLinked->array_options['options_' . $objectType . 'address'] = $objectLinked->array_options['options_' . $objectType . 'address'] == $favoriteAddressId ? 0 : $favoriteAddressId;
            $objectLinked->update($user);
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

    print load_fiche_titre($langs->trans("NewAddress"), $backtopage, $object->picto);

    print dol_get_fiche_head();

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&from_type=' . $objectType . '">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add_address">';
	if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    }

	print '<table class="border centpercent tableforfieldcreate address-table">'."\n";

	// Name -- Nom
	print '<tr><td class="fieldrequired">'.$langs->trans("Name").'</td><td>';
	print '<input class="flat minwidth300 maxwidth300" type="text" size="36" name="name" id="name" value="'.$addressName.'">';
	print '</td></tr>';

	// Type -- Type
	print '<tr><td class="fieldrequired"><label class="" for="type">' . $langs->trans("Type") . '</label></td><td>';
	print saturne_select_dictionary('address_type','c_address_type', 'ref', 'label', $addressType, 0, '', '', 'minwidth300 maxwidth300');
	print '</td></tr>';

	// Country -- Pays
	print '<tr><td class="fieldrequired"><label class="" for="type">' . $langs->trans("Country") . '</label></td><td>';
	print $formcompany->select_country($addressCountry, 'fk_country', '', 0, 'minwidth300 maxwidth300') . ' ' . img_picto('', 'country', 'class="pictofixedwidth"');
	print '</td></tr>';

	// Region -- Region
	print '<tr><td class=""><label class="" for="type">' . $langs->trans("Region") . '</label></td><td>';
	print $formcompany->select_region($addressRegion, 'fk_region') . ' ' . img_picto('', 'state', 'class="pictofixedwidth"');;
	print '</td></tr>';

	// State - Departements
	print '<tr><td class=""><label class="" for="type">' . $langs->trans("State") . '</label></td><td>';
	print $formcompany->select_state($addressState, '', 'fk_state', 'minwidth300 maxwidth300') . ' ' . img_picto('', 'state', 'class="pictofixedwidth"');
	print '</td></tr>';

    // Common attributes
    include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	print '</table></br>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Create', 'Cancel', [], false, 'wpeo-button');
} else if ($fromId > 0 || !empty($ref) && empty($action)) {
    $objectLinked->fetch($fromId);

    saturne_get_fiche_head($objectLinked, 'address', $title);

    $morehtml = '<a href="' . dol_buildpath('/' . $objectLinked->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $objectLinked->element . '">' . $langs->trans('BackToList') . '</a>';
	saturne_banner_tab($objectLinked, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($objectLinked->photo));

	$objectLinked->fetch_optionals();

	print '<div class="fichecenter">';

	print '<div class="addresses-container">';

	$parameters = ['address' => $object];
	$reshook    = $hookmanager->executeHooks('easycrmAddressType', $parameters, $objectLinked); // Note that $action and $objectLinked may have been modified by some hooks
	if ($reshook > 0) {
		$addressByType = $hookmanager->resArray;
	} else {
		$addressByType['Address'] = $object->fetchAddresses($objectLinked->id, $objectType);
	}

    print load_fiche_titre($langs->trans('AddressesList'), '', $object->picto);

    $alreadyAddedAddress = [];
	if (is_array($addressByType) && !empty($addressByType)) {
		foreach ($addressByType as $addressType => $addresses) {
			require __DIR__ . '/../core/tpl/easycrm_address_table_view.tpl.php';
		}
	} else {
		print '<div class="opacitymedium">' . $langs->trans('NoAddresses') . '</div>';
	}

	print '</div>';

	print dol_get_fiche_end();
}

// End of page
llxFooter();
$db->close();
