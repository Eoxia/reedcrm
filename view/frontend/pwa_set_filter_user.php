<?php
/**
 * \file    view/frontend/pwa_set_filter_user.php
 * \ingroup reedcrm
 * \brief   Endpoint to save the PWA person filter preference and redirect back
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

global $conf, $db, $user;

$filterUserId = GETPOST('pwa_filter_user_id', 'int');
$backtopage   = GETPOST('backtopage', 'alpha');

// Save (or clear) the user preference in llx_user_param
$sql = "DELETE FROM " . MAIN_DB_PREFIX . "user_param WHERE fk_user = " . (int) $user->id . " AND param = 'REEDCRM_PWA_FILTER_USER_ID'";
$db->query($sql);

if ($filterUserId > 0) {
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "user_param (fk_user, param, value)"
         . " VALUES (" . (int) $user->id . ", 'REEDCRM_PWA_FILTER_USER_ID', '" . $db->escape((string) $filterUserId) . "')";
    $db->query($sql);
}

$db->close();

// Redirect back to origin page
if (empty($backtopage)) {
    $backtopage = dol_buildpath('/custom/reedcrm/view/frontend/pwa_home.php?source=pwa', 1);
}

header('Location: ' . $backtopage);
exit;
