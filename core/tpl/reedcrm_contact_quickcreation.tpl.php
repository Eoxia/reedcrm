<?php

// Quick add contact
if ($permissiontoaddcontact) {
	print load_fiche_titre($langs->trans('QuickContactCreation'), '', 'contact');

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldcreate">';

	// The contact is created with the third party: identical to it (its phone, email and address, its name when none is
	// typed) or a new blank one, whose details are typed below and which is created only when it has a name
	$contactSame = $permissiontoaddthirdparty && GETPOST('contact_mode', 'aZ09') !== 'new';
	if ($permissiontoaddthirdparty) {
		print '<tr><td class="titlefieldcreate">' . $langs->trans('QuickCreationContactMode') . '</td><td>';
		print '<input type="radio" name="contact_mode" id="contact_mode_same" class="reedcrm-contact-mode" value="same"' . ($contactSame ? ' checked' : '') . '>';
		print ' <label for="contact_mode_same" class="marginrightonly">' . $langs->trans('QuickCreationContactSame') . '</label>';
		print '<input type="radio" name="contact_mode" id="contact_mode_new" class="reedcrm-contact-mode" value="new"' . ($contactSame ? '' : ' checked') . '>';
		print ' <label for="contact_mode_new">' . $langs->trans('QuickCreationContactNew') . '</label>';
		print '<div class="opacitymedium small reedcrm-contact-same-help' . ($contactSame ? '' : ' hideobject') . '">' . $langs->trans('QuickCreationContactSameHelp') . '</div>';
		print '</td></tr>';
	}

	// Name, firstname. An identical contact left without a name takes the one of the third party, shown as placeholder
	if (getDolGlobalInt('REEDCRM_CONTACT_LASTNAME_VISIBLE') > 0) {
		print '<tr><td class="titlefieldcreate"><label for="lastname">' . $langs->trans('Lastname') . ' / ' . $langs->trans('Label') . '</label></td>';
		print '<td><input type="text" name="lastname" id="lastname" class="maxwidth200 widthcentpercentminusx" maxlength="50" value="' . dol_escape_htmltag(GETPOSTISSET('lastname') ? GETPOST('lastname', 'alpha') : '') . '"' . ($contactSame ? ' placeholder="' . dol_escape_htmltag(GETPOST('name', 'alpha')) . '"' : '') . '></td>';
		print '</tr>';
	}

	if (getDolGlobalInt('REEDCRM_CONTACT_FIRSTNAME_VISIBLE') > 0) {
		print '<tr><td><label for="firstname">' . $langs->trans('Firstname') . '</label></td>';
		print '<td><input type="text" name="firstname" id="firstname" class="maxwidth200 widthcentpercentminusx" maxlength="50" value="' . dol_escape_htmltag(GETPOSTISSET('firstname') ? GETPOST('firstname', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	// Job position
	if (getDolGlobalInt('REEDCRM_CONTACT_JOB_VISIBLE') > 0) {
		print '<tr><td><label for="job">' . $langs->trans('PostOrFunction') . '</label></td>';
		print '<td><input type="text" name="job" id="job" class="maxwidth200 widthcentpercentminusx" maxlength="255" value="' . dol_escape_htmltag(GETPOSTISSET('job') ? GETPOST('job') : '') . '"></td>';
		print '</tr>';
	}

	// Phone and email of a new contact, those of the third party for an identical one
	if (getDolGlobalInt('REEDCRM_CONTACT_PHONEPRO_VISIBLE') > 0) {
		print '<tr class="reedcrm-contact-own-details' . ($contactSame ? ' hideobject' : '') . '"><td><label for="phone_pro">' . $langs->trans('PhonePro') . '</label></td>';
		print '<td>' . img_picto('', 'object_phoning', 'class="pictofixedwidth"') . ' <input type="text" name="phone_pro" id="phone_pro" class="maxwidth200 widthcentpercentminusx" value="' . dol_escape_htmltag(GETPOSTISSET('phone_pro') ? GETPOST('phone_pro', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	if (getDolGlobalInt('REEDCRM_CONTACT_EMAIL_VISIBLE') > 0) {
		print '<tr class="reedcrm-contact-own-details' . ($contactSame ? ' hideobject' : '') . '"><td><label for="email_contact">' . $langs->trans('Email') . '</label></td>';
		print '<td>' . img_picto('', 'object_email', 'class="pictofixedwidth"') . ' <input type="text" name="email_contact" id="email_contact" class="maxwidth200 widthcentpercentminusx" value="' . dol_escape_htmltag(GETPOSTISSET('email_contact') ? GETPOST('email_contact', 'alpha') : '') . '"></td>';
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
	// PROJECTCONTRIBUTOR roles). By default they are the contact typed above, ticking "another person"
	// opens the fields to create a dedicated one. Unticked by default, so an empty POST reads back right.
	if ($permissiontoaddproject) {
		$addressContactOther = GETPOSTISSET('address_contact_other');
		$projectContactOther = GETPOSTISSET('project_contact_other');

		// Address of the project, carried by the address contact. Named so: the third party block shows its own address
		print '<tr><td class="titlefieldcreate"><label for="address_detail">' . $langs->trans('QuickCreationProjectAddress') . '</label></td>';
		print '<td><textarea name="address_detail" id="address_detail" class="maxwidth500 widthcentpercentminusx" rows="' . ROWS_3 . '">' . dol_escape_htmltag(GETPOSTISSET('address_detail') ? GETPOST('address_detail', 'restricthtml') : '') . '</textarea></td>';
		print '</tr>';

		// Address contact
		print '<tr><td>' . $langs->trans('QuickCreationAddressContact') . '</td>';
		print '<td><input type="checkbox" name="address_contact_other" id="address_contact_other" class="reedcrm-other-contact" data-target="address-contact-fields"' . ($addressContactOther ? ' checked' : '') . '>';
		print ' <label for="address_contact_other">' . $langs->trans('QuickCreationOtherContact') . '</label></td>';
		print '</tr>';

		print '<tr class="address-contact-fields' . ($addressContactOther ? '' : ' hideobject') . '">';
		print '<td><label for="lastname_address">' . $langs->trans('Name') . '</label></td>';
		print '<td><input type="text" name="lastname_address" id="lastname_address" class="maxwidth200 widthcentpercentminusx" maxlength="50" value="' . dol_escape_htmltag(GETPOSTISSET('lastname_address') ? GETPOST('lastname_address', 'alpha') : '') . '"></td>';
		print '</tr>';

		// Project contact, whose details fill the contact details of the project (see reedcrm_quickcreation_actions.tpl.php)
		print '<tr><td>' . $langs->trans('QuickCreationProjectContact') . '</td>';
		print '<td><input type="checkbox" name="project_contact_other" id="project_contact_other" class="reedcrm-other-contact" data-target="project-contact-fields"' . ($projectContactOther ? ' checked' : '') . '>';
		print ' <label for="project_contact_other">' . $langs->trans('QuickCreationOtherContact') . '</label>';
		print '<div class="opacitymedium small">' . $langs->trans('QuickCreationProjectContactHelp') . '</div></td>';
		print '</tr>';

		print '<tr class="project-contact-fields' . ($projectContactOther ? '' : ' hideobject') . '">';
		print '<td><label for="lastname_project">' . $langs->trans('Lastname') . '</label></td>';
		print '<td><input type="text" name="lastname_project" id="lastname_project" class="maxwidth200 widthcentpercentminusx" maxlength="50" value="' . dol_escape_htmltag(GETPOSTISSET('lastname_project') ? GETPOST('lastname_project', 'alpha') : '') . '"></td>';
		print '</tr>';

		print '<tr class="project-contact-fields' . ($projectContactOther ? '' : ' hideobject') . '">';
		print '<td><label for="firstname_project">' . $langs->trans('Firstname') . '</label></td>';
		print '<td><input type="text" name="firstname_project" id="firstname_project" class="maxwidth200 widthcentpercentminusx" maxlength="50" value="' . dol_escape_htmltag(GETPOSTISSET('firstname_project') ? GETPOST('firstname_project', 'alpha') : '') . '"></td>';
		print '</tr>';

		print '<tr class="project-contact-fields' . ($projectContactOther ? '' : ' hideobject') . '">';
		print '<td><label for="phone_pro_project">' . $langs->trans('PhonePro') . '</label></td>';
		print '<td>' . img_picto('', 'object_phoning', 'class="pictofixedwidth"') . ' <input type="text" name="phone_pro_project" id="phone_pro_project" class="maxwidth200 widthcentpercentminusx" value="' . dol_escape_htmltag(GETPOSTISSET('phone_pro_project') ? GETPOST('phone_pro_project', 'alpha') : '') . '"></td>';
		print '</tr>';

		print '<tr class="project-contact-fields' . ($projectContactOther ? '' : ' hideobject') . '">';
		print '<td><label for="email_project">' . $langs->trans('Email') . '</label></td>';
		print '<td>' . img_picto('', 'object_email', 'class="pictofixedwidth"') . ' <input type="text" name="email_project" id="email_project" class="maxwidth200 widthcentpercentminusx" value="' . dol_escape_htmltag(GETPOSTISSET('email_project') ? GETPOST('email_project', 'alpha') : '') . '"></td>';
		print '</tr>';
	}

	print '</table>';

	print dol_get_fiche_end();
}
