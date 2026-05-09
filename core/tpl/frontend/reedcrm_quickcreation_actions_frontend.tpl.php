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

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

// --- Début des actions de la brique média Saturne ---
$saturneModule = GETPOST('module_name', 'alpha');
$saturneSubDir = GETPOST('sub_dir', 'alphanohtml');

if ($action == 'uploadPhoto' && !empty($saturneModule)) {

    $moduleNameLowerCase = dol_strtolower($saturneModule);
    $uploadDir           = !empty($conf->$moduleNameLowerCase->dir_output)
        ? $conf->$moduleNameLowerCase->dir_output
        : $conf->ecm->dir_output . '/' . $moduleNameLowerCase;
    if (!empty($saturneSubDir)) {
        $uploadDir .= '/' . $saturneSubDir;
    }

    if (!dol_is_dir($uploadDir)) {
        dol_mkdir($uploadDir);
    }

    // Validate that every uploaded file is a real image via MIME type
    $uploadedFiles = isset($_FILES['userfile']) ? $_FILES['userfile'] : [];
    $invalidFile   = false;
    if (!empty($uploadedFiles['tmp_name'])) {
        $tmpNames = is_array($uploadedFiles['tmp_name']) ? $uploadedFiles['tmp_name'] : [$uploadedFiles['tmp_name']];
        foreach ($tmpNames as $tmpName) {
            if (empty($tmpName)) {
                continue;
            }
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);
            if (strpos($mimeType, 'image/') !== 0) {
                $invalidFile = true;
                break;
            }
        }
    }

    if ($invalidFile) {
        setEventMessages($langs->trans('ErrorFileNotAnImage'), null, 'errors');
    } else {
        $allowOverwrite = GETPOSTINT('overwrite') ? 1 : 0;
        if (!dol_is_dir($uploadDir)) {
            dol_mkdir($uploadDir);
        }

        $res = dol_add_file_process($uploadDir, $allowOverwrite, 1, 'userfile', '', null, '', 1);

        if ($res > 0) {
            setEventMessages($langs->trans('PhotoWellSent'), null, 'mesgs');
        } else {
            setEventMessages($langs->trans('PhotoNotSent'), null, 'errors');
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
            print saturne_render_media_block($saturneModule, $saturneSubDir);
            exit;
        }
    }
}

