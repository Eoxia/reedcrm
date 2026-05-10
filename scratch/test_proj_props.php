<?php
require 'c:/wamp64/www/dolibarr22/dolibarr/htdocs/master.inc.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
$p = new Project($db);
$p->fetch(1); // Assuming project ID 1 exists
print_r(array_keys(get_object_vars($p)));
