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
					if ($contactID < 0) {
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
			$project->statut            = 1;
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

				$defaultref = '';
				$obj        = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

				if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $conf->global->PROJECT_TASK_ADDON . '.php')) {
					require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $conf->global->PROJECT_TASK_ADDON . '.php';
					$modTask    = new $obj();
					$defaultref = $modTask->getNextValue($thirdparty, $task);
				}

				$task->fk_project = $projectID;
				$task->ref        = $defaultref;
				$task->label      = (!empty($conf->global->EASYCRM_TASK_LABEL_VALUE) ? $conf->global->EASYCRM_TASK_LABEL_VALUE : $langs->trans('CommercialFollowUp')) . ' - ' . $project->title;
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
