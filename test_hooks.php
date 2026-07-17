<?php
// Load ReedCRM environment
if (file_exists('reedcrm.main.inc.php')) {
    require_once __DIR__ . '/reedcrm.main.inc.php';
} elseif (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}
dol_include_once('/ticket/class/ticket.class.php');
global $hookmanager, $conf;
$hookmanager->initHooks(array("ticketcard"));
echo "<pre>";
echo "Executing formObjectOptions:\n";
$object = new Ticket($db);
$object->fetch(1); // Fetch any ticket, or just fake it
$action = "view";
$parameters = array("context" => "ticketcard");
ob_start();
$reshook = $hookmanager->executeHooks("formObjectOptions", $parameters, $object, $action);
$output = ob_get_clean();
echo "Reshook: " . $reshook . "\n";
echo "resPrint: " . htmlentities($hookmanager->resPrint) . "\n";
echo "Direct Output: " . htmlentities($output) . "\n";
echo "</pre>";
