<?php

// Quick add project/task
if ($permissiontoaddproject) {
	print load_fiche_titre($langs->trans('QuickProjectCreation'), '', 'project');

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldcreate">';

	$defaultref = '';
	$modele = empty($conf->global->PROJECT_ADDON) ? 'mod_project_simple' : $conf->global->PROJECT_ADDON;

	// Search template files
	$file = '';
	$classname = '';
	$filefound = 0;
	$dirmodels = array_merge(['/'], $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir . 'core/modules/project/' . $modele . '.php');
		if (file_exists($file)) {
			$filefound = 1;
			$classname = $modele;
			break;
		}
	}

	if ($filefound) {
		$result = dol_include_once($reldir . 'core/modules/project/' . $modele . '.php');
		$modProject = new $classname();

		$defaultref = $modProject->getNextValue($thirdparty, $project);
	}

	if (is_numeric($defaultref) && $defaultref <= 0) {
		$defaultref = '';
	}

	// Ref
	$suggestedref = (GETPOST('ref') ? GETPOST('ref') : $defaultref);
	print '<input type="hidden" name="ref" value="' . dol_escape_htmltag($suggestedref) . '">';

	// Label
	if ($conf->global->EASYCRM_PROJECT_LABEL_VISIBLE > 0) {
		print '<tr><td class="titlefieldcreate fieldrequired"><label for="title">' . $langs->trans('ProjectLabel') . '</label></td>';
		print '<td><input type="text" name="title" id="title" class="maxwidth500 widthcentpercentminusx" maxlength="255" value="' . dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')) . '"></td>';
		print '</tr>';
	}

	if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) {
		// Opportunity status
		if ($conf->global->EASYCRM_PROJECT_OPPORTUNITY_STATUS_VISIBLE > 0) {
			print '<tr><td><label for="opp_status">' . $langs->trans('OpportunityStatus') . '</label></td>';
			print '<td>' . $formproject->selectOpportunityStatus('opp_status', GETPOSTISSET('opp_status') ? GETPOST('opp_status') : $conf->global->EASYCRM_PROJECT_OPPORTUNITY_STATUS_VALUE, 1, 0, 0, 0, '', 0, 1) . '</td>';
			print '</tr>';
		}

		// Opportunity amount
		if ($conf->global->EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) {
			print '<tr><td><label for="opp_amount">' . $langs->trans('OpportunityAmount') . '</label></td>';
			print '<td><input type="text" name="opp_amount" id="opp_amount" size="5" value="' . dol_escape_htmltag(GETPOSTISSET('opp_amount') ? GETPOST('opp_amount') : $conf->global->EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VALUE) . '"></td>';
			print '</tr>';
		}
	}

	// Date start
	if ($conf->global->EASYCRM_PROJECT_DATE_START_VISIBLE > 0) {
		print '<tr><td><label for="projectstart">' . $langs->trans('DateStart') . '</label></td>';
		print '<td>' . $form->selectDate(($date_start ?: ''), 'projectstart') . '</td>';
		print '</tr>';
	}

    // Description
    if ($conf->global->EASYCRM_PROJECT_DESCRIPTION_VISIBLE > 0 && isModEnabled('fckeditor')) {
        print '<tr><td>' . $langs->trans('Description') . '</td>';
        print '<td>';
        $dolEditor = new DolEditor('description', GETPOST('description', 'restricthtml'), '', 90, 'dolibarr_details', '', false, true, getDolGlobalString('FCKEDITOR_ENABLE_SOCIETE'), ROWS_3, '90%');
        $dolEditor->Create();
        print '</td></tr>';
    }

    // Other attributes.
    if ($conf->global->EASYCRM_PROJECT_EXTRAFIELDS_VISIBLE > 0) {
        $object = $project;
        $extrafields->fetch_name_optionals_label($object->table_element);
        include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';
        $object = '';
    }

	// Categories
	if (isModEnabled('categorie') && $conf->global->EASYCRM_PROJECT_CATEGORIES_VISIBLE > 0) {
		print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
		$cate_arbo = $form->select_all_categories(Categorie::TYPE_PROJECT, '', 'parent', 64, 0, 1);
		print img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('categories_project', $cate_arbo, GETPOST('categories_project', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
		print '</td></tr>';
	}

	print '</table>';

	print dol_get_fiche_end();
}
