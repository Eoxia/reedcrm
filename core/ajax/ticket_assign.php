<?php
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
header('Content-Type: application/json');

$action = GETPOST('action', 'alpha');
$ticket_id = GETPOSTINT('ticket_id');
$user_assign = GETPOSTINT('user_assign');

if ($action == 'save_assign' && $ticket_id > 0) {
    require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
    $ticket = new Ticket($db);
    if ($ticket->fetch($ticket_id) > 0) {
        $res = $ticket->assignUser($user, $user_assign);
        if ($res >= 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $ticket->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Ticket not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Bad request']);
}
