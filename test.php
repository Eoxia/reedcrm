<?php
require_once "../../main.inc.php";
global $db;
$res = $db->query("DESCRIBE llx_expeditiondet");
while($row = $db->fetch_object($res)) {
    echo $row->Field . " | ";
}
echo "\n\n";
$res2 = $db->query("DESCRIBE llx_commandedet");
while($row2 = $db->fetch_object($res2)) {
    echo $row2->Field . " | ";
}
?>
