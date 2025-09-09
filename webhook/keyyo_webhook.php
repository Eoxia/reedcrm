<?php
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (file_exists(__DIR__ . '/../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../saturne/saturne.main.inc.php';
} elseif (file_exists(__DIR__ . '/../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}
global $db;

require_once __DIR__ . '/../lib/easycrm_function.lib.php';

date_default_timezone_set('Europe/Paris');

function log_to_file($msg) {
    $line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
    file_put_contents(__DIR__.'/keyyo_webhook.log', $line, FILE_APPEND);
}

$expected = $conf->global->EASY_CRM_KEYYO_EXPECTED_TOKEN ?? '';
$got = $_GET['token'] ?? '';
if ($expected && $got !== $expected) {
    log_to_file('Forbidden: bad token: '.$got);
    http_response_code(403);
    exit;
}

$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $h = str_replace('_', '-', substr($k, 5));
        $headers[$h] = $v;
    }
}

$raw = file_get_contents('php://input');
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';

$payload = [
    'when'      => date('c'),
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
    'query'     => $_GET,
    'ctype'     => $ctype,
    'headers'   => $headers,
    'raw'       => $raw,
    'post'      => $_POST,
];

log_to_file('REQUEST: '.json_encode($payload, JSON_UNESCAPED_UNICODE));

// Récupération des numéros d'appel
$caller = $_GET['caller'] ?? '';
$callee = $_GET['callee'] ?? '';

// Traitement JSON si présent
$data = json_decode($raw, true);
if (json_last_error() === JSON_ERROR_NONE) {
    $caller = $data['caller'] ?? $caller;
    $callee = $data['callee'] ?? $callee;
    log_to_file('JSON OK: caller='.$caller.' callee='.$callee);
} else {
    if (!empty($_POST)) {
        $caller = $_POST['caller'] ?? $caller;
        $callee = $_POST['callee'] ?? $callee;
        log_to_file('FORM DATA: '.json_encode($_POST, JSON_UNESCAPED_UNICODE));
    } else {
        log_to_file('No JSON / No POST fields');
    }
}

// Identifier l'utilisateur et le contact
$result = get_and_show_contact($caller, $callee);

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';

require_once __DIR__ . '/../lib/easycrm_function.lib.php';

