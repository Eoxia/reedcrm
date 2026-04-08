<?php
/**
 * \file    view/frontend/pwa_home.php
 * \ingroup reedcrm
 * \brief   App Dashboard / Home
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

global $conf, $db, $hookmanager, $langs, $user;

$title    = $langs->trans('Home');
$help_url = 'FR:Module_ReedCRM';
$moreJS   = ['/custom/saturne/js/saturne.min.js', '/custom/reedcrm/js/reedcrm.min.js'];
$moreCSS  = ['/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, $help_url, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-home');

// Define specific indicators for this page (optional)
$pwaHeaderCenterHtml = '<div style="background: #e2e8f0; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: bold; color: #475569;"><i class="fas fa-home"></i> Accueil</div>';
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

print '<div class="pwa-container" style="padding: 15px;">';
print '  <h2><i class="fas fa-home" style="color: #64748b;"></i> Accueil App</h2>';
print '</div>';

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_home_graphs.tpl.php';

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();
