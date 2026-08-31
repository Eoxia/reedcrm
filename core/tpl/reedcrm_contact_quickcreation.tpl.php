<?php

// Quick add contact
if ($permissiontoaddcontact) {
	print load_fiche_titre($langs->trans('QuickContactCreation'), '', 'contact');

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldcreate">';

	// Name, firstname
	if ($conf->global->REEDCRM_CONTACT_LASTNAME_VISIBLE > 0) {
		print '<tr><td class="titlefieldcreate fieldrequired"><label for="lastname">' . $langs->trans('Lastname') . ' / ' . $langs->trans('Label') . '</label></td>';
		print '<td><input type="text" name="lastname" id="lastname" class="maxwidth200 widthcentpercentminusx" maxlength="80" value="' . dol_escape_htmltag(GETPOSTISSET('lastname') ? GETPOST('lastname', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	if ($conf->global->REEDCRM_CONTACT_FIRSTNAME_VISIBLE > 0) {
		print '<tr><td><label for="firstname">' . $langs->trans('Firstname') . '</label></td>';
		print '<td><input type="text" name="firstname" id="firstname" class="maxwidth200 widthcentpercentminusx" maxlength="80" value="' . dol_escape_htmltag(GETPOSTISSET('firstname') ? GETPOST('firstname', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Job position
	if ($conf->global->REEDCRM_CONTACT_JOB_VISIBLE > 0) {
		print '<tr><td><label for="job">' . $langs->trans('PostOrFunction') . '</label></td>';
		print '<td><input type="text" name="job" id="job" class="maxwidth200 widthcentpercentminusx" maxlength="255" value="' . dol_escape_htmltag(GETPOSTISSET('job') ? GETPOST('job') : '') . '"></td>';
		print '</tr>';
	}

	// Phone
	if ($conf->global->REEDCRM_CONTACT_PHONEPRO_VISIBLE > 0) {
		print '<tr><td><label for="phone_pro">' . $langs->trans('PhonePro') . '</label></td>';
		print '<td>' . img_picto('', 'object_phoning', 'class="pictofixedwidth"') . ' <input type="text" name="phone_pro" id="phone_pro" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('phone_pro') ? GETPOST('phone_pro', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Email
	if ($conf->global->REEDCRM_CONTACT_EMAIL_VISIBLE > 0) {
		print '<tr><td><label for="email_contact">' . $langs->trans('Email') . '</label></td>';
		print '<td>' . img_picto('', 'object_email', 'class="pictofixedwidth"') . ' <input type="text" name="email_contact" id="email_contact" class="maxwidth200 widthcentpercentminusx" value="' . (GETPOSTISSET('email_contact') ? GETPOST('email_contact', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Categories
	if (isModEnabled('categorie') && getDolGlobalInt('REEDCRM_CONTACT_CATEGORIES_VISIBLE') > 0) {
		print '<tr><td>' . $langs->trans('ContactCategoriesShort') . '</td><td>';
		$cate_arbo = $form->select_all_categories(Categorie::TYPE_CONTACT, '', 'parent', 64, 0, 1);
		print img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('categories_contact', $cate_arbo, GETPOST('categories_contact', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
		print '</td></tr>';
	}

	print '</table>';

	print dol_get_fiche_end();
}
