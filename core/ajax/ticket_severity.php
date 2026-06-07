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
$severity_code = GETPOST('severity_code', 'alpha');

if ($action == 'save_severity' && $ticket_id > 0) {
    $sql = "UPDATE " . MAIN_DB_PREFIX . "ticket SET severity_code = '" . $db->escape($severity_code) . "' WHERE rowid = " . (int)$ticket_id;
    $resql = $db->query($sql);
    
    if ($resql) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $db->lasterror()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Bad request']);
}
