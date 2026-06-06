<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
if (file_exists('../../reedcrm.main.inc.php')) {
    require_once '../../reedcrm.main.inc.php';
} elseif (file_exists('../../../reedcrm.main.inc.php')) {
    require_once '../../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

header('Content-Type: application/json');

$action = GETPOST('action', 'alpha');
$ticket_id = GETPOSTINT('ticket_id');
$note = GETPOST('note', 'restricthtml');
$minutes = GETPOSTINT('minutes');

// Basic checks
if (empty($user->id)) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($action === 'save_time' && $ticket_id > 0 && $minutes > 0) {
    $ticket = new Ticket($db);
    $res = $ticket->fetch($ticket_id);
    if ($res <= 0) {
        echo json_encode(['success' => false, 'error' => 'Ticket not found']);
        exit;
    }

    if (empty($ticket->fk_project)) {
        echo json_encode(['success' => false, 'error' => 'Le ticket n\'est lié à aucun projet. Veuillez lier un projet au ticket pour consigner du temps.']);
        exit;
    }

    $prefix = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps');
    $suffix_type = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref');
    
    $project = new Project($db);
    $project->fetch($ticket->fk_project);
    
    $suffix_str = '';
    if ($suffix_type === 'ticket_ref') {
        $suffix_str = ' ' . $ticket->ref;
    } elseif ($suffix_type === 'project_ref') {
        $suffix_str = ' ' . $project->ref;
    } elseif ($suffix_type === 'project_label') {
        $suffix_str = ' ' . $project->title;
    }
    
    $expected_label = trim($prefix . $suffix_str);

    // Find the task in this project that matches the expected label
    $sql = "SELECT t.rowid FROM " . MAIN_DB_PREFIX . "projet_task as t";
    $sql .= " WHERE t.fk_projet = " . (int)$ticket->fk_project;
    $sql .= " AND t.label = '" . $db->escape($expected_label) . "'";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        
        $task = new Task($db);
        if ($obj) {
            $resTask = $task->fetch($obj->rowid);
            if ($resTask <= 0) {
                echo json_encode(['success' => false, 'error' => 'Erreur lors du chargement de la tâche: ' . $task->error]);
                exit;
            }
        } else {
            // Task not found, create it automatically
            $task->fk_project = $ticket->fk_project;
            $task->ref = $ticket->ref;
            $task->label = $expected_label;
            $task->description = 'Tâche générée automatiquement pour le ticket ' . $ticket->ref;
            $task->date_c = dol_now();
            $task->date_start = dol_now();
            $task->date_end = dol_now();
            $task->progress = 0;
            $task->status = 1; // Open
            
            $resCreate = $task->create($user);
            if ($resCreate <= 0) {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la création de la tâche: ' . $task->error]);
                exit;
            }
        }
        
        // Add time spent
        $task->timespent_duration = $minutes * 60;
        $task->timespent_date = dol_now();
        $task->timespent_datehour = dol_now();
        $task->timespent_fk_user = $user->id;
        $task->timespent_note = $note;
        $resTime = $task->addTimeSpent($user);
        
        if ($resTime > 0) {
            require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
            $actioncomm = new ActionComm($db);
            $actioncomm->type_code = 'AC_OTH_AUTO';
            $actioncomm->code = 'TICKET_TIMESPENT';
            $actioncomm->socid = $ticket->socid;
            
            $clean_note_for_label = trim(preg_replace('/\s+/', ' ', $note));
            $actioncomm->label = !empty($clean_note_for_label) ? dol_trunc($clean_note_for_label, 100) : 'Temps consigné (' . $minutes . ' min)';
            
            $desc = 'Temps : ' . $minutes . ' min';
            if (!empty($note)) {
                $desc .= '<br>Commentaire :<br>' . nl2br(dol_escape_htmltag($note));
            }
            $actioncomm->note_private = $desc;
            
            $actioncomm->userassigned = array($user->id => array('id' => $user->id, 'transparency' => 0));
            $actioncomm->userownerid = $user->id;
            $actioncomm->datep = dol_now();
            $actioncomm->percentage = 100;
            $actioncomm->elementtype = 'ticket';
            $actioncomm->fk_element = $ticket->id;
            $actioncomm->create($user);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du temps: ' . $task->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $db->lasterror()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides. Veuillez fournir un temps > 0.']);
}
