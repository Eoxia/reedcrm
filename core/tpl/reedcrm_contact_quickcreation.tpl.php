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

	// Address and project contacts. Both are contacts linked to the project (PROJECTADDRESS and
	// PROJECTCONTRIBUTOR roles). By default they are the contact typed above, a checkbox opens the
	// fields to create a dedicated one, like the delivery/billing address pattern.
	if ($permissiontoaddproject) {
		// Checkbox states cannot be read back from an empty POST, this marker tells a repost from a first display
		$rolesSubmitted     = GETPOSTISSET('contact_roles_submitted');
		$addressContactSame = !$rolesSubmitted || GETPOSTISSET('address_contact_same');
		$projectContactSame = !$rolesSubmitted || GETPOSTISSET('project_contact_same');

		// Address of the project, carried by the address contact
		print '<tr><td class="titlefieldcreate"><label for="address_detail">' . $langs->trans('Address') . '</label></td>';
		print '<td><input type="hidden" name="contact_roles_submitted" value="1">';
		print '<textarea name="address_detail" id="address_detail" class="maxwidth500 widthcentpercentminusx" rows="' . ROWS_3 . '">' . dol_escape_htmltag(GETPOSTISSET('address_detail') ? GETPOST('address_detail', 'restricthtml') : '') . '</textarea></td>';
		print '</tr>';

		// Address contact
		print '<tr><td><label for="address_contact_same">' . $langs->trans('AddressContactSameAsThirdPartyContact') . '</label></td>';
		print '<td><input type="checkbox" name="address_contact_same" id="address_contact_same" class="reedcrm-same-contact" data-target="address-contact-fields"' . ($addressContactSame ? ' checked' : '') . '></td>';
		print '</tr>';

		print '<tr class="address-contact-fields"' . ($addressContactSame ? ' style="display: none;"' : '') . '>';
		print '<td><label for="lastname_address">' . $langs->trans('Name') . '</label></td>';
		print '<td><input type="text" name="lastname_address" id="lastname_address" class="maxwidth200 widthcentpercentminusx" maxlength="80" value="' . dol_escape_htmltag(GETPOSTISSET('lastname_address') ? GETPOST('lastname_address', 'alpha') : '') . '"></td>';
		print '</tr>';

		// Project contact
		print '<tr><td><label for="project_contact_same">' . $langs->trans('ProjectContactSameAsThirdPartyContact') . '</label></td>';
		print '<td><input type="checkbox" name="project_contact_same" id="project_contact_same" class="reedcrm-same-contact" data-target="project-contact-fields"' . ($projectContactSame ? ' checked' : '') . '></td>';
		print '</tr>';

		print '<tr class="project-contact-fields"' . ($projectContactSame ? ' style="display: none;"' : '') . '>';
		print '<td><label for="lastname_project">' . $langs->trans('Lastname') . '</label></td>';
		print '<td><input type="text" name="lastname_project" id="lastname_project" class="maxwidth200 widthcentpercentminusx" maxlength="80" value="' . dol_escape_htmltag(GETPOSTISSET('lastname_project') ? GETPOST('lastname_project', 'alpha') : '') . '"></td>';
		print '</tr>';

		print '<tr class="project-contact-fields"' . ($projectContactSame ? ' style="display: none;"' : '') . '>';
		print '<td><label for="firstname_project">' . $langs->trans('Firstname') . '</label></td>';
		print '<td><input type="text" name="firstname_project" id="firstname_project" class="maxwidth200 widthcentpercentminusx" maxlength="80" value="' . dol_escape_htmltag(GETPOSTISSET('firstname_project') ? GETPOST('firstname_project', 'alpha') : '') . '"></td>';
		print '</tr>';

		print '<tr class="project-contact-fields"' . ($projectContactSame ? ' style="display: none;"' : '') . '>';
		print '<td><label for="phone_pro_project">' . $langs->trans('PhonePro') . '</label></td>';
		print '<td>' . img_picto('', 'object_phoning', 'class="pictofixedwidth"') . ' <input type="text" name="phone_pro_project" id="phone_pro_project" class="maxwidth200 widthcentpercentminusx" value="' . dol_escape_htmltag(GETPOSTISSET('phone_pro_project') ? GETPOST('phone_pro_project', 'alpha') : '') . '"></td>';
		print '</tr>';

		print '<tr class="project-contact-fields"' . ($projectContactSame ? ' style="display: none;"' : '') . '>';
		print '<td><label for="email_project">' . $langs->trans('Email') . '</label></td>';
		print '<td>' . img_picto('', 'object_email', 'class="pictofixedwidth"') . ' <input type="text" name="email_project" id="email_project" class="maxwidth200 widthcentpercentminusx" value="' . dol_escape_htmltag(GETPOSTISSET('email_project') ? GETPOST('email_project', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	print '</table>';

	print dol_get_fiche_end();
}
