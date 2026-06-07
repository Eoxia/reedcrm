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
            
            $titleMaxLength = getDolGlobalInt('REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', 200);
            $clean_note_for_label = trim(preg_replace('/\s+/', ' ', $note));
            $actioncomm->label = !empty($clean_note_for_label) ? dol_trunc($clean_note_for_label, $titleMaxLength) : 'Temps consigné (' . $minutes . ' min)';
            
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
            
            require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
            $dateTs   = dol_now();
            $dateStr  = dol_print_date($dateTs, '%d/%m/%y %H:%M');
            $userStr  = $user->login;
            $noteStr  = dol_trunc(strip_tags($note), 100);
            $dureeStr = convertSecondToTime($minutes * 60, 'allhourmin');
            
            $initial = strtoupper(substr($userStr, 0, 1));
            $colorHash = substr(md5($userStr), 0, 6);
            $userHtml = '<span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background-color: #'.$colorHash.'; color: white; font-size: 0.7em; font-weight: bold; margin: 0 4px;" title="'.dol_escape_htmltag($userStr).'">'.$initial.'</span>';

            $lineStr = '<div style="display: flex; align-items: center; width: 100%;">';
            $lineStr .= '<span style="font-size: 0.75em; color: #a0aec0; margin-right: 4px; white-space: nowrap;">' . dol_escape_htmltag($dateStr) . '</span>';
            $lineStr .= $userHtml;
            $lineStr .= '<span style="margin: 0 4px; white-space: nowrap; font-size: 0.85em;">| ' . dol_escape_htmltag($dureeStr) . '</span>';
            if (!empty($noteStr)) {
                $lineStr .= '<span style="margin: 0 4px; white-space: nowrap; font-size: 0.85em;">|</span><span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.85em; flex-grow: 1;" title="' . dol_escape_htmltag($noteStr) . '">' . dol_escape_htmltag($noteStr) . '</span>';
            }
            $lineStr .= '</div>';

            $htmlLine = '<div id="reedcrm-ticket-last-time" style="flex-basis: 100%; font-weight: normal; font-size: 0.85em; color: #718096; padding-left: 4px; margin-top: 2px; max-width: 260px; overflow: hidden;">' . $lineStr . '</div>';
            
            echo json_encode(['success' => true, 'new_line_html' => $htmlLine]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du temps: ' . $task->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $db->lasterror()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides. Veuillez fournir un temps > 0.']);
}
