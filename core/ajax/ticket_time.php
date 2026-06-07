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

    $db->begin();

    // Insert time directly into element_time using native ticket elementtype
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "element_time (";
    $sql .= " fk_element, elementtype, element_date, element_datehour, element_date_withhour,";
    $sql .= " element_duration, fk_user, datec, note";
    $sql .= ") VALUES (";
    $sql .= " " . (int)$ticket->id . ",";
    $sql .= " 'ticket',";
    $sql .= " '" . $db->idate(dol_now()) . "',";
    $sql .= " '" . $db->idate(dol_now()) . "',";
    $sql .= " 1,"; // element_date_withhour
    $sql .= " " . (int)($minutes * 60) . ","; // duration in seconds
    $sql .= " " . (int)$user->id . ",";
    $sql .= " '" . $db->idate(dol_now()) . "',";
    $sql .= " '" . $db->escape($note) . "'";
    $sql .= ")";

    $resql = $db->query($sql);
    if ($resql) {
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
        
        $db->commit();
        echo json_encode(['success' => true]);
    } else {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du temps: ' . $db->lasterror()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides. Veuillez fournir un temps > 0.']);
}
