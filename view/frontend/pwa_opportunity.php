<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    view/frontend/pwa_opportunity.php
 * \ingroup reedcrm
 * \brief   Mobile App view of ONE opportunity: summary, person to call, event timeline and relaunch bar.
 *          Reached from the App call list; the relaunch shortcuts lead to pwa_relaunch.php.
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
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';
require_once __DIR__ . '/../../class/calllist.class.php';
require_once __DIR__ . '/../../class/calllistline.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['projects', 'agenda', 'companies']);

// Get parameters
$id         = GETPOSTINT('from_id') ?: GETPOSTINT('id');
$fromType   = GETPOST('from_type', 'aZ09') ?: 'project';
$callListId = GETPOSTINT('call_list_id');

// Initialize view objects
$form = new Form($db);

// Permissions
$permissionToRead = $user->hasRight('reedcrm', 'eventpro', 'read');
saturne_check_access($permissionToRead);

// Only opportunities (projects) are exposed in the App for now; the desktop card handles the other types
if ($fromType !== 'project' || !isModEnabled('project')) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

$project = new Project($db);
if ($id <= 0 || $project->fetch($id) <= 0) {
    accessforbidden($langs->trans('RecordNotFound'), 0);
    exit;
}
$project->fetch_optionals();

if (!$user->hasRight('projet', 'lire')) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

$oppContact = reedcrm_get_project_contact_details($project);

// Timeline: every event of the opportunity, newest first (socid left empty so events not carrying the
// thirdparty are listed too). The relaunch counters below use the narrower tagged-events rule instead.
$actionComm = new ActionComm($db);
$oppEvents  = $actionComm->getActions(0, $project->id, 'project', '', 'a.datep', 'DESC');
if (!is_array($oppEvents)) {
    $oppEvents = [];
}

$oppRelaunchCounts = [];
foreach (array_keys(reedcrm_get_relaunch_types()) as $typeKey) {
    $oppRelaunchCounts[$typeKey] = 0;
}
$relaunchTagId = getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG');
if ($relaunchTagId > 0) {
    $relaunchFilter   = ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $relaunchTagId . ')';
    $relaunchedEvents = $actionComm->getActions((int) $project->socid, $project->id, 'project', $relaunchFilter, 'a.datec');
    if (is_array($relaunchedEvents)) {
        foreach ($relaunchedEvents as $relaunchedEvent) {
            $oppRelaunchCounts[reedcrm_get_relaunch_type_key((string) $relaunchedEvent->type_code)]++;
        }
    }
}

/*
 * View
 */

$title   = $project->ref . ' - ' . $project->title;
$helpUrl = 'FR:Module_ReedCRM';
$moreJS  = ['/custom/saturne/js/saturne.min.js', '/custom/reedcrm/js/reedcrm.min.js'];
$moreCSS = ['/custom/saturne/css/saturne.min.css', '/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, $helpUrl, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-opportunity');

// Coming from a call list: keep its indicator in the header and offer the way back
$pwaHeaderCenterHtml = '';
if ($callListId > 0) {
    $callList = new CallList($db);
    if ($callList->fetch($callListId) > 0) {
        $callListLine  = new CallListLine($db);
        $callListLines = $callListLine->fetchAllByCallList($callList->id);
        $toCallCount   = 0;
        if (is_array($callListLines)) {
            foreach ($callListLines as $callListLineToCount) {
                if ((int) $callListLineToCount->status === CallListLine::STATUS_TO_CALL) {
                    $toCallCount++;
                }
            }
        }
        $pwaHeaderCenterHtml  = '<a class="pwa-opp-back" href="' . dol_escape_htmltag(dol_buildpath('/custom/reedcrm/view/frontend/pwa_call_list.php', 1) . '?id=' . (int) $callList->id) . '">';
        $pwaHeaderCenterHtml .= '<i class="fas fa-arrow-left"></i> <i class="fas fa-phone"></i> ' . dol_escape_htmltag($callList->label);
        $pwaHeaderCenterHtml .= ' — ' . $toCallCount . '/' . (is_array($callListLines) ? count($callListLines) : 0) . ' ' . $langs->trans('ToCallShort');
        $pwaHeaderCenterHtml .= '</a>';
    }
}
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

print '<div class="pwa-opportunity-container">';

require __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_opportunity_head.tpl.php';
require __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_opportunity_timeline.tpl.php';

print '</div>';

require __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_relaunch_bar.tpl.php';

// End of page
llxFooter();
$db->close();
