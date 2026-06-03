<?php
/**
 * \file    view/frontend/pwa_projets_overview.php
 * \ingroup reedcrm
 * \brief   PWA matrix overview of the opportunity->payment chain across projects.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

global $conf, $db, $hookmanager, $langs, $user;

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/lib/reedcrm.lib.php';

saturne_load_langs(['projects', 'users', 'companies', 'main']);

$title    = $langs->trans('ProjectsOverviewMatrix');
$help_url = 'FR:Module_ReedCRM';
$moreJS   = [
    '/custom/saturne/js/saturne.min.js',
    '/custom/reedcrm/js/reedcrm.min.js'
];
$moreCSS  = ['/custom/saturne/css/saturne.min.css', '/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu         = 1;
$conf->dol_hide_leftmenu        = 1;
$conf->global->MAIN_FAVICON_URL = DOL_URL_ROOT . '/custom/reedcrm/img/reedcrm_color_512.png';

llxHeader('', $title, $help_url, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-projets-overview');

if (!$user->rights->projet->lire) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

// 1. Search & pagination params (same contract as pwa_projets.php).
$searchStr = trim(GETPOST('s', 'alpha'));
$page = GETPOST('page', 'int');
if (empty($page)) $page = 0;

$limit = getDolGlobalInt('REEDCRM_NB_LATEST_OPPORTUNITIES_FRONTEND', 15);
if ($limit <= 0) $limit = 15;
$offset = $limit * $page;

// 2. Filters (same as pwa_projets.php).
$filter = [];
if (!empty($searchStr)) {
    $searchEscaped = $db->escape($searchStr);
    $customSql  = "(t.ref LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR t.title LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR t.description LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.reedcrm_firstname LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.reedcrm_lastname LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.projectphone LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.reedcrm_email LIKE '%" . $searchEscaped . "%' ";
    $customSql .= ")";
    $filter['customsql'] = $customSql;
}

// 3. Fetch total + current page.
$moreparamsCount = ['count' => true];
$totalProjects = saturne_fetch_all_object_type('Project', 'DESC', 't.datec', 0, 0, $filter, 'AND', true, true, false, '', $moreparamsCount);
if (!is_numeric($totalProjects) || $totalProjects < 0) $totalProjects = 0;

$matrixProjects = saturne_fetch_all_object_type('Project', 'DESC', 't.datec', $limit, $offset, $filter, 'AND', true, true, false, '', [], ', t.description', ['description']);
if (empty($matrixProjects) || !is_array($matrixProjects)) {
    $matrixProjects = [];
}
$nbResults = count($matrixProjects);

$pwaProjectIds = [];
foreach ($matrixProjects as $proj) {
    $pwaProjectIds[] = $proj->id;
}
$matrixDocs = reedcrm_get_pwa_projects_documents($pwaProjectIds, true);

// --- SEARCH BAR + VIEW TOGGLE for the header ---
$selfList   = dol_buildpath('/custom/reedcrm/view/frontend/pwa_projets.php', 1);
$qs         = (!empty($searchStr) ? '&s=' . urlencode($searchStr) : '') . ($page > 0 ? '&page=' . (int) $page : '');
$toggleHtml = '<a href="' . $selfList . '?source=pwa' . $qs . '" class="pwa-matrix-toggle"><i class="fas fa-list"></i><span>' . $langs->trans('ViewAsList') . '</span></a>';

$searchHtml = '
  <form method="GET" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="display: flex; gap: 6px; justify-content: center; margin-left: 10px; flex: 1; max-width: 360px;">
    <div style="position: relative; flex: 1; min-width: 40px;">
      <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85em;"></i>
      <input type="text" name="s" value="' . dol_escape_htmltag($searchStr) . '" style="width: 100%; padding: 6px 10px 6px 28px; border: 1px solid #cbd5e0; border-radius: 20px; font-size: 14px; outline: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
    </div>
    <button type="submit" style="background: #3b82f6; color: white; border: none; border-radius: 20px; padding: 0 12px; cursor: pointer;"><i class="fas fa-arrow-right"></i></button>';
if (!empty($searchStr)) {
    $searchHtml .= '<a href="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="display: flex; align-items: center; justify-content: center; background: #e2e8f0; color: #64748b; border-radius: 20px; padding: 0 10px; text-decoration: none;"><i class="fas fa-times"></i></a>';
}
$searchHtml .= '  </form>';

$pwaHeaderCenterHtml  = '<div style="display: flex; align-items: center; width: 100%; justify-content: center; gap: 8px;">';
$pwaHeaderCenterHtml .=    $searchHtml;
$pwaHeaderCenterHtml .=    $toggleHtml;
$pwaHeaderCenterHtml .= '</div>';

print reedcrm_chain_matrix_styles();

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

print '<div class="pwa-container" style="padding: 15px; max-width: 1200px; margin: 0 auto;">';

if (!empty($matrixProjects)) {
    require __DIR__ . '/../../core/tpl/frontend/reedcrm_opportunity_chain_matrix.tpl.php';
} else {
    print '<div style="text-align: center; padding: 40px 20px; color: #94a3b8;">';
    print '  <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; color: #cbd5e0;"></i>';
    print '  <div style="font-size: 18px;">' . $langs->trans('NoRecordFound') . '</div>';
    print '</div>';
}

print '</div>';

// Include the Bottom Navigation Bar for App
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();
