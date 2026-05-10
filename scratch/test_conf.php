<?php
require 'c:/wamp64/www/dolibarr22/dolibarr/htdocs/master.inc.php';
echo "PROJECT: " . (isset($conf->project->dir_output) ? $conf->project->dir_output : 'NOT_FOUND') . "\n";
echo "PROJET: " . (isset($conf->projet->dir_output) ? $conf->projet->dir_output : 'NOT_FOUND') . "\n";
