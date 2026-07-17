<?php

/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    admin/App-ReedCRM.php
 * \ingroup reedcrm
 * \brief   Progressive web apps configuration page for ReedCRM
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once TCPDF_PATH . 'tcpdf_barcodes_2d.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

// Load Module Libraries
require_once __DIR__ . '/../lib/reedcrm.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$action = GETPOST('action', 'alpha');

// Initialize view objects
$form = new Form($db);

// Security check - Protection if external user
$permissionToRead = $user->rights->reedcrm->adminpage->read;
saturne_check_access($permissionToRead);

/*
 * Actions
 */

$startUrl = dol_buildpath('custom/reedcrm/view/frontend/quickcreation.php?source=pwa', 3);

if ($action == 'generate_QRCode') {
    $urlToEncode = GETPOST('urlToEncode');
    if (empty($urlToEncode)) {
        $urlToEncode = $startUrl;
    }

    $barcode = new TCPDF2DBarcode($urlToEncode, 'QRCODE,L');

    dol_mkdir($conf->reedcrm->multidir_output[$conf->entity] . '/pwa/qrcode/');
    $file = $conf->reedcrm->multidir_output[$conf->entity] . '/pwa/qrcode/' . 'barcode_' . dol_print_date(dol_now(), 'dayhourlog') . '.png';

    $imageData = $barcode->getBarcodePngData();
    $imageData = imagecreatefromstring($imageData);
    imagepng($imageData, $file);

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title   = $langs->trans('ModuleSetup', 'ReedCRM');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

// Subheader
$linkBack = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1' . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'reedcrm_color@reedcrm');

// Configuration header
$head = reedcrm_admin_prepare_head();
print dol_get_fiche_head($head, 'pwa_reedcrm', $title, -1, 'reedcrm_color@reedcrm');

print '<a class="marginrightonly" href="' . $startUrl . '" target="_blank">' . img_picto('', 'url', 'class="pictofixedwidth"') . $langs->trans('PWA') . '</a>';
print showValueWithClipboardCPButton($startUrl, 0, 'none');

// PWA QR Code generation
print load_fiche_titre($langs->transnoentities('PWAQRCodeGenerationManagement'), '', '');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="generate_QRCode">';
print '<input hidden name="urlToEncode" value="' . $startUrl . '">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Value') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>' . $langs->trans('GeneratePWAQRCode') . '</td>';
print '<td>' . $langs->trans('GeneratePWAQRCodeDescription') . '</td>';
print '<td class="center">' . saturne_show_medias_linked('reedcrm', $conf->reedcrm->multidir_output[$conf->entity] . '/pwa/qrcode/', 'small', 1, 0, 0, 0, 80, 80, 0, 0, 0, 'pwa/qrcode/', null, '', 0, 0, 0, 0, 'center') . '</td></tr>';

print '</table>';
print $form->buttonsSaveCancel('Generate', '');
print '</form>';

// Configurations
print '<br>';
print load_fiche_titre($langs->trans('Config'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '</tr>';

// App close project when probability zero
print '<tr class="oddeven"><td>';
print $langs->trans('AppCloseProjectOpportunityZero');
print '</td><td>';
print $langs->trans('AppCloseProjectOpportunityZeroDescription');
print '</td><td class="center">';
print ajax_constantonoff('REEDCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO');
print '</td></tr>';

// Create ActionComm on call list call button
print '<tr class="oddeven"><td>';
print $langs->transnoentities('CallListCreateActioncomm');
print '</td><td>';
print $langs->transnoentities('CallListCreateActioncommDesc');
print '</td><td class="center">';
print ajax_constantonoff('REEDCRM_CALL_LIST_CREATE_ACTIONCOMM');
print '</td></tr>';

print '</table>';

// Piece display configuration
print '<br>';
print load_fiche_titre($langs->trans('PwaShowPiecesTitle'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '</tr>';

$pieces = [
    'REEDCRM_PWA_SHOW_OPP_AMOUNT',
    'REEDCRM_PWA_SHOW_PROPAL',
    'REEDCRM_PWA_SHOW_COMMANDE',
    'REEDCRM_PWA_SHOW_COMMANDE_FOURN',
    'REEDCRM_PWA_SHOW_RECEPTION',
    'REEDCRM_PWA_SHOW_FACTURE_FOURN',
    'REEDCRM_PWA_SHOW_EXPEDITION',
    'REEDCRM_PWA_SHOW_FACTURE',
    'REEDCRM_PWA_SHOW_PAYMENT'
];

foreach ($pieces as $piece) {
    print '<tr class="oddeven"><td>';
    print $langs->transnoentities($piece);
    print '</td><td>';
    print $langs->transnoentities($piece . '_Desc');
    print '</td><td class="center">';
    print ajax_constantonoff($piece);
    print '</td></tr>';
}

print '</table>';

// Display option: icons-only pieces bar
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>' . $langs->trans('Parameters') . '</td><td>' . $langs->trans('Description') . '</td><td class="center">' . $langs->trans('Status') . '</td></tr>';
print '<tr class="oddeven"><td>' . $langs->transnoentities('REEDCRM_PWA_PIECES_ICONS_ONLY') . '</td><td>' . $langs->transnoentities('REEDCRM_PWA_PIECES_ICONS_ONLY_Desc') . '</td><td class="center">' . ajax_constantonoff('REEDCRM_PWA_PIECES_ICONS_ONLY') . '</td></tr>';
print '</table>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
