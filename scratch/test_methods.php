<?php
require 'c:/wamp64/www/dolibarr22/dolibarr/htdocs/master.inc.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
$p = new Project($db);
$p->fetch(1);
// Check if it has something like propal amount in an array
var_dump($p->get_element_list('propal', 'propal'));
