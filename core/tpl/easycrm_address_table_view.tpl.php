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
 * \file    core/tpl/easycrm_address_table_view.tpl.php
 * \ingroup easycrm
 * \brief   Template page for address table.
 */

/**
 * The following vars must be defined:
 * Global     : $conf, $db, $langs, $user,
 * Parameters : $objectType, $fromId, $backtopage,
 * Objects    : $contact, $objectLinked
 * Variable   : $addresses, $moduleNameLowerCase, $permissiontoadd
 */

print '<table class="border centpercent tableforfield">';

print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Address') . '</td>';
print '<td class="right">' . $langs->trans('SignatureActions') . '</td>';
print '</tr>';

if (is_array($addresses) && !empty($addresses)) {
	foreach ($addresses as $address) {
        $contact->fetch($address['id']);

        //Object favorite
        $favorite = 0;
        if (isset($objectLinked->array_options['options_' . $objectType . 'address']) && dol_strlen($objectLinked->array_options['options_' . $objectType . 'address']) > 0) {
            $favorite = $objectLinked->array_options['options_' . $objectType . 'address'] == $address['id'];
        }

		// Address name
		print '<td>';
		print $contact->getNomUrl(1) . ' ' . ($permissiontoadd ? '<span style="cursor:pointer;" name="favorite_address" id="address"' . $address['id'] . ' value="' . $address['id'] . '" class="' . ($favorite ? 'fas' : 'far') . ' fa-star"></span>' : '');
		print '</td>';

        // Address location
		print '<td class="minwidth300">';
        $geolocations    = $geolocation->fetchAll('DESC', 'rowid', 1, 0, ['customsql' => 'fk_element = ' . $address['id']]);
        $lastGeolocation = array_shift($geolocations);

        if ($lastGeolocation->longitude > 0 && $lastGeolocation->latitude > 0) {
            print img_picto($langs->trans('DataSuccessfullyRetrieved'), 'fontawesome_map-marker-alt_fas_#28a745') . ' ';
        } else {
            print img_picto($langs->trans('CouldntFindDataOnOSM'), 'fontawesome_exclamation-triangle_fas_#8c4446') . ' ';
        }
		print dol_strlen($contact->address) > 0 ? $contact->address : $langs->trans('N/A');
		print '</td>';

		// Actions
		print '<td class="right">';
        if ($permissiontoadd) {
            print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&action=edit&from_type=' . $objectType . '"  style="display: inline">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="contact_id" value="' . $address['id'] . '">';
            if (!empty($backtopage)) {
                print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
            }
            print '<button type="submit" class="wpeo-button button-grey"><i class="fas fa-pen"></i></button> ';
            print '</form>';
        }
		if ($permissiontodelete) {
			print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&module_name=' . $moduleName . '&from_type=' . $objectLinked->element . '"  style="display: inline">';
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="action" value="delete_address">';
			print '<input type="hidden" name="contact_id" value="' . $address['id'] . '">';
			print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
			print '<button type="submit" class="wpeo-button button-grey" value="' . $address['id'] . '">';
			print '<i class="fas fa-trash"></i>';
			print '</button>';
			print '</form>';
		}
        print '</td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="4">';
	print '<div class="opacitymedium">' . $langs->trans('NoAddresses') . '</div><br>';
	print '</td></tr>';
}

if ($permissiontoadd) {
	print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?from_id=' . $fromId . '&action=create&from_type=' . $objectType . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	if (!empty($backtopage)) {
		print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
	}

	print '<tr class="oddeven">';
	print '<td>' . $langs->trans('AddAnAddress') . '</td>';
	print '<td></td>';
	print '<td class="right">';
	print '<button type="submit" class="wpeo-button button-blue"><i class="fas fa-plus"></i></button>';
	print '</td></tr>';
	print '</table>';
	print '</form>';
}
?>
