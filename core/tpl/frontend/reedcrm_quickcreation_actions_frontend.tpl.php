<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/reedcrm_quickcreation_actions_frontend.tpl.php
 * \ingroup reedcrm
 * \brief   Template page for quick creation action frontend
 */

/**
 * The following vars must be defined :
 * Global     : $conf, $langs, $user
 * Parameters : $action, $subaction
 * Objects    : $extraFields, $geolocation, $project, $task
 * Variable   : $error, $permissionToAddProject
 */

// Protection to avoid direct call of template
if (!$permissionToAddProject) {
    exit;
}


if ($action == 'add_audio') {
    $uploadDir  = $conf->reedcrm->multidir_output[$conf->entity] . '/project/tmp/0/project_audio/';
    $uploadFile = $uploadDir . basename($_FILES['audio']['name']);
    if (!dol_is_dir($uploadDir)) {
        dol_mkdir($uploadDir);
    }
    move_uploaded_file($_FILES['audio']['tmp_name'], $uploadFile);
}

if ($action == 'add_audio_existing') {
    $projectId = GETPOST('projectid', 'int');
    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $uploadDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($proj->ref);
            if (!dol_is_dir($uploadDir)) {
                dol_mkdir($uploadDir);
            }
            $filename = 'audio_' . time() . '.wav';
            $uploadFile = $uploadDir . '/' . $filename;
            move_uploaded_file($_FILES['audio']['tmp_name'], $uploadFile);
        }
    }
    exit;
}

if ($action == 'add_photo_existing') {
    $projectId = GETPOST('projectid', 'int');
    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $uploadDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($proj->ref);
            if (!dol_is_dir($uploadDir)) {
                dol_mkdir($uploadDir);
            }
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $filename = 'photo_' . time() . '.jpg';
                $uploadFile = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
                    if (function_exists('vignette')) {
                        vignette($uploadFile, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_MINI ?? 120, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_MINI ?? 120, '_mini');
                        vignette($uploadFile, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_SMALL ?? 240, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_SMALL ?? 240);
                    }
                    
                    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                    $actionComm = new ActionComm($db);
                    $actionComm->label = $proj->ref . "-Ajout-Photo";
                    $actionComm->note_private = "L'utilisateur " . $user->login . " a ajouté une nouvelle photo depuis le listing.";
                    $actionComm->fk_project = $proj->id;
                    $actionComm->elementtype = 'project';
                    $actionComm->datep = dol_now();
                    $actionComm->datef = dol_now();
                    $actionComm->type_id = 0;
                    $actionComm->percentage = -1;
                    $actionComm->create($user);
                }
            }
        }
    }
    exit;
}

if ($action == 'add_file_existing') {
    $projectId = GETPOST('projectid', 'int');
    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $uploadDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($proj->ref);
            if (!dol_is_dir($uploadDir)) {
                dol_mkdir($uploadDir);
            }
            if (isset($_FILES['userfile']['name']) && is_array($_FILES['userfile']['name'])) {
                $nbFiles = count($_FILES['userfile']['name']);
                for ($i = 0; $i < $nbFiles; $i++) {
                    if ($_FILES['userfile']['error'][$i] == 0) {
                        $fileName = dol_sanitizeFileName($_FILES['userfile']['name'][$i]);
                        $fullPath = $uploadDir . '/' . $fileName;
                        
                        if (dol_move_uploaded_file($_FILES['userfile']['tmp_name'][$i], $fullPath, 1, 0, $_FILES['userfile']['error'][$i])) {
                            if (function_exists('vignette')) {
                                vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_MINI ?? 120, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_MINI ?? 120, '_mini');
                                vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_SMALL ?? 240, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_SMALL ?? 240);
                            }
                            
                            require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                            $actionComm = new ActionComm($db);
                            $actionComm->label = $proj->ref . "-Ajout-Document";
                            $actionComm->note_private = "L'utilisateur " . $user->login . " a joint le document '" . $fileName . "' depuis le listing.";
                            $actionComm->fk_project = $proj->id;
                            $actionComm->elementtype = 'project';
                            $actionComm->datep = dol_now();
                            $actionComm->datef = dol_now();
                            $actionComm->type_id = 0;
                            $actionComm->percentage = -1;
                            $actionComm->create($user);
                        }
                    }
                }
            }
        }
    }
    exit;
}

