<?php
/**
 * \file    view/frontend/pwa_projets.php
 * \ingroup reedcrm
 * \brief   Page to list Projects/Opportunities on frontend App view
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

saturne_load_langs(['projects', 'users', 'companies', 'main']);

$title    = $langs->trans('Projects');
$help_url = 'FR:Module_ReedCRM';
$moreJS   = [
    '/custom/saturne/js/saturne.min.js',
    '/custom/reedcrm/js/reedcrm.min.js'
];
$moreCSS  = ['/custom/saturne/css/saturne.min.css', '/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;
$conf->global->MAIN_FAVICON_URL = DOL_URL_ROOT . '/custom/reedcrm/img/reedcrm_color_512.png';

$action = GETPOST('action', 'aZ09');
if (!empty($action)) {
    require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
    $project = new Project($db);
    $task    = new Task($db);
    $extraFields = new ExtraFields($db);
    $extraFields->fetch_name_optionals_label($project->table_element);
    $permissionToAddProject = $user->rights->projet->creer;
    require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_quickcreation_actions_frontend.tpl.php';
}

llxHeader('', $title, $help_url, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-projets-list');

print '<style>
.gmail-pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    color: #475569 !important;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
    cursor: pointer;
}
.gmail-pagination-btn:hover {
    background-color: #e2e8f0;
    color: #1e293b !important;
}
.gmail-pagination-btn.disabled {
    color: #cbd5e0 !important;
    cursor: not-allowed;
    background: transparent;
}
</style>';

if (!$user->rights->projet->lire) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

// 1. Get Search & Pagination params
$searchStr = trim(GETPOST('s', 'alpha'));
$page = GETPOST('page', 'int');
if (empty($page)) $page = 0;

$limit = getDolGlobalInt('REEDCRM_NB_LATEST_OPPORTUNITIES_FRONTEND', 15);
if ($limit <= 0) $limit = 15;
$offset = $limit * $page;

// 2. Build Filters
$filter = [];
if (!empty($searchStr)) {
    $searchEscaped = $db->escape($searchStr);
    $customSql = "(t.ref LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR t.title LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR t.description LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.reedcrm_firstname LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.reedcrm_lastname LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.projectphone LIKE '%" . $searchEscaped . "%' ";
    $customSql .= " OR eft.reedcrm_email LIKE '%" . $searchEscaped . "%' ";
    $customSql .= ")";
    $filter['customsql'] = $customSql;
}

// 3. Fetch Total Count & Data
$moreparamsCount = ['count' => true];
$totalProjects = saturne_fetch_all_object_type('Project', 'DESC', 't.datec', 0, 0, $filter, 'AND', true, true, false, '', $moreparamsCount);
if (!is_numeric($totalProjects) || $totalProjects < 0) $totalProjects = 0;

$latestProjects = saturne_fetch_all_object_type('Project', 'DESC', 't.datec', $limit, $offset, $filter, 'AND', true, true, false, '', [], ', t.description, t.note_public, t.note_private', ['description', 'note_public', 'note_private']);
if (empty($latestProjects) || !is_array($latestProjects)) {
    $latestProjects = [];
}
$nbResults = count($latestProjects);

// --- SEARCH BAR HTML FOR HEADER ---
$searchHtml = '
  <form method="GET" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="display: flex; gap: 6px; justify-content: center; margin-left: 10px; flex: 1; max-width: 400px;">
    <div style="position: relative; flex: 1; min-width: 40px;">
      <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85em;"></i>
      <input type="text" name="s" value="' . dol_escape_htmltag($searchStr) . '" style="width: 100%; padding: 6px 10px 6px 28px; border: 1px solid #cbd5e0; border-radius: 20px; font-size: 14px; outline: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); transition: border-color 0.2s;">
    </div>
    <button type="submit" style="background: #3b82f6; color: white; border: none; border-radius: 20px; padding: 0 12px; cursor: pointer; box-shadow: 0 2px 4px rgba(59,130,246,0.3);"><i class="fas fa-arrow-right"></i></button>';
if (!empty($searchStr)) {
    $searchHtml .= '<a href="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="display: flex; align-items: center; justify-content: center; background: #e2e8f0; color: #64748b; border: none; border-radius: 20px; padding: 0 10px; text-decoration: none;"><i class="fas fa-times"></i></a>';
}
$searchHtml .= '  </form>';

$startItem = $totalProjects > 0 ? $offset + 1 : 0;
$endItem = min($offset + $nbResults, $totalProjects);

$paginationHtml = '';
$totalPages = ceil($totalProjects / $limit);
if ($totalPages > 1) {
    $paginationHtml .= '<div class="gmail-pagination" style="display: flex; align-items: center; gap: 4px; font-size: 13px; color: #475569; font-weight: 500; margin-left: 10px; white-space: nowrap;">';
    $paginationHtml .= '<span>' . $startItem . '-' . $endItem . ' sur ' . $totalProjects . '</span>';
    
    // Prev Button
    if ($page > 0) {
        $prevUrl = $_SERVER['PHP_SELF'] . '?page=' . ($page - 1) . (!empty($searchStr) ? '&s=' . urlencode($searchStr) : '');
        $paginationHtml .= '<a href="' . $prevUrl . '" class="gmail-pagination-btn"><i class="fas fa-chevron-left"></i></a>';
    } else {
        $paginationHtml .= '<span class="gmail-pagination-btn disabled"><i class="fas fa-chevron-left"></i></span>';
    }
    
    // Next Button
    if ($page < ($totalPages - 1)) {
        $nextUrl = $_SERVER['PHP_SELF'] . '?page=' . ($page + 1) . (!empty($searchStr) ? '&s=' . urlencode($searchStr) : '');
        $paginationHtml .= '<a href="' . $nextUrl . '" class="gmail-pagination-btn"><i class="fas fa-chevron-right"></i></a>';
    } else {
        $paginationHtml .= '<span class="gmail-pagination-btn disabled"><i class="fas fa-chevron-right"></i></span>';
    }
    $paginationHtml .= '</div>';
}

require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/lib/reedcrm.lib.php';
$selfMatrix = dol_buildpath('/custom/reedcrm/view/frontend/pwa_projets_overview.php', 1);
$qsToggle   = (!empty($searchStr) ? '&s=' . urlencode($searchStr) : '') . ($page > 0 ? '&page=' . (int) $page : '');
$toggleHtml = '<a href="' . $selfMatrix . '?source=pwa' . $qsToggle . '" class="pwa-matrix-toggle"><i class="fas fa-th"></i><span>' . $langs->trans('ViewAsMatrix') . '</span></a>';

$pwaHeaderCenterHtml = '<div style="display: flex; align-items: center; width: 100%; justify-content: center; gap: 8px;">';
$pwaHeaderCenterHtml .=    $searchHtml;
$pwaHeaderCenterHtml .=    $paginationHtml;
$pwaHeaderCenterHtml .=    $toggleHtml;
$pwaHeaderCenterHtml .= '</div>';

print reedcrm_chain_matrix_styles();

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

print '<div class="pwa-container" style="padding: 15px; max-width: 1000px; margin: 0 auto;">';




// --- LIST INCLUSION ---
if (!empty($latestProjects)) {
    require __DIR__ . '/../../core/tpl/frontend/reedcrm_opportunities_list_frontend.tpl.php';
} else {
    print '<div style="text-align: center; padding: 40px 20px; color: #94a3b8;">';
    print '  <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; color: #cbd5e0;"></i>';
    print '  <div style="font-size: 18px;">' . $langs->trans('NoRecordFound') . '</div>';
    print '</div>';
}



print '</div>';

// Include the Bottom Navigation Bar for App
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

// Include Saturne Photo Editor Modal (Required for photo uploads)
include dol_buildpath('/saturne/core/tpl/medias/photo_editor_modal.tpl.php');

llxFooter();
$db->close();
