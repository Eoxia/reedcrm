<?php
require 'c:/wamp64/www/dolibarr22/dolibarr/htdocs/master.inc.php';
$logFile = dol_os_getenv('DOL_DATA_ROOT') . '/dolibarr.log';
if (empty(dol_os_getenv('DOL_DATA_ROOT'))) {
    $logFile = $conf->syslog->dir_output . '/dolibarr.log';
}
echo "Log file: " . $logFile . "\n";
file_put_contents('c:/wamp64/www/dolibarr22/dolibarr/htdocs/custom/reedcrm/test_log_path.txt', $logFile);
