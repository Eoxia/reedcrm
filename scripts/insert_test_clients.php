<?php
/**
 * Script de génération de 100 clients de test (Client 10 → Client 109)
 * Usage : appeler depuis CLI ou navigateur en étant connecté admin
 * php insert_test_clients.php (depuis le dossier dolibarr/htdocs)
 */

define('NOSESSION', '1'); // script CLI friendly

// Load Dolibarr environment
if (file_exists(__DIR__ . '/../../main.inc.php')) {
    require_once __DIR__ . '/../../main.inc.php';
} elseif (file_exists(__DIR__ . '/../../../main.inc.php')) {
    require_once __DIR__ . '/../../../main.inc.php';
} else {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

global $db, $user, $conf;

// Authenticate as admin user (rowid=1)
$user->fetch(1);
$user->getrights();

$entity  = 1;
$created = 0;
$skipped = 0;
$errors  = [];

for ($i = 10; $i <= 109; $i++) {
    $name = 'Client ' . $i;

    // Check if already exists
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "societe WHERE nom = '" . $db->escape($name) . "' AND entity = " . (int)$entity;
    $res = $db->query($sql);
    if ($res && $db->fetch_object($res)) {
        $skipped++;
        continue;
    }

    $soc = new Societe($db);
    $soc->nom           = $name;
    $soc->client        = 1;   // Client
    $soc->fournisseur   = 0;
    $soc->status        = 1;   // Actif
    $soc->entity        = $entity;
    $soc->country_id    = 74;  // France
    $soc->code_client   = 'auto';

    $ret = $soc->create($user);
    if ($ret > 0) {
        $created++;
    } else {
        $errors[] = $name . ': ' . implode(', ', $soc->errors ?: [$soc->error]);
    }
}

echo "=== Résultat ===\n";
echo "Créés   : $created\n";
echo "Ignorés : $skipped (déjà existants)\n";
echo "Erreurs : " . count($errors) . "\n";
if ($errors) {
    echo implode("\n", $errors) . "\n";
}
