<?php
$res = @include '../../main.inc.php';
if (!$res) {
    die("Error: can't include main.inc.php");
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
