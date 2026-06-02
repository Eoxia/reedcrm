<?php
if (file_exists(__DIR__ . '/../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists(__DIR__ . '/../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

global $db, $langs, $user;

header('Content-Type: application/json');

if (!$user->hasRight('expedition', 'creer')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$id = GETPOSTINT('id');
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$expedition = new Expedition($db);
if ($expedition->fetch($id) <= 0) {
    echo json_encode(['success' => false, 'error' => 'Expedition not found']);
    exit;
}

// Update billed status
$expedition->setBilled();

// Create ActionComm
$langs->load('commercial');
$actioncomm                  = new ActionComm($db);
$actioncomm->type_code       = 'AC_OTH';
$actioncomm->label           = 'Bon d\'expédition marqué comme facturé (contrôle OK)';
$actioncomm->datep           = dol_now();
$actioncomm->datef           = dol_now();
$actioncomm->percent         = 100;
$actioncomm->userownerid     = $user->id;
$actioncomm->fk_user_author  = $user->id;
$actioncomm->fk_element      = $expedition->id;
$actioncomm->elementtype     = 'shipping';
$actioncomm->fk_soc          = $expedition->socid;

$res = $actioncomm->create($user);

echo json_encode(['success' => true]);
exit;
