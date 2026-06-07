<?php
$moduleRoot = dirname(__DIR__);
$outputFile = $moduleRoot . '/js/reedcrm.min.js';

$files = [];
$files[] = $moduleRoot . '/js/reedcrm.js';

$modulesDir = $moduleRoot . '/js/modules';
$dirFiles = glob($modulesDir . '/*.js');
if ($dirFiles) {
    sort($dirFiles); // alphabetical sorting
    $allFiles = array_merge($files, $dirFiles);
} else {
    $allFiles = $files;
}

$mergedContent = '';
foreach ($allFiles as $f) {
    $mergedContent .= "/* --- Source: " . basename($f) . " --- */\n";
    $mergedContent .= file_get_contents($f) . "\n";
}

if (file_put_contents($outputFile, $mergedContent) !== false) {
    echo "Rebuilt js/reedcrm.min.js successfully (" . count($allFiles) . " files concatenated).\n";
} else {
    echo "Failed to write to $outputFile\n";
}
