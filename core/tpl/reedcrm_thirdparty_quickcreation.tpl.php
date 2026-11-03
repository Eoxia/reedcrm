<?php

// Quick add thirdparty
if ($permissiontoaddthirdparty) {
	print load_fiche_titre($langs->trans('QuickThirdPartyCreation'), '', 'company');

	print dol_get_fiche_head();

	// The SIREN search of the module Sirene prefills the third party with the official company data
	$sireneEnabled = isModEnabled('sirene') && dol_include_once('/sirene/class/actions_sirene.class.php');
	if ($sireneEnabled) {
		// Data of the company selected in the search, kept in the form until the third party creation
		foreach (['name_alias', 'address', 'zipcode', 'town', 'state_id', 'country_id', 'idprof1', 'idprof2', 'idprof3', 'idprof6', 'tva_intra', 'effectif_id', 'forme_juridique_code', 'options_sirene_company_admin_status'] as $sireneField) {
			print '<input type="hidden" name="' . $sireneField . '" value="' . dol_escape_htmltag(GETPOST($sireneField, 'alphanohtml')) . '">';
		}
	}

	print '<table class="border centpercent tableforfieldcreate">';

	// SIREN search form, moved above the table by the module Sirene javascript
	if ($sireneEnabled) {
		$sireneThirdParty          = new Societe($db);
		$sireneThirdParty->name    = GETPOST('name', 'alphanohtml');
		$sireneThirdParty->idprof1 = GETPOST('idprof1', 'alphanohtml');
		$sireneThirdParty->idprof2 = GETPOST('idprof2', 'alphanohtml');
		$sireneThirdParty->idprof3 = GETPOST('idprof3', 'alphanohtml');
		$sireneThirdParty->idprof6 = GETPOST('idprof6', 'alphanohtml');
		$sireneThirdParty->zip     = GETPOST('zipcode', 'alphanohtml');
		$sireneThirdParty->town    = GETPOST('town', 'alphanohtml');

		$sireneAction  = 'create';
		$actionsSirene = new ActionsSirene($db);
		$actionsSirene->formObjectOptions(['context' => 'thirdpartycard'], $sireneThirdParty, $sireneAction, $hookmanager);
	}

	// Name, firstname
	if (getDolGlobalInt('REEDCRM_THIRDPARTY_NAME_VISIBLE') > 0) {
		print '<tr><td class="titlefieldcreate fieldrequired"><label for="name">' . $langs->trans('ThirdPartyName') . '</label></td>';
		print '<td><input type="text" name="name" id="name" class="maxwidth200 widthcentpercentminusx" maxlength="128" value="' . (GETPOSTISSET('name') ? GETPOST('name', 'alpha') : '') . '" autofocus="autofocus"></td>';
		print '</tr>';
	}

	if (getDolGlobalInt('REEDCRM_THIRDPARTY_CLIENT_VISIBLE') > 0) {
		print '<tr><td class="titlefieldcreate fieldrequired"><label for="name">' . $langs->trans('ProspectCustomer') . '</label></td>';
		print '<td>' . $formcompany->selectProspectCustomerType(GETPOSTISSET('client') ? GETPOST('client') : getDolGlobalInt('REEDCRM_THIRDPARTY_CLIENT_VALUE'), 'client', 'customerprospect', 'form', 'maxwidth200 widthcentpercentminusx') . '</td>';
	}

	// Phone
	if (getDolGlobalInt('REEDCRM_THIRDPARTY_PHONE_VISIBLE') > 0) {
		print '<tr><td><label for="phone">' . $langs->trans('Phone') . '</label></td>';
		print '<td>' . img_picto('', 'phone', 'class="pictofixedwidth"') . ' <input type="text" name="phone" id="phone" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('phone') ? GETPOST('phone', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Email
	if (getDolGlobalInt('REEDCRM_THIRDPARTY_EMAIL_VISIBLE') > 0) {
		print '<tr><td><label for="email_thirdparty">' . $langs->trans('Email') . '</label></td>';
		print '<td>' . img_picto('', 'object_email', 'class="pictofixedwidth"') . ' <input type="text" name="email_thirdparty" id="email_thirdparty" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('email_thirdparty') ? GETPOST('email_thirdparty', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Web
	if (getDolGlobalInt('REEDCRM_THIRDPARTY_WEB_VISIBLE') > 0) {
		print '<tr><td><label for="url">' . $langs->trans('Web') . '</label></td>';
		print '<td>' . img_picto('', 'globe', 'class="pictofixedwidth"') . ' <input type="text" name="url" id="url" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('url') ? GETPOST('url', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

    // Commercial
    if (getDolGlobalInt('REEDCRM_THIRDPARTY_COMMERCIAL_VISIBLE') > 0) {
        print '<tr><td>' . $langs->trans('AllocateCommercial') . '</td><td>';
        $userList = $form->select_dolusers('', '', 0, null, 0, '', '', 0, 0, 0, '((u.statut:=:1) AND (u.employee:=:1))', 0, '', '', 0, 1);
        print img_picto('', 'user', 'class="pictofixedwidth"') . $form->multiselectarray('commercial', $userList, GETPOST('commercial', 'array'), '', '', 'quatrevingtpercent widthcentpercentminusx');
        print '</td></tr>';
    }

	// Private note
	if (getDolGlobalInt('REEDCRM_THIRDPARTY_PRIVATE_NOTE_VISIBLE') > 0 && isModEnabled('fckeditor')) {
		print '<tr><td><label for="note_private">' . $langs->trans('NotePrivate') . '</label></td>';
		$doleditor = new DolEditor('note_private', (GETPOSTISSET('note_private') ? GETPOST('note_private', 'alpha') : ''), '', 80, 'dolibarr_notes', 'In', 0, false, ((empty(getDolGlobalInt('FCKEDITOR_ENABLE_NOTE_PRIVATE')) || $conf->browser->layout == 'phone') ? 0 : 1), ROWS_3, '90%');
		print '<td>' . $doleditor->Create(1) . '</td>';
		print '</tr>';
	}

	// Categories
	if (isModEnabled('categorie') && getDolGlobalInt('REEDCRM_THIRDPARTY_CATEGORIES_VISIBLE') > 0 ) {
		print '<tr><td>' . $langs->trans('CustomersProspectsCategoriesShort') . '</td><td>';
		$cate_arbo = $form->select_all_categories(Categorie::TYPE_CUSTOMER, '', 'parent', 64, 0, 1);
		print img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('categories_customer', $cate_arbo, GETPOST('categories_customer', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
		print '</td></tr>';
	}

	print '</table>';

	print dol_get_fiche_end();
}
