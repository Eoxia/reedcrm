<?php
require 'c:/wamp64/www/dolibarr22/dolibarr/htdocs/master.inc.php';
$dir = $conf->projet->dir_output . '/tmp/test_dir_123';
echo "Trying to create $dir\n";
$res = dol_mkdir($dir);
echo "Result: $res\n";
echo dol_is_dir($dir) ? 'EXISTS' : 'NOPE';
echo "\n";
