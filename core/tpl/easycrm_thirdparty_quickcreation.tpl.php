<?php

// Quick add thirdparty
if ($permissiontoaddthirdparty) {
	print load_fiche_titre($langs->trans('QuickThirdPartyCreation'), '', 'company');

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldcreate">';

	// Name, firstname
	if (getDolGlobalInt('EASYCRM_THIRDPARTY_NAME_VISIBLE') > 0) {
		print '<tr><td class="titlefieldcreate fieldrequired"><label for="name">' . $langs->trans('ThirdPartyName') . '</label></td>';
		print '<td><input type="text" name="name" id="name" class="maxwidth200 widthcentpercentminusx" maxlength="128" value="' . (GETPOSTISSET('name') ? GETPOST('name', 'alpha') : '') . '" autofocus="autofocus"></td>';
		print '</tr>';
	}

	if (getDolGlobalInt('EASYCRM_THIRDPARTY_CLIENT_VISIBLE') > 0) {
		print '<tr><td class="titlefieldcreate fieldrequired"><label for="name">' . $langs->trans('ProspectCustomer') . '</label></td>';
		print '<td>' . $formcompany->selectProspectCustomerType(GETPOSTISSET('client') ? GETPOST('client') : getDolGlobalInt('EASYCRM_THIRDPARTY_CLIENT_VALUE'), 'client', 'customerprospect', 'form', 'maxwidth200 widthcentpercentminusx') . '</td>';
	}

	// Phone
	if (getDolGlobalInt('EASYCRM_THIRDPARTY_PHONE_VISIBLE') > 0) {
		print '<tr><td><label for="phone">' . $langs->trans('Phone') . '</label></td>';
		print '<td>' . img_picto('', 'phone', 'class="pictofixedwidth"') . ' <input type="text" name="phone" id="phone" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('phone') ? GETPOST('phone', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Email
	if (getDolGlobalInt('EASYCRM_THIRDPARTY_EMAIL_VISIBLE') > 0) {
		print '<tr><td><label for="email_thirdparty">' . $langs->trans('Email') . '</label></td>';
		print '<td>' . img_picto('', 'object_email', 'class="pictofixedwidth"') . ' <input type="text" name="email_thirdparty" id="email_thirdparty" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('email_thirdparty') ? GETPOST('email_thirdparty', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Web
	if (getDolGlobalInt('EASYCRM_THIRDPARTY_WEB_VISIBLE') > 0) {
		print '<tr><td><label for="url">' . $langs->trans('Web') . '</label></td>';
		print '<td>' . img_picto('', 'globe', 'class="pictofixedwidth"') . ' <input type="text" name="url" id="url" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('url') ? GETPOST('url', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

    // Commercial
    if (getDolGlobalInt('EASYCRM_THIRDPARTY_COMMERCIAL_VISIBLE') > 0) {
        print '<tr><td>' . $langs->trans('AllocateCommercial') . '</td><td>';
        $userList = $form->select_dolusers('', '', 0, null, 0, '', '', 0, 0, 0, ' AND u.statut = 1 AND u.employee = 1', 0, '', '', 0, 1);
        print img_picto('', 'user', 'class="pictofixedwidth"') . $form->multiselectarray('commercial', $userList, GETPOST('commercial', 'array'), '', '', 'quatrevingtpercent widthcentpercentminusx');
        print '</td></tr>';
    }

	// Private note
	if (getDolGlobalInt('EASYCRM_THIRDPARTY_PRIVATE_NOTE_VISIBLE') > 0 && isModEnabled('fckeditor')) {
		print '<tr><td><label for="note_private">' . $langs->trans('NotePrivate') . '</label></td>';
		$doleditor = new DolEditor('note_private', (GETPOSTISSET('note_private') ? GETPOST('note_private', 'alpha') : ''), '', 80, 'dolibarr_notes', 'In', 0, false, ((empty(getDolGlobalInt('FCKEDITOR_ENABLE_NOTE_PRIVATE')) || $conf->browser->layout == 'phone') ? 0 : 1), ROWS_3, '90%');
		print '<td>' . $doleditor->Create(1) . '</td>';
		print '</tr>';
	}

	// Categories
	if (isModEnabled('categorie') && getDolGlobalInt('EASYCRM_THIRDPARTY_CATEGORIES_VISIBLE') > 0 ) {
		print '<tr><td>' . $langs->trans('CustomersProspectsCategoriesShort') . '</td><td>';
		$cate_arbo = $form->select_all_categories(Categorie::TYPE_CUSTOMER, '', 'parent', 64, 0, 1);
		print img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('categories_customer', $cate_arbo, GETPOST('categories_customer', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
		print '</td></tr>';
	}

	print '</table>';

	print dol_get_fiche_end();
}