if ($action == 'delete_audio' && !empty($saturneModule)) {
    $audioFilename  = GETPOST('filename', 'alpha');
    $audioModLower  = dol_strtolower($saturneModule);

    $uploadDir = !empty($conf->$audioModLower->dir_output)
        ? $conf->$audioModLower->dir_output
        : $conf->ecm->dir_output . '/' . $audioModLower;
    if (!empty($saturneSubDir)) {
        $uploadDir .= '/' . $saturneSubDir;
    }

    $filePath = $uploadDir . '/' . basename($audioFilename);
    if (!empty($audioFilename) && file_exists($filePath) && dol_delete_file($filePath)) {
        setEventMessages($langs->trans('FileDeleted'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('ErrorFileNotDeleted'), null, 'errors');
    }
}

if ($action == 'add_audio' && !empty($saturneModule) && !empty($_FILES['audio']['tmp_name'])) {
    $audioModLower  = dol_strtolower($saturneModule);

    $uploadDir = !empty($conf->$audioModLower->dir_output)
        ? $conf->$audioModLower->dir_output
        : $conf->ecm->dir_output . '/' . $audioModLower;
    if (!empty($saturneSubDir)) {
        $uploadDir .= '/' . $saturneSubDir;
    }

    if (!dol_is_dir($uploadDir)) {
        dol_mkdir($uploadDir);
    }

    $fileName = dol_print_date(dol_now(), 'dayhourlog') . '_audio.wav';
    $destFile = $uploadDir . '/' . $fileName;

    if (move_uploaded_file($_FILES['audio']['tmp_name'], $destFile)) {
        setEventMessages($langs->trans('PhotoWellSent'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('PhotoNotSent'), null, 'errors');
    }
}
// --- Fin des actions de la brique média Saturne ---

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

if ($action == 'search_tiers_ajax') {
    $q = GETPOST('q', 'alpha');
    $res = array('results' => array());
    if (strlen($q) >= 2) {
        $sql = "SELECT rowid, nom FROM " . MAIN_DB_PREFIX . "societe WHERE nom LIKE '%" . $db->escape($q) . "%' AND entity IN (" . getEntity('societe') . ") LIMIT 50";
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $res['results'][] = array('id' => $obj->rowid, 'text' => $obj->nom);
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

if ($action == 'search_contact_ajax') {
    $q         = GETPOST('q', 'alpha');
    $projectId = GETPOST('projectid', 'int');
    $socidDirect = GETPOST('socid', 'int');
    $res       = ['results' => []];

    // Resolve the socid: either passed directly or via projectid
    $targetSocId = 0;
    if ($socidDirect > 0) {
        $targetSocId = $socidDirect;
    } elseif ($projectId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0 && $proj->socid > 0) {
            $targetSocId = (int)$proj->socid;
        }
    }

    // Require minimum 2 chars only when q is provided and socid was resolved via projectid search
    // When called with socid directly (preload mode), q can be empty → returns all contacts
    if ($targetSocId > 0 && ($socidDirect > 0 || strlen($q) >= 2)) {
        $whereQ = '';
        if (strlen($q) >= 1) {
            $whereQ = " AND (firstname LIKE '%" . $db->escape($q) . "%' OR lastname LIKE '%" . $db->escape($q) . "%')";
        }
        $sql   = "SELECT rowid, firstname, lastname FROM " . MAIN_DB_PREFIX . "socpeople WHERE fk_soc = " . $targetSocId . $whereQ . " ORDER BY lastname, firstname LIMIT 100";
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $res['results'][] = ['id' => $obj->rowid, 'text' => trim($obj->firstname . ' ' . $obj->lastname)];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}


if ($action == 'updateoppcontactid') {
    $projectId = GETPOST('projectid', 'int');
    $contactId = GETPOST('contactid', 'int');
    $res = array('success' => false);

    if ($projectId > 0 && $contactId > 0) {
        $proj = new Project($db);
        if ($proj->fetch($projectId) > 0) {
            $resAdd = $proj->add_contact($contactId, 'PROJECTADDRESS', 'external');
            if ($resAdd >= 0) {
                $res['success'] = true;
                
                // Copy the contact details into the project extrafields so the UI updates!
                require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
                $contact = new Contact($db);
                if ($contact->fetch($contactId) > 0) {
                    $proj->fetch_optionals();
                    $proj->array_options['options_reedcrm_firstname'] = $contact->firstname;
                    $proj->array_options['options_reedcrm_lastname']  = $contact->lastname;
                    $proj->array_options['options_projectphone']      = !empty($contact->phone_pro) ? $contact->phone_pro : $contact->phone_perso;
                    $proj->array_options['options_reedcrm_email']     = $contact->email;

                    $proj->updateExtraField('reedcrm_firstname');
                    $proj->updateExtraField('reedcrm_lastname');
                    $proj->updateExtraField('projectphone');
                    $proj->updateExtraField('reedcrm_email');

                    // Return contact data so JS can update DOM without page reload
                    $res['contact'] = [
                        'firstname' => $contact->firstname,
                        'lastname'  => $contact->lastname,
                        'phone'     => !empty($contact->phone_pro) ? $contact->phone_pro : $contact->phone_perso,
                        'email'     => $contact->email,
                    ];
                }

                
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                $autoEvent = new ActionComm($db);
                $autoEvent->type_code = 'AC_OTH'; 
                $autoEvent->label = "Ajout d'un contact au projet";
                $autoEvent->datep = dol_now();
                $autoEvent->datef = dol_now();
                $autoEvent->percentage = 100;
                $autoEvent->fk_project = $projectId;
                $autoEvent->note_private = "Le contact ID $contactId a été ajouté au projet depuis l'application ReedCRM.";
                $autoEvent->userownerid = $user->id;
                $autoEvent->insert($user);
            } else {
                $res['error'] = $proj->error;
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

if ($action == 'get_contact_details') {
    $contactid = GETPOST('contactid', 'int');
    $res = array();
    if ($contactid > 0) {
        require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
        $contact = new Contact($db);
        if ($contact->fetch($contactid) > 0) {
            $res = array(
                'id' => $contact->id,
                'firstname' => $contact->firstname,
                'lastname' => $contact->lastname,
                'phone' => $contact->phone_pro,
                'email' => $contact->email
            );
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
    $project->socid       = GETPOST('socid', 'int');
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
        // Category association
        $categories = GETPOST('categories', 'array:int');
        if (!empty($categories)) {
            $result = $project->setCategories($categories);
            if ($result < 0) {
                setEventMessages($project->error, $project->errors, 'errors');
                $error++;
            }
        }

        $pathToProjectDir = $conf->project->multidir_output[$conf->entity] . '/' . $project->ref;
        if (!dol_is_dir($pathToProjectDir)) {
            dol_mkdir($pathToProjectDir);
        }

        // Saturne Media Module Migration
        require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
        $uploadContext = 'reedcrm_quickcreation_' . $user->id;
        $subDir = 'tmp/' . saturne_get_upload_token($uploadContext);

        $uploadedFiles = saturne_get_media_files('project', $subDir);
        foreach ($uploadedFiles as $file) {
            $fullPath = $pathToProjectDir . '/' . $file['name'];
            dol_copy($file['fullname'], $fullPath);
            
            // Thumbnail generation for images
            if ($file['type'] === 'image' && function_exists('vignette')) {
                vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_MINI ?? 120, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_MINI ?? 120, '_mini');
                vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_SMALL ?? 240, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_SMALL ?? 240);
                vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_MEDIUM ?? 500, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_MEDIUM ?? 500, '_medium');
                vignette($fullPath, $conf->global->REEDCRM_MEDIA_MAX_WIDTH_LARGE ?? 800, $conf->global->REEDCRM_MEDIA_MAX_HEIGHT_LARGE ?? 800, '_large');
            }
        }
        saturne_invalidate_upload_token($uploadContext, 'project', 'tmp');

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

        $contactid = GETPOST('contactid', 'int');
        if ($contactid > 0) {
            $project->add_contact($contactid, 'PROJECTADDRESS', 'external');

            // Link geolocation to this existing contact instead of the project
            if (empty(GETPOST('geolocation-error')) && $lat !== '' && $lon !== '') {
                require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
                $existingContact = new Contact($db);
                if ($existingContact->fetch($contactid) > 0) {
                    $geolocation->latitude    = (float)$lat;
                    $geolocation->longitude   = (float)$lon;
                    $geolocation->element_type = $existingContact->element;
                    $geolocation->fk_element  = $contactid;
                    $geolocation->create($user);
                }
            }
        } else {
            // Create a contact if firstname and lastname are provided
            $contactFirstname = trim($project->array_options['options_reedcrm_firstname'] ?? '');
            $contactLastname  = trim($project->array_options['options_reedcrm_lastname'] ?? '');
            if (empty($contactFirstname) && !empty($contactLastname)) {
                $contactFirstname = 'Address';
                $contactLastname  = $project->ref;
            }
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
