<?php
require "../../main.inc.php";
require_once DOL_DOCUMENT_ROOT."/ticket/class/ticket.class.php";
global $hookmanager, $conf;
$hookmanager->initHooks(array("ticketcard"));
echo "<pre>";
echo "Modules with hooks active:\n";
print_r($conf->modules_parts["hooks"]);
echo "\nLoaded contexts:\n";
print_r($hookmanager->contextarray);
echo "\nLoaded modules for ticketcard:\n";
if (isset($hookmanager->hooks["ticketcard"])) {
    print_r(array_keys($hookmanager->hooks["ticketcard"]));
} else {
    echo "None\n";
}
echo "\nExecuting formObjectOptions:\n";
$object = new Ticket($db);
$object->fetch(1); // Fetch any ticket, or just fake it
$action = "view";
$parameters = array();
$reshook = $hookmanager->executeHooks("formObjectOptions", $parameters, $object, $action);
echo "Reshook: " . $reshook . "\n";
echo "resPrint: " . htmlentities($hookmanager->resPrint) . "\n";
echo "</pre>";
