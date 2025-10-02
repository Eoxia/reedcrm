<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/easycrm_quickcreation_actions_frontend.tpl.php
 * \ingroup easycrm
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

if ($action == 'add_img') {
    $data = json_decode(file_get_contents('php://input'), true);

    $encodedImage = explode(',', $data['img'])[1];
    $decodedImage = base64_decode($encodedImage);
    $uploadDir    = $conf->easycrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos/';
    if (!dol_is_dir($uploadDir)) {
        dol_mkdir($uploadDir);
    }
    file_put_contents($uploadDir . dol_print_date(dol_now(), 'dayhourlog') . '_img.jpg', $decodedImage);
}

if ($action == 'add_audio') {
    $uploadDir  = $conf->easycrm->multidir_output[$conf->entity] . '/project/tmp/0/project_audio/';
    $uploadFile = $uploadDir . basename($_FILES['audio']['name']);
    if (!dol_is_dir($uploadDir)) {
        dol_mkdir($uploadDir);
    }
    move_uploaded_file($_FILES['audio']['tmp_name'], $uploadFile);
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
    $project->status            = getDolGlobalInt('EASYCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO') > 0 && $project->opp_percent == 0 ? Project::STATUS_CLOSED : Project::STATUS_VALIDATED;
    $project->usage_opportunity = 1;
    $project->usage_task        = 1;

    $extraFields->setOptionalsFromPost(null, $project);

    $projectID = $project->create($user);
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
        $pathToTmpImg  = $conf->easycrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos/';
        $imgList       = dol_dir_list($pathToTmpImg, 'files');
        if (!empty($imgList)) {
            foreach ($imgList as $img) {
                if (!dol_is_dir($pathToProjectDir)) {
                    dol_mkdir($pathToProjectDir);
                }

                $fullPath = $pathToProjectDir . '/' . $img['name'];
                dol_copy($img['fullname'], $fullPath);

                vignette($fullPath, $conf->global->EASYCRM_MEDIA_MAX_WIDTH_MINI, $conf->global->EASYCRM_MEDIA_MAX_HEIGHT_MINI, '_mini');
                vignette($fullPath, $conf->global->EASYCRM_MEDIA_MAX_WIDTH_SMALL, $conf->global->EASYCRM_MEDIA_MAX_HEIGHT_SMALL);
                vignette($fullPath, $conf->global->EASYCRM_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->EASYCRM_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
                vignette($fullPath, $conf->global->EASYCRM_MEDIA_MAX_WIDTH_LARGE, $conf->global->EASYCRM_MEDIA_MAX_HEIGHT_LARGE, '_large');
                unlink($img['fullname']);
            }
        }

        $pathToTmpAudio = $conf->easycrm->multidir_output[$conf->entity] . '/project/tmp/0/project_audio/';
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

        if (empty(GETPOST('geolocation-error'))) {
            $geolocation->latitude = GETPOST('latitude');
            $geolocation->longitude = GETPOST('longitude');
            $geolocation->element_type = $project->element;
            $geolocation->fk_element = $projectID;

            $geolocation->create($user);
        } else {
            setEventMessage($langs->transnoentities('GeolocationError', GETPOST('geolocation-error')));
        }

        $task->fk_project = $projectID;
        $task->ref        = $refTaskMod->getNextValue(null, $task);
        $task->label      = (!empty($conf->global->EASYCRM_TASK_LABEL_VALUE) ? $conf->global->EASYCRM_TASK_LABEL_VALUE : $langs->trans('CommercialFollowUp')) . ' - ' . $project->title;
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
    } else {
        $langs->load('errors');
        setEventMessages($project->error, $project->errors, 'errors');
        $error++;
    }

    if (!$error) {
        setEventMessage($langs->transnoentities('QuickCreationFrontendSuccess') . ' : <a href="' . DOL_URL_ROOT . '/projet/card.php?id=' . $projectID . '">'  . $project->ref . '</a>');
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