if ($action == 'updateopppercent') {
    $projectId = GETPOST('projectid', 'int');
    $newPercent = GETPOST('percent', 'int');
    $res = array('success' => false);

    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $oldPercent = $proj->opp_percent;
            $proj->opp_percent = $newPercent;
            switch (true) {
                case $newPercent < 20: $proj->opp_status = 1; break;
                case $newPercent < 40: $proj->opp_status = 2; break;
                case $newPercent < 60: $proj->opp_status = 3; break;
                case $newPercent < 100: $proj->opp_status = 4; break;
                case $newPercent == 100: $proj->opp_status = 5; break;
            }
            // Update the project
            if ($proj->update($user) > 0) {
                $res['success'] = true;

                // Intercept the auto-generated "Projet modifié" Agenda event and overwrite its label
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                
                $sqlFindEvent = "SELECT id FROM " . MAIN_DB_PREFIX . "actioncomm 
                                 WHERE fk_project = " . (int)$proj->id . " 
                                 ORDER BY datec DESC LIMIT 1";
                $resqlFound = $db->query($sqlFindEvent);
                if ($resqlFound && $db->num_rows($resqlFound) > 0) {
                    $objEvent = $db->fetch_object($resqlFound);
                    $autoEvent = new ActionComm($db);
                    if ($autoEvent->fetch($objEvent->id) > 0) {
                        $autoEvent->label = $proj->ref . "-Status-Opp. : " . (int)$oldPercent . "% à " . (int)$newPercent . "%";
                        $autoEvent->note_private = "L'utilisateur " . $user->login . " a modifié la probabilité de " . (int)$oldPercent . "% à " . (int)$newPercent . "%.";
                        // Prevent infinite loop by not triggering the action update again
                        $autoEvent->update($user, 1);
                    }
                }
            } else {
                $res['error'] = !empty($proj->errors) ? $proj->errors : $proj->error;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'updateopporigin') {
    $projectId = GETPOST('projectid', 'int');
    $newOrigin = GETPOST('origin', 'aZ09');
    $res = array('success' => false);

    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $proj->fetch_optionals();
            $proj->array_options['options_opporigin'] = $newOrigin;
            
            if ($proj->insertExtraFields() > 0) {
                // If it worked, we might want to return the translated label of the origin
                $res['success'] = true;
                
                // Fetch the actual label from c_opporigin if it exists (assuming it is a dictionary)
                // Extrafields might be populated via dictionary, we'll return the raw value, the frontend JS can use the selected option text
                $res['new_origin'] = $newOrigin;
            } else {
                $res['error'] = !empty($proj->errors) ? $proj->errors : $proj->error;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'updateopptitle') {
    $projectId = GETPOST('projectid', 'int');
    $newTitle = trim(GETPOST('title'));
    $res = array('success' => false);

    if ($projectId > 0 && !empty($newTitle)) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $oldTitle = $proj->title;
            $proj->title = $newTitle;
            
            if ($proj->update($user) > 0) {
                $res['success'] = true;
                $res['escaped_title'] = dol_escape_htmltag($newTitle);
                
                // Intercept the auto-generated "Projet modifié" Agenda event and overwrite its label
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                
                $sqlFindEvent = "SELECT id FROM " . MAIN_DB_PREFIX . "actioncomm 
                                 WHERE fk_project = " . (int)$proj->id . " 
                                 ORDER BY datec DESC LIMIT 1";
                $resqlFound = $db->query($sqlFindEvent);
                if ($resqlFound && $db->num_rows($resqlFound) > 0) {
                    $objEvent = $db->fetch_object($resqlFound);
                    $autoEvent = new ActionComm($db);
                    if ($autoEvent->fetch($objEvent->id) > 0) {
                        $autoEvent->label = $proj->ref . " - " . $oldTitle . " -> " . $newTitle;
                        $autoEvent->note_private = "L'utilisateur " . $user->login . " a modifié le libellé de l'opportunité : '" . $oldTitle . "' vers '" . $newTitle . "'.";
                        // Prevent infinite loop by not triggering the action update again
                        $autoEvent->update($user, 1);
                    }
                }
            } else {
                $res['error'] = !empty($proj->errors) ? $proj->errors : $proj->error;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'updateoppsocid') {
    $projectId = GETPOST('projectid', 'int');
    $newSocid = GETPOST('socid', 'int');
    $res = array('success' => false);

    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $oldSocid = $proj->socid;
            $oldCompanyName = '';
            if ($oldSocid > 0) {
                $oldSoc = new Societe($db);
                if ($oldSoc->fetch($oldSocid) > 0) {
                    $oldCompanyName = $oldSoc->name;
                }
            }

            $proj->socid = $newSocid;
            if ($proj->update($user) > 0) {
                $res['success'] = true;
                
                $newSoc = new Societe($db);
                $newCompanyName = '';
                $newCompanyUrl = '';
                if ($newSocid > 0 && $newSoc->fetch($newSocid) > 0) {
                    $newCompanyName = $newSoc->name;
                    $newCompanyUrl = $newSoc->getNomUrl(1); // Standard dolibarr company HTML Link
                }
                
                $res['new_socid'] = $newSocid;
                $res['new_company_name'] = $newCompanyName;
                $res['new_company_url'] = $newCompanyUrl;
                
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                $autoEvent = new ActionComm($db);
                $autoEvent->type_code = 'AC_OTH'; 
                $autoEvent->label = "Modification de l'entreprise rattachée";
                $autoEvent->datep = dol_now();
                $autoEvent->datef = dol_now();
                $autoEvent->percentage = 100;
                $autoEvent->userownerid = $user->id;
                $autoEvent->fk_project = $proj->id;
                $autoEvent->socid = $newSocid;
                
                $oldNameStr = empty($oldCompanyName) ? '(Aucune)' : $oldCompanyName;
                $newNameStr = empty($newCompanyName) ? '(Aucune)' : $newCompanyName;
                $autoEvent->note_private = "Mise à jour par ".$user->login." :\n- Tiers : " . $oldNameStr . " -> " . $newNameStr;
                $autoEvent->create($user);
            } else {
                $res['error'] = !empty($proj->errors) ? $proj->errors : $proj->error;
            }
        } else {
            $res['error'] = 'Project not found';
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'updateoppamount') {
    $projectId = GETPOST('projectid', 'int');
    $newAmount = price2num(GETPOST('amount', 'alpha'));
    $res = array('success' => false);

    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $oldAmount = $proj->opp_amount;
            $proj->opp_amount = $newAmount;
            
            if ($proj->update($user) > 0) {
                $res['success'] = true;
                $res['formatted_amount'] = price($newAmount, 1, $langs, 1, -1, -1, $conf->currency);

                // Intercept the auto-generated "Projet modifié" Agenda event and overwrite its label
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                
                $sqlFindEvent = "SELECT id FROM " . MAIN_DB_PREFIX . "actioncomm 
                                 WHERE fk_project = " . (int)$proj->id . " 
                                 ORDER BY datec DESC LIMIT 1";
                $resqlFound = $db->query($sqlFindEvent);
                if ($resqlFound && $db->num_rows($resqlFound) > 0) {
                    $objEvent = $db->fetch_object($resqlFound);
                    $autoEvent = new ActionComm($db);
                    if ($autoEvent->fetch($objEvent->id) > 0) {
                        $autoEvent->label = $proj->ref . "-Montant-Opp. : " . price($oldAmount, 0, $langs, 0, -1, -1, $conf->currency) . " à " . price($newAmount, 0, $langs, 0, -1, -1, $conf->currency);
                        $autoEvent->note_private = "L'utilisateur " . $user->login . " a modifié le montant de " . price($oldAmount, 0, $langs, 0, -1, -1, $conf->currency) . " à " . price($newAmount, 0, $langs, 0, -1, -1, $conf->currency) . ".";
                        // Prevent infinite loop by not triggering the action update again
                        $autoEvent->update($user, 1);
                    }
                }
            } else {
                $res['error'] = !empty($proj->errors) ? $proj->errors : $proj->error;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'updateoppcontact') {
    $projectId = GETPOST('projectid', 'int');
    $firstname = trim(GETPOST('firstname'));
    $lastname  = trim(GETPOST('lastname'));
    $phone     = trim(GETPOST('phone'));
    $email     = trim(GETPOST('email'));
    $website   = trim(GETPOST('website'));
    
    $res = array('success' => false);
    if ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $proj->fetch_optionals();
            
            // Record old values for the history
            $oldFirstname = $proj->array_options['options_reedcrm_firstname'] ?? '';
            $oldLastname  = $proj->array_options['options_reedcrm_lastname'] ?? '';
            $oldPhone     = $proj->array_options['options_projectphone'] ?? '';
            $oldEmail     = $proj->array_options['options_reedcrm_email'] ?? '';
            $oldWebsite   = $proj->array_options['options_reedcrm_website'] ?? '';

            // Assign new values
            $proj->array_options['options_reedcrm_firstname'] = $firstname;
            $proj->array_options['options_reedcrm_lastname']  = $lastname;
            $proj->array_options['options_projectphone']      = $phone;
            $proj->array_options['options_reedcrm_email']     = $email;
            $proj->array_options['options_reedcrm_website']   = $website;
            
            // Update in DB
            $proj->updateExtraField('reedcrm_firstname');
            $proj->updateExtraField('reedcrm_lastname');
            $proj->updateExtraField('projectphone');
            $proj->updateExtraField('reedcrm_website');
            $resUpdateEmail = $proj->updateExtraField('reedcrm_email');
            
            file_put_contents(DOL_DOCUMENT_ROOT.'/custom/reedcrm/debug_update.log', "Finished extrafields, last result: $resUpdateEmail\n", FILE_APPEND);
            
            $res['success']      = true;
            $res['firstname']    = dol_escape_htmltag($firstname);
            $res['lastname']     = dol_escape_htmltag($lastname);
            $res['contactName']  = dol_escape_htmltag(trim($firstname . ' ' . $lastname));
            $res['contactPhone'] = dol_escape_htmltag($phone);
            $res['contactEmail'] = dol_escape_htmltag($email);

            // ActionComm Event (Create new trace)
            $noteChanges = array();
            if (trim($oldFirstname) !== $firstname) $noteChanges[] = "- Prénom : " . (empty($oldFirstname) ? '(vide)' : $oldFirstname) . " -> " . (empty($firstname) ? '(vide)' : $firstname);
            if (trim($oldLastname) !== $lastname)   $noteChanges[] = "- Nom : " . (empty($oldLastname) ? '(vide)' : $oldLastname) . " -> " . (empty($lastname) ? '(vide)' : $lastname);
            if (trim($oldPhone) !== $phone)         $noteChanges[] = "- Téléphone : " . (empty($oldPhone) ? '(vide)' : $oldPhone) . " -> " . (empty($phone) ? '(vide)' : $phone);
            if (trim($oldEmail) !== $email)         $noteChanges[] = "- Email : " . (empty($oldEmail) ? '(vide)' : $oldEmail) . " -> " . (empty($email) ? '(vide)' : $email);
            if (trim($oldWebsite) !== $website)     $noteChanges[] = "- Site Web : " . (empty($oldWebsite) ? '(vide)' : $oldWebsite) . " -> " . (empty($website) ? '(vide)' : $website);
            
            if (!empty($noteChanges)) {
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                $autoEvent = new ActionComm($db);
                $autoEvent->type_code = 'AC_OTH'; 
                $autoEvent->label = "Modification des contacts de l'opportunité";
                $autoEvent->datep = dol_now();
                $autoEvent->datef = dol_now();
                $autoEvent->percentage = 100;
                $autoEvent->userownerid = $user->id;
                $autoEvent->fk_project = $proj->id;
                $autoEvent->socid = $proj->socid;
                $autoEvent->note_private = "Mise à jour par ".$user->login." :\n" . implode("\n", $noteChanges);
                $autoEvent->create($user);
            }
        } else {
            $res['error'] = 'Project not found';
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'add') {
    $numberingModules = [
        'project'      => $conf->global->PROJECT_ADDON,
        'project/task' => $conf->global->PROJECT_TASK_ADDON,
    ];

    list ($refProjectMod, $refTaskMod) = saturne_require_objects_mod($numberingModules);

    $project->ref         = $refProjectMod->getNextValue(null, $project);
    $project->title       = GETPOST('title');
    $project->description = GETPOST('description', 'restricthtml');
    $project->opp_percent = GETPOST('opp_percent','int');
    switch ($project->opp_percent) {
        case $project->opp_percent < 20:
            $project->opp_status = 1;
            break;
        case $project->opp_percent < 40:
            $project->opp_status = 2;
            break;
        case $project->opp_percent < 60:
            $project->opp_status = 3;
            break;
        case $project->opp_percent < 100:
            $project->opp_status = 4;
            break;
        case $project->opp_percent == 100:
            $project->opp_status = 5;
            break;
        default:
            break;
    }

    $project->opp_amount        = price2num(GETPOST('opp_amount', 'int'));
    $project->date_c            = dol_now();
    $project->date_start        = dol_now();
    $project->status            = getDolGlobalInt('REEDCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO') > 0 && $project->opp_percent == 0 ? Project::STATUS_CLOSED : Project::STATUS_VALIDATED;
    $project->usage_opportunity = 1;
    $project->usage_task        = 1;

    $extraFields->setOptionalsFromPost(null, $project);

    $error = 0;
    $projectPhone = GETPOST('options_projectphone', 'alpha');
    if (!empty($projectPhone)) {
        if (!preg_match('/^[+0-9\s.\-()]{2,20}$/', $projectPhone) || strlen($projectPhone) > 20) {
            setEventMessages($langs->trans('ErrorInvalidProjectPhone'), null, 'errors');
            $error++;
        }
    }

    $projectID = 0;
    if ($error == 0) {
        $projectID = $project->create($user);
    }
    
    if ($projectID > 0) {
//        // Category association
//        $categories = GETPOST('categories_project', 'array');
//        if (count($categories) > 0) {
//            $result = $project->setCategories($categories);
//            if ($result < 0) {
//                setEventMessages($project->error, $project->errors, 'errors');
//                $error++;
//            }
//        }

        $pathToProjectDir = $conf->project->multidir_output[$conf->entity] . '/' . $project->ref;
        if (!dol_is_dir($pathToProjectDir)) {
            dol_mkdir($pathToProjectDir);
        }

        // Standard File Upload processing
        $hasFilesToUpload = false;
        if (isset($_FILES['userfile']['name']) && is_array($_FILES['userfile']['name'])) {
            foreach ($_FILES['userfile']['name'] as $fileName) {
                if (!empty($fileName)) {
                    $hasFilesToUpload = true;
                    break;
                }
            }
        }
        
        if ($hasFilesToUpload) {
            $nbFiles = count($_FILES['userfile']['name']);
            for ($i = 0; $i < $nbFiles; $i++) {
                if ($_FILES['userfile']['error'][$i] == 0) {
                    $fileName = dol_sanitizeFileName($_FILES['userfile']['name'][$i]);
                    $fullPath = $pathToProjectDir . '/' . $fileName;
                    
                    if (dol_move_uploaded_file($_FILES['userfile']['tmp_name'][$i], $fullPath, 1, 0, $_FILES['userfile']['error'][$i])) {
                        if (function_exists('vignette')) {
                            vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_MINI, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_MINI, '_mini');
                            vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_SMALL, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_SMALL);
                            vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
                            vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_LARGE, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_LARGE, '_large');
                        }
                    } else {
                        setEventMessages($langs->transnoentities('ErrorFileNotUploaded').' (dol_move_uploaded_file failed)', null, 'errors');
                        $error++;
                    }
                } else if ($_FILES['userfile']['error'][$i] == UPLOAD_ERR_INI_SIZE || $_FILES['userfile']['error'][$i] == UPLOAD_ERR_FORM_SIZE) {
                    setEventMessages('La taille du fichier dépasse la limite autorisée par le serveur (upload_max_filesize).', null, 'errors');
                    $error++;
                } else if ($_FILES['userfile']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                    setEventMessages('Erreur technique lors du téléversement du fichier (Code: '.$_FILES['userfile']['error'][$i].').', null, 'errors');
                    $error++;
                }
            }
        }

        $pathToTmpAudio = $conf->reedcrm->multidir_output[$conf->entity] . '/project/tmp/0/project_audio/';
        $audioList      = dol_dir_list($pathToTmpAudio, 'files');
        if (!empty($audioList)) {
            foreach ($audioList as $audio) {
                if (!dol_is_dir($pathToProjectDir)) {
                    dol_mkdir($pathToProjectDir);
                }

                $fullPath = $pathToProjectDir . '/' . $audio['name'];
                dol_copy($audio['fullname'], $fullPath);
                unlink($audio['fullname']);
            }
        }

        $project->add_contact($user->id, 'PROJECTLEADER', 'internal');

        $lat = GETPOST('latitude');
        $lon = GETPOST('longitude');
        if (!empty(GETPOST('geolocation-error'))) {
            setEventMessage($langs->transnoentities('GeolocationError', GETPOST('geolocation-error')));
        }

        $task->fk_project = $projectID;
        $task->ref        = $refTaskMod->getNextValue(null, $task);
        $task->label      = (!empty($conf->global->REEDCRM_TASK_LABEL_VALUE) ? $conf->global->REEDCRM_TASK_LABEL_VALUE : $langs->trans('CommercialFollowUp')) . ' - ' . $project->title;
        $task->date_c     = dol_now();

        $taskID = $task->create($user);
        if ($taskID > 0) {
            $task->add_contact($user->id, 'TASKEXECUTIVE', 'internal');
            $project->array_options['commtask'] = $taskID;
            $project->updateExtraField('commtask');
        } else {
            setEventMessages($task->error, $task->errors, 'errors');
            $error++;
        }

        // Create a contact if firstname and lastname are provided
        $contactFirstname = trim($project->array_options['options_reedcrm_firstname'] ?? '');
        $contactLastname  = trim($project->array_options['options_reedcrm_lastname'] ?? '');
        if (!empty($contactFirstname) && !empty($contactLastname)) {
            require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
            $contact            = new Contact($db);
            $contact->firstname = $contactFirstname;
            $contact->lastname  = $contactLastname;
            $contact->socid     = $project->socid > 0 ? $project->socid : 0;
            $contact->phone_pro = $project->array_options['options_projectphone'] ?? '';
            $contact->email     = $project->array_options['options_reedcrm_email'] ?? '';
            $contact->url       = $project->array_options['options_reedcrm_website'] ?? '';
            $contact->address   = $geolocation->getAddressFromLatLon($lat, $lon)['display_name'] ?? '';
            $contact->status    = 1;
            $contactID = $contact->create($user);
            if ($contactID > 0) {
                $project->add_contact($contactID, 'PROJECTADDRESS', 'external');

                // Link geolocation to the contact instead of the project
                if (empty(GETPOST('geolocation-error')) && $lat !== '' && $lon !== '') {
                    $geolocation->latitude    = (float)$lat;
                    $geolocation->longitude   = (float)$lon;
                    $geolocation->element_type = $contact->element;
                    $geolocation->fk_element  = $contactID;
                    $geolocation->create($user);
                }
            } else {
                setEventMessages($contact->error, $contact->errors, 'errors');
            }
        } elseif (empty(GETPOST('geolocation-error')) && $lat !== '' && $lon !== '') {
            // No contact: fallback, link geolocation to the project
            $geolocation->latitude    = (float)$lat;
            $geolocation->longitude   = (float)$lon;
            $geolocation->element_type = $project->element;
            $geolocation->fk_element  = $projectID;
            $geolocation->create($user);
        }
    } else {
        $langs->load('errors');
        setEventMessages($project->error, $project->errors, 'errors');
        $error++;
    }

    if (!$error) {
        setEventMessage($langs->transnoentities('QuickCreationFrontendSuccess') . ' : <a href="' . DOL_URL_ROOT . '/projet/card.php?id=' . $projectID . '" target="_blank">'  . $project->ref . '</a>');
        
        if (GETPOST('ajax_submission') == '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect_url' => $_SERVER["PHP_SELF"]]);
            exit;
        }
        
        header('Location: ' . $_SERVER["PHP_SELF"]);
        exit;
    } else {
        $action = '';
    }
}

