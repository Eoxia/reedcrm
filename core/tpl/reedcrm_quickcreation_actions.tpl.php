<?php

if ($action == 'add') {
	// Check project parameters
	if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) {
		if (GETPOST('opp_amount') != '' && !(GETPOST('opp_status') > 0)) {
			setEventMessages($langs->trans('ErrorOppStatusRequiredIfAmount'), [], 'errors');
			$error++;
		}
	}

	if (empty(GETPOST('name')) && empty(GETPOST('title'))) {
		setEventMessages($langs->trans('ErrorNoProjectAndThirdpartyInformations'), [], 'errors');
		$error++;
	}

	if (!$error) {
		$db->begin();

		if (!empty(GETPOST('name'))) {
			$thirdparty->code_client  = -1;
			$thirdparty->client       = GETPOST('client');
			$thirdparty->name         = GETPOST('name');
			$thirdparty->phone        = GETPOST('phone', 'alpha');
			$thirdparty->email        = !empty(GETPOST('email_thirdparty', 'custom', 0, FILTER_SANITIZE_EMAIL)) ? trim(GETPOST('email_thirdparty', 'custom', 0, FILTER_SANITIZE_EMAIL)) : 'nomail@nomail.com-' .  dol_print_date(dol_now(), 'dayhourlog');
			$thirdparty->url          = trim(GETPOST('url', 'custom', 0, FILTER_SANITIZE_URL));
			$thirdparty->note_private = GETPOST('note_private');
            $thirdparty->country_id   = $mysoc->country_id;

			// Official company data brought by the SIREN search (module Sirene)
			if (isModEnabled('sirene') && GETPOSTINT('has_done_sirene_search')) {
				$thirdparty->name_alias           = GETPOST('name_alias', 'alphanohtml');
				$thirdparty->address              = GETPOST('address', 'alphanohtml');
				$thirdparty->zip                  = GETPOST('zipcode', 'alphanohtml');
				$thirdparty->town                 = GETPOST('town', 'alphanohtml');
				$thirdparty->state_id             = GETPOSTINT('state_id');
				$thirdparty->idprof1              = GETPOST('idprof1', 'alphanohtml');
				$thirdparty->idprof2              = GETPOST('idprof2', 'alphanohtml');
				$thirdparty->idprof3              = GETPOST('idprof3', 'alphanohtml');
				$thirdparty->idprof6              = GETPOST('idprof6', 'alphanohtml');
				$thirdparty->tva_intra            = GETPOST('tva_intra', 'alphanohtml');
				$thirdparty->effectif_id          = GETPOSTINT('effectif_id');
				$thirdparty->forme_juridique_code = GETPOST('forme_juridique_code', 'alphanohtml');
				if (GETPOSTINT('country_id') > 0) {
					$thirdparty->country_id = GETPOSTINT('country_id');
				}

				$thirdparty->array_options['options_sirene_company_admin_status'] = GETPOST('options_sirene_company_admin_status', 'alphanohtml');
				$thirdparty->array_options['options_sirene_update_date']          = dol_now();
			}

			$thirdpartyID = $thirdparty->create($user);
			if ($thirdpartyID > 0) {
				$backtopage = dol_buildpath('/societe/card.php', 1) . '?id=' . $thirdpartyID;

                // Sales representatives association
                $salesReps = GETPOST('commercial', 'array');
                if (count($salesReps) > 0) {
                    $result = $thirdparty->setSalesRep($salesReps, true);
                    if ($result < 0) {
                        setEventMessages($thirdparty->error, $thirdparty->errors, 'errors');
                        $error++;
                    }
                }

				// Category association
				$categories = GETPOST('categories_customer', 'array');
				if (count($categories) > 0) {
					$result = $thirdparty->setCategories($categories, 'customer');
					if ($result < 0) {
						setEventMessages($thirdparty->error, $thirdparty->errors, 'errors');
						$error++;
					}
				}

				if (!empty(GETPOST('lastname', 'alpha'))) {
					$contact->socid     = !empty($thirdpartyID) ? $thirdpartyID : '';
					$contact->lastname  = GETPOST('lastname', 'alpha');
					$contact->firstname = GETPOST('firstname', 'alpha');
					$contact->poste     = GETPOST('job', 'alpha');
					$contact->email     = trim(GETPOST('email_contact', 'custom', 0, FILTER_SANITIZE_EMAIL));
					$contact->phone_pro = GETPOST('phone_pro', 'alpha');

					$contactID = $contact->create($user);
					if ($contactID > 0) {
						// Category association
						$categories = GETPOST('categories_contact', 'array');
						if (count($categories) > 0) {
							$result = $contact->setCategories($categories);
							if ($result < 0) {
								setEventMessages($contact->error, $contact->errors, 'errors');
								$error++;
							}
						}
					} else {
						setEventMessages($contact->error, $contact->errors, 'errors');
						$error++;
					}
				}
			} else {
				setEventMessages($thirdparty->error, $thirdparty->errors, 'errors');
				$error++;
			}
		}

		if (!empty(GETPOST('title'))) {
            $project->socid       = !empty($thirdpartyID) ? $thirdpartyID : '';
            $project->ref         = GETPOST('ref');
            $project->title       = GETPOST('title');
            $project->description = GETPOST('description', 'restricthtml'); // Do not use 'alpha' here, we want field as it is
            $project->opp_status  = GETPOST('opp_status', 'int');

            $extrafields->fetch_name_optionals_label($project->table_element);
            $extrafields->setOptionalsFromPost([], $project);

			switch ($project->opp_status) {
				case 2:
					$project->opp_percent = 20;
					break;
				case 3:
					$project->opp_percent = 40;
					break;
				case 4:
					$project->opp_percent = 60;
					break;
				case 5:
					$project->opp_percent = 100;
					break;
				default:
					$project->opp_percent = 0;
					break;
			}

			$project->opp_amount        = price2num(GETPOST('opp_amount'));
			$project->date_c            = dol_now();
			$project->date_start        = $date_start;
			$project->status            = getDolGlobalString('PROJECT_CREATE_NO_DRAFT') ? Project::STATUS_VALIDATED : Project::STATUS_DRAFT;
			$project->usage_opportunity = 1;
			$project->usage_task        = 1;

			$projectID = $project->create($user);
			if (!$error && $projectID > 0) {
				$backtopage = dol_buildpath('/projet/card.php', 1) . '?id=' . $projectID;

				// Category association
				$categories = GETPOST('categories_project', 'array');
				if (count($categories) > 0) {
					$result = $project->setCategories($categories);
					if ($result < 0) {
						setEventMessages($project->error, $project->errors, 'errors');
						$error++;
					}
				}

				$project->add_contact($user->id, 'PROJECTLEADER', 'internal');

				// Add commercial to project as SALESREPINTERNAL
				$salesRepsProject = GETPOST('commercial_project', 'array');
				$salesRepsThirdParty = GETPOST('commercial', 'array');

				// Héritage: si l'option est cochée, on prend les commerciaux du tiers. Sinon, ceux du projet.
				if (!empty($conf->global->REEDCRM_PROJECT_COMMERCIAL_INHERIT)) {
					$salesRepsToAssign = $salesRepsThirdParty;
				} else {
					$salesRepsToAssign = $salesRepsProject;
				}

				if (!empty($salesRepsToAssign) && is_array($salesRepsToAssign) && count($salesRepsToAssign) > 0) {
					foreach ($salesRepsToAssign as $salesrepId) {
						$project->add_contact($salesrepId, 'SALESREPINTERNAL', 'internal');
					}
				}

				$defaultref = '';
				$obj        = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

				if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $conf->global->PROJECT_TASK_ADDON . '.php')) {
					require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $conf->global->PROJECT_TASK_ADDON . '.php';
					$modTask    = new $obj();
					$defaultref = $modTask->getNextValue($thirdparty, $task);
				}

				$task->fk_project = $projectID;
				$task->ref        = $defaultref;
				$task->label      = (!empty($conf->global->REEDCRM_TASK_LABEL_VALUE) ? $conf->global->REEDCRM_TASK_LABEL_VALUE : $langs->trans('CommercialFollowUp')) . ' - ' . $project->title;
				$task->date_c     = dol_now();

				$taskID = $task->create($user);
				if ($taskID > 0) {
					$task->add_contact($user->id, 'TASKEXECUTIVE', 'internal');
					$project->array_options['commtask'] = $taskID;
					$project->update($user);
				} else {
					setEventMessages($task->error, $task->errors, 'errors');
					$error++;
				}

				// Address contact: the one carrying the address of the project (PROJECTADDRESS role)
				$addressContactID = 0;
				$addressDetail    = trim(GETPOST('address_detail', 'restricthtml'));
				if (dol_strlen($addressDetail) > 0) {
					$addressContact = new Contact($db);
					if (GETPOSTISSET('address_contact_same') && !empty($contactID) && $addressContact->fetch($contactID) > 0) {
						// Same person as the third party contact, only the address is completed
						$addressContact->address = $addressDetail;
						if ($addressContact->update($contactID, $user) > 0) {
							$addressContactID = $contactID;
						} else {
							setEventMessages($addressContact->error, $addressContact->errors, 'errors');
							$error++;
						}
					} else {
						$addressContact->socid    = !empty($thirdpartyID) ? $thirdpartyID : '';
						$addressContact->lastname = !empty(GETPOST('lastname_address', 'alpha')) ? GETPOST('lastname_address', 'alpha') : $project->title;
						$addressContact->address  = $addressDetail;

						$addressContactID = $addressContact->create($user);
						if ($addressContactID < 0) {
							setEventMessages($addressContact->error, $addressContact->errors, 'errors');
							$error++;
						}
					}
				}

				if ($addressContactID > 0) {
					$project->add_contact($addressContactID, 'PROJECTADDRESS', 'external');
					$project->array_options['options_projectaddress'] = $addressContactID;
					$project->updateExtraField('projectaddress');

					if (isModEnabled('categorie') && getDolGlobalInt('REEDCRM_ADDRESS_MAIN_CATEGORY') > 0) {
						$category->fetch(getDolGlobalInt('REEDCRM_ADDRESS_MAIN_CATEGORY'));
						$category->add_type($addressContact);
					}

					// The PROJECT_ADD_CONTACT trigger geolocates from the posted contactid, absent here
					$addressesList = $geolocation->getDataFromOSM($addressContact);
					if (!empty($addressesList)) {
						$geolocation->latitude  = $addressesList[0]->lat;
						$geolocation->longitude = $addressesList[0]->lon;
						$geolocation->status    = Geolocation::STATUS_GEOLOCATED;
					} else {
						$geolocation->status = Geolocation::STATUS_NOTFOUND;
					}
					$geolocation->element_type = 'contact';
					$geolocation->gis          = 'osm';
					$geolocation->fk_element   = $addressContactID;
					$geolocation->create($user);

					$addressContact->array_options['options_address_status'] = $geolocation->status;
					$addressContact->updateExtraField('address_status');
				}

				// Project contact (PROJECTCONTRIBUTOR role)
				$projectContactID = 0;
				if (GETPOSTISSET('project_contact_same')) {
					$projectContactID = !empty($contactID) ? $contactID : 0;
				} elseif (!empty(GETPOST('lastname_project', 'alpha'))) {
					$projectContact            = new Contact($db);
					$projectContact->socid     = !empty($thirdpartyID) ? $thirdpartyID : '';
					$projectContact->lastname  = GETPOST('lastname_project', 'alpha');
					$projectContact->firstname = GETPOST('firstname_project', 'alpha');
					$projectContact->phone_pro = GETPOST('phone_pro_project', 'alpha');
					$projectContact->email     = trim(GETPOST('email_project', 'custom', 0, FILTER_SANITIZE_EMAIL));

					$projectContactID = $projectContact->create($user);
					if ($projectContactID < 0) {
						setEventMessages($projectContact->error, $projectContact->errors, 'errors');
						$error++;
					}
				}

				if ($projectContactID > 0) {
					$project->add_contact($projectContactID, 'PROJECTCONTRIBUTOR', 'external');
				}
			} else {
				$langs->load('errors');
				setEventMessages($project->error, $project->errors, 'errors');
				$error++;
			}
		}

		$parameters['projectID']    = $projectID;
		$parameters['contactID']    = $contactID;
		$parameters['thirdpartyID'] = $thirdpartyID;

		$reshook = $hookmanager->executeHooks('quickCreationAction', $parameters, $project, $action); // Note that $action and $project may have been modified by some hooks

		if ($reshook > 0) {
			$backtopage = $hookmanager->resPrint;
		}

		if (!$error) {
			$db->commit();
			if (!empty($backtopage)) {
				header('Location: ' . $backtopage);
			}
			exit;
		} else {
			$db->rollback();
			unset($_POST['ref']);
			$action = '';
		}
	} else {
		$action = '';
	}
}
