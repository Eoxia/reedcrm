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
        $sqlStatus = "SELECT rowid, code FROM " . MAIN_DB_PREFIX . "c_lead_status WHERE code = '" . $db->escape($statusStr) . "'";
        $resStatus = $db->query($sqlStatus);
        if ($resStatus && $db->num_rows($resStatus) > 0) {
            $objStatus = $db->fetch_object($resStatus);
            $object->fk_opp_status = $objStatus->rowid;
            $object->opp_status = $objStatus->rowid;
            
            if ($objStatus->code === 'WON') {
                $object->opp_percent = 100;
                $object->usage_opportunity = 0; // Uncheck "Suivre une opportunité"
                
                // Map the new fields
                if (!empty($end_date)) {
                    $object->date_end = dol_stringtotime($end_date);
                }
                if ($budget !== '') {
                    $object->budget_amount = (float)$budget;
                }
            } elseif ($objStatus->code === 'LOST') {
                $object->opp_percent = 0;
            }
            // Native update of the opportunity status
            $object->update($user);
        }

        // 2. Update Extrafield (opprefusal)
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($db);
        $extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
        $object->fetch_optionals();
        $object->array_options['options_opprefusal'] = $reason;
        $object->insertExtraFields('PROJET_CUSTOM_OPTIONS'); // Use generic insert
        
        // Custom explicit update just in case native method name changes
        $sqlExtra = "UPDATE " . MAIN_DB_PREFIX . "projet_extrafields SET opprefusal = '" . $db->escape($reason) . "' WHERE fk_object = " . ((int)$id);
        $db->query($sqlExtra);

        // 3. Trigger native closure ONLY IF LOST
        // Draft projects (status 0) cannot be closed directly in Dolibarr core. We must validate them first.
        if ((int)$object->statut === 0) {
            $object->setValid($user);
        }
        
        if ($objStatus->code !== 'WON') {
            // This will natively spawn the 'actioncomm' trigger "Projet ... fermé"
            $resClose = $object->setClose($user);
        }
        
        // 4. Update the generated actioncomm label to match the strictly requested format
        $newLabel = $object->ref . ($objStatus->code === 'WON' ? " - Gagné" : " - Clôturé");
        if (!empty($comment)) {
            $newLabel .= " - " . $comment;
        }

        $eventUpdated = false;
        if ($objStatus->code !== 'WON') {
            // We called setClose(), let's try to find its generated event
            $sqlEvent = "SELECT id FROM " . MAIN_DB_PREFIX . "actioncomm WHERE fk_project = " . ((int)$id) . " ORDER BY id DESC LIMIT 1";
            $resEvent = $db->query($sqlEvent);
            if ($resEvent && $db->num_rows($resEvent) > 0) {
                $objEvent = $db->fetch_object($resEvent);
                $sqlUpdateEvent = "UPDATE " . MAIN_DB_PREFIX . "actioncomm SET label = '" . $db->escape($newLabel) . "' WHERE id = " . ((int)$objEvent->id);
                $db->query($sqlUpdateEvent);
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
            $rescreate = $actioncomm->create($user);
            
            if ($rescreate > 0) {
                // Manually tag it as 'project_closed' so the summary widget can find it
                $sqlExtraEvent = "INSERT INTO " . MAIN_DB_PREFIX . "actioncomm_extrafields (fk_object, reedcrm_status_object) VALUES (" . (int)$rescreate . ", 'project_closed') ON DUPLICATE KEY UPDATE reedcrm_status_object = 'project_closed'";
                $db->query($sqlExtraEvent);
            }
        }

        $db->commit();
        echo json_encode(['success' => true]);
        exit;
    }
}

$db->rollback();
echo json_encode(['error' => 'Unsupported type or save failed']);
exit;
