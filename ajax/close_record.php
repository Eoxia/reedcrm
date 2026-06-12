<?php
/**
 * AJAX endpoint to securely close an opportunity (Project) 
 * and append a custom comment to the native actioncomm event.
 */

// define('NOCSRFCHECK', 1); // We now pass the token natively

require '../../../main.inc.php';

header('Content-Type: application/json; charset=utf-8');
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

$id = GETPOST('id', 'int');
$type = GETPOST('type', 'alpha'); // 'projet' or 'propal'
$statusStr = GETPOST('status', 'alpha'); // 'WON' or 'LOST'
$reason = GETPOST('reason', 'alpha'); // 'ToSignElsewhere', etc.
$comment = GETPOST('comment', 'alpha'); // Custom typed comment
$end_date = GETPOST('end_date', 'alpha'); // End date for WON
$budget = GETPOST('budget', 'int'); // Budget for WON
$action = GETPOST('action', 'alpha'); // Action type ('reopen' or empty/close)

// Protection
if (empty($id) || empty($user->rights->projet->creer)) {
    echo json_encode(['error' => 'Permission denied or invalid ID']);
    exit;
}

$db->begin();

if ($type === 'projet' || $type === 'project') {
    $object = new Project($db);
    if ($object->fetch($id) > 0) {
        
        if ($action === 'reopen') {
            // Drop the old closure events so the timeline stays clean
            $sqlDel = "SELECT a.id FROM " . MAIN_DB_PREFIX . "actioncomm as a ";
            $sqlDel .= "INNER JOIN " . MAIN_DB_PREFIX . "actioncomm_extrafields as ae ON a.id = ae.fk_object ";
            $sqlDel .= "WHERE a.fk_project = " . ((int)$id) . " AND ae.reedcrm_status_object = 'project_closed'";
            $resDel = $db->query($sqlDel);
            if ($resDel) {
                $ac = new ActionComm($db);
                while ($objDel = $db->fetch_object($resDel)) {
                    if ($ac->fetch($objDel->id) > 0) {
                        $ac->delete($user);
                    }
                }
            }
            
            if (isset($object->usage_opportunity) && $object->usage_opportunity == 0) {
                $object->usage_opportunity = 1;
                $object->opp_percent = NULL; // Reset probability
                $object->update($user);
            }
            
            if ((int)$object->statut >= 2) {
                $object->setValid($user);
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
            exit;
        }

        // 1. Get the correct opp_status ID for WON/LOST
        $oppStatusId = dol_getIdFromCode($db, $statusStr, 'c_lead_status', 'code', 'rowid');
        if ($oppStatusId > 0) {
            $object->fk_opp_status = $oppStatusId;
            $object->opp_status    = $oppStatusId;

            if ($statusStr === 'WON') {
                $object->opp_percent = 100;
                $object->usage_opportunity = 0; // Uncheck "Suivre une opportunité"

                // Map the new fields
                if (!empty($end_date)) {
                    $object->date_end = dol_stringtotime($end_date);
                }
                if ($budget !== '') {
                    $object->budget_amount = (float)$budget;
                }
            } elseif ($statusStr === 'LOST') {
                $object->opp_percent = 0;
            }
            // Native update of the opportunity status
            $object->update($user);
        }

        // 2. Update Extrafield (opprefusal) through the native extrafields API
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($db);
        $extrafields->fetch_name_optionals_label($object->table_element);
        $object->fetch_optionals();
        $object->array_options['options_opprefusal'] = $reason;
        $object->insertExtraFields('PROJET_CUSTOM_OPTIONS');

        // 3. Trigger native closure ONLY IF LOST
        // Draft projects (status 0) cannot be closed directly in Dolibarr core. We must validate them first.
        if ((int)$object->statut === 0) {
            $object->setValid($user);
        }
        
        if ($statusStr !== 'WON') {
            // This will natively spawn the 'actioncomm' trigger "Projet ... fermé"
            $resClose = $object->setClose($user);
        }
        
        // 4. Build the actioncomm label from the dictionary refusal reason (not the free text)
        $reasonLabel = '';
        if (!empty($reason)) {
            $langs->load('reedcrm@reedcrm');
            $reasonRef   = getDictionaryValue('c_refusal_reason', 'ref', (int) $reason);
            $reasonLabel = $langs->trans($reasonRef);
            if (empty($reasonRef) || $reasonLabel == $reasonRef) {
                $reasonLabel = getDictionaryValue('c_refusal_reason', 'label', (int) $reason);
            }
        }

        $newLabel = $object->ref . ($statusStr === 'WON' ? " - Gagné" : " - Clôturé");
        if ($statusStr !== 'WON' && !empty($reasonLabel)) {
            $newLabel .= " - " . $reasonLabel;
        }

        $eventUpdated = false;
        if ($statusStr !== 'WON') {
            // setClose() spawned an agenda event: fetch the latest one for this project via the ORM
            $actionStatic = new ActionComm($db);
            $events       = $actionStatic->getActions(0, $id, 'project', '', 'a.id', 'DESC', 1);
            if (is_array($events) && count($events) > 0) {
                $event        = array_shift($events);
                $event->label = $newLabel;
                if (!empty($comment)) {
                    $event->note_private = $comment; // handwritten reason goes to the description
                }
                $event->update($user);
                $eventUpdated = true;
            }
        }
        
        if (!$eventUpdated) {
            // Failsafe or WON case where no event generated natively: manually create one
            $actioncomm = new ActionComm($db);
            $actioncomm->type_code = 'AC_OTH_AUTO';
            $actioncomm->label = $newLabel;
            $actioncomm->fk_project = $id;
            $actioncomm->datep = dol_now();
            $actioncomm->userownerid = $user->id;
            if (!empty($comment)) {
                $actioncomm->note_private = $comment;
            }
            // Tag it as 'project_closed' so the summary widget can find it (create() persists extrafields)
            $actioncomm->array_options['options_reedcrm_status_object'] = 'project_closed';
            $actioncomm->create($user);
        }

        $db->commit();
        echo json_encode(['success' => true]);
        exit;
    }
}

$db->rollback();
echo json_encode(['error' => 'Unsupported type or save failed']);
exit;
