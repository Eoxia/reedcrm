<?php
require "../../main.inc.php";
require_once DOL_DOCUMENT_ROOT."/ticket/class/ticket.class.php";
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
