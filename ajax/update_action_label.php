<?php
// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

header('Content-Type: application/json');

$actionid = GETPOST('actionid', 'int');
$label    = GETPOST('label', 'restricthtml');

dol_syslog("ReedCRM update_action_label: start actionid=" . $actionid . " label=" . $label);

if (empty($actionid)) {
    dol_syslog("ReedCRM update_action_label: no action ID");
    echo json_encode(array('success' => false, 'error' => 'No action ID'));
    exit;
}

$actioncomm = new ActionComm($db);
if ($actioncomm->fetch($actionid) > 0) {
    if ($actioncomm->label !== $label) {
        $actioncomm->oldcopy = clone $actioncomm;
        $actioncomm->label = $label;
        $res = $actioncomm->update($user);
        dol_syslog("ReedCRM update_action_label: updated res=" . $res . " error=" . $actioncomm->error);
        if ($res > 0) {
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'error' => $actioncomm->error));
        }
    } else {
        dol_syslog("ReedCRM update_action_label: identical label");
        echo json_encode(array('success' => true));
    }
} else {
    dol_syslog("ReedCRM update_action_label: action not found");
    echo json_encode(array('success' => false, 'error' => 'Action not found'));
}