if ($subaction == 'unlinkFile') {
    $data = json_decode(file_get_contents('php://input'), true);

    $filePath = $data['filepath'];
    $fileName = $data['filename'];
    $fullPath = $filePath . '/' . $fileName;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }

    $sizesArray = ['mini', 'small', 'medium', 'large'];
    foreach($sizesArray as $size) {
        $thumbName = $filePath . '/thumbs/' . saturne_get_thumb_name($fileName, $size);
        if (is_file($thumbName)) {
            unlink($thumbName);
        }
    }
}

/* ADDED_FAR_AI */
if ($subaction == 'readFileAI' && isModEnabled('ai') && getDolGlobalString('AI_API_SERVICE') == 'chatgpt') {

    require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/ai/class/ai.class.php';
    saturne_load_langs(['medias']);

    $data = json_decode(file_get_contents('php://input'), true);
    $result = array();

    // GET AUTH INFO & ENDPOINT
    $activeAI = mb_strtoupper(getDolGlobalString('AI_API_SERVICE'));
    $aiToken = getDolGlobalString('AI_API_'.$activeAI.'_KEY');
    $aiEndpoint = getDolGlobalString('AI_API_'.$activeAI.'_URL');

    if (empty($activeAI) || empty($aiToken) || empty($aiEndpoint)) {
        $result['success'] = false;
        $result['error'] = 'AiConfigError - Token or Endpoint is empty';
    } else {

        // HEADERS
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer '.getDolGlobalString('AI_API_CHATGPT_KEY')
        );

        // ENDPOINT
        $fullEndpoint = $aiEndpoint;
        if (!preg_match('#/$#', $fullEndpoint)) {
            $fullEndpoint .= '/';
        }
        $fullEndpoint .= 'chat/completions';

        // FILE
        $filePath = $data['filepath'];
        $fileName = $data['filename'];
        $fullPath = $filePath . '/' . $fileName;
        $type = pathinfo($fullPath, PATHINFO_EXTENSION);
        $data = file_get_contents($fullPath);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        //
        $instruction = 'Sur cette image, peux tu retrouver les informations suivantes: nom, prénom, téléphone, email ? Si oui, retourne moi ces données sous la forme d\'un json, sinon, décris moi les images en texte';

        $ins = "Sur cette image, si tu peux trouver des informations de contact, retourne moi **uniquement** ce tableau JSON et ne remplis que la partie contact_details:\n";
        $ins .= "{\"type\":\"contact\",\"contact_details\":[{\"nom\":\"\",\"prenom\":\"\",\"email\":\"\",\"telephone\":\"\"}]}\n";
        $ins .= "Sinon, fais une analyse synthétique de l'image et retourne moi **uniquement** ce tableau JSON et ne remplis que la partie content:\n";
        $ins .= "{\"type\":\"read\",\"content\":\"\"}\n";
        $ins .= "** IMPORTANT: ** Retourne moi seulement le JSON";

        // PAYLOAD
        $payload = array(
            'model' => 'gpt-5',
            'messages' => array(
                0 => array('role' => 'user', 'content' => array(
                    0 => array('type' => 'text', 'text' => $ins),
                    1 => array('type' => 'image_url', 'image_url' => array(
                        'url' => $base64
                    )),
                ))
            ),
        );

        // RES
        $res = getURLContent($fullEndpoint, 'POST', json_encode($payload), 1, $headers);
        $aiResponse = json_decode($res['content']);

        if (is_null($aiResponse)) {
            $result['success'] = false;
            $result['error'] = 'AiResponseError - Ai response return NULL';
        } else {

            $aiResponseMessage = $aiResponse->choices[0]->message->content;
            $aiResponseDecoded = json_decode($aiResponseMessage);

            $result['success'] = true;
            $result['type'] = $aiResponseDecoded->type;
            $result['text'] = "**".$langs->transnoentities('AIAnalyseImage', $fileName)."**\n";

            if ($result['type'] == 'contact') {
                $result['contact'] = $aiResponseDecoded->contact_details[0];
                $result['text'] .= $langs->transnoentities('Contact').": ".$aiResponseDecoded->contact_details[0]->nom.' '.$aiResponseDecoded->contact_details[0]->prenom."\n";
                if (!empty($aiResponseDecoded->contact_details[0]->email)) {
                    $result['text'] .= $langs->transnoentities('Email').":".$aiResponseDecoded->contact_details[0]->email."\n";
                }
                if (!empty($aiResponseDecoded->contact_details[0]->telephone)) {
                    $result['text'] .= $langs->transnoentities('Phone').":".$aiResponseDecoded->contact_details[0]->telephone."\n";
                }
            } else if ($result['type'] == 'read') {
                $result['text'] .= $aiResponseDecoded->content;
            }
        }

    }
    echo json_encode($result);
    exit();
}
